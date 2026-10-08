<?php
$sqlFile = $argv[1] ?? __DIR__ . '/../database/u700125577_keuangan (1).sql';
$content = file_get_contents($sqlFile);

$kasIds = [784, 785, 786, 787, 788];
$kasLabels = [784 => 'Kas', 785 => 'Bank BNU', 786 => 'Mandiri', 787 => 'BNI', 788 => 'BRI'];

$akunNames = [];
if (preg_match_all("/\((\d+), '([^']+)', '([^']+)', '[^']+', '[^']+', '[^']+', [^,]+, [^,]+, '[^']+', 6\)/", $content, $m, PREG_SET_ORDER)) {
    foreach ($m as $row) {
        $akunNames[(int) $row[1]] = $row[2] . ' - ' . $row[3];
    }
}

$debit = array_fill_keys($kasIds, 0.0);
$kredit = array_fill_keys($kasIds, 0.0);
$bankBnu = [];
$allBumnu = [];

// Parse transaksi lines containing ", 6, '20" at end (id_perusahaan = 6)
foreach (explode("\n", $content) as $line) {
    if (!preg_match("/, 6, '20\d{2}-/", $line)) {
        continue;
    }
    // Strip leading ( and trailing ),
    $line = trim($line, "(), \r");
    // Split from end: created_at, id_perusahaan, created_by, tag, penanggung_jawab, file, total, bunga, pajak, jumlah, jenis
    if (!preg_match(
        "/^\((\d+), '(\d{4}-\d{2}-\d{2})', (\d+), (\d+), '((?:''|[^'])*)', '([^']+)', ([0-9.]+), [0-9.]+, [0-9.]+, ([0-9.]+),/",
        $line,
        $x
    )) {
        continue;
    }

    $rec = [
        'id' => (int) $x[1],
        'tanggal' => $x[2],
        'id_debit' => (int) $x[3],
        'id_kredit' => (int) $x[4],
        'keterangan' => str_replace("''", "'", $x[5]),
        'jenis' => $x[6],
        'jumlah' => (float) $x[7],
        'total' => (float) $x[8],
    ];
    $allBumnu[] = $rec;

    if (in_array($rec['id_debit'], $kasIds, true)) {
        $debit[$rec['id_debit']] += $rec['jumlah'];
    }
    if (in_array($rec['id_kredit'], $kasIds, true)) {
        $kredit[$rec['id_kredit']] += $rec['jumlah'];
    }

    if ($rec['id_debit'] === 785 || $rec['id_kredit'] === 785) {
        $rec['side'] = $rec['id_debit'] === 785 ? 'IN' : 'OUT';
        $rec['debit_name'] = $akunNames[$rec['id_debit']] ?? (string) $rec['id_debit'];
        $rec['kredit_name'] = $akunNames[$rec['id_kredit']] ?? (string) $rec['id_kredit'];
        $bankBnu[] = $rec;
    }
}

echo 'Transaksi KAS BUMNU: ' . count($allBumnu) . "\n\n";
echo "=== Saldo Kas & Bank (dari jurnal, field jumlah) ===\n";
$total = 0;
foreach ($kasIds as $id) {
    $s = $debit[$id] - $kredit[$id];
    $total += $s;
    printf("%-12s debit %15s kredit %15s saldo %15s\n",
        $kasLabels[$id],
        number_format($debit[$id], 0, ',', '.'),
        number_format($kredit[$id], 0, ',', '.'),
        number_format($s, 0, ',', '.')
    );
}
echo 'TOTAL: ' . number_format($total, 0, ',', '.') . "\n\n";

$pm = $pg = 0;
foreach ($allBumnu as $t) {
    if ($t['jenis'] === 'pemasukan') {
        $pm += $t['jumlah'];
    }
    if ($t['jenis'] === 'pengeluaran') {
        $pg += $t['jumlah'];
    }
}
echo 'Dashboard (pemasukan-pengeluaran): ' . number_format($pm - $pg, 0, ',', '.') . "\n";
echo "  pemasukan $pm pengeluaran $pg\n\n";

$outJenis = [];
$inJenis = [];
foreach ($bankBnu as $t) {
    if ($t['side'] === 'OUT') {
        $outJenis[$t['jenis']] = ($outJenis[$t['jenis']] ?? 0) + $t['jumlah'];
    } else {
        $inJenis[$t['jenis']] = ($inJenis[$t['jenis']] ?? 0) + $t['jumlah'];
    }
}
arsort($outJenis);
arsort($inJenis);
echo "=== Bank BNU KELUAR by jenis ===\n";
foreach ($outJenis as $j => $v) {
    echo "  $j: " . number_format($v, 0, ',', '.') . "\n";
}
echo "=== Bank BNU MASUK by jenis ===\n";
foreach ($inJenis as $j => $v) {
    echo "  $j: " . number_format($v, 0, ',', '.') . "\n";
}

// Saldo akun field in DB for Bank BNU (static)
if (preg_match("/\(785, '1-10002', 'Bank BNU'[^)]+\)/", $content, $ab)) {
    echo "\nakun.saldo kolom (statis di dump): " . $ab[0] . "\n";
}

// Wrong jenis: pemasukan but money leaves bank (kredit 785)
echo "\n=== SALAH JENIS: jenis=pemasukan tapi KREDIT Bank BNU (uang keluar) ===\n";
foreach ($bankBnu as $t) {
    if ($t['jenis'] === 'pemasukan' && $t['side'] === 'OUT') {
        printf("#%d %s %s | %s → %s\n", $t['id'], $t['tanggal'], number_format($t['jumlah'], 0, ',', '.'), $t['debit_name'], $t['kredit_name']);
    }
}

echo "\n=== SALAH JENIS: jenis=pengeluaran tapi DEBIT Bank BNU (uang masuk) ===\n";
foreach ($bankBnu as $t) {
    if ($t['jenis'] === 'pengeluaran' && $t['side'] === 'IN') {
        printf("#%d %s %s | %s | %s\n", $t['id'], $t['tanggal'], number_format($t['jumlah'], 0, ',', '.'), $t['keterangan'], $t['debit_name'] . ' → ' . $t['kredit_name']);
    }
}

echo "\n=== transfer_uang yang KREDIT Bank BNU (pindah keluar — tidak di dashboard pengeluaran) ===\n";
$tu = 0;
foreach ($bankBnu as $t) {
    if ($t['jenis'] === 'transfer_uang' && $t['side'] === 'OUT') {
        $tu += $t['jumlah'];
        printf("#%d %s %s | %s → %s | %s\n", $t['id'], $t['tanggal'], number_format($t['jumlah'], 0, ',', '.'), $t['debit_name'], $t['kredit_name'], mb_substr($t['keterangan'], 0, 60));
    }
}
echo "Total transfer_uang OUT: " . number_format($tu, 0, ',', '.') . "\n";

echo "\n=== Top 15 KELUAR dari Bank BNU ===\n";
usort($bankBnu, fn ($a, $b) => $b['jumlah'] <=> $a['jumlah']);
$n = 0;
foreach ($bankBnu as $t) {
    if ($t['side'] !== 'OUT') {
        continue;
    }
    if (++$n > 15) {
        break;
    }
    printf("#%d %s [%s] %s\n   %s → %s\n   %s\n", $t['id'], $t['tanggal'], $t['jenis'], number_format($t['jumlah'], 0, ',', '.'), $t['debit_name'], $t['kredit_name'], $t['keterangan']);
}

// Debit to non-beban when credit bank - check account 866 modal
echo "\n=== Pengeluaran besar: kredit Bank BNU ===\n";
foreach ($bankBnu as $t) {
    if ($t['side'] === 'OUT' && $t['jumlah'] >= 50000000) {
        printf("#%d %s [%s] %s — %s\n", $t['id'], $t['tanggal'], $t['jenis'], number_format($t['jumlah'], 0, ',', '.'), $t['keterangan']);
    }
}
