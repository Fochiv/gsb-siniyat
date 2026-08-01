<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireLogin();
$db   = getDB();
$activeYear = getActiveYear();
$niveaux    = getNiveaux();
$allYears   = getAllYears();

$editId = (int)($_GET['edit'] ?? 0);
$eleve  = $editId ? getEleveById($editId) : null;
$docs   = null;
if ($eleve) {
    $docsStmt = $db->prepare("SELECT * FROM documents_eleve WHERE eleve_id=?");
    $docsStmt->execute([$editId]);
    $docs = $docsStmt->fetch();
}

$pageTitle = $eleve ? 'Modifier un élève' : 'Inscrire un élève';
$message = ''; $messageType = '';

// Get families for linking siblings
$familles = $db->query("SELECT DISTINCT famille_id, MIN(nom||' '||prenoms) AS repr
    FROM eleves WHERE famille_id IS NOT NULL GROUP BY famille_id ORDER BY repr")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $anneeId  = (int)$_POST['annee_id'];
    $niveauId = (int)$_POST['niveau_id'];
    $nom      = trim($_POST['nom'] ?? '');
    $prenoms  = trim($_POST['prenoms'] ?? '');
    $sexe     = $_POST['sexe'] ?? 'M';
    $dateNais = $_POST['date_naissance'] ?? '';
    $lieuNais = trim($_POST['lieu_naissance'] ?? '');
    $quartier = trim($_POST['quartier'] ?? '');
    $adresse  = trim($_POST['adresse'] ?? '');
    $nomPere  = trim($_POST['nom_pere'] ?? '');
    $telPere  = trim($_POST['tel_pere'] ?? '');
    $nomMere  = trim($_POST['nom_mere'] ?? '');
    $telMere  = trim($_POST['tel_mere'] ?? '');
    $nomTuteur= trim($_POST['nom_tuteur'] ?? '');
    $telTuteur= trim($_POST['tel_tuteur'] ?? '');
    $urgence  = trim($_POST['contact_urgence'] ?? '');
    $statutEl = $_POST['statut_eleve'] ?? 'nouveau';
    $familleId= $_POST['famille_id'] ? (int)$_POST['famille_id'] : null;
    $nouvelleFamille = !empty($_POST['nouvelle_famille']);

    // Docs
    $docsData = [
        'photos_identite'     => !empty($_POST['photos_identite']),
        'acte_naissance'      => !empty($_POST['acte_naissance']),
        'carnet_vaccination'  => !empty($_POST['carnet_vaccination']),
        'certificat_transfert'=> !empty($_POST['certificat_transfert']),
        'livret_scolaire'     => !empty($_POST['livret_scolaire']),
        'certificat_medical'  => !empty($_POST['certificat_medical']),
    ];

    if ($nom && $prenoms && $dateNais && $niveauId && $anneeId) {
        try {
            if ($nouvelleFamille) {
                // Create new family ID
                $maxFam = $db->query("SELECT COALESCE(MAX(famille_id),0)+1 FROM eleves")->fetchColumn();
                $familleId = (int)$maxFam;
            }

            if ($eleve) {
                // Update
                $stmt = $db->prepare("UPDATE eleves SET nom=?,prenoms=?,sexe=?,date_naissance=?,lieu_naissance=?,
                    quartier=?,adresse=?,nom_pere=?,tel_pere=?,nom_mere=?,tel_mere=?,nom_tuteur=?,tel_tuteur=?,
                    contact_urgence=?,annee_id=?,niveau_id=?,statut_eleve=?,famille_id=? WHERE id=?");
                $stmt->execute([$nom,$prenoms,$sexe,$dateNais,$lieuNais,$quartier,$adresse,$nomPere,$telPere,
                    $nomMere,$telMere,$nomTuteur,$telTuteur,$urgence,$anneeId,$niveauId,$statutEl,$familleId,$editId]);

                // Update docs
                $db->prepare("UPDATE documents_eleve SET photos_identite=?,acte_naissance=?,carnet_vaccination=?,
                    certificat_transfert=?,livret_scolaire=?,certificat_medical=?,date_maj=NOW() WHERE eleve_id=?")
                    ->execute([...(array_values($docsData)), $editId]);
                auditLog($user['user_id'], 'MODIF_ELEVE', 'eleves', $editId, "Modification: $nom $prenoms");
                $message = 'student.saved'; $messageType = 'success';
            } else {
                // Insert new student
                $matricule = generateMatricule($anneeId);
                $stmt = $db->prepare("INSERT INTO eleves (matricule,nom,prenoms,sexe,date_naissance,lieu_naissance,
                    quartier,adresse,nom_pere,tel_pere,nom_mere,tel_mere,nom_tuteur,tel_tuteur,contact_urgence,
                    annee_id,niveau_id,statut_eleve,famille_id,inscrit_par)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id");
                $stmt->execute([$matricule,$nom,$prenoms,$sexe,$dateNais,$lieuNais,$quartier,$adresse,
                    $nomPere,$telPere,$nomMere,$telMere,$nomTuteur,$telTuteur,$urgence,
                    $anneeId,$niveauId,$statutEl,$familleId,$user['user_id']]);
                $newId = (int)$stmt->fetchColumn();

                // Insert docs
                $db->prepare("INSERT INTO documents_eleve (eleve_id,photos_identite,acte_naissance,carnet_vaccination,
                    certificat_transfert,livret_scolaire,certificat_medical) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$newId, ...array_values($docsData)]);
                auditLog($user['user_id'], 'CREATION_ELEVE', 'eleves', $newId, "Inscription: $nom $prenoms — $matricule");
                redirect("/secretary/student_view.php?id=$newId&created=1");
            }
        } catch (Exception $ex) {
            $message = 'common.error'; $messageType = 'danger';
            error_log($ex->getMessage());
        }
    } else {
        $message = 'common.required'; $messageType = 'danger';
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="fade-in-load">
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="page-title mb-0">
        <i class="bi bi-<?= $eleve ? 'pencil-square' : 'person-plus' ?>"></i>
        <span data-i18n="student.<?= $eleve ? 'edit' : 'new' ?>"><?= $eleve ? 'Modifier' : 'Inscrire un élève' ?></span>
        <?php if ($eleve): ?><small class="text-muted fs-6 ms-2"><?= e($eleve['matricule']) ?></small><?php endif; ?>
    </h1>
    <a href="/secretary/search.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Retour
    </a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>" data-i18n="<?= $message ?>">
    <?= $message === 'student.saved' ? 'Élève enregistré avec succès.' : ($message === 'common.error' ? 'Une erreur est survenue.' : 'Tous les champs obligatoires doivent être remplis.') ?>
</div>
<?php endif; ?>

<form method="POST" novalidate>
    <?= csrfField() ?>

    <!-- Section 1: Identité -->
    <div class="card mb-3">
        <div class="card-header bg-primary-siniyat text-white">
            <i class="bi bi-person-badge me-2"></i><span>Identité</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" data-i18n="student.nom">Nom <span class="text-danger">*</span></label>
                    <input type="text" name="nom" class="form-control" required
                           value="<?= e($eleve['nom'] ?? $_POST['nom'] ?? '') ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label" data-i18n="student.prenoms">Prénom(s) <span class="text-danger">*</span></label>
                    <input type="text" name="prenoms" class="form-control" required
                           value="<?= e($eleve['prenoms'] ?? $_POST['prenoms'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" data-i18n="student.sexe">Sexe <span class="text-danger">*</span></label>
                    <select name="sexe" class="form-select" required>
                        <option value="M" <?= ($eleve['sexe']??'M')==='M'?'selected':'' ?> data-i18n="student.sexe_m">Masculin</option>
                        <option value="F" <?= ($eleve['sexe']??'')==='F'?'selected':'' ?> data-i18n="student.sexe_f">Féminin</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" data-i18n="student.date_naissance">Date de naissance <span class="text-danger">*</span></label>
                    <input type="date" name="date_naissance" class="form-control" required
                           value="<?= e($eleve['date_naissance'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" data-i18n="student.lieu_naissance">Lieu de naissance</label>
                    <input type="text" name="lieu_naissance" class="form-control"
                           value="<?= e($eleve['lieu_naissance'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" data-i18n="student.statut">Statut</label>
                    <select name="statut_eleve" class="form-select">
                        <option value="nouveau" <?= ($eleve['statut_eleve']??'nouveau')==='nouveau'?'selected':'' ?> data-i18n="student.nouveau">Nouveau</option>
                        <option value="ancien"  <?= ($eleve['statut_eleve']??'')==='ancien'?'selected':'' ?> data-i18n="student.ancien">Ancien</option>
                        <option value="redoublant" <?= ($eleve['statut_eleve']??'')==='redoublant'?'selected':'' ?> data-i18n="student.redoublant">Redoublant</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" data-i18n="student.annee">Année scolaire <span class="text-danger">*</span></label>
                    <select name="annee_id" class="form-select" required>
                        <?php foreach ($allYears as $y): ?>
                        <option value="<?= $y['id'] ?>" <?= ($eleve['annee_id']??$activeYear['id'])==$y['id']?'selected':'' ?>>
                            <?= e($y['libelle']) ?><?= $y['statut']==='active'?' (active)':'' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" data-i18n="student.classe">Classe / Niveau <span class="text-danger">*</span></label>
                    <select name="niveau_id" class="form-select" required>
                        <option value="">-- Sélectionner --</option>
                        <?php
                        $lastSection = '';
                        foreach ($niveaux as $n):
                            if ($n['section'] !== $lastSection) {
                                echo '<optgroup label="' . ($n['section'] === 'maternelle' ? 'Maternelle' : 'Primaire') . '">';
                                $lastSection = $n['section'];
                            }
                        ?>
                        <option value="<?= $n['id'] ?>" <?= ($eleve['niveau_id']??0)==$n['id']?'selected':'' ?>>
                            <?= e($n['nom_fr']) ?>
                        </option>
                        <?php endforeach; ?></optgroup>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Résidence & Famille -->
    <div class="card mb-3">
        <div class="card-header bg-primary-siniyat text-white">
            <i class="bi bi-house me-2"></i>Résidence & Famille
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" data-i18n="student.quartier">Quartier</label>
                    <input type="text" name="quartier" class="form-control" value="<?= e($eleve['quartier']??'') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label" data-i18n="student.adresse">Adresse</label>
                    <input type="text" name="adresse" class="form-control" value="<?= e($eleve['adresse']??'') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" data-i18n="student.nom_pere">Nom du père</label>
                    <input type="text" name="nom_pere" class="form-control" value="<?= e($eleve['nom_pere']??'') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" data-i18n="student.tel_pere">Tél. père</label>
                    <input type="tel" name="tel_pere" class="form-control" value="<?= e($eleve['tel_pere']??'') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" data-i18n="student.nom_mere">Nom de la mère</label>
                    <input type="text" name="nom_mere" class="form-control" value="<?= e($eleve['nom_mere']??'') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" data-i18n="student.tel_mere">Tél. mère</label>
                    <input type="tel" name="tel_mere" class="form-control" value="<?= e($eleve['tel_mere']??'') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" data-i18n="student.nom_tuteur">Tuteur légal</label>
                    <input type="text" name="nom_tuteur" class="form-control" value="<?= e($eleve['nom_tuteur']??'') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" data-i18n="student.tel_tuteur">Tél. tuteur</label>
                    <input type="tel" name="tel_tuteur" class="form-control" value="<?= e($eleve['tel_tuteur']??'') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" data-i18n="student.contact_urgence">Contact d'urgence</label>
                    <input type="text" name="contact_urgence" class="form-control" value="<?= e($eleve['contact_urgence']??'') ?>">
                </div>
                <!-- Fratrie -->
                <div class="col-12">
                    <div class="section-divider"><i class="bi bi-people me-1"></i>Fratrie (réductions)</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Famille existante (fratrie)</label>
                    <select name="famille_id" class="form-select">
                        <option value="">-- Aucune --</option>
                        <?php foreach ($familles as $f): ?>
                        <option value="<?= $f['famille_id'] ?>" <?= ($eleve['famille_id']??0)==$f['famille_id']?'selected':'' ?>>
                            Famille #<?= $f['famille_id'] ?> — <?= e($f['repr']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="nouvelle_famille" id="nouvelle_famille">
                        <label class="form-check-label" for="nouvelle_famille">
                            Créer une nouvelle famille (lier à un frère/sœur plus tard)
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 3: Documents -->
    <div class="card mb-3">
        <div class="card-header bg-primary-siniyat text-white d-flex justify-content-between">
            <span><i class="bi bi-folder me-2"></i><span data-i18n="student.documents">Documents administratifs</span></span>
            <div class="form-check form-check-inline m-0">
                <input class="form-check-input" type="checkbox" id="docs_select_all">
                <label class="form-check-label text-white small" for="docs_select_all" data-i18n="student.select_all">Tout sélectionner</label>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <?php
                $docFields = [
                    'photos_identite'      => 'student.photos_identite',
                    'acte_naissance'       => 'student.acte_naissance',
                    'carnet_vaccination'   => 'student.carnet_vaccination',
                    'certificat_transfert' => 'student.certificat_transfert',
                    'livret_scolaire'      => 'student.livret_scolaire',
                    'certificat_medical'   => 'student.certificat_medical',
                ];
                foreach ($docFields as $field => $i18nKey):
                    $checked = $docs ? $docs[$field] : false;
                ?>
                <div class="col-md-4 col-sm-6">
                    <div class="form-check">
                        <input class="form-check-input doc-checkbox" type="checkbox"
                               name="<?= $field ?>" id="doc_<?= $field ?>"
                               <?= $checked ? 'checked' : '' ?>>
                        <label class="form-check-label" for="doc_<?= $field ?>" data-i18n="<?= $i18nKey ?>">
                            <?= $i18nKey ?>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Submit -->
    <div class="d-flex gap-2 justify-content-end">
        <a href="/secretary/search.php" class="btn btn-outline-secondary px-4" data-i18n="common.cancel">Annuler</a>
        <button type="submit" class="btn btn-primary-siniyat px-5">
            <i class="bi bi-check-lg me-2"></i><span data-i18n="student.save">Enregistrer</span>
        </button>
    </div>
</form>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
