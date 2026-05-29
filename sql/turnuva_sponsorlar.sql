-- Sponsorların tanımlandığı ana tablo
CREATE TABLE IF NOT EXISTS sponsorlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    logo VARCHAR(255) NOT NULL,            -- Sponsor logosu görsel adı
    link VARCHAR(255) DEFAULT NULL,        -- Web sitesi adresi
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Turnuva ile sponsorları eşleştiren ve sponsorun yaptığı ödül katkısını tutan tablo
CREATE TABLE IF NOT EXISTS turnuva_sponsorlari (
    tournament_id INT NOT NULL,
    sponsor_id INT NOT NULL,
    prize_contribution DECIMAL(10,2) DEFAULT 0.00,
    PRIMARY KEY (tournament_id, sponsor_id),
    FOREIGN KEY (tournament_id) REFERENCES turnuvalar(id) ON DELETE CASCADE,
    FOREIGN KEY (sponsor_id) REFERENCES sponsorlar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
