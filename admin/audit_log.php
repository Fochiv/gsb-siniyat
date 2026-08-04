<?php
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$user = requireAdmin();
$db   = getDB();
$pageTitle = "Journal d'audit";

$filterUser   = (int)($_GET['user_id'] ?? 0);
$filterAction = trim($_GET['action'] ?? '');
$filterFrom   = $_GET['from'] ?? '';
$filterTo     = $_GET['to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$sql = "SELECT j.*, CONCAT(u.prenom,' ',u.nom) AS user_name, u.login
        FROM journal_audit j
        LEFT JOIN utilisateurs u ON u.id = j.utilisateur_id
        WHERE 1=1";
$params = [];

if ($filterUser) { $sql .= " AND j.utilisateur_id=?"; $params[] = $filterUser; }
if ($filterAction) { $sql .= " AND j.action LIKE ?"; $params[] = "%$filterAction%"; }
if ($filterFrom) { $sql .= " AND j.date_heure >= ?"; $params[] = $filterFrom . ' 00:00:00'; }
if ($filterTo) { $sql .= " AND j.date_heure <= ?"; $params[] = $filterTo . ' 23:59:59'; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM journal_audit j LEFT JOIN utilisateurs u ON u.id=j.utilisateur_id WHERE 1=1" .
    ($filterUser ? " AND j.utilisateur_id=$filterUser" : '') .
    ($filterAction ? " AND j.action LIKE '%$filterAction%'" : ''));
$countStmt->execute();
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$sql .= " ORDER BY j.date_heure DESC LIMIT ? OFFSET ?";
$params[] = $perPage; $params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$users = $db->query("SELECT id, login, CONCAT(prenom,' ',nom) AS name FROM utilisateurs ORDER BY nom")->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="fade-in-load">
<h1 class="page-title"><i class="bi bi-journal-text"></i> <span data-i18n="audit.title">Journal d'audit</span></h1>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" data-i18n="audit.filter_user">Utilisateur</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $u['id']==$filterUser?'selected':'' ?>><?= e($u['name']) ?> (<?= e($u['login']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" data-i18n="audit.filter_action">Action</label>
                <input type="text" name="action" class="form-control form-control-sm" value="<?= e($filterAction) ?>"
                       placeholder="CONNEXION, PAIEMENT...">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Du</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= e($filterFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Au</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= e($filterTo) ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary-siniyat btn-sm flex-fill">
                    <i class="bi bi-filter"></i>
                </button>
                <a href="/admin/audit_log.php" class="btn btn-outline-secondary btn-sm flex-fill">
                    <i class="bi bi-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-2">
    <span class="text-muted small"><?= $totalRows ?> entrée(s) — page <?= $page ?>/<?= max(1,$totalPages) ?></span>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th data-i18n="audit.date">Date/Heure</th>
                        <th data-i18n="audit.user">Utilisateur</th>
                        <th data-i18n="audit.action">Action</th>
                        <th data-i18n="audit.entity">Entité</th>
                        <th data-i18n="audit.details">Détails</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log):
                        $badgeColors = [
                            'CONNEXION'=>'bg-success','DECONNEXION'=>'bg-secondary',
                            'PAIEMENT'=>'bg-primary','CREATION_ELEVE'=>'bg-info',
                            'MODIF_ELEVE'=>'bg-warning text-dark','CREATION_UTILISATEUR'=>'bg-danger',
                            'MODIF_UTILISATEUR'=>'bg-danger','CHANGEMENT_MDP'=>'bg-dark',
                            'CREATION_ANNEE'=>'bg-primary','PROMOTION_MASSE'=>'bg-info',
                        ];
                        $badge = $badgeColors[$log['action']] ?? 'bg-secondary';
                    ?>
                    <tr>
                        <td class="small text-muted text-nowrap"><?= date('d/m/Y H:i:s', strtotime($log['date_heure'])) ?></td>
                        <td><code class="small"><?= e($log['login']??'—') ?></code></td>
                        <td><span class="badge <?= $badge ?> small"><?= e($log['action']) ?></span></td>
                        <td class="small text-muted"><?= e($log['entite']??'') ?><?= $log['entite_id'] ? ' #'.$log['entite_id'] : '' ?></td>
                        <td class="small"><?= e($log['details']??'') ?></td>
                        <td class="small text-muted"><?= e($log['ip_address']??'') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4" data-i18n="common.no_data">Aucune entrée.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p==$page?'active':'' ?>">
            <a class="page-link" href="?page=<?= $p ?>&user_id=<?= $filterUser ?>&action=<?= urlencode($filterAction) ?>&from=<?= $filterFrom ?>&to=<?= $filterTo ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
