<?php
require_once 'config/database.php';
require_once 'includes/transaction_handler.php';

// Cek apakah user sudah login
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Inisialisasi database connection
try {
    $db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $transactionHandler = new TransactionHandler($db);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get transaction details
$transaction_id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$transaction_id) {
    header("Location: index.php");
    exit;
}

$transaction = $transactionHandler->getTransactionById($transaction_id);
if (!$transaction) {
    $_SESSION['error'] = "Transaksi tidak ditemukan";
    header("Location: index.php");
    exit;
}

$remaining_payment = $transaction['total_amount'] - $transaction['down_payment_amount'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Pesanan - <?php echo $transaction['transaction_number']; ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Verifikasi Pesanan - <?php echo $transaction['transaction_number']; ?></h5>
            </div>
            <div class="card-body">
                <form id="verificationForm" method="POST" action="process_verification.php">
                    <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Total Transaksi</label>
                                <input type="text" class="form-control" value="<?php echo number_format($transaction['total_amount'], 0, ',', '.'); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>DP yang Sudah Dibayar</label>
                                <input type="text" class="form-control" value="<?php echo number_format($transaction['down_payment_amount'], 0, ',', '.'); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Sisa Pembayaran</label>
                                <input type="text" class="form-control" value="<?php echo number_format($remaining_payment, 0, ',', '.'); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status Transaksi</label>
                                <select class="form-control" name="status" id="status" required>
                                    <option value="in_progress">Dalam Proses</option>
                                    <option value="completed">Selesai</option>
                                    <option value="cancelled">Dibatalkan</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Form Pembayaran Sisa DP -->
                    <div id="remainingPaymentForm" style="display: none;">
                        <hr>
                        <h6>Pembayaran Sisa DP</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Jumlah Pembayaran</label>
                                    <input type="number" class="form-control" name="payment_amount" id="payment_amount" min="0" max="<?php echo $remaining_payment; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Metode Pembayaran</label>
                                    <select class="form-control" name="payment_method" id="payment_method">
                                        <option value="cash">Tunai</option>
                                        <option value="transfer">Transfer Bank</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Keterangan</label>
                                    <textarea class="form-control" name="payment_note" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Catatan Verifikasi</label>
                        <textarea class="form-control" name="verification_note" rows="3" required></textarea>
                    </div>

                    <div class="text-right">
                        <a href="index.php" class="btn btn-secondary">Kembali</a>
                        <button type="submit" class="btn btn-primary">Verifikasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script>
        document.getElementById('status').addEventListener('change', function() {
            var remainingPaymentForm = document.getElementById('remainingPaymentForm');
            if (this.value === 'completed') {
                remainingPaymentForm.style.display = 'block';
            } else {
                remainingPaymentForm.style.display = 'none';
            }
        });

        // Validasi form
        document.getElementById('verificationForm').addEventListener('submit', function(e) {
            var status = document.getElementById('status').value;
            var paymentAmount = document.getElementById('payment_amount').value;

            if (status === 'completed' && (!paymentAmount || paymentAmount <= 0)) {
                e.preventDefault();
                alert('Mohon isi jumlah pembayaran sisa DP');
            }
        });
    </script>
</body>

</html>