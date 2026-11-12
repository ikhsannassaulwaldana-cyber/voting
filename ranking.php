<?php
require 'config.php';
require 'partials.php';
$rows = $pdo->query("
    SELECT p.*, COALESCE(SUM(v.quantity), 0) AS total_vote
    FROM participants p
    LEFT JOIN votes v ON v.participant_id = p.id
    WHERE p.active = 1
    GROUP BY p.id
    ORDER BY total_vote DESC, p.number ASC
")->fetchAll();
header_html('Peringkat Voting');
?>
<div class="eyebrow">Hasil sementara</div>
<div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4"><h1 class="section-title mb-0">Peringkat Peserta</h1><span class="text-secondary small">Diperbarui secara langsung dari vote berhasil</span></div>
<div class="card p-3 p-md-4">
    <?php if ($rows): ?>
        <div class="table-responsive"><table class="table">
            <thead><tr><th>Posisi</th><th>Finalis</th><th>Kategori</th><th class="text-end">Vote</th></tr></thead>
            <tbody><?php foreach ($rows as $i => $participant): ?>
                <tr>
                    <td><span class="badge <?= $i < 3 ? 'badge-gold' : 'bg-light text-secondary' ?> rounded-pill px-3 py-2">#<?= $i + 1 ?></span></td>
                    <td><a class="d-flex align-items-center gap-3 text-decoration-none text-dark" href="detail.php?id=<?= (int)$participant['id'] ?>"><?= participant_photo($participant['photo'], $participant['name'], 'avatar-thumb') ?><span><strong><?= e($participant['name']) ?></strong><span class="d-block small text-secondary">No. <?= e($participant['number']) ?> · <?= e($participant['origin']) ?></span></span></a></td>
                    <td><span class="badge badge-gold"><?= e($participant['category']) ?></span></td>
                    <td class="text-end fw-bold"><?= number_format((int)$participant['total_vote']) ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php else: ?>
        <div class="empty-state">Peringkat akan muncul setelah peserta tersedia.</div>
    <?php endif; ?>
</div>
<?php footer_html(); ?>
