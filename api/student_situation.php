<?php
/**
 * API — Situation financière d'un élève (AJAX)
 * GET /api/student_situation.php?eleve_id=X
 */
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

$user = requireLogin();
$eleveId = (int)($_GET['eleve_id'] ?? 0);
if (!$eleveId) { echo json_encode(['error' => 'eleve_id requis']); exit; }

$eleve = getEleveById($eleveId);
if (!$eleve) { echo json_encode(['error' => 'Élève introuvable']); exit; }

$sit = getSituationFinanciere($eleveId);

// Format tranches for JS
$tranches = [];
foreach (($sit['tranches'] ?? []) as $t) {
    $restant = max(0, (float)$t['montant'] - (float)$t['paye']);
    $isPaid  = $restant <= 0;
    $tranches[] = [
        'id'      => (int)$t['id'],
        'label'   => $t['libelle_fr'] . ($isPaid ? ' ✓ Soldée' : ' — Reste : ' . number_format($restant, 0, ',', ' ') . ' FCFA'),
        'restant' => $restant,
        'montant' => (float)$t['montant'],
        'paye'    => (float)$t['paye'],
        'is_paid' => $isPaid,
    ];
}

echo json_encode([
    'eleve' => [
        'id'        => $eleve['id'],
        'nom'       => $eleve['nom'],
        'prenoms'   => $eleve['prenoms'],
        'matricule' => $eleve['matricule'],
        'classe'    => $eleve['niveau_nom'],
        'annee'     => $eleve['annee_libelle'],
    ],
    'situation' => [
        'totalDu'                   => (float)($sit['totalDu'] ?? 0),
        'totalBrut'                 => (float)($sit['totalBrut'] ?? 0),
        'paye'                      => (float)($sit['paye'] ?? 0),
        'reste'                     => (float)($sit['reste'] ?? 0),
        'statut'                    => $sit['statut'] ?? 'impaye',
        'totalAvecReductionComplete'=> (float)($sit['totalAvecReductionComplete'] ?? 0),
        'tauxReductionComplet'      => (float)($sit['tauxReductionComplet'] ?? 0),
    ],
    'tranches' => $tranches,
]);
