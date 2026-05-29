<?php
/**
 * Yardımcı Fonksiyonlar
 */

/**
 * XSS koruması
 */
function temizle($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

/**
 * Slug oluştur (Türkçe karakter desteği)
 */
function slugOlustur($str) {
    $turkce = ['ç','ğ','ı','ö','ş','ü','Ç','Ğ','İ','Ö','Ş','Ü',' '];
    $ingilizce = ['c','g','i','o','s','u','c','g','i','o','s','u','-'];
    $str = str_replace($turkce, $ingilizce, $str);
    $str = strtolower($str);
    $str = preg_replace('/[^a-z0-9\-]/', '', $str);
    $str = preg_replace('/-+/', '-', $str);
    return trim($str, '-');
}

/**
 * Tarih formatla (Türkçe)
 */
function tarihFormatla($tarih, $format = 'long') {
    if (!$tarih) return '-';
    $timestamp = strtotime($tarih);
    $aylar = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
    
    switch ($format) {
        case 'short':
            return date('d', $timestamp) . ' ' . $aylar[date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
        case 'long':
            return date('d', $timestamp) . ' ' . $aylar[date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp) . ', ' . date('H:i', $timestamp);
        case 'relative':
            return zamanOnce($tarih);
        default:
            return date($format, $timestamp);
    }
}

/**
 * Geçen zaman (ör: 2 saat önce)
 */
function zamanOnce($tarih) {
    $simdi = time();
    $fark = $simdi - strtotime($tarih);
    
    if ($fark < 60) return 'Az önce';
    if ($fark < 3600) return floor($fark / 60) . ' dakika önce';
    if ($fark < 86400) return floor($fark / 3600) . ' saat önce';
    if ($fark < 604800) return floor($fark / 86400) . ' gün önce';
    if ($fark < 2592000) return floor($fark / 604800) . ' hafta önce';
    return tarihFormatla($tarih, 'short');
}

/**
 * Para formatla
 */
function paraFormatla($tutar) {
    if ($tutar >= 1000) {
        return number_format($tutar, 0, ',', '.') . ' ₺';
    }
    return number_format($tutar, 2, ',', '.') . ' ₺';
}

/**
 * Sayfalama HTML
 */
function sayfalamaHTML($toplamKayit, $mevcutSayfa, $sayfaBasina = ITEMS_PER_PAGE, $url = '') {
    $toplamSayfa = ceil($toplamKayit / $sayfaBasina);
    if ($toplamSayfa <= 1) return '';
    
    $html = '<div class="sayfalama">';
    
    if ($mevcutSayfa > 1) {
        $html .= '<a href="' . $url . 'sayfa=' . ($mevcutSayfa - 1) . '" class="sayfa-btn"><i class="fas fa-chevron-left"></i></a>';
    }
    
    $baslangic = max(1, $mevcutSayfa - 2);
    $bitis = min($toplamSayfa, $mevcutSayfa + 2);
    
    if ($baslangic > 1) {
        $html .= '<a href="' . $url . 'sayfa=1" class="sayfa-btn">1</a>';
        if ($baslangic > 2) $html .= '<span class="sayfa-ara">...</span>';
    }
    
    for ($i = $baslangic; $i <= $bitis; $i++) {
        $aktif = ($i == $mevcutSayfa) ? ' aktif' : '';
        $html .= '<a href="' . $url . 'sayfa=' . $i . '" class="sayfa-btn' . $aktif . '">' . $i . '</a>';
    }
    
    if ($bitis < $toplamSayfa) {
        if ($bitis < $toplamSayfa - 1) $html .= '<span class="sayfa-ara">...</span>';
        $html .= '<a href="' . $url . 'sayfa=' . $toplamSayfa . '" class="sayfa-btn">' . $toplamSayfa . '</a>';
    }
    
    if ($mevcutSayfa < $toplamSayfa) {
        $html .= '<a href="' . $url . 'sayfa=' . ($mevcutSayfa + 1) . '" class="sayfa-btn"><i class="fas fa-chevron-right"></i></a>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Durum badge'i
 */
function durumBadge($durum) {
    $durumlar = [
        'draft' => ['Taslak', 'badge-secondary'],
        'upcoming' => ['Yaklaşıyor', 'badge-info'],
        'registration' => ['Kayıt Açık', 'badge-success'],
        'ongoing' => ['Devam Ediyor', 'badge-warning'],
        'completed' => ['Tamamlandı', 'badge-primary'],
        'cancelled' => ['İptal', 'badge-danger'],
        'scheduled' => ['Planlandı', 'badge-info'],
        'live' => ['CANLI', 'badge-live'],
        'pending' => ['Beklemede', 'badge-warning'],
        'approved' => ['Onaylandı', 'badge-success'],
        'rejected' => ['Reddedildi', 'badge-danger'],
    ];
    
    $d = $durumlar[$durum] ?? [$durum, 'badge-secondary'];
    return '<span class="badge ' . $d[1] . '">' . $d[0] . '</span>';
}

/**
 * Format badge'i
 */
function formatBadge($format) {
    $formatlar = [
        'single_elimination' => 'Tek Eleme',
        'double_elimination' => 'Çift Eleme',
        'round_robin' => 'Lig',
        'swiss' => 'Swiss',
        'league' => 'Lig',
    ];
    return $formatlar[$format] ?? $format;
}

/**
 * Rol ismini Türkçe'ye çevirir
 */
function rolCevir($rol, $tur = 'user') {
    if ($tur === 'user') {
        $roller = [
            'user' => 'Kullanıcı',
            'player' => 'Oyuncu',
            'moderator' => 'Moderatör',
            'admin' => 'Yönetici'
        ];
        return $roller[$rol] ?? ucfirst($rol);
    } elseif ($tur === 'team') {
        $roller = [
            'captain' => 'Kaptan',
            'player' => 'Oyuncu',
            'substitute' => 'Yedek',
            'coach' => 'Koç'
        ];
        return $roller[$rol] ?? ucfirst($rol);
    }
    return ucfirst($rol);
}

/**
 * Haber kategorisini Türkçe'ye çevirir
 */
function kategoriCevir($kategori) {
    $kategoriler = [
        'haberler' => 'Haberler',
        'turnuva' => 'Turnuva',
        'esports' => 'E-Spor',
        'rehber' => 'Rehber',
        'duyuru' => 'Duyuru'
    ];
    return $kategoriler[$kategori] ?? ucfirst($kategori);
}

/**
 * Avatar URL'si
 */
function avatarURL($avatar) {
    if (!$avatar || $avatar === 'default-avatar.png') {
        return ASSETS_URL . '/images/default/default-avatar.png';
    }
    return UPLOADS_URL . '/avatars/' . $avatar;
}

/**
 * Takım logo URL'si
 */
function takimLogoURL($logo) {
    if (!$logo || $logo === 'default-team.png') {
        return ASSETS_URL . '/images/default/default-team.png';
    }
    if (strpos($logo, 'assets/') === 0) {
        return SITE_URL . '/' . $logo;
    }
    return UPLOADS_URL . '/team-logos/' . $logo;
}

/**
 * Haber resim URL'si
 */
function haberResimURL($resim) {
    if (!$resim || $resim === 'default-news.jpg') {
        return ''; // Geriye boş dönebilir, view kısmında icon gösteririz veya default resim
    }
    if (strpos($resim, 'assets/') === 0) {
        return SITE_URL . '/' . $resim;
    }
    if (strpos($resim, 'http') === 0) {
        return $resim;
    }
    return UPLOADS_URL . '/news/' . $resim;
}

/**
 * Güvenli dosya yükleme
 */
function dosyaYukle($dosya, $hedefKlasor, $maxBoyut = MAX_AVATAR_SIZE) {
    if ($dosya['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Dosya yükleme hatası.'];
    }
    
    if ($dosya['size'] > $maxBoyut) {
        return ['success' => false, 'error' => 'Dosya çok büyük. Maksimum: ' . ($maxBoyut / 1024 / 1024) . 'MB'];
    }
    
    $mime = mime_content_type($dosya['tmp_name']);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'error' => 'Geçersiz dosya türü. Sadece JPEG, PNG, WebP ve GIF kabul edilir.'];
    }
    
    $uzanti = pathinfo($dosya['name'], PATHINFO_EXTENSION);
    $yeniAd = uniqid() . '_' . time() . '.' . $uzanti;
    $hedefYol = UPLOADS_PATH . $hedefKlasor . DIRECTORY_SEPARATOR . $yeniAd;
    
    // Klasör yoksa oluştur
    $klasor = dirname($hedefYol);
    if (!is_dir($klasor)) {
        mkdir($klasor, 0755, true);
    }
    
    if (move_uploaded_file($dosya['tmp_name'], $hedefYol)) {
        return ['success' => true, 'filename' => $yeniAd];
    }
    
    return ['success' => false, 'error' => 'Dosya kaydedilemedi.'];
}

/**
 * CSRF Token oluştur
 */
function csrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF Token doğrula
 */
function csrfDogrula($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRF hidden input
 */
function csrfInput() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Flash mesaj ayarla
 */
function flashMesaj($mesaj, $tip = 'success') {
    $_SESSION['flash'] = ['mesaj' => $mesaj, 'tip' => $tip];
}

/**
 * Flash mesaj göster
 */
function flashGoster() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return '<div class="alert alert-' . $flash['tip'] . '">
                    <i class="fas fa-' . ($flash['tip'] === 'success' ? 'check-circle' : ($flash['tip'] === 'error' ? 'exclamation-circle' : 'info-circle')) . '"></i>
                    ' . $flash['mesaj'] . '
                    <button class="alert-kapat" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                </div>';
    }
    return '';
}

/**
 * Kazanma oranı hesapla
 */
function kazanmaOrani($galibiyet, $maglup) {
    $toplam = $galibiyet + $maglup;
    if ($toplam === 0) return '0%';
    return round(($galibiyet / $toplam) * 100) . '%';
}

/**
 * Kısaltılmış sayı (1000 -> 1K)
 */
function kisaSayi($sayi) {
    if ($sayi >= 1000000) return round($sayi / 1000000, 1) . 'M';
    if ($sayi >= 1000) return round($sayi / 1000, 1) . 'K';
    return $sayi;
}

/**
 * Site ayarı al
 */
function siteAyari($key) {
    global $db;
    $result = $db->fetch("SELECT setting_value FROM ayarlar WHERE setting_key = ?", [$key]);
    return $result ? $result['setting_value'] : null;
}

/**
 * Aktif sayfa kontrolü
 */
function aktifSayfa($sayfa) {
    $current = basename($_SERVER['PHP_SELF'], '.php');
    return ($current === $sayfa) ? 'aktif' : '';
}

/**
 * JSON yanıt
 */
function jsonYanit($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
