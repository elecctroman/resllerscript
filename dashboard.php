<?php
require __DIR__ . '/bootstrap.php';

use App\Helpers;
use App\Database;

if (empty($_SESSION['user'])) {
    Helpers::redirect('/');
}

$user = $_SESSION['user'];
$pdo = Database::connection();

$pageTitle = 'Kontrol Paneli';


include __DIR__ . '/templates/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">

                </div>
            </div>
        </div>
    </div>


</div>
<?php include __DIR__ . '/templates/footer.php';
