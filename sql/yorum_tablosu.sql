-- =====================================================
-- E-Spor Turnuva Platformu - Yorumlar Tablosu Göçü
-- =====================================================

CREATE TABLE IF NOT EXISTS haber_yorumlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    news_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (news_id) REFERENCES haberler(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
