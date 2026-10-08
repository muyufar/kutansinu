<?php

function bumnuPublicConfig(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/public_report.php';
    }
    return $config;
}

function bumnuPublicCheckToken(): bool
{
    $config = bumnuPublicConfig();
    $expected = trim((string) ($config['access_token'] ?? ''));
    if ($expected === '') {
        return true;
    }
    $given = trim((string) ($_GET['token'] ?? ''));
    return $given !== '' && hash_equals($expected, $given);
}

function bumnuResolveCompany(PDO $db): ?array
{
    $names = bumnuPublicConfig()['bumnu_company_names'] ?? ['KAS BUMNU'];
    foreach ($names as $name) {
        $stmt = $db->prepare('SELECT id, nama, logo FROM perusahaan WHERE UPPER(TRIM(nama)) = UPPER(TRIM(?)) LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }
    $stmt = $db->query("SELECT id, nama, logo FROM perusahaan WHERE UPPER(nama) LIKE '%KAS BUMNU%' LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    return $row ?: null;
}

function bumnuGetKasAccounts(PDO $db, int $id_perusahaan): array
{
    $stmt = $db->prepare("
        SELECT id, kode_akun, nama_akun, tipe_akun
        FROM akun
        WHERE id_perusahaan = ?
          AND kategori = 'aktiva'
          AND sub_kategori = 'Kas & Bank'
        ORDER BY kode_akun ASC
    ");
    $stmt->execute([$id_perusahaan]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function bumnuTotalSaldoKas(PDO $db, array $kasAccounts, string $tanggal, int $id_perusahaan): float
{
    $total = 0.0;
    foreach ($kasAccounts as $akun) {
        $total += getSaldoAkunSampaiTanggal(
            $db,
            (int) $akun['id'],
            $tanggal,
            $id_perusahaan,
            $akun['tipe_akun']
        );
    }
    return $total;
}

function bumnuSaldoPerRekening(PDO $db, array $kasAccounts, string $tanggal, int $id_perusahaan): array
{
    $rows = [];
    foreach ($kasAccounts as $akun) {
        $saldo = getSaldoAkunSampaiTanggal(
            $db,
            (int) $akun['id'],
            $tanggal,
            $id_perusahaan,
            $akun['tipe_akun']
        );
        if (abs($saldo) < 0.005) {
            continue;
        }
        $rows[] = [
            'nama' => $akun['nama_akun'],
            'saldo' => $saldo,
        ];
    }
    return $rows;
}

function bumnuJenisLabel(string $jenis): string
{
    $map = [
        'pemasukan' => 'Penerimaan',
        'pengeluaran' => 'Pengeluaran',
        'hutang' => 'Hutang / kewajiban',
        'piutang' => 'Piutang',
        'tanam_modal' => 'Penambahan modal',
        'tarik_modal' => 'Penarikan modal',
        'transfer_uang' => 'Pindah antar rekening',
        'pemasukan_piutang' => 'Pelunasan piutang',
        'transfer_hutang' => 'Pelunasan hutang',
    ];
    return $map[$jenis] ?? ucfirst(str_replace('_', ' ', $jenis));
}

function bumnuKasMutasiPeriode(PDO $db, array $kasIds, int $id_perusahaan, string $tanggal_awal, string $tanggal_akhir): array
{
    if ($kasIds === []) {
        return ['masuk' => 0.0, 'keluar' => 0.0, 'by_jenis' => []];
    }
    $ph = implode(',', array_fill(0, count($kasIds), '?'));
    $sql = "
        SELECT
            t.jenis,
            COALESCE(SUM(CASE WHEN t.id_akun_debit IN ($ph) THEN t.total ELSE 0 END), 0) AS masuk_kas,
            COALESCE(SUM(CASE WHEN t.id_akun_kredit IN ($ph) THEN t.total ELSE 0 END), 0) AS keluar_kas
        FROM transaksi t
        WHERE t.id_perusahaan = ?
          AND t.tanggal BETWEEN ? AND ?
          AND (t.id_akun_debit IN ($ph) OR t.id_akun_kredit IN ($ph))
        GROUP BY t.jenis
    ";
    $params = array_merge($kasIds, $kasIds, [$id_perusahaan, $tanggal_awal, $tanggal_akhir], $kasIds, $kasIds);

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $masuk = 0.0;
    $keluar = 0.0;
    $by_jenis = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $m = (float) $row['masuk_kas'];
        $k = (float) $row['keluar_kas'];
        $masuk += $m;
        $keluar += $k;
        if ($m > 0 || $k > 0) {
            $by_jenis[] = [
                'jenis' => $row['jenis'],
                'label' => bumnuJenisLabel($row['jenis']),
                'masuk' => $m,
                'keluar' => $k,
            ];
        }
    }

    usort($by_jenis, static function ($a, $b) {
        return ($b['masuk'] + $b['keluar']) <=> ($a['masuk'] + $a['keluar']);
    });

    return ['masuk' => $masuk, 'keluar' => $keluar, 'by_jenis' => $by_jenis];
}

function bumnuKasTransaksiList(PDO $db, array $kasIds, int $id_perusahaan, string $tanggal_awal, string $tanggal_akhir, int $limit): array
{
    if ($kasIds === []) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($kasIds), '?'));
    $params = array_merge($kasIds, [$id_perusahaan, $tanggal_awal, $tanggal_akhir], $kasIds, $kasIds, [$limit]);

    $sql = "
        SELECT t.tanggal, t.keterangan, t.jenis, t.total,
            CASE WHEN t.id_akun_debit IN ($ph) THEN 'masuk' ELSE 'keluar' END AS arah
        FROM transaksi t
        WHERE t.id_perusahaan = ?
          AND t.tanggal BETWEEN ? AND ?
          AND (t.id_akun_debit IN ($ph) OR t.id_akun_kredit IN ($ph))
        ORDER BY t.tanggal DESC, t.id DESC
        LIMIT ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['jenis_label'] = bumnuJenisLabel($row['jenis']);
        $row['total'] = (float) $row['total'];
    }
    unset($row);
    return $rows;
}

function bumnuFormatBulanIndonesia(string $yyyy_mm): string
{
    $parts = explode('-', $yyyy_mm);
    if (count($parts) !== 2) {
        return $yyyy_mm;
    }
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $m = (int) $parts[1];
    $y = $parts[0];
    return ($bulan[$m] ?? $parts[1]) . ' ' . $y;
}

function bumnuFormatTanggalIndonesia(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }
    $bulan = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];
    return (int) date('j', $ts) . ' ' . ($bulan[(int) date('n', $ts)] ?? '') . ' ' . date('Y', $ts);
}
