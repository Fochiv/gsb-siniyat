<?php
/**
 * Common HTML header
 */
$pageTitle = $pageTitle ?? 'GSB SINIYAT';
$userRole  = $_SESSION['user_role'] ?? '';
$userName  = ($_SESSION['user_prenom'] ?? '') . ' ' . ($_SESSION['user_nom'] ?? '');
$currentLang = $_SESSION['lang'] ?? 'fr';
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
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" rel="preload" as="script">
    <link href="/assets/css/style.css" rel="stylesheet">
    <script>
        window.APP_LANG = '<?= $currentLang ?>';
        window.CSRF_TOKEN = '<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES) ?>';
        window.SESSION_TIMEOUT = <?= SESSION_TIMEOUT ?>;
        window.USER_ROLE = '<?= $userRole ?>';
    </script>
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary-siniyat shadow-sm">
    <div class="container-fluid px-3">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $userRole === 'admin' ? '/admin/index.php' : '/secretary/index.php' ?>">
            <div class="logo-circle">
                <img src="/assets/img/logo.png" alt="Logo" height="36" width="36">
            </div>
            <div class="d-none d-md-block">
                <div class="fw-bold lh-1" style="font-size:.95rem;">GSB SINIYAT</div>
                <div class="opacity-75" style="font-size:.7rem;" data-i18n="app.year">2026-2027</div>
            </div>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if ($userRole === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'], '/admin/index') ? 'active' : '' ?>" href="/admin/index.php">
                        <i class="bi bi-speedometer2 me-1"></i><span data-i18n="nav.dashboard">Tableau de bord</span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-people me-1"></i><span data-i18n="nav.students">Élèves</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/secretary/students.php"><i class="bi bi-person-plus me-2"></i><span data-i18n="nav.new_student">Inscrire un élève</span></a></li>
                        <li><a class="dropdown-item" href="/secretary/search.php"><i class="bi bi-search me-2"></i><span data-i18n="nav.search">Rechercher</span></a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'], '/secretary/payments') ? 'active' : '' ?>" href="/secretary/payments.php">
                        <i class="bi bi-cash-coin me-1"></i><span data-i18n="nav.payments">Paiements</span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-gear me-1"></i><span data-i18n="nav.admin">Administration</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/admin/users.php"><i class="bi bi-person-badge me-2"></i><span data-i18n="nav.users">Utilisateurs</span></a></li>
                        <li><a class="dropdown-item" href="/admin/classes.php"><i class="bi bi-building me-2"></i><span data-i18n="nav.classes">Classes & Niveaux</span></a></li>
                        <li><a class="dropdown-item" href="/admin/fees.php"><i class="bi bi-currency-exchange me-2"></i><span data-i18n="nav.fees">Grille des frais</span></a></li>
                        <li><a class="dropdown-item" href="/admin/academic_years.php"><i class="bi bi-calendar-range me-2"></i><span data-i18n="nav.years">Années scolaires</span></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/admin/audit_log.php"><i class="bi bi-journal-text me-2"></i><span data-i18n="nav.audit">Journal d'audit</span></a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'], '/secretary/index') ? 'active' : '' ?>" href="/secretary/index.php">
                        <i class="bi bi-speedometer2 me-1"></i><span data-i18n="nav.dashboard">Tableau de bord</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/secretary/students.php">
                        <i class="bi bi-person-plus me-1"></i><span data-i18n="nav.new_student">Inscrire un élève</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/secretary/payments.php">
                        <i class="bi bi-cash-coin me-1"></i><span data-i18n="nav.payments">Paiements</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/secretary/search.php">
                        <i class="bi bi-search me-1"></i><span data-i18n="nav.search">Rechercher</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <!-- Language switcher -->
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-light btn-sm <?= $currentLang === 'fr' ? 'active' : '' ?>" onclick="switchLang('fr')">FR</button>
                    <button type="button" class="btn btn-outline-light btn-sm <?= $currentLang === 'en' ? 'active' : '' ?>" onclick="switchLang('en')">EN</button>
                </div>
                <!-- User info & logout -->
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i><?= e(trim($userName)) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header"><?= e(strtoupper($userRole)) ?></h6></li>
                        <li><a class="dropdown-item" href="/change_password.php"><i class="bi bi-key me-2"></i><span data-i18n="nav.change_password">Changer mot de passe</span></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="/logout.php">
                                <?= csrfField() ?>
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i><span data-i18n="nav.logout">Déconnexion</span>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Session timeout warning -->
<div id="timeout-warning" class="alert alert-warning alert-dismissible m-0 d-none" role="alert" style="border-radius:0;position:sticky;top:0;z-index:1050;">
    <i class="bi bi-clock-history me-2"></i>
    <strong data-i18n="session.warning_title">Session expirée bientôt</strong> — 
    <span data-i18n="session.warning_msg">Votre session expire dans 2 minutes. Cliquez pour rester connecté.</span>
    <button type="button" class="btn btn-sm btn-warning ms-2" onclick="extendSession()">
        <span data-i18n="session.extend">Rester connecté</span>
    </button>
</div>

<main class="container-fluid py-3 px-3 px-md-4">
