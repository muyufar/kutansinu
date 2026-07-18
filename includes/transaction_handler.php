<?php
class TransactionHandler
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // Menangani transaksi DP/Uang Muka
    public function handleDownPayment($data)
    {
        try {
            $this->db->beginTransaction();

            // 1. Catat penerimaan DP
            $sql = "INSERT INTO transactions (
                type,
                amount,
                category_id,
                description,
                status,
                created_by,
                payment_type,
                customer_id,
                transaction_date
            ) VALUES (
                'deferred_revenue',
                :amount,
                :category_id,
                :description,
                'approved',
                :created_by,
                'down_payment',
                :customer_id,
                :transaction_date
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':amount' => $data['amount'],
                ':category_id' => $data['category_id'],
                ':description' => "DP/Uang Muka - " . $data['description'],
                ':created_by' => $data['user_id'],
                ':customer_id' => $data['customer_id'],
                ':transaction_date' => $data['transaction_date']
            ]);

            // 2. Catat di jurnal
            $transaction_id = $this->db->lastInsertId();

            // Debit Kas/Bank
            $this->createJournalEntry(
                $transaction_id,
                $data['cash_account_id'], // ID akun kas/bank
                'debit',
                $data['amount'],
                'Penerimaan DP/Uang Muka'
            );

            // Kredit Pendapatan Diterima di Muka
            $this->createJournalEntry(
                $transaction_id,
                $data['deferred_revenue_account_id'], // ID akun pendapatan diterima di muka
                'credit',
                $data['amount'],
                'Penerimaan DP/Uang Muka'
            );

            $this->db->commit();
            return [
                'success' => true,
                'transaction_id' => $transaction_id,
                'message' => 'Transaksi DP berhasil dicatat'
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    // Membuat entri jurnal
    private function createJournalEntry($transaction_id, $account_id, $type, $amount, $description)
    {
        $sql = "INSERT INTO journal_entries (
            transaction_id,
            account_id,
            type,
            amount,
            description,
            created_at
        ) VALUES (
            :transaction_id,
            :account_id,
            :type,
            :amount,
            :description,
            NOW()
        )";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':transaction_id' => $transaction_id,
            ':account_id' => $account_id,
            ':type' => $type,
            ':amount' => $amount,
            ':description' => $description
        ]);
    }

    // Mengakui pendapatan dari DP
    public function recognizeRevenue($transaction_id, $amount, $description)
    {
        try {
            $this->db->beginTransaction();

            // 1. Kurangi pendapatan diterima di muka
            $sql1 = "UPDATE transactions 
                    SET amount = amount - :amount 
                    WHERE id = :transaction_id";

            $stmt1 = $this->db->prepare($sql1);
            $stmt1->execute([
                ':amount' => $amount,
                ':transaction_id' => $transaction_id
            ]);

            // 2. Tambahkan ke pendapatan
            $sql2 = "INSERT INTO transactions (
                type,
                amount,
                category_id,
                description,
                status,
                created_by,
                payment_type,
                customer_id,
                transaction_date
            ) VALUES (
                'income',
                :amount,
                (SELECT category_id FROM transactions WHERE id = :transaction_id),
                :description,
                'approved',
                (SELECT created_by FROM transactions WHERE id = :transaction_id),
                'revenue_recognition',
                (SELECT customer_id FROM transactions WHERE id = :transaction_id),
                CURDATE()
            )";

            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute([
                ':amount' => $amount,
                ':transaction_id' => $transaction_id,
                ':description' => $description
            ]);

            $new_transaction_id = $this->db->lastInsertId();

            // 3. Buat jurnal
            // Debit Pendapatan Diterima di Muka
            $this->createJournalEntry(
                $transaction_id,
                $data['deferred_revenue_account_id'],
                'debit',
                $amount,
                'Pengakuan Pendapatan dari DP'
            );

            // Kredit Pendapatan
            $this->createJournalEntry(
                $new_transaction_id,
                $data['revenue_account_id'],
                'credit',
                $amount,
                'Pengakuan Pendapatan dari DP'
            );

            $this->db->commit();
            return [
                'success' => true,
                'message' => 'Pendapatan berhasil diakui'
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    // Mendapatkan detail transaksi
    public function getTransactionById($transaction_id)
    {
        $sql = "SELECT t.*, 
                c.name as customer_name,
                u.username as created_by_name
                FROM transactions t
                LEFT JOIN customers c ON t.customer_id = c.id
                LEFT JOIN users u ON t.created_by = u.id
                WHERE t.id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$transaction_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Memproses pembayaran
    public function processPayment($data)
    {
        try {
            $this->db->beginTransaction();

            // 1. Catat pembayaran
            $sql = "INSERT INTO payments (
                transaction_id,
                amount,
                payment_method,
                note,
                created_by,
                created_at
            ) VALUES (
                :transaction_id,
                :amount,
                :payment_method,
                :note,
                :user_id,
                NOW()
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':transaction_id' => $data['transaction_id'],
                ':amount' => $data['amount'],
                ':payment_method' => $data['payment_method'],
                ':note' => $data['note'],
                ':user_id' => $data['user_id']
            ]);

            $payment_id = $this->db->lastInsertId();

            // 2. Update status pembayaran di transaksi
            $sql2 = "UPDATE transactions 
                    SET payment_status = CASE 
                        WHEN (SELECT SUM(amount) FROM payments WHERE transaction_id = :transaction_id) >= total_amount 
                        THEN 'paid' 
                        ELSE 'partial' 
                    END
                    WHERE id = :transaction_id";

            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute([':transaction_id' => $data['transaction_id']]);

            // 3. Buat jurnal
            // Debit Kas/Bank
            $this->createJournalEntry(
                $payment_id,
                $data['cash_account_id'],
                'debit',
                $data['amount'],
                'Pembayaran sisa DP - ' . $data['note']
            );

            // Kredit Piutang
            $this->createJournalEntry(
                $payment_id,
                $data['receivable_account_id'],
                'credit',
                $data['amount'],
                'Pembayaran sisa DP - ' . $data['note']
            );

            $this->db->commit();
            return [
                'success' => true,
                'payment_id' => $payment_id,
                'message' => 'Pembayaran berhasil diproses'
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
}
