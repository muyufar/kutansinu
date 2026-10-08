<?php
/** Hitung saldo jurnal Bank BNU (785) perusahaan KAS BUMNU dari dump SQL atau DB live. */
$sqlFile = $argv[1] ?? null;
$idPerusahaan = 6;
$bankId = 785;

if ($sqlFile && is_file($sqlFile)) {
    $pat = '/^\((\d+), \'(\d{4}-\d{2}-\d{2})\', (\d+), (\d+), /';
    $debit = $kredit = 0.0;
    $n = 0;
    foreach (file($sqlFile) as $line) {
        if (!preg_match("/, {$idPerusahaan}, '20/", $line)) {
            continue;
        }
        if (!preg_match($pat, trim($line), $m)) {
            continue;
        }
        if (!preg_match("/, '([^']+)', ([0-9.]+), [0-9.]+, [0-9.]+, ([0-9.]+),/", $line, $j)) {
            continue;
        }
        $ad = (int) $m[3];
        $ak = (int) $m[4];
        $jml = (float) $j[2];
        $n++;
        if ($ad === $bankId) {
            $debit += $jml;
        }
        if ($ak === $bankId) {
            $kredit += $jml;
        }
    }
    echo "Transaksi: $n\n";
    echo "Debit 785: " . number_format($debit, 0, ',', '.') . "\n";
    echo "Kredit 785: " . number_format($kredit, 0, ',', '.') . "\n";
    echo "Saldo: " . number_format($debit - $kredit, 0, ',', '.') . "\n";
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
$akun = getAkunById($db, $bankId, $idPerusahaan);
$saldo = getSaldoAkunSampaiTanggal($db, $bankId, date('Y-m-d'), $idPerusahaan, $akun['tipe_akun']);
echo 'Saldo live Bank BNU: ' . number_format($saldo, 0, ',', '.') . "\n";
