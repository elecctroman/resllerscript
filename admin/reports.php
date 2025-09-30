<?php
require __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Helpers;
use App\Reports\ReportService;

if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'demo'], true)) {
    Helpers::redirect('/');
}

$pdo = Database::connection();
$pageTitle = 'Raporlar';

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$reportType = $_GET['type'] ?? 'orders';
$exportFormat = $_GET['export'] ?? null;

function exportReport(array $headers, array $rows, string $filename, string $format): void
{
    $format = strtolower($format);
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    if ($format === 'xlsx') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        echo "<table border=\"1\"><thead><tr>";
        foreach ($headers as $header) {
            echo '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
        exit;
    }
}

if ($exportFormat && in_array($reportType, ['orders', 'balances'], true)) {
    if ($reportType === 'orders') {
        $ordersForExport = ReportService::getOrdersByDateRange($pdo, $startDate, $endDate);
        $rows = array_map(static function (array $order): array {
            return [
                $order['id'],
                $order['package_name'] ?? '—',
                $order['name'],
                $order['email'],
                $order['status'],
                number_format((float)$order['total_amount'], 2, '.', ''),
                $order['created_at'],
            ];
        }, $ordersForExport);
        exportReport(
            ['ID', 'Paket', 'Ad Soyad', 'E-posta', 'Durum', 'Tutar', 'Oluşturulma'],
            $rows,
            'orders_' . $startDate . '_' . $endDate,
            $exportFormat
        );
    } else {
        $balancesForExport = ReportService::getBalanceTransactionsByDateRange($pdo, $startDate, $endDate);
        $rows = array_map(static function (array $transaction): array {
            return [
                $transaction['id'],
                $transaction['user_name'],
                $transaction['user_email'],
                $transaction['type'],
                number_format((float)$transaction['amount'], 2, '.', ''),
                $transaction['description'],
                $transaction['created_at'],
            ];
        }, $balancesForExport);
        exportReport(
            ['ID', 'Bayi', 'E-posta', 'Tür', 'Tutar', 'Açıklama', 'Tarih'],
            $rows,
            'balances_' . $startDate . '_' . $endDate,
            $exportFormat
        );
    }
}

$orders = ReportService::getOrdersByDateRange($pdo, $startDate, $endDate);
$balanceTransactions = ReportService::getBalanceTransactionsByDateRange($pdo, $startDate, $endDate);

$orderCount = count($orders);
$orderRevenue = array_sum(array_map(static fn ($order) => (float)$order['total_amount'], $orders));

$balanceCredit = 0.0;
$balanceDebit = 0.0;
foreach ($balanceTransactions as $transaction) {
    if ($transaction['type'] === 'credit') {
        $balanceCredit += (float)$transaction['amount'];
    } else {
        $balanceDebit += (float)$transaction['amount'];
    }
}

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Rapor Filtreleri</h5>
            </div>
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-12 col-sm-4 col-lg-3">
                        <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                        <input type="date" id="start_date" name="start_date" value="<?= Helpers::sanitize($startDate) ?>" class="form-control">
                    </div>
                    <div class="col-12 col-sm-4 col-lg-3">
                        <label for="end_date" class="form-label">Bitiş Tarihi</label>
                        <input type="date" id="end_date" name="end_date" value="<?= Helpers::sanitize($endDate) ?>" class="form-control">
                    </div>
                    <div class="col-12 col-sm-4 col-lg-3">
                        <label for="type" class="form-label">Öncelikli Tablo</label>
                        <select id="type" name="type" class="form-select">
                            <option value="orders" <?= $reportType === 'orders' ? 'selected' : '' ?>>Siparişler</option>
                            <option value="balances" <?= $reportType === 'balances' ? 'selected' : '' ?>>Bakiye Hareketleri</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-3">
                        <button type="submit" class="btn btn-primary w-100">Filtrele</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
                <div>
                    <h5 class="mb-1">Sipariş Raporu</h5>
                    <small class="text-muted">Toplam <?= (int)$orderCount ?> sipariş, toplam gelir $<?= number_format((float)$orderRevenue, 2, '.', ',') ?></small>
                </div>
                <?php
                $baseQuery = [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ];
                ?>
                <div class="d-flex gap-2 mt-3 mt-lg-0">
                    <a class="btn btn-outline-secondary btn-sm" href="/admin/reports.php?<?= http_build_query(array_merge($baseQuery, ['type' => 'orders', 'export' => 'csv'])) ?>">CSV Dışa Aktar</a>
                    <a class="btn btn-outline-secondary btn-sm" href="/admin/reports.php?<?= http_build_query(array_merge($baseQuery, ['type' => 'orders', 'export' => 'xlsx'])) ?>">Excel Dışa Aktar</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Paket</th>
                            <th>Ad Soyad</th>
                            <th>E-posta</th>
                            <th>Durum</th>
                            <th class="text-end">Tutar ($)</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($orders): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?= (int)$order['id'] ?></td>
                                <td><?= Helpers::sanitize($order['package_name'] ?? '—') ?></td>
                                <td><?= Helpers::sanitize($order['name']) ?></td>
                                <td><?= Helpers::sanitize($order['email']) ?></td>
                                <td><span class="badge bg-light text-dark text-uppercase"><?= Helpers::sanitize($order['status']) ?></span></td>
                                <td class="text-end">$<?= number_format((float)$order['total_amount'], 2, '.', ',') ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Seçili tarih aralığında sipariş bulunamadı.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
                <div>
                    <h5 class="mb-1">Bakiye Hareketleri</h5>
                    <small class="text-muted">Toplam yatırılan $<?= number_format($balanceCredit, 2, '.', ',') ?> | Toplam harcanan $<?= number_format($balanceDebit, 2, '.', ',') ?></small>
                </div>
                <div class="d-flex gap-2 mt-3 mt-lg-0">
                    <a class="btn btn-outline-secondary btn-sm" href="/admin/reports.php?<?= http_build_query(array_merge($baseQuery, ['type' => 'balances', 'export' => 'csv'])) ?>">CSV Dışa Aktar</a>
                    <a class="btn btn-outline-secondary btn-sm" href="/admin/reports.php?<?= http_build_query(array_merge($baseQuery, ['type' => 'balances', 'export' => 'xlsx'])) ?>">Excel Dışa Aktar</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Bayi</th>
                            <th>E-posta</th>
                            <th>Tür</th>
                            <th class="text-end">Tutar ($)</th>
                            <th>Açıklama</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($balanceTransactions): ?>
                        <?php foreach ($balanceTransactions as $transaction): ?>
                            <tr>
                                <td>#<?= (int)$transaction['id'] ?></td>
                                <td><?= Helpers::sanitize($transaction['user_name']) ?></td>
                                <td><?= Helpers::sanitize($transaction['user_email']) ?></td>
                                <td>
                                    <span class="badge <?= $transaction['type'] === 'credit' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $transaction['type'] === 'credit' ? 'Yatırma' : 'Harcanan' ?>
                                    </span>
                                </td>
                                <td class="text-end">$<?= number_format((float)$transaction['amount'], 2, '.', ',') ?></td>
                                <td><?= Helpers::sanitize($transaction['description'] ?? '-') ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($transaction['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Seçili tarih aralığında bakiye hareketi bulunamadı.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
