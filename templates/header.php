<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Pelaporan Keuangan</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- Dark Mode CSS -->
    <link href="/kutansinu/assets/css/dark-mode.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        .navbar-brand {
            font-weight: bold;
        }
        .nav-link {
            color: rgba(255,255,255,.8) !important;
        }
        .nav-link:hover {
            color: rgba(255,255,255,1) !important;
        }
        .theme-switch {
            cursor: pointer;
            padding: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255,255,255,.8);
        }
        .theme-switch:hover {
            color: rgba(255,255,255,1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/kutansinu/index.php">SiKeu</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/kutansinu/transaksi/index.php">Transaksi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/kutansinu/akun/index.php">Daftar Akun</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            Laporan
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/kutansinu/laporan/transaksi.php">Transaksi</a></li>
                            <li><a class="dropdown-item" href="/kutansinu/laporan/neraca.php">Neraca</a></li>
                            <li><a class="dropdown-item" href="/kutansinu/laporan/laba-rugi.php">Laba Rugi</a></li>
                            <li><a class="dropdown-item" href="/kutansinu/laporan/arus-kas.php">Arus Kas</a></li>
                        </ul>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <div class="theme-switch" onclick="toggleTheme()">
                            <i class="fas fa-moon"></i>
                            <span class="d-none d-lg-inline">Mode Gelap</span>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/kutansinu/logout.php">Keluar</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <script>
        // Fungsi untuk mengganti tema
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Update ikon dan teks
            const themeIcon = document.querySelector('.theme-switch i');
            const themeText = document.querySelector('.theme-switch span');
            
            if (newTheme === 'dark') {
                themeIcon.className = 'fas fa-sun';
                themeText.textContent = 'Mode Terang';
            } else {
                themeIcon.className = 'fas fa-moon';
                themeText.textContent = 'Mode Gelap';
            }
        }

        // Set tema berdasarkan preferensi yang tersimpan
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            
            // Update ikon dan teks sesuai tema yang tersimpan
            if (savedTheme === 'dark') {
                document.querySelector('.theme-switch i').className = 'fas fa-sun';
                document.querySelector('.theme-switch span').textContent = 'Mode Terang';
            }
        });
    </script>