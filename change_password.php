<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$user = requireLogin();
$pageTitle = 'Changer le mot de passe';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $ancien  = $_POST['ancien_mdp'] ?? '';
    $nouveau = $_POST['nouveau_mdp'] ?? '';
    $confirm = $_POST['confirm_mdp'] ?? '';

    $db   = getDB();
    $stmt = $db->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id=?");
    $stmt->execute([$user['user_id']]);
    $row = $stmt->fetch();

    if (!password_verify($ancien, $row['mot_de_passe'])) {
        $message = 'error_old'; $messageType = 'danger';
    } elseif (strlen($nouveau) < 8) {
        $message = 'error_length'; $messageType = 'danger';
    } elseif (!preg_match('/[A-Z]/', $nouveau) || !preg_match('/[0-9]/', $nouveau)) {
        $message = 'error_complexity'; $messageType = 'danger';
    } elseif ($nouveau !== $confirm) {
        $message = 'error_match'; $messageType = 'danger';
    } else {
        $hash = password_hash($nouveau, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("UPDATE utilisateurs SET mot_de_passe=? WHERE id=?")->execute([$hash, $user['user_id']]);
        auditLog($user['user_id'], 'CHANGEMENT_MDP', 'utilisateurs', $user['user_id'], 'Mot de passe modifié');
        $message = 'success'; $messageType = 'success';
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary-siniyat text-white">
                <h5 class="mb-0"><i class="bi bi-key me-2"></i><span data-i18n="password.title">Changer le mot de passe</span></h5>
            </div>
            <div class="card-body p-4">
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>" data-i18n="password.<?= $message ?>">
                    <?php
                    $msgs = [
                        'success'          => 'Mot de passe modifié avec succès.',
                        'error_old'        => 'Ancien mot de passe incorrect.',
                        'error_length'     => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
                        'error_complexity' => 'Le mot de passe doit contenir au moins une majuscule et un chiffre.',
                        'error_match'      => 'Les mots de passe ne correspondent pas.',
                    ];
                    echo e($msgs[$message] ?? '');
                    ?>
                </div>
                <?php endif; ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label" data-i18n="password.old">Ancien mot de passe</label>
                        <input type="password" name="ancien_mdp" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" data-i18n="password.new">Nouveau mot de passe</label>
                        <input type="password" name="nouveau_mdp" class="form-control" required
                               pattern="(?=.*[A-Z])(?=.*[0-9]).{8,}">
                        <div class="form-text" data-i18n="password.hint">Min. 8 caractères, 1 majuscule, 1 chiffre.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" data-i18n="password.confirm">Confirmer le nouveau mot de passe</label>
                        <input type="password" name="confirm_mdp" class="form-control" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary-siniyat">
                            <i class="bi bi-check-lg me-2"></i><span data-i18n="password.save">Enregistrer</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
