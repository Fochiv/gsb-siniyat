<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireAdmin();
$pageTitle = 'Tableau de bord';
$db = getDB();
$activeYear = getActiveYear();
$yearId = (int)($_GET['annee'] ?? $activeYear['id']);
$allYears = getAllYears();

// Stats globales
$totalEleves = $db->prepare("SELECT COUNT(*) FROM eleves WHERE annee_id=? AND actif=TRUE");
$totalEleves->execute([$yearId]);
$nbEleves = (int)$totalEleves->fetchColumn();

$totalPaye = $db->prepare("SELECT COALESCE(SUM(p.montant),0) FROM paiements p JOIN eleves e ON e.id=p.eleve_id WHERE e.annee_id=? AND p.annule=FALSE");
$totalPaye->execute([$yearId]);
$montantPaye = (float)$totalPaye->fetchColumn();

// Stats par statut paiement
$statuts = $db->prepare("
    SELECT
        SUM(CASE WHEN sit.statut='paye'   THEN 1 ELSE 0 END) AS nb_payes,
        SUM(CASE WHEN sit.statut='partiel' THEN 1 ELSE 0 END) AS nb_partiels,
        SUM(CASE WHEN sit.statut='impaye'  THEN 1 ELSE 0 END) AS nb_impayes,
        SUM(sit.total_du - sit.total_paye) AS total_reste
    FROM (
        SELECT e.id,
            COALESCE(SUM(CASE WHEN p.annule=FALSE THEN p.montant ELSE 0 END),0) AS total_paye,
            COALESCE((SELECT SUM(t.montant) FROM grille_frais g JOIN tranches t ON t.grille_id=g.id WHERE g.annee_id=e.annee_id AND g.niveau_id=e.niveau_id),0) AS total_du,
            CASE
                WHEN COALESCE(SUM(CASE WHEN p.annule=FALSE THEN p.montant ELSE 0 END),0) >= COALESCE((SELECT SUM(t.montant) FROM grille_frais g JOIN tranches t ON t.grille_id=g.id WHERE g.annee_id=e.annee_id AND g.niveau_id=e.niveau_id),0) THEN 'paye'
                WHEN COALESCE(SUM(CASE WHEN p.annule=FALSE THEN p.montant ELSE 0 END),0) > 0 THEN 'partiel'
                ELSE 'impaye'
            END AS statut
        FROM eleves e
        LEFT JOIN paiements p ON p.eleve_id = e.id
        WHERE e.annee_id = ? AND e.actif = TRUE
        GROUP BY e.id, e.annee_id, e.niveau_id
    ) sit
");
$statuts->execute([$yearId]);
$statRow = $statuts->fetch();

// By class stats
$byClass = $db->prepare("
    SELECT n.nom_fr, n.nom_en,
        COUNT(e.id) AS nb_eleves,
        COALESCE(SUM(CASE WHEN p.annule=FALSE THEN p.montant ELSE 0 END),0) AS total_paye,
        SUM(CASE WHEN e.sexe='M' THEN 1 ELSE 0 END) AS nb_garcons,
        SUM(CASE WHEN e.sexe='F' THEN 1 ELSE 0 END) AS nb_filles
    FROM niveaux n
    JOIN eleves e ON e.niveau_id = n.id AND e.annee_id = ? AND e.actif = TRUE
    LEFT JOIN paiements p ON p.eleve_id = e.id
    GROUP BY n.id, n.nom_fr, n.nom_en, n.ordre
    ORDER BY n.ordre
");
$byClass->execute([$yearId]);
$classeStats = $byClass->fetchAll();

// Recent payments
$recentPay = $db->prepare("
    SELECT p.*, e.nom, e.prenoms, e.matricule, n.nom_fr AS classe, u.prenom||' '||u.nom AS agent,
           r.numero_recu
    FROM paiements p
    JOIN eleves e ON e.id = p.eleve_id
    JOIN niveaux n ON n.id = e.niveau_id
    LEFT JOIN utilisateurs u ON u.id = p.encaisse_par
    LEFT JOIN recus r ON r.paiement_id = p.id
    WHERE e.annee_id = ? AND p.annule = FALSE
    ORDER BY p.date_paiement DESC LIMIT 10
");
$recentPay->execute([$yearId]);
$recentPayments = $recentPay->fetchAll();

// Monthly chart data
$monthly = $db->prepare("
    SELECT TO_CHAR(p.date_paiement, 'YYYY-MM') AS mois,
           SUM(p.montant) AS total
    FROM paiements p
    JOIN eleves e ON e.id = p.eleve_id
    WHERE e.annee_id = ? AND p.annule = FALSE
    GROUP BY mois ORDER BY mois
");
$monthly->execute([$yearId]);
$monthlyData = $monthly->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="fade-in-load">

<!-- Page title + year selector -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
    <h1 class="page-title mb-0">
        <i class="bi bi-speedometer2"></i> <span data-i18n="dashboard.title">Tableau de bord</span>
    </h1>
    <form class="d-flex align-items-center gap-2">
        <label class="fw-semibold text-muted small" data-i18n="nav.years">Année :</label>
        <select name="annee" class="form-select form-select-sm" style="width:140px;" onchange="this.form.submit()">
            <?php foreach ($allYears as $y): ?>
            <option value="<?= $y['id'] ?>" <?= $y['id'] == $yearId ? 'selected' : '' ?>>
                <?= e($y['libelle']) ?><?= $y['statut']==='active' ? ' ★' : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small" data-i18n="dashboard.total_students">Élèves inscrits</div>
                    <div class="stat-value text-primary-siniyat"><?= $nbEleves ?></div>
                </div>
                <i class="bi bi-people stat-icon text-primary-siniyat"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card stat-success h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small" data-i18n="dashboard.total_collected">Total encaissé</div>
                    <div class="stat-value text-success" style="font-size:1.2rem;"><?= formatMontant($montantPaye) ?></div>
                </div>
                <i class="bi bi-cash-coin stat-icon text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card stat-danger h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small" data-i18n="dashboard.total_remaining">Reste à recouvrer</div>
                    <div class="stat-value text-danger" style="font-size:1.2rem;"><?= formatMontant((float)($statRow['total_reste']??0)) ?></div>
                </div>
                <i class="bi bi-exclamation-circle stat-icon text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card stat-warning h-100">
            <div class="card-body">
                <div class="text-muted small mb-2" data-i18n="dashboard.financial_summary">Statut paiements</div>
                <div class="d-flex gap-3 text-center">
                    <div><div class="fw-bold text-success"><?= (int)($statRow['nb_payes']??0) ?></div><small class="badge-paye rounded px-1" data-i18n="payment.status_paid">Soldés</small></div>
                    <div><div class="fw-bold text-warning"><?= (int)($statRow['nb_partiels']??0) ?></div><small class="badge-partiel rounded px-1" data-i18n="payment.status_partial">Partiels</small></div>
                    <div><div class="fw-bold text-danger"><?= (int)($statRow['nb_impayes']??0) ?></div><small class="badge-impaye rounded px-1" data-i18n="payment.status_unpaid">Impayés</small></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts + By class -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-primary-siniyat text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart me-2"></i><span data-i18n="dashboard.by_class">Par classe</span></span>
                <a href="/api/export.php?type=all_classes&annee_id=<?= $yearId ?>&format=excel"
                   class="btn btn-sm btn-light" data-i18n="export.excel">Exporter Excel</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th data-i18n="fees.class">Classe</th>
                                <th data-i18n="dashboard.total_students">Élèves</th>
                                <th data-i18n="common.male">G</th>
                                <th data-i18n="common.female">F</th>
                                <th data-i18n="dashboard.total_collected">Encaissé</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classeStats as $cs): ?>
                            <tr>
                                <td data-label="Classe"><strong><?= e($cs['nom_fr']) ?></strong></td>
                                <td data-label="Élèves"><?= $cs['nb_eleves'] ?></td>
                                <td><?= $cs['nb_garcons'] ?></td>
                                <td><?= $cs['nb_filles'] ?></td>
                                <td><?= formatMontant((float)$cs['total_paye']) ?></td>
                                <td>
                                    <a href="/secretary/search.php?annee_id=<?= $yearId ?>&classe_nom=<?= urlencode($cs['nom_fr']) ?>"
                                       class="btn btn-sm btn-outline-siniyat btn-action" title="Voir">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($classeStats)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4" data-i18n="common.no_data">Aucune donnée.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-pie-chart me-2"></i><span data-i18n="dashboard.financial_summary">Répartition</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="paymentChart" width="260" height="260"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Monthly chart -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-graph-up me-2"></i>Évolution des encaissements
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent payments -->
<div class="card">
    <div class="card-header bg-primary-siniyat text-white">
        <i class="bi bi-clock-history me-2"></i><span data-i18n="dashboard.recent_payments">Paiements récents</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Reçu N°</th>
                        <th data-i18n="student.matricule">Matricule</th>
                        <th data-i18n="student.nom">Élève</th>
                        <th data-i18n="fees.class">Classe</th>
                        <th data-i18n="payment.amount">Montant</th>
                        <th data-i18n="payment.mode">Mode</th>
                        <th data-i18n="audit.user">Agent</th>
                        <th data-i18n="audit.date">Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPayments as $p): ?>
                    <tr>
                        <td><span class="badge bg-secondary">#<?= e($p['numero_recu'] ?? '—') ?></span></td>
                        <td><code><?= e($p['matricule']) ?></code></td>
                        <td><strong><?= e($p['nom'] . ' ' . $p['prenoms']) ?></strong></td>
                        <td><?= e($p['classe']) ?></td>
                        <td class="fw-semibold"><?= formatMontant((float)$p['montant']) ?></td>
                        <td>
                            <?php if ($p['mode_paiement'] === 'especes'): ?>
                            <span class="badge bg-success"><i class="bi bi-cash me-1"></i>Espèces</span>
                            <?php else: ?>
                            <span class="badge bg-info text-dark"><i class="bi bi-bank me-1"></i>Virement</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= e($p['agent'] ?? '—') ?></td>
                        <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($p['date_paiement'])) ?></td>
                        <td>
                            <?php if ($p['numero_recu']): ?>
                            <a href="/pdf/receipt.php?paiement_id=<?= $p['id'] ?>" target="_blank"
                               class="btn btn-sm btn-outline-secondary btn-action" title="Imprimer">
                                <i class="bi bi-printer"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentPayments)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4" data-i18n="common.no_data">Aucun paiement.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>

<?php
$nbPayes   = (int)($statRow['nb_payes']   ?? 0);
$nbPartiels= (int)($statRow['nb_partiels']?? 0);
$nbImpayes = (int)($statRow['nb_impayes'] ?? 0);

$monthLabels = json_encode(array_column($monthlyData, 'mois'));
$monthValues = json_encode(array_map(fn($r) => (float)$r['total'], $monthlyData));

$extraScripts = <<<HTML
<script>
// Doughnut chart
new Chart(document.getElementById('paymentChart'), {
    type: 'doughnut',
    data: {
        labels: ['Soldés', 'Partiels', 'Impayés'],
        datasets: [{ data: [{$nbPayes},{$nbPartiels},{$nbImpayes}],
            backgroundColor: ['#198754','#ffc107','#dc3545'],
            borderWidth: 2, borderColor: '#fff'
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
// Monthly chart
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: {$monthLabels},
        datasets: [{ label: 'Encaissements (FCFA)', data: {$monthValues},
            backgroundColor: 'rgba(13,27,75,0.7)', borderColor: '#0d1b4b',
            borderWidth: 1, borderRadius: 4
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { y: { ticks: { callback: v => new Intl.NumberFormat('fr').format(v) } } }
    }
});
</script>
HTML;
include dirname(__DIR__) . '/includes/footer.php';
?>
