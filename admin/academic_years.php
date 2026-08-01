<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireAdmin();
$db   = getDB();
$pageTitle = 'Années scolaires';
$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_year') {
        $libelle = trim($_POST['libelle'] ?? '');
        if ($libelle && preg_match('/^\d{4}-\d{4}$/', $libelle)) {
            try {
                // Get current active year
                $prevYear = $db->query("SELECT * FROM annees_scolaires WHERE statut='active' ORDER BY id DESC LIMIT 1")->fetch();

                // Archive current active year
                $db->exec("UPDATE annees_scolaires SET statut='cloturee' WHERE statut='active'");

                // Create new year
                $db->prepare("INSERT INTO annees_scolaires (libelle, statut) VALUES (?,?)")
                   ->execute([$libelle, 'active']);
                $newYearId = (int)$db->lastInsertId();

                if (!$newYearId) {
                    $newYearId = (int)$db->query("SELECT id FROM annees_scolaires WHERE libelle='$libelle'")->fetchColumn();
                }

                // Copy fee structure from previous year
                if ($prevYear) {
                    $prevGrids = $db->prepare("SELECT g.*, array_agg(t.*) FROM grille_frais g LEFT JOIN tranches t ON t.grille_id=g.id WHERE g.annee_id=? GROUP BY g.id");
                    $prevGrids->execute([$prevYear['id']]);
                    $grids = $db->prepare("SELECT * FROM grille_frais WHERE annee_id=?")->execute([$prevYear['id']]);

                    // Copy grids
                    $gridRows = $db->prepare("SELECT g.*, n.id AS niv_id FROM grille_frais g JOIN niveaux n ON n.id=g.niveau_id WHERE g.annee_id=?");
                    $gridRows->execute([$prevYear['id']]);
                    foreach ($gridRows->fetchAll() as $g) {
                        $db->prepare("INSERT INTO grille_frais (annee_id,niveau_id,frais_inscription) VALUES (?,?,?)
                            ON CONFLICT (annee_id,niveau_id) DO NOTHING")
                            ->execute([$newYearId, $g['niveau_id'], $g['frais_inscription']]);
                        $newGridId = (int)$db->prepare("SELECT id FROM grille_frais WHERE annee_id=? AND niveau_id=?")
                                            ->execute([$newYearId,$g['niveau_id']]) ? $db->query("SELECT id FROM grille_frais WHERE annee_id=$newYearId AND niveau_id={$g['niveau_id']}")->fetchColumn() : 0;
                        // Copy tranches
                        $prevTranches = $db->prepare("SELECT * FROM tranches WHERE grille_id=?");
                        $prevTranches->execute([$g['id']]);
                        foreach ($prevTranches->fetchAll() as $t) {
                            $db->prepare("INSERT INTO tranches (grille_id,numero,libelle_fr,libelle_en,montant,echeance_indicative)
                                VALUES (?,?,?,?,?,?) ON CONFLICT (grille_id,numero) DO NOTHING")
                                ->execute([$newGridId,$t['numero'],$t['libelle_fr'],$t['libelle_en'],$t['montant'],$t['echeance_indicative']]);
                        }
                    }
                }
                auditLog($user['user_id'],'CREATION_ANNEE','annees_scolaires',$newYearId,"Nouvelle année: $libelle");
                $message = "Année scolaire $libelle créée. Structure copiée depuis l'année précédente."; $messageType = 'success';
            } catch (Exception $ex) {
                $message = 'Erreur : ' . $ex->getMessage(); $messageType = 'danger';
                error_log($ex->getMessage());
            }
        } else {
            $message = 'Format invalide. Utilisez AAAA-AAAA (ex: 2027-2028).'; $messageType = 'danger';
        }
    } elseif ($action === 'set_active') {
        $yearId = (int)$_POST['year_id'];
        $db->exec("UPDATE annees_scolaires SET statut='cloturee' WHERE statut='active'");
        $db->prepare("UPDATE annees_scolaires SET statut='active' WHERE id=?")->execute([$yearId]);
        auditLog($user['user_id'],'ACTIVATION_ANNEE','annees_scolaires',$yearId,'Année activée');
        $message = 'Année active mise à jour.'; $messageType = 'success';
    } elseif ($action === 'promote') {
        // Mass promotion
        $fromNiveauId = (int)$_POST['from_niveau_id'];
        $fromAnneeId  = (int)$_POST['from_annee_id'];
        $toNiveauId   = (int)$_POST['to_niveau_id'];
        $toAnneeId    = (int)$_POST['to_annee_id'];
        $selectedIds  = $_POST['student_ids'] ?? [];

        if ($fromNiveauId && $toNiveauId && $toAnneeId && !empty($selectedIds)) {
            $count = 0;
            foreach ($selectedIds as $sid) {
                $sid = (int)$sid;
                $orig = $db->prepare("SELECT * FROM eleves WHERE id=?");
                $orig->execute([$sid]);
                $s = $orig->fetch();
                if ($s) {
                    $newMat = generateMatricule($toAnneeId);
                    $db->prepare("INSERT INTO eleves (matricule,nom,prenoms,sexe,date_naissance,lieu_naissance,
                        quartier,adresse,nom_pere,tel_pere,nom_mere,tel_mere,nom_tuteur,tel_tuteur,contact_urgence,
                        annee_id,niveau_id,statut_eleve,famille_id,inscrit_par)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'ancien',?,?)")
                        ->execute([$newMat,$s['nom'],$s['prenoms'],$s['sexe'],$s['date_naissance'],$s['lieu_naissance'],
                            $s['quartier'],$s['adresse'],$s['nom_pere'],$s['tel_pere'],$s['nom_mere'],$s['tel_mere'],
                            $s['nom_tuteur'],$s['tel_tuteur'],$s['contact_urgence'],
                            $toAnneeId,$toNiveauId,$s['famille_id'],$user['user_id']]);
                    $count++;
                }
            }
            auditLog($user['user_id'],'PROMOTION_MASSE','eleves',0,"$count élèves promus vers niveau $toNiveauId, année $toAnneeId");
            $message = "$count élève(s) promu(s) avec succès."; $messageType = 'success';
        }
    }
}

$allYears = getAllYears();
$niveaux  = getNiveaux();
$activeYear = getActiveYear();

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="fade-in-load">
<h1 class="page-title"><i class="bi bi-calendar-range"></i> <span data-i18n="year.title">Années scolaires</span></h1>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
    <?= e($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Create new year -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-plus-circle me-2"></i><span data-i18n="year.new">Nouvelle année scolaire</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create_year">
                    <div class="mb-3">
                        <label class="form-label">Libellé (ex: 2027-2028)</label>
                        <input type="text" name="libelle" class="form-control" placeholder="2027-2028"
                               pattern="\d{4}-\d{4}" required>
                        <div class="form-text" data-i18n="year.copy_from">
                            Copie la structure de l'année précédente automatiquement.
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary-siniyat w-100" onclick="return confirm('Créer cette année et archiver l\'année active ?')">
                        <i class="bi bi-calendar-plus me-1"></i><span data-i18n="year.create">Créer l'année</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Years list -->
        <div class="card">
            <div class="card-header bg-primary-siniyat text-white"><i class="bi bi-list-ul me-2"></i>Toutes les années</div>
            <div class="card-body p-0">
                <?php foreach ($allYears as $y): ?>
                <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
                    <div>
                        <strong><?= e($y['libelle']) ?></strong>
                        <?php if ($y['statut']==='active'): ?>
                        <span class="badge bg-success ms-1" data-i18n="year.active">Active</span>
                        <?php else: ?>
                        <span class="badge bg-secondary ms-1" data-i18n="year.closed">Clôturée</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($y['statut']!=='active'): ?>
                    <form method="POST" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="set_active">
                        <input type="hidden" name="year_id" value="<?= $y['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success" data-i18n="year.set_active">Activer</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Mass promotion -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-arrow-up-circle me-2"></i><span data-i18n="year.promote">Promotion en masse des élèves</span>
            </div>
            <div class="card-body">
                <form method="POST" id="promote-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="promote">
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small">Année source</label>
                            <select name="from_annee_id" class="form-select form-select-sm" id="from_annee" onchange="loadStudents()">
                                <?php foreach ($allYears as $y): ?>
                                <option value="<?= $y['id'] ?>"><?= e($y['libelle']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Classe source</label>
                            <select name="from_niveau_id" class="form-select form-select-sm" id="from_niveau" onchange="loadStudents()">
                                <option value="">-- Choisir --</option>
                                <?php foreach ($niveaux as $n): ?>
                                <option value="<?= $n['id'] ?>"><?= e($n['nom_fr']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Vers l'année</label>
                            <select name="to_annee_id" class="form-select form-select-sm">
                                <?php foreach ($allYears as $y): ?>
                                <option value="<?= $y['id'] ?>" <?= $y['statut']==='active'?'selected':'' ?>><?= e($y['libelle']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Vers la classe</label>
                            <select name="to_niveau_id" class="form-select form-select-sm">
                                <option value="">-- Choisir --</option>
                                <?php foreach ($niveaux as $n): ?>
                                <option value="<?= $n['id'] ?>"><?= e($n['nom_fr']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div id="students-to-promote" class="text-muted text-center py-4">
                        <i class="bi bi-info-circle"></i> Sélectionnez une classe source pour afficher les élèves.
                    </div>
                    <div class="d-grid mt-2" id="promote-btn-wrap" style="display:none!important;">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Promouvoir les élèves sélectionnés ?')">
                            <i class="bi bi-arrow-up-circle me-1"></i>Promouvoir les sélectionnés
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<?php
$extraScripts = <<<'HTML'
<script>
async function loadStudents() {
    const anneeId = document.getElementById('from_annee').value;
    const niveauId = document.getElementById('from_niveau').value;
    if (!niveauId) return;
    const box = document.getElementById('students-to-promote');
    box.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
    const data = await apiGet('/api/students.php?annee_id='+anneeId+'&niveau_id='+niveauId+'&limit=200');
    const students = data.students || [];
    if (!students.length) { box.innerHTML = '<p class="text-muted text-center">Aucun élève dans cette classe.</p>'; return; }
    let html = '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="sel_all" onchange="document.querySelectorAll(\'.promo-cb\').forEach(c=>c.checked=this.checked)"><label class="form-check-label fw-semibold" for="sel_all">Tout sélectionner</label></div><div class="row g-1">';
    students.forEach(s => {
        html += `<div class="col-md-6"><div class="form-check"><input class="form-check-input promo-cb" type="checkbox" name="student_ids[]" value="${s.id}" id="s${s.id}"><label class="form-check-label small" for="s${s.id}">${s.nom} ${s.prenoms} <code>${s.matricule}</code></label></div></div>`;
    });
    html += '</div>';
    box.innerHTML = html;
    document.getElementById('promote-btn-wrap').style.display = '';
}
</script>
HTML;
include dirname(__DIR__) . '/includes/footer.php';
?>
