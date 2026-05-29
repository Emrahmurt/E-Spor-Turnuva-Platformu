<?php
/**
 * Kimlik Doğrulama Fonksiyonları
 */

/**
 * Kullanıcı girişi yap
 */
function girisYap($emailOrUsername, $sifre) {
    global $db;
    $user = $db->fetch("SELECT * FROM kullanicilar WHERE (email = ? OR username = ?) AND is_active = 1", [$emailOrUsername, $emailOrUsername]);
    
    if ($user && password_verify($sifre, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = $user['avatar'];
        
        // Son giriş güncelle
        $db->query("UPDATE kullanicilar SET last_login = NOW() WHERE id = ?", [$user['id']]);
        
        return true;
    }
    return false;
}

/**
 * Kullanıcı kaydı
 */
function kayitOl($username, $email, $sifre, $ad = '', $soyad = '') {
    global $db;
    
    // Kullanıcı adı kontrolü
    $existing = $db->fetch("SELECT id FROM kullanicilar WHERE username = ?", [$username]);
    if ($existing) {
        return ['success' => false, 'error' => 'Bu kullanıcı adı zaten kullanılıyor.'];
    }
    
    // Email kontrolü
    $existing = $db->fetch("SELECT id FROM kullanicilar WHERE email = ?", [$email]);
    if ($existing) {
        return ['success' => false, 'error' => 'Bu e-posta adresi zaten kayıtlı.'];
    }
    
    // Şifre hash
    $hash = password_hash($sifre, PASSWORD_DEFAULT);
    
    $id = $db->insert(
        "INSERT INTO kullanicilar (username, email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?, ?)",
        [$username, $email, $hash, $ad, $soyad]
    );
    
    if ($id) {
        return ['success' => true, 'user_id' => $id];
    }
    return ['success' => false, 'error' => 'Kayıt sırasında bir hata oluştu.'];
}

/**
 * Çıkış yap
 */
function cikisYap() {
    session_unset();
    session_destroy();
}

/**
 * Kullanıcı giriş yapmış mı?
 */
function girisYapmisMi() {
    return isset($_SESSION['user_id']);
}

/**
 * Admin mi?
 */
function adminMi() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Mevcut kullanıcı bilgilerini al
 */
function mevcutKullanici() {
    if (!girisYapmisMi()) return null;
    global $db;
    return $db->fetch("SELECT * FROM kullanicilar WHERE id = ?", [$_SESSION['user_id']]);
}

/**
 * Giriş zorunlu
 */
function girisGerekli() {
    if (!girisYapmisMi()) {
        header('Location: ' . SITE_URL . '/giris.php');
        exit;
    }
}

/**
 * Admin giriş zorunlu
 */
function adminGerekli() {
    if (!adminMi()) {
        header('Location: ' . SITE_URL . '/giris.php');
        exit;
    }
}

/**
 * Okunmamış bildirim sayısı
 */
function okunmamisBildirimSayisi() {
    if (!girisYapmisMi()) return 0;
    global $db;
    return $db->count("SELECT COUNT(*) FROM bildirimler WHERE user_id = ? AND is_read = 0", [$_SESSION['user_id']]);
}
