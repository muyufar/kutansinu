<?php

/** Fallback jika functions.php di server belum terbaru (login bisa jalan, laporan publik butuh ini). */
if (!function_exists('hitungMutasiAkun')) {
    function hitungMutasiAkun($total_debit, $total_kredit, $tipe_akun)
    {
        $total_debit = (float) $total_debit;
        $total_kredit = (float) $total_kredit;
        if ($tipe_akun === 'debit') {
            return $total_debit - $total_kredit;
        }
        return $total_kredit - $total_debit;
    }
}

if (!function_exists('getSaldoAkunSampaiTanggal')) {
    function getSaldoAkunSampaiTanggal($db, $id_akun, $tanggal_akhir, $id_perusahaan, $tipe_akun)
    {
        $stmt = $db->prepare('
            SELECT
                COALESCE(SUM(CASE WHEN t.id_akun_debit = ? THEN t.jumlah ELSE 0 END), 0) AS total_debit,
                COALESCE(SUM(CASE WHEN t.id_akun_kredit = ? THEN t.jumlah ELSE 0 END), 0) AS total_kredit
            FROM transaksi t
            WHERE t.id_perusahaan = ?
              AND t.tanggal <= ?
              AND (t.id_akun_debit = ? OR t.id_akun_kredit = ?)
        ');
        $stmt->execute([$id_akun, $id_akun, $id_perusahaan, $tanggal_akhir, $id_akun, $id_akun]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return hitungMutasiAkun($row['total_debit'], $row['total_kredit'], $tipe_akun);
    }
}

if (!function_exists('formatRupiah')) {
    function formatRupiah($angka)
    {
        return 'Rp ' . number_format((float) $angka, 0, ',', '.');
    }
}

function bumnuPublicConfig(): array
{
    static $config = null;
    if ($config === null) {
        $path = __DIR__ . '/../config/public_report.php';
        $head = (string) file_get_contents($path, false, null, 0, 32);
        if (strpos($head, '<?php') !== 0) {
            throw new RuntimeException('config/public_report.php rusak: baris pertama harus <?php');
        }
        $config = require $path;
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

function bumnuKasTransaksiCount(PDO $db, array $kasIds, int $id_perusahaan, string $tanggal_awal, string $tanggal_akhir): int
{
    if ($kasIds === []) {
        return 0;
    }
    $ph = implode(',', array_fill(0, count($kasIds), '?'));
    $params = array_merge([$id_perusahaan, $tanggal_awal, $tanggal_akhir], $kasIds, $kasIds);
    $sql = "
        SELECT COUNT(*) FROM transaksi t
        WHERE t.id_perusahaan = ?
          AND t.tanggal BETWEEN ? AND ?
          AND (t.id_akun_debit IN ($ph) OR t.id_akun_kredit IN ($ph))
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function bumnuPublicTransaksiFetchLimit(array $config, array $periode, int $txCount): array
{
    $normal = max(1, (int) ($config['max_transaksi_rows'] ?? 120));
    $fullCap = max($normal, (int) ($config['max_transaksi_rows_semua'] ?? 500));

    $preset = $periode['form']['preset'] ?? '';
    $fullList = $periode['mode'] === 'preset' && $preset === 'semua';

    $limit = $fullList
        ? min($fullCap, max($txCount, 1))
        : min($normal, max($txCount, 1));

    return ['limit' => $limit, 'order' => 'desc', 'full_list' => $fullList, 'cap' => $fullCap];
}

function bumnuKasTransaksiList(
    PDO $db,
    array $kasIds,
    int $id_perusahaan,
    string $tanggal_awal,
    string $tanggal_akhir,
    int $limit,
    string $order = 'desc'
): array {
    if ($kasIds === []) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($kasIds), '?'));
    $limit = max(1, min(2000, (int) $limit));
    $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
    $params = array_merge($kasIds, [$id_perusahaan, $tanggal_awal, $tanggal_akhir], $kasIds, $kasIds);

    $sql = "
        SELECT t.tanggal, t.keterangan, t.jenis, t.total,
            CASE WHEN t.id_akun_debit IN ($ph) THEN 'masuk' ELSE 'keluar' END AS arah
        FROM transaksi t
        WHERE t.id_perusahaan = ?
          AND t.tanggal BETWEEN ? AND ?
          AND (t.id_akun_debit IN ($ph) OR t.id_akun_kredit IN ($ph))
        ORDER BY t.tanggal {$order}, t.id {$order}
        LIMIT {$limit}
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

/**
 * @return array{
 *   mode: string,
 *   tanggal_awal: string,
 *   tanggal_akhir: string,
 *   label: string,
 *   hari_sebelum_awal: string,
 *   form: array<string, string>
 * }
 */
function bumnuResolvePeriode(array $query): array
{
    $today = date('Y-m-d');
    $mode = isset($query['mode']) ? trim((string) $query['mode']) : 'bulan';
    $allowedModes = ['bulan', 'triwulan', 'semester', 'tahun', 'rentang', 'preset'];
    if (!in_array($mode, $allowedModes, true)) {
        $mode = 'bulan';
    }

    $form = ['mode' => $mode];
    $tanggal_awal = date('Y-m-01');
    $tanggal_akhir = $today;
    $label = '';

    $clampEnd = static function (string $end) use ($today): string {
        return $end > $today ? $today : $end;
    };

    $validDate = static function (string $d): bool {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
    };

    if ($mode === 'preset') {
        $preset = isset($query['preset']) ? trim((string) $query['preset']) : 'bulan_ini';
        $form['preset'] = $preset;
        switch ($preset) {
            case 'bulan_lalu':
                $tanggal_awal = date('Y-m-01', strtotime('first day of last month'));
                $tanggal_akhir = date('Y-m-t', strtotime('last day of last month'));
                $label = 'Bulan lalu (' . bumnuFormatBulanIndonesia(date('Y-m', strtotime($tanggal_awal))) . ')';
                break;
            case 'kuartal_ini':
                $m = (int) date('n');
                $qStartMonth = (int) (floor(($m - 1) / 3) * 3 + 1);
                $tanggal_awal = sprintf('%d-%02d-01', (int) date('Y'), $qStartMonth);
                $tanggal_akhir = $clampEnd($today);
                $qNum = (int) ceil($m / 3);
                $label = 'Triwulan ' . $qNum . ' ' . date('Y') . ' (s/d ' . bumnuFormatTanggalIndonesia($tanggal_akhir) . ')';
                break;
            case 'tahun_ini':
                $tanggal_awal = date('Y') . '-01-01';
                $tanggal_akhir = $clampEnd($today);
                $label = 'Tahun ' . date('Y') . ' (s/d ' . bumnuFormatTanggalIndonesia($tanggal_akhir) . ')';
                break;
            case '12_bulan':
                $tanggal_awal = date('Y-m-d', strtotime('-11 months', strtotime(date('Y-m-01'))));
                $tanggal_akhir = $clampEnd($today);
                $label = bumnuFormatTanggalIndonesia($tanggal_awal) . ' – ' . bumnuFormatTanggalIndonesia($tanggal_akhir);
                break;
            case 'semua':
                $tanggal_awal = '2020-01-01';
                $tanggal_akhir = $clampEnd($today);
                $label = 'Semua pencatatan (s/d ' . bumnuFormatTanggalIndonesia($tanggal_akhir) . ')';
                break;
            case 'bulan_ini':
            default:
                $form['preset'] = 'bulan_ini';
                $tanggal_awal = date('Y-m-01');
                $tanggal_akhir = $clampEnd($today);
                $label = bumnuFormatBulanIndonesia(date('Y-m')) . ' (s/d ' . bumnuFormatTanggalIndonesia($tanggal_akhir) . ')';
                break;
        }
    } elseif ($mode === 'triwulan') {
        $tahun = isset($query['tahun']) ? (int) $query['tahun'] : (int) date('Y');
        if ($tahun < 2020 || $tahun > (int) date('Y') + 1) {
            $tahun = (int) date('Y');
        }
        $triwulan = isset($query['triwulan']) ? (int) $query['triwulan'] : (int) ceil((int) date('n') / 3);
        if ($triwulan < 1 || $triwulan > 4) {
            $triwulan = 1;
        }
        $startMonth = ($triwulan - 1) * 3 + 1;
        $tanggal_awal = sprintf('%04d-%02d-01', $tahun, $startMonth);
        $endMonth = $startMonth + 2;
        $tanggal_akhir = $clampEnd(date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $tahun, $endMonth))));
        $form['tahun'] = (string) $tahun;
        $form['triwulan'] = (string) $triwulan;
        $label = 'Triwulan ' . $triwulan . ' ' . $tahun;
    } elseif ($mode === 'semester') {
        $tahun = isset($query['tahun']) ? (int) $query['tahun'] : (int) date('Y');
        if ($tahun < 2020 || $tahun > (int) date('Y') + 1) {
            $tahun = (int) date('Y');
        }
        $semester = isset($query['semester']) ? (int) $query['semester'] : (((int) date('n') <= 6) ? 1 : 2);
        if ($semester !== 1 && $semester !== 2) {
            $semester = 1;
        }
        $tanggal_awal = $semester === 1 ? sprintf('%04d-01-01', $tahun) : sprintf('%04d-07-01', $tahun);
        $tanggal_akhir = $clampEnd(
            $semester === 1 ? sprintf('%04d-06-30', $tahun) : sprintf('%04d-12-31', $tahun)
        );
        $form['tahun'] = (string) $tahun;
        $form['semester'] = (string) $semester;
        $label = 'Semester ' . $semester . ' ' . $tahun;
    } elseif ($mode === 'tahun') {
        $tahun = isset($query['tahun']) ? (int) $query['tahun'] : (int) date('Y');
        if ($tahun < 2020 || $tahun > (int) date('Y') + 1) {
            $tahun = (int) date('Y');
        }
        $tanggal_awal = sprintf('%04d-01-01', $tahun);
        $tanggal_akhir = $clampEnd(sprintf('%04d-12-31', $tahun));
        $form['tahun'] = (string) $tahun;
        $label = 'Tahun ' . $tahun;
    } elseif ($mode === 'rentang') {
        $dari = isset($query['dari']) ? trim((string) $query['dari']) : date('Y-m-01');
        $sampai = isset($query['sampai']) ? trim((string) $query['sampai']) : $today;
        if (!$validDate($dari)) {
            $dari = date('Y-m-01');
        }
        if (!$validDate($sampai)) {
            $sampai = $today;
        }
        if ($dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        $tanggal_awal = $dari;
        $tanggal_akhir = $clampEnd($sampai);
        $form['dari'] = $dari;
        $form['sampai'] = $sampai;
        $label = bumnuFormatTanggalIndonesia($tanggal_awal) . ' – ' . bumnuFormatTanggalIndonesia($tanggal_akhir);
    } else {
        $bulan = isset($query['bulan']) ? trim((string) $query['bulan']) : date('Y-m');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            $bulan = date('Y-m');
        }
        $form['bulan'] = $bulan;
        $tanggal_awal = $bulan . '-01';
        $endMonth = date('Y-m-t', strtotime($tanggal_awal));
        $tanggal_akhir = $clampEnd($endMonth);
        $label = bumnuFormatBulanIndonesia($bulan);
        if ($tanggal_akhir < $endMonth) {
            $label .= ' (s/d ' . bumnuFormatTanggalIndonesia($tanggal_akhir) . ')';
        }
    }

    $hari_sebelum_awal = date('Y-m-d', strtotime($tanggal_awal . ' -1 day'));

    return [
        'mode' => $mode,
        'tanggal_awal' => $tanggal_awal,
        'tanggal_akhir' => $tanggal_akhir,
        'label' => $label,
        'hari_sebelum_awal' => $hari_sebelum_awal,
        'form' => $form,
    ];
}

function bumnuPublicQueryString(array $params, string $token = ''): string
{
    if ($token !== '') {
        $params['token'] = $token;
    }
    return http_build_query($params);
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
