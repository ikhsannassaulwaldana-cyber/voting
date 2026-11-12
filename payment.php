<?php
require 'config.php';
require 'partials.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$stmt = $pdo->prepare("
    SELECT pay.*, p.name AS participant_name, p.number AS participant_number, p.category
    FROM payments pay
    LEFT JOIN participants p ON p.id = pay.participant_id
    WHERE pay.id = ? AND pay.user_id = ?
");
$stmt->execute([$id ?: 0, $_SESSION['user']['id']]);
$payment = $stmt->fetch();
if (!$payment) {
    http_response_code(404);
    header_html('Transaksi tidak ditemukan');
    echo '<div class="card empty-state"><h1 class="h4 section-title">Transaksi tidak ditemukan.</h1><a href="history.php">Kembali ke riwayat voting</a></div>';
    footer_html();
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ? AND user_id = ? FOR UPDATE');
            $stmt->execute([$payment['id'], $_SESSION['user']['id']]);
            $fresh = $stmt->fetch();
            if (!$fresh) {
                throw new RuntimeException('Transaksi tidak ditemukan.');
            }
            if ($fresh['status'] === 'pending') {
                if (empty($fresh['participant_id'])) {
                    throw new RuntimeException('Transaksi lama belum memiliki data peserta.');
                }
                $stmt = $pdo->prepare('INSERT INTO votes (payment_id, participant_id, quantity) VALUES (?, ?, ?)');
                $stmt->execute([$fresh['id'], $fresh['participant_id'], $fresh['vote_qty']]);
                $stmt = $pdo->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE id = ? AND status = 'pending'");
                $stmt->execute([$fresh['id']]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Status transaksi berubah saat diproses.');
                }
            } elseif ($fresh['status'] !== 'paid') {
                throw new RuntimeException('Transaksi ini tidak dapat dibayar.');
            }
            $pdo->commit();
            header('Location: history.php?success=1');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Process demo payment failed: ' . $exception->getMessage());
            $error = 'Pembayaran demo gagal diproses. Silakan coba kembali atau hubungi administrator.';
        }
    }
}

header_html('Pembayaran Demo');
?>
<div class="mb-4"><a href="history.php" class="text-decoration-none">← Kembali ke riwayat voting</a></div>
<div class="row justify-content-center"><div class="col-lg-7">
    <div class="card p-4 p-md-5">
        <div class="eyebrow">Checkout simulasi</div>
        <h1 class="section-title mt-2">Konfirmasi Pembayaran</h1>
        <p class="text-secondary">Invoice <strong class="text-dark"><?= e($payment['invoice']) ?></strong></p>
        <div class="d-flex justify-content-between py-3 border-bottom"><span>Peserta</span><strong><?= e($payment['participant_name'] ?: 'Peserta transaksi lama') ?></strong></div>
        <div class="d-flex justify-content-between py-3 border-bottom"><span>Jumlah vote</span><strong><?= number_format((int)$payment['vote_qty']) ?></strong></div>
        <div class="d-flex justify-content-between py-3 fs-5"><span>Total demo</span><strong><?= rupiah($payment['amount']) ?></strong></div>
        <div class="alert alert-info">Pembayaran ini hanya simulasi. Tidak ada uang sungguhan yang ditagihkan.</div>
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if ($payment['status'] === 'pending'): ?>
            <form method="post" data-loading data-confirm="Konfirmasi pembayaran simulasi ini?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-gold w-100">Simulasikan Pembayaran Berhasil</button>
            </form>
        <?php elseif ($payment['status'] === 'paid'): ?>
            <div class="alert alert-success mb-0">Pembayaran berhasil dan vote telah dicatat.</div>
        <?php else: ?>
            <div class="alert alert-danger mb-0">Pembayaran ini berstatus gagal dan tidak menambahkan vote.</div>
        <?php endif; ?>
    </div>
</div></div>
<?php footer_html(); ?>
