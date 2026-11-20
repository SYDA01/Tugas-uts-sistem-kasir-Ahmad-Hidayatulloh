-- =====================================================
-- BPHOMES - Database SQL
-- Import lewat phpMyAdmin (tab Import) atau jalankan di MySQL.
-- Catatan: aplikasi bphomes.php juga membuat semua ini otomatis,
-- file ini berguna untuk instalasi manual / backup struktur.
-- =====================================================

CREATE DATABASE IF NOT EXISTS bphomes CHARACTER SET utf8mb4;
USE bphomes;

-- Akun login
CREATE TABLE IF NOT EXISTS pengguna (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(40)  NOT NULL UNIQUE,
  nama          VARCHAR(80)  NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  dibuat        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Daftar satuan (pcs, kg, dus, dst)
CREATE TABLE IF NOT EXISTS satuan (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Pengaturan aplikasi (nama toko, dll)
CREATE TABLE IF NOT EXISTS pengaturan (
  k VARCHAR(40)  PRIMARY KEY,
  v VARCHAR(200) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

-- Master barang
CREATE TABLE IF NOT EXISTS barang (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  kode   VARCHAR(30)  NOT NULL UNIQUE,
  nama   VARCHAR(120) NOT NULL,
  satuan VARCHAR(20)  NOT NULL DEFAULT 'pcs',
  stok   INT          NOT NULL DEFAULT 0,
  modal  DECIMAL(15,4) NOT NULL DEFAULT 0   -- modal rata-rata per satuan
) ENGINE=InnoDB;

-- Transaksi barang masuk (pembelian) & keluar (penjualan)
CREATE TABLE IF NOT EXISTS transaksi (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  barang_id INT NOT NULL,
  tipe      ENUM('masuk','keluar') NOT NULL,
  tanggal   DATE NOT NULL,
  qty       INT NOT NULL,
  harga     DECIMAL(15,2) NOT NULL,          -- harga beli (masuk) / harga jual (keluar)
  hpp       DECIMAL(15,4) NOT NULL DEFAULT 0, -- modal per satuan saat terjual (dihitung otomatis)
  catatan   VARCHAR(200) NOT NULL DEFAULT '',
  dibuat    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (barang_id, tanggal),
  INDEX (tipe, tanggal),
  FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------- Data awal ----------
-- Akun awal: username BPHOMES, password BP2026 (tersimpan sebagai hash bcrypt)
INSERT INTO pengguna (username, nama, password_hash)
SELECT 'BPHOMES', 'Admin BPHOMES', '$2y$10$9AYeQPaPuFZJQK7f7uB1XeUkokBtEMsOrDS5PtzebxyFQECLMvNqO'
WHERE NOT EXISTS (SELECT 1 FROM pengguna);

INSERT IGNORE INTO satuan (nama) VALUES
  ('pcs'),('kg'),('gram'),('liter'),('dus'),('pak'),('lusin'),('botol'),('sachet'),('box');

INSERT IGNORE INTO pengaturan (k, v) VALUES ('nama_toko', 'BPHOMES');
