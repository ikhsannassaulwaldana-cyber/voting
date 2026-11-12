<?php
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $https,
    'samesite' => 'Lax',
]);
session_start();

$host = 'localhost';
$db = 'kakang_senduk';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Pastikan MySQL aktif dan konfigurasi database sudah benar.');
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function logged_in()
{
    return isset($_SESSION['user']['id'], $_SESSION['user']['role']);
}

function require_login()
{
    if (!logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_admin()
{
    require_login();
    if ($_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        exit('Akses khusus admin.');
    }
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_is_valid()
{
    $submitted = $_POST['csrf_token'] ?? '';
    return is_string($submitted)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

function rupiah($amount)
{
    return 'Rp' . number_format((int)$amount, 0, ',', '.');
}

function participant_photo_url($photo)
{
    $photo = (string)$photo;
    if ($photo === '' || basename($photo) !== $photo || !preg_match('/\A[a-f0-9]{32}\.(jpg|jpeg|png|webp)\z/i', $photo)) {
        return '';
    }
    return 'uploads/participants/' . rawurlencode($photo);
}

function participant_photo($photo, $name, $class = '')
{
    $url = participant_photo_url($photo);
    if ($url !== '') {
        return '<img class="' . e($class) . '" src="' . e($url) . '" alt="Foto ' . e($name) . '" loading="lazy">';
    }
    $initial = function_exists('mb_substr') ? mb_substr((string)$name, 0, 1, 'UTF-8') : substr((string)$name, 0, 1);
    return '<div class="' . e(trim('photo-placeholder ' . $class)) . '" role="img" aria-label="Foto belum tersedia">' . e(strtoupper($initial ?: 'K')) . '</div>';
}
