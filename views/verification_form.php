<?php
// Get transaction details
$transaction = $transactionHandler->getTransactionById($_GET['id']);
$remaining_payment = $transaction['total_amount'] - $transaction['down_payment_amount'];
?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Verifikasi Pembayaran - <?php echo $transaction['transaction_number']; ?></h5>
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
                <button type="submit" class="btn btn-primary">Verifikasi</button>
            </div>
        </form>
    </div>
</div>

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