<?php
/**
 * Üst Bölüm (Header + Navigasyon)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/kimlik.php';
require_once __DIR__ . '/fonksiyonlar.php';

$bildirimSayisi = okunmamisBildirimSayisi();
$sayfaBasligi = isset($sayfaBasligi) ? $sayfaBasligi . ' | ' . SITE_NAME : SITE_NAME;
$sayfaAciklama = isset($sayfaAciklama) ? $sayfaAciklama : SITE_DESC;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= temizle($sayfaAciklama) ?>">
    <meta name="site-url" content="<?= SITE_URL ?>">
    <title><?= temizle($sayfaBasligi) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= SITE_URL ?>/assets/images/favicon.svg">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="<?= CSS_URL ?>/style.css?v=<?= filemtime(ASSETS_PATH . 'css' . DIRECTORY_SEPARATOR . 'style.css') ?>">
    <link rel="stylesheet" href="<?= CSS_URL ?>/bilesenler.css?v=<?= filemtime(ASSETS_PATH . 'css' . DIRECTORY_SEPARATOR . 'bilesenler.css') ?>">
    <?php if (isset($ekstraCSS)): ?>
        <link rel="stylesheet" href="<?= CSS_URL ?>/<?= $ekstraCSS ?>?v=<?= filemtime(ASSETS_PATH . 'css' . DIRECTORY_SEPARATOR . $ekstraCSS) ?>">
    <?php endif; ?>
</head>
<body>

<!-- Üst Bilgi Çubuğu -->
<div class="ust-cubuk">
    <div class="container ust-cubuk-icerik">
        <div class="ust-cubuk-sol">
            <span><i class="fas fa-envelope"></i> info@esporturnuva.com</span>
            <span><i class="fas fa-phone"></i> +90 555 123 4567</span>
        </div>
        <div class="ust-cubuk-sag">
            <a href="https://discord.com" target="_blank" title="Discord"><i class="fab fa-discord"></i></a>
            <a href="https://x.com" target="_blank" title="Twitter"><i class="fab fa-twitter"></i></a>
            <a href="https://instagram.com" target="_blank" title="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="https://twitch.tv" target="_blank" title="Twitch"><i class="fab fa-twitch"></i></a>
            <a href="https://youtube.com" target="_blank" title="YouTube"><i class="fab fa-youtube"></i></a>
        </div>
    </div>
</div>

<!-- Ana Navigasyon -->
<nav class="navbar" id="navbar">
    <div class="container navbar-icerik">
        <!-- Logo -->
        <a href="<?= SITE_URL ?>" class="logo">
            <i class="fas fa-gamepad"></i>
            <span>E-SPOR<span class="logo-vurgu">TURNUVA</span></span>
        </a>

        <!-- Menü -->
        <ul class="nav-menu" id="navMenu">
            <li><a href="<?= SITE_URL ?>/index.php" class="nav-link <?= aktifSayfa('index') ?>"><i class="fas fa-home"></i> Ana Sayfa</a></li>
            <li><a href="<?= SITE_URL ?>/turnuvalar.php" class="nav-link <?= aktifSayfa('turnuvalar') ?>"><i class="fas fa-trophy"></i> Turnuvalar</a></li>
            <li><a href="<?= SITE_URL ?>/maclar.php" class="nav-link <?= aktifSayfa('maclar') ?>"><i class="fas fa-gamepad"></i> Maçlar</a></li>
            <li><a href="<?= SITE_URL ?>/takimlar.php" class="nav-link <?= aktifSayfa('takimlar') ?>"><i class="fas fa-users"></i> Takımlar</a></li>
            <li><a href="<?= SITE_URL ?>/oyuncular.php" class="nav-link <?= aktifSayfa('oyuncular') ?>"><i class="fas fa-user-ninja"></i> Oyuncular</a></li>
            <li><a href="<?= SITE_URL ?>/siralama.php" class="nav-link <?= aktifSayfa('siralama') ?>"><i class="fas fa-ranking-star"></i> Sıralama</a></li>
            <li><a href="<?= SITE_URL ?>/haberler.php" class="nav-link <?= aktifSayfa('haberler') ?>"><i class="fas fa-newspaper"></i> Haberler</a></li>
        </ul>

        <!-- Sağ Taraf -->
        <div class="nav-sag">
            <!-- Tema Değiştirici -->
            <button class="tema-toggle" id="temaToggle" title="Tema Değiştir">
                <div class="tema-toggle-ikon">
                    <i class="fas fa-moon"></i>
                </div>
            </button>

            <?php if (girisYapmisMi()): ?>
                <!-- Bildirimler -->
                <div class="bildirim-wrapper">
                    <button class="bildirim-btn" id="bildirimBtn">
                        <i class="fas fa-bell"></i>
                        <?php if ($bildirimSayisi > 0): ?>
                            <span class="bildirim-badge"><?= $bildirimSayisi ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="bildirim-dropdown" id="bildirimDropdown">
                        <div class="bildirim-baslik">
                            <h4>Bildirimler</h4>
                            <a href="<?= SITE_URL ?>/panel.php?tab=bildirimler">Tümünü Gör</a>
                        </div>
                        <div class="bildirim-liste" id="bildirimListe">
                            <p class="bildirim-bos">Yükleniyor...</p>
                        </div>
                    </div>
                </div>

                <!-- Kullanıcı Menü -->
                <div class="kullanici-wrapper">
                    <button class="kullanici-btn" id="kullaniciBtn">
                        <img src="<?= avatarURL($_SESSION['avatar'] ?? '') ?>" alt="Kullanıcı Avatarı" class="nav-avatar">
                        <span class="nav-username"><?= temizle($_SESSION['username']) ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="kullanici-dropdown" id="kullaniciDropdown">
                        <a href="<?= SITE_URL ?>/panel.php"><i class="fas fa-tachometer-alt"></i> Panelim</a>
                        <a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $_SESSION['user_id'] ?>"><i class="fas fa-user"></i> Profilim</a>
                        <?php if (adminMi()): ?>
                            <a href="<?= SITE_URL ?>/yonetim/" class="admin-link"><i class="fas fa-crown"></i> Yönetim Paneli</a>
                        <?php endif; ?>
                        <hr>
                        <a href="<?= SITE_URL ?>/cikis.php" class="cikis-link"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= SITE_URL ?>/giris.php" class="btn btn-ghost btn-sm"><i class="fas fa-sign-in-alt"></i> Giriş Yap</a>
                <a href="<?= SITE_URL ?>/kayit.php" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Kayıt Ol</a>
            <?php endif; ?>

            <!-- Mobil Menü Butonu -->
            <button class="menu-toggle" id="menuToggle">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </div>
</nav>

<!-- Flash Mesajlar -->
<div class="container">
    <?= flashGoster() ?>
</div>

<!-- Ana İçerik -->
<main>
