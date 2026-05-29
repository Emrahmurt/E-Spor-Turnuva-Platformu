-- =====================================================
-- E-Spor Turnuva Platformu - Veritabanı Şeması
-- =====================================================


-- =====================================================
-- 1. OYUNLAR
-- =====================================================
CREATE TABLE oyunlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(255) DEFAULT NULL,
    banner_image VARCHAR(255) DEFAULT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 2. KULLANICILAR
-- =====================================================
CREATE TABLE kullanicilar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    role ENUM('admin', 'user', 'player') DEFAULT 'user',
    first_name VARCHAR(50) DEFAULT NULL,
    last_name VARCHAR(50) DEFAULT NULL,
    bio TEXT,
    country VARCHAR(50) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    discord VARCHAR(100) DEFAULT NULL,
    steam_id VARCHAR(100) DEFAULT NULL,
    total_wins INT DEFAULT 0,
    total_losses INT DEFAULT 0,
    points INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 3. TAKIMLAR
-- =====================================================
CREATE TABLE takimlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    logo VARCHAR(255) DEFAULT 'default-team.png',
    banner_image VARCHAR(255) DEFAULT NULL,
    game_id INT NOT NULL,
    description TEXT,
    captain_id INT NOT NULL,
    country VARCHAR(50) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    discord VARCHAR(255) DEFAULT NULL,
    total_wins INT DEFAULT 0,
    total_losses INT DEFAULT 0,
    points INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES oyunlar(id) ON DELETE CASCADE,
    FOREIGN KEY (captain_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 4. TAKIM ÜYELERİ
-- =====================================================
CREATE TABLE takim_uyeleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('captain', 'player', 'substitute', 'coach') DEFAULT 'player',
    jersey_number INT DEFAULT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_team_user (team_id, user_id),
    FOREIGN KEY (team_id) REFERENCES takimlar(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 5. TURNUVALAR
-- =====================================================
CREATE TABLE turnuvalar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    game_id INT NOT NULL,
    description TEXT,
    rules TEXT,
    banner_image VARCHAR(255) DEFAULT NULL,
    format ENUM('single_elimination', 'double_elimination', 'round_robin', 'swiss', 'league') DEFAULT 'single_elimination',
    team_size INT DEFAULT 5,
    max_teams INT DEFAULT 16,
    min_teams INT DEFAULT 4,
    prize_pool DECIMAL(10,2) DEFAULT 0.00,
    prize_distribution TEXT COMMENT 'JSON: {"1st":"50%","2nd":"30%","3rd":"20%"}',
    entry_fee DECIMAL(10,2) DEFAULT 0.00,
    start_date DATETIME NOT NULL,
    end_date DATETIME DEFAULT NULL,
    registration_start DATETIME DEFAULT NULL,
    registration_end DATETIME DEFAULT NULL,
    status ENUM('draft', 'upcoming', 'registration', 'ongoing', 'completed', 'cancelled') DEFAULT 'draft',
    is_featured TINYINT(1) DEFAULT 0,
    stream_url VARCHAR(255) DEFAULT NULL,
    contact_email VARCHAR(100) DEFAULT NULL,
    created_by INT NOT NULL,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES oyunlar(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES kullanicilar(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 6. TURNUVA KAYITLARI
-- =====================================================
CREATE TABLE turnuva_kayitlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    team_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'withdrawn') DEFAULT 'pending',
    notes TEXT,
    seed INT DEFAULT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT DEFAULT NULL,
    UNIQUE KEY unique_tournament_team (tournament_id, team_id),
    FOREIGN KEY (tournament_id) REFERENCES turnuvalar(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES takimlar(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES kullanicilar(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 7. MAÇLAR
-- =====================================================
CREATE TABLE maclar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    round INT NOT NULL DEFAULT 1,
    match_number INT NOT NULL DEFAULT 1,
    bracket_position VARCHAR(20) DEFAULT NULL COMMENT 'e.g., W1, L1 for winners/losers bracket',
    team1_id INT DEFAULT NULL,
    team2_id INT DEFAULT NULL,
    score1 INT DEFAULT 0,
    score2 INT DEFAULT 0,
    winner_id INT DEFAULT NULL,
    map_name VARCHAR(100) DEFAULT NULL,
    best_of INT DEFAULT 1 COMMENT 'BO1, BO3, BO5',
    status ENUM('scheduled', 'live', 'completed', 'cancelled', 'postponed') DEFAULT 'scheduled',
    scheduled_at DATETIME DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    stream_url VARCHAR(255) DEFAULT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES turnuvalar(id) ON DELETE CASCADE,
    FOREIGN KEY (team1_id) REFERENCES takimlar(id) ON DELETE SET NULL,
    FOREIGN KEY (team2_id) REFERENCES takimlar(id) ON DELETE SET NULL,
    FOREIGN KEY (winner_id) REFERENCES takimlar(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 8. MAÇ ROUNDLARI (BO3/BO5 detay)
-- =====================================================
CREATE TABLE mac_roundlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    round_number INT NOT NULL DEFAULT 1,
    map_name VARCHAR(100) DEFAULT NULL,
    team1_score INT DEFAULT 0,
    team2_score INT DEFAULT 0,
    winner_id INT DEFAULT NULL,
    duration_minutes INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES maclar(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_id) REFERENCES takimlar(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 9. CANLI SKOR GÜNCELLEMELERİ
-- =====================================================
CREATE TABLE canli_guncellemeler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    message VARCHAR(500) NOT NULL,
    event_type ENUM('match_start', 'match_end', 'round_win', 'kill', 'objective', 'pause', 'resume', 'timeout', 'info') DEFAULT 'info',
    team_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES maclar(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES takimlar(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 10. HABERLER
-- =====================================================
CREATE TABLE haberler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(300) NOT NULL,
    slug VARCHAR(300) NOT NULL UNIQUE,
    content LONGTEXT NOT NULL,
    excerpt VARCHAR(500) DEFAULT NULL,
    featured_image VARCHAR(255) DEFAULT NULL,
    category ENUM('haberler', 'turnuva', 'esports', 'rehber', 'duyuru', 'guncelleme') DEFAULT 'haberler',
    author_id INT NOT NULL,
    is_featured TINYINT(1) DEFAULT 0,
    is_published TINYINT(1) DEFAULT 1,
    views INT DEFAULT 0,
    tags VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 11. BİLDİRİMLER
-- =====================================================
CREATE TABLE bildirimler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'tournament', 'match', 'team') DEFAULT 'info',
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 12. SİTE AYARLARI
-- =====================================================
CREATE TABLE ayarlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 13. SAYFA GÖRÜNTÜLEMELERİ (İstatistik)
-- =====================================================
CREATE TABLE sayfa_goruntulemeleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES kullanicilar(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 14. İLETİŞİM MESAJLARI
-- =====================================================
CREATE TABLE iletisim_mesajlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    replied_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- INDEXLER (Performans)
-- =====================================================
CREATE INDEX idx_kullanicilar_role ON kullanicilar(role);
CREATE INDEX idx_kullanicilar_points ON kullanicilar(points DESC);
CREATE INDEX idx_takimlar_game ON takimlar(game_id);
CREATE INDEX idx_takimlar_points ON takimlar(points DESC);
CREATE INDEX idx_turnuvalar_status ON turnuvalar(status);
CREATE INDEX idx_turnuvalar_game ON turnuvalar(game_id);
CREATE INDEX idx_turnuvalar_featured ON turnuvalar(is_featured);
CREATE INDEX idx_maclar_tournament ON maclar(tournament_id);
CREATE INDEX idx_maclar_status ON maclar(status);
CREATE INDEX idx_maclar_scheduled ON maclar(scheduled_at);
CREATE INDEX idx_haberler_category ON haberler(category);
CREATE INDEX idx_haberler_featured ON haberler(is_featured);
CREATE INDEX idx_haberler_published ON haberler(is_published);
CREATE INDEX idx_bildirimler_user ON bildirimler(user_id, is_read);
CREATE INDEX idx_canli_guncellemeler_match ON canli_guncellemeler(match_id);
CREATE INDEX idx_registrations_tournament ON turnuva_kayitlari(tournament_id);
CREATE INDEX idx_sayfa_goruntulemeleri_page ON sayfa_goruntulemeleri(page, created_at);

-- =====================================================
-- VARSAYILAN VERİLER
-- =====================================================

-- Admin kullanıcı (şifre: admin123)
INSERT INTO kullanicilar (username, email, password_hash, role, first_name, last_name, bio, country) VALUES
('admin', 'admin@esporturnuva.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'Yönetici', 'Platform yöneticisi', 'Türkiye');

-- Oyunlar
INSERT INTO oyunlar (name, slug, icon, description) VALUES
('League of Legends', 'league-of-legends', 'lol.png', 'Riot Games tarafından geliştirilen, 5v5 takım tabanlı strateji oyunu. Dünya genelinde en popüler e-spor oyunlarından biri.'),
('Valorant', 'valorant', 'valorant.png', 'Riot Games\'in taktiksel FPS oyunu. 5v5 ajan tabanlı rekabetçi nişancılık.'),
('CS2', 'cs2', 'cs2.png', 'Counter-Strike 2, Valve\'ın efsanevi taktiksel FPS serisi. Rekabetçi e-sporun temel taşı.'),
('Dota 2', 'dota-2', 'dota2.png', 'Valve tarafından geliştirilen, 5v5 MOBA tarzı strateji oyunu. Devasa ödül havuzlarıyla ünlü.'),
('EA FC 25', 'ea-fc-25', 'eafc.png', 'EA Sports\'un futbol simülasyonu. 1v1 rekabetçi e-spor turnuvaları.'),
('Fortnite', 'fortnite', 'fortnite.png', 'Epic Games\'in battle royale oyunu. Solo ve takım bazlı turnuvalar.'),
('PUBG', 'pubg', 'pubg.png', 'Krafton\'un battle royale oyunu. Taktiksel hayatta kalma mücadelesi.'),
('Rocket League', 'rocket-league', 'rocketleague.png', 'Psyonix\'in arabalı futbol oyunu. 3v3 hızlı tempolu e-spor.'),
('Overwatch 2', 'overwatch-2', 'overwatch2.png', 'Blizzard\'ın takım tabanlı FPS oyunu. 5v5 hero shooter.'),
('Apex Legends', 'apex-legends', 'apex.png', 'Respawn Entertainment\'ın battle royale FPS oyunu. 3 kişilik takımlar.');

-- Site ayarları
INSERT INTO ayarlar (setting_key, setting_value, setting_group) VALUES
('site_name', 'E-Spor Turnuva', 'general'),
('site_description', 'Profesyonel E-Spor Turnuva Platformu', 'general'),
('site_email', 'info@esporturnuva.com', 'general'),
('site_phone', '+90 555 123 4567', 'general'),
('site_address', 'İstanbul, Türkiye', 'general'),
('social_discord', 'https://discord.gg/esporturnuva', 'social'),
('social_twitter', 'https://twitter.com/esporturnuva', 'social'),
('social_instagram', 'https://instagram.com/esporturnuva', 'social'),
('social_youtube', 'https://youtube.com/esporturnuva', 'social'),
('social_twitch', 'https://twitch.tv/esporturnuva', 'social'),
('maintenance_mode', '0', 'system'),
('registration_enabled', '1', 'system'),
('max_team_size', '10', 'tournament'),
('default_tournament_format', 'single_elimination', 'tournament');

-- =====================================================
-- Demo Kullanıcılar (şifre hepsi: password123)
-- =====================================================
INSERT INTO kullanicilar (username, email, password_hash, role, first_name, last_name, bio, country, points, total_wins, total_losses) VALUES
('thunderbolt', 'thunder@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Ahmet', 'Yıldırım', 'Pro Valorant oyuncusu. 5 yıllık e-spor deneyimi.', 'Türkiye', 2450, 48, 12),
('shadowhunter', 'shadow@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Mehmet', 'Kaya', 'CS2 IGL. Stratejist ve takım kaptanı.', 'Türkiye', 2380, 45, 15),
('frostbite', 'frost@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Elif', 'Demir', 'LoL mid-laner. Challenger seviyesi.', 'Türkiye', 2290, 42, 18),
('blazestorm', 'blaze@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Can', 'Öztürk', 'Fortnite pro player. Build master.', 'Türkiye', 2150, 38, 22),
('nightwolf', 'wolf@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Deniz', 'Kurt', 'Dota 2 carry. TI rüyası.', 'Türkiye', 2100, 36, 24),
('viperstrike', 'viper@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Zeynep', 'Arslan', 'Valorant duelist. Hızlı refleksler.', 'Türkiye', 2050, 35, 25),
('ironclad', 'iron@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Burak', 'Çelik', 'CS2 AWPer. Tek atışlık efsane.', 'Türkiye', 1980, 33, 27),
('phoenixrise', 'phoenix@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Ayşe', 'Yılmaz', 'LoL support main. Makro queen.', 'Türkiye', 1920, 30, 20),
('stormbreaker', 'storm@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Emre', 'Fırtına', 'PUBG squad leader. Survival specialist.', 'Türkiye', 1850, 28, 22),
('cyberdragon', 'cyber@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Selin', 'Doğan', 'Apex Legends main. Agresif oyun stili.', 'Türkiye', 1800, 26, 24),
('darkphoenix', 'darkph@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Oğuz', 'Kara', 'Rocket League freestyler.', 'Türkiye', 1750, 25, 25),
('mysticblade', 'mystic@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Gizem', 'Kılıç', 'Overwatch DPS specialist.', 'Türkiye', 1700, 24, 26),
('steelwarden', 'steel@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Kaan', 'Koruyucu', 'Valorant sentinel. Site anchor.', 'Türkiye', 1680, 23, 27),
('lunareclipse', 'lunar@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Melis', 'Ay', 'CS2 rifler. Entry fragger.', 'Türkiye', 1620, 22, 28),
('titanfall', 'titan@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Barış', 'Titan', 'EA FC pro. Tiki-taka ustası.', 'Türkiye', 1550, 20, 30),
('arcticfox', 'arctic@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Yasemin', 'Tilki', 'LoL jungler. Ganking expert.', 'Türkiye', 1500, 19, 31),
('thunderstrike', 'tstrike@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Serkan', 'Gök', 'Dota 2 offlaner. Space creator.', 'Türkiye', 1450, 18, 32),
('shadowblade', 'sblade@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Hande', 'Gölge', 'Fortnite builder. Creative mod expert.', 'Türkiye', 1400, 17, 33),
('infernoblaze', 'inferno@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'Tolga', 'Ateş', 'PUBG sniper. Long-range specialist.', 'Türkiye', 1350, 16, 34),
('crystalstorm', 'crystal@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player', 'İrem', 'Kristal', 'Apex Legends pathfinder main.', 'Türkiye', 1300, 15, 35);

-- =====================================================
-- Demo Takımlar
-- =====================================================
INSERT INTO takimlar (name, slug, game_id, description, captain_id, country, points, total_wins, total_losses) VALUES
('Bozkurt E-Sports', 'bozkurt-esports', 2, 'Türkiye''nin en güçlü Valorant takımı. 2023 kuruluş.', 2, 'Türkiye', 3200, 45, 10),
('Osmanlı Gaming', 'osmanli-gaming', 3, 'CS2 sahnesinin yükselen yıldızı. Taktik ve disiplin.', 3, 'Türkiye', 2950, 40, 15),
('Anadolu Ateşi', 'anadolu-atesi', 1, 'LoL Türkiye şampiyonluğu hedefi. Challenger kadro.', 4, 'Türkiye', 2800, 38, 18),
('İstanbul Wolves', 'istanbul-wolves', 2, 'Valorant VCT katılımcısı. Yoğun bootcamp programı.', 7, 'Türkiye', 2650, 35, 20),
('Ankara Titans', 'ankara-titans', 3, 'CS2 Major rüyası. Sistematik oyun anlayışı.', 8, 'Türkiye', 2500, 33, 22),
('Karadeniz Storm', 'karadeniz-storm', 4, 'Dota 2 TI hedefli kadro. Agresif oyun tarzı.', 6, 'Türkiye', 2400, 30, 25),
('Ege Legends', 'ege-legends', 1, 'LoL akademi takımı. Genç yetenekler.', 9, 'Türkiye', 2200, 28, 27),
('Trakya Phoenix', 'trakya-phoenix', 5, 'EA FC e-spor. Türkiye''nin FIFA temsilcisi.', 16, 'Türkiye', 2100, 25, 25),
('Akdeniz Dragons', 'akdeniz-dragons', 6, 'Fortnite turnuva takımı. Creative ve competitive.', 5, 'Türkiye', 2000, 22, 28),
('Marmara United', 'marmara-united', 8, 'Rocket League RLCS katılımcısı.', 12, 'Türkiye', 1900, 20, 30);

-- Takım üyeleri
INSERT INTO takim_uyeleri (team_id, user_id, role) VALUES
-- Bozkurt E-Sports (Valorant)
(1, 2, 'captain'), (1, 7, 'player'), (1, 14, 'player'), (1, 8, 'player'), (1, 15, 'substitute'),
-- Osmanlı Gaming (CS2)
(2, 3, 'captain'), (2, 8, 'player'), (2, 15, 'player'), (2, 9, 'player'), (2, 14, 'substitute'),
-- Anadolu Ateşi (LoL)
(3, 4, 'captain'), (3, 9, 'player'), (3, 17, 'player'), (3, 13, 'player'), (3, 16, 'player'),
-- İstanbul Wolves (Valorant)
(4, 7, 'captain'), (4, 14, 'player'), (4, 3, 'player'), (4, 8, 'player'),
-- Ankara Titans (CS2)
(5, 8, 'captain'), (5, 15, 'player'), (5, 3, 'player'), (5, 7, 'player'),
-- Karadeniz Storm (Dota2)
(6, 6, 'captain'), (6, 18, 'player'), (6, 10, 'player'), (6, 11, 'player'),
-- Ege Legends (LoL)
(7, 9, 'captain'), (7, 4, 'player'), (7, 17, 'player'), (7, 13, 'player'),
-- Trakya Phoenix (EA FC)
(8, 16, 'captain'),
-- Akdeniz Dragons (Fortnite)
(9, 5, 'captain'), (9, 19, 'player'), (9, 20, 'player'),
-- Marmara United (Rocket League)
(10, 12, 'captain'), (10, 11, 'player'), (10, 20, 'player');

-- =====================================================
-- Demo Turnuvalar
-- =====================================================
INSERT INTO turnuvalar (name, slug, game_id, description, rules, format, team_size, max_teams, prize_pool, entry_fee, start_date, end_date, registration_start, registration_end, status, is_featured, created_by) VALUES
('Valorant Türkiye Şampiyonası 2025', 'valorant-turkiye-sampiyonasi-2025', 2, 
 'Türkiye''nin en büyük Valorant turnuvası! 32 takım, tek eleme formatında mücadele edecek. Büyük ödül havuzu ve profesyonel yayın desteği.',
 '1. Tüm maçlar BO3 formatında oynanacaktır.\n2. Map veto sistemi uygulanacaktır.\n3. Anti-cheat zorunludur.\n4. Maçlara 10 dakikadan fazla geç kalınamaz.\n5. Toxic davranış diskalifiye sebebidir.',
 'single_elimination', 5, 32, 50000.00, 0.00, '2025-07-01 14:00:00', '2025-07-15 22:00:00', '2025-06-01 00:00:00', '2025-06-25 23:59:59', 'registration', 1, 1),

('CS2 Major Qualifier TR', 'cs2-major-qualifier-tr', 3,
 'CS2 Major yolunda Türkiye elemeleri. En iyi 16 takım mücadele edecek.',
 '1. BO3 format, final BO5.\n2. VAC ban olan oyuncular katılamaz.\n3. Overtime MR12.',
 'single_elimination', 5, 16, 25000.00, 100.00, '2025-06-15 16:00:00', '2025-06-22 22:00:00', '2025-05-20 00:00:00', '2025-06-10 23:59:59', 'ongoing', 1, 1),

('LoL Üniversiteler Ligi', 'lol-universiteler-ligi', 1,
 'Üniversite takımları arası League of Legends ligi. Sezon boyu süren heyecan!',
 '1. Round Robin format.\n2. Her takım diğer takımlarla 2 kez karşılaşır.\n3. BO1 maçlar, playoff BO3.',
 'round_robin', 5, 12, 15000.00, 0.00, '2025-09-01 18:00:00', '2025-12-20 22:00:00', '2025-08-01 00:00:00', '2025-08-25 23:59:59', 'upcoming', 0, 1),

('Dota 2 İstanbul Cup', 'dota-2-istanbul-cup', 4,
 'İstanbul''da düzenlenen LAN turnuvası. 8 takımlık çift eleme.',
 '1. Double elimination bracket.\n2. Tüm maçlar BO3.\n3. Grand final BO5.\n4. LAN ortamında oynanacaktır.',
 'double_elimination', 5, 8, 30000.00, 250.00, '2025-08-10 12:00:00', '2025-08-12 22:00:00', '2025-07-01 00:00:00', '2025-08-01 23:59:59', 'upcoming', 1, 1),

('Fortnite Solo Showdown', 'fortnite-solo-showdown', 6,
 'Solo battle royale turnuvası. Puan bazlı sıralama sistemi.',
 '1. 6 maç oynanacak.\n2. Kill başına 1 puan.\n3. Victory Royale 10 puan.\n4. Top 10: 5 puan, Top 25: 3 puan.',
 'swiss', 1, 100, 10000.00, 0.00, '2025-07-20 20:00:00', '2025-07-20 23:59:00', '2025-07-01 00:00:00', '2025-07-18 23:59:59', 'upcoming', 0, 1),

('EA FC 25 Türkiye Kupası', 'ea-fc-25-turkiye-kupasi', 5,
 'EA FC 25 1v1 turnuvası. Türkiye''nin en iyi FIFA oyuncuları.',
 '1. Tek eleme.\n2. Her maç 2 yarı, uzatma ve penaltı.\n3. Özel takımlar kullanılacak.',
 'single_elimination', 1, 64, 8000.00, 50.00, '2025-06-28 15:00:00', '2025-06-29 22:00:00', '2025-06-01 00:00:00', '2025-06-25 23:59:59', 'registration', 0, 1);

-- Turnuva kayıtları
INSERT INTO turnuva_kayitlari (tournament_id, team_id, status) VALUES
(1, 1, 'approved'), (1, 4, 'approved'), (1, 2, 'pending'), (1, 5, 'pending'),
(2, 2, 'approved'), (2, 5, 'approved'), (2, 1, 'approved'), (2, 4, 'approved'),
(3, 3, 'approved'), (3, 7, 'approved'),
(4, 6, 'approved'),
(6, 8, 'approved');

-- =====================================================
-- Demo Maçlar (CS2 Major Qualifier - devam eden turnuva)
-- =====================================================
INSERT INTO maclar (tournament_id, round, match_number, team1_id, team2_id, score1, score2, winner_id, best_of, status, scheduled_at, completed_at) VALUES
-- Round 1
(2, 1, 1, 2, 4, 2, 1, 2, 3, 'completed', '2025-06-15 16:00:00', '2025-06-15 18:30:00'),
(2, 1, 2, 5, 1, 0, 2, 1, 3, 'completed', '2025-06-15 19:00:00', '2025-06-15 21:00:00'),
(2, 1, 3, 3, 7, 2, 0, 3, 3, 'completed', '2025-06-16 16:00:00', '2025-06-16 17:30:00'),
(2, 1, 4, 6, 10, 1, 2, 10, 3, 'completed', '2025-06-16 18:00:00', '2025-06-16 20:30:00'),
-- Round 2 (Yarı Final)
(2, 2, 1, 2, 1, 1, 1, NULL, 3, 'live', '2025-06-18 16:00:00', NULL),
(2, 2, 2, 3, 10, 0, 0, NULL, 3, 'scheduled', '2025-06-18 19:00:00', NULL),
-- Final
(2, 3, 1, NULL, NULL, 0, 0, NULL, 5, 'scheduled', '2025-06-22 18:00:00', NULL);

-- Canlı güncelleme (devam eden maç için)
INSERT INTO canli_guncellemeler (match_id, message, event_type, team_id) VALUES
(5, 'Maç başladı! İlk harita: Mirage', 'match_start', NULL),
(5, 'Osmanlı Gaming pistol round''u aldı!', 'round_win', 2),
(5, 'Bozkurt E-Sports ekonomiyi dengeledi, 3-2 Osmanlı önde.', 'info', NULL),
(5, 'shadowhunter müthiş bir clutch yaptı! 1v3!', 'kill', 2),
(5, 'İlk yarı sonu: 8-7 Osmanlı Gaming', 'info', NULL),
(5, 'ironclad AWP ile art arda 3 kill! Bozkurt eşitledi!', 'kill', 1),
(5, 'Mirage sona erdi: 16-14 Bozkurt E-Sports', 'round_win', 1),
(5, 'İkinci harita: Inferno başlıyor', 'info', NULL),
(5, 'Osmanlı Gaming agresif başladı, 4-0 önde!', 'round_win', 2);

-- =====================================================
-- Demo Haberler
-- =====================================================
INSERT INTO haberler (title, slug, content, excerpt, category, author_id, is_featured, views) VALUES
('Valorant Türkiye Şampiyonası 2025 Kayıtları Açıldı!', 'valorant-turkiye-sampiyonasi-2025-kayitlari-acildi',
 '<p>Türkiye''nin en büyük Valorant turnuvası için kayıtlar resmen başladı! 50.000 TL ödül havuzu ile 32 takımın mücadele edeceği bu dev organizasyona hemen kaydolun.</p>\n<h3>Turnuva Detayları</h3>\n<p>Turnuva tek eleme formatında oynanacak olup, tüm maçlar BO3 şeklinde gerçekleşecektir. Final maçı ise BO5 formatında olacak.</p>\n<h3>Ödül Dağılımı</h3>\n<ul>\n<li>1. - 25.000 TL</li>\n<li>2. - 15.000 TL</li>\n<li>3/4. - 5.000 TL</li>\n</ul>\n<p>Son kayıt tarihi: 25 Haziran 2025. Acele edin, kontenjan sınırlı!</p>',
 'Türkiye''nin en büyük Valorant turnuvası için kayıtlar başladı! 50.000 TL ödül havuzu sizi bekliyor.',
 'turnuva', 1, 1, 1250),

('CS2 Major Qualifier Heyecanı Devam Ediyor', 'cs2-major-qualifier-heyecani-devam-ediyor',
 '<p>CS2 Major Türkiye elemeleri tam gaz devam ediyor! Yarı final eşleşmeleri belli oldu.</p>\n<h3>Yarı Final Eşleşmeleri</h3>\n<p><strong>Osmanlı Gaming vs Bozkurt E-Sports</strong> - İki dev takımın karşılaşması büyük heyecan yaratıyor.</p>\n<p><strong>Anadolu Ateşi vs Marmara United</strong> - Sürpriz bir eşleşme!</p>\n<p>shadowhunter''ın performansı turnuvanın en dikkat çekici anlarından birini oluşturdu. 1v3 clutch''ı sosyal medyada viral oldu.</p>',
 'CS2 Major Türkiye elemeleri devam ediyor. Yarı final eşleşmeleri belli oldu!',
 'esports', 1, 1, 980),

('E-Spor Türkiye''de Hızla Büyüyor', 'e-spor-turkiyede-hizla-buyuyor',
 '<p>2025 yılında Türkiye''deki e-spor ekosistemi büyük bir ivme kazandı. Artan sponsorluklar, profesyonel takımlar ve turnuva organizasyonları, Türkiye''yi bölgenin e-spor merkezi haline getiriyor.</p>\n<h3>Rakamlarla E-Spor</h3>\n<p>Türkiye''de aktif e-spor oyuncusu sayısı 2 milyonu aştı. Profesyonel takım sayısı ise son bir yılda %40 arttı.</p>\n<p>Yıllık turnuva ödül havuzu toplamı 5 milyon TL''yi geçti.</p>',
 'Türkiye''deki e-spor ekosistemi 2025''te büyük bir ivme kazandı.',
 'haberler', 1, 0, 750),

('Yeni Başlayanlar İçin Valorant Rehberi', 'yeni-baslayanlar-icin-valorant-rehberi',
 '<p>Valorant''a yeni mi başladınız? Bu rehber size temel mekanikleri, ajan seçimini ve rekabetçi modda yükselme ipuçlarını sunuyor.</p>\n<h3>Ajan Seçimi</h3>\n<p>Yeni başlayanlar için önerilen ajanlar: Sage, Brimstone, Sova. Bu ajanlar takıma katkı sağlaması kolay ajanlardır.</p>\n<h3>Aim Geliştirme</h3>\n<p>Her gün 15 dakika aim lab çalışması yapın. Crosshair placement (nişangah pozisyonu) en önemli beceridir.</p>',
 'Valorant''a yeni başlayanlar için kapsamlı rehber.',
 'rehber', 1, 0, 520),

('LoL Üniversiteler Ligi Duyurusu', 'lol-universiteler-ligi-duyurusu',
 '<p>Üniversite takımları arası League of Legends ligi Eylül ayında başlıyor! Üniversitenizi temsil edin ve kampüs şampiyonu olun.</p>\n<h3>Katılım Şartları</h3>\n<ul>\n<li>Aktif üniversite öğrencisi olmak</li>\n<li>Minimum Gold rank</li>\n<li>5 kişilik tam kadro</li>\n</ul>\n<p>Kayıtlar 1 Ağustos''ta açılacak. 15.000 TL ödül havuzu!</p>',
 'Üniversite takımları arası LoL ligi Eylül''de başlıyor!',
 'duyuru', 1, 0, 430),

('Bozkurt E-Sports Yeni Kadrosunu Açıkladı', 'bozkurt-esports-yeni-kadrosunu-acikladi',
 '<p>Valorant sahnesinin güçlü takımı Bozkurt E-Sports, 2025 sezonu için yeni kadrosunu açıkladı. Takım, özellikle duelist pozisyonunda önemli bir transfer gerçekleştirdi.</p>\n<p>Kaptan thunderbolt: "Bu sezon Türkiye şampiyonluğunu hedefliyoruz. Yeni kadromuzla çok güçlüyüz."</p>',
 'Bozkurt E-Sports 2025 sezonu kadrosunu açıkladı.',
 'esports', 1, 0, 380);

-- =====================================================
-- Demo Bildirimler
-- =====================================================
INSERT INTO bildirimler (user_id, title, message, type, link) VALUES
(2, 'Turnuva Kaydınız Onaylandı', 'Valorant Türkiye Şampiyonası 2025 turnuvasına kaydınız onaylanmıştır. İyi oyunlar!', 'tournament', 'tournament.php?id=1'),
(2, 'Maçınız Yaklaşıyor', 'CS2 Major Qualifier yarı final maçınız 18 Haziran saat 16:00''da başlayacak.', 'match', 'tournament.php?id=2'),
(3, 'Yeni Takım Daveti', 'Ankara Titans takımından oyuncu daveti aldınız.', 'team', 'dashboard.php'),
(7, 'Turnuva Kaydınız Onaylandı', 'Valorant Türkiye Şampiyonası 2025 turnuvasına İstanbul Wolves olarak kaydınız onaylandı.', 'tournament', 'tournament.php?id=1');
