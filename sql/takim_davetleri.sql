-- E-Spor Turnuva Platformu - Takım Davet ve Başvuru Tablosu

CREATE TABLE IF NOT EXISTS `takim_davetleri` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `sender_id` INT NOT NULL,
    `type` ENUM('invitation', 'request') NOT NULL,
    `status` ENUM('pending', 'accepted', 'rejected', 'cancelled') DEFAULT 'pending',
    `message` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `takimlar`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `kullanicilar`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `kullanicilar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
