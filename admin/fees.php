<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireAdmin();
$db   = getDB();
$pageTitle = 'Grille des frais';
$message = ''; $messageType = '';
$activeYear = getActiveYear();
$allYears   = getAllYears();
$niveaux    = getNiveaux();

$yearId = (int)($_GET['annee'] ?? $_POST['annee_id'] ?? $activeYear['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_params') {
        $redComplet = (float)str_replace(',','.', $_POST['reduction_paiement_complet'] ?? '2');
        $redFratrie = (float)str_replace(',','.', $_POST['reduction_fratrie'] ?? '2');
        foreach ([['reduction_paiement_complet', $redComplet], ['reduction_fratrie', $redFratrie]] as [$cle, $val]) {
            try {
                $db->prepare("INSERT INTO parametres (cle,valeur,updated_at) VALUES (?,?,NOW())")->execute([$cle,$val]);
            } catch (PDOException $e) {
                $db->prepare("UPDATE parametres SET valeur=?, updated_at=NOW() WHERE cle=?")->execute([$val,$cle]);
            }
        }
        auditLog($user['user_id'],'MODIF_PARAMETRES','parametres',0,"Réductions: complet={$redComplet}%, fratrie={$redFratrie}%");
        $message = 'Paramètres de réduction mis à jour.'; $messageType = 'success';
    }

    if ($action === 'save_grid') {
        $anneeId  = (int)$_POST['annee_id'];
        $niveauId = (int)$_POST['niveau_id'];
        $fraisInscription = (float)str_replace(',','.', $_POST['frais_inscription']??'0');

        // Upsert grid (compatible MySQL et PostgreSQL)
        try {
            $db->prepare("INSERT INTO grille_frais (annee_id,niveau_id,frais_inscription) VALUES (?,?,?)")
               ->execute([$anneeId,$niveauId,$fraisInscription]);
        } catch (PDOException $e) {
            $db->prepare("UPDATE grille_frais SET frais_inscription=? WHERE annee_id=? AND niveau_id=?")
               ->execute([$fraisInscription,$anneeId,$niveauId]);
        }

        $gridStmt = $db->prepare("SELECT id FROM grille_frais WHERE annee_id=? AND niveau_id=?");
        $gridStmt->execute([$anneeId,$niveauId]);
        $gridId = (int)$gridStmt->fetchColumn();

        // Tranches
        $tranches = $_POST['tranches'] ?? [];
        foreach ($tranches as $num => $t) {
            $num = (int)$num;
            $libFr = trim($t['libelle_fr'] ?? '');
            $libEn = trim($t['libelle_en'] ?? '');
            $montant = (float)str_replace(',','.', $t['montant']??'0');
            $echeance = trim($t['echeance'] ?? '');
            if ($libFr && $montant > 0) {
                try {
                    $db->prepare("INSERT INTO tranches (grille_id,numero,libelle_fr,libelle_en,montant,echeance_indicative)
                        VALUES (?,?,?,?,?,?)")
                        ->execute([$gridId,$num,$libFr,$libEn,$montant,$echeance?:null]);
                } catch (PDOException $e) {
                    $db->prepare("UPDATE tranches SET libelle_fr=?,libelle_en=?,montant=?,echeance_indicative=?
                        WHERE grille_id=? AND numero=?")
                        ->execute([$libFr,$libEn,$montant,$echeance?:null,$gridId,$num]);
                }
            }
        }
        // Delete removed tranches
        if (!empty($_POST['delete_tranches'])) {
            foreach ((array)$_POST['delete_tranches'] as $delNum) {
                $db->prepare("DELETE FROM tranches WHERE grille_id=? AND numero=?")->execute([$gridId,(int)$delNum]);
            }
        }
        auditLog($user['user_id'],'MODIF_GRILLE_FRAIS','grille_frais',$gridId,"Année $anneeId, Niveau $niveauId");
        $message = 'Grille mise à jour avec succès.'; $messageType = 'success';
    }
}

// Load grid for selected year
$grids = $db->prepare("
    SELECT g.*, n.nom_fr, n.nom_en, n.ordre, n.section,
        (SELECT SUM(t.montant) FROM tranches t WHERE t.grille_id=g.id) AS total_tranches
    FROM grille_frais g
    JOIN niveaux n ON n.id = g.niveau_id
    WHERE g.annee_id = ?
    ORDER BY n.ordre
");
$grids->execute([$yearId]);
$gridRows = $grids->fetchAll();

// Get tranches for all grids
$allTranches = [];
if ($gridRows) {
    $gridIds = array_column($gridRows, 'id');
    $inStr = implode(',', array_fill(0, count($gridIds), '?'));
    $trStmt = $db->prepare("SELECT * FROM tranches WHERE grille_id IN ($inStr) ORDER BY grille_id, numero");
    $trStmt->execute($gridIds);
    foreach ($trStmt->fetchAll() as $t) {
        $allTranches[$t['grille_id']][] = $t;
    }
}

// Handle grid initialization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'init_grids') {
    csrfCheck();
    $initYearId = (int)$_POST['annee_id'];
    $niveauxAll = getDB()->query("SELECT id FROM niveaux WHERE actif=TRUE")->fetchAll();
    foreach ($niveauxAll as $n) {
        try {
            $db->prepare("INSERT INTO grille_frais (annee_id,niveau_id,frais_inscription) VALUES (?,?,0)")
               ->execute([$initYearId, $n['id']]);
        } catch (PDOException $e) { /* doublon ignoré */ }
    }
    auditLog($user['user_id'],'INIT_GRILLE_FRAIS','grille_frais',0,"Initialisation grilles année $initYearId");
    // Reload
    $grids->execute([$initYearId]);
    $gridRows = $grids->fetchAll();
    if ($gridRows) {
        $gridIds = array_column($gridRows, 'id');
        $inStr = implode(',', array_fill(0, count($gridIds), '?'));
        $trStmt = $db->prepare("SELECT * FROM tranches WHERE grille_id IN ($inStr) ORDER BY grille_id, numero");
        $trStmt->execute($gridIds);
        $allTranches = [];
        foreach ($trStmt->fetchAll() as $t) { $allTranches[$t['grille_id']][] = $t; }
    }
    $message = 'Grilles initialisées pour toutes les classes.'; $messageType = 'success';
}

$editGridId = (int)($_GET['grid'] ?? 0);
$editGrid   = null;
$editTranches = [];
if ($editGridId) {
    foreach ($gridRows as $g) if ($g['id']==$editGridId) { $editGrid=$g; break; }
    $editTranches = $allTranches[$editGridId] ?? [];
}

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="fade-in-load">
<h1 class="page-title"><i class="bi bi-currency-exchange"></i> <span data-i18n="fees.title">Grille des frais</span></h1>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
    <?= e($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Year selector + init -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <form class="d-flex align-items-center gap-2">
                <label class="fw-semibold text-muted small" data-i18n="nav.years">Année :</label>
                <select name="annee" class="form-select form-select-sm" style="width:150px;" onchange="this.form.submit()">
                    <?php foreach ($allYears as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= $y['id']==$yearId?'selected':'' ?>><?= e($y['libelle']) ?><?= $y['statut']==='active'?' ★':'' ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if (empty($gridRows)): ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="init_grids">
                <input type="hidden" name="annee_id" value="<?= $yearId ?>">
                <button type="submit" class="btn btn-primary-siniyat btn-sm">
                    <i class="bi bi-magic me-1"></i>Initialiser les grilles pour toutes les classes
                </button>
            </form>
            <?php else: ?>
            <div class="d-flex align-items-center gap-3">
                <small class="text-muted"><i class="bi bi-tag me-1"></i>Réductions : <strong><?= e(getParametre('reduction_paiement_complet',(string)REDUCTION_PAIEMENT_COMPLET)) ?>%</strong> paiement complet &nbsp;|&nbsp; <strong><?= e(getParametre('reduction_fratrie',(string)REDUCTION_FRATRIE)) ?>%</strong> / enfant supplémentaire</small>
                <form method="POST" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="init_grids">
                    <input type="hidden" name="annee_id" value="<?= $yearId ?>">
                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Ajouter les nouvelles classes manquantes">
                        <i class="bi bi-plus-circle me-1"></i>Ajouter classes manquantes
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Paramètres de réduction -->
<div class="card mb-3">
    <div class="card-header bg-primary-siniyat text-white">
        <i class="bi bi-tag me-2"></i>Paramètres de réduction
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3 align-items-end">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_params">
            <input type="hidden" name="annee_id" value="<?= $yearId ?>">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">
                    <i class="bi bi-cash-stack me-1"></i>Réduction paiement complet (%)
                </label>
                <div class="input-group input-group-sm">
                    <input type="number" name="reduction_paiement_complet" class="form-control"
                           min="0" max="50" step="0.5"
                           value="<?= e(getParametre('reduction_paiement_complet', (string)REDUCTION_PAIEMENT_COMPLET)) ?>">
                    <span class="input-group-text">%</span>
                </div>
                <div class="form-text">Appliquée si l'élève paie en une seule fois.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">
                    <i class="bi bi-people me-1"></i>Réduction fratrie (% / enfant supplémentaire)
                </label>
                <div class="input-group input-group-sm">
                    <input type="number" name="reduction_fratrie" class="form-control"
                           min="0" max="50" step="0.5"
                           value="<?= e(getParametre('reduction_fratrie', (string)REDUCTION_FRATRIE)) ?>">
                    <span class="input-group-text">%</span>
                </div>
                <div class="form-text">Par enfant supplémentaire de la même famille.</div>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary-siniyat btn-sm w-100">
                    <i class="bi bi-check-lg me-1"></i>Enregistrer les réductions
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <!-- Grid overview -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-table me-2"></i>Grille <?= e($allYears[array_search($yearId, array_column($allYears,'id'))]['libelle']??'') ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Nb Tranches</th>
                                <th>Total Tranches</th>
                                <th>Frais Inscription</th>
                                <th>Total Général</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gridRows as $g): ?>
                            <tr>
                                <td><strong><?= e($g['nom_fr']) ?></strong></td>
                                <td><?= count($allTranches[$g['id']]??[]) ?></td>
                                <td><?= formatMontant((float)($g['total_tranches']??0)) ?></td>
                                <td><?= formatMontant((float)$g['frais_inscription']) ?></td>
                                <td class="fw-bold"><?= formatMontant((float)($g['total_tranches']??0) + (float)$g['frais_inscription']) ?></td>
                                <td>
                                    <a href="?annee=<?= $yearId ?>&grid=<?= $g['id'] ?>"
                                       class="btn btn-sm btn-outline-siniyat btn-action">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit form -->
    <div class="col-lg-5">
        <?php if ($editGrid): ?>
        <div class="card">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-pencil me-2"></i>Modifier : <?= e($editGrid['nom_fr']) ?>
            </div>
            <div class="card-body">
                <form method="POST" id="grid-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save_grid">
                    <input type="hidden" name="annee_id" value="<?= $yearId ?>">
                    <input type="hidden" name="niveau_id" value="<?= $editGrid['niveau_id'] ?>">

                    <div class="mb-3">
                        <label class="form-label small" data-i18n="fees.inscription">Frais d'inscription (FCFA)</label>
                        <input type="number" name="frais_inscription" class="form-control" min="0" step="500"
                               value="<?= (float)$editGrid['frais_inscription'] ?>">
                    </div>

                    <div class="section-divider"><i class="bi bi-list-ol me-1"></i>Tranches</div>
                    <div id="tranches-container">
                        <?php foreach ($editTranches as $i => $t): ?>
                        <div class="tranche-row border rounded p-2 mb-2" data-num="<?= $t['numero'] ?>">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary-siniyat">Tranche <?= $t['numero'] ?></span>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-action"
                                        onclick="removeTranche(this,<?= $t['numero'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="text" name="tranches[<?= $t['numero'] ?>][libelle_fr]"
                                           class="form-control form-control-sm" placeholder="Libellé FR"
                                           value="<?= e($t['libelle_fr']) ?>">
                                </div>
                                <div class="col-6">
                                    <input type="text" name="tranches[<?= $t['numero'] ?>][libelle_en]"
                                           class="form-control form-control-sm" placeholder="Label EN"
                                           value="<?= e($t['libelle_en']) ?>">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="tranches[<?= $t['numero'] ?>][montant]"
                                           class="form-control form-control-sm" placeholder="Montant FCFA" min="0"
                                           value="<?= (float)$t['montant'] ?>">
                                </div>
                                <div class="col-6">
                                    <input type="text" name="tranches[<?= $t['numero'] ?>][echeance]"
                                           class="form-control form-control-sm" placeholder="Échéance"
                                           value="<?= e($t['echeance_indicative']??'') ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="deleted-tranches"></div>

                    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addTranche()">
                        <i class="bi bi-plus me-1"></i>Ajouter une tranche
                    </button>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary-siniyat">
                            <i class="bi bi-check-lg me-1"></i><span data-i18n="fees.save">Enregistrer</span>
                        </button>
                        <a href="?annee=<?= $yearId ?>" class="btn btn-outline-secondary" data-i18n="common.cancel">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-arrow-left fs-2 d-block mb-2"></i>
                Sélectionnez une classe pour modifier sa grille de frais.
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<?php
$nextTranche = count($editTranches ?? []) + 1;
$extraScripts = <<<HTML
<script>
let trancheCount = {$nextTranche};
function addTranche() {
    const n = trancheCount++;
    const html = `<div class="tranche-row border rounded p-2 mb-2" data-num="\${n}">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="badge bg-primary-siniyat">Tranche \${n}</span>
            <button type="button" class="btn btn-sm btn-outline-danger btn-action" onclick="removeTranche(this,\${n})"><i class="bi bi-trash"></i></button>
        </div>
        <div class="row g-2">
            <div class="col-6"><input type="text" name="tranches[\${n}][libelle_fr]" class="form-control form-control-sm" placeholder="Libellé FR"></div>
            <div class="col-6"><input type="text" name="tranches[\${n}][libelle_en]" class="form-control form-control-sm" placeholder="Label EN"></div>
            <div class="col-6"><input type="number" name="tranches[\${n}][montant]" class="form-control form-control-sm" placeholder="Montant FCFA" min="0"></div>
            <div class="col-6"><input type="text" name="tranches[\${n}][echeance]" class="form-control form-control-sm" placeholder="Échéance"></div>
        </div></div>`;
    document.getElementById('tranches-container').insertAdjacentHTML('beforeend', html);
}
function removeTranche(btn, num) {
    btn.closest('.tranche-row').remove();
    const inp = document.createElement('input');
    inp.type='hidden'; inp.name='delete_tranches[]'; inp.value=num;
    document.getElementById('deleted-tranches').appendChild(inp);
}
</script>
HTML;
include dirname(__DIR__) . '/includes/footer.php';
?>
