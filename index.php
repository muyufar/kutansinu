<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

// Cek status login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Header
include 'templates/header.php';

// Content
?>
<div class="container mt-4">
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-success">
                <div class="card-body text-center">
                    <i class="fas fa-money-bill-wave text-success mb-3" style="font-size: 2em;"></i>
                    <h5 class="card-title">Total Pemasukan</h5>
                    <h3 class="text-success" id="total-pemasukan">Rp 0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-danger">
                <div class="card-body text-center">
                    <i class="fas fa-hand-holding-usd text-danger mb-3" style="font-size: 2em;"></i>
                    <h5 class="card-title">Total Pengeluaran</h5>
                    <h3 class="text-danger" id="total-pengeluaran">Rp 0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-primary">
                <div class="card-body text-center">
                    <i class="fas fa-wallet text-primary mb-3" style="font-size: 2em;"></i>
                    <h5 class="card-title">Saldo</h5>
                    <h3 class="text-primary" id="saldo">Rp 0</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Grafik Keuangan</h5>
                    <canvas id="financeChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Distribusi Pengeluaran</h5>
                    <canvas id="expenseChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Menu Utama</h5>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="transaksi/index.php" class="btn btn-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="fas fa-exchange-alt"></i> Transaksi
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="akun/index.php" class="btn btn-secondary btn-lg w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="fas fa-list"></i> Daftar Akun
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="laporan/neraca.php" class="btn btn-info btn-lg w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="fas fa-balance-scale"></i> Neraca
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="laporan/laba-rugi.php" class="btn btn-success btn-lg w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="fas fa-chart-line"></i> Laba Rugi
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Fungsi untuk memuat data ringkasan transaksi
function loadTransactionSummary() {
    fetch('api/get_summary.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('total-pemasukan').textContent = data.total_pemasukan;
            document.getElementById('total-pengeluaran').textContent = data.total_pengeluaran;
            document.getElementById('saldo').textContent = data.saldo;

            // Update grafik keuangan
            const ctx = document.getElementById('financeChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Pemasukan', 'Pengeluaran', 'Saldo'],
                    datasets: [{
                        label: 'Ringkasan Keuangan',
                        data: [
                            parseInt(data.total_pemasukan.replace(/[^0-9-]/g, '')),
                            parseInt(data.total_pengeluaran.replace(/[^0-9-]/g, '')),
                            parseInt(data.saldo.replace(/[^0-9-]/g, ''))
                        ],
                        backgroundColor: [
                            'rgba(40, 167, 69, 0.5)',
                            'rgba(220, 53, 69, 0.5)',
                            'rgba(0, 123, 255, 0.5)'
                        ],
                        borderColor: [
                            'rgb(40, 167, 69)',
                            'rgb(220, 53, 69)',
                            'rgb(0, 123, 255)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                                }
                            }
                        }
                    }
                }
            });

            // Grafik distribusi pengeluaran (dummy data untuk contoh)
            const ctxPie = document.getElementById('expenseChart').getContext('2d');
            new Chart(ctxPie, {
                type: 'doughnut',
                data: {
                    labels: ['Operasional', 'Gaji', 'Peralatan', 'Lainnya'],
                    datasets: [{
                        data: [35, 25, 20, 20],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        })
        .catch(error => console.error('Error:', error));
}

// Muat data saat halaman dimuat
document.addEventListener('DOMContentLoaded', loadTransactionSummary);
</script>

<?php
// Footer
include 'templates/footer.php';
?>