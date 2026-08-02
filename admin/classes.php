<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireAdmin();
$db   = getDB();
$pageTitle = 'Classes & Niveaux';
$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $nomFr   = trim($_POST['nom_fr'] ?? '');
        $nomEn   = trim($_POST['nom_en'] ?? '');
        $ordre   = (int)($_POST['ordre'] ?? 0);
        $section = in_array($_POST['section']??'', ['francophone','anglophone']) ? $_POST['section'] : 'francophone';
        $actif   = !empty($_POST['actif']);
        if ($nomFr && $nomEn) {
            if ($id) {
                $db->prepare("UPDATE niveaux SET nom_fr=?,nom_en=?,ordre=?,section=?,actif=? WHERE id=?")
                   ->execute([$nomFr,$nomEn,$ordre,$section,$actif,$id]);
                auditLog($user['user_id'],'MODIF_NIVEAU','niveaux',$id,"Modification: $nomFr ($section)");
            } else {
                $db->prepare("INSERT INTO niveaux (nom_fr,nom_en,ordre,section,actif) VALUES (?,?,?,?,?)")
                   ->execute([$nomFr,$nomEn,$ordre,$section,$actif]);
                auditLog($user['user_id'],'CREATION_NIVEAU','niveaux',0,"Création: $nomFr ($section)");
            }
            $message = 'Niveau enregistré.'; $messageType = 'success';
        } else { $message = 'Champs obligatoires manquants.'; $messageType = 'danger'; }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE niveaux SET actif=NOT actif WHERE id=?")->execute([$id]);
        $message = 'Statut modifié.'; $messageType = 'success';
    }
}

$niveaux = $db->query("SELECT * FROM niveaux ORDER BY section, ordre")->fetchAll();
$editId  = (int)($_GET['edit'] ?? 0);
$editN   = null;
foreach ($niveaux as $n) if ($n['id']==$editId) { $editN=$n; break; }

// Group by section
$franco = array_filter($niveaux, fn($n) => $n['section'] === 'francophone');
$anglo  = array_filter($niveaux, fn($n) => $n['section'] === 'anglophone');

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="fade-in-load">
<h1 class="page-title"><i class="bi bi-building"></i> <span data-i18n="nav.classes">Classes & Niveaux</span></h1>
<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
    <?= e($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Form -->
    <div class="col-lg-4">
        <?php if (!$editN): ?>
        <div class="mb-3">
            <button type="button" class="btn btn-primary-siniyat w-100" onclick="toggleClassForm()">
                <i class="bi bi-plus-circle me-2"></i>Ajouter un niveau
            </button>
        </div>
        <?php endif; ?>
        <div class="card <?= !$editN ? 'd-none' : '' ?>" id="class-form-card">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-<?= $editN?'pencil':'plus-circle' ?> me-2"></i>
                <?= $editN ? 'Modifier le niveau' : 'Ajouter un niveau' ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save">
                    <?php if ($editN): ?><input type="hidden" name="id" value="<?= $editN['id'] ?>"><?php endif; ?>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Section <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="section" id="sec_fr" value="francophone"
                                    <?= ($editN['section']??'francophone')==='francophone'?'checked':'' ?>>
                                <label class="form-check-label" for="sec_fr">
                                    <span class="badge badge-francophone">Francophone</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="section" id="sec_en" value="anglophone"
                                    <?= ($editN['section']??'')==='anglophone'?'checked':'' ?>>
                                <label class="form-check-label" for="sec_en">
                                    <span class="badge badge-anglophone">Anglophone</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Nom français <span class="text-danger">*</span></label>
                        <input type="text" name="nom_fr" class="form-control" required
                               placeholder="ex : CM2, Pré-Maternelle"
                               value="<?= e($editN['nom_fr']??'') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">English name <span class="text-danger">*</span></label>
                        <input type="text" name="nom_en" class="form-control" required
                               placeholder="ex : Class 6, Pre-Nursery"
                               value="<?= e($editN['nom_en']??'') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Ordre d'affichage</label>
                        <input type="number" name="ordre" class="form-control" min="0"
                               value="<?= $editN['ordre']??0 ?>">
                    </div>
                    <div class="mb-3 form-check">
                        <input class="form-check-input" type="checkbox" name="actif" id="actif"
                               <?= ($editN['actif']??true)?'checked':'' ?>>
                        <label class="form-check-label" for="actif">Actif</label>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary-siniyat">
                            <i class="bi bi-check-lg me-1"></i>Enregistrer
                        </button>
                        <a href="/admin/classes.php" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Lists -->
    <div class="col-lg-8">
        <!-- Francophone -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center gap-2" style="background:#dbeafe;">
                <span class="badge badge-francophone fs-6 px-3">Section Francophone</span>
                <small class="text-muted"><?= count($franco) ?> classe(s)</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Ordre</th><th>Nom FR</th><th>Nom EN</th><th>Statut</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($franco as $n): ?>
                        <tr>
                            <td><?= $n['ordre'] ?></td>
                            <td><strong><?= e($n['nom_fr']) ?></strong></td>
                            <td class="text-muted"><?= e($n['nom_en']) ?></td>
                            <td><span class="badge <?= $n['actif']?'bg-success':'bg-secondary' ?>"><?= $n['actif']?'Actif':'Inactif' ?></span></td>
                            <td>
                                <a href="?edit=<?= $n['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $n['actif']?'btn-outline-warning':'btn-outline-success' ?> btn-action">
                                        <i class="bi bi-<?= $n['actif']?'pause':'play' ?>-circle"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($franco)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Aucune classe francophone.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Anglophone -->
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2" style="background:#dcfce7;">
                <span class="badge badge-anglophone fs-6 px-3">Section Anglophone</span>
                <small class="text-muted"><?= count($anglo) ?> classe(s)</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Ordre</th><th>Nom FR</th><th>Nom EN</th><th>Statut</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($anglo as $n): ?>
                        <tr>
                            <td><?= $n['ordre'] ?></td>
                            <td><strong><?= e($n['nom_fr']) ?></strong></td>
                            <td class="text-muted"><?= e($n['nom_en']) ?></td>
                            <td><span class="badge <?= $n['actif']?'bg-success':'bg-secondary' ?>"><?= $n['actif']?'Actif':'Inactif' ?></span></td>
                            <td>
                                <a href="?edit=<?= $n['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $n['actif']?'btn-outline-warning':'btn-outline-success' ?> btn-action">
                                        <i class="bi bi-<?= $n['actif']?'pause':'play' ?>-circle"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($anglo)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Aucune classe anglophone.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
<script>
function toggleClassForm() {
    const card = document.getElementById('class-form-card');
    card.classList.toggle('d-none');
    if (!card.classList.contains('d-none')) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>
