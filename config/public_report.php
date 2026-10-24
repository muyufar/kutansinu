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
        /** Uang masuk ke kas dengan tag ini dijumlahkan sebagai Pemasukan (dapur + sewa mobil) */
        'pemasukan_tags' => ['PENDAPATAN DAPUR', 'PENDAPATAN SEWA MOBIL'],
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
        /** Akad pembagian bagi hasil (% dari laba bersih per bulan) */
        'bagi_hasil_shares' => [
            'lp_maarif' => 0.20,
            'pcnu' => 0.20,
            'bumnu' => 0.20,
            'penerima_manfaat' => 0.20,
            'cadangan' => 0.20,
        ],
        'tasyaruf_tags' => [
            'lp_maarif' => ['BASIL LP MAARIF'],
            'pcnu' => ['BASIL PCNU'],
            'bumnu' => ['BASIL BUMNU'],
            'penerima_manfaat' => [],
            'cadangan' => [],
        ],
        /** Realisasi transfer gabungan PCNU+BUMNU (untuk tampilan per baris: dibagi 50–50) */
        'split_pcnu_bumnu_gabungan' => [
            'pcnu' => 0.5,
            'bumnu' => 0.5,
        ],
        'tags_pcnu_bumnu_gabungan' => ['BASIL PCNU DAN BUMNU'],
        /** Pengurangan dari kuota gabungan PCNU+BUMNU saat rencana transfer (branding mobil) */
        'pcnu_bumnu_branding_deduct' => 6000000,
        'pcnu_bumnu_gabungan_note' => 'Transfer PCNU dan BUMNU digabung satu kali; setelah alokasi branding mobil Rp 6 jt, nominal dibulatkan (mis. Rp 50 jt).',
        /**
         * Penerima manfaat & cadangan: jika belum ada transaksi, tampilkan acuan 20%
         * (mengikuti nominal tasyaruf LP Ma\'arif bulan itu bila sudah dibayar).
         */
        'tasyaruf_tampilan_belum_dari_lp' => ['penerima_manfaat', 'cadangan'],
    ],
];
