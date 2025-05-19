<?php
require_once 'config/database.php';
require_once 'includes/transaction_handler.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = $_POST['transaction_id'];
    $status = $_POST['status'];
    $verification_note = $_POST['verification_note'];

    try {
        $db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $transactionHandler = new TransactionHandler($db);

        // Update status transaksi
        $sql = "UPDATE transactions 
                SET status = :status,
                    verification_note = :verification_note,
                    verified_at = NOW(),
                    verified_by = :user_id
                WHERE id = :transaction_id";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':status' => $status,
            ':verification_note' => $verification_note,
            ':user_id' => $_SESSION['user_id'],
            ':transaction_id' => $transaction_id
        ]);

        // Jika status selesai, proses pengakuan pendapatan
        if ($status === 'completed') {
            $payment_amount = $_POST['payment_amount'];
            $payment_method = $_POST['payment_method'];
            $payment_note = $_POST['payment_note'];

            // 1. Proses pembayaran sisa
            if ($payment_amount > 0) {
                $payment_data = [
                    'transaction_id' => $transaction_id,
                    'amount' => $payment_amount,
                    'payment_method' => $payment_method,
                    'note' => $payment_note,
                    'user_id' => $_SESSION['user_id']
                ];

                $transactionHandler->processPayment($payment_data);
            }

            // 2. Akui pendapatan dari DP
            $transaction = $transactionHandler->getTransactionById($transaction_id);
            $dp_amount = $transaction['down_payment_amount'];

            if ($dp_amount > 0) {
                $transactionHandler->recognizeRevenue(
                    $transaction_id,
                    $dp_amount,
                    "Pengakuan pendapatan dari DP transaksi bus - " . $transaction['transaction_number']
                );
            }
        }

        $_SESSION['success'] = "Verifikasi transaksi berhasil diproses";
        header("Location: transaction_detail.php?id=" . $transaction_id);
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: verification_form.php?id=" . $transaction_id);
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
