<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireAdmin();
$db   = getDB();
$pageTitle = 'Gestion des utilisateurs';
$message = ''; $messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $login  = trim($_POST['login'] ?? '');
        $role   = $_POST['role'] ?? 'secretaire';
        $actif  = !empty($_POST['actif']);
        $editId = (int)($_POST['edit_id'] ?? 0);

        if ($nom && $prenom && $login) {
            try {
                if ($action === 'create') {
                    $mdp = trim($_POST['mot_de_passe'] ?? '');
                    if (strlen($mdp) < 8 || !preg_match('/[A-Z]/',$mdp) || !preg_match('/[0-9]/',$mdp)) {
                        $message = 'Mot de passe : min 8 caractères, 1 majuscule, 1 chiffre.'; $messageType = 'danger';
                    } else {
                        $hash = password_hash($mdp, PASSWORD_BCRYPT, ['cost'=>12]);
                        $db->prepare("INSERT INTO utilisateurs (nom,prenom,login,mot_de_passe,role,actif,created_by)
                            VALUES (?,?,?,?,?,?,?)")
                            ->execute([$nom,$prenom,$login,$hash,$role,$actif,$user['user_id']]);
                        auditLog($user['user_id'],'CREATION_UTILISATEUR','utilisateurs',0,"Création: $login ($role)");
                        $message = 'Compte créé avec succès.'; $messageType = 'success';
                    }
                } else {
                    $db->prepare("UPDATE utilisateurs SET nom=?,prenom=?,login=?,role=?,actif=? WHERE id=? AND id!=?")
                       ->execute([$nom,$prenom,$login,$role,$actif,$editId,$user['user_id']]);
                    // Reset password if provided
                    if (!empty($_POST['reset_mdp'])) {
                        $newMdp = trim($_POST['new_mdp'] ?? '');
                        if (strlen($newMdp) >= 8) {
                            $hash = password_hash($newMdp, PASSWORD_BCRYPT, ['cost'=>12]);
                            $db->prepare("UPDATE utilisateurs SET mot_de_passe=? WHERE id=?")->execute([$hash,$editId]);
                        }
                    }
                    auditLog($user['user_id'],'MODIF_UTILISATEUR','utilisateurs',$editId,"Modification: $login");
                    $message = 'Compte mis à jour.'; $messageType = 'success';
                }
            } catch (Exception $ex) {
                $message = 'Erreur : ' . $ex->getMessage(); $messageType = 'danger';
            }
        } else {
            $message = 'Tous les champs obligatoires doivent être remplis.'; $messageType = 'danger';
        }
    } elseif ($action === 'toggle') {
        $toggleId = (int)$_POST['toggle_id'];
        if ($toggleId !== $user['user_id']) {
            $db->prepare("UPDATE utilisateurs SET actif = NOT actif WHERE id=?")->execute([$toggleId]);
            auditLog($user['user_id'],'TOGGLE_UTILISATEUR','utilisateurs',$toggleId,'Activation/Désactivation');
            $message = 'Statut modifié.'; $messageType = 'success';
        }
    } elseif ($action === 'delete') {
        $delId = (int)$_POST['del_id'];
        if ($delId !== $user['user_id']) {
            $db->prepare("UPDATE utilisateurs SET actif=FALSE WHERE id=?")->execute([$delId]);
            auditLog($user['user_id'],'DESACTIVATION_UTILISATEUR','utilisateurs',$delId,'Désactivation');
            $message = 'Compte désactivé.'; $messageType = 'warning';
        }
    }
}

$users = $db->query("SELECT u.*, c.login AS created_by_login FROM utilisateurs u
    LEFT JOIN utilisateurs c ON c.id=u.created_by ORDER BY u.id")->fetchAll();

$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($users as $u) if ($u['id']==$editId) { $editUser=$u; break; }
}

// Login history
$loginHistory = $db->query("
    SELECT h.*, u.login AS user_login
    FROM historique_connexions h
    LEFT JOIN utilisateurs u ON u.id = h.utilisateur_id
    ORDER BY h.date_heure DESC LIMIT 30")->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="fade-in-load">
<h1 class="page-title"><i class="bi bi-people"></i> <span data-i18n="user.title">Utilisateurs</span></h1>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
    <i class="bi bi-<?= $messageType==='success'?'check-circle-fill':'exclamation-triangle' ?> me-2"></i>
    <?= e($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Form -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-<?= $editUser ? 'pencil' : 'person-plus' ?> me-2"></i>
                <span data-i18n="user.<?= $editUser ? 'edit' : 'new' ?>"><?= $editUser ? 'Modifier' : 'Créer un compte' ?></span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
                    <?php if ($editUser): ?><input type="hidden" name="edit_id" value="<?= $editUser['id'] ?>"><?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label" data-i18n="user.nom">Nom</label>
                        <input type="text" name="nom" class="form-control" required value="<?= e($editUser['nom']??'') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" data-i18n="user.prenom">Prénom</label>
                        <input type="text" name="prenom" class="form-control" required value="<?= e($editUser['prenom']??'') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" data-i18n="user.login">Identifiant</label>
                        <input type="text" name="login" class="form-control" required value="<?= e($editUser['login']??'') ?>"
                               autocomplete="off">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" data-i18n="user.role">Rôle</label>
                        <select name="role" class="form-select">
                            <option value="secretaire" <?= ($editUser['role']??'')==='secretaire'?'selected':'' ?> data-i18n="user.secretary">Secrétaire / Caissière</option>
                            <option value="admin" <?= ($editUser['role']??'')==='admin'?'selected':'' ?> data-i18n="user.admin">Administrateur</option>
                        </select>
                    </div>
                    <div class="mb-2 form-check">
                        <input class="form-check-input" type="checkbox" name="actif" id="actif" <?= ($editUser['actif']??true)?'checked':'' ?>>
                        <label class="form-check-label" for="actif" data-i18n="user.active">Actif</label>
                    </div>
                    <?php if (!$editUser): ?>
                    <div class="mb-3">
                        <label class="form-label" data-i18n="user.password">Mot de passe</label>
                        <input type="password" name="mot_de_passe" class="form-control" required
                               pattern="(?=.*[A-Z])(?=.*[0-9]).{8,}" autocomplete="new-password">
                        <div class="form-text" data-i18n="password.hint">Min. 8 caractères, 1 majuscule, 1 chiffre.</div>
                    </div>
                    <?php else: ?>
                    <div class="mb-2 border-top pt-2 mt-2">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="reset_mdp" id="reset_mdp" onchange="document.getElementById('new_mdp_field').style.display=this.checked?'':'none'">
                            <label class="form-check-label" for="reset_mdp" data-i18n="user.reset_password">Réinitialiser le mot de passe</label>
                        </div>
                        <div id="new_mdp_field" style="display:none;">
                            <input type="password" name="new_mdp" class="form-control" placeholder="Nouveau mot de passe" autocomplete="new-password">
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary-siniyat">
                            <i class="bi bi-check-lg me-1"></i><span data-i18n="user.save">Enregistrer</span>
                        </button>
                        <?php if ($editUser): ?>
                        <a href="/admin/users.php" class="btn btn-outline-secondary" data-i18n="common.cancel">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Users list -->
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-primary-siniyat text-white"><i class="bi bi-list-ul me-2"></i>Comptes utilisateurs</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Identifiant</th>
                                <th>Rôle</th>
                                <th>Statut</th>
                                <th>Dernière connexion</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><strong><?= e($u['prenom'].' '.$u['nom']) ?></strong></td>
                                <td><code><?= e($u['login']) ?></code></td>
                                <td>
                                    <span class="badge <?= $u['role']==='admin'?'bg-danger':'bg-secondary' ?>">
                                        <?= $u['role']==='admin'?'Admin':'Secrétaire' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $u['actif']?'bg-success':'bg-warning text-dark' ?>">
                                        <?= $u['actif']?'Actif':'Inactif' ?>
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <?= $u['derniere_connexion'] ? date('d/m/Y H:i', strtotime($u['derniere_connexion'])) : '—' ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($u['id'] !== $user['user_id']): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="toggle_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $u['actif']?'btn-outline-warning':'btn-outline-success' ?> btn-action"
                                                    title="<?= $u['actif']?'Désactiver':'Activer' ?>">
                                                <i class="bi bi-<?= $u['actif']?'pause-circle':'play-circle' ?>"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Login history -->
        <div class="card">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-clock-history me-2"></i>Historique de connexion (30 dernières)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr><th>Utilisateur</th><th>Date/heure</th><th>Statut</th><th>IP</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loginHistory as $h): ?>
                            <tr>
                                <td><code><?= e($h['login_tente'] ?? $h['user_login'] ?? '—') ?></code></td>
                                <td class="small"><?= date('d/m/Y H:i:s', strtotime($h['date_heure'])) ?></td>
                                <td>
                                    <span class="badge <?= $h['succes']?'bg-success':'bg-danger' ?>">
                                        <?= $h['succes']?'Succès':'Échec' ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= e($h['ip_address']??'—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
