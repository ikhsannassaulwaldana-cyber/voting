<?php
require 'config.php';
require 'partials.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!csrf_is_valid()) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Masukkan email dan password yang valid.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ];
            header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'participants.php'));
            exit;
        }
        $error = 'Email atau password salah.';
    }
}
header_html('Masuk - Kakang Senduk');
?>
<section class="auth-layout row g-0">
    <div class="col-lg-5 auth-brand d-flex flex-column justify-content-between">
        <div><div class="eyebrow mb-3">Kakang Senduk Ponorogo</div><h1 class="display-5">Satu dukungan,<br>sejuta inspirasi.</h1><p class="text-white-50 mt-3">Masuk untuk mendukung finalis pilihanmu dan mengikuti perkembangan ajang duta budaya Ponorogo.</p></div>
        <div class="small text-white-50 mt-5">Merayakan budaya. Menginspirasi masa depan.</div>
    </div>
    <div class="col-lg-7 auth-form">
        <div class="eyebrow">Selamat datang kembali</div><h2 class="section-title mt-2 mb-4">Masuk ke akun</h2>
        <?php if (isset($_GET['registered'])): ?><div class="alert alert-success">Pendaftaran berhasil. Silakan masuk.</div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" data-loading>
            <?= csrf_field() ?>
            <label class="form-label fw-semibold" for="email">Email</label>
            <input id="email" class="form-control mb-3" name="email" type="email" autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>" required>
            <label class="form-label fw-semibold" for="password">Password</label>
            <div class="input-group mb-4"><input id="password" class="form-control" name="password" type="password" autocomplete="current-password" required><button class="btn btn-outline-secondary" type="button" data-password-toggle="password">Tampilkan</button></div>
            <button type="submit" class="btn btn-gold btn-lg w-100">Masuk</button>
        </form>
        <p class="mt-4 mb-0 text-secondary">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
    </div>
</section>
<?php footer_html(); ?>
