<?php
require 'config.php';
require 'partials.php';
require_admin();

function store_participant_photo($field)
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Foto gagal diunggah. Silakan pilih ulang file foto.');
    }
    if ($file['size'] < 1 || $file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Ukuran foto maksimal 2 MB.');
    }

    $allowed = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $image = @getimagesize($file['tmp_name']);
    if (!isset($allowed[$extension]) || $mime !== $allowed[$extension] || !$image || $image['mime'] !== $allowed[$extension]) {
        throw new RuntimeException('Format foto harus JPG, JPEG, PNG, atau WEBP yang valid.');
    }

    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'participants';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder upload tidak dapat dibuat. Periksa izin folder aplikasi.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Foto tidak dapat disimpan. Periksa izin folder uploads.');
    }
    return $filename;
}

function remove_participant_photo($filename)
{
    $url = participant_photo_url($filename);
    if ($url !== '') {
        $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $url);
        if (is_file($path) && !unlink($path)) {
            error_log('Unable to remove participant image: ' . $path);
        }
    }
}

function admin_redirect($view = 'dashboard')
{
    header('Location: admin.php?view=' . rawurlencode($view));
    exit;
}

$view = $_GET['view'] ?? 'dashboard';
$validViews = ['dashboard', 'participants', 'users', 'payments', 'votes', 'ranking', 'reports', 'settings'];
if (!in_array($view, $validViews, true)) {
    $view = 'dashboard';
}
$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedView = $_POST['view'] ?? 'dashboard';
    if (!in_array($postedView, $validViews, true)) {
        $postedView = 'dashboard';
    }

    if (!csrf_is_valid()) {
        $_SESSION['admin_flash'] = ['type' => 'danger', 'message' => 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.'];
        admin_redirect($postedView);
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'save_participant') {
        $id = filter_var($_POST['id'] ?? '0', FILTER_VALIDATE_INT);
        $number = filter_var($_POST['number'] ?? '', FILTER_VALIDATE_INT);
        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? '';
        $origin = trim($_POST['origin'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        $existing = null;
        $newPhoto = null;

        if (!$number || $number < 1 || $name === '' || strlen($name) > 120 || !in_array($category, ['Kakang', 'Senduk'], true) || strlen($origin) > 120) {
            $_SESSION['admin_flash'] = ['type' => 'danger', 'message' => 'Lengkapi data peserta dengan benar. Nomor harus berupa bilangan positif.'];
            admin_redirect($postedView);
        }
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM participants WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            if (!$existing) {
                $_SESSION['admin_flash'] = ['type' => 'danger', 'message' => 'Data peserta yang akan diedit tidak ditemukan.'];
                admin_redirect('participants');
            }
        }

        try {
            $newPhoto = store_participant_photo('photo');
            $photo = $newPhoto ?: ($existing['photo'] ?? '');
            if (!$newPhoto && isset($_POST['remove_photo'])) {
                $photo = '';
            }

            if ($existing) {
                $stmt = $pdo->prepare('UPDATE participants SET number = ?, name = ?, category = ?, origin = ?, bio = ?, photo = ?, active = ? WHERE id = ?');
                $stmt->execute([$number, $name, $category, $origin, $bio, $photo, $active, $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO participants (number, name, category, origin, bio, photo, active) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$number, $name, $category, $origin, $bio, $photo, $active]);
            }

            if ($existing && ($newPhoto || (isset($_POST['remove_photo']) && $existing['photo'] !== ''))) {
                remove_participant_photo($existing['photo']);
            }
            $_SESSION['admin_flash'] = ['type' => 'success', 'message' => $existing ? 'Data peserta berhasil diperbarui.' : 'Peserta berhasil ditambahkan.'];
            admin_redirect('participants');
        } catch (RuntimeException $exception) {
            if ($newPhoto) {
                remove_participant_photo($newPhoto);
            }
            $_SESSION['admin_flash'] = ['type' => 'danger', 'message' => $exception->getMessage()];
        } catch (PDOException $exception) {
            if ($newPhoto) {
                remove_participant_photo($newPhoto);
            }
            error_log('Save participant failed: ' . $exception->getMessage());
            $_SESSION['admin_flash'] = [
                'type' => 'danger',
                'message' => $exception->getCode() === '23000'
                    ? 'Nomor peserta tersebut sudah digunakan.'
                    : 'Data peserta belum berhasil disimpan. Silakan coba kembali.',
            ];
        }
        admin_redirect('participants');
    }

    if ($action === 'delete_participant' || $action === 'toggle_participant') {
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        $stmt = $pdo->prepare('SELECT * FROM participants WHERE id = ?');
        $stmt->execute([$id ?: 0]);
        $participant = $stmt->fetch();
        if (!$participant) {
            $_SESSION['admin_flash'] = ['type' => 'danger', 'message' => 'Peserta tidak ditemukan.'];
            admin_redirect('participants');
        }
        try {
            if ($action === 'toggle_participant') {
                $stmt = $pdo->prepare('UPDATE participants SET active = IF(active = 1, 0, 1) WHERE id = ?');
                $stmt->execute([$id]);
                $_SESSION['admin_flash'] = ['type' => 'success', 'message' => 'Status peserta berhasil diperbarui.'];
            } else {
                $stmt = $pdo->prepare('SELECT EXISTS(SELECT 1 FROM votes WHERE participant_id = ?) OR EXISTS(SELECT 1 FROM payments WHERE participant_id = ?)');
                $stmt->execute([$id, $id]);
                if ($stmt->fetchColumn()) {
                    $stmt = $pdo->prepare('UPDATE participants SET active = 0 WHERE id = ?');
                    $stmt->execute([$id]);
                    $_SESSION['admin_flash'] = ['type' => 'warning', 'message' => 'Peserta memiliki riwayat transaksi atau vote, sehingga dinonaktifkan agar histori tetap aman.'];
                } else {
                    $stmt = $pdo->prepare('DELETE FROM participants WHERE id = ?');
                    $stmt->execute([$id]);
                    remove_participant_photo($participant['photo']);
                    $_SESSION['admin_flash'] = ['type' => 'success', 'message' => 'Peserta berhasil dihapus.'];
                }
            }
        } catch (PDOException $exception) {
            error_log('Update participant failed: ' . $exception->getMessage());
            $_SESSION['admin_flash'] = ['type' => 'danger', 'message' => 'Perubahan peserta gagal disimpan.'];
        }
        admin_redirect('participants');
    }

    $_SESSION['admin_flash'] = ['type' => 'danger', 'message' => 'Aksi tidak dikenali.'];
    admin_redirect($postedView);
}

$participantCount = (int)$pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn();
$userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$voteCount = (int)$pdo->query('SELECT COALESCE(SUM(quantity), 0) FROM votes')->fetchColumn();
$transactionCount = (int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$paidCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'paid'")->fetchColumn();

$editParticipant = null;
if ($view === 'participants' && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM participants WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editParticipant = $stmt->fetch() ?: null;
}
$isAddingParticipant = $view === 'participants' && isset($_GET['add']);

header_html('Dashboard Admin - Kakang Senduk');
?>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="sidebar-heading">Panel Admin</div>
        <nav class="nav flex-column" aria-label="Navigasi admin">
            <?php
            $menu = [
                'dashboard' => 'Dashboard',
                'participants' => 'Kelola Peserta',
                'users' => 'Kelola Pengguna',
                'payments' => 'Data Pembayaran',
                'votes' => 'Data Voting',
                'ranking' => 'Peringkat',
                'reports' => 'Laporan',
                'settings' => 'Pengaturan',
            ];
            foreach ($menu as $key => $label):
                $active = $view === $key && !($key === 'participants' && ($isAddingParticipant || $editParticipant));
            ?>
                <a class="nav-link <?= $active ? 'active' : '' ?>" href="admin.php?view=<?= e($key) ?>"><?= e($label) ?></a>
                <?php if ($key === 'participants'): ?><a class="nav-link <?= $isAddingParticipant ? 'active' : '' ?>" href="admin.php?view=participants&add=1">+ Tambah Peserta</a><?php endif; ?>
            <?php endforeach; ?>
            <form method="post" action="logout.php" class="mt-2">
                <?= csrf_field() ?>
                <button class="nav-link btn btn-link text-start w-100" type="submit">Keluar</button>
            </form>
        </nav>
    </aside>

    <section class="admin-content">
        <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>

        <?php if ($view === 'dashboard'): ?>
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4"><div><div class="eyebrow">Ringkasan pengelolaan</div><h1 class="section-title mb-0">Dashboard</h1></div><span class="badge badge-gold rounded-pill px-3 py-2">Administrator</span></div>
            <div class="row g-3 mb-4">
                <?php foreach ([
                    ['Total peserta', $participantCount],
                    ['Total pengguna', $userCount],
                    ['Total vote masuk', $voteCount],
                    ['Total transaksi', $transactionCount],
                    ['Pembayaran pending', $pendingCount],
                    ['Pembayaran berhasil', $paidCount],
                ] as $stat): ?>
                    <div class="col-sm-6 col-xl-4"><div class="stat"><div class="stat-label"><?= e($stat[0]) ?></div><div class="stat-value"><?= number_format((int)$stat[1]) ?></div></div></div>
                <?php endforeach; ?>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-xl-6"><div class="card p-3 p-md-4 h-100"><h2 class="h5 section-title">Vote per hari</h2><canvas id="voteChart" height="130" aria-label="Grafik jumlah vote"></canvas></div></div>
                <div class="col-xl-6"><div class="card p-3 p-md-4 h-100"><h2 class="h5 section-title">Transaksi demo per hari</h2><canvas id="paymentChart" height="130" aria-label="Grafik jumlah transaksi"></canvas></div></div>
            </div>
            <?php
            $topParticipants = $pdo->query("
                SELECT p.id, p.number, p.name, p.category, p.photo, COALESCE(SUM(v.quantity), 0) AS total_vote
                FROM participants p LEFT JOIN votes v ON v.participant_id = p.id
                GROUP BY p.id ORDER BY total_vote DESC, p.number LIMIT 5
            ")->fetchAll();
            $recentPayments = $pdo->query("
                SELECT pay.id, pay.invoice, pay.amount, pay.vote_qty, pay.status, pay.created_at, u.name AS user_name, p.name AS participant_name
                FROM payments pay JOIN users u ON u.id = pay.user_id
                LEFT JOIN participants p ON p.id = pay.participant_id
                ORDER BY pay.created_at DESC, pay.id DESC LIMIT 8
            ")->fetchAll();
            ?>
            <div class="row g-3">
                <div class="col-xl-6"><div class="card p-3"><h2 class="h5 section-title p-2">Peserta dengan vote terbanyak</h2><div class="table-responsive"><table class="table"><thead><tr><th>Peserta</th><th>Kategori</th><th>Vote</th></tr></thead><tbody>
                    <?php foreach ($topParticipants as $participant): ?><tr><td><a class="d-flex align-items-center gap-2 text-decoration-none text-dark" href="detail.php?id=<?= (int)$participant['id'] ?>"><?= participant_photo($participant['photo'], $participant['name'], 'avatar-thumb') ?><span><?= e($participant['name']) ?></span></a></td><td><?= e($participant['category']) ?></td><td class="fw-bold"><?= number_format((int)$participant['total_vote']) ?></td></tr><?php endforeach; ?>
                    <?php if (!$topParticipants): ?><tr><td colspan="3" class="text-secondary text-center">Belum ada data peserta.</td></tr><?php endif; ?>
                </tbody></table></div></div></div>
                <div class="col-xl-6"><div class="card p-3"><h2 class="h5 section-title p-2">Transaksi terbaru</h2><div class="table-responsive"><table class="table"><thead><tr><th>Invoice</th><th>Pengguna</th><th>Status</th></tr></thead><tbody>
                    <?php foreach ($recentPayments as $payment): ?><tr><td><strong><?= e($payment['invoice']) ?></strong><span class="d-block small text-secondary"><?= e($payment['participant_name'] ?: 'Peserta lama') ?> · <?= rupiah($payment['amount']) ?></span></td><td><?= e($payment['user_name']) ?></td><td><span class="badge rounded-pill <?= $payment['status'] === 'paid' ? 'text-bg-success' : ($payment['status'] === 'pending' ? 'text-bg-warning' : 'text-bg-secondary') ?>"><?= e(ucfirst($payment['status'])) ?></span></td></tr><?php endforeach; ?>
                    <?php if (!$recentPayments): ?><tr><td colspan="3" class="text-secondary text-center">Belum ada transaksi.</td></tr><?php endif; ?>
                </tbody></table></div></div></div>
            </div>
            <?php
            $voteSeries = $pdo->query("SELECT DATE(created_at) AS day, SUM(quantity) AS total FROM votes WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY day")->fetchAll();
            $paymentSeries = $pdo->query("SELECT DATE(created_at) AS day, COUNT(*) AS total FROM payments WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY day")->fetchAll();
            ?>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
            <script>
            (function () {
                var voteData = <?= json_encode($voteSeries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
                var paymentData = <?= json_encode($paymentSeries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
                var labels = Array.from({length: 7}, function (_, index) {
                    var date = new Date();
                    date.setDate(date.getDate() - 6 + index);
                    return date.toISOString().slice(0, 10);
                });
                function renderChart(id, rows, color, label) {
                    var values = labels.map(function (day) {
                        var row = rows.find(function (item) { return item.day === day; });
                        return row ? Number(row.total) : 0;
                    });
                    new Chart(document.getElementById(id), {
                        type: 'line',
                        data: {labels: labels, datasets: [{label: label, data: values, borderColor: color, backgroundColor: color + '22', fill: true, tension: .35}]},
                        options: {responsive: true, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {precision: 0}}}}
                    });
                }
                renderChart('voteChart', voteData, '#b98c3f', 'Vote');
                renderChart('paymentChart', paymentData, '#1d3858', 'Transaksi');
            }());
            </script>

        <?php elseif ($view === 'participants'): ?>
            <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4"><div><div class="eyebrow">Data finalis</div><h1 class="section-title mb-0"><?= $editParticipant ? 'Edit Peserta' : ($isAddingParticipant ? 'Tambah Peserta' : 'Kelola Peserta') ?></h1></div><?php if (!$isAddingParticipant && !$editParticipant): ?><a href="admin.php?view=participants&add=1" class="btn btn-gold">+ Tambah peserta</a><?php endif; ?></div>
            <?php if ($isAddingParticipant || $editParticipant): ?>
                <div class="card p-3 p-md-4 mb-4">
                    <form method="post" enctype="multipart/form-data" action="admin.php?view=participants" data-loading>
                        <?= csrf_field() ?><input type="hidden" name="view" value="participants"><input type="hidden" name="action" value="save_participant"><input type="hidden" name="id" value="<?= (int)($editParticipant['id'] ?? 0) ?>">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label fw-semibold" for="number">Nomor peserta</label><input id="number" class="form-control" type="number" min="1" name="number" value="<?= e($editParticipant['number'] ?? '') ?>" required></div>
                            <div class="col-md-5"><label class="form-label fw-semibold" for="name">Nama peserta</label><input id="name" class="form-control" name="name" maxlength="120" value="<?= e($editParticipant['name'] ?? '') ?>" required></div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="category">Kategori</label><select id="category" class="form-select" name="category" required><option value="Kakang" <?= ($editParticipant['category'] ?? '') === 'Kakang' ? 'selected' : '' ?>>Kakang</option><option value="Senduk" <?= ($editParticipant['category'] ?? '') === 'Senduk' ? 'selected' : '' ?>>Senduk</option></select></div>
                            <div class="col-md-6"><label class="form-label fw-semibold" for="origin">Asal daerah</label><input id="origin" class="form-control" name="origin" maxlength="120" value="<?= e($editParticipant['origin'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold" for="photo">Foto peserta</label><input id="photo" class="form-control" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-preview-input="photo-preview"><div class="form-text">JPG, JPEG, PNG, WEBP · Maksimal 2 MB</div></div>
                            <div class="col-12"><label class="form-label fw-semibold" for="bio">Deskripsi / biodata</label><textarea id="bio" class="form-control" name="bio" rows="4"><?= e($editParticipant['bio'] ?? '') ?></textarea></div>
                            <div class="col-md-6 d-flex align-items-center"><div class="form-check"><input class="form-check-input" id="active" type="checkbox" name="active" value="1" <?= !isset($editParticipant['active']) || $editParticipant['active'] ? 'checked' : '' ?>><label class="form-check-label" for="active">Peserta aktif dan tampil untuk pemilih</label></div></div>
                            <div class="col-md-6 d-flex align-items-center gap-3">
                                <?php if (!empty($editParticipant['photo'])): ?><?= participant_photo($editParticipant['photo'], $editParticipant['name'], 'avatar-thumb') ?><div class="form-check"><input class="form-check-input" id="remove-photo" type="checkbox" name="remove_photo" value="1"><label class="form-check-label" for="remove-photo">Hapus foto saat ini</label></div><?php endif; ?>
                                <img id="photo-preview" class="avatar-thumb <?= empty($editParticipant['photo']) ? 'd-none' : 'd-none' ?>" alt="Preview foto baru">
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-4"><button class="btn btn-gold" type="submit">Simpan peserta</button><a class="btn btn-outline-secondary" href="admin.php?view=participants">Batal</a></div>
                    </form>
                </div>
            <?php endif; ?>

            <?php
            $search = trim($_GET['q'] ?? '');
            $categoryFilter = $_GET['category'] ?? '';
            $conditions = [];
            $params = [];
            if ($search !== '') {
                $conditions[] = '(p.name LIKE ? OR p.origin LIKE ? OR CAST(p.number AS CHAR) LIKE ?)';
                $term = '%' . $search . '%';
                array_push($params, $term, $term, $term);
            }
            if (in_array($categoryFilter, ['Kakang', 'Senduk'], true)) {
                $conditions[] = 'p.category = ?';
                $params[] = $categoryFilter;
            }
            if (isset($_GET['status']) && in_array($_GET['status'], ['0', '1'], true)) {
                $conditions[] = 'p.active = ?';
                $params[] = (int)$_GET['status'];
            }
            $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $countQuery = $pdo->prepare('SELECT COUNT(*) FROM participants p ' . $where);
            $countQuery->execute($params);
            $filteredCount = (int)$countQuery->fetchColumn();
            $perPage = 10;
            $page = max(1, (int)($_GET['page'] ?? 1));
            $pageCount = max(1, (int)ceil($filteredCount / $perPage));
            $page = min($page, $pageCount);
            $offset = ($page - 1) * $perPage;
            $listQuery = $pdo->prepare("
                SELECT p.*, COALESCE(SUM(v.quantity), 0) AS total_vote
                FROM participants p LEFT JOIN votes v ON v.participant_id = p.id
                $where GROUP BY p.id ORDER BY p.number LIMIT ? OFFSET ?
            ");
            foreach ($params as $index => $value) {
                $listQuery->bindValue($index + 1, $value);
            }
            $listQuery->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
            $listQuery->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
            $listQuery->execute();
            $participantRows = $listQuery->fetchAll();
            ?>
            <?php if (!$isAddingParticipant && !$editParticipant): ?>
                <form method="get" class="card p-3 mb-3"><input type="hidden" name="view" value="participants"><div class="row g-2">
                    <div class="col-md-5"><label class="visually-hidden" for="admin-search">Cari peserta</label><input id="admin-search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Cari nama, nomor, asal"></div>
                    <div class="col-md-3"><label class="visually-hidden" for="admin-category">Kategori</label><select id="admin-category" class="form-select" name="category"><option value="">Semua kategori</option><option value="Kakang" <?= $categoryFilter === 'Kakang' ? 'selected' : '' ?>>Kakang</option><option value="Senduk" <?= $categoryFilter === 'Senduk' ? 'selected' : '' ?>>Senduk</option></select></div>
                    <div class="col-md-2"><select class="form-select" name="status"><option value="">Semua status</option><option value="1" <?= ($_GET['status'] ?? '') === '1' ? 'selected' : '' ?>>Aktif</option><option value="0" <?= ($_GET['status'] ?? '') === '0' ? 'selected' : '' ?>>Nonaktif</option></select></div>
                    <div class="col-md-2 d-grid"><button class="btn btn-outline-gold">Filter</button></div>
                </div></form>
                <div class="card p-3"><div class="table-responsive"><table class="table"><thead><tr><th>Peserta</th><th>No.</th><th>Kategori</th><th>Status</th><th>Vote</th><th>Aksi</th></tr></thead><tbody>
                    <?php foreach ($participantRows as $participant): ?><tr>
                        <td><div class="d-flex align-items-center gap-2"><?= participant_photo($participant['photo'], $participant['name'], 'avatar-thumb') ?><span class="fw-semibold"><?= e($participant['name']) ?><span class="d-block small text-secondary"><?= e($participant['origin']) ?></span></span></div></td>
                        <td><?= e($participant['number']) ?></td><td><?= e($participant['category']) ?></td>
                        <td><span class="badge <?= $participant['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $participant['active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                        <td><?= number_format((int)$participant['total_vote']) ?></td>
                        <td><div class="d-flex flex-wrap gap-1"><a class="btn btn-sm btn-outline-gold" href="admin.php?view=participants&edit=<?= (int)$participant['id'] ?>">Edit</a>
                            <form method="post" action="admin.php?view=participants" data-loading data-confirm="<?= $participant['active'] ? 'Hapus peserta ini? Jika ada histori, data akan dinonaktifkan agar riwayat voting tetap aman.' : 'Hapus peserta ini secara permanen jika tidak memiliki histori?' ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="view" value="participants"><input type="hidden" name="id" value="<?= (int)$participant['id'] ?>"><input type="hidden" name="action" value="delete_participant"><button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form>
                            <form method="post" action="admin.php?view=participants" data-loading><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="view" value="participants"><input type="hidden" name="id" value="<?= (int)$participant['id'] ?>"><input type="hidden" name="action" value="toggle_participant"><button class="btn btn-sm btn-outline-secondary" type="submit"><?= $participant['active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
                        </div></td>
                    </tr><?php endforeach; ?>
                    <?php if (!$participantRows): ?><tr><td colspan="6" class="text-center text-secondary py-4">Tidak ada peserta yang cocok dengan filter.</td></tr><?php endif; ?>
                </tbody></table></div>
                <?php if ($pageCount > 1): ?><nav aria-label="Halaman daftar peserta"><ul class="pagination justify-content-end mb-0"><?php for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++): $query = $_GET; $query['view'] = 'participants'; $query['page'] = $pageNumber; ?><li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>"><a class="page-link" href="admin.php?<?= e(http_build_query($query)) ?>"><?= $pageNumber ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($view === 'users'): ?>
            <?php $users = $pdo->query('SELECT id, name, email, phone, role, created_at FROM users ORDER BY created_at DESC LIMIT 200')->fetchAll(); ?>
            <div class="eyebrow">Akun terdaftar</div><h1 class="section-title mb-4">Kelola Pengguna</h1>
            <div class="card p-3"><div class="table-responsive"><table class="table"><thead><tr><th>Nama</th><th>Email</th><th>Nomor HP</th><th>Role</th><th>Terdaftar</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><?= e($user['name']) ?></td><td><?= e($user['email']) ?></td><td><?= e($user['phone'] ?? '-') ?></td><td><span class="badge <?= $user['role'] === 'admin' ? 'badge-gold' : 'bg-light text-secondary' ?>"><?= e(ucfirst($user['role'])) ?></span></td><td><?= e($user['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>

        <?php elseif ($view === 'payments'): ?>
            <?php $payments = $pdo->query('SELECT pay.*, u.name AS user_name, p.name AS participant_name FROM payments pay JOIN users u ON u.id = pay.user_id LEFT JOIN participants p ON p.id = pay.participant_id ORDER BY pay.created_at DESC LIMIT 200')->fetchAll(); ?>
            <div class="eyebrow">Transaksi demo</div><h1 class="section-title mb-4">Data Pembayaran</h1>
            <div class="card p-3"><div class="table-responsive"><table class="table"><thead><tr><th>Invoice</th><th>Pengguna</th><th>Peserta</th><th>Vote</th><th>Jumlah</th><th>Status</th><th>Tanggal</th></tr></thead><tbody><?php foreach ($payments as $payment): ?><tr><td><?= e($payment['invoice']) ?></td><td><?= e($payment['user_name']) ?></td><td><?= e($payment['participant_name'] ?: 'Data lama') ?></td><td><?= (int)$payment['vote_qty'] ?></td><td><?= rupiah($payment['amount']) ?></td><td><span class="badge <?= $payment['status'] === 'paid' ? 'text-bg-success' : ($payment['status'] === 'pending' ? 'text-bg-warning' : 'text-bg-secondary') ?>"><?= e(ucfirst($payment['status'])) ?></span></td><td><?= e($payment['created_at']) ?></td></tr><?php endforeach; ?><?php if (!$payments): ?><tr><td colspan="7" class="text-center text-secondary">Belum ada transaksi.</td></tr><?php endif; ?></tbody></table></div></div>

        <?php elseif ($view === 'votes'): ?>
            <?php $votes = $pdo->query('SELECT v.*, p.name AS participant_name, p.category, u.name AS user_name, pay.invoice FROM votes v JOIN participants p ON p.id = v.participant_id JOIN payments pay ON pay.id = v.payment_id JOIN users u ON u.id = pay.user_id ORDER BY v.created_at DESC LIMIT 200')->fetchAll(); ?>
            <div class="eyebrow">Vote yang sudah berhasil dibayar</div><h1 class="section-title mb-4">Data Voting</h1>
            <div class="card p-3"><div class="table-responsive"><table class="table"><thead><tr><th>Peserta</th><th>Kategori</th><th>Pemilih</th><th>Vote</th><th>Invoice</th><th>Tanggal</th></tr></thead><tbody><?php foreach ($votes as $vote): ?><tr><td><?= e($vote['participant_name']) ?></td><td><?= e($vote['category']) ?></td><td><?= e($vote['user_name']) ?></td><td><?= number_format((int)$vote['quantity']) ?></td><td><?= e($vote['invoice']) ?></td><td><?= e($vote['created_at']) ?></td></tr><?php endforeach; ?><?php if (!$votes): ?><tr><td colspan="6" class="text-center text-secondary">Belum ada vote berhasil.</td></tr><?php endif; ?></tbody></table></div></div>

        <?php elseif ($view === 'ranking'): ?>
            <?php $ranking = $pdo->query('SELECT p.*, COALESCE(SUM(v.quantity), 0) AS total_vote FROM participants p LEFT JOIN votes v ON v.participant_id = p.id WHERE p.active = 1 GROUP BY p.id ORDER BY total_vote DESC, p.number')->fetchAll(); ?>
            <div class="eyebrow">Hasil sementara</div><h1 class="section-title mb-4">Peringkat Peserta</h1>
            <div class="card p-3"><div class="table-responsive"><table class="table"><thead><tr><th>Rank</th><th>Peserta</th><th>Kategori</th><th>Vote</th></tr></thead><tbody><?php foreach ($ranking as $index => $participant): ?><tr><td>#<?= $index + 1 ?></td><td><div class="d-flex align-items-center gap-2"><?= participant_photo($participant['photo'], $participant['name'], 'avatar-thumb') ?><?= e($participant['name']) ?></div></td><td><?= e($participant['category']) ?></td><td class="fw-bold"><?= number_format((int)$participant['total_vote']) ?></td></tr><?php endforeach; ?><?php if (!$ranking): ?><tr><td colspan="4" class="text-center text-secondary">Belum ada peserta aktif.</td></tr><?php endif; ?></tbody></table></div></div>

        <?php elseif ($view === 'reports'): ?>
            <?php $paidIncome = (int)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid'")->fetchColumn(); $reportByCategory = $pdo->query("SELECT p.category, COALESCE(SUM(v.quantity), 0) AS total_vote FROM participants p LEFT JOIN votes v ON v.participant_id = p.id GROUP BY p.category")->fetchAll(); ?>
            <div class="eyebrow">Ringkasan sistem</div><h1 class="section-title mb-4">Laporan</h1>
            <div class="row g-3 mb-4"><div class="col-md-4"><div class="stat"><div class="stat-label">Total vote berhasil</div><div class="stat-value"><?= number_format($voteCount) ?></div></div></div><div class="col-md-4"><div class="stat"><div class="stat-label">Transaksi berhasil</div><div class="stat-value"><?= number_format($paidCount) ?></div></div></div><div class="col-md-4"><div class="stat"><div class="stat-label">Nilai pembayaran demo</div><div class="stat-value fs-3"><?= rupiah($paidIncome) ?></div></div></div></div>
            <div class="card p-3"><h2 class="h5 section-title p-2">Vote menurut kategori</h2><div class="table-responsive"><table class="table"><thead><tr><th>Kategori</th><th>Total vote</th></tr></thead><tbody><?php foreach ($reportByCategory as $report): ?><tr><td><?= e($report['category']) ?></td><td><?= number_format((int)$report['total_vote']) ?></td></tr><?php endforeach; ?><?php if (!$reportByCategory): ?><tr><td colspan="2" class="text-center text-secondary">Belum ada data.</td></tr><?php endif; ?></tbody></table></div></div>

        <?php else: ?>
            <div class="eyebrow">Informasi aplikasi</div><h1 class="section-title mb-4">Pengaturan</h1>
            <div class="card p-4"><h2 class="h5 section-title">Konfigurasi & keamanan</h2><p class="text-secondary">Koneksi database dan kredensial MySQL dikelola di <code>config.php</code>. Jangan membagikan konfigurasi tersebut. Folder foto peserta: <code>uploads/participants/</code>; izinkan Apache menulis ke folder ini.</p><p class="mb-0">Mode pembayaran: simulasi DEMO. Tidak ada pembayaran uang sungguhan yang diproses.</p></div>
        <?php endif; ?>
    </section>
</div>
<?php footer_html(); ?>
