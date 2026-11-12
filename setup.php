<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Jalankan setup.php melalui PHP CLI setelah impor database.');
}

require 'config.php';
$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($adminCount > 0) {
    exit("Akun admin sudah tersedia. Setup dihentikan.\n");
}

$name = trim(readline('Nama administrator: '));
$email = trim(readline('Email administrator: '));
$password = readline('Password administrator (minimal 12 karakter): ');
$confirmation = readline('Ulangi password: ');
if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120 || strlen($password) < 12 || $password !== $confirmation) {
    fwrite(STDERR, "Data tidak valid atau konfirmasi password tidak cocok.\n");
    exit(1);
}

try {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    echo "Akun admin berhasil dibuat. Hapus atau amankan file setup.php setelah instalasi.\n";
} catch (PDOException $exception) {
    error_log('Create initial admin failed: ' . $exception->getMessage());
    fwrite(STDERR, "Akun admin tidak berhasil dibuat. Periksa apakah email sudah digunakan.\n");
    exit(1);
}
