<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireLogin();
$db   = getDB();
$activeYear = getActiveYear();

$eleveId = (int)($_GET['eleve_id'] ?? $_POST['eleve_id'] ?? 0);
$eleve   = $eleveId ? getEleveById($eleveId) : null;
$situation = $eleve ? getSituationFinanciere($eleveId) : [];

$pageTitle   = 'Enregistrer un paiement';
$message     = ''; $messageType = '';
$newPaiementId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    csrfCheck();
    $eleveId     = (int)$_POST['eleve_id'];
    $trancheId   = !empty($_POST['tranche_id']) ? (int)$_POST['tranche_id'] : null;
    $typePaiement= $_POST['type_paiement'] ?? 'tranche';
    $montant     = (float)str_replace([' ',','],['','.'], $_POST['montant'] ?? '0');
    $mode        = $_POST['mode_paiement'] ?? 'especes';
    $nomBanque   = trim($_POST['nom_banque'] ?? '');
    $refBancaire = trim($_POST['reference_bancaire'] ?? '');
    $dateDepot   = !empty($_POST['date_depot']) ? $_POST['date_depot'] : null;
    $datePaiement= !empty($_POST['date_paiement']) ? $_POST['date_paiement'] : date('Y-m-d H:i:s');
    $notes       = trim($_POST['notes'] ?? '');

    if ($montant > 0 && $eleveId) {
        try {
            $stmt = $db->prepare("INSERT INTO paiements
                (eleve_id,tranche_id,type_paiement,montant,mode_paiement,nom_banque,reference_bancaire,
                 date_depot,date_paiement,encaisse_par,notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?) RETURNING id");
            $stmt->execute([$eleveId,$trancheId,$typePaiement,$montant,$mode,
                $nomBanque?:null,$refBancaire?:null,$dateDepot,$datePaiement,$user['user_id'],$notes?:null]);
            $newPaiementId = (int)$stmt->fetchColumn();

            // Generate receipt
            $numRecu = generateNumeroRecu();
            $db->prepare("INSERT INTO recus (numero_recu,paiement_id,eleve_id,genere_par)
                VALUES (?,?,?,?)")->execute([$numRecu,$newPaiementId,$eleveId,$user['user_id']]);

            auditLog($user['user_id'],'PAIEMENT','paiements',$newPaiementId,
                "Élève #$eleveId — ".formatMontant($montant)." — $mode — reçu #$numRecu");

            // Refresh situation
            $situation = getSituationFinanciere($eleveId);
            $message = 'payment.saved'; $messageType = 'success';
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
<h1 class="page-title"><i class="bi bi-cash-coin"></i> <span data-i18n="payment.title">Enregistrer un paiement</span></h1>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
    <i class="bi bi-<?= $messageType==='success'?'check-circle-fill':'exclamation-triangle' ?> me-2"></i>
    <?php if ($message === 'payment.saved'): ?>
        Paiement enregistré avec succès.
        <?php if ($newPaiementId): ?>
        <a href="/pdf/receipt.php?paiement_id=<?= $newPaiementId ?>" target="_blank" class="btn btn-sm btn-outline-success ms-2">
            <i class="bi bi-printer me-1"></i><span data-i18n="receipt.print">Imprimer le reçu</span>
        </a>
        <?php endif; ?>
    <?php elseif ($message === 'common.error'): ?>
        Une erreur est survenue.
    <?php else: ?>
        Tous les champs obligatoires doivent être remplis.
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Left: Student search + info -->
    <div class="col-lg-5">
        <!-- Student lookup -->
        <div class="card mb-3">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-search me-2"></i>Élève
            </div>
            <div class="card-body">
                <div class="input-group mb-2">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="student-search" class="form-control"
                           placeholder="Rechercher nom, matricule..." autocomplete="off">
                </div>
                <div id="student-suggestions" class="list-group mb-2"></div>
                <div id="eleve-info-box" class="alert alert-info py-2 mb-0" <?= $eleve ? '' : 'style="display:none;"' ?>>
                    <?php if ($eleve): ?>
                    <strong><?= e($eleve['nom'].' '.$eleve['prenoms']) ?></strong><br>
                    <small><code><?= e($eleve['matricule']) ?></code> — <?= e($eleve['niveau_nom']) ?> — <?= e($eleve['annee_libelle']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Financial summary — always in DOM, hidden when no student -->
        <div class="card" <?= ($eleve && !empty($situation)) ? '' : 'style="display:none;"' ?>>
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-wallet2 me-2"></i>Situation financière
            </div>
            <div class="card-body" id="fin-summary-box">
                <?php if ($eleve && !empty($situation)):
                    $statut = $situation['statut'];
                    $bgs   = ['paye'=>'badge-paye','partiel'=>'badge-partiel','impaye'=>'badge-impaye'];
                    $labels= ['paye'=>'Soldé','partiel'=>'Partiel','impaye'=>'Impayé'];
                ?>
                <div class="d-flex gap-2 mb-3">
                    <span class="badge <?= $bgs[$statut] ?> fs-6"><?= $labels[$statut] ?></span>
                </div>
                <div class="row g-2 text-center mb-3">
                    <div class="col-4">
                        <div class="p-2 bg-light rounded">
                            <div class="small text-muted">Dû</div>
                            <div class="fw-bold small"><?= formatMontant($situation['totalDu']) ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 rounded" style="background:#d1fae5;">
                            <div class="small text-muted">Payé</div>
                            <div class="fw-bold small text-success"><?= formatMontant($situation['paye']) ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 rounded" style="background:#fee2e2;">
                            <div class="small text-muted">Reste</div>
                            <div class="fw-bold small text-danger"><?= formatMontant(max(0,$situation['reste'])) ?></div>
                        </div>
                    </div>
                </div>
                <?php foreach ($situation['tranches'] as $t): ?>
                <div class="mb-1 small">
                    <div class="d-flex justify-content-between">
                        <span><?= e($t['libelle_fr']) ?></span>
                        <span><?= formatMontant((float)$t['paye']) ?>/<?= formatMontant((float)$t['montant']) ?></span>
                    </div>
                    <div class="progress" style="height:5px;">
                        <div class="progress-bar bg-success" style="width:<?= min(100,round((float)$t['paye']/(float)$t['montant']>0?(float)$t['paye']/(float)$t['montant']*100:0)) ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Payment form -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-cash-coin me-2"></i><span data-i18n="payment.title">Enregistrer un paiement</span>
            </div>
            <div class="card-body">
                <form method="POST" id="payment-form" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="save_payment" value="1">
                    <input type="hidden" name="eleve_id" id="eleve_id_hidden" value="<?= $eleveId ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" data-i18n="payment.installment">Tranche <span class="text-danger">*</span></label>
                            <select name="tranche_id" id="tranche_select" class="form-select" onchange="updateTypePaiement(this)">
                                <option value="">-- Sélectionner une tranche --</option>
                                <?php if ($eleve && !empty($situation['tranches'])): ?>
                                    <?php foreach ($situation['tranches'] as $t):
                                        $restant = max(0,(float)$t['montant']-(float)$t['paye']);
                                        $isPaid  = $restant <= 0;
                                    ?>
                                    <option value="<?= $t['id'] ?>"
                                            data-restant="<?= $restant ?>"
                                            <?= $isPaid ? 'style="color:#198754;"' : '' ?>>
                                        <?= e($t['libelle_fr']) ?>
                                        <?php if ($isPaid): ?>
                                         ✓ Soldée
                                        <?php else: ?>
                                         — Reste : <?= formatMontant($restant) ?>
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php if (!$eleve): ?>
                                    <option value="" disabled style="color:#94a3b8;">⟵ Recherchez d'abord un élève</option>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <option value="__solde" data-restant="<?= max(0, ($situation['totalAvecReductionComplete'] ?? $situation['totalBrut'] ?? 0) - ($situation['paye'] ?? 0)) ?>" data-taux="<?= $situation['tauxReductionComplet'] ?? 0 ?>">
                                    🏦 Paiement complet — Solde intégral<?php if (($situation['tauxReductionComplet']??0)>0): ?> (<?= $situation['tauxReductionComplet'] ?>% réduction)<?php endif; ?>
                                </option>
                                <option value="__annexe">📋 Frais annexes</option>
                            </select>
                            <?php if (!$eleve): ?>
                            <div class="form-text text-warning">
                                <i class="bi bi-arrow-up me-1"></i>Recherchez un élève ci-dessus pour voir ses tranches.
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" data-i18n="payment.amount">Montant versé (FCFA) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="montant" id="montant" class="form-control"
                                       min="1" step="1" required value="<?= isset($_POST['montant'])?e($_POST['montant']):'' ?>">
                                <span class="input-group-text">FCFA</span>
                            </div>
                            <input type="hidden" name="type_paiement" id="type_paiement_hidden" value="tranche">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" data-i18n="payment.mode">Mode de paiement <span class="text-danger">*</span></label>
                            <select name="mode_paiement" id="mode_paiement" class="form-select" required>
                                <option value="especes" data-i18n="payment.cash">Espèces</option>
                                <option value="virement" data-i18n="payment.bank">Virement / Dépôt bancaire</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" data-i18n="payment.date">Date du paiement</label>
                            <input type="datetime-local" name="date_paiement" class="form-control"
                                   value="<?= date('Y-m-d\TH:i') ?>">
                        </div>

                        <!-- Bank fields (shown only for virement) -->
                        <div id="bank-fields" class="col-12" style="display:none;">
                            <div class="card bg-light border-0 p-3">
                                <div class="row g-2">
                                    <div class="col-md-5">
                                        <label class="form-label small" data-i18n="payment.bank_name">Banque</label>
                                        <input type="text" name="nom_banque" class="form-control form-control-sm"
                                               value="Rural Investment Credit SA">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small" data-i18n="payment.bank_ref">Référence bancaire</label>
                                        <input type="text" name="reference_bancaire" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small" data-i18n="payment.bank_date">Date dépôt</label>
                                        <input type="date" name="date_depot" class="form-control form-control-sm"
                                               value="<?= date('Y-m-d') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" data-i18n="payment.notes">Notes (optionnel)</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary-siniyat btn-lg"
                                        <?= !$eleve ? 'disabled' : '' ?>>
                                    <i class="bi bi-check-lg me-2"></i>
                                    <span data-i18n="payment.save">Enregistrer le paiement</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<?php
$firstUnpaidTranche = null;
foreach (($situation['tranches'] ?? []) as $t) {
    if (max(0, (float)$t['montant'] - (float)$t['paye']) > 0) {
        $firstUnpaidTranche = (int)$t['id'];
        break;
    }
}
$eleveInfo = $eleve ? json_encode([
    'nom' => $eleve['nom'], 'prenoms' => $eleve['prenoms'],
    'matricule' => $eleve['matricule'], 'classe' => $eleve['niveau_nom'],
    'annee' => $eleve['annee_libelle'],
]) : 'null';

$extraScripts = <<<HTML
<script>
/* -------------------------------------------------------
   Student search — AJAX (no page reload)
------------------------------------------------------- */
let searchTimer;
let currentEleveId = {$eleveId};

document.getElementById('student-search').addEventListener('input', function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) { document.getElementById('student-suggestions').innerHTML=''; return; }
    searchTimer = setTimeout(async () => {
        try {
            const data = await apiGet('/api/students.php?q='+encodeURIComponent(q)+'&limit=8');
            const box = document.getElementById('student-suggestions');
            box.innerHTML = '';
            (data.students||[]).forEach(s => {
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action py-2';
                a.innerHTML = '<strong>'+s.nom+' '+s.prenoms+'</strong> <small class="text-muted ms-2">'+s.matricule+' — '+s.classe+'</small>';
                a.addEventListener('click', e => { e.preventDefault(); loadEleve(s.id); });
                box.appendChild(a);
            });
        } catch(e) {}
    }, 300);
});

async function loadEleve(id) {
    try {
        const data = await apiGet('/api/student_situation.php?eleve_id='+id);
        if (data.error) return;

        currentEleveId = id;
        document.getElementById('eleve_id_hidden').value = id;
        document.getElementById('student-suggestions').innerHTML = '';
        document.getElementById('student-search').value = '';

        // Show student info
        const infoBox = document.getElementById('eleve-info-box');
        if (infoBox) {
            infoBox.innerHTML = '<strong>'+data.eleve.nom+' '+data.eleve.prenoms+'</strong><br>'
                +'<small><code>'+data.eleve.matricule+'</code> — '+data.eleve.classe+' — '+data.eleve.annee+'</small>';
            infoBox.style.display = '';
        }

        // Update financial summary
        updateFinancialSummary(data.situation, data.tranches);

        // Populate tranche dropdown
        const sel = document.getElementById('tranche_select');
        const prevSelected = sel.value;
        // Keep only the last two special options
        while (sel.options.length > 1) sel.remove(1);
        // Rebuild from end — insert tranches before special options
        // Actually: clear all, rebuild completely
        sel.innerHTML = '<option value="">-- Sélectionner une tranche --</option>';

        let firstUnpaid = null;
        data.tranches.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.setAttribute('data-restant', t.restant);
            opt.textContent = t.label;
            if (t.is_paid) opt.style.color = '#198754';
            sel.appendChild(opt);
            if (!firstUnpaid && !t.is_paid) firstUnpaid = opt;
        });

        // Solde complet option
        const sit = data.situation;
        const soldeRestant = Math.max(0, sit.totalAvecReductionComplete - sit.paye);
        const soldeOpt = document.createElement('option');
        soldeOpt.value = '__solde';
        soldeOpt.setAttribute('data-restant', soldeRestant);
        soldeOpt.setAttribute('data-taux', sit.tauxReductionComplet);
        soldeOpt.textContent = '🏦 Paiement complet — Solde intégral' + (sit.tauxReductionComplet > 0 ? ' ('+sit.tauxReductionComplet+'% réduction)' : '');
        sel.appendChild(soldeOpt);

        const annexeOpt = document.createElement('option');
        annexeOpt.value = '__annexe';
        annexeOpt.textContent = '📋 Frais annexes';
        sel.appendChild(annexeOpt);

        // Enable submit button
        document.querySelector('button[type=submit]').disabled = false;

        // Auto-select first unpaid tranche
        if (firstUnpaid) {
            firstUnpaid.selected = true;
            updateTypePaiement(sel);
        }
    } catch(e) { console.error(e); }
}

function updateFinancialSummary(sit, tranches) {
    const box = document.getElementById('fin-summary-box');
    if (!box) return;
    const pct = sit.totalDu > 0 ? Math.min(100, Math.round(sit.paye / sit.totalDu * 100)) : 0;
    const stClass = sit.statut === 'paye' ? 'badge-paye' : (sit.statut === 'partiel' ? 'badge-partiel' : 'badge-impaye');
    const stLabel = sit.statut === 'paye' ? 'Soldé' : (sit.statut === 'partiel' ? 'Partiel' : 'Impayé');
    const fmt = v => new Intl.NumberFormat('fr').format(v) + ' FCFA';
    let trancheHtml = '';
    tranches.forEach(t => {
        const p = t.montant > 0 ? Math.min(100, Math.round(t.paye / t.montant * 100)) : 0;
        trancheHtml += '<div class="mb-1 small"><div class="d-flex justify-content-between"><span>'+t.label.split(' — ')[0]+'</span>'
            +'<span>'+fmt(t.paye)+'/'+fmt(t.montant)+'</span></div>'
            +'<div class="progress" style="height:5px;"><div class="progress-bar bg-success" style="width:'+p+'%"></div></div></div>';
    });
    box.innerHTML = '<div class="d-flex gap-2 mb-3"><span class="badge '+stClass+' fs-6">'+stLabel+'</span></div>'
        +'<div class="row g-2 text-center mb-3">'
        +'<div class="col-4"><div class="p-2 bg-light rounded"><div class="small text-muted">Dû</div><div class="fw-bold small">'+fmt(sit.totalDu)+'</div></div></div>'
        +'<div class="col-4"><div class="p-2 rounded" style="background:#d1fae5;"><div class="small text-muted">Payé</div><div class="fw-bold small text-success">'+fmt(sit.paye)+'</div></div></div>'
        +'<div class="col-4"><div class="p-2 rounded" style="background:#fee2e2;"><div class="small text-muted">Reste</div><div class="fw-bold small text-danger">'+fmt(Math.max(0,sit.reste))+'</div></div></div>'
        +'</div>'+trancheHtml;
    box.closest('.card').style.display = '';
}

/* -------------------------------------------------------
   Tranche selection — prefill amount and type
------------------------------------------------------- */
function updateTypePaiement(sel) {
    const opt = sel.options[sel.selectedIndex];
    const restant = parseFloat(opt.getAttribute('data-restant') || '0');
    const taux    = parseFloat(opt.getAttribute('data-taux')    || '0');
    const montantInput = document.getElementById('montant');

    if (restant > 0) montantInput.value = Math.round(restant);

    const tp = sel.value === '__solde' ? 'solde_complet' : (sel.value === '__annexe' ? 'annexe' : 'tranche');
    document.getElementById('type_paiement_hidden').value = tp;

    let hint = document.getElementById('montant-hint');
    if (!hint) {
        hint = document.createElement('div');
        hint.id = 'montant-hint';
        hint.className = 'form-text';
        montantInput.parentNode.parentNode.appendChild(hint);
    }
    hint.innerHTML = (tp === 'solde_complet' && taux > 0)
        ? '<i class="bi bi-tag text-success me-1"></i>Réduction <strong>'+taux+'%</strong> appliquée pour paiement complet.'
        : '';

    if (sel.value.startsWith('__')) sel.name = '_tranche_noop';
    else sel.name = 'tranche_id';
}

// Auto-select first unpaid tranche on initial page load
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('tranche_select');
    if (!sel || !currentEleveId) return;
    const firstUnpaidId = {$firstUnpaidTranche};
    if (!firstUnpaidId) return;
    for (let i = 0; i < sel.options.length; i++) {
        if (parseInt(sel.options[i].value) === firstUnpaidId) {
            sel.selectedIndex = i;
            updateTypePaiement(sel);
            break;
        }
    }
});

// Bank fields toggle
document.getElementById('mode_paiement').addEventListener('change', function() {
    document.getElementById('bank-fields').style.display = this.value === 'virement' ? '' : 'none';
});
</script>
HTML;
include dirname(__DIR__) . '/includes/footer.php';
?>
