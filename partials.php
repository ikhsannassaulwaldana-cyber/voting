<?php
function header_html($title = 'Kakang Senduk Ponorogo')
{
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#12243a">
        <title><?= e($title) ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <nav class="navbar navbar-expand-lg navbar-dark nav-main sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><span class="brand-mark">KS</span><span class="brand-name">KAKANG <span>&</span> SENDUK</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Buka navigasi"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="nav">
                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <a class="nav-link" href="index.php">Beranda</a>
                    <a class="nav-link" href="participants.php">Peserta</a>
                    <a class="nav-link" href="ranking.php">Peringkat</a>
                    <?php if (logged_in()): ?>
                        <a class="nav-link" href="history.php">Riwayat Vote</a>
                        <?php if ($_SESSION['user']['role'] === 'admin'): ?><a class="nav-link" href="admin.php">Dashboard</a><?php endif; ?>
                        <form method="post" action="logout.php" class="d-inline">
                            <?= csrf_field() ?>
                            <button class="nav-link btn btn-link text-start" type="submit">Keluar</button>
                        </form>
                    <?php else: ?>
                        <a class="nav-link" href="login.php">Masuk</a>
                        <a class="btn btn-gold btn-sm ms-lg-2" href="register.php">Daftar / Vote</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main class="container page-content">
    <?php
}

function footer_html()
{
    ?>
    </main>
    <footer class="footer mt-5">
        <div class="container py-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <strong>Kakang Senduk Ponorogo</strong>
            <span class="small text-center">Ajang apresiasi budaya dan generasi muda • Pembayaran demo, bukan transaksi uang sungguhan.</span>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelectorAll('[data-preview-input]').forEach(function (input) {
        input.addEventListener('change', function () {
            var target = document.getElementById(input.dataset.previewInput);
            if (target && input.files && input.files[0]) {
                target.src = URL.createObjectURL(input.files[0]);
                target.classList.remove('d-none');
            }
        });
    });
    document.querySelectorAll('form[data-loading]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                event.preventDefault();
                return;
            }
            var button = form.querySelector('button[type="submit"],button:not([type])');
            if (button && !button.disabled) {
                button.disabled = true;
                button.dataset.originalText = button.innerHTML;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Memproses...';
            }
        });
    });
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            button.textContent = input.type === 'password' ? 'Tampilkan' : 'Sembunyikan';
        });
    });
    </script>
    </body>
    </html>
    <?php
}
