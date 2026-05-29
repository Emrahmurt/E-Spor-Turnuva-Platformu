<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';
adminGerekli();

$kullanici = mevcutKullanici();

// İstatistikler
$toplamKullanici = $db->count("SELECT COUNT(*) FROM kullanicilar");
$toplamTakim = $db->count("SELECT COUNT(*) FROM takimlar");
$toplamTurnuva = $db->count("SELECT COUNT(*) FROM turnuvalar");
$aktifMac = $db->count("SELECT COUNT(*) FROM maclar WHERE status = 'live'");
$sonKayitlar = $db->fetchAll("SELECT tr.*, t.name as turnuva_adi, tm.name as takim_adi FROM turnuva_kayitlari tr LEFT JOIN turnuvalar t ON tr.tournament_id = t.id LEFT JOIN takimlar tm ON tr.team_id = tm.id ORDER BY tr.registered_at DESC LIMIT 10");
$sonKullanicilar = $db->fetchAll("SELECT * FROM kullanicilar ORDER BY created_at DESC LIMIT 5");

$sayfaBasligi = 'Yönetim Paneli';
require_once dirname(__DIR__) . '/includes/ust.php';
?>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <div class="dashboard-grid">
            <!-- Sidebar -->
            <?php 
            $aktifSekme = 'dashboard';
            require_once __DIR__ . '/sidebar.php'; 
            ?>

            <!-- Ana İçerik -->
            <div class="dashboard-icerik">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xl);padding-bottom:var(--space-lg);border-bottom:1px solid var(--border-light)">
                    <h2 style="font-size:1.5rem;"><i class="fas fa-tachometer-alt" style="color:var(--accent)"></i> Yönetim Paneli</h2>
                    <span style="color:var(--text-secondary);font-size:0.85rem;">Merhaba, <strong style="color:var(--text-white)"><?= temizle($kullanici['username']) ?></strong></span>
                </div>

                <div class="dashboard-stat-grid" style="margin-bottom:var(--space-2xl);">
                    <div class="dashboard-stat-kart">
                        <div class="dashboard-stat-ikon mor"><i class="fas fa-users"></i></div>
                        <div class="dashboard-stat-bilgi"><h3><?= $toplamKullanici ?></h3><span>Kullanıcı</span></div>
                    </div>
                    <div class="dashboard-stat-kart">
                        <div class="dashboard-stat-ikon yesil"><i class="fas fa-shield-halved"></i></div>
                        <div class="dashboard-stat-bilgi"><h3><?= $toplamTakim ?></h3><span>Takım</span></div>
                    </div>
                    <div class="dashboard-stat-kart">
                        <div class="dashboard-stat-ikon kirmizi"><i class="fas fa-trophy"></i></div>
                        <div class="dashboard-stat-bilgi"><h3><?= $toplamTurnuva ?></h3><span>Turnuva</span></div>
                    </div>
                    <div class="dashboard-stat-kart">
                        <div class="dashboard-stat-ikon mavi"><i class="fas fa-broadcast-tower"></i></div>
                        <div class="dashboard-stat-bilgi"><h3><?= $aktifMac ?></h3><span>Canlı Maç</span></div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:2fr 1fr;gap:var(--space-xl);">
                    <div class="kart" style="padding:var(--space-xl);">
                        <h3 style="margin-bottom:var(--space-lg);"><i class="fas fa-clipboard-list" style="color:var(--accent)"></i> Son Kayıtlar</h3>
                        <div class="tablo-wrapper">
                            <table class="tablo">
                                <thead>
                                    <tr>
                                        <th>Takım</th>
                                        <th>Turnuva</th>
                                        <th>Durum</th>
                                        <th>Tarih</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($sonKayitlar as $k): ?>
                                    <tr>
                                        <td style="font-weight:600;"><?= temizle($k['takim_adi']) ?></td>
                                        <td class="wrap-text"><?= temizle($k['turnuva_adi']) ?></td>
                                        <td><?= durumBadge($k['status']) ?></td>
                                        <td style="color:var(--text-muted);font-size:0.8rem;"><?= zamanOnce($k['registered_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="kart" style="padding:var(--space-xl);">
                        <h3 style="margin-bottom:var(--space-lg);"><i class="fas fa-user-plus" style="color:var(--neon)"></i> Son Üyeler</h3>
                        <?php foreach ($sonKullanicilar as $u): ?>
                        <div style="display:flex;align-items:center;gap:var(--space-sm);padding:var(--space-sm) 0;border-bottom:1px solid var(--border-light);">
                            <div style="width:32px;height:32px;border-radius:50%;background:var(--bg-surface);display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-user" style="color:var(--text-muted);font-size:0.7rem;"></i>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:0.85rem;"><?= temizle($u['username']) ?></div>
                                <div style="color:var(--text-muted);font-size:0.72rem;"><?= zamanOnce($u['created_at']) ?></div>
                            </div>
                            <span class="badge badge-<?= $u['role']==='admin'?'warning':($u['role']==='player'?'primary':'secondary') ?>" style="margin-left:auto;font-size:0.6rem;"><?= rolCevir($u['role'], 'user') ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/alt.php'; ?>
