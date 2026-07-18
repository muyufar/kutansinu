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
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Pelaporan Keuangan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(90deg, #f8f9fa 50%, rgb(143, 245, 143) 100%);
            min-height: 100vh;
        }

        .main-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-section {
            background: #fff;
            border-radius: 20px 0 0 20px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.08);
            padding: 60px 40px;
            width: 400px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .illustration-section {
            background: linear-gradient(135deg, rgb(143, 245, 148) 0%, #f8f9fa 100%);
            border-radius: 0 20px 20px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 450px;
            min-height: 500px;
            position: relative;
            overflow: hidden;
        }

        .illustration-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, #a18ff5 60%, #fff0 100%);
            opacity: 0.7;
            z-index: 1;
        }

        .illustration-section img {
            max-width: 100%;
            max-height: 100%;
            width: 100%;
            height: 100%;
            object-fit: contain;
            position: relative;
            z-index: 2;
            display: block;
            margin: 0 auto;
        }

        .nu-brand-logo {
            display: block;
            margin: 0 auto 10px auto;
            height: 48px;
            width: 80px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.13);
            background: #fff;
            object-fit: cover;
        }

        .brand {
            color: #22c55e !important;
            text-align: center;
        }

        .login-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-subtitle {
            color: #888;
            margin-bottom: 30px;
        }

        .form-check-label {
            font-size: 0.95rem;
        }

        .forgot-link,
        .signup-link {
            color: #219c2c;
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-link:hover,
        .signup-link:hover {
            text-decoration: underline;
        }

        .btn-primary {
            background: rgb(38, 168, 48);
            border: none;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: #219c2c;
        }

        @media (max-width: 900px) {
            .main-container {
                flex-direction: column;
            }

            .illustration-section,
            .login-section {
                border-radius: 20px 20px 0 0;
                width: 100%;
            }

            .illustration-section img {
                max-width: 350px;
            }
        }

        @media (max-width: 600px) {
            body {
                background: url('assets/img/loginuser.png') center center/cover no-repeat !important;
            }

            .main-container {
                background: none !important;
                flex-direction: column;
            }

            .illustration-section {
                background: none !important;
                min-height: 0;
                height: 0;
                padding: 0;
            }

            .illustration-section img {
                display: none;
            }

            .login-section {
                background: rgba(255, 255, 255, 0.92);
                padding: 40px 10px;
                border-radius: 18px;
                box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
                margin: 30px 8px;
            }
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="login-section">
            <img src="assets/img/logo-nu.jpg" alt="Logo NU" class="nu-brand-logo">
            <div class="login-title">Assalamualaikum,</div>
            <div class="login-subtitle">Silahkan login untuk melanjutkan ke sistem pelaporan keuangan PCNU KAB Magelang</div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Email or Username</label>
                    <input type="text" class="form-control" id="username" name="username" required value="<?php echo isset($_COOKIE['username']) ? $_COOKIE['username'] : ''; ?>">
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
        <div class="illustration-section">
            <img src="assets/img/loginuser.png" alt="Login Illustration">
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>