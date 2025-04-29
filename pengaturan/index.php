<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Pengaturan</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="profil.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-user me-2"></i> Profil
                    </a>
                    <a href="perusahaan.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-building me-2"></i> Perusahaan
                    </a>
                    <a href="pengaturan_utama.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog me-2"></i> Pengaturan Utama
                    </a>
                    <a href="karyawan.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i> Karyawan
                    </a>
                    <a href="reset_data.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-trash-alt me-2"></i> Reset Data
                    </a>
                    <a href="backup.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-download me-2"></i> Backup Data
                    </a>
                    <a href="../logout.php" class="list-group-item list-group-item-action text-danger">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-9">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Selamat Datang di Pengaturan</h5>
                </div>
                <div class="card-body">
                    <p>Silahkan pilih menu pengaturan di samping untuk mengelola:</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-user"></i> Profil</h5>
                                    <p class="card-text">Kelola informasi profil pengguna Anda.</p>
                                    <a href="profil.php" class="btn btn-sm btn-primary">Buka</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-building"></i> Perusahaan</h5>
                                    <p class="card-text">Kelola data perusahaan Anda.</p>
                                    <a href="perusahaan.php" class="btn btn-sm btn-primary">Buka</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-cog"></i> Pengaturan Utama</h5>
                                    <p class="card-text">Konfigurasi pengaturan utama aplikasi.</p>
                                    <a href="pengaturan_utama.php" class="btn btn-sm btn-primary">Buka</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-users"></i> Karyawan</h5>
                                    <p class="card-text">Kelola data karyawan perusahaan.</p>
                                    <a href="karyawan.php" class="btn btn-sm btn-primary">Buka</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-trash-alt"></i> Reset Data</h5>
                                    <p class="card-text">Reset data transaksi dan kontak.</p>
                                    <a href="reset_data.php" class="btn btn-sm btn-danger">Buka</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-download"></i> Backup Data</h5>
                                    <p class="card-text">Backup dan restore data aplikasi.</p>
                                    <a href="backup.php" class="btn btn-sm btn-primary">Buka</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer
include '../templates/footer.php';
?>