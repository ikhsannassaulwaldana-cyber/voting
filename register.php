<?php
require 'config.php';
require 'partials.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['password_confirmation'] ?? '';
    if (!csrf_is_valid()) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.';
    } elseif ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        $error = 'Periksa kembali nama lengkap dan alamat email.';
    } elseif ($phone === '' || !preg_match('/\A[0-9+(). -]{8,30}\z/', $phone)) {
        $error = 'Masukkan nomor HP yang valid (8–30 karakter).';
    } elseif (strlen($password) < 8) {
        $error = 'Password harus terdiri dari minimal 8 karakter.';
    } elseif ($password !== $confirmation) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'voter')");
            $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'Email tersebut sudah terdaftar.';
            } else {
                error_log('Create voter account failed: ' . $exception->getMessage());
                $error = 'Pendaftaran belum berhasil. Silakan coba kembali.';
            }
        }
    }
}
header_html('Daftar - Kakang Senduk');
?>
<section class="auth-layout row g-0">
    <div class="col-lg-5 auth-brand d-flex flex-column justify-content-between">
        <div><div class="eyebrow mb-3">Bergabung bersama kami</div><h1 class="display-5">Dukung talenta<br>kebanggaan kota.</h1><p class="text-white-50 mt-3">Buat akun untuk memilih finalis dan memantau riwayat dukunganmu.</p></div>
        <div class="small text-white-50 mt-5">Kakang Senduk Ponorogo</div>
    </div>
    <div class="col-lg-7 auth-form">
        <div class="eyebrow">Akun pemilih</div><h2 class="section-title mt-2 mb-4">Buat akun baru</h2>
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" data-loading>
            <?= csrf_field() ?>
            <label class="form-label fw-semibold" for="name">Nama lengkap</label>
            <input id="name" class="form-control mb-3" name="name" autocomplete="name" maxlength="100" value="<?= e($_POST['name'] ?? '') ?>" required>
            <label class="form-label fw-semibold" for="email">Email</label>
            <input id="email" class="form-control mb-3" type="email" name="email" autocomplete="email" maxlength="120" value="<?= e($_POST['email'] ?? '') ?>" required>
            <label class="form-label fw-semibold" for="phone">Nomor HP</label>
            <input id="phone" class="form-control mb-3" type="tel" name="phone" autocomplete="tel" maxlength="30" value="<?= e($_POST['phone'] ?? '') ?>" required>
            <label class="form-label fw-semibold" for="password">Password (minimal 8 karakter)</label>
            <div class="input-group mb-3"><input id="password" class="form-control" type="password" name="password" autocomplete="new-password" minlength="8" required><button class="btn btn-outline-secondary" type="button" data-password-toggle="password">Tampilkan</button></div>
            <label class="form-label fw-semibold" for="password-confirmation">Konfirmasi password</label>
            <input id="password-confirmation" class="form-control mb-4" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
            <button type="submit" class="btn btn-gold btn-lg w-100">Daftar</button>
        </form>
        <p class="mt-4 mb-0 text-secondary">Sudah punya akun? <a href="login.php">Masuk</a></p>
    </div>
</section>
<?php footer_html(); ?>
