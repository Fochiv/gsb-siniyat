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

// Stats globales de l'établissement (année active)
$globalStats = $db->prepare("
    SELECT
        COUNT(*) AS nb_eleves,
        COALESCE(SUM(CASE WHEN sexe='M' THEN 1 ELSE 0 END), 0) AS nb_garcons,
        COALESCE(SUM(CASE WHEN sexe='F' THEN 1 ELSE 0 END), 0) AS nb_filles
    FROM eleves WHERE annee_id=? AND actif=TRUE
");
$globalStats->execute([$activeYear['id']]);
$globalRow = $globalStats->fetch();

$totalCollecte = $db->prepare("
    SELECT COALESCE(SUM(p.montant),0)
    FROM paiements p
    JOIN eleves e ON e.id=p.eleve_id
    WHERE e.annee_id=? AND p.annule=FALSE
");
$totalCollecte->execute([$activeYear['id']]);
$montantTotal = (float)$totalCollecte->fetchColumn();

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

<!-- Stats globales établissement -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Total élèves inscrits</div>
                    <div class="stat-value text-primary-siniyat"><?= (int)($globalRow['nb_eleves']??0) ?></div>
                </div>
                <i class="bi bi-people stat-icon text-primary-siniyat"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card stat-success h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Total collecté (établissement)</div>
                    <div class="stat-value text-success" style="font-size:1.1rem;"><?= formatMontant($montantTotal) ?></div>
                </div>
                <i class="bi bi-cash-coin stat-icon text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Garçons</div>
                    <div class="stat-value text-primary"><?= (int)($globalRow['nb_garcons']??0) ?></div>
                </div>
                <i class="bi bi-gender-male stat-icon text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card stat-danger h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Filles</div>
                    <div class="stat-value text-danger"><?= (int)($globalRow['nb_filles']??0) ?></div>
                </div>
                <i class="bi bi-gender-female stat-icon text-danger"></i>
            </div>
        </div>
    </div>
</div>

<!-- Quick stats personnels -->
<div class="row g-3 mb-4">
    <div class="col-sm-6">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Mes inscriptions aujourd'hui</div>
                    <div class="stat-value text-primary-siniyat"><?= $nbInscriptions ?></div>
                </div>
                <i class="bi bi-person-plus stat-icon text-primary-siniyat"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="card stat-card stat-success h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Mes encaissements aujourd'hui</div>
                    <div class="stat-value text-success" style="font-size:1.1rem;"><?= formatMontant((float)$todayStats['total']) ?> <small class="text-muted fw-normal" style="font-size:.75rem;">(<?= (int)$todayStats['nb'] ?> paiements)</small></div>
                </div>
                <i class="bi bi-cash-coin stat-icon text-success"></i>
            </div>
        </div>
    </div>
</div>

<!-- Quick actions — vrais boutons cliquables -->
<div class="mb-4">
    <h6 class="text-muted fw-semibold mb-3 small text-uppercase" style="letter-spacing:.06em;">
        <i class="bi bi-lightning me-1"></i>Actions rapides
    </h6>
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <a href="/secretary/students.php" class="quick-action-card primary h-100">
                <i class="bi bi-person-plus"></i>
                <span data-i18n="nav.new_student">Inscrire un élève</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="/secretary/payments.php" class="quick-action-card success h-100">
                <i class="bi bi-cash-coin"></i>
                <span data-i18n="nav.payments">Enregistrer paiement</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="/secretary/search.php" class="quick-action-card neutral h-100">
                <i class="bi bi-search"></i>
                <span data-i18n="nav.search">Rechercher élève</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="/secretary/classes.php" class="quick-action-card warning h-100">
                <i class="bi bi-list-ul"></i>
                <span>Liste par classe</span>
            </a>
        </div>
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
                        <td data-label="Reçu"><span class="badge bg-secondary">#<?= e($p['numero_recu'] ?? '—') ?></span></td>
                        <td data-label="Matricule"><code><?= e($p['matricule']) ?></code></td>
                        <td data-label="Élève"><?= e($p['nom'] . ' ' . $p['prenoms']) ?></td>
                        <td data-label="Classe"><?= e($p['classe']) ?></td>
                        <td data-label="Montant" class="fw-semibold"><?= formatMontant((float)$p['montant']) ?></td>
                        <td data-label="Mode"><?= $p['mode_paiement'] === 'especes' ? '<i class="bi bi-cash text-success"></i> Espèces' : '<i class="bi bi-bank text-info"></i> Virement' ?></td>
                        <td data-label="Date" class="text-muted small"><?= date('d/m/Y H:i', strtotime($p['date_paiement'])) ?></td>
                        <td data-label="">
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
