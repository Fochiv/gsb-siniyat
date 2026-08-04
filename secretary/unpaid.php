<?php
/**
 * Liste des élèves par statut de paiement
 * Accessible : admin + secrétaire
 */
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireLogin();
$db   = getDB();
$pageTitle  = 'Recouvrement — Statut paiements';
$activeYear = getActiveYear();
$allYears   = getAllYears();
$niveaux    = getNiveaux();

$yearId   = (int)($_GET['annee']   ?? $activeYear['id']);
$statut   = in_array($_GET['statut'] ?? '', ['impaye','partiel','paye','']) ? ($_GET['statut'] ?? '') : '';
$niveauId = (int)($_GET['niveau']  ?? 0);

// Build SQL for all students of the year with their financial summary
// We approximate total_du via tranches (no sibling reduction for performance; full-payment reduction via subquery)
$whereClause = ['e.annee_id = ?', 'e.actif = TRUE'];
$params = [$yearId];
if ($niveauId) { $whereClause[] = 'e.niveau_id = ?'; $params[] = $niveauId; }

$whereStr = implode(' AND ', $whereClause);

$rows = $db->prepare("
    SELECT
        e.id, e.matricule, e.nom, e.prenoms, e.sexe,
        n.nom_fr AS classe, n.section,
        COALESCE(SUM(CASE WHEN p.annule=FALSE THEN p.montant ELSE 0 END), 0) AS total_paye,
        COALESCE((
            SELECT SUM(t.montant)
            FROM grille_frais g JOIN tranches t ON t.grille_id=g.id
            WHERE g.annee_id=e.annee_id AND g.niveau_id=e.niveau_id
        ), 0) * (1 - (
            CASE WHEN EXISTS(
                SELECT 1 FROM paiements px
                WHERE px.eleve_id=e.id AND px.type_paiement='solde_complet' AND px.annule=FALSE
            ) THEN COALESCE((SELECT CAST(valeur AS DECIMAL) FROM parametres WHERE cle='reduction_paiement_complet'),2)
              ELSE 0 END
        ) / 100.0) AS total_du
    FROM eleves e
    JOIN niveaux n ON n.id = e.niveau_id
    LEFT JOIN paiements p ON p.eleve_id = e.id
    WHERE {$whereStr}
    GROUP BY e.id, e.matricule, e.nom, e.prenoms, e.sexe, n.nom_fr, n.section, n.id, n.ordre, e.annee_id, e.niveau_id
    ORDER BY n.ordre, e.nom, e.prenoms
");
$rows->execute($params);
$allStudents = $rows->fetchAll();

// Compute statut and filter
$students = [];
$totalReste = 0;
$counts = ['paye' => 0, 'partiel' => 0, 'impaye' => 0];

foreach ($allStudents as $s) {
    $du    = (float)$s['total_du'];
    $paye  = (float)$s['total_paye'];
    $reste = max(0, $du - $paye);

    if ($du <= 0 && $paye <= 0) {
        $st = 'impaye';
    } elseif ($paye >= $du && $du > 0) {
        $st = 'paye';
    } elseif ($paye > 0) {
        $st = 'partiel';
    } else {
        $st = 'impaye';
    }

    $counts[$st]++;
    $totalReste += $reste;

    if ($statut === '' || $statut === $st) {
        $students[] = array_merge($s, [
            'statut' => $st,
            'reste'  => $reste,
        ]);
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="fade-in-load">

<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
    <h1 class="page-title mb-0">
        <i class="bi bi-exclamation-circle"></i> Recouvrement &amp; Statut paiements
    </h1>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <a href="?annee=<?= $yearId ?>&statut=impaye<?= $niveauId ? '&niveau='.$niveauId : '' ?>" class="text-decoration-none">
            <div class="card stat-card stat-danger h-100 <?= $statut==='impaye'?'border-danger':'' ?>">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small">Impayés</div>
                        <div class="stat-value text-danger"><?= $counts['impaye'] ?></div>
                    </div>
                    <i class="bi bi-x-circle stat-icon text-danger"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="?annee=<?= $yearId ?>&statut=partiel<?= $niveauId ? '&niveau='.$niveauId : '' ?>" class="text-decoration-none">
            <div class="card stat-card stat-warning h-100 <?= $statut==='partiel'?'border-warning':'' ?>">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small">Partiels</div>
                        <div class="stat-value text-warning"><?= $counts['partiel'] ?></div>
                    </div>
                    <i class="bi bi-exclamation-circle stat-icon text-warning"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="?annee=<?= $yearId ?>&statut=paye<?= $niveauId ? '&niveau='.$niveauId : '' ?>" class="text-decoration-none">
            <div class="card stat-card stat-success h-100 <?= $statut==='paye'?'border-success':'' ?>">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small">Soldés</div>
                        <div class="stat-value text-success"><?= $counts['paye'] ?></div>
                    </div>
                    <i class="bi bi-check-circle stat-icon text-success"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card stat-danger h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Total à recouvrer</div>
                    <div class="stat-value text-danger" style="font-size:1.1rem;"><?= formatMontant($totalReste) ?></div>
                </div>
                <i class="bi bi-cash stat-icon text-danger"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">Année</label>
                <select name="annee" class="form-select form-select-sm" style="width:140px;" onchange="this.form.submit()">
                    <?php foreach ($allYears as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= $y['id']==$yearId?'selected':'' ?>>
                        <?= e($y['libelle']) ?><?= $y['statut']==='active'?' ★':'' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Statut</label>
                <select name="statut" class="form-select form-select-sm" style="width:140px;">
                    <option value="" <?= $statut===''?'selected':'' ?>>Tous</option>
                    <option value="impaye"  <?= $statut==='impaye' ?'selected':'' ?>>Impayés</option>
                    <option value="partiel" <?= $statut==='partiel'?'selected':'' ?>>Partiels</option>
                    <option value="paye"    <?= $statut==='paye'   ?'selected':'' ?>>Soldés</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Classe</label>
                <select name="niveau" class="form-select form-select-sm" style="width:160px;">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($niveaux as $n): ?>
                    <option value="<?= $n['id'] ?>" <?= $n['id']==$niveauId?'selected':'' ?>><?= e($n['nom_fr']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary-siniyat btn-sm">
                    <i class="bi bi-filter me-1"></i>Filtrer
                </button>
                <a href="?" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Students table -->
<div class="card">
    <div class="card-header bg-primary-siniyat text-white d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-table me-2"></i>
            <?php
            $lbls = [''=>'Tous les élèves','impaye'=>'Élèves impayés','partiel'=>'Paiements partiels','paye'=>'Élèves soldés'];
            echo e($lbls[$statut] ?? 'Élèves');
            ?>
            <span class="badge bg-light text-dark ms-2"><?= count($students) ?></span>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Nom &amp; Prénoms</th>
                        <th>Classe</th>
                        <th>Total dû</th>
                        <th>Total payé</th>
                        <th>Reste</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                    <?php
                        $bgs   = ['paye'=>'badge-paye','partiel'=>'badge-partiel','impaye'=>'badge-impaye'];
                        $lblst = ['paye'=>'Soldé','partiel'=>'Partiel','impaye'=>'Impayé'];
                    ?>
                    <tr>
                        <td data-label="Matricule"><code><?= e($s['matricule'] ?? '—') ?></code></td>
                        <td data-label="Nom">
                            <strong><?= e($s['nom']) ?></strong> <?= e($s['prenoms']) ?>
                            <span class="badge ms-1" style="font-size:.65rem;background:<?= $s['sexe']==='M'?'#e0f2fe':'#fce7f3' ?>;color:<?= $s['sexe']==='M'?'#0369a1':'#9d174d' ?>;"><?= $s['sexe'] ?></span>
                        </td>
                        <td data-label="Classe">
                            <?= e($s['classe']) ?>
                            <span class="badge ms-1 <?= $s['section']==='anglophone'?'badge-anglophone':'badge-francophone' ?>" style="font-size:.6rem;"><?= $s['section']==='anglophone'?'EN':'FR' ?></span>
                        </td>
                        <td data-label="Total dû" class="text-muted"><?= formatMontant((float)$s['total_du']) ?></td>
                        <td data-label="Payé" class="text-success fw-semibold"><?= formatMontant((float)$s['total_paye']) ?></td>
                        <td data-label="Reste" class="<?= $s['reste']>0?'text-danger fw-bold':'' ?>"><?= formatMontant($s['reste']) ?></td>
                        <td data-label="Statut">
                            <span class="badge <?= $bgs[$s['statut']] ?>"><?= $lblst[$s['statut']] ?></span>
                        </td>
                        <td data-label="">
                            <div class="d-flex gap-1">
                                <a href="/secretary/student_view.php?id=<?= $s['id'] ?>"
                                   class="btn btn-sm btn-outline-siniyat btn-action" title="Voir le profil">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($s['statut'] !== 'paye'): ?>
                                <a href="/secretary/payments.php?eleve_id=<?= $s['id'] ?>"
                                   class="btn btn-sm btn-outline-success btn-action" title="Enregistrer paiement">
                                    <i class="bi bi-cash-coin"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($students)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-check-circle fs-2 d-block mb-2 text-success"></i>
                        <?= $statut === 'impaye' ? 'Aucun élève impayé.' : 'Aucun résultat pour ce filtre.' ?>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (!empty($students) && ($counts['impaye'] + $counts['partiel']) > 0): ?>
    <div class="card-footer text-muted small d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-info-circle me-1"></i>
            <?= $counts['impaye'] + $counts['partiel'] ?> élève(s) avec paiement incomplet &mdash;
            Total à recouvrer : <strong class="text-danger"><?= formatMontant($totalReste) ?></strong>
        </span>
    </div>
    <?php endif; ?>
</div>

</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
