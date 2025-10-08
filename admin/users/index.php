<?php
require __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Helpers;

Auth::requireAdmin(array('super_admin', 'admin'));

$pageTitle = 'Üyeler';
$roleFilter = isset($_GET['role']) ? trim((string) $_GET['role']) : '';
$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$perPageOptions = array(25, 50, 100);
$perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 25;
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 25;
}

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$roleOptions = array('' => 'Tüm Roller');
foreach (Auth::roles() as $availableRole) {
    $roleOptions[$availableRole] = Auth::roleLabel($availableRole);
}

$statusOptions = array(
    '' => 'Tüm Durumlar',
    'active' => 'Aktif',
    'inactive' => 'Pasif',
    'suspended' => 'Askıya Alındı',
    'pending' => 'Onay Bekliyor',
);

if (!array_key_exists($roleFilter, $roleOptions)) {
    $roleFilter = '';
}

if (!array_key_exists($statusFilter, $statusOptions)) {
    $statusFilter = '';
}

$pdo = App\Database::connection();

$tableExists = static function ($pdoConnection, $tableName) {
    $stmt = $pdoConnection->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
    $stmt->execute(array('table' => $tableName));

    return (int) $stmt->fetchColumn() > 0;
};

$columnExists = static function ($pdoConnection, $tableName, $columnName) {
    $stmt = $pdoConnection->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute(array('table' => $tableName, 'column' => $columnName));

    return (int) $stmt->fetchColumn() > 0;
};

$selectStatements = array();
$hasUsersTable = $tableExists($pdo, 'users');
$hasResellersTable = $tableExists($pdo, 'resellers');

if ($hasUsersTable) {
    $userCompanyExpr = $columnExists($pdo, 'users', 'company') ? 'company' : 'NULL';
    $userStatusExpr = $columnExists($pdo, 'users', 'status') ? 'status' : "'active'";
    $userBalanceExpr = $columnExists($pdo, 'users', 'balance') ? 'balance' : '0';
    $userCreatedExpr = $columnExists($pdo, 'users', 'created_at') ? 'created_at' : 'CURRENT_TIMESTAMP';

    $selectStatements[] = 'SELECT id, name, email, role, ' . $userStatusExpr . ' AS status, ' . $userBalanceExpr . ' AS balance, ' . $userCreatedExpr . ' AS created_at, ' . $userCompanyExpr . ' AS company, \'' . 'users' . '\' AS source FROM users';
}

if ($hasResellersTable) {
    $resellerEmailColumn = $columnExists($pdo, 'resellers', 'email') ? 'email' : null;

    if ($resellerEmailColumn !== null) {
        $resellerNameColumn = null;
        foreach (array('name', 'company_name', 'company', 'full_name') as $candidate) {
            if ($columnExists($pdo, 'resellers', $candidate)) {
                $resellerNameColumn = $candidate;
                break;
            }
        }

        $resellerCompanyColumn = null;
        foreach (array('company', 'company_name', 'business_name') as $candidate) {
            if ($columnExists($pdo, 'resellers', $candidate)) {
                $resellerCompanyColumn = $candidate;
                break;
            }
        }

        $resellerStatusExpr = "'active'";
        if ($columnExists($pdo, 'resellers', 'status')) {
            $resellerStatusExpr = 'status';
        } elseif ($columnExists($pdo, 'resellers', 'state')) {
            $resellerStatusExpr = 'state';
        } elseif ($columnExists($pdo, 'resellers', 'is_active')) {
            $resellerStatusExpr = 'CASE WHEN is_active = 1 THEN \'active\' ELSE \'inactive\' END';
        }

        $resellerBalanceExpr = '0';
        foreach (array('balance', 'credit', 'wallet_balance') as $candidate) {
            if ($columnExists($pdo, 'resellers', $candidate)) {
                $resellerBalanceExpr = $candidate;
                break;
            }
        }

        $resellerCreatedExpr = 'CURRENT_TIMESTAMP';
        foreach (array('created_at', 'created_on', 'registered_at') as $candidate) {
            if ($columnExists($pdo, 'resellers', $candidate)) {
                $resellerCreatedExpr = $candidate;
                break;
            }
        }

        $resellerNameExpr = $resellerNameColumn !== null ? $resellerNameColumn : $resellerEmailColumn;
        $resellerCompanyExpr = $resellerCompanyColumn !== null ? $resellerCompanyColumn : ($resellerNameColumn !== null ? $resellerNameColumn : 'NULL');

        $selectStatements[] = 'SELECT id, ' . $resellerNameExpr . ' AS name, ' . $resellerEmailColumn . ' AS email, \'reseller\' AS role, ' . $resellerStatusExpr . ' AS status, ' . $resellerBalanceExpr . ' AS balance, ' . $resellerCreatedExpr . ' AS created_at, ' . $resellerCompanyExpr . ' AS company, \'resellers\' AS source FROM resellers';
    }
}

$members = array();
$totalRecords = 0;
$totalPages = 1;
$offset = ($page - 1) * $perPage;

if (!empty($selectStatements)) {
    $unionSql = implode(' UNION ALL ', $selectStatements);
    $conditions = array();
    $parameters = array();

    if ($roleFilter !== '') {
        $conditions[] = 'members.role = :role_filter';
        $parameters[':role_filter'] = $roleFilter;
    }

    if ($statusFilter !== '') {
        $conditions[] = 'members.status = :status_filter';
        $parameters[':status_filter'] = $statusFilter;
    }

    if ($searchQuery !== '') {
        $conditions[] = '(members.name LIKE :search_term OR members.email LIKE :search_term OR members.company LIKE :search_term)';
        $parameters[':search_term'] = '%' . $searchQuery . '%';
    }

    $whereClause = '';
    if (!empty($conditions)) {
        $whereClause = ' WHERE ' . implode(' AND ', $conditions);
    }

    $countSql = 'SELECT COUNT(*) FROM (' . $unionSql . ') AS members' . $whereClause;
    $countStmt = $pdo->prepare($countSql);
    foreach ($parameters as $placeholder => $value) {
        $countStmt->bindValue($placeholder, $value);
    }
    $countStmt->execute();
    $totalRecords = (int) $countStmt->fetchColumn();

    if ($totalRecords > 0) {
        $totalPages = (int) ceil($totalRecords / $perPage);
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }
    } else {
        $page = 1;
        $offset = 0;
        $totalPages = 1;
    }

    $dataSql = 'SELECT * FROM (' . $unionSql . ') AS members' . $whereClause . ' ORDER BY members.created_at DESC, members.id DESC LIMIT :limit OFFSET :offset';
    $dataStmt = $pdo->prepare($dataSql);
    foreach ($parameters as $placeholder => $value) {
        $dataStmt->bindValue($placeholder, $value);
    }
    $dataStmt->bindValue(':limit', (int) $perPage, \PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', (int) $offset, \PDO::PARAM_INT);
    $dataStmt->execute();
    $members = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);
}

$statusBadgeMap = array(
    'active' => array('label' => 'Aktif', 'class' => 'bg-success-subtle text-success'),
    'inactive' => array('label' => 'Pasif', 'class' => 'bg-secondary-subtle text-secondary'),
    'suspended' => array('label' => 'Askıda', 'class' => 'bg-warning-subtle text-warning'),
    'pending' => array('label' => 'Onay Bekliyor', 'class' => 'bg-warning-subtle text-warning'),
);

$formatDate = static function ($value) {
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return (string) $value;
    }

    return date('d.m.Y H:i', $timestamp);
};

$paginationQuery = $_GET;
unset($paginationQuery['page']);
$paginationQuery['per_page'] = $perPage;
$buildPageUrl = static function ($pageNumber) use ($paginationQuery) {
    $params = $paginationQuery;
    $params['page'] = $pageNumber;
    $query = http_build_query($params);

    return '/admin/users/index.php' . ($query !== '' ? '?' . $query : '');
};

$showingFrom = $totalRecords > 0 ? $offset + 1 : 0;
$showingTo = $totalRecords > 0 ? $offset + count($members) : 0;
$prevPage = $page > 1 ? $page - 1 : null;
$nextPage = $page < $totalPages ? $page + 1 : null;

$pageWindow = 2;
$startPage = max(1, $page - $pageWindow);
$endPage = min($totalPages, $page + $pageWindow);
$paginationNumbers = array();

if ($startPage > 1) {
    $paginationNumbers[] = 1;
    if ($startPage > 2) {
        $paginationNumbers[] = 'ellipsis';
    }
}

for ($i = $startPage; $i <= $endPage; $i++) {
    $paginationNumbers[] = $i;
}

if ($endPage < $totalPages) {
    if ($endPage < $totalPages - 1) {
        $paginationNumbers[] = 'ellipsis';
    }
    $paginationNumbers[] = $totalPages;
}

include __DIR__ . '/../../templates/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Üyeler</h1>
        <p class="text-muted mb-0">Platformunuzdaki tüm üyeleri görüntüleyin, filtreleyin ve yönetin.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/admin/users/create.php" class="btn btn-primary">
            <i class="bi bi-person-plus me-2"></i>
            Yeni Üye Oluştur
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-md-3 col-lg-3">
                <label for="filter-role" class="form-label">Rol</label>
                <select id="filter-role" name="role" class="form-select">
                    <?php foreach ($roleOptions as $value => $label): ?>
                        <option value="<?= Helpers::sanitize($value) ?>"<?= $value === $roleFilter ? ' selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3 col-lg-3">
                <label for="filter-status" class="form-label">Durum</label>
                <select id="filter-status" name="status" class="form-select">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= Helpers::sanitize($value) ?>"<?= $value === $statusFilter ? ' selected' : '' ?>><?= Helpers::sanitize($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <label for="filter-search" class="form-label">Ara</label>
                <input type="search" id="filter-search" name="q" class="form-control" value="<?= Helpers::sanitize($searchQuery) ?>" placeholder="Ad, e-posta veya şirket">
            </div>
            <div class="col-12 col-md-3 col-lg-2">
                <label for="filter-per-page" class="form-label">Sayfa Başına</label>
                <select id="filter-per-page" name="per_page" class="form-select">
                    <?php foreach ($perPageOptions as $option): ?>
                        <option value="<?= (int) $option ?>"<?= (int) $option === $perPage ? ' selected' : '' ?>><?= (int) $option ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-auto d-flex gap-2 align-items-end">
                <button type="submit" class="btn btn-outline-primary">Filtrele</button>
                <a href="/admin/users/index.php" class="btn btn-light" title="Filtreleri temizle">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-3">
        <h2 class="h5 mb-0">Üye Listesi</h2>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                <i class="bi bi-download me-1"></i>CSV Dışa Aktar (yakında)
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                Toplu İşlem
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="width: 40px;">
                        <input class="form-check-input" type="checkbox" disabled>
                    </th>
                    <th scope="col">Ad Soyad</th>
                    <th scope="col">E-posta</th>
                    <th scope="col">Rol</th>
                    <th scope="col">Durum</th>
                    <th scope="col" class="text-end">Bakiye</th>
                    <th scope="col">Kayıt Tarihi</th>
                    <th scope="col" class="text-end">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($members)): ?>
                    <?php foreach ($members as $member): ?>
                        <?php
                        $memberId = isset($member['id']) ? (int) $member['id'] : 0;
                        $memberName = isset($member['name']) ? trim((string) $member['name']) : '';
                        $memberEmail = isset($member['email']) ? trim((string) $member['email']) : '';
                        $memberRole = isset($member['role']) ? (string) $member['role'] : '';
                        $memberStatus = strtolower(isset($member['status']) ? (string) $member['status'] : '');
                        $memberCompany = isset($member['company']) ? trim((string) $member['company']) : '';
                        $memberBalance = isset($member['balance']) ? (float) $member['balance'] : 0.0;
                        $memberCreatedAt = isset($member['created_at']) ? (string) $member['created_at'] : '';
                        $statusMeta = isset($statusBadgeMap[$memberStatus]) ? $statusBadgeMap[$memberStatus] : array('label' => ucfirst($memberStatus !== '' ? $memberStatus : 'Bilinmiyor'), 'class' => 'bg-light text-body');
                        $roleLabel = Auth::roleLabel($memberRole !== '' ? $memberRole : 'reseller');
                        $formattedDate = $formatDate($memberCreatedAt);
                        ?>
                        <tr>
                            <td>
                                <input class="form-check-input" type="checkbox" disabled>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= Helpers::sanitize($memberName !== '' ? $memberName : $memberEmail) ?></div>
                                <?php if ($memberCompany !== '' && $memberCompany !== $memberName): ?>
                                    <div class="text-muted small"><?= Helpers::sanitize($memberCompany) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= Helpers::sanitize($memberEmail) ?></td>
                            <td><span class="badge bg-primary-subtle text-primary"><?= Helpers::sanitize($roleLabel) ?></span></td>
                            <td><span class="badge <?= Helpers::sanitize($statusMeta['class']) ?>"><?= Helpers::sanitize($statusMeta['label']) ?></span></td>
                            <td class="text-end"><?= Helpers::formatCurrencyHtml($memberBalance) ?></td>
                            <td><?= Helpers::sanitize($formattedDate) ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Üye işlemleri">
                                    <a class="btn btn-outline-secondary" href="/admin/users/edit.php?id=<?= $memberId ?>" title="Üyeyi düzenle">Düzenle</a>
                                    <button type="button" class="btn btn-outline-secondary" disabled>Durum</button>
                                    <button type="button" class="btn btn-outline-danger" disabled>Sil</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            Seçilen kriterlere uygun üye bulunamadı.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted">
            <?php if ($totalRecords > 0): ?>
                <?= Helpers::sanitize($totalRecords) ?> kayıt içinde <?= Helpers::sanitize($showingFrom) ?>-<?= Helpers::sanitize($showingTo) ?> arası görüntüleniyor
            <?php else: ?>
                Kayıt bulunamadı
            <?php endif; ?>
        </small>
        <nav aria-label="Üye sayfaları">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item<?= $prevPage === null ? ' disabled' : '' ?>">
                    <?php if ($prevPage === null): ?>
                        <span class="page-link">Önceki</span>
                    <?php else: ?>
                        <a class="page-link" href="<?= Helpers::sanitize($buildPageUrl($prevPage)) ?>">Önceki</a>
                    <?php endif; ?>
                </li>
                <?php foreach ($paginationNumbers as $pageNumber): ?>
                    <?php if ($pageNumber === 'ellipsis'): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php else: ?>
                        <li class="page-item<?= (int) $pageNumber === (int) $page ? ' active' : '' ?>">
                            <?php if ((int) $pageNumber === (int) $page): ?>
                                <span class="page-link"><?= Helpers::sanitize($pageNumber) ?></span>
                            <?php else: ?>
                                <a class="page-link" href="<?= Helpers::sanitize($buildPageUrl($pageNumber)) ?>"><?= Helpers::sanitize($pageNumber) ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <li class="page-item<?= $nextPage === null ? ' disabled' : '' ?>">
                    <?php if ($nextPage === null): ?>
                        <span class="page-link">Sonraki</span>
                    <?php else: ?>
                        <a class="page-link" href="<?= Helpers::sanitize($buildPageUrl($nextPage)) ?>">Sonraki</a>
                    <?php endif; ?>
                </li>
            </ul>
        </nav>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>

