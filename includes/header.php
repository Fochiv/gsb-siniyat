<?php
/**
 * Common HTML header — Sidebar layout
 */
$pageTitle   = $pageTitle ?? 'GSB SINIYAT';
$userRole    = $_SESSION['user_role'] ?? '';
$userName    = trim(($_SESSION['user_prenom'] ?? '') . ' ' . ($_SESSION['user_nom'] ?? ''));
$currentLang = $_SESSION['lang'] ?? 'fr';
$uri         = $_SERVER['REQUEST_URI'] ?? '';

// Helper: active class
function navActive(string $path): string {
    global $uri;
    return str_contains($uri, $path) ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d1b4b">
    <title><?= e($pageTitle) ?> — GSB SINIYAT</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <script>
        window.APP_LANG       = '<?= $currentLang ?>';
        window.CSRF_TOKEN     = '<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES) ?>';
        window.SESSION_TIMEOUT= <?= SESSION_TIMEOUT ?>;
        window.USER_ROLE      = '<?= $userRole ?>';
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebar-overlay').classList.toggle('show');
        }
    </script>
</head>
<body>

<!-- Overlay mobile -->
<div id="sidebar-overlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- ========== SIDEBAR ========== -->
<nav id="sidebar" class="sidebar">
    <!-- Logo -->
    <div class="sidebar-header">
        <a class="sidebar-brand" href="<?= $userRole === 'admin' ? '/admin/index.php' : '/secretary/index.php' ?>">
            <div class="logo-circle">
                <img src="/assets/img/logo.png" alt="Logo" width="38" height="38">
            </div>
            <div class="sidebar-brand-text">
                <div class="sidebar-brand-name">GSB SINIYAT</div>
                <div class="sidebar-brand-year" data-i18n="app.year">2026-2027</div>
            </div>
        </a>
    </div>

    <!-- Navigation -->
    <div class="sidebar-nav">
        <?php if ($userRole === 'admin'): ?>

        <div class="sidebar-section-label">Général</div>
        <a class="nav-link<?= navActive('/admin/index') ?>" href="/admin/index.php">
            <i class="bi bi-speedometer2"></i><span data-i18n="nav.dashboard">Tableau de bord</span>
        </a>

        <div class="sidebar-section-label">Élèves</div>
        <a class="nav-link<?= navActive('/secretary/students') ?>" href="/secretary/students.php">
            <i class="bi bi-person-plus"></i><span data-i18n="nav.new_student">Inscrire un élève</span>
        </a>
        <a class="nav-link<?= navActive('/secretary/search') ?>" href="/secretary/search.php">
            <i class="bi bi-search"></i><span data-i18n="nav.search">Rechercher</span>
        </a>
        <a class="nav-link<?= navActive('/secretary/classes') ?>" href="/secretary/classes.php">
            <i class="bi bi-list-ul"></i><span>Liste par classe</span>
        </a>

        <div class="sidebar-section-label">Finances</div>
        <a class="nav-link<?= navActive('/secretary/payments') ?>" href="/secretary/payments.php">
            <i class="bi bi-cash-coin"></i><span data-i18n="nav.payments">Enregistrer paiement</span>
        </a>
        <a class="nav-link<?= navActive('/admin/payments') ?>" href="/admin/payments.php">
            <i class="bi bi-receipt-cutoff"></i><span>Tous les paiements</span>
        </a>

        <div class="sidebar-section-label">Administration</div>
        <a class="nav-link<?= navActive('/admin/users') ?>" href="/admin/users.php">
            <i class="bi bi-person-badge"></i><span data-i18n="nav.users">Utilisateurs</span>
        </a>
        <a class="nav-link<?= navActive('/admin/classes') ?>" href="/admin/classes.php">
            <i class="bi bi-building"></i><span data-i18n="nav.classes">Classes & Niveaux</span>
        </a>
        <a class="nav-link<?= navActive('/admin/fees') ?>" href="/admin/fees.php">
            <i class="bi bi-currency-exchange"></i><span data-i18n="nav.fees">Grille des frais</span>
        </a>
        <a class="nav-link<?= navActive('/admin/academic_years') ?>" href="/admin/academic_years.php">
            <i class="bi bi-calendar-range"></i><span data-i18n="nav.years">Années scolaires</span>
        </a>
        <div class="sidebar-divider"></div>
        <a class="nav-link<?= navActive('/admin/audit_log') ?>" href="/admin/audit_log.php">
            <i class="bi bi-journal-text"></i><span data-i18n="nav.audit">Journal d'audit</span>
        </a>

        <?php else: /* secretary */ ?>

        <div class="sidebar-section-label">Général</div>
        <a class="nav-link<?= navActive('/secretary/index') ?>" href="/secretary/index.php">
            <i class="bi bi-speedometer2"></i><span data-i18n="nav.dashboard">Tableau de bord</span>
        </a>

        <div class="sidebar-section-label">Élèves</div>
        <a class="nav-link<?= navActive('/secretary/students') ?>" href="/secretary/students.php">
            <i class="bi bi-person-plus"></i><span data-i18n="nav.new_student">Inscrire un élève</span>
        </a>
        <a class="nav-link<?= navActive('/secretary/search') ?>" href="/secretary/search.php">
            <i class="bi bi-search"></i><span data-i18n="nav.search">Rechercher</span>
        </a>
        <a class="nav-link<?= navActive('/secretary/classes') ?>" href="/secretary/classes.php">
            <i class="bi bi-list-ul"></i><span>Liste par classe</span>
        </a>

        <div class="sidebar-section-label">Finances</div>
        <a class="nav-link<?= navActive('/secretary/payments') ?>" href="/secretary/payments.php">
            <i class="bi bi-cash-coin"></i><span data-i18n="nav.payments">Paiements</span>
        </a>

        <div class="sidebar-divider"></div>
        <a class="nav-link<?= navActive('/change_password') ?>" href="/change_password.php">
            <i class="bi bi-key"></i><span data-i18n="nav.change_password">Mon mot de passe</span>
        </a>

        <?php endif; ?>
    </div>
</nav>
<!-- ========== / SIDEBAR ========== -->

<!-- ========== CONTENT WRAPPER ========== -->
<div class="content-wrapper">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <!-- Hamburger (mobile) -->
            <button class="sidebar-toggle-btn" onclick="toggleSidebar()" aria-label="Menu">
                <i class="bi bi-list" style="font-size:1.2rem;"></i>
            </button>
            <span class="topbar-title d-none d-sm-inline"><?= e($pageTitle) ?></span>
        </div>

        <div class="topbar-right">
            <!-- Language switcher -->
            <button type="button" id="lang-toggle-btn" class="btn btn-sm btn-outline-secondary" onclick="toggleLang()">
                <i class="bi bi-translate me-1"></i><?php if ($currentLang==='fr'): ?>FR&nbsp;<span class="text-muted">/&nbsp;EN</span><?php else: ?><span class="text-muted">FR&nbsp;/&nbsp;</span>EN<?php endif; ?>
            </button>

            <!-- User dropdown -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-1"
                        type="button" data-bs-toggle="dropdown" aria-expanded="false"
                        style="max-width:180px;">
                    <i class="bi bi-person-circle"></i>
                    <span class="text-truncate"><?= e($userName) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                        <h6 class="dropdown-header text-muted small">
                            <i class="bi bi-shield-check me-1"></i>
                            <?= $userRole === 'admin' ? 'Administrateur' : 'Secrétaire / Caissière' ?>
                        </h6>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="/change_password.php">
                            <i class="bi bi-key me-2"></i><span data-i18n="nav.change_password">Changer mot de passe</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Logout (always visible) -->
            <form method="POST" action="/logout.php" class="d-inline">
                <?= csrfField() ?>
                <button type="submit" class="btn-logout" title="Déconnexion">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="d-none d-md-inline" data-i18n="nav.logout">Déconnexion</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Session timeout warning -->
    <div id="timeout-warning" class="alert alert-warning alert-dismissible d-none" role="alert">
        <i class="bi bi-clock-history me-2"></i>
        <strong data-i18n="session.warning_title">Session expirée bientôt</strong> —
        <span data-i18n="session.warning_msg">Votre session expire dans 2 minutes.</span>
        <button type="button" class="btn btn-sm btn-warning ms-2" onclick="extendSession()">
            <span data-i18n="session.extend">Rester connecté</span>
        </button>
    </div>

    <main class="container-fluid">
