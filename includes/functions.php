<?php
/**
 * Core utility functions
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function auditLog(int $userId, string $action, string $entite = '', int $entiteId = 0, string $details = ''): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $db->prepare("INSERT INTO journal_audit (utilisateur_id, action, entite, entite_id, details, ip_address)
                              VALUES (?,?,?,?,?,?)");
        $stmt->execute([$userId, $action, $entite ?: null, $entiteId ?: null, $details ?: null, $ip]);
    } catch (Exception $e) { /* silent */ }
}

function generateMatricule(int $anneeId): string {
    $db = getDB();
    $annee = $db->prepare("SELECT libelle FROM annees_scolaires WHERE id=?");
    $annee->execute([$anneeId]);
    $row = $annee->fetch();
    $suffix = str_replace('-', '', $row['libelle'] ?? '20262027');
    $num = $db->query("SELECT nextval('seq_matricule')")->fetchColumn();
    return 'SIN-' . substr($suffix, 2) . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function generateNumeroRecu(): int {
    $db = getDB();
    return (int) $db->query("SELECT nextval('seq_numero_recu')")->fetchColumn();
}

function getActiveYear(): array {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM annees_scolaires WHERE statut='active' ORDER BY id DESC LIMIT 1");
    return $stmt->fetch() ?: ['id' => 0, 'libelle' => '2026-2027'];
}

function getAllYears(): array {
    return getDB()->query("SELECT * FROM annees_scolaires ORDER BY id DESC")->fetchAll();
}

function getNiveaux(): array {
    return getDB()->query("SELECT * FROM niveaux WHERE actif=TRUE ORDER BY ordre")->fetchAll();
}

function getEleveById(int $id): ?array {
    $stmt = getDB()->prepare("
        SELECT e.*, n.nom_fr AS niveau_nom, n.nom_en AS niveau_nom_en, n.section,
               a.libelle AS annee_libelle
        FROM eleves e
        JOIN niveaux n ON n.id = e.niveau_id
        JOIN annees_scolaires a ON a.id = e.annee_id
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getParametre(string $cle, string $defaut = ''): string {
    static $cache = [];
    if (!isset($cache[$cle])) {
        try {
            $stmt = getDB()->prepare("SELECT valeur FROM parametres WHERE cle=?");
            $stmt->execute([$cle]);
            $val = $stmt->fetchColumn();
            $cache[$cle] = $val !== false ? $val : $defaut;
        } catch (Exception $e) {
            $cache[$cle] = $defaut;
        }
    }
    return $cache[$cle];
}

function getSituationFinanciere(int $eleveId): array {
    $db = getDB();

    // Get student's year and level
    $eleve = $db->prepare("SELECT e.annee_id, e.niveau_id, e.famille_id FROM eleves e WHERE e.id=?");
    $eleve->execute([$eleveId]);
    $info = $eleve->fetch();
    if (!$info) return [];

    // Get fee grid
    $gridStmt = $db->prepare("SELECT g.id, g.frais_inscription FROM grille_frais g WHERE g.annee_id=? AND g.niveau_id=?");
    $gridStmt->execute([$info['annee_id'], $info['niveau_id']]);
    $grid = $gridStmt->fetch();
    if (!$grid) return ['total_du' => 0, 'total_paye' => 0, 'reste' => 0, 'statut' => 'impaye', 'tranches' => []];

    // Get tranches
    $tranchesStmt = $db->prepare("SELECT t.*, COALESCE(SUM(p.montant) FILTER (WHERE p.annule=FALSE), 0) AS paye
        FROM tranches t
        LEFT JOIN paiements p ON p.tranche_id = t.id AND p.eleve_id = ?
        WHERE t.grille_id = ?
        GROUP BY t.id ORDER BY t.numero");
    $tranchesStmt->execute([$eleveId, $grid['id']]);
    $tranches = $tranchesStmt->fetchAll();

    // Get annexes
    $annexesStmt = $db->prepare("
        SELECT fac.montant, COALESCE(SUM(p.montant) FILTER (WHERE p.annule=FALSE), 0) AS paye
        FROM frais_annexes_eleve fae
        JOIN frais_annexes_config fac ON fac.id = fae.frais_annexe_id
        LEFT JOIN paiements p ON p.eleve_id = ? AND p.type_paiement = 'annexe'
        WHERE fae.eleve_id = ? AND fae.active = TRUE
        GROUP BY fac.id, fac.montant
    ");
    $annexesStmt->execute([$eleveId, $eleveId]);
    $annexes = $annexesStmt->fetchAll();

    // Calculate reductions
    $reductionFratrie = 0;
    if ($info['famille_id']) {
        $siblings = $db->prepare("SELECT COUNT(*) FROM eleves WHERE famille_id=? AND id!=? AND annee_id=? AND actif=TRUE");
        $siblings->execute([$info['famille_id'], $eleveId, $info['annee_id']]);
        $nbSiblings = (int)$siblings->fetchColumn();
        $reductionFratrie = $nbSiblings * (float)getParametre('reduction_fratrie', (string)REDUCTION_FRATRIE);
    }

    $totalTranches = array_sum(array_column($tranches, 'montant'));
    $totalAnnexes  = array_sum(array_column($annexes, 'montant'));
    $fraisInscription = (float)($grid['frais_inscription'] ?? 0);
    $totalBrut = $totalTranches + $totalAnnexes + $fraisInscription;

    // Check if paid in full (single payment)
    $fullPayment = $db->prepare("SELECT COUNT(*) FROM paiements WHERE eleve_id=? AND type_paiement='solde_complet' AND annule=FALSE");
    $fullPayment->execute([$eleveId]);
    $isFullPayment = (int)$fullPayment->fetchColumn() > 0;
    $reductionComplet = $isFullPayment ? (float)getParametre('reduction_paiement_complet', (string)REDUCTION_PAIEMENT_COMPLET) : 0;

    $tauxReduction = min(($reductionFratrie + $reductionComplet), 20); // cap at 20%
    $montantReduction = $totalBrut * $tauxReduction / 100;
    $totalDu = $totalBrut - $montantReduction;

    $totalPaye = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE eleve_id=? AND annule=FALSE");
    $totalPaye->execute([$eleveId]);
    $paye = (float)$totalPaye->fetchColumn();
    $reste = $totalDu - $paye;

    $statut = 'impaye';
    if ($paye >= $totalDu) $statut = 'paye';
    elseif ($paye > 0) $statut = 'partiel';

    return compact('totalDu', 'totalBrut', 'paye', 'reste', 'statut', 'tranches',
                   'tauxReduction', 'montantReduction', 'reductionFratrie', 'reductionComplet', 'fraisInscription');
}

function formatMontant(float $amount): string {
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}
