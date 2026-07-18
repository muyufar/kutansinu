# Dokumentasi Teknis Sistem Keuangan

## Arsitektur Sistem

### Teknologi yang Digunakan

- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend**: PHP 8.x
- **Database**: MySQL 8.x
- **Web Server**: Apache 2.x
- **Framework**: Custom MVC Framework

### Struktur Direktori

```
/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── config/
│   ├── database.php
│   └── config.php
├── controllers/
├── models/
├── views/
├── includes/
└── docs/
```

## Struktur Database

### Tabel Users

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'editor', 'viewer', 'approver') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Tabel Transactions

```sql
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('income', 'expense') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    category_id INT NOT NULL,
    description TEXT,
    status ENUM('pending', 'approved', 'rejected') NOT NULL,
    created_by INT NOT NULL,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);
```

### Tabel Categories

```sql
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabel Audit Log

```sql
CREATE TABLE audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    module VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

## API Endpoints

### Autentikasi

- `POST /api/auth/login`
- `POST /api/auth/logout`
- `POST /api/auth/refresh-token`

### Transaksi

- `GET /api/transactions`
- `POST /api/transactions`
- `PUT /api/transactions/{id}`
- `DELETE /api/transactions/{id}`
- `POST /api/transactions/{id}/approve`
- `POST /api/transactions/{id}/reject`

### Pengguna

- `GET /api/users`
- `POST /api/users`
- `PUT /api/users/{id}`
- `DELETE /api/users/{id}`

### Laporan

- `GET /api/reports/daily`
- `GET /api/reports/monthly`
- `GET /api/reports/custom`

## Keamanan

### Enkripsi

- Password di-hash menggunakan bcrypt
- Data sensitif dienkripsi menggunakan AES-256

### Autentikasi

- JWT (JSON Web Token) untuk autentikasi API
- Session-based authentication untuk web interface
- Rate limiting untuk mencegah brute force

### Validasi Input

- Sanitasi input untuk mencegah XSS
- Prepared statements untuk mencegah SQL injection
- Validasi file upload

## Backup dan Recovery

### Backup Otomatis

- Backup database harian
- Backup file sistem mingguan
- Penyimpanan backup di lokasi terpisah

### Prosedur Recovery

1. Restore database dari backup
2. Verifikasi integritas data
3. Update sistem jika diperlukan
4. Test fungsionalitas

## Monitoring dan Logging

### Log Sistem

- Error log
- Access log
- Security log
- Performance log

### Monitoring

- CPU usage
- Memory usage
- Disk space
- Network traffic
- Response time

## Deployment

### Persyaratan Sistem

- PHP 8.x atau lebih baru
- MySQL 8.x atau lebih baru
- Apache 2.x dengan mod_rewrite
- SSL certificate untuk HTTPS

### Prosedur Deployment

1. Clone repository
2. Install dependencies
3. Konfigurasi environment
4. Import database
5. Set permissions
6. Test sistem

## Troubleshooting

### Common Issues

1. **Database Connection Error**

   - Periksa konfigurasi database
   - Verifikasi kredensial
   - Cek status MySQL service

2. **Upload File Error**

   - Periksa permission direktori
   - Verifikasi ukuran file
   - Cek konfigurasi PHP

3. **Performance Issues**
   - Optimize queries
   - Enable caching
   - Check server resources

## Maintenance

### Regular Tasks

- Update sistem
- Optimize database
- Clear cache
- Check logs
- Backup verification

### Security Updates

- Monitor security advisories
- Apply patches
- Update dependencies
- Security audit

---

_Dokumen ini akan diperbarui secara berkala. Versi terakhir: 1.0_
