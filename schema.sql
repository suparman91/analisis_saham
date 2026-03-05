-- Database schema for Analisis Saham (IHSG)
CREATE DATABASE IF NOT EXISTS analisis_saham CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE analisis_saham;

CREATE TABLE IF NOT EXISTS stocks (
  symbol VARCHAR(32) PRIMARY KEY,
  name VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS prices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  symbol VARCHAR(32) NOT NULL,
  date DATE NOT NULL,
  open DOUBLE NOT NULL,
  high DOUBLE NOT NULL,
  low DOUBLE NOT NULL,
  close DOUBLE NOT NULL,
  volume BIGINT DEFAULT 0,
  UNIQUE KEY uniq_symbol_date (symbol, date),
  INDEX idx_symbol_date (symbol, date),
  FOREIGN KEY (symbol) REFERENCES stocks(symbol) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS fundamentals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  symbol VARCHAR(32) NOT NULL,
  date DATE NOT NULL,
  pe DOUBLE DEFAULT NULL,
  pbv DOUBLE DEFAULT NULL,
  roe DOUBLE DEFAULT NULL,
  eps DOUBLE DEFAULT NULL,
  UNIQUE KEY uniq_fundamental (symbol, date),
  FOREIGN KEY (symbol) REFERENCES stocks(symbol) ON DELETE CASCADE
);

-- Sample seeds (replace with real IHSG symbols and data)
INSERT IGNORE INTO stocks (symbol, name) VALUES
('BBCA', 'Bank Central Asia Tbk'),
('TLKM', 'Telkom Indonesia Tbk');

-- Sample prices for BBCA (10 days) - used for demo only
INSERT IGNORE INTO prices (symbol, date, open, high, low, close, volume) VALUES
('BBCA','2026-02-02',8300,8500,8200,8450,1200000),
('BBCA','2026-02-03',8450,8600,8400,8550,1100000),
('BBCA','2026-02-04',8550,8700,8500,8650,1300000),
('BBCA','2026-02-05',8650,8800,8600,8750,1000000),
('BBCA','2026-02-06',8750,8900,8700,8850,900000),
('BBCA','2026-02-09',8850,9000,8800,8950,950000),
('BBCA','2026-02-10',8950,9100,8900,9050,980000),
('BBCA','2026-02-11',9050,9200,9000,9150,1020000),
('BBCA','2026-02-12',9150,9300,9100,9250,1070000),
('BBCA','2026-02-13',9250,9400,9200,9350,990000);

-- Sample fundamentals
INSERT IGNORE INTO fundamentals (symbol, date, pe, pbv, roe, eps) VALUES
('BBCA','2026-01-01',14.5,2.8,18.2,560),
('TLKM','2026-01-01',18.2,3.1,12.5,230);
