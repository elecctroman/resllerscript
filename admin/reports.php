<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\Helpers;
use App\Reporting;

Auth::requireRoles(array('super_admin', 'admin', 'finance'));

$currentUser = $_SESSION['user'];
$pageTitle = 'Raporlar';

$today = new DateTime('today');
$defaultEnd = clone $today;
$defaultStart = (clone $today)->modify('-29 days');

$fromInput = isset($_GET['from']) ? trim($_GET['from']) : '';
$toInput = isset($_GET['to']) ? trim($_GET['to']) : '';

$startDate = DateTime::createFromFormat('Y-m-d', $fromInput) ?: clone $defaultStart;
$endDate = DateTime::createFromFormat('Y-m-d', $toInput) ?: clone $defaultEnd;

if ($startDate > $endDate) {
    $temp = clone $startDate;
    $startDate = clone $endDate;
    $endDate = $temp;
}

$exportType = isset($_GET['export']) ? $_GET['export'] : '';

if ($exportType === 'orders' || $exportType === 'balances') {
    $filenameSuffix = $startDate->format('Ymd') . '-' . $endDate->format('Ymd');

    if ($exportType === 'orders') {
        $ordersData = Reporting::ordersInRange($startDate, $endDate);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="orders-' . $filenameSuffix . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, array('Tip', 'Sipariş ID', 'Ad Soyad', 'E-posta', 'Ürün/Paket', 'Durum', 'Tutar', 'Oluşturma')); 

        foreach ($ordersData['packages'] as $row) {
            fputcsv($output, array(
                'Paket',
                $row['id'],
                $row['name'],
                $row['email'],
                $row['package_name'],
                $row['status'],
                number_format((float)$row['total_amount'], 2, '.', ''),
                $row['created_at'],
            ));
        }

        foreach ($ordersData['products'] as $row) {
            $total = (float)$row['price'] * (int)max(1, $row['quantity']);
            fputcsv($output, array(
                'Ürün',
                $row['id'],
                $row['user_name'],
                $row['user_email'],
                $row['product_name'],
                $row['status'],
                number_format($total, 2, '.', ''),
                $row['created_at'],
            ));
        }

        fclose($output);
        exit;
    }

    if ($exportType === 'balances') {
        $transactions = Reporting::balanceTransactionsInRange($startDate, $endDate);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="balances-' . $filenameSuffix . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, array('İşlem ID', 'Bayi', 'E-posta', 'Tip', 'Tutar', 'Açıklama', 'Tarih'));

        foreach ($transactions as $transaction) {
            fputcsv($output, array(
                $transaction['id'],
                $transaction['user_name'],
                $transaction['user_email'],
                $transaction['type'],
                number_format((float)$transaction['amount'], 2, '.', ''),
                $transaction['description'],
                $transaction['created_at'],
            ));
        }

        fclose($output);
        exit;
    }
}

$summary = Reporting::summaryInRange($startDate, $endDate);
$orders = Reporting::ordersInRange($startDate, $endDate);
$balances = Reporting::balanceTransactionsInRange($startDate, $endDate);

include __DIR__ . '/../templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Raporlama Filtresi</h5>
            </div>
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label">Başlangıç Tarihi</label>
                        <input type="date" name="from" class="form-control" value="<?= Helpers::sanitize($startDate->format('Y-m-d')) ?>">
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label">Bitiş Tarihi</label>
                        <input type="date" name="to" class="form-control" value="<?= Helpers::sanitize($endDate->format('Y-m-d')) ?>">
                    </div>
                    <div class="col-sm-12 col-md-3">
                        <button type="submit" class="btn btn-primary">Filtrele</button>
                    </div>
                    <div class="col-sm-12 col-md-3 d-flex gap-2">
                        <?php $queryString = '?from=' . $startDate->format('Y-m-d') . '&to=' . $endDate->format('Y-m-d'); ?>
                        <a href="<?= Helpers::sanitize($queryString . '&export=orders') ?>" class="btn btn-outline-secondary flex-grow-1">Siparişleri CSV İndir</a>
                        <a href="<?= Helpers::sanitize($queryString . '&export=balances') ?>" class="btn btn-outline-secondary flex-grow-1">Bakiyeleri CSV İndir</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Sipariş Özeti</h5>
            </div>
            <div class="card-body">
                <p class="fs-4 mb-1"><?= (int)$summary['packages']['total'] + (int)$summary['products']['total'] ?></p>
                <p class="text-muted mb-0">Toplam sipariş (Paket: <?= (int)$summary['packages']['total'] ?> · Ürün: <?= (int)$summary['products']['total'] ?>)</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Gelir Özeti</h5>
            </div>
            <div class="card-body">
                <p class="fs-4 mb-1"><?= Helpers::sanitize(Helpers::formatCurrency($summary['packages']['revenue'] + $summary['products']['revenue'])) ?></p>
                <p class="text-muted mb-0">Tamamlanan paket ve ürün siparişlerinden elde edilen toplam gelir.</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Bakiye Hareketleri</h5>
            </div>
            <div class="card-body">
                <p class="fs-5 mb-1">Kredi: <?= Helpers::sanitize(Helpers::formatCurrency($summary['balance']['credits'])) ?></p>
                <p class="fs-5">Borç: <?= Helpers::sanitize(Helpers::formatCurrency($summary['balance']['debits'])) ?></p>
                <p class="text-muted mb-0">İlgili tarih aralığındaki toplam bakiye giriş/çıkışları.</p>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Paket Siparişleri</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Bayi</th>
                            <th>Paket</th>
                            <th>Tutar</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$orders['packages']): ?>
                        <tr>
                            <td colspan="6" class="text-muted text-center">Seçilen tarih aralığında paket siparişi bulunmuyor.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders['packages'] as $row): ?>
                            <tr>
                                <td><?= (int)$row['id'] ?></td>
                                <td>
                                    <strong><?= Helpers::sanitize($row['name']) ?></strong><br>
                                    <small class="text-muted"><?= Helpers::sanitize($row['email']) ?></small>
                                </td>
                                <td><?= Helpers::sanitize($row['package_name']) ?></td>
                                <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$row['total_amount'])) ?></td>
                                <td><span class="badge bg-light text-dark text-uppercase"><?= Helpers::sanitize($row['status']) ?></span></td>
                                <td><?= date('d.m.Y H:i', strtotime($row['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Ürün Siparişleri</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Bayi</th>
                            <th>Ürün</th>
                            <th>Adet</th>
                            <th>Tutar</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$orders['products']): ?>
                        <tr>
                            <td colspan="7" class="text-muted text-center">Seçilen tarih aralığında ürün siparişi bulunmuyor.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders['products'] as $row): ?>
                            <?php $total = (float)$row['price'] * (int)max(1, $row['quantity']); ?>
                            <tr>
                                <td><?= (int)$row['id'] ?></td>
                                <td>
                                    <strong><?= Helpers::sanitize($row['user_name']) ?></strong><br>
                                    <small class="text-muted"><?= Helpers::sanitize($row['user_email']) ?></small>
                                </td>
                                <td><?= Helpers::sanitize($row['product_name']) ?></td>
                                <td><?= (int)$row['quantity'] ?></td>
                                <td><?= Helpers::sanitize(Helpers::formatCurrency($total)) ?></td>
                                <td><span class="badge bg-light text-dark text-uppercase"><?= Helpers::sanitize($row['status']) ?></span></td>
                                <td><?= date('d.m.Y H:i', strtotime($row['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Bakiye Hareketleri</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Bayi</th>
                            <th>Tip</th>
                            <th>Tutar</th>
                            <th>Açıklama</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$balances): ?>
                        <tr>
                            <td colspan="6" class="text-muted text-center">Seçilen tarih aralığında bakiye hareketi bulunmuyor.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($balances as $transaction): ?>
                            <tr>
                                <td><?= (int)$transaction['id'] ?></td>
                                <td>
                                    <strong><?= Helpers::sanitize($transaction['user_name']) ?></strong><br>
                                    <small class="text-muted"><?= Helpers::sanitize($transaction['user_email']) ?></small>
                                </td>
                                <td>
                                    <?php if ($transaction['type'] === 'credit'): ?>
                                        <span class="badge bg-success">Kredi</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Borç</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= Helpers::sanitize(Helpers::formatCurrency((float)$transaction['amount'])) ?></td>
                                <td><?= Helpers::sanitize($transaction['description']) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($transaction['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
