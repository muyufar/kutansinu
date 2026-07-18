<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

// Cek status login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Cek role admin
$is_admin = false;
if (isset($_SESSION['user_id']) && isset($db)) {
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT default_company FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    $default_company_id = $user_data['default_company'];
    if (checkUserRole($db, $user_id, $default_company_id, 'admin')) {
        $is_admin = true;
    }
}

// Ringkasan keuangan semua perusahaan untuk admin
$ringkasan_perusahaan = [];
if ($is_admin) {
    $stmt = $db->prepare("SELECT p.id, p.nama FROM perusahaan p JOIN user_perusahaan up ON p.id = up.perusahaan_id WHERE up.user_id = ? AND up.role = 'admin'");
    $stmt->execute([$_SESSION['user_id']]);
    $perusahaan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($perusahaan_list as $perusahaan) {
        $pid = $perusahaan['id'];
        // Total pemasukan
        $stmt_in = $db->prepare("SELECT SUM(jumlah) as total FROM transaksi WHERE jenis = 'pemasukan' AND id_perusahaan = ?");
        $stmt_in->execute([$pid]);
        $pemasukan = $stmt_in->fetch()['total'] ?? 0;
        // Total pengeluaran
        $stmt_out = $db->prepare("SELECT SUM(jumlah) as total FROM transaksi WHERE jenis = 'pengeluaran' AND id_perusahaan = ?");
        $stmt_out->execute([$pid]);
        $pengeluaran = $stmt_out->fetch()['total'] ?? 0;
        // Saldo akhir
        $saldo = $pemasukan - $pengeluaran;
        $ringkasan_perusahaan[] = [
            'nama' => $perusahaan['nama'],
            'pemasukan' => $pemasukan,
            'pengeluaran' => $pengeluaran,
            'saldo' => $saldo
        ];
    }
}

// Ambil tanggal 4 hari ke belakang dan 3 hari ke depan
$labels = [];
$pemasukan = [];
$pengeluaran = [];
$kas_bersih = [];
$today = new DateTime();
$saldo_awal = 0;

// Ambil id_perusahaan dari default_company pengguna
$stmt_company = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt_company->execute([$_SESSION['user_id']]);
$user_data = $stmt_company->fetch();
$id_perusahaan = $user_data['default_company'];

// Hitung saldo awal sebelum 4 hari ke belakang
$start_date = (clone $today)->modify('-4 days')->format('Y-m-d');
$stmt = $db->prepare("SELECT SUM(CASE WHEN jenis = 'pemasukan' THEN jumlah ELSE -jumlah END) as saldo FROM transaksi WHERE tanggal < ? AND id_perusahaan = ?");
$stmt->execute([$start_date, $id_perusahaan]);
$saldo_awal = (int)($stmt->fetch()['saldo'] ?? 0);

$kas_bersih_val = $saldo_awal;
for ($i = -4; $i <= 3; $i++) {
    $date = (clone $today)->modify("$i days")->format('Y-m-d');
    $labels[] = (clone $today)->modify("$i days")->format('d M');
    // Pemasukan
    $stmt = $db->prepare("SELECT SUM(jumlah) as total FROM transaksi WHERE tanggal = ? AND jenis = 'pemasukan' AND id_perusahaan = ?");
    $stmt->execute([$date, $id_perusahaan]);
    $masuk = (int)($stmt->fetch()['total'] ?? 0);
    $pemasukan[] = $masuk;
    // Pengeluaran
    $stmt = $db->prepare("SELECT SUM(jumlah) as total FROM transaksi WHERE tanggal = ? AND jenis = 'pengeluaran' AND id_perusahaan = ?");
    $stmt->execute([$date, $id_perusahaan]);
    $keluar = (int)($stmt->fetch()['total'] ?? 0);
    $pengeluaran[] = -$keluar;
    // Kas Bersih
    $kas_bersih_val += $masuk - $keluar;
    $kas_bersih[] = $kas_bersih_val;
}
$labels_json = json_encode($labels);
$pemasukan_json = json_encode($pemasukan);
$pengeluaran_json = json_encode($pengeluaran);
$kas_bersih_json = json_encode($kas_bersih);

// Query distribusi pengeluaran per akun debit
$stmt = $db->prepare("SELECT ad.nama_akun as kategori, SUM(t.jumlah) as total FROM transaksi t LEFT JOIN akun ad ON t.id_akun_debit = ad.id WHERE t.jenis = 'pengeluaran' AND t.id_perusahaan = ? GROUP BY ad.nama_akun");
$stmt->execute([$id_perusahaan]);
$pengeluaran_kategori = $stmt->fetchAll();
$kategori_labels = [];
$kategori_values = [];
$kategori_colors = ['#6a7cff', '#ffc233', '#4ecb71', '#ff6384', '#36a2eb', '#ffce56'];
$color_map = [];
$total_pengeluaran = 0;
foreach ($pengeluaran_kategori as $i => $row) {
    $kategori_labels[] = $row['kategori'] ?: 'Lainnya';
    $kategori_values[] = (int)$row['total'];
    $color_map[] = $kategori_colors[$i % count($kategori_colors)];
    $total_pengeluaran += (int)$row['total'];
}
$kategori_labels_json = json_encode($kategori_labels);
$kategori_values_json = json_encode($kategori_values);
$color_map_json = json_encode($color_map);
$total_pengeluaran_formatted = number_format($total_pengeluaran, 0, ',', '.');

// Header
include 'templates/header.php';

// Content
?>
<style>
    .summary-card {
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(34, 60, 80, 0.13), 0 1.5px 6px rgba(34, 60, 80, 0.10);
        border: 1px solid rgba(0, 0, 0, 0.06);
        background: rgba(255, 255, 255, 0.92);
        transition: transform 0.15s cubic-bezier(.4, 2, .6, 1), box-shadow 0.15s;
        padding: 18px 0 14px 0;
        text-align: center;
        font-family: 'Inter', 'Poppins', sans-serif;
    }

    .summary-card:hover {
        transform: translateY(-4px) scale(1.045);
        box-shadow: 0 10px 32px rgba(34, 60, 80, 0.18), 0 2px 8px rgba(34, 60, 80, 0.13);
        border-color: #d1d5db;
    }

    .summary-card .icon {
        font-size: 1.5rem;
        margin-bottom: 6px;
        border-radius: 50%;
        padding: 7px;
        background: linear-gradient(135deg, #f3f4f6 60%, #fff 100%);
        display: inline-block;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
    }

    .summary-card .label {
        font-size: 0.92rem;
        color: #7b7b93;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        margin-bottom: 3px;
    }

    .summary-card .value {
        font-size: 1.25rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin-top: 1px;
    }

    .summary-card.income .icon {
        background: linear-gradient(135deg, #e7fbe9 60%, #d1f5e1 100%);
    }

    .summary-card.expense .icon {
        background: linear-gradient(135deg, #fde7e7 60%, #f9dada 100%);
    }

    .summary-card.balance .icon {
        background: linear-gradient(135deg, #e7f0fd 60%, #d1e3fa 100%);
    }

    .summary-card.income .value {
        color: #22c55e;
    }

    .summary-card.expense .value {
        color: #ef4444;
    }

    .summary-card.balance .value {
        color: #2563eb;
    }

    .summary-company-card {
        transition: transform 0.18s cubic-bezier(.4, 2, .6, 1), box-shadow 0.18s;
        border-radius: 18px;
        min-height: 210px;
        box-shadow: 0 2px 12px rgba(99, 102, 241, 0.08), 0 1.5px 6px rgba(34, 60, 80, 0.10);
    }

    .summary-company-card:hover {
        transform: translateY(-6px) scale(1.04);
        box-shadow: 0 8px 32px rgba(99, 102, 241, 0.18), 0 2px 8px rgba(34, 60, 80, 0.13);
        background: linear-gradient(135deg, #e0e7ff 60%, #f8fafc 100%);
    }
</style>
<div class="container mt-4">

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="summary-card income">
                <div class="icon">
                    <!-- Futuristic arrow up for income -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="11" fill="#22c55e" fill-opacity="0.13" />
                        <path d="M12 17V7M12 7l-4 4M12 7l4 4" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div class="label">Pemasukan</div>
                <div class="value" id="total-pemasukan">Rp 0</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card expense">
                <div class="icon">
                    <!-- Futuristic arrow down for expense -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="11" fill="#ef4444" fill-opacity="0.13" />
                        <path d="M12 7v10M12 17l-4-4M12 17l4-4" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div class="label">Pengeluaran</div>
                <div class="value" id="total-pengeluaran">Rp 0</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card balance">
                <div class="icon">
                    <!-- Futuristic coin for balance -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="11" fill="#2563eb" fill-opacity="0.13" />
                        <circle cx="12" cy="12" r="6" fill="#fff" stroke="#2563eb" stroke-width="2" />
                        <path d="M9.5 12h5M12 9.5v5" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </div>
                <div class="label">Saldo</div>
                <div class="value" id="saldo">Rp 0</div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="card-title mb-0">Arus Kas</h5>
                        <span class="fw-bold text-dark" style="font-size:1.2em">Rp <?= number_format(end($kas_bersih), 0, ',', '.') ?></span>
                    </div>
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
    <?php if ($is_admin && !empty($ringkasan_perusahaan)): ?>
        <div class="row g-3 mb-4 justify-content-center">
            <?php foreach ($ringkasan_perusahaan as $r): ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex align-items-stretch">
                    <div class="card shadow-sm border-0 h-100 summary-company-card w-100" style="background: linear-gradient(135deg, #f8fafc 70%, #e0e7ff 100%);">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center">
                            <div class="rounded-circle mb-2" style="background: #6366f1; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-building text-white" style="font-size: 1.5rem;"></i>
                            </div>
                            <h6 class="fw-bold text-center mb-1" style="color:#3730a3"><?= htmlspecialchars($r['nama']) ?></h6>
                            <div class="w-100 mt-2">
                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted">Pemasukan</span>
                                    <span class="fw-bold" style="color:#2563eb">Rp <?= number_format($r['pemasukan'], 0, ',', '.') ?></span>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted">Pengeluaran</span>
                                    <span class="fw-bold" style="color:#f59e42">Rp <?= number_format($r['pengeluaran'], 0, ',', '.') ?></span>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted">Saldo</span>
                                    <span class="fw-bold" style="color:<?= $r['saldo'] < 0 ? '#ef4444' : '#22c55e' ?>">
                                        Rp <?= number_format($r['saldo'], 0, ',', '.') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <!-- <div class="row mt-4">
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
</div> -->

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

    <script>
        const labels = <?php echo $labels_json; ?>;
        const pemasukan = <?php echo $pemasukan_json; ?>;
        const pengeluaran = <?php echo $pengeluaran_json; ?>;
        const kasBersih = <?php echo $kas_bersih_json; ?>;
        const ctx = document.getElementById('financeChart').getContext('2d');
        new Chart(ctx, {
            data: {
                labels: labels,
                datasets: [{
                        type: 'bar',
                        label: 'Kas Masuk',
                        data: pemasukan,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderRadius: 6,
                        order: 2
                    },
                    {
                        type: 'bar',
                        label: 'Kas Keluar',
                        data: pengeluaran,
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                        borderRadius: 6,
                        order: 2
                    },
                    {
                        type: 'line',
                        label: 'Kas Bersih',
                        data: kasBersih,
                        borderColor: '#f6c23e',
                        backgroundColor: 'rgba(246, 194, 62, 0.2)',
                        borderWidth: 2,
                        tension: 0.4,
                        pointRadius: 3,
                        fill: false,
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        stacked: false,
                        beginAtZero: true,
                        grid: {
                            color: '#f3f3f3'
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });

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
                        data: {
                            labels: labels,
                            datasets: [{
                                    type: 'bar',
                                    label: 'Kas Masuk',
                                    data: pemasukan,
                                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                    borderRadius: 6,
                                    order: 2
                                },
                                {
                                    type: 'bar',
                                    label: 'Kas Keluar',
                                    data: pengeluaran,
                                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                                    borderRadius: 6,
                                    order: 2
                                },
                                {
                                    type: 'line',
                                    label: 'Kas Bersih',
                                    data: kas_bersih,
                                    borderColor: '#f6c23e',
                                    backgroundColor: 'rgba(246, 194, 62, 0.2)',
                                    borderWidth: 2,
                                    tension: 0.4,
                                    pointRadius: 3,
                                    fill: false,
                                    order: 1
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom'
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false
                                }
                            },
                            scales: {
                                x: {
                                    stacked: true,
                                    grid: {
                                        display: false
                                    }
                                },
                                y: {
                                    stacked: false,
                                    beginAtZero: true,
                                    grid: {
                                        color: '#f3f3f3'
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return value.toLocaleString('id-ID');
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

        const pengeluaranLabels = <?php echo $kategori_labels_json; ?>;
        const pengeluaranData = <?php echo $kategori_values_json; ?>;
        const pengeluaranColors = <?php echo $color_map_json; ?>;
        const totalPengeluaran = '<?php echo $total_pengeluaran_formatted; ?>';
        const ctxPie = document.getElementById('expenseChart').getContext('2d');
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: pengeluaranLabels,
                datasets: [{
                    data: pengeluaranData,
                    backgroundColor: pengeluaranColors,
                    borderWidth: 2
                }]
            },
            options: {
                cutout: '75%',
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            font: {
                                size: 14
                            }
                        }
                    },
                    datalabels: {
                        display: true,
                        formatter: function(value, context) {
                            if (context.dataIndex === 0) {
                                return 'Rp ' + totalPengeluaran + '\nTotal';
                            }
                            return '';
                        },
                        color: '#222',
                        font: {
                            weight: 'bold',
                            size: 15
                        },
                        anchor: 'center',
                        align: 'center',
                        offset: 0
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        function animateValue(id, start, end, duration) {
            let range = end - start;
            let current = start;
            let increment = end > start ? Math.ceil(range / 60) : -Math.ceil(range / 60);
            let stepTime = Math.abs(Math.floor(duration / (range === 0 ? 1 : Math.abs(range))));
            const obj = document.getElementById(id);
            let timer = setInterval(function() {
                current += increment;
                if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                    current = end;
                    clearInterval(timer);
                }
                obj.textContent = 'Rp ' + current.toLocaleString('id-ID');
            }, stepTime);
        }
    </script>

    <?php
    // Footer
    include 'templates/footer.php';
    ?>