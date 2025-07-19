-- Tabel transaksi yang diperbaiki
CREATE TABLE IF NOT EXISTS transaksi (
    id_transaksi INT PRIMARY KEY AUTO_INCREMENT,
    tipe ENUM('pemasukan', 'pengeluaran') NOT NULL,
    kategori VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    jumlah DECIMAL(15,2) NOT NULL,
    tanggal DATE NOT NULL DEFAULT CURRENT_DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel anggaran baru
CREATE TABLE IF NOT EXISTS anggaran (
    id_anggaran INT PRIMARY KEY AUTO_INCREMENT,
    kategori VARCHAR(100) NOT NULL,
    jumlah_anggaran DECIMAL(15,2) NOT NULL,
    periode ENUM('bulanan', 'tahunan') NOT NULL,
    tahun INT NOT NULL,
    bulan INT NULL, -- NULL untuk anggaran tahunan
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_anggaran (kategori, periode, tahun, bulan)
);

-- Tabel laporan (untuk cache hasil laporan)
CREATE TABLE IF NOT EXISTS laporan (
    id_laporan INT PRIMARY KEY AUTO_INCREMENT,
    periode VARCHAR(50) NOT NULL,
    tahun INT NOT NULL,
    bulan INT NULL,
    total_pemasukan DECIMAL(15,2) DEFAULT 0,
    total_pengeluaran DECIMAL(15,2) DEFAULT 0,
    saldo DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index untuk performa
CREATE INDEX idx_transaksi_tanggal ON transaksi(tanggal);
CREATE INDEX idx_transaksi_kategori ON transaksi(kategori);
CREATE INDEX idx_transaksi_tipe ON transaksi(tipe);