<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireLogin();
$pageTitle = 'Recherche élève';
$db = getDB();
$activeYear = getActiveYear();
$allYears = getAllYears();
$niveaux  = getNiveaux();

$q        = trim($_GET['q'] ?? '');
$anneeId  = (int)($_GET['annee_id'] ?? $activeYear['id']);
$niveauId = (int)($_GET['niveau_id'] ?? 0);
$eleves   = [];

if ($q || $niveauId) {
    $sql = "SELECT e.*, n.nom_fr AS classe, a.libelle AS annee,
                   COALESCE(SUM(CASE WHEN p.annule=FALSE THEN p.montant ELSE 0 END),0) AS total_paye,
                   COALESCE((SELECT SUM(t.montant) FROM grille_frais g JOIN tranches t ON t.grille_id=g.id WHERE g.annee_id=e.annee_id AND g.niveau_id=e.niveau_id),0) AS total_du
            FROM eleves e
            JOIN niveaux n ON n.id = e.niveau_id
            JOIN annees_scolaires a ON a.id = e.annee_id
            LEFT JOIN paiements p ON p.eleve_id = e.id
            WHERE e.actif = TRUE AND e.annee_id = ?";
    $params = [$anneeId];
    if ($q) { $sql .= " AND (e.nom ILIKE ? OR e.prenoms ILIKE ? OR e.matricule ILIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
    if ($niveauId) { $sql .= " AND e.niveau_id = ?"; $params[] = $niveauId; }
    $sql .= " GROUP BY e.id, n.nom_fr, a.libelle ORDER BY e.nom, e.prenoms LIMIT 100";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $eleves = $stmt->fetchAll();
}

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="fade-in-load">
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="page-title mb-0"><i class="bi bi-search"></i> <span data-i18n="nav.search">Rechercher un élève</span></h1>
    <a href="/secretary/students.php" class="btn btn-primary-siniyat btn-sm">
        <i class="bi bi-person-plus me-1"></i><span data-i18n="nav.new_student">Inscrire</span>
    </a>
</div>

<!-- Search form -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" data-i18n="student.search_placeholder">Recherche</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" value="<?= e($q) ?>"
                           placeholder="Nom, matricule..." data-i18n-placeholder="student.search_placeholder" autofocus>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label" data-i18n="nav.years">Année</label>
                <select name="annee_id" class="form-select">
                    <?php foreach ($allYears as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= $y['id']==$anneeId ? 'selected' : '' ?>><?= e($y['libelle']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" data-i18n="fees.class">Classe</label>
                <select name="niveau_id" class="form-select">
                    <option value="" data-i18n="common.all_classes">Toutes les classes</option>
                    <?php foreach ($niveaux as $n): ?>
                    <option value="<?= $n['id'] ?>" <?= $n['id']==$niveauId ? 'selected' : '' ?>><?= e($n['nom_fr']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary-siniyat w-100">
                    <i class="bi bi-search me-1"></i><span data-i18n="common.search">Rechercher</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Results -->
<?php if ($q || $niveauId): ?>
<div class="d-flex align-items-center justify-content-between mb-2">
    <span class="text-muted small"><?= count($eleves) ?> résultat(s)</span>
    <?php if (!empty($eleves) && $niveauId): ?>
    <div class="d-flex gap-2">
        <a href="/api/export.php?type=class&niveau_id=<?= $niveauId ?>&annee_id=<?= $anneeId ?>&format=excel"
           class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i><span data-i18n="export.excel">Excel</span></a>
        <a href="/api/export.php?type=class&niveau_id=<?= $niveauId ?>&annee_id=<?= $anneeId ?>&format=csv"
           class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i><span data-i18n="export.csv">CSV</span></a>
    </div>
    <?php endif; ?>
</div>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th data-i18n="student.matricule">Matricule</th>
                        <th data-i18n="student.nom">Nom & Prénom(s)</th>
                        <th data-i18n="student.sexe">Sexe</th>
                        <th data-i18n="fees.class">Classe</th>
                        <th data-i18n="payment.total_due">Total dû</th>
                        <th data-i18n="payment.total_paid">Payé</th>
                        <th data-i18n="payment.remaining">Reste</th>
                        <th>Statut</th>
                        <th data-i18n="common.actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eleves as $el):
                        $reste = (float)$el['total_du'] - (float)$el['total_paye'];
                        $statut = $reste <= 0 ? 'paye' : ($el['total_paye'] > 0 ? 'partiel' : 'impaye');
                        $labels = ['paye'=>'Soldé','partiel'=>'Partiel','impaye'=>'Impayé'];
                        $bgs    = ['paye'=>'badge-paye','partiel'=>'badge-partiel','impaye'=>'badge-impaye'];
                    ?>
                    <tr>
                        <td><code><?= e($el['matricule']) ?></code></td>
                        <td><strong><?= e($el['nom']) ?></strong> <?= e($el['prenoms']) ?></td>
                        <td><?= $el['sexe'] === 'M' ? '<i class="bi bi-gender-male text-primary"></i>' : '<i class="bi bi-gender-female text-danger"></i>' ?></td>
                        <td><?= e($el['classe']) ?></td>
                        <td><?= formatMontant((float)$el['total_du']) ?></td>
                        <td class="text-success fw-semibold"><?= formatMontant((float)$el['total_paye']) ?></td>
                        <td class="<?= $reste > 0 ? 'text-danger fw-semibold' : '' ?>"><?= formatMontant(max(0,$reste)) ?></td>
                        <td><span class="badge <?= $bgs[$statut] ?>"><?= $labels[$statut] ?></span></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/secretary/student_view.php?id=<?= $el['id'] ?>"
                                   class="btn btn-sm btn-outline-primary btn-action" title="Voir fiche">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="/secretary/students.php?edit=<?= $el['id'] ?>"
                                   class="btn btn-sm btn-outline-secondary btn-action" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="/secretary/payments.php?eleve_id=<?= $el['id'] ?>"
                                   class="btn btn-sm btn-outline-success btn-action" title="Paiement">
                                    <i class="bi bi-cash-coin"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($eleves)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4" data-i18n="common.no_data">Aucun élève trouvé.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i>
    <p>Entrez un nom, matricule ou sélectionnez une classe pour rechercher.</p>
</div>
<?php endif; ?>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
