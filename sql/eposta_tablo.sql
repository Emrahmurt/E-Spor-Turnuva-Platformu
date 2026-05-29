-- E-posta Log Tablosu
CREATE TABLE IF NOT EXISTS `eposta_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `alici_email` VARCHAR(255) NOT NULL,
    `konu` VARCHAR(500) NOT NULL,
    `tip` VARCHAR(50) DEFAULT 'bildirim',
    `gonderim_tarihi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `basarili` TINYINT(1) DEFAULT 1,
    INDEX `idx_alici` (`alici_email`),
    INDEX `idx_tip` (`tip`),
    INDEX `idx_tarih` (`gonderim_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kullanıcı e-posta bildirim tercihleri
ALTER TABLE `kullanicilar` 
    ADD COLUMN IF NOT EXISTS `eposta_bildirim` TINYINT(1) DEFAULT 1 COMMENT 'Genel e-posta bildirimi açık/kapalı',
    ADD COLUMN IF NOT EXISTS `eposta_turnuva` TINYINT(1) DEFAULT 1 COMMENT 'Turnuva bildirimleri',
    ADD COLUMN IF NOT EXISTS `eposta_mac` TINYINT(1) DEFAULT 1 COMMENT 'Maç hatırlatmaları',
    ADD COLUMN IF NOT EXISTS `eposta_takim` TINYINT(1) DEFAULT 1 COMMENT 'Takım bildirimleri';
