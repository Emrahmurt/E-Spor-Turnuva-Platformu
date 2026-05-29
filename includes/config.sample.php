<?php
/**
 * E-Spor Turnuva Platformu - Ana Yapılandırma Şablonu
 * Bu dosyayı config.php olarak kopyalayıp kendi bilgilerinizi giriniz.
 */

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Oturum başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Zaman dilimi
date_default_timezone_set('Europe/Istanbul');

// Karakter seti
mb_internal_encoding('UTF-8');

// Veritabanı ayarları
define('DB_HOST', 'localhost'); // Sunucu adresi (genelde localhost)
define('DB_NAME', 'espor_turnuva'); // Veritabanı adı
define('DB_USER', 'veritabani_kullanici_adi'); // Veritabanı kullanıcı adı
define('DB_PASS', 'veritabani_sifresi'); // Veritabanı şifresi
define('DB_CHARSET', 'utf8mb4');

// Site ayarları (Canlı sitenizin URL'si, örn: https://siteniz.com)
define('SITE_URL', 'http://localhost/e_spor'); 
define('SITE_NAME', 'E-Spor Turnuva');
define('SITE_DESC', 'Profesyonel E-Spor Turnuva Platformu');

// Dosya yolları
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('INCLUDES_PATH', ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR);
define('ASSETS_PATH', ROOT_PATH . 'assets' . DIRECTORY_SEPARATOR);
define('UPLOADS_PATH', ASSETS_PATH . 'uploads' . DIRECTORY_SEPARATOR);

// URL yolları
define('ASSETS_URL', SITE_URL . '/assets');
define('CSS_URL', ASSETS_URL . '/css');
define('JS_URL', ASSETS_URL . '/js');
define('IMG_URL', ASSETS_URL . '/images');
define('UPLOADS_URL', ASSETS_URL . '/uploads');

// Yükleme limitleri
define('MAX_AVATAR_SIZE', 2 * 1024 * 1024); // 2MB
define('MAX_BANNER_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// Sayfalama
define('ITEMS_PER_PAGE', 12);

// Veritabanı bağlantısı
require_once INCLUDES_PATH . 'veritabani.php';
