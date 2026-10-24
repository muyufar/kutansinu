<?php

/**
 * Pengaturan laporan publik (tanpa login).
 * access_token kosong = halaman bisa dibuka siapa saja yang punya URL.
 * Isi token (atau env BUMNU_PUBLIC_REPORT_TOKEN) jika ingin URL ?token=...
 */
return [
    'bumnu_company_names' => ['KAS BUMNU'],
    'access_token' => getenv('BUMNU_PUBLIC_REPORT_TOKEN') ?: '',
    'max_transaksi_rows' => 120,
    /** Batas saat preset "Semua data" (riwayat lengkap periode) */
    'max_transaksi_rows_semua' => 500,
    'page_title' => 'Laporan Kas BUMNU',
    'org_line' => 'PCNU Kabupaten Magelang',
    'footnote' => 'Angka dihitung dari pencatatan transaksi resmi. Untuk rincian teknis lengkap, hubungi pengurus BUMNU.',

    /**
     * Laporan MBG (format ringkas untuk pengurus).
     * Pemetaan berdasarkan tag transaksi kas BUMNU.
     */
    'mbg_report' => [
        'title' => 'Laporan Kas BUMNU (MBG)',
        'subtitle' => 'Ringkasan pemasukan dapur, operasional, laba bersih, dan tasyaruf',
        /** Uang masuk ke kas dengan tag ini dijumlahkan sebagai Pemasukan */
        'pemasukan_tags' => ['PENDAPATAN DAPUR'],
        /** Opsional: pemasukan lain (mis. sewa) — ditambah ke laba */
        'pemasukan_lain_tags' => [],
        /**
         * Pengeluaran operasional = uang keluar kas periode ini, kecuali tag/jenis di bawah.
         * (Gaji dapur, fee koperasi, infrastruktur, dll.)
         */
        'operasional_exclude_tags' => [
            'BASIL LP MAARIF',
            'BASIL PCNU DAN BUMNU',
            'BASIL PCNU',
            'BASIL BUMNU',
        ],
        'operasional_exclude_jenis' => [
            'tarik_modal',
            'tanam_modal',
            'transfer_hutang',
            'transfer_uang',
            'hutang',
            'piutang',
            'pemasukan_piutang',
        ],
        'tasyaruf_tags' => [
            'pcnu' => ['BASIL PCNU'],
            'lp_maarif' => ['BASIL LP MAARIF'],
            'bumnu' => ['BASIL BUMNU'],
            'penerima_manfaat' => [],
            'cadangan' => [],
        ],
        /** Pembagian tag gabungan BASIL PCNU DAN BUMNU (harus total 1.0) */
        'split_pcnu_bumnu_gabungan' => [
            'pcnu' => 0.5,
            'bumnu' => 0.5,
        ],
        'tags_pcnu_bumnu_gabungan' => ['BASIL PCNU DAN BUMNU'],
        /** Jika true: baris Cadangan = laba bersih − tasyaruf PCNU/LP/BUMNU/Penerima (min 0) */
        'cadangan_is_sisa_laba' => true,
    ],
];
