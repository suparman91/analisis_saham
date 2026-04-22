-- Tabel audit log eksekusi robot AI
CREATE TABLE IF NOT EXISTS robo_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    symbol VARCHAR(20),
    action VARCHAR(20), -- BUY, SEROK, SELL, SKIP, dsb
    price DECIMAL(12,2),
    lots INT,
    reason VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);