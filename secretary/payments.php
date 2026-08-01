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
                <?php if ($eleve): ?>
                <div class="alert alert-info py-2 mb-0">
                    <strong><?= e($eleve['nom'].' '.$eleve['prenoms']) ?></strong><br>
                    <small><code><?= e($eleve['matricule']) ?></code> — <?= e($eleve['niveau_nom']) ?> — <?= e($eleve['annee_libelle']) ?></small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Financial summary -->
        <?php if ($eleve && !empty($situation)): ?>
        <div class="card">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-wallet2 me-2"></i>Situation financière
            </div>
            <div class="card-body">
                <?php
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
                        <div class="progress-bar bg-success" style="width:<?= min(100,round((float)$t['paye']/(float)$t['montant']*100)) ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
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
                                <option value="">-- Sélectionner --</option>
                                <?php if ($eleve && !empty($situation['tranches'])): ?>
                                    <?php foreach ($situation['tranches'] as $t): ?>
                                    <option value="<?= $t['id'] ?>" data-restant="<?= max(0,(float)$t['montant']-(float)$t['paye']) ?>">
                                        <?= e($t['libelle_fr']) ?> (<?= formatMontant((float)$t['montant']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <option value="__solde" data-restant="<?= max(0,$situation['reste']??0) ?>">Solde complet</option>
                                <option value="__annexe">Frais annexe</option>
                            </select>
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
$extraScripts = <<<HTML
<script>
// Student search autocomplete
let searchTimer;
document.getElementById('student-search').addEventListener('input', function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) { document.getElementById('student-suggestions').innerHTML=''; return; }
    searchTimer = setTimeout(async () => {
        const data = await apiGet('/api/students.php?q='+encodeURIComponent(q)+'&limit=8');
        const box = document.getElementById('student-suggestions');
        box.innerHTML = '';
        (data.students||[]).forEach(s => {
            const a = document.createElement('a');
            a.href = '/secretary/payments.php?eleve_id='+s.id;
            a.className = 'list-group-item list-group-item-action py-2';
            a.innerHTML = '<strong>'+s.nom+' '+s.prenoms+'</strong> <small class="text-muted ms-2">'+s.matricule+' — '+s.classe+'</small>';
            box.appendChild(a);
        });
    }, 300);
});

// Tranche selection — prefill amount with remaining
function updateTypePaiement(sel) {
    const opt = sel.options[sel.selectedIndex];
    const restant = parseFloat(opt.getAttribute('data-restant')||'0');
    if (restant > 0) document.getElementById('montant').value = Math.round(restant);
    const tp = sel.value === '__solde' ? 'solde_complet' : (sel.value === '__annexe' ? 'annexe' : 'tranche');
    document.getElementById('type_paiement_hidden').value = tp;
    // Blank tranche_id for special types
    if (sel.value.startsWith('__')) sel.name = '_tranche_noop';
    else sel.name = 'tranche_id';
}
</script>
HTML;
include dirname(__DIR__) . '/includes/footer.php';
?>
