<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireLogin();
$db   = getDB();

$paiementId = (int)($_GET['paiement_id'] ?? 0);
$isDuplicate = !empty($_GET['dup']);

if (!$paiementId) redirect('/');

// Get payment + receipt
$stmt = $db->prepare("
    SELECT p.*, r.numero_recu, r.date_generation, r.duplicata,
           e.nom, e.prenoms, e.matricule, e.sexe,
           n.nom_fr AS classe, n.nom_en AS classe_en,
           a.libelle AS annee,
           u.prenom||' '||u.nom AS agent_nom,
           t.libelle_fr AS tranche_nom, t.libelle_en AS tranche_nom_en
    FROM paiements p
    LEFT JOIN recus r ON r.paiement_id = p.id
    JOIN eleves e ON e.id = p.eleve_id
    JOIN niveaux n ON n.id = e.niveau_id
    JOIN annees_scolaires a ON a.id = e.annee_id
    LEFT JOIN utilisateurs u ON u.id = p.encaisse_par
    LEFT JOIN tranches t ON t.id = p.tranche_id
    WHERE p.id = ?
");
$stmt->execute([$paiementId]);
$data = $stmt->fetch();

if (!$data) redirect('/');

// Mark as duplicate if reprinting
if ($isDuplicate && $data['numero_recu']) {
    $db->prepare("UPDATE recus SET duplicata=TRUE WHERE paiement_id=?")->execute([$paiementId]);
    auditLog($user['user_id'], 'DUPLICATA_RECU', 'recus', $paiementId, "Duplicata reçu #{$data['numero_recu']}");
}

// Financial situation
$situation = getSituationFinanciere((int)$data['eleve_id'] ?? 0);

// Require dompdf via composer
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);

$lang = $_SESSION['lang'] ?? 'fr';
$isEn = $lang === 'en';

$numRecu    = $data['numero_recu'] ?? '—';
$dateHeure  = date('d/m/Y à H:i', strtotime($data['date_paiement']));
$dateGen    = date('d/m/Y à H:i', strtotime($data['date_generation'] ?? 'now'));
$isDup      = $data['duplicata'] || $isDuplicate;

$modeLabel = $data['mode_paiement'] === 'especes'
    ? ($isEn ? 'Cash' : 'Espèces')
    : ($isEn ? 'Bank Transfer/Deposit' : 'Virement / Dépôt bancaire');

$trancheLabel = $data['tranche_nom'] ?? ucfirst($data['type_paiement'] ?? '');

// Base64-encode logo
$logoPath = dirname(__DIR__) . '/assets/img/logo.png';
$logoB64  = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '';

$totalDu   = $situation['totalDu'] ?? 0;
$totalPaye = $situation['paye'] ?? 0;
$reste     = max(0, $situation['reste'] ?? 0);
$reduction = $situation['tauxReduction'] ?? 0;

$dupBadge = $isDup ? '<div style="text-align:center;margin-bottom:8px;"><span style="background:#f59e0b;color:#fff;padding:4px 12px;border-radius:4px;font-weight:bold;font-size:13px;">'.($isEn?'DUPLICATE':'DUPLICATA').'</span></div>' : '';

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; margin: 0; padding: 0; }
  .receipt { max-width: 700px; margin: 0 auto; padding: 24px; }
  .header { display: flex; align-items: center; background: #0d1b4b; color: white; padding: 16px 20px; border-radius: 8px 8px 0 0; margin-bottom: 0; }
  .logo-circle { width: 60px; height: 60px; border-radius: 50%; background: white; overflow: hidden; margin-right: 16px; border: 2px solid rgba(255,255,255,0.3); }
  .logo-circle img { width: 60px; height: 60px; object-fit: cover; }
  .school-name { font-size: 16px; font-weight: bold; margin-bottom: 2px; }
  .school-sub  { font-size: 11px; opacity: 0.8; }
  .receipt-title { text-align: center; background: #1a3580; color: white; padding: 10px; font-size: 14px; font-weight: bold; letter-spacing: 1px; }
  .receipt-num { text-align: center; padding: 8px; font-size: 13px; border-bottom: 2px solid #0d1b4b; margin-bottom: 12px; }
  table.info { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
  table.info td { padding: 5px 8px; border: 1px solid #e2e8f0; font-size: 11px; }
  table.info td.label { background: #f8fafc; font-weight: bold; width: 40%; color: #0d1b4b; }
  .section-title { background: #0d1b4b; color: white; padding: 5px 10px; font-size: 11px; font-weight: bold; margin: 10px 0 4px; border-radius: 3px; }
  .totals { background: #f0f4ff; border: 1px solid #c7d7f8; padding: 10px 14px; border-radius: 6px; margin: 10px 0; }
  .totals table { width: 100%; }
  .totals td { padding: 3px 0; font-size: 11px; }
  .totals .bold { font-weight: bold; font-size: 13px; }
  .status-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; }
  .status-paye    { background: #d1fae5; color: #065f46; }
  .status-partiel { background: #fef3c7; color: #92400e; }
  .status-impaye  { background: #fee2e2; color: #991b1b; }
  .signature-area { border: 1px dashed #94a3b8; border-radius: 6px; padding: 20px; text-align: center; min-height: 60px; color: #94a3b8; font-size: 11px; margin-top: 8px; }
  .footer-note { text-align: center; font-size: 9px; color: #94a3b8; margin-top: 12px; border-top: 1px solid #e2e8f0; padding-top: 6px; }
</style>
</head>
<body>
<div class="receipt">
  {$dupBadge}
  <div class="header">
    <div class="logo-circle"><img src="{$logoB64}" alt="Logo"></div>
    <div>
      <div class="school-name">Groupe Scolaire Bilingue SINIYAT</div>
      <div class="school-sub">Siniyat Bilingual School Group</div>
      <div class="school-sub">Année scolaire / Academic Year: {$data['annee']}</div>
    </div>
  </div>
  <div class="receipt-title">{$isEn ? 'PAYMENT RECEIPT' : 'REÇU DE PAIEMENT'}</div>
  <div class="receipt-num">
    <strong>{$isEn ? 'Receipt No.' : 'Reçu N°'} : <span style="color:#0d1b4b;font-size:16px;">{$numRecu}</span></strong>
    &nbsp;&nbsp;|&nbsp;&nbsp;
    {$isEn ? 'Date' : 'Date'} : {$dateHeure}
  </div>

  <div class="section-title">{$isEn ? 'STUDENT INFORMATION' : 'INFORMATIONS ÉLÈVE'}</div>
  <table class="info">
    <tr><td class="label">{$isEn ? 'Student ID' : 'Matricule'}</td><td><strong>{$data['matricule']}</strong></td><td class="label">{$isEn ? 'Class' : 'Classe'}</td><td>{$data['classe']}</td></tr>
    <tr><td class="label">{$isEn ? 'Last Name' : 'Nom'}</td><td>{$data['nom']}</td><td class="label">{$isEn ? 'First Name' : 'Prénom(s)'}</td><td>{$data['prenoms']}</td></tr>
    <tr><td class="label">{$isEn ? 'Academic Year' : 'Année scolaire'}</td><td colspan="3">{$data['annee']}</td></tr>
  </table>

  <div class="section-title">{$isEn ? 'PAYMENT DETAILS' : 'DÉTAIL DU PAIEMENT'}</div>
  <table class="info">
    <tr><td class="label">{$isEn ? 'Installment' : 'Tranche'}</td><td><strong>{$trancheLabel}</strong></td><td class="label">{$isEn ? 'Amount Paid' : 'Montant versé'}</td><td><strong style="color:#0d1b4b;font-size:14px;">__MONTANT_PAIEMENT__ FCFA</strong></td></tr>
    <tr><td class="label">{$isEn ? 'Payment Method' : 'Mode de paiement'}</td><td>{$modeLabel}</td><td class="label">{$isEn ? 'Agent' : 'Agent'}</td><td>{$data['agent_nom']}</td></tr>
HTML;

if ($data['mode_paiement'] === 'virement') {
    $html .= "<tr><td class='label'>".($isEn?'Bank':'Banque')."</td><td>".e($data['nom_banque']??'')."</td><td class='label'>".($isEn?'Bank Reference':'Réf. bancaire')."</td><td>".e($data['reference_bancaire']??'—')."</td></tr>";
    $html .= "<tr><td class='label'>".($isEn?'Deposit Date':'Date dépôt')."</td><td colspan='3'>".($data['date_depot']?date('d/m/Y',strtotime($data['date_depot'])):'—')."</td></tr>";
}

$statusLabel = $situation['statut']==='paye' ? ($isEn?'Paid in Full':'Soldé') : ($situation['statut']==='partiel' ? ($isEn?'Partial':'Partiel') : ($isEn?'Unpaid':'Impayé'));
$statusClass = 'status-' . ($situation['statut']??'impaye');

$html .= <<<HTML2
  </table>

  <div class="section-title">{$isEn ? 'FINANCIAL SUMMARY' : 'RÉCAPITULATIF FINANCIER'}</div>
  <div class="totals">
    <table>
      <tr><td>{$isEn ? 'Total Due' : 'Total dû'}</td><td style="text-align:right;">
HTML2;
$html .= formatMontant($totalDu);
$html .= "</td></tr>";
if ($reduction > 0) {
    $html .= "<tr><td style='color:#d97706;'>".($isEn?"Discount ({$reduction}%)":"Réduction ({$reduction}%)")."</td><td style='text-align:right;color:#d97706;'>- ".formatMontant($situation['montantReduction']??0)."</td></tr>";
}
$html .= "<tr><td>".($isEn?'Total Paid':'Total payé')."</td><td style='text-align:right;color:#065f46;'>".formatMontant($totalPaye)."</td></tr>";
$html .= "<tr><td class='bold'>".($isEn?'Remaining Balance':'Reste à payer')."</td><td class='bold' style='text-align:right;color:".($reste>0?'#991b1b':'#065f46')."'>".formatMontant($reste)."</td></tr>";
$html .= "<tr><td colspan='2' style='padding-top:4px;'>Statut : <span class='status-badge {$statusClass}'>{$statusLabel}</span></td></tr>";

$html .= <<<HTML3
    </table>
  </div>

  <table style="width:100%;margin-top:16px;">
    <tr>
      <td style="width:55%;">
        <div class="signature-area">
          <div style="font-size:10px;font-weight:bold;color:#0d1b4b;">{$isEn ? 'Signature / Stamp' : 'Signature / Cachet'}</div>
          <div style="font-size:9px;margin-top:4px;">{$isEn ? 'Secretary / Cashier' : 'Secrétaire / Caissière'}</div>
          <div style="margin-top:24px;font-size:10px;">{$data['agent_nom']}</div>
        </div>
      </td>
      <td style="width:45%;padding-left:16px;vertical-align:top;font-size:10px;color:#64748b;">
        <div><strong>{$isEn?'Receipt generated':'Reçu généré le'} :</strong> {$dateGen}</div>
        <div style="margin-top:4px;">{$isEn?'This receipt is an official proof of payment.':'Ce reçu tient lieu de quittance officielle.'}</div>
        {$isDup ? '<div style="margin-top:4px;color:#f59e0b;font-weight:bold;">'.($isEn?'DUPLICATE — Not valid as original':'DUPLICATA — Non valable comme original').'</div>' : ''}
      </td>
    </tr>
  </table>

  <div class="footer-note">
    Groupe Scolaire Bilingue SINIYAT &mdash; gestion-gsb-siniyat.com &mdash;
    {$isEn ? 'All rights reserved' : 'Tous droits réservés'}
  </div>
</div>
</body>
</html>
HTML3;

// Replace placeholder with actual formatted amount
$html = str_replace('__MONTANT_PAIEMENT__', number_format((float)$data['montant'], 0, ',', ' '), $html);

$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Recu_' . $numRecu . '_' . e($data['matricule']) . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
