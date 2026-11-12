<?php
require 'config.php';
require 'partials.php';

$category = $_GET['category'] ?? '';
$search = trim($_GET['q'] ?? '');
$conditions = ['p.active = 1'];
$params = [];
if (in_array($category, ['Kakang', 'Senduk'], true)) {
    $conditions[] = 'p.category = ?';
    $params[] = $category;
}
if ($search !== '') {
    $conditions[] = '(p.name LIKE ? OR p.origin LIKE ? OR CAST(p.number AS CHAR) LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
}
$sql = "
    SELECT p.*, COALESCE(SUM(v.quantity), 0) AS total_vote
    FROM participants p
    LEFT JOIN votes v ON v.participant_id = p.id
    WHERE " . implode(' AND ', $conditions) . "
    GROUP BY p.id
    ORDER BY p.number
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

header_html('Daftar Peserta');
?>
<div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
    <div><div class="eyebrow">Kenali para finalis</div><h1 class="section-title mb-0">Daftar Peserta</h1></div>
    <span class="badge badge-gold rounded-pill px-3 py-2"><?= count($rows) ?> peserta</span>
</div>
<form method="get" class="card p-3 mb-4">
    <div class="row g-2">
        <div class="col-md-6"><label class="visually-hidden" for="participant-search">Cari peserta</label><input id="participant-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Cari nama, nomor, atau asal daerah"></div>
        <div class="col-md-4"><label class="visually-hidden" for="participant-category">Kategori</label><select id="participant-category" class="form-select" name="category"><option value="">Semua kategori</option><option value="Kakang" <?= $category === 'Kakang' ? 'selected' : '' ?>>Kakang</option><option value="Senduk" <?= $category === 'Senduk' ? 'selected' : '' ?>>Senduk</option></select></div>
        <div class="col-md-2 d-grid"><button class="btn btn-outline-gold">Cari peserta</button></div>
    </div>
</form>
<?php if ($rows): ?>
    <div class="row g-4">
        <?php foreach ($rows as $participant): ?>
            <div class="col-sm-6 col-lg-3">
                <article class="card card-hover h-100 overflow-hidden">
                    <?= participant_photo($participant['photo'], $participant['name'], 'participant-photo') ?>
                    <div class="p-3 d-flex flex-column flex-grow-1">
                        <div class="d-flex justify-content-between gap-2"><span class="badge badge-gold"><?= e($participant['category']) ?></span><small class="text-secondary">No. <?= e($participant['number']) ?></small></div>
                        <h2 class="h5 mt-3 mb-1"><?= e($participant['name']) ?></h2>
                        <div class="text-secondary small"><?= e($participant['origin']) ?></div>
                        <p class="small mt-2 text-secondary"><?= e($participant['bio'] ?? '') ?></p>
                        <div class="small mb-3 mt-auto fw-semibold"><?= number_format((int)$participant['total_vote']) ?> vote</div>
                        <div class="d-grid gap-2"><a class="btn btn-outline-gold" href="detail.php?id=<?= (int)$participant['id'] ?>">Lihat profil</a><a class="btn btn-gold" href="vote.php?id=<?= (int)$participant['id'] ?>">Dukung peserta</a></div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card empty-state"><h2 class="h5 section-title">Peserta tidak ditemukan</h2><p class="mb-3">Coba kata kunci atau kategori lain.</p><a class="btn btn-outline-gold align-self-center" href="participants.php">Hapus filter</a></div>
<?php endif; ?>
<?php footer_html(); ?>
