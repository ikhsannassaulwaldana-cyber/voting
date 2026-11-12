<?php
require 'config.php';
require 'partials.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$stmt = $pdo->prepare("
    SELECT p.*, COALESCE(SUM(v.quantity), 0) AS total_vote
    FROM participants p
    LEFT JOIN votes v ON v.participant_id = p.id
    WHERE p.id = ? AND p.active = 1
    GROUP BY p.id
");
$stmt->execute([$id ?: 0]);
$participant = $stmt->fetch();
if (!$participant) {
    http_response_code(404);
    header_html('Peserta tidak ditemukan');
    echo '<div class="card empty-state"><h1 class="h4 section-title">Peserta tidak ditemukan</h1><a href="participants.php">Kembali ke daftar peserta</a></div>';
    footer_html();
    exit;
}

header_html('Profil ' . $participant['name']);
?>
<div class="mb-4"><a href="participants.php" class="text-decoration-none">← Kembali ke peserta</a></div>
<article class="card p-3 p-md-4">
    <div class="row g-4 align-items-center">
        <div class="col-lg-5"><?= participant_photo($participant['photo'], $participant['name'], 'detail-photo') ?></div>
        <div class="col-lg-7">
            <div class="eyebrow"><?= e($participant['category']) ?> • Nomor <?= e($participant['number']) ?></div>
            <h1 class="section-title display-5 mt-2"><?= e($participant['name']) ?></h1>
            <p class="text-secondary fs-5"><?= e($participant['origin']) ?></p>
            <p class="lh-lg"><?= nl2br(e($participant['bio'] ?? 'Profil peserta belum dilengkapi.')) ?></p>
            <div class="stat d-inline-block mb-4"><div class="stat-label">Vote saat ini</div><div class="stat-value"><?= number_format((int)$participant['total_vote']) ?></div></div>
            <div><a class="btn btn-gold btn-lg" href="vote.php?id=<?= (int)$participant['id'] ?>">Dukung peserta</a></div>
        </div>
    </div>
</article>
<?php footer_html(); ?>
