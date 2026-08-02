<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireLogin();
$db   = getDB();
$id   = (int)($_GET['id'] ?? 0);

if (!$id) redirect('/secretary/search.php');

$eleve = getEleveById($id);
if (!$eleve) redirect('/secretary/search.php');

// ── Inline payment handler ────────────────────────────────────────────────
$payMessage = ''; $payMessageType = ''; $newPayId = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment_inline'])) {
    csrfCheck();
    $trancheId    = !empty($_POST['tranche_id']) ? (int)$_POST['tranche_id'] : null;
    $typePaiement = $_POST['type_paiement'] ?? 'tranche';
    $montant      = (float)str_replace([' ',','],['','.'], $_POST['montant'] ?? '0');
    $mode         = $_POST['mode_paiement'] ?? 'especes';
    $nomBanque    = trim($_POST['nom_banque'] ?? '');
    $refBancaire  = trim($_POST['reference_bancaire'] ?? '');
    $dateDepot    = !empty($_POST['date_depot']) ? $_POST['date_depot'] : null;
    $datePaiement = !empty($_POST['date_paiement']) ? $_POST['date_paiement'] : date('Y-m-d H:i:s');
    $notes        = trim($_POST['notes'] ?? '');

    if ($montant > 0) {
        try {
            $stmt = $db->prepare("INSERT INTO paiements
                (eleve_id,tranche_id,type_paiement,montant,mode_paiement,nom_banque,
                 reference_bancaire,date_depot,date_paiement,encaisse_par,notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?) RETURNING id");
            $stmt->execute([$id,$trancheId,$typePaiement,$montant,$mode,
                $nomBanque?:null,$refBancaire?:null,$dateDepot,$datePaiement,
                $user['user_id'],$notes?:null]);
            $newPayId = (int)$stmt->fetchColumn();
            $numRecu  = generateNumeroRecu();
            $db->prepare("INSERT INTO recus (numero_recu,paiement_id,eleve_id,genere_par)
                VALUES (?,?,?,?)")->execute([$numRecu,$newPayId,$id,$user['user_id']]);
            auditLog($user['user_id'],'PAIEMENT','paiements',$newPayId,
                "Élève #$id — ".formatMontant($montant)." — $mode — reçu #$numRecu");
            redirect("/secretary/student_view.php?id=$id&payment_ok=$newPayId");
        } catch (Exception $ex) {
            $payMessage = 'Erreur lors de l\'enregistrement du paiement.';
            $payMessageType = 'danger';
            error_log($ex->getMessage());
        }
    } else {
        $payMessage = 'Le montant doit être supérieur à 0.';
        $payMessageType = 'warning';
    }
}

$docs = $db->prepare("SELECT * FROM documents_eleve WHERE eleve_id=?");
$docs->execute([$id]);
$docs = $docs->fetch();

$situation = getSituationFinanciere($id);

// Payment history
$payments = $db->prepare("
    SELECT p.*, t.libelle_fr AS tranche_nom, u.prenom||' '||u.nom AS agent, r.numero_recu
    FROM paiements p
    LEFT JOIN tranches t ON t.id = p.tranche_id
    LEFT JOIN utilisateurs u ON u.id = p.encaisse_par
    LEFT JOIN recus r ON r.paiement_id = p.id
    WHERE p.eleve_id = ? AND p.annule = FALSE
    ORDER BY p.date_paiement DESC
");
$payments->execute([$id]);
$paiements = $payments->fetchAll();

$pageTitle = e($eleve['nom'] . ' ' . $eleve['prenoms']);

$bgs   = ['paye'=>'badge-paye','partiel'=>'badge-partiel','impaye'=>'badge-impaye'];
$labels= ['paye'=>'Soldé','partiel'=>'Partiel','impaye'=>'Impayé'];
$statut = $situation['statut'] ?? 'impaye';

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="fade-in-load">
<?php if (isset($_GET['created'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>
    <strong>Élève inscrit avec succès !</strong> Matricule : <code><?= e($eleve['matricule']) ?></code>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (isset($_GET['payment_ok'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill me-2"></i>
    <strong>Paiement enregistré avec succès !</strong>
    <a href="/pdf/receipt.php?paiement_id=<?= (int)$_GET['payment_ok'] ?>" target="_blank"
       class="btn btn-sm btn-outline-success ms-2">
        <i class="bi bi-printer me-1"></i>Imprimer le reçu
    </a>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($payMessage): ?>
<div class="alert alert-<?= $payMessageType ?> alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle me-2"></i><?= e($payMessage) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Header bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="page-title mb-1">
            <i class="bi bi-person-circle"></i>
            <?= e($eleve['nom'] . ' ' . $eleve['prenoms']) ?>
        </h1>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary-siniyat"><code><?= e($eleve['matricule']) ?></code></span>
            <span class="badge bg-secondary"><?= e($eleve['niveau_nom']) ?></span>
            <span class="badge bg-dark"><?= e($eleve['annee_libelle']) ?></span>
            <span class="badge <?= $bgs[$statut] ?>"><?= $labels[$statut] ?></span>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/secretary/students.php?edit=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil me-1"></i><span data-i18n="student.edit">Modifier</span>
        </a>
        <button type="button" class="btn btn-primary-siniyat btn-sm"
                data-bs-toggle="modal" data-bs-target="#payModal">
            <i class="bi bi-cash-coin me-1"></i>Nouveau paiement
        </button>
        <a href="/secretary/search.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Retour
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Left: Identity + Family -->
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-person-badge me-2"></i>Identité
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th style="width:45%">Matricule</th><td><code><?= e($eleve['matricule']) ?></code></td></tr>
                    <tr><th>Nom</th><td><?= e($eleve['nom']) ?></td></tr>
                    <tr><th>Prénom(s)</th><td><?= e($eleve['prenoms']) ?></td></tr>
                    <tr><th>Sexe</th><td><?= $eleve['sexe']==='M' ? '<i class="bi bi-gender-male text-primary"></i> Masculin' : '<i class="bi bi-gender-female text-danger"></i> Féminin' ?></td></tr>
                    <tr><th>Date de naissance</th><td><?= e(date('d/m/Y', strtotime($eleve['date_naissance']))) ?></td></tr>
                    <tr><th>Lieu de naissance</th><td><?= e($eleve['lieu_naissance'] ?? '—') ?></td></tr>
                    <tr><th>Classe</th><td><strong><?= e($eleve['niveau_nom']) ?></strong></td></tr>
                    <tr><th>Statut</th><td><?= e(ucfirst($eleve['statut_eleve'])) ?></td></tr>
                    <tr><th>Année scolaire</th><td><?= e($eleve['annee_libelle']) ?></td></tr>
                </table>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-house me-2"></i>Famille & Contacts
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th style="width:45%">Quartier</th><td><?= e($eleve['quartier']??'—') ?></td></tr>
                    <tr><th>Père</th><td><?= e($eleve['nom_pere']??'—') ?><?= $eleve['tel_pere'] ? ' — <a href="tel:'.$eleve['tel_pere'].'">'.$eleve['tel_pere'].'</a>' : '' ?></td></tr>
                    <tr><th>Mère</th><td><?= e($eleve['nom_mere']??'—') ?><?= $eleve['tel_mere'] ? ' — <a href="tel:'.$eleve['tel_mere'].'">'.$eleve['tel_mere'].'</a>' : '' ?></td></tr>
                    <tr><th>Tuteur</th><td><?= e($eleve['nom_tuteur']??'—') ?></td></tr>
                    <tr><th>Urgence</th><td><?= e($eleve['contact_urgence']??'—') ?></td></tr>
                </table>
            </div>
        </div>
        <!-- Documents -->
        <div class="card">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-folder-check me-2"></i>Documents
            </div>
            <div class="card-body">
                <?php
                $docLabels = [
                    'photos_identite'      => '4 photos d\'identité',
                    'acte_naissance'       => 'Acte de naissance',
                    'carnet_vaccination'   => 'Carnet de vaccination',
                    'certificat_transfert' => 'Certificat de transfert',
                    'livret_scolaire'      => 'Livret scolaire',
                    'certificat_medical'   => 'Certificat médical',
                ];
                foreach ($docLabels as $field => $label):
                    $ok = $docs && $docs[$field];
                ?>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-<?= $ok ? 'check-circle-fill text-success' : 'x-circle text-danger' ?>"></i>
                    <span class="<?= $ok ? '' : 'text-muted' ?>"><?= $label ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right: Financial situation + payments -->
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header bg-primary-siniyat text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-wallet2 me-2"></i><span data-i18n="student.financial_status">Situation financière</span></span>
                <button type="button" class="btn btn-sm btn-light"
                        data-bs-toggle="modal" data-bs-target="#payModal">
                    <i class="bi bi-plus-circle me-1"></i>Nouveau paiement
                </button>
            </div>
            <div class="card-body">
                <?php if (!empty($situation)): ?>
                <div class="fin-box <?= $statut ?> mb-3">
                    <i class="bi bi-<?= $statut==='paye'?'check-circle-fill text-success':($statut==='partiel'?'exclamation-circle-fill text-warning':'x-circle-fill text-danger') ?> fs-3"></i>
                    <div>
                        <div class="fw-bold fs-6"><?= $labels[$statut] ?></div>
                        <div class="small text-muted">Reste à payer : <strong><?= formatMontant(max(0, $situation['reste']??0)) ?></strong></div>
                    </div>
                </div>
                <div class="row g-2 text-center mb-3">
                    <div class="col-4">
                        <div class="p-2 rounded bg-light">
                            <div class="text-muted small" data-i18n="payment.total_due">Total dû</div>
                            <div class="fw-bold"><?= formatMontant($situation['totalDu']??0) ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 rounded" style="background:#d1fae5;">
                            <div class="text-muted small" data-i18n="payment.total_paid">Payé</div>
                            <div class="fw-bold text-success"><?= formatMontant($situation['paye']??0) ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 rounded" style="background:#fee2e2;">
                            <div class="text-muted small" data-i18n="payment.remaining">Reste</div>
                            <div class="fw-bold text-danger"><?= formatMontant(max(0,$situation['reste']??0)) ?></div>
                        </div>
                    </div>
                </div>
                <?php if ($situation['tauxReduction']??0 > 0): ?>
                <div class="alert alert-info py-2 small">
                    <i class="bi bi-tag me-1"></i>
                    Réduction appliquée : <strong><?= $situation['tauxReduction'] ?>%</strong>
                    = <?= formatMontant($situation['montantReduction']??0) ?>
                    (<?= $situation['reductionComplet']>0 ? 'paiement complet ' : '' ?>
                    <?= $situation['reductionFratrie']>0 ? 'fratrie '.$situation['reductionFratrie'].'%' : '' ?>)
                </div>
                <?php endif; ?>
                <!-- Tranches progress -->
                <?php foreach (($situation['tranches']??[]) as $t): ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= e($t['libelle_fr']) ?></span>
                        <span><?= formatMontant((float)$t['paye']) ?> / <?= formatMontant((float)$t['montant']) ?></span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" style="width:<?= min(100, round((float)$t['paye']/(float)$t['montant']*100)) ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p class="text-muted" data-i18n="common.no_data">Aucune grille de frais configurée pour cette classe.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment history -->
        <div class="card">
            <div class="card-header bg-primary-siniyat text-white">
                <i class="bi bi-clock-history me-2"></i><span data-i18n="student.payment_history">Historique des paiements</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 table-sm">
                        <thead>
                            <tr>
                                <th>Reçu</th>
                                <th>Tranche</th>
                                <th>Montant</th>
                                <th>Mode</th>
                                <th>Agent</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paiements as $p): ?>
                            <tr>
                                <td><span class="badge bg-secondary">#<?= e($p['numero_recu']??'—') ?></span></td>
                                <td><?= e($p['tranche_nom']??ucfirst($p['type_paiement'])) ?></td>
                                <td class="fw-semibold text-success"><?= formatMontant((float)$p['montant']) ?></td>
                                <td><?= $p['mode_paiement']==='especes' ? '<i class="bi bi-cash"></i>' : '<i class="bi bi-bank"></i>' ?> <?= e(ucfirst($p['mode_paiement'])) ?></td>
                                <td class="small text-muted"><?= e($p['agent']??'—') ?></td>
                                <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($p['date_paiement'])) ?></td>
                                <td>
                                    <?php if ($p['numero_recu']): ?>
                                    <a href="/pdf/receipt.php?paiement_id=<?= $p['id'] ?>&dup=1" target="_blank"
                                       class="btn btn-sm btn-outline-secondary btn-action">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($paiements)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3" data-i18n="common.no_data">Aucun paiement.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php
// Build tranches JSON for JS auto-fill
$tranchesJs = [];
foreach (($situation['tranches'] ?? []) as $t) {
    $tranchesJs[] = [
        'id'      => $t['id'],
        'label'   => $t['libelle_fr'],
        'montant' => (float)$t['montant'],
        'restant' => max(0, (float)$t['montant'] - (float)$t['paye']),
    ];
}
$resteTotal = max(0, $situation['reste'] ?? 0);
$tranchesJson = json_encode($tranchesJs, JSON_UNESCAPED_UNICODE);

$extraScripts = <<<HTML
<script>
const PAY_TRANCHES = {$tranchesJson};
const PAY_RESTE    = {$resteTotal};

// Auto-fill amount when tranche selected
document.getElementById('payTranche').addEventListener('change', function() {
    const val = this.value;
    const amtInput = document.getElementById('payMontant');
    if (val === '__solde') {
        amtInput.value = Math.round(PAY_RESTE);
        document.getElementById('payType').value = 'solde_complet';
        this.name = '_noop';
    } else if (val === '__annexe') {
        amtInput.value = '';
        document.getElementById('payType').value = 'annexe';
        this.name = '_noop';
    } else {
        const t = PAY_TRANCHES.find(t => t.id == val);
        if (t) amtInput.value = Math.round(t.restant);
        document.getElementById('payType').value = 'tranche';
        this.name = 'tranche_id';
    }
});

// Show/hide bank fields
document.getElementById('payMode').addEventListener('change', function() {
    document.getElementById('payBankFields').style.display = this.value === 'virement' ? '' : 'none';
});
</script>
HTML;
?>

<!-- ══ Paiement modal ══════════════════════════════════════════════════════ -->
<div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary-siniyat text-white">
                <h5 class="modal-title" id="payModalLabel">
                    <i class="bi bi-cash-coin me-2"></i>
                    Nouveau paiement — <?= e($eleve['nom'].' '.$eleve['prenoms']) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="save_payment_inline" value="1">
                <input type="hidden" name="eleve_id" value="<?= $id ?>">
                <input type="hidden" id="payType" name="type_paiement" value="tranche">
                <div class="modal-body">
                    <!-- Situation rapide -->
                    <?php if (!empty($situation)): ?>
                    <div class="row g-2 text-center mb-3">
                        <div class="col-4">
                            <div class="p-2 bg-light rounded">
                                <div class="small text-muted">Total dû</div>
                                <div class="fw-bold"><?= formatMontant($situation['totalDu'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded" style="background:#d1fae5;">
                                <div class="small text-muted">Déjà payé</div>
                                <div class="fw-bold text-success"><?= formatMontant($situation['paye'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded" style="background:#fee2e2;">
                                <div class="small text-muted">Reste à payer</div>
                                <div class="fw-bold text-danger"><?= formatMontant(max(0, $situation['reste'] ?? 0)) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tranche <span class="text-danger">*</span></label>
                            <select id="payTranche" name="tranche_id" class="form-select">
                                <option value="">-- Sélectionner --</option>
                                <?php foreach (($situation['tranches'] ?? []) as $t): ?>
                                <option value="<?= $t['id'] ?>">
                                    <?= e($t['libelle_fr']) ?>
                                    (<?= formatMontant((float)$t['montant']) ?>
                                    — reste : <?= formatMontant(max(0,(float)$t['montant']-(float)$t['paye'])) ?>)
                                </option>
                                <?php endforeach; ?>
                                <option value="__solde">Paiement complet — Solde (<?= formatMontant(max(0,$situation['reste']??0)) ?>)</option>
                                <option value="__annexe">Frais annexes</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Montant versé (FCFA) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" id="payMontant" name="montant" class="form-control"
                                       min="1" step="1" required placeholder="0">
                                <span class="input-group-text">FCFA</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mode de paiement <span class="text-danger">*</span></label>
                            <select id="payMode" name="mode_paiement" class="form-select">
                                <option value="especes">Espèces</option>
                                <option value="virement">Virement / Dépôt bancaire</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date du paiement</label>
                            <input type="datetime-local" name="date_paiement" class="form-control"
                                   value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <!-- Champs bancaires -->
                        <div id="payBankFields" class="col-12" style="display:none;">
                            <div class="card bg-light border-0 p-3">
                                <div class="row g-2">
                                    <div class="col-md-5">
                                        <label class="form-label small">Banque</label>
                                        <input type="text" name="nom_banque" class="form-control form-control-sm"
                                               value="<?= e(BANQUE_NOM) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small">Référence bancaire</label>
                                        <input type="text" name="reference_bancaire" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Date dépôt</label>
                                        <input type="date" name="date_depot" class="form-control form-control-sm"
                                               value="<?= date('Y-m-d') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes (optionnel)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Observation, référence..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary-siniyat">
                        <i class="bi bi-check-lg me-1"></i>Enregistrer le paiement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
