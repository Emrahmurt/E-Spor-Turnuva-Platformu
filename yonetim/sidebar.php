<?php
/**
 * Yönetim Paneli Ortak Sidebar Bileşeni
 */
$aktifSekme = $aktifSekme ?? '';
?>
<aside class="dashboard-sidebar">
    <div style="text-align:center;padding:var(--space-md) 0;border-bottom:1px solid var(--border-light);margin-bottom:var(--space-md);">
        <h3 style="font-family:var(--font-heading);font-size:1.2rem;font-weight:700;color:var(--text-white);">
            <i class="fas fa-crown" style="color:var(--accent)"></i> YÖNETİM
        </h3>
    </div>
    
    <nav class="admin-nav-list">
        <a href="<?= SITE_URL ?>/yonetim/index.php" class="dashboard-menu-link <?= $aktifSekme === 'dashboard' ? 'aktif' : '' ?>">
            <i class="fas fa-tachometer-alt"></i> Kontrol Paneli
        </a>
        
        <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);margin:var(--space-md) 0 var(--space-xs) var(--space-sm);">İçerik Yönetimi</div>
        
        <a href="<?= SITE_URL ?>/yonetim/turnuvalar.php" class="dashboard-menu-link <?= $aktifSekme === 'turnuvalar' ? 'aktif' : '' ?>">
            <i class="fas fa-trophy"></i> Turnuvalar
        </a>
        <a href="<?= SITE_URL ?>/yonetim/basvurular.php" class="dashboard-menu-link <?= $aktifSekme === 'basvurular' ? 'aktif' : '' ?>">
            <i class="fas fa-file-signature"></i> Başvurular
        </a>
        <a href="<?= SITE_URL ?>/yonetim/maclar.php" class="dashboard-menu-link <?= $aktifSekme === 'maclar' ? 'aktif' : '' ?>">
            <i class="fas fa-gamepad"></i> Maçlar
        </a>
        <?php
        $itirazSayisi = $db->count("SELECT COUNT(*) FROM maclar WHERE dispute_status = 'pending'");
        ?>
        <a href="<?= SITE_URL ?>/yonetim/itirazlar.php" class="dashboard-menu-link <?= $aktifSekme === 'itirazlar' ? 'aktif' : '' ?>" style="display:flex; align-items:center; justify-content:space-between; width:100%;">
            <span><i class="fas fa-gavel"></i> İtirazlar</span>
            <?php if ($itirazSayisi > 0): ?>
                <span class="badge" style="background:var(--live); color:var(--text-white); font-size:0.7rem; padding:2px 6px; border-radius:10px;"><?= $itirazSayisi ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= SITE_URL ?>/yonetim/takimlar.php" class="dashboard-menu-link <?= $aktifSekme === 'takimlar' ? 'aktif' : '' ?>">
            <i class="fas fa-shield-halved"></i> Takımlar
        </a>
        <a href="<?= SITE_URL ?>/yonetim/kullanicilar.php" class="dashboard-menu-link <?= $aktifSekme === 'kullanicilar' ? 'aktif' : '' ?>">
            <i class="fas fa-users"></i> Kullanıcılar
        </a>
        <a href="<?= SITE_URL ?>/yonetim/haberler.php" class="dashboard-menu-link <?= $aktifSekme === 'haberler' ? 'aktif' : '' ?>">
            <i class="fas fa-newspaper"></i> Haberler
        </a>
        <a href="<?= SITE_URL ?>/yonetim/yorumlar.php" class="dashboard-menu-link <?= $aktifSekme === 'yorumlar' ? 'aktif' : '' ?>">
            <i class="fas fa-comments"></i> Yorumlar
        </a>
        <a href="<?= SITE_URL ?>/yonetim/oyunlar.php" class="dashboard-menu-link <?= $aktifSekme === 'oyunlar' ? 'aktif' : '' ?>">
            <i class="fas fa-puzzle-piece"></i> Oyunlar
        </a>
        <a href="<?= SITE_URL ?>/yonetim/sponsorlar.php" class="dashboard-menu-link <?= $aktifSekme === 'sponsorlar' ? 'aktif' : '' ?>">
            <i class="fas fa-handshake"></i> Sponsorlar
        </a>
        
        <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);margin:var(--space-md) 0 var(--space-xs) var(--space-sm);">Sistem</div>
        
        <a href="<?= SITE_URL ?>/" class="dashboard-menu-link">
            <i class="fas fa-external-link-alt"></i> Siteyi Gör
        </a>
        <hr style="border-color:var(--border-light);margin:var(--space-sm) 0;">
        <a href="<?= SITE_URL ?>/cikis.php" class="dashboard-menu-link" style="color:var(--live);">
            <i class="fas fa-sign-out-alt"></i> Çıkış Yap
        </a>
    </nav>
</aside>
