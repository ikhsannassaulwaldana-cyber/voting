<?php
require 'config.php';
require 'partials.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$stmt = $pdo->prepare('SELECT * FROM participants WHERE id = ? AND active = 1');
$stmt->execute([$id ?: 0]);
$participant = $stmt->fetch();
if (!$participant) {
    http_response_code(404);
    header_html('Peserta tidak ditemukan');
    echo '<div class="card empty-state"><h1 class="h4 section-title">Peserta tidak ditemukan atau tidak aktif.</h1><a href="participants.php">Kembali ke peserta</a></div>';
    footer_html();
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    if (!csrf_is_valid()) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.';
    } elseif (!$quantity || $quantity < 1 || $quantity > 100) {
        $error = 'Jumlah vote harus antara 1 sampai 100.';
    } else {
        try {
            $invoice = 'KS' . date('ymdHis') . random_int(100000, 999999);
            $amount = $quantity * 1000;
            $stmt = $pdo->prepare("
                INSERT INTO payments (user_id, participant_id, invoice, amount, vote_qty)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user']['id'], $participant['id'], $invoice, $amount, $quantity]);
            header('Location: payment.php?id=' . (int)$pdo->lastInsertId());
            exit;
        } catch (Throwable $exception) {
            error_log('Create voting payment failed: ' . $exception->getMessage());
            $error = 'Transaksi belum dapat dibuat. Silakan coba kembali.';
        }
    }
}

header_html('Dukung ' . $participant['name']);
?>
<div class="mb-4"><a href="detail.php?id=<?= (int)$participant['id'] ?>" class="text-decoration-none">← Kembali ke profil peserta</a></div>
<div class="row justify-content-center"><div class="col-lg-8">
    <div class="card p-4 p-md-5">
        <div class="row align-items-center g-4 mb-4">
            <div class="col-sm-4"><?= participant_photo($participant['photo'], $participant['name'], 'avatar-thumb w-100') ?></div>
            <div class="col-sm-8"><div class="eyebrow"><?= e($participant['category']) ?> • Nomor <?= e($participant['number']) ?></div><h1 class="section-title mt-2"><?= e($participant['name']) ?></h1><p class="text-secondary mb-0"><?= e($participant['origin']) ?></p></div>
        </div>
        <h2 class="h5 section-title">Tentukan dukunganmu</h2>
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" data-loading>
            <?= csrf_field() ?>
            <label class="form-label fw-semibold" for="quantity">Jumlah vote</label>
            <input id="quantity" class="form-control mb-3" type="number" name="quantity" min="1" max="100" value="<?= e($_POST['quantity'] ?? '1') ?>" required>
            <div class="alert alert-warning">Harga simulasi: <strong>Rp1.000 / vote</strong>. Tidak ada uang sungguhan yang ditagihkan.</div>
            <button type="submit" class="btn btn-gold w-100">Lanjut ke Pembayaran Demo</button>
        </form>
    </div>
</div></div>
<?php footer_html(); ?>
