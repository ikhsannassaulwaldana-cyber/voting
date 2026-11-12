<?php
require 'config.php';
require 'partials.php';

$totalVotes = (int)$pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM votes")->fetchColumn();
$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM participants WHERE active = 1")->fetchColumn();
$featured = $pdo->query("
    SELECT p.*, COALESCE(SUM(v.quantity), 0) AS total_vote
    FROM participants p
    LEFT JOIN votes v ON v.participant_id = p.id
    WHERE p.active = 1
    GROUP BY p.id
    ORDER BY total_vote DESC, p.number ASC
    LIMIT 4
")->fetchAll();

header_html('Beranda - Kakang Senduk Ponorogo');
?>
<section class="hero mb-5">
    <div class="eyebrow mb-3">Ajang Pemilihan Duta Budaya</div>
    <h1 class="display-3 mb-3">Warisan budaya.<br><span style="color:var(--gold)">Masa depan Ponorogo.</span></h1>
    <p class="lead hero-copy">Kenali para finalis Kakang Senduk, rayakan potensi generasi muda, dan dukung pilihanmu melalui voting simulasi.</p>
    <a class="btn btn-gold btn-lg mt-3 px-4" href="participants.php">Jelajahi Peserta <span aria-hidden="true">→</span></a>
</section>

<div class="row g-3 mb-5">
    <div class="col-sm-6"><div class="stat"><div class="stat-label">Finalis aktif</div><div class="stat-value"><?= number_format($activeCount) ?></div></div></div>
    <div class="col-sm-6"><div class="stat"><div class="stat-label">Vote tercatat</div><div class="stat-value"><?= number_format($totalVotes) ?></div></div></div>
</div>

<div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
    <div><div class="eyebrow">Kenali para finalis</div><h2 class="section-title mb-0">Peserta pilihan</h2></div>
    <a class="btn btn-outline-gold" href="participants.php">Lihat semua peserta</a>
</div>
<?php if ($featured): ?>
    <div class="row g-4 mb-5">
        <?php foreach ($featured as $participant): ?>
            <div class="col-sm-6 col-lg-3">
                <article class="card card-hover h-100 overflow-hidden">
                    <?= participant_photo($participant['photo'], $participant['name'], 'participant-photo') ?>
                    <div class="p-3">
                        <div class="d-flex justify-content-between gap-2"><span class="badge badge-gold"><?= e($participant['category']) ?></span><span class="small text-secondary">No. <?= e($participant['number']) ?></span></div>
                        <h3 class="h5 mt-3 mb-1"><?= e($participant['name']) ?></h3>
                        <p class="small text-secondary mb-2"><?= e($participant['origin']) ?></p>
                        <div class="small mb-3"><?= number_format((int)$participant['total_vote']) ?> vote</div>
                        <a class="btn btn-outline-gold w-100" href="detail.php?id=<?= (int)$participant['id'] ?>">Lihat profil</a>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card empty-state mb-5">Profil peserta akan ditampilkan di sini.</div>
<?php endif; ?>

<div class="eyebrow">Mudah dan transparan</div>
<h2 class="section-title">Cara mengikuti voting</h2>
<div class="row g-3 mt-1">
    <div class="col-md-4"><div class="card card-hover p-4 h-100"><div class="eyebrow">01</div><h3 class="h5 mt-2">Pilih finalis</h3><p class="text-secondary mb-0">Kenali profil Kakang dan Senduk yang ingin kamu dukung.</p></div></div>
    <div class="col-md-4"><div class="card card-hover p-4 h-100"><div class="eyebrow">02</div><h3 class="h5 mt-2">Tentukan vote</h3><p class="text-secondary mb-0">Masukkan jumlah vote, maksimal 100 vote per transaksi. Harga demo Rp1.000 per vote.</p></div></div>
    <div class="col-md-4"><div class="card card-hover p-4 h-100"><div class="eyebrow">03</div><h3 class="h5 mt-2">Konfirmasi demo</h3><p class="text-secondary mb-0">Selesaikan simulasi pembayaran; vote baru tercatat setelah transaksi berhasil.</p></div></div>
</div>
<?php footer_html(); ?>
