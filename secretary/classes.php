<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireLogin();
$db   = getDB();
$pageTitle = 'Liste par classe';
$activeYear = getActiveYear();
$allYears   = getAllYears();

$yearId    = (int)($_GET['annee']   ?? $activeYear['id']);
$niveauId  = (int)($_GET['niveau']  ?? 0);
$section   = in_array($_GET['section']??'', ['francophone','anglophone']) ? $_GET['section'] : 'francophone';

// All niveaux for selected section
$niveaux = $db->prepare("SELECT * FROM niveaux WHERE actif=TRUE AND section=? ORDER BY ordre");
$niveaux->execute([$section]);
$niveauxList = $niveaux->fetchAll();

// Class info
$niveauInfo = null;
$students   = [];
$stats      = ['total'=>0,'garcons'=>0,'filles'=>0,'payes'=>0,'partiels'=>0,'impayes'=>0];

if ($niveauId) {
    // Get niveau info
    $ni = $db->prepare("SELECT * FROM niveaux WHERE id=?");
    $ni->execute([$niveauId]);
    $niveauInfo = $ni->fetch();

    // Get students
    $stu = $db->prepare("
        SELECT e.id, e.matricule, e.nom, e.prenoms, e.sexe, e.date_naissance, e.statut_eleve,
               e.nom_pere, e.tel_pere, e.nom_mere, e.tel_mere,
               COALESCE((
                   SELECT SUM(p.montant) FROM paiements p WHERE p.eleve_id=e.id AND p.annule=FALSE
               ),0) AS total_paye
        FROM eleves e
        WHERE e.niveau_id=? AND e.annee_id=? AND e.actif=TRUE
        ORDER BY e.nom, e.prenoms
    ");
    $stu->execute([$niveauId, $yearId]);
    $students = $stu->fetchAll();

    // Compute stats
    $stats['total'] = count($students);
    foreach ($students as $s) {
        if ($s['sexe']==='M') $stats['garcons']++;
        else $stats['filles']++;
    }
}

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="fade-in-load">

<!-- Print styles -->
<style>
@media print {
  .no-print, .sidebar, .topbar, .content-wrapper > footer,
  .btn, nav, form, .card-header .btn { display: none !important; }
  .content-wrapper { margin-left: 0 !important; }
  .print-header { display: block !important; }
  body { background: #fff !important; }
  .table thead th { background: #0d1b4b !important; color: #fff !important; -webkit-print-color-adjust: exact; }
}
.print-header { display: none; }
</style>

<!-- Print-only header -->
<div class="print-header mb-3 text-center">
    <img src="/assets/img/logo.png" alt="Logo" height="60" style="border-radius:50%;border:2px solid #0d1b4b;">
    <h4 class="mt-2 mb-0">Groupe Scolaire Bilingue SINIYAT</h4>
    <?php if ($niveauInfo): ?>
    <p class="mb-0">Liste des élèves — <?= e($niveauInfo['nom_fr']) ?> — Année <?= e($activeYear['libelle']) ?></p>
    <?php endif; ?>
    <hr>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2 no-print">
    <h1 class="page-title mb-0">
        <i class="bi bi-list-ul"></i> Liste par classe
    </h1>
    <?php if ($niveauId && !empty($students)): ?>
    <button onclick="window.print()" class="btn btn-outline-siniyat btn-sm">
        <i class="bi bi-printer me-1"></i>Imprimer cette liste
    </button>
    <?php endif; ?>
</div>

<div class="row g-3">
    <!-- Left: filters — compact dropdowns -->
    <div class="col-lg-3 no-print">
        <div class="card mb-3">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-funnel me-2"></i>Sélection
            </div>
            <div class="card-body">
                <form method="GET" id="filter-form">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Année scolaire</label>
                        <select name="annee" class="form-select form-select-sm" onchange="document.getElementById('niveau-select').innerHTML='<option value=\"\">-- Choisir une classe --</option>'; loadClasses(this.value, document.getElementById('section-select').value)">
                            <?php foreach ($allYears as $y): ?>
                            <option value="<?= $y['id'] ?>" <?= $y['id']==$yearId?'selected':'' ?>>
                                <?= e($y['libelle']) ?><?= $y['statut']==='active'?' ★':'' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Section</label>
                        <select name="section" id="section-select" class="form-select form-select-sm" onchange="loadClasses(document.querySelector('[name=annee]').value, this.value)">
                            <option value="francophone" <?= $section==='francophone'?'selected':'' ?>>Francophone</option>
                            <option value="anglophone"  <?= $section==='anglophone' ?'selected':'' ?>>Anglophone</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Classe</label>
                        <select name="niveau" id="niveau-select" class="form-select form-select-sm" required>
                            <option value="">-- Choisir une classe --</option>
                            <?php foreach ($niveauxList as $n): ?>
                            <option value="<?= $n['id'] ?>" <?= $n['id']==$niveauId?'selected':'' ?>><?= e($n['nom_fr']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary-siniyat btn-sm">
                            <i class="bi bi-check-circle me-1"></i>Voir la liste
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right: students list -->
    <div class="col-lg-9">
        <?php if (!$niveauId): ?>
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-arrow-left fs-2 d-block mb-2"></i>
                Sélectionnez une classe pour voir la liste des élèves.
            </div>
        </div>

        <?php else: ?>

        <!-- Stats cards -->
        <div class="row g-2 mb-3">
            <div class="col-4">
                <div class="card stat-card text-center py-2">
                    <div class="stat-value text-primary-siniyat"><?= $stats['total'] ?></div>
                    <div class="text-muted small">Total élèves</div>
                </div>
            </div>
            <div class="col-4">
                <div class="card stat-card text-center py-2" style="border-left-color:#0ea5e9!important;">
                    <div class="stat-value" style="color:#0ea5e9;"><?= $stats['garcons'] ?></div>
                    <div class="text-muted small">Garçons</div>
                </div>
            </div>
            <div class="col-4">
                <div class="card stat-card text-center py-2" style="border-left-color:#ec4899!important;">
                    <div class="stat-value" style="color:#ec4899;"><?= $stats['filles'] ?></div>
                    <div class="text-muted small">Filles</div>
                </div>
            </div>
        </div>

        <!-- Class header (print visible) -->
        <div class="card">
            <div class="card-header bg-primary-siniyat text-white d-flex justify-content-between align-items-center">
                <div>
                    <strong><?= e($niveauInfo['nom_fr']) ?></strong>
                    <span class="badge <?= $section==='anglophone'?'badge-anglophone':'badge-francophone' ?> ms-2">
                        <?= ucfirst($section) ?>
                    </span>
                </div>
                <span class="opacity-75 small">Année <?= e($activeYear['libelle']) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Matricule</th>
                                <th>Nom &amp; Prénoms</th>
                                <th>Sexe</th>
                                <th>Date naissance</th>
                                <th>Contact parent</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $i => $s): ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td><code><?= e($s['matricule'] ?? '—') ?></code></td>
                                <td>
                                    <strong><?= e($s['nom']) ?></strong> <?= e($s['prenoms']) ?>
                                    <?php if ($s['statut_eleve'] === 'redoublant'): ?>
                                    <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;">Redoublant</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($s['sexe']==='M'): ?>
                                    <span class="badge" style="background:#e0f2fe;color:#0369a1;">M</span>
                                    <?php else: ?>
                                    <span class="badge" style="background:#fce7f3;color:#9d174d;">F</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= $s['date_naissance'] ? date('d/m/Y', strtotime($s['date_naissance'])) : '—' ?></td>
                                <td class="small">
                                    <?php if ($s['tel_pere']): ?>
                                    <div><i class="bi bi-person me-1 text-muted"></i><?= e($s['tel_pere']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($s['tel_mere']): ?>
                                    <div><i class="bi bi-person-heart me-1 text-muted"></i><?= e($s['tel_mere']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="no-print">
                                    <a href="/secretary/student_view.php?id=<?= $s['id'] ?>"
                                       class="btn btn-sm btn-outline-siniyat btn-action">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="/secretary/students.php?edit=<?= $s['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary btn-action">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($students)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-person-x d-block fs-2 mb-2"></i>Aucun élève inscrit dans cette classe.
                            </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (!empty($students)): ?>
            <div class="card-footer text-muted small no-print d-flex justify-content-between">
                <span><?= $stats['total'] ?> élève(s) — <?= $stats['garcons'] ?> garçon(s), <?= $stats['filles'] ?> fille(s)</span>
                <button onclick="window.print()" class="btn btn-sm btn-outline-siniyat py-0">
                    <i class="bi bi-printer me-1"></i>Imprimer
                </button>
            </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </div>
</div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
<script>
// Reload class dropdown when section changes
async function loadClasses(anneeId, section) {
    const sel = document.getElementById('niveau-select');
    sel.innerHTML = '<option value="">Chargement...</option>';
    try {
        const res = await fetch('/api/students.php?niveaux=1&section='+encodeURIComponent(section));
        const data = await res.json();
        sel.innerHTML = '<option value="">-- Choisir une classe --</option>';
        (data.niveaux||[]).forEach(n => {
            const opt = document.createElement('option');
            opt.value = n.id; opt.textContent = n.nom_fr;
            sel.appendChild(opt);
        });
    } catch(e) {
        sel.innerHTML = '<option value="">-- Choisir une classe --</option>';
    }
}
</script>
