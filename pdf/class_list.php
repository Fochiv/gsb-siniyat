<?php
/**
 * PDF — Liste des élèves d'une classe
 * Usage: /pdf/class_list.php?niveau_id=X&annee_id=Y
 */
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireLogin();
$db   = getDB();

$niveauId = (int)($_GET['niveau_id'] ?? 0);
$anneeId  = (int)($_GET['annee_id']  ?? 0);
$withFin  = !empty($_GET['finances']); // include financial status column

if (!$niveauId || !$anneeId) redirect('/secretary/classes.php');

// Niveau & année info
$niveau = $db->prepare("SELECT * FROM niveaux WHERE id=?");
$niveau->execute([$niveauId]);
$niveau = $niveau->fetch();
if (!$niveau) redirect('/secretary/classes.php');

$annee = $db->prepare("SELECT * FROM annees_scolaires WHERE id=?");
$annee->execute([$anneeId]);
$annee = $annee->fetch();
if (!$annee) redirect('/secretary/classes.php');

// Students
$stu = $db->prepare("
    SELECT e.id, e.matricule, e.nom, e.prenoms, e.sexe, e.date_naissance,
           e.statut_eleve, e.nom_pere, e.tel_pere, e.nom_mere, e.tel_mere,
           COALESCE((SELECT SUM(p.montant) FROM paiements p WHERE p.eleve_id=e.id AND p.annule=FALSE),0) AS total_paye
    FROM eleves e
    WHERE e.niveau_id=? AND e.annee_id=? AND e.actif=TRUE
    ORDER BY e.nom, e.prenoms
");
$stu->execute([$niveauId, $anneeId]);
$students = $stu->fetchAll();

if (empty($students)) redirect('/secretary/classes.php?niveau='.$niveauId.'&annee='.$anneeId);

// Financial status (only if requested)
$finData = [];
if ($withFin) {
    foreach ($students as $s) {
        $sit = getSituationFinanciere((int)$s['id']);
        $finData[$s['id']] = $sit;
    }
}

// Stats
$nbG = count(array_filter($students, fn($s) => $s['sexe'] === 'M'));
$nbF = count(array_filter($students, fn($s) => $s['sexe'] === 'F'));

// Load Dompdf
require_once dirname(__DIR__) . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$opts = new Options();
$opts->set('isRemoteEnabled', false);
$opts->set('isHtml5ParserEnabled', true);
$opts->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($opts);

// Logo base64
$logoPath = dirname(__DIR__) . '/assets/img/logo.png';
$logoB64  = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '';

$dateImpression = date('d/m/Y à H:i');
$niveauNom      = $niveau['nom_fr'];
$anneeLib       = $annee['libelle'];
$section        = $niveau['section'] === 'anglophone' ? 'Anglophone' : 'Francophone';
$nbTotal        = count($students);

// Build rows
$rows = '';
foreach ($students as $i => $s) {
    $bg = ($i % 2 === 0) ? '#ffffff' : '#f8fafc';
    $sexeBg  = $s['sexe'] === 'M' ? '#e0f2fe' : '#fce7f3';
    $sexeCol = $s['sexe'] === 'M' ? '#0369a1' : '#9d174d';
    $dob = $s['date_naissance'] ? date('d/m/Y', strtotime($s['date_naissance'])) : '—';
    $contact = '';
    if ($s['tel_pere'])  $contact .= $s['nom_pere'] ? htmlspecialchars($s['nom_pere']).' : '.$s['tel_pere'] : $s['tel_pere'];
    if ($s['tel_mere'])  $contact .= ($contact ? ' / ' : '') . ($s['nom_mere'] ? htmlspecialchars($s['nom_mere']).' : ' : '') . $s['tel_mere'];
    if (!$contact) $contact = '—';

    $statut = '';
    if ($s['statut_eleve'] === 'redoublant') $statut = ' <span style="background:#fef3c7;color:#92400e;padding:1px 4px;border-radius:3px;font-size:8px;">Redoublant</span>';

    $finCol = '';
    if ($withFin) {
        $sit = $finData[$s['id']] ?? [];
        if (!empty($sit)) {
            $st = $sit['statut'] ?? 'impaye';
            $colors = ['paye'=>['#d1fae5','#065f46','Soldé'],'partiel'=>['#fef3c7','#92400e','Partiel'],'impaye'=>['#fee2e2','#991b1b','Impayé']];
            [$bg2,$col2,$lbl] = $colors[$st] ?? $colors['impaye'];
            $finCol = "<td style='background:{$bg};text-align:center;'><span style='background:{$bg2};color:{$col2};padding:2px 6px;border-radius:3px;font-size:8px;font-weight:bold;'>{$lbl}</span></td>";
        } else {
            $finCol = "<td style='background:{$bg};text-align:center;'>—</td>";
        }
    }

    $rows .= "<tr>
        <td style='background:{$bg};text-align:center;font-weight:bold;'>" . ($i+1) . "</td>
        <td style='background:{$bg};font-size:9px;'><code>" . htmlspecialchars($s['matricule'] ?? '—') . "</code></td>
        <td style='background:{$bg};font-weight:bold;'>" . htmlspecialchars($s['nom']) . " " . htmlspecialchars($s['prenoms']) . "{$statut}</td>
        <td style='background:{$bg};text-align:center;'><span style='background:{$sexeBg};color:{$sexeCol};padding:2px 6px;border-radius:3px;font-weight:bold;font-size:9px;'>" . $s['sexe'] . "</span></td>
        <td style='background:{$bg};text-align:center;font-size:9px;'>{$dob}</td>
        <td style='background:{$bg};font-size:9px;'>{$contact}</td>
        {$finCol}
    </tr>\n";
}

$finHeader = $withFin ? "<th>Statut paiement</th>" : '';

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 0; }
  .page { padding: 16px 20px; }
  .header { display: flex; align-items: center; background: #0d1b4b; color: white; padding: 12px 16px; border-radius: 6px; margin-bottom: 10px; }
  .logo-circle { width: 50px; height: 50px; border-radius: 50%; background: white; overflow: hidden; margin-right: 14px; border: 2px solid rgba(255,255,255,.3); flex-shrink: 0; }
  .logo-circle img { width: 50px; height: 50px; object-fit: cover; }
  .school-name { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
  .school-sub  { font-size: 10px; opacity: 0.85; }
  .list-title  { text-align: center; background: #1a3580; color: white; padding: 7px; font-size: 13px; font-weight: bold; letter-spacing: .5px; border-radius: 4px; margin-bottom: 8px; }
  .stats-bar   { display: flex; gap: 16px; background: #f0f4ff; border: 1px solid #c7d7f8; padding: 6px 12px; border-radius: 4px; margin-bottom: 8px; font-size: 9px; }
  .stats-bar b { font-size: 12px; }
  table { width: 100%; border-collapse: collapse; }
  thead th { background: #0d1b4b; color: white; padding: 6px 8px; font-size: 9px; font-weight: bold; text-align: left; white-space: nowrap; }
  tbody td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
  .footer-note { text-align: center; font-size: 8px; color: #94a3b8; margin-top: 10px; border-top: 1px solid #e2e8f0; padding-top: 6px; }
  code { font-family: monospace; background: #f1f5f9; padding: 1px 3px; border-radius: 2px; }
</style>
</head>
<body>
<div class="page">
  <div class="header">
    <div class="logo-circle"><img src="{$logoB64}" alt="Logo"></div>
    <div>
      <div class="school-name">Groupe Scolaire Bilingue SINIYAT</div>
      <div class="school-sub">Siniyat Bilingual School Group &nbsp;|&nbsp; Année scolaire {$anneeLib}</div>
    </div>
  </div>

  <div class="list-title">LISTE DES ÉLÈVES — {$niveauNom} ({$section})</div>

  <div class="stats-bar">
    <div>Total : <b>{$nbTotal}</b> élève(s)</div>
    <div>Garçons : <b style="color:#0369a1;">{$nbG}</b></div>
    <div>Filles : <b style="color:#9d174d;">{$nbF}</b></div>
    <div style="margin-left:auto;">Imprimé le : {$dateImpression}</div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:25px;">#</th>
        <th style="width:70px;">Matricule</th>
        <th>Nom &amp; Prénoms</th>
        <th style="width:25px;">Sexe</th>
        <th style="width:60px;">Date naiss.</th>
        <th>Contact parent</th>
        {$finHeader}
      </tr>
    </thead>
    <tbody>
      {$rows}
    </tbody>
  </table>

  <div class="footer-note">
    Groupe Scolaire Bilingue SINIYAT &mdash; Classe : {$niveauNom} &mdash; Année {$anneeLib} &mdash; Tous droits réservés
  </div>
</div>
</body>
</html>
HTML;

$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = 'Liste_' . preg_replace('/[^a-zA-Z0-9]/', '_', $niveauNom) . '_' . str_replace('-', '_', $anneeLib) . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
