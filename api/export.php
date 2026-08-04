<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireLogin();
$db   = getDB();

$type     = $_GET['type'] ?? 'class';
$niveauId = (int)($_GET['niveau_id'] ?? 0);
$anneeId  = (int)($_GET['annee_id'] ?? getActiveYear()['id']);
$format   = strtolower($_GET['format'] ?? 'excel');

// Build query
if ($type === 'class' && $niveauId) {
    $stmt = $db->prepare("
        SELECT e.matricule, e.nom, e.prenoms, e.sexe,
               DATE_FORMAT(e.date_naissance,'%d/%m/%Y') AS date_naissance,
               e.quartier, e.nom_pere, e.tel_pere, e.nom_mere, e.tel_mere,
               n.nom_fr AS classe, a.libelle AS annee,
               e.statut_eleve,
               COALESCE(SUM(CASE WHEN p.annule=FALSE THEN p.montant ELSE 0 END),0) AS total_paye,
               COALESCE((SELECT SUM(t.montant) FROM grille_frais g JOIN tranches t ON t.grille_id=g.id WHERE g.annee_id=e.annee_id AND g.niveau_id=e.niveau_id),0) AS total_du
        FROM eleves e
        JOIN niveaux n ON n.id = e.niveau_id
        JOIN annees_scolaires a ON a.id = e.annee_id
        LEFT JOIN paiements p ON p.eleve_id = e.id
        WHERE e.actif = TRUE AND e.annee_id = ? AND e.niveau_id = ?
        GROUP BY e.id, n.nom_fr, a.libelle
        ORDER BY e.nom, e.prenoms
    ");
    $stmt->execute([$anneeId, $niveauId]);
    $filename = 'Eleves_classe';
} elseif ($type === 'all_classes') {
    $stmt = $db->prepare("
        SELECT e.matricule, e.nom, e.prenoms, e.sexe,
               DATE_FORMAT(e.date_naissance,'%d/%m/%Y') AS date_naissance,
               e.quartier, e.nom_pere, e.tel_pere, e.nom_mere, e.tel_mere,
               n.nom_fr AS classe, a.libelle AS annee,
               e.statut_eleve,
               COALESCE(SUM(CASE WHEN p.annule=FALSE THEN p.montant ELSE 0 END),0) AS total_paye,
               COALESCE((SELECT SUM(t.montant) FROM grille_frais g JOIN tranches t ON t.grille_id=g.id WHERE g.annee_id=e.annee_id AND g.niveau_id=e.niveau_id),0) AS total_du
        FROM eleves e
        JOIN niveaux n ON n.id = e.niveau_id
        JOIN annees_scolaires a ON a.id = e.annee_id
        LEFT JOIN paiements p ON p.eleve_id = e.id
        WHERE e.actif = TRUE AND e.annee_id = ?
        GROUP BY e.id, n.nom_fr, a.libelle
        ORDER BY n.ordre, e.nom
    ");
    $stmt->execute([$anneeId]);
    $filename = 'Eleves_toutes_classes';
} else {
    jsonResponse(['error' => 'Type invalide'], 400);
}

$rows = $stmt->fetchAll();

$headers = ['Matricule','Nom','Prénom(s)','Sexe','Date Naissance','Quartier',
            'Père','Tél. Père','Mère','Tél. Mère','Classe','Année','Statut',
            'Total Payé (FCFA)','Total Dû (FCFA)','Reste (FCFA)','Statut Financier'];

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM for Excel
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ';');
    foreach ($rows as $r) {
        $reste = max(0, (float)$r['total_du'] - (float)$r['total_paye']);
        $sf = $reste <= 0 ? 'Soldé' : ((float)$r['total_paye'] > 0 ? 'Partiel' : 'Impayé');
        fputcsv($out, [
            $r['matricule'], $r['nom'], $r['prenoms'], $r['sexe']==='M'?'Masculin':'Féminin',
            $r['date_naissance'], $r['quartier']??'', $r['nom_pere']??'', $r['tel_pere']??'',
            $r['nom_mere']??'', $r['tel_mere']??'', $r['classe'], $r['annee'],
            ucfirst($r['statut_eleve']), number_format((float)$r['total_paye'],0,',',' '),
            number_format((float)$r['total_du'],0,',',' '), number_format($reste,0,',',' '), $sf
        ], ';');
    }
    fclose($out);
    exit;
}

// Excel (xlsx) via PhpSpreadsheet
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Élèves');

// Header row style
$headerStyle = [
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D1B4B']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];

foreach ($headers as $col => $h) {
    $cell = chr(65 + $col) . '1';
    $sheet->setCellValue($cell, $h);
    $sheet->getStyle($cell)->applyFromArray($headerStyle);
    $sheet->getColumnDimensionByColumn($col + 1)->setAutoSize(true);
}

$row = 2;
foreach ($rows as $r) {
    $reste = max(0, (float)$r['total_du'] - (float)$r['total_paye']);
    $sf = $reste <= 0 ? 'Soldé' : ((float)$r['total_paye'] > 0 ? 'Partiel' : 'Impayé');
    $values = [
        $r['matricule'], $r['nom'], $r['prenoms'], $r['sexe']==='M'?'Masculin':'Féminin',
        $r['date_naissance'], $r['quartier']??'', $r['nom_pere']??'', $r['tel_pere']??'',
        $r['nom_mere']??'', $r['tel_mere']??'', $r['classe'], $r['annee'],
        ucfirst($r['statut_eleve']), (float)$r['total_paye'],
        (float)$r['total_du'], $reste, $sf
    ];
    foreach ($values as $col => $val) {
        $sheet->setCellValue(chr(65+$col).$row, $val);
    }
    // Color financial status
    $sfCell = chr(65+16).$row;
    $sfColor = $reste<=0 ? '198754' : ((float)$r['total_paye']>0 ? 'FFC107' : 'DC3545');
    $sheet->getStyle($sfCell)->getFont()->getColor()->setRGB($sfColor);
    $row++;
}

// Freeze header
$sheet->freezePane('A2');

// Output
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Ymd') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
