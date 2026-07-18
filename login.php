<?php

session_start();

require_once 'config/database.php';

require_once 'config/functions.php';



// Tangkap parameter redirect jika ada

$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';



// Jika sudah login, redirect ke dashboard

if (isset($_SESSION['user_id'])) {

    header("Location: index.php");

    exit();

}



$error = '';



// Proses login

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = validateInput($_POST['username']);

    $password = $_POST['password'];

    $remember = isset($_POST['remember']);



    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");

    $stmt->execute([$username]);

    $user = $stmt->fetch();



    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['user_id'] = $user['id'];

        $_SESSION['username'] = $user['username'];

        if ($remember) {

            setcookie('username', $username, time() + (86400 * 30), "/");

        }

        if (!empty($redirect)) {

            header("Location: $redirect");

        } else {

            header("Location: index.php");

        }

        logAudit($db, $user['id'], 'login', 'User berhasil login');

        exit();

    } else {

        $error = 'Username atau password salah';

    }

}

?>



<!DOCTYPE html>

<html lang="id" data-theme="light">



<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Sistem Pelaporan Keuangan</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

    <link href="/assets/css/sikeu-theme.css" rel="stylesheet">

    <link href="/assets/css/dark-mode.css" rel="stylesheet">

</head>



<body class="sikeu-login">

    <div class="main-container">

        <button type="button" class="login-theme-toggle" id="loginThemeToggle" aria-label="Toggle theme">

            <i class="fas fa-moon"></i>

            <span>Mode Gelap</span>

        </button>



        <div class="login-card-shell">

            <div class="login-section">

                <div class="login-logo-frame" style="--logo-src: url('assets/img/logo-nu.jpg');">

                    <img src="assets/img/logo-nu.jpg" alt="Logo NU" class="nu-brand-logo">

                </div>

                <div class="login-title">Assalamualaikum,</div>

                <div class="login-subtitle">Silahkan login untuk melanjutkan ke sistem pelaporan keuangan PCNU KAB Magelang</div>

                <?php if ($error): ?>

                    <div class="alert alert-danger"><?php echo $error; ?></div>

                <?php endif; ?>

                <form method="POST" action="">

                    <div class="mb-3">

                        <label for="username" class="form-label">Email or Username</label>

                        <input type="text" class="form-control" id="username" name="username" required value="<?php echo isset($_COOKIE['username']) ? htmlspecialchars($_COOKIE['username']) : ''; ?>">

                    </div>

                    <div class="mb-3">

                        <label for="password" class="form-label">Password</label>

                        <input type="password" class="form-control" id="password" name="password" required>

                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div class="form-check">

                            <input class="form-check-input" type="checkbox" id="remember" name="remember" <?php echo isset($_COOKIE['username']) ? 'checked' : ''; ?>>

                            <label class="form-check-label" for="remember">Remember me</label>

                        </div>

                        <a href="#" class="forgot-link">Lupa Password?</a>

                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3">Masuk</button>

                </form>

            </div>

            <div class="illustration-section" role="img" aria-label="Ilustrasi pelaporan keuangan PCNU Kab Magelang">

                <div class="illustration-glow illustration-glow-1"></div>

                <div class="illustration-glow illustration-glow-2"></div>

                <div class="illustration-inner">

                    <span class="illustration-badge"><i class="fas fa-mosque"></i> PCNU Kab. Magelang</span>

                    <h2 class="illustration-heading">Keuangan Amanah &amp; Transparan</h2>

                    <p class="illustration-desc">Sistem pelaporan keuangan organisasi — dari transaksi harian hingga laporan akuntabilitas yang terpercaya.</p>

                    <ul class="illustration-features">

                        <li><i class="fas fa-book-open"></i> Buku Kas &amp; Transaksi</li>

                        <li><i class="fas fa-chart-pie"></i> Laporan Keuangan</li>

                        <li><i class="fas fa-hand-holding-heart"></i> Akuntabilitas Amanah</li>

                    </ul>

                    <div class="illustration-visual">

                        <img src="assets/img/login-pcnu-keuangan.png" alt="Ilustrasi keuangan agama PCNU" class="login-illustration-img">

                    </div>

                </div>

            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>

        (function() {

            const root = document.documentElement;

            const toggle = document.getElementById('loginThemeToggle');

            const saved = localStorage.getItem('theme') || 'light';

            root.setAttribute('data-theme', saved);

            updateToggleLabel(saved);



            toggle.addEventListener('click', function() {

                const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';

                root.setAttribute('data-theme', next);

                localStorage.setItem('theme', next);

                updateToggleLabel(next);

            });



            function updateToggleLabel(theme) {

                const icon = toggle.querySelector('i');

                const label = toggle.querySelector('span');

                if (theme === 'dark') {

                    icon.className = 'fas fa-sun';

                    label.textContent = 'Mode Terang';

                } else {

                    icon.className = 'fas fa-moon';

                    label.textContent = 'Mode Gelap';

                }

            }

        })();

    </script>

</body>



</html>


