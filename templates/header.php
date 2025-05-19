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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar {
            background-color: rgb(33, 146, 42) !important;
        }

        .navbar-brand img {
            height: 32px;
            width: 32px;
            margin-right: 10px;
            border-radius: 50%;
            background: #fff;
            object-fit: cover;
            border: 2px solid #e0e0e0;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.07);
        }

        .nav-link {
            color: rgba(255, 255, 255, .8) !important;
        }

        .nav-link:hover {
            color: rgba(255, 255, 255, 1) !important;
        }

        .theme-switch {
            cursor: pointer;
            padding: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, .8);
        }

        .theme-switch:hover {
            color: rgba(255, 255, 255, 1);
        }
    </style>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Tambahkan di bagian head -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
</head>

<body>
    <?php
    // Cek role user untuk menampilkan menu yang sesuai
    $is_viewer = false;
    $user_role = '';
    $is_nugrosir = false; // Inisialisasi variabel untuk pengecekan Nugrosir

    if (isset($_SESSION['user_id']) && isset($db)) {
        $user_id = $_SESSION['user_id'];

        // Ambil perusahaan default user
        $stmt = $db->prepare("SELECT default_company FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        $default_company_id = $user_data['default_company'];

        // Cek role user
        if ($default_company_id) {
            $stmt = $db->prepare("SELECT role FROM user_perusahaan WHERE user_id = ? AND perusahaan_id = ?");
            $stmt->execute([$user_id, $default_company_id]);
            $user_role = $stmt->fetchColumn();
            $is_viewer = ($user_role === 'viewer');

            // Jika user adalah viewer dan mencoba mengakses halaman selain laporan, redirect ke halaman laporan
            if ($is_viewer) {
                $current_path = $_SERVER['REQUEST_URI'];
                if (strpos($current_path, '/laporan/') === false && $current_path !== '/kutansinu/index.php' && $current_path !== '/kutansinu/') {
                    header('Location: /kutansinu/laporan/transaksi.php');
                    exit();
                }
            }
        }

        // Cek apakah user terhubung dengan perusahaan Nugrosir
        $stmt_nugrosir = $db->prepare("SELECT 1 FROM user_perusahaan up
                            JOIN perusahaan p ON up.perusahaan_id = p.id
                            WHERE up.user_id = ? AND UPPER(p.nama) = 'NUGO' AND up.status = 'active'");
        $stmt_nugrosir->execute([$user_id]);
        $is_nugrosir = $stmt_nugrosir->fetch() ? true : false;
    }
    ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/kutansinu/index.php">
                <?php
                $logo_path = '/kutansinu/assets/img/logo.jpg'; // default
                if (isset($_SESSION['user_id']) && isset($db)) {
                    $user_id = $_SESSION['user_id'];
                    // Ambil default_company user
                    $stmt = $db->prepare("SELECT default_company FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user_data = $stmt->fetch();
                    $default_company_id = $user_data['default_company'] ?? null;
                    if ($default_company_id) {
                        $stmt = $db->prepare("SELECT logo, nama FROM perusahaan WHERE id = ?");
                        $stmt->execute([$default_company_id]);
                        $perusahaan = $stmt->fetch();
                        if ($perusahaan && !empty($perusahaan['logo'])) {
                            $logo_path = '/kutansinu/' . $perusahaan['logo'];
                        }
                    }
                }
                ?>
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo" />
                <?php
                // Ambil nama perusahaan yang sedang aktif untuk user yang login
                if (isset($perusahaan) && !empty($perusahaan['nama'])) {
                    echo htmlspecialchars($perusahaan['nama']);
                } else {
                    echo "SiKeu";
                }
                ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php
                    // Tampilkan menu Transaksi dan Daftar Akun hanya jika bukan viewer
                    if (!$is_viewer) :
                    ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/kutansinu/transaksi/index.php"><i class="fas fa-exchange-alt me-1"></i> Transaksi</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/kutansinu/akun/index.php"><i class="fas fa-list-alt me-1"></i> Daftar Akun</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-chart-bar me-1"></i> Laporan
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="/kutansinu/laporan/transaksi.php"><i class="fas fa-exchange-alt me-1"></i> Transaksi</a></li>
                            <li><a class="dropdown-item" href="/kutansinu/laporan/neraca.php"><i class="fas fa-balance-scale me-1"></i> Neraca</a></li>
                            <li><a class="dropdown-item" href="/kutansinu/laporan/laba-rugi.php"><i class="fas fa-chart-line me-1"></i> Laba Rugi</a></li>
                            <li><a class="dropdown-item" href="/kutansinu/laporan/arus-kas.php"><i class="fas fa-money-bill-wave me-1"></i> Arus Kas</a></li>
                        </ul>
                    </li>
                    <?php if ($is_nugrosir) : ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/kutansinu/bus/index.php"><i class="fas fa-bus me-1"></i> Pemesanan Bus</a>
                        </li>
                    <?php endif; ?>
                    <?php if (!$is_viewer) : ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/kutansinu/pengaturan/index.php"><i class="fas fa-cog me-1"></i> Pengaturan</a>
                        </li>
                    <?php endif; ?>

                    <?php if (!isset($_SESSION['user_id'])) : ?>
                        <!-- Menu Jadwal Bus Umum (hanya ditampilkan u  ntuk pengunjung yang belum login) -->
                        <li class="nav-item">
                            <a class="nav-link" href="/kutansinu/jadwal_bus_umum.php"><i class="fas fa-calendar-alt me-1"></i> Jadwal Bus</a>
                        </li>
                    <?php endif; ?>

                </ul>
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])) :
                        // Ambil username pengguna yang login
                        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $user = $stmt->fetch();
                        $username = $user['username'] ?? 'Pengguna';
                    ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/kutansinu/pengaturan/profil.php">
                                <i class="fas fa-user me-1"></i>
                                <span><?php echo htmlspecialchars($username); ?></span>
                            </a>
                        </li>


                        <li class="nav-item">
                            <div class="theme-switch" onclick="toggleTheme()">
                                <i class="fas fa-moon"></i>
                                <span class="d-none d-lg-inline">Mode Gelap</span>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/kutansinu/logout.php">Keluar</a>
                        </li>
                    <?php endif; ?>
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