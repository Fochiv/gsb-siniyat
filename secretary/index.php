<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireLogin();
$pageTitle = 'Tableau de bord';
$db = getDB();
$activeYear = getActiveYear();

// Paiements du jour par cet utilisateur
$today = $db->prepare("
    SELECT COUNT(*) AS nb, COALESCE(SUM(montant),0) AS total
    FROM paiements
    WHERE DATE(date_paiement) = CURRENT_DATE AND encaisse_par=? AND annule=FALSE
");
$today->execute([$user['user_id']]);
$todayStats = $today->fetch();

// Inscriptions du jour par cet utilisateur
$inscriptions = $db->prepare("
    SELECT COUNT(*) FROM eleves WHERE DATE(date_inscription)=CURRENT_DATE AND inscrit_par=? AND actif=TRUE
");
$inscriptions->execute([$user['user_id']]);
$nbInscriptions = (int)$inscriptions->fetchColumn();

// Paiements récents par cet utilisateur
$recentPay = $db->prepare("
    SELECT p.*, e.nom, e.prenoms, e.matricule, n.nom_fr AS classe, r.numero_recu
    FROM paiements p
    JOIN eleves e ON e.id = p.eleve_id
    JOIN niveaux n ON n.id = e.niveau_id
    LEFT JOIN recus r ON r.paiement_id = p.id
    WHERE p.encaisse_par = ? AND p.annule = FALSE
    ORDER BY p.date_paiement DESC LIMIT 15
");
$recentPay->execute([$user['user_id']]);
$recentPayments = $recentPay->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="fade-in-load">
<h1 class="page-title"><i class="bi bi-speedometer2"></i> <span data-i18n="dashboard.title">Tableau de bord</span></h1>

<!-- Quick stats -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Inscriptions aujourd'hui</div>
                    <div class="stat-value text-primary-siniyat"><?= $nbInscriptions ?></div>
                </div>
                <i class="bi bi-person-plus stat-icon text-primary-siniyat"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card stat-success">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Paiements aujourd'hui</div>
                    <div class="stat-value text-success"><?= (int)$todayStats['nb'] ?></div>
                </div>
                <i class="bi bi-receipt stat-icon text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card stat-success">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Total encaissé aujourd'hui</div>
                    <div class="stat-value text-success" style="font-size:1.1rem;"><?= formatMontant((float)$todayStats['total']) ?></div>
                </div>
                <i class="bi bi-cash-coin stat-icon text-success"></i>
            </div>
        </div>
    </div>
</div>

<!-- Quick actions -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="/secretary/students.php" class="card text-decoration-none text-center p-3 h-100 d-flex flex-column align-items-center justify-content-center gap-2" style="border:2px dashed var(--siniyat-primary);">
            <i class="bi bi-person-plus fs-2 text-primary-siniyat"></i>
            <span class="fw-semibold text-primary-siniyat small" data-i18n="nav.new_student">Inscrire un élève</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="/secretary/payments.php" class="card text-decoration-none text-center p-3 h-100 d-flex flex-column align-items-center justify-content-center gap-2" style="border:2px dashed var(--siniyat-success);">
            <i class="bi bi-cash-coin fs-2 text-success"></i>
            <span class="fw-semibold text-success small" data-i18n="nav.payments">Enregistrer paiement</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="/secretary/search.php" class="card text-decoration-none text-center p-3 h-100 d-flex flex-column align-items-center justify-content-center gap-2" style="border:2px dashed #64748b;">
            <i class="bi bi-search fs-2 text-muted"></i>
            <span class="fw-semibold text-muted small" data-i18n="nav.search">Rechercher élève</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="/change_password.php" class="card text-decoration-none text-center p-3 h-100 d-flex flex-column align-items-center justify-content-center gap-2" style="border:2px dashed #64748b;">
            <i class="bi bi-key fs-2 text-muted"></i>
            <span class="fw-semibold text-muted small" data-i18n="nav.change_password">Mon mot de passe</span>
        </a>
    </div>
</div>

<!-- Recent payments -->
<div class="card">
    <div class="card-header bg-primary-siniyat text-white">
        <i class="bi bi-clock-history me-2"></i><span data-i18n="dashboard.recent_payments">Mes paiements récents</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Reçu</th>
                        <th data-i18n="student.matricule">Matricule</th>
                        <th data-i18n="student.nom">Élève</th>
                        <th data-i18n="fees.class">Classe</th>
                        <th data-i18n="payment.amount">Montant</th>
                        <th data-i18n="payment.mode">Mode</th>
                        <th data-i18n="audit.date">Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPayments as $p): ?>
                    <tr>
                        <td><span class="badge bg-secondary">#<?= e($p['numero_recu'] ?? '—') ?></span></td>
                        <td><code><?= e($p['matricule']) ?></code></td>
                        <td><?= e($p['nom'] . ' ' . $p['prenoms']) ?></td>
                        <td><?= e($p['classe']) ?></td>
                        <td class="fw-semibold"><?= formatMontant((float)$p['montant']) ?></td>
                        <td><?= $p['mode_paiement'] === 'especes' ? '<i class="bi bi-cash text-success"></i> Espèces' : '<i class="bi bi-bank text-info"></i> Virement' ?></td>
                        <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($p['date_paiement'])) ?></td>
                        <td>
                            <?php if ($p['numero_recu']): ?>
                            <a href="/pdf/receipt.php?paiement_id=<?= $p['id'] ?>" target="_blank"
                               class="btn btn-sm btn-outline-secondary btn-action">
                                <i class="bi bi-printer"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentPayments)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4" data-i18n="common.no_data">Aucun paiement récent.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
