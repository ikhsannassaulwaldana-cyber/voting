<?php
require 'config.php';
require 'partials.php';
require_login();
$stmt = $pdo->prepare("
    SELECT pay.*, p.name AS participant_name, p.number AS participant_number, p.category
    FROM payments pay
    LEFT JOIN participants p ON p.id = pay.participant_id
    WHERE pay.user_id = ?
    ORDER BY pay.created_at DESC, pay.id DESC
");
$stmt->execute([$_SESSION['user']['id']]);
$rows = $stmt->fetchAll();
header_html('Riwayat Voting');
?>
<div class="eyebrow">Aktivitas akunmu</div><h1 class="section-title mb-4">Riwayat Voting</h1>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success" role="status">Pembayaran demo berhasil. Vote sudah dihitung.</div><?php endif; ?>
<div class="card p-3 p-md-4">
    <?php if ($rows): ?>
        <div class="table-responsive"><table class="table">
            <thead><tr><th>Invoice</th><th>Peserta</th><th>Vote</th><th>Total</th><th>Status</th><th>Tanggal</th><th></th></tr></thead>
            <tbody><?php foreach ($rows as $row): ?>
                <tr>
                    <td class="fw-semibold"><?= e($row['invoice']) ?></td>
                    <td><?= e($row['participant_name'] ?: 'Data peserta lama') ?></td>
                    <td><?= number_format((int)$row['vote_qty']) ?></td>
                    <td><?= rupiah($row['amount']) ?></td>
                    <td><span class="badge rounded-pill <?= $row['status'] === 'paid' ? 'text-bg-success' : ($row['status'] === 'pending' ? 'text-bg-warning' : 'text-bg-secondary') ?>"><?= e(ucfirst($row['status'])) ?></span></td>
                    <td class="small text-secondary"><?= e($row['created_at']) ?></td>
                    <td><?php if ($row['status'] === 'pending'): ?><a class="btn btn-sm btn-outline-gold" href="payment.php?id=<?= (int)$row['id'] ?>">Lanjut bayar</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php else: ?>
        <div class="empty-state"><h2 class="h5 section-title">Belum ada transaksi</h2><p>Transaksi vote akan tercatat di sini.</p><a class="btn btn-gold" href="participants.php">Jelajahi peserta</a></div>
    <?php endif; ?>
</div>
<?php footer_html(); ?>
