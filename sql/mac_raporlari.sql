-- Maçlar tablosuna itiraz durumunu ekle
ALTER TABLE maclar ADD COLUMN dispute_status ENUM('none', 'pending', 'resolved') DEFAULT 'none' AFTER status;

-- Kaptanların bildirdiği skor ve kanıtları tutacak tablo
CREATE TABLE IF NOT EXISTS mac_raporlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    reporter_id INT NOT NULL,              -- Raporu gönderen kaptanın ID'si
    team_id INT NOT NULL,                  -- Raporu gönderen takımın ID'si
    score1 INT NOT NULL,                   -- Raporlanan Takım 1 skoru
    score2 INT NOT NULL,                   -- Raporlanan Takım 2 skoru
    proof_image VARCHAR(255) NOT NULL,     -- Kanıt ekran görüntüsü dosya adı
    notes TEXT DEFAULT NULL,               -- Kaptanın ek açıklamaları
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES maclar(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES takimlar(id) ON DELETE CASCADE,
    UNIQUE KEY unique_match_reporter (match_id, reporter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
