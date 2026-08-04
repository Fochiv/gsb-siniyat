<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireAdmin();
$db   = getDB();
$pageTitle = 'Tous les paiements';
$activeYear = getActiveYear();
$allYears   = getAllYears();

// Filters
$yearId    = (int)($_GET['annee']   ?? $activeYear['id']);
$dateFrom  = $_GET['date_from'] ?? '';
$dateTo    = $_GET['date_to']   ?? '';
$agentId   = (int)($_GET['agent']   ?? 0);
$mode      = $_GET['mode']      ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 30;

// Build WHERE clause
$where  = ['e.annee_id = ?', 'p.annule = FALSE'];
$params = [$yearId];
if ($dateFrom) { $where[] = 'DATE(p.date_paiement) >= ?'; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = 'DATE(p.date_paiement) <= ?'; $params[] = $dateTo;   }
if ($agentId)  { $where[] = 'p.encaisse_par = ?';         $params[] = $agentId;  }
if ($mode)     { $where[] = 'p.mode_paiement = ?';        $params[] = $mode;     }
$whereStr = implode(' AND ', $where);

// Total count
$countStmt = $db->prepare("SELECT COUNT(*) FROM paiements p JOIN eleves e ON e.id=p.eleve_id WHERE $whereStr");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$offset = ($page - 1) * $perPage;

// Payments
$stmt = $db->prepare("
    SELECT p.*, e.nom, e.prenoms, e.matricule,
           n.nom_fr AS classe, n.section,
           CONCAT(u.prenom,' ',u.nom) AS agent_nom,
           r.numero_recu
    FROM paiements p
    JOIN eleves e ON e.id = p.eleve_id
    JOIN niveaux n ON n.id = e.niveau_id
    LEFT JOIN utilisateurs u ON u.id = p.encaisse_par
    LEFT JOIN recus r ON r.paiement_id = p.id
    WHERE $whereStr
    ORDER BY p.date_paiement DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([...$params, $perPage, $offset]);
$payments = $stmt->fetchAll();

// Totals for filter
$totStmt = $db->prepare("SELECT COUNT(*) AS nb, COALESCE(SUM(p.montant),0) AS total FROM paiements p JOIN eleves e ON e.id=p.eleve_id WHERE $whereStr");
$totStmt->execute($params);
$totals = $totStmt->fetch();

// Daily summary (today)
$todayStmt = $db->prepare("
    SELECT CONCAT(u.prenom,' ',u.nom) AS agent, COUNT(*) AS nb, SUM(p.montant) AS total
    FROM paiements p
    JOIN eleves e ON e.id=p.eleve_id
    LEFT JOIN utilisateurs u ON u.id=p.encaisse_par
    WHERE e.annee_id=? AND p.annule=FALSE AND DATE(p.date_paiement)=CURRENT_DATE
    GROUP BY u.id, u.prenom, u.nom
    ORDER BY total DESC
");
$todayStmt->execute([$yearId]);
$todaySummary = $todayStmt->fetchAll();

// Agent list for filter
$agents = $db->query("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet FROM utilisateurs WHERE actif=TRUE ORDER BY nom")->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="fade-in-load">

<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
    <h1 class="page-title mb-0">
        <i class="bi bi-receipt-cutoff"></i> Tous les paiements
    </h1>
    <a href="/api/export.php?type=payments&annee_id=<?= $yearId ?>&format=excel"
       class="btn btn-sm btn-outline-siniyat">
        <i class="bi bi-file-earmark-excel me-1"></i>Exporter Excel
    </a>
</div>

<!-- Today's summary -->
<?php if ($todaySummary): ?>
<div class="card mb-3 border-success">
    <div class="card-header" style="background:#d1fae5;">
        <i class="bi bi-sun me-2 text-success"></i>
        <strong class="text-success">Encaissements d'aujourd'hui</strong>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th>Agent</th><th>Nb paiements</th><th>Total encaissé</th></tr></thead>
            <tbody>
                <?php foreach ($todaySummary as $t): ?>
                <tr>
                    <td><i class="bi bi-person me-1 text-muted"></i><?= e($t['agent'] ?? '—') ?></td>
                    <td><span class="badge bg-secondary"><?= $t['nb'] ?></span></td>
                    <td class="fw-bold text-success"><?= formatMontant((float)$t['total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">Année</label>
                <select name="annee" class="form-select form-select-sm" style="width:130px;">
                    <?php foreach ($allYears as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= $y['id']==$yearId?'selected':'' ?>>
                        <?= e($y['libelle']) ?><?= $y['statut']==='active'?' ★':'' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Du</label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="<?= e($dateFrom) ?>" style="width:140px;">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Au</label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?= e($dateTo) ?>" style="width:140px;">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Agent</label>
                <select name="agent" class="form-select form-select-sm" style="width:160px;">
                    <option value="">Tous</option>
                    <?php foreach ($agents as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $a['id']==$agentId?'selected':'' ?>><?= e($a['nom_complet']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Mode</label>
                <select name="mode" class="form-select form-select-sm" style="width:130px;">
                    <option value="">Tous</option>
                    <option value="especes" <?= $mode==='especes'?'selected':'' ?>>Espèces</option>
                    <option value="virement" <?= $mode==='virement'?'selected':'' ?>>Virement</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary-siniyat btn-sm">
                    <i class="bi bi-filter me-1"></i>Filtrer
                </button>
                <a href="/admin/payments.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Summary bar -->
<div class="row g-2 mb-3">
    <div class="col-auto">
        <div class="card stat-card stat-success px-3 py-2 d-flex flex-row align-items-center gap-3">
            <div>
                <div class="text-muted small">Paiements trouvés</div>
                <div class="fw-bold fs-5"><?= number_format($totalCount) ?></div>
            </div>
            <i class="bi bi-receipt text-success" style="font-size:1.75rem;opacity:.25;"></i>
        </div>
    </div>
    <div class="col-auto">
        <div class="card stat-card stat-success px-3 py-2 d-flex flex-row align-items-center gap-3">
            <div>
                <div class="text-muted small">Total encaissé</div>
                <div class="fw-bold text-success"><?= formatMontant((float)$totals['total']) ?></div>
            </div>
            <i class="bi bi-cash-coin text-success" style="font-size:1.75rem;opacity:.25;"></i>
        </div>
    </div>
</div>

<!-- Payments table -->
<div class="card">
    <div class="card-header bg-primary-siniyat text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2"></i>Liste des paiements</span>
        <small class="opacity-75">Page <?= $page ?>/<?= $totalPages ?></small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Reçu</th>
                        <th>Matricule</th>
                        <th>Élève</th>
                        <th>Classe</th>
                        <th>Montant</th>
                        <th>Mode</th>
                        <th>Agent</th>
                        <th>Date &amp; Heure</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td data-label="Reçu">
                            <span class="badge bg-secondary">#<?= e($p['numero_recu'] ?? '—') ?></span>
                        </td>
                        <td data-label="Matricule"><code><?= e($p['matricule']) ?></code></td>
                        <td data-label="Élève"><strong><?= e($p['nom'].' '.$p['prenoms']) ?></strong></td>
                        <td data-label="Classe">
                            <?= e($p['classe']) ?>
                            <span class="badge <?= $p['section']==='anglophone'?'badge-anglophone':'badge-francophone' ?> ms-1" style="font-size:.65rem;">
                                <?= $p['section']==='anglophone'?'EN':'FR' ?>
                            </span>
                        </td>
                        <td data-label="Montant" class="fw-semibold text-success"><?= formatMontant((float)$p['montant']) ?></td>
                        <td data-label="Mode">
                            <?php if ($p['mode_paiement']==='especes'): ?>
                            <span class="badge bg-success"><i class="bi bi-cash me-1"></i>Espèces</span>
                            <?php else: ?>
                            <span class="badge bg-info text-dark"><i class="bi bi-bank me-1"></i>Virement</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Agent" class="text-muted small"><?= e($p['agent_nom'] ?? '—') ?></td>
                        <td data-label="Date" class="text-muted small"><?= date('d/m/Y H:i', strtotime($p['date_paiement'])) ?></td>
                        <td>
                            <?php if ($p['numero_recu']): ?>
                            <a href="/pdf/receipt.php?paiement_id=<?= $p['id'] ?>" target="_blank"
                               class="btn btn-sm btn-outline-secondary btn-action" title="Imprimer reçu">
                                <i class="bi bi-printer"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payments)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>Aucun paiement trouvé.
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer py-2">
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php
                $qs = http_build_query(['annee'=>$yearId,'date_from'=>$dateFrom,'date_to'=>$dateTo,'agent'=>$agentId,'mode'=>$mode]);
                for ($i = max(1,$page-3); $i <= min($totalPages,$page+3); $i++):
                ?>
                <li class="page-item <?= $i==$page?'active':'' ?>">
                    <a class="page-link" href="?<?= $qs ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
