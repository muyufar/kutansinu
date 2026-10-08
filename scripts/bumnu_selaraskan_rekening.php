<?php
/**
 * Selaraskan jurnal KAS BUMNU (perusahaan 6) dengan rekening koran Bank BNU.
 *
 * PENTING production:
 * - Baris 1 file ini WAJIB "<?php" (bukan "k?php" — bug editor Hostinger).
 * - JANGAN require config/database.production.php langsung (hanya return array).
 *   Pakai config/database.php — di server live otomatis memuat database.production.php.
 *
 * CLI:
 *   php scripts/bumnu_selaraskan_rekening.php
 *   php scripts/bumnu_selaraskan_rekening.php --apply --saldo-koran=55345807 --per=2026-10-08
 *
 * Browser (harus login SiKeu; apply hanya jika ?apply=1):
 *   .../scripts/bumnu_selaraskan_rekening.php?per=2026-10-08&saldo_koran=55345807
 *   .../scripts/bumnu_selaraskan_rekening.php?apply=1&saldo_koran=55345807&per=2026-10-08
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/functions.php';

    if (PHP_SAPI !== 'cli') {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        requireLogin();
    }

    if (!isset($db) || !$db instanceof PDO) {
        throw new RuntimeException(
            'Variabel $db tidak tersedia. Gunakan require config/database.php, bukan database.production.php.'
        );
    }
} catch (Throwable $e) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        echo "Error: " . $e->getMessage() . "\n";
        if (function_exists('appEnvironment') && appEnvironment() !== 'production') {
            echo $e->getTraceAsString();
        }
    } else {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
    }
    exit(1);
}

const BUMNU_PERUSAHAAN = 6;
const AKUN_BANK_BNU = 785;
const AKUN_SALDO_AWAL = 849;
const AKUN_HUTANG_BNU = 834;
const AKUN_HUTANG_NUGO = 827;
const AKUN_BEBAN_BANK = 866;
const SYNC_TAG = 'BUMNU-SYNC-RK';
const DEFAULT_CREATED_BY = 9;

function argValue(string $name, array $argv): ?string
{
    foreach ($argv as $i => $arg) {
        if ($arg === $name && isset($argv[$i + 1])) {
            return $argv[$i + 1];
        }
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }

    return null;
}

function hasFlag(string $name, array $argv): bool
{
    return in_array($name, $argv, true);
}

/** CLI ($argv) atau query string browser (?apply=1&saldo_koran=...). */
function buildRuntimeArgv(): array
{
    if (PHP_SAPI === 'cli') {
        global $argv;

        return is_array($argv) ? $argv : ['scripts/bumnu_selaraskan_rekening.php'];
    }

    $args = ['scripts/bumnu_selaraskan_rekening.php'];
    $g = $_GET;

    if (!empty($g['apply']) && (string) $g['apply'] !== '0') {
        $args[] = '--apply';
    }
    if (!empty($g['catat_hutang_rk_awal'])) {
        $args[] = '--catat-hutang-rk-awal';
    }
    if (!empty($g['hapus_transaksi_2166'])) {
        $args[] = '--hapus-transaksi-2166';
    }

    $map = [
        'saldo_koran' => '--saldo-koran',
        'saldo-koran' => '--saldo-koran',
        'per' => '--per',
        'saldo_awal' => '--saldo-awal',
        'saldo-awal' => '--saldo-awal',
        'tanggal_awal' => '--tanggal-awal',
        'tanggal-awal' => '--tanggal-awal',
    ];
    foreach ($map as $key => $flag) {
        if (isset($g[$key]) && (string) $g[$key] !== '') {
            $args[] = $flag . '=' . rawurlencode((string) $g[$key]);
        }
    }

    return $args;
}

function saldoBank(PDO $db, int $idPerusahaan, int $bankId, string $tanggal): float
{
    $akun = getAkunById($db, $bankId, $idPerusahaan);
    if (!$akun) {
        throw new RuntimeException("Akun bank $bankId tidak ditemukan untuk perusahaan $idPerusahaan");
    }

    return getSaldoAkunSampaiTanggal($db, $bankId, $tanggal, $idPerusahaan, $akun['tipe_akun']);
}

function countByTag(PDO $db, int $idPerusahaan, string $tag): int
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM transaksi WHERE id_perusahaan = ? AND tag = ?');
    $stmt->execute([$idPerusahaan, $tag]);

    return (int) $stmt->fetchColumn();
}

function insertTransaksi(
    PDO $db,
    bool $apply,
    string $tanggal,
    int $debit,
    int $kredit,
    string $ket,
    string $jenis,
    float $jumlah,
    string $tag
): void {
    $sql = 'INSERT INTO transaksi (tanggal, id_akun_debit, id_akun_kredit, keterangan, jenis, jumlah, pajak, bunga, total, file_lampiran, penanggung_jawab, tag, created_by, id_perusahaan)
            VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, \'\', \'Sistem\', ?, ?, ?)';
    echo "  + INSERT $tanggal | Dr $debit Cr $kredit | $jenis | " . number_format($jumlah, 0, ',', '.') . " | $ket\n";
    if ($apply) {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $tanggal,
            $debit,
            $kredit,
            $ket,
            $jenis,
            $jumlah,
            $jumlah,
            $tag,
            DEFAULT_CREATED_BY,
            BUMNU_PERUSAHAAN,
        ]);
    }
}

$runtimeArgv = buildRuntimeArgv();
$apply = hasFlag('--apply', $runtimeArgv);
$saldoKoran = argValue('--saldo-koran', $runtimeArgv);
$perKoran = argValue('--per', $runtimeArgv) ?? date('Y-m-d');
$saldoAwal = argValue('--saldo-awal', $runtimeArgv);
$tanggalAwal = argValue('--tanggal-awal', $runtimeArgv) ?? '2025-12-14';
$catatHutangAwal = hasFlag('--catat-hutang-rk-awal', $runtimeArgv);
$hapusDuplikat2166 = hasFlag('--hapus-transaksi-2166', $runtimeArgv);

if (PHP_SAPI !== 'cli') {
    echo "Environment: " . appEnvironment() . " (config: database." . appEnvironment() . ".php)\n\n";
}

echo $apply ? "MODE: APPLY (perubahan ditulis ke DB)\n\n" : "MODE: DRY-RUN (tambahkan --apply atau ?apply=1 untuk eksekusi)\n\n";

$saldoSebelum = saldoBank($db, BUMNU_PERUSAHAAN, AKUN_BANK_BNU, $perKoran);
echo 'Saldo jurnal Bank BNU per ' . $perKoran . ': ' . number_format($saldoSebelum, 0, ',', '.') . "\n\n";

// --- 1. Pemasukan terbalik (uang masuk koran harus DEBIT 785) ---
$stmt = $db->prepare("
    SELECT id, tanggal, id_akun_debit, id_akun_kredit, jumlah, keterangan
    FROM transaksi
    WHERE id_perusahaan = ?
      AND jenis = 'pemasukan'
      AND id_akun_kredit = ?
      AND id_akun_debit <> ?
    ORDER BY id ASC
");
$stmt->execute([BUMNU_PERUSAHAAN, AKUN_BANK_BNU, AKUN_BANK_BNU]);
$terbalik = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '=== 1. Pemasukan debit/kredit terbalik (' . count($terbalik) . " transaksi) ===\n";
$deltaTerbalik = 0.0;
foreach ($terbalik as $row) {
    $j = (float) $row['jumlah'];
    $deltaTerbalik += 2 * $j;
    echo "  #{$row['id']} {$row['tanggal']} " . number_format($j, 0, ',', '.') . " | Dr {$row['id_akun_debit']} → Kr {$row['id_akun_kredit']} | {$row['keterangan']}\n";
    if ($apply) {
        $u = $db->prepare('UPDATE transaksi SET id_akun_debit = ?, id_akun_kredit = ? WHERE id = ? AND id_perusahaan = ?');
        $u->execute([(int) $row['id_akun_kredit'], (int) $row['id_akun_debit'], (int) $row['id'], BUMNU_PERUSAHAAN]);
    }
}
if ($terbalik) {
    echo '  Dampak saldo bank (perbaikan): +' . number_format($deltaTerbalik, 0, ',', '.') . "\n\n";
} else {
    echo "  (tidak ada)\n\n";
}

// --- 2. Pelunasan RK salah akun 866 → hutang ---
$modalMap = [
    661 => ['debit' => AKUN_HUTANG_NUGO, 'label' => 'Pengembalian modal NUGO 100jt (RK via BNU)'],
    687 => ['debit' => AKUN_HUTANG_BNU, 'label' => 'Penurunan pokok hutang BNU 250jt'],
    2166 => ['debit' => AKUN_HUTANG_BNU, 'label' => 'Pelunasan pokok hutang BNU 250jt'],
];

echo "=== 2. Pelunasan RK: Beban Bank → Hutang ===\n";
foreach ($modalMap as $tid => $cfg) {
    $q = $db->prepare('SELECT id, id_akun_debit, jenis, jumlah FROM transaksi WHERE id = ? AND id_perusahaan = ?');
    $q->execute([$tid, BUMNU_PERUSAHAAN]);
    $t = $q->fetch(PDO::FETCH_ASSOC);
    if (!$t) {
        echo "  #$tid tidak ada (skip)\n";
        continue;
    }
    if ((int) $t['id_akun_debit'] === $cfg['debit']) {
        echo "  #$tid sudah benar (Dr {$cfg['debit']})\n";
        continue;
    }
    if ((int) $t['id_akun_debit'] !== AKUN_BEBAN_BANK) {
        echo "  #$tid akun debit bukan 866 (Dr {$t['id_akun_debit']}), skip manual\n";
        continue;
    }
    echo "  #$tid UPDATE Dr {$cfg['debit']} jenis=pengeluaran | {$cfg['label']}\n";
    if ($apply) {
        $u = $db->prepare("UPDATE transaksi SET id_akun_debit = ?, jenis = 'pengeluaran' WHERE id = ? AND id_perusahaan = ?");
        $u->execute([$cfg['debit'], $tid, BUMNU_PERUSAHAAN]);
    }
}

// Pelunasan hutang yang sudah transfer_hutang → pengeluaran (tampilan dashboard)
echo "=== 2a. Bayar hutang = pengeluaran (jenis) ===\n";
$stmtTh = $db->prepare("
    SELECT id FROM transaksi
    WHERE id_perusahaan = ?
      AND jenis = 'transfer_hutang'
      AND id_akun_kredit = ?
      AND id_akun_debit IN (?, ?, ?)
");
$stmtTh->execute([BUMNU_PERUSAHAAN, AKUN_BANK_BNU, AKUN_HUTANG_BNU, AKUN_HUTANG_NUGO, AKUN_BEBAN_BANK]);
$thIds = $stmtTh->fetchAll(PDO::FETCH_COLUMN);
if ($thIds) {
    echo '  UPDATE jenis pengeluaran untuk ID: ' . implode(', ', $thIds) . "\n";
    if ($apply) {
        $placeholders = implode(',', array_fill(0, count($thIds), '?'));
        $params = array_merge(['pengeluaran', BUMNU_PERUSAHAAN], array_map('intval', $thIds));
        $db->prepare("UPDATE transaksi SET jenis = ? WHERE id_perusahaan = ? AND id IN ($placeholders)")->execute($params);
    }
} else {
    echo "  (tidak ada transfer_hutang ke Bank BNU yang perlu diubah)\n";
}

// Beban adm bank #699 — tetap 866, perbaiki total
$q699 = $db->prepare('SELECT id, jumlah, total FROM transaksi WHERE id = 699 AND id_perusahaan = ?');
$q699->execute([BUMNU_PERUSAHAAN]);
if ($t699 = $q699->fetch(PDO::FETCH_ASSOC)) {
    if ((float) $t699['total'] !== (float) $t699['jumlah']) {
        echo "  #699 perbaiki kolom total = jumlah ({$t699['jumlah']})\n";
        if ($apply) {
            $db->prepare('UPDATE transaksi SET total = jumlah WHERE id = 699 AND id_perusahaan = ?')->execute([BUMNU_PERUSAHAAN]);
        }
    }
}
echo "\n";

if ($hapusDuplikat2166) {
    echo "=== 2b. Hapus transaksi #2166 (opsional, jika duplikat di koran) ===\n";
    if ($apply) {
        $db->prepare('DELETE FROM transaksi WHERE id = 2166 AND id_perusahaan = ?')->execute([BUMNU_PERUSAHAAN]);
        echo "  Dihapus.\n\n";
    } else {
        echo "  Akan DELETE #2166 (+250jt saldo bank jika memang duplikat).\n\n";
    }
}

// --- 3. Saldo awal rekening ---
echo "=== 3. Jurnal saldo awal rekening (785 ↔ 849) ===\n";
$tagAwal = SYNC_TAG . '-SALDO-AWAL';
if ($saldoAwal !== null && (float) $saldoAwal > 0) {
    if (countByTag($db, BUMNU_PERUSAHAAN, $tagAwal) > 0) {
        echo "  Sudah ada transaksi tag $tagAwal (skip).\n\n";
    } else {
        insertTransaksi(
            $db,
            $apply,
            $tanggalAwal,
            AKUN_BANK_BNU,
            AKUN_SALDO_AWAL,
            'Saldo awal rekening Bank BNU sesuai rekening koran / RK (sebelum pencatatan transaksi)',
            'tanam_modal',
            (float) $saldoAwal,
            $tagAwal
        );
        echo "\n";
    }
} else {
    echo "  Lewati (isi --saldo-awal=... jika koran punya saldo opening terpisah dari mutasi).\n\n";
}

// --- 4. Hutang RK awal (neraca saja) ---
if ($catatHutangAwal) {
    echo "=== 4. Hutang RK awal (849 ↔ 834/827, tidak ubah saldo bank) ===\n";
    $entries = [
        ['tag' => SYNC_TAG . '-HUTANG-BNU-500', 'tanggal' => $tanggalAwal, 'd' => AKUN_SALDO_AWAL, 'k' => AKUN_HUTANG_BNU, 'j' => 500000000.0, 'ket' => 'Pencatatan hutang RK pembiayaan BNU 500 jt'],
        ['tag' => SYNC_TAG . '-HUTANG-NUGO-100', 'tanggal' => $tanggalAwal, 'd' => AKUN_SALDO_AWAL, 'k' => AKUN_HUTANG_NUGO, 'j' => 100000000.0, 'ket' => 'Pencatatan hutang RK pencairan NUGO 100 jt via rekening BNU'],
    ];
    foreach ($entries as $e) {
        if (countByTag($db, BUMNU_PERUSAHAAN, $e['tag']) > 0) {
            echo "  Tag {$e['tag']} sudah ada (skip).\n";
            continue;
        }
        insertTransaksi($db, $apply, $e['tanggal'], $e['d'], $e['k'], $e['ket'], 'hutang', $e['j'], $e['tag']);
    }
    echo "\n";
}

// Recalculate after structural fixes (in dry-run, simulate)
if (!$apply) {
    $saldoSetelah = $saldoSebelum + $deltaTerbalik;
    if ($saldoAwal !== null && (float) $saldoAwal > 0 && countByTag($db, BUMNU_PERUSAHAAN, SYNC_TAG . '-SALDO-AWAL') === 0) {
        $saldoSetelah += (float) $saldoAwal;
    }
    if ($hapusDuplikat2166) {
        $saldoSetelah += 250000000.0;
    }
} else {
    $saldoSetelah = saldoBank($db, BUMNU_PERUSAHAAN, AKUN_BANK_BNU, $perKoran);
}

echo 'Saldo jurnal Bank BNU (setelah koreksi di atas): ' . number_format($saldoSetelah, 0, ',', '.') . "\n";

// --- 5. Penyesuaian ke saldo koran ---
$saldoKoranTrim = $saldoKoran !== null ? trim((string) $saldoKoran) : '';
if ($saldoKoranTrim !== '') {
    $target = (float) str_replace(['.', ','], ['', '.'], $saldoKoranTrim);
    if ($target <= 0 && !hasFlag('--izinkan-saldo-koran-nol', $runtimeArgv)) {
        echo "\n=== 5. Penyesuaian rekening koran ===\n";
        echo "  DIBATALKAN: saldo_koran <= 0 (sering karena parameter kosong/salah).\n";
        echo "  Isi saldo ASLI dari rekening koran BNU, contoh: saldo_koran=55345807\n";
        echo "  Jangan samakan dengan 'Net jenis' di dashboard.\n\n";
    } else {
    $selisih = $target - $saldoSetelah;
    echo "\n=== 5. Penyesuaian rekening koran target " . number_format($target, 0, ',', '.') . " ===\n";
    echo 'Selisih: ' . number_format($selisih, 0, ',', '.') . "\n";

    if (abs($selisih) < 1) {
        echo "  Sudah selaras.\n";
    } else {
        $tagAdj = SYNC_TAG . '-ADJ-KORAN-' . $perKoran;
        if (countByTag($db, BUMNU_PERUSAHAAN, $tagAdj) > 0) {
            echo "  Penyesuaian tag $tagAdj sudah pernah dibuat (skip).\n";
        } elseif ($selisih > 0) {
            insertTransaksi(
                $db,
                $apply,
                $perKoran,
                AKUN_BANK_BNU,
                AKUN_SALDO_AWAL,
                "Penyesuaian saldo rekening koran BNU per $perKoran",
                'tanam_modal',
                $selisih,
                $tagAdj
            );
        } else {
            insertTransaksi(
                $db,
                $apply,
                $perKoran,
                AKUN_SALDO_AWAL,
                AKUN_BANK_BNU,
                "Penyesuaian saldo rekening koran BNU per $perKoran (kurang di jurnal)",
                'tarik_modal',
                abs($selisih),
                $tagAdj
            );
        }
    }

    if ($apply) {
        $final = saldoBank($db, BUMNU_PERUSAHAAN, AKUN_BANK_BNU, $perKoran);
        echo "\nSaldo akhir Bank BNU: " . number_format($final, 0, ',', '.') . "\n";
    }
    }
} else {
    echo "\n=== 5. Penyesuaian rekening koran ===\n";
    echo "  Lewati (parameter saldo_koran tidak diisi).\n";
}

echo "\n--- Catatan RK ---\n";
echo "Basil MBG dari BNU = pemasukan (Dr Bank, Cr Pendapatan). Angsuran = Dr Hutang pokok + Dr Beban basil/margin, Cr Bank.\n";
echo "Pelunasan pokok sudah dipetakan ke akun 834/827, bukan Beban Bank.\n";
echo "Isi --saldo-koran=<angka di koran> --per=<tanggal koran> setelah cek mutasi.\n";

if (PHP_SAPI !== 'cli') {
    echo "\n--- URL contoh (sudah login) ---\n";
    echo "?per=2026-10-08&saldo_koran=55345807\n";
    echo "?apply=1&per=2026-10-08&saldo_koran=55345807\n";
}
