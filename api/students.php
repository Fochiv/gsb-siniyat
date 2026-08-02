<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireLogin();
$db   = getDB();
$activeYear = getActiveYear();

$method = $_SERVER['REQUEST_METHOD'];
$q      = trim($_GET['q'] ?? '');
$limit  = min((int)($_GET['limit'] ?? 20), 100);
$anneeId= (int)($_GET['annee_id'] ?? $activeYear['id']);

// Niveaux by section (used by classes.php dropdown)
if ($method === 'GET' && isset($_GET['niveaux'])) {
    $section = in_array($_GET['section']??'', ['francophone','anglophone']) ? $_GET['section'] : 'francophone';
    $stmt = $db->prepare("SELECT id, nom_fr, nom_en FROM niveaux WHERE actif=TRUE AND section=? ORDER BY ordre");
    $stmt->execute([$section]);
    jsonResponse(['niveaux' => $stmt->fetchAll()]);
}

if ($method === 'GET' && $q) {
    $stmt = $db->prepare("
        SELECT e.id, e.nom, e.prenoms, e.matricule, e.sexe,
               n.nom_fr AS classe, a.libelle AS annee
        FROM eleves e
        JOIN niveaux n ON n.id = e.niveau_id
        JOIN annees_scolaires a ON a.id = e.annee_id
        WHERE e.actif = TRUE AND e.annee_id = ?
          AND (e.nom ILIKE ? OR e.prenoms ILIKE ? OR e.matricule ILIKE ?)
        ORDER BY e.nom, e.prenoms LIMIT ?
    ");
    $stmt->execute([$anneeId, "%$q%", "%$q%", "%$q%", $limit]);
    jsonResponse(['students' => $stmt->fetchAll()]);
}

jsonResponse(['students' => []]);
