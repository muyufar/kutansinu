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
    'page_title' => 'Laporan Kas BUMNU',
    'org_line' => 'PCNU Kabupaten Magelang',
    'footnote' => 'Angka dihitung dari pencatatan transaksi resmi. Untuk rincian teknis lengkap, hubungi pengurus BUMNU.',
];
