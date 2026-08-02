<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    redirect($_SESSION['user_role'] === 'admin' ? '/admin/index.php' : '/secretary/index.php');
}

$error = '';
$currentLang = $_SESSION['lang'] ?? 'fr';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $login = trim($_POST['login'] ?? '');
    $mdp   = $_POST['mot_de_passe'] ?? '';
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($login && $mdp) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE login = ? AND actif = TRUE LIMIT 1");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && password_verify($mdp, $user['mot_de_passe'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_nom']    = $user['nom'];
            $_SESSION['user_prenom'] = $user['prenom'];
            $_SESSION['user_role']   = $user['role'];
            $_SESSION['user_login']  = $user['login'];
            $_SESSION['_last_activity'] = time();

            // Log successful login
            $db->prepare("INSERT INTO historique_connexions (utilisateur_id, login_tente, succes, ip_address, user_agent) VALUES (?,?,TRUE,?,?)")
               ->execute([$user['id'], $login, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '']);
            $db->prepare("UPDATE utilisateurs SET derniere_connexion=NOW() WHERE id=?")
               ->execute([$user['id']]);
            auditLog($user['id'], 'CONNEXION', 'utilisateurs', $user['id'], 'Connexion réussie');

            redirect($user['role'] === 'admin' ? '/admin/index.php' : '/secretary/index.php');
        } else {
            // Log failed attempt
            $userId = $user ? $user['id'] : null;
            $db->prepare("INSERT INTO historique_connexions (utilisateur_id, login_tente, succes, ip_address, user_agent) VALUES (?,?,FALSE,?,?)")
               ->execute([$userId, $login, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '']);
            $error = 'fr';
        }
    } else {
        $error = 'empty';
    }
}

$timeout   = isset($_GET['timeout']);
$forbidden = isset($_GET['forbidden']);
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d1b4b">
    <title>Connexion — GSB SINIYAT</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <script>window.APP_LANG='<?= $currentLang ?>';</script>
</head>
<body class="login-page">
<div class="login-container">
    <div class="card login-card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
            <!-- Logo -->
            <div class="text-center mb-4">
                <div class="logo-circle-lg mx-auto mb-3">
                    <img src="/assets/img/logo.png" alt="Logo GSB SINIYAT" class="img-fluid">
                </div>
                <h4 class="fw-bold text-primary-siniyat mb-0" data-i18n="login.school_name"><?= APP_NAME_FR ?></h4>
                <p class="text-muted small mb-0" data-i18n="login.system">Système de Gestion Scolaire</p>
                <span class="badge bg-primary-siniyat mt-1"><?= APP_YEAR ?></span>
            </div>

            <?php if ($timeout): ?>
            <div class="alert alert-warning d-flex align-items-center" role="alert">
                <i class="bi bi-clock-history me-2"></i>
                <span data-i18n="login.timeout_msg">Session expirée. Veuillez vous reconnecter.</span>
            </div>
            <?php endif; ?>

            <?php if ($forbidden): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="bi bi-shield-x me-2"></i>
                <span data-i18n="login.forbidden">Accès refusé.</span>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <span data-i18n="login.error_invalid">Identifiant ou mot de passe incorrect.</span>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" novalidate>
                <?= csrfField() ?>
                <div class="mb-3">
                    <label for="login" class="form-label fw-semibold" data-i18n="login.username">Identifiant</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="login" name="login"
                               value="<?= e($_POST['login'] ?? '') ?>"
                               required autofocus autocomplete="username">
                    </div>
                </div>
                <div class="mb-4">
                    <label for="mot_de_passe" class="form-label fw-semibold" data-i18n="login.password">Mot de passe</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe"
                               required autocomplete="current-password">
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                            <i class="bi bi-eye" id="eye-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary-siniyat btn-lg fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        <span data-i18n="login.submit">Se connecter</span>
                    </button>
                </div>
            </form>

            <!-- Language switcher -->
            <div class="text-center mt-4">
                <button type="button" id="lang-toggle-btn" class="btn btn-sm btn-outline-secondary" onclick="toggleLang()">
                    <i class="bi bi-translate me-1"></i><?php if ($currentLang==='fr'): ?>FR&nbsp;<span class="text-muted">/&nbsp;EN</span><?php else: ?><span class="text-muted">FR&nbsp;/&nbsp;</span>EN<?php endif; ?>
                </button>
            </div>
        </div>
    </div>
    <p class="text-center text-white-50 mt-3 small">&copy; <?= date('Y') ?> GSB SINIYAT — Tous droits réservés</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/lang.js"></script>
<script>
function togglePassword() {
    const f = document.getElementById('mot_de_passe');
    const i = document.getElementById('eye-icon');
    if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { f.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>
</body>
</html>
