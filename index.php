<?php
$sayfaBasligi = 'Ana Sayfa';
$sayfaAciklama = 'Türkiye\'nin en profesyonel e-spor turnuva platformu. Turnuvalara katıl, takımını kur, şampiyon ol!';
require_once __DIR__ . '/includes/ust.php';

// İstatistikler
$toplamTurnuva = $db->count("SELECT COUNT(*) FROM turnuvalar");
$toplamOyuncu = $db->count("SELECT COUNT(*) FROM kullanicilar WHERE role='player'");
$toplamTakim = $db->count("SELECT COUNT(*) FROM takimlar");
$toplamMac = $db->count("SELECT COUNT(*) FROM maclar");

// Canlı maçlar
$canliMaclar = $db->fetchAll("SELECT m.*, 
    t1.name as takim1_adi, t1.logo as takim1_logo,
    t2.name as takim2_adi, t2.logo as takim2_logo,
    tr.name as turnuva_adi, g.name as oyun_adi
    FROM maclar m
    LEFT JOIN takimlar t1 ON m.team1_id = t1.id
    LEFT JOIN takimlar t2 ON m.team2_id = t2.id
    LEFT JOIN turnuvalar tr ON m.tournament_id = tr.id
    LEFT JOIN oyunlar g ON tr.game_id = g.id
    WHERE m.status = 'live'
    ORDER BY m.started_at DESC LIMIT 4");

// Öne çıkan turnuvalar
$oneCikanTurnuvalar = $db->fetchAll("SELECT t.*, g.name as oyun_adi, g.slug as oyun_slug,
    (SELECT COUNT(*) FROM turnuva_kayitlari WHERE tournament_id = t.id AND status = 'approved') as kayitli_takim
    FROM turnuvalar t
    LEFT JOIN oyunlar g ON t.game_id = g.id
    WHERE t.status IN ('registration','upcoming','ongoing')
    ORDER BY t.is_featured DESC, t.start_date ASC LIMIT 4");

// Son haberler
$sonHaberler = $db->fetchAll("SELECT n.*, u.username as yazar_adi 
    FROM haberler n LEFT JOIN kullanicilar u ON n.author_id = u.id
    WHERE n.is_published = 1 ORDER BY n.created_at DESC LIMIT 3");

// Popüler oyunlar
$oyunlar = $db->fetchAll("SELECT g.*, 
    (SELECT COUNT(*) FROM turnuvalar WHERE game_id = g.id) as turnuva_sayisi
    FROM oyunlar g WHERE g.is_active = 1 ORDER BY turnuva_sayisi DESC");
?>

<!-- HERO BÖLÜMÜ -->
<section class="hero">
    <div class="hero-grid-bg"></div>
    <div class="container">
        <div class="hero-icerik fade-in">
            <div class="hero-badge">
                <i class="fas fa-gamepad"></i> #1 E-SPOR PLATFORMU
            </div>
            <h1 class="hero-baslik">
                Turnuvanı Bul,<br>
                Takımını Kur,<br>
                <span>Şampiyon Ol!</span>
            </h1>
            <p class="hero-aciklama">
                Türkiye'nin en profesyonel e-spor turnuva platformuna hoş geldiniz. 
                Yüzlerce turnuva, binlerce oyuncu ve rekabetçi bir topluluk sizi bekliyor.
            </p>
            <div class="hero-butonlar">
                <a href="<?= SITE_URL ?>/turnuvalar.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-trophy"></i> Turnuvaları Keşfet
                </a>
                <a href="<?= SITE_URL ?>/kayit.php" class="btn btn-ghost btn-lg">
                    <i class="fas fa-user-plus"></i> Kayıt Ol
                </a>
            </div>
            <div class="hero-istatistik">
                <div class="hero-stat">
                    <span class="hero-stat-sayi" data-sayac="<?= $toplamTurnuva ?>">0</span>
                    <span class="hero-stat-etiket">Turnuva</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-sayi" data-sayac="<?= $toplamOyuncu ?>">0</span>
                    <span class="hero-stat-etiket">Oyuncu</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-sayi" data-sayac="<?= $toplamTakim ?>">0</span>
                    <span class="hero-stat-etiket">Takım</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-sayi" data-sayac="<?= $toplamMac ?>">0</span>
                    <span class="hero-stat-etiket">Maç</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- İSTATİSTİK BAR -->
<section class="istatistik-bar">
    <div class="container">
        <div class="istatistik-grid">
            <div class="istatistik-kutu fade-in">
                <div class="istatistik-ikon"><i class="fas fa-trophy"></i></div>
                <div class="istatistik-sayi" data-sayac="<?= $toplamTurnuva ?>">0</div>
                <div class="istatistik-etiket">Toplam Turnuva</div>
            </div>
            <div class="istatistik-kutu fade-in">
                <div class="istatistik-ikon" style="background:var(--neon-light);color:var(--neon)"><i class="fas fa-users"></i></div>
                <div class="istatistik-sayi" data-sayac="<?= $toplamOyuncu ?>">0</div>
                <div class="istatistik-etiket">Aktif Oyuncu</div>
            </div>
            <div class="istatistik-kutu fade-in">
                <div class="istatistik-ikon" style="background:rgba(255,171,0,0.15);color:var(--warning)"><i class="fas fa-shield-halved"></i></div>
                <div class="istatistik-sayi" data-sayac="<?= $toplamTakim ?>">0</div>
                <div class="istatistik-etiket">Kayıtlı Takım</div>
            </div>
            <div class="istatistik-kutu fade-in">
                <div class="istatistik-ikon" style="background:rgba(255,82,82,0.15);color:var(--danger)"><i class="fas fa-crosshairs"></i></div>
                <div class="istatistik-sayi" data-sayac="<?= $toplamMac ?>">0</div>
                <div class="istatistik-etiket">Oynanan Maç</div>
            </div>
        </div>
    </div>
</section>

<!-- CANLI MAÇLAR -->
<?php if (!empty($canliMaclar)): ?>
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-broadcast-tower"></i> Canlı Maçlar</h2>
        </div>
        <div class="grid grid-2">
            <?php foreach ($canliMaclar as $mac): ?>
            <div class="canli-mac-kart canli fade-in">
                <div class="canli-mac-ust">
                    <span class="canli-mac-turnuva"><i class="fas fa-trophy"></i> <?= temizle($mac['turnuva_adi']) ?></span>
                    <?= durumBadge('live') ?>
                </div>
                <div class="canli-mac-takimlar">
                    <div class="canli-mac-takim">
                        <div class="canli-mac-takim-logo">
                            <?php if (!empty($mac['takim1_logo']) && $mac['takim1_logo'] !== 'default-team.png'): ?>
                                <img src="<?= SITE_URL ?>/<?= temizle($mac['takim1_logo']) ?>" alt="<?= temizle($mac['takim1_adi']) ?>">
                            <?php else: ?>
                                <i class="fas fa-shield-halved"></i>
                            <?php endif; ?>
                        </div>
                        <div class="canli-mac-takim-isim"><?= temizle($mac['takim1_adi'] ?? 'TBD') ?></div>
                    </div>
                    <div class="canli-mac-skor">
                        <span class="canli-mac-skor-sayi"><?= $mac['score1'] ?></span>
                        <span class="canli-mac-skor-ayrac">:</span>
                        <span class="canli-mac-skor-sayi"><?= $mac['score2'] ?></span>
                    </div>
                    <div class="canli-mac-takim">
                        <div class="canli-mac-takim-logo">
                            <?php if (!empty($mac['takim2_logo']) && $mac['takim2_logo'] !== 'default-team.png'): ?>
                                <img src="<?= SITE_URL ?>/<?= temizle($mac['takim2_logo']) ?>" alt="<?= temizle($mac['takim2_adi']) ?>">
                            <?php else: ?>
                                <i class="fas fa-shield-halved"></i>
                            <?php endif; ?>
                        </div>
                        <div class="canli-mac-takim-isim"><?= temizle($mac['takim2_adi'] ?? 'TBD') ?></div>
                    </div>
                </div>
                <div class="canli-mac-bilgi">
                    <i class="fas fa-gamepad"></i> <?= temizle($mac['oyun_adi']) ?> • BO<?= $mac['best_of'] ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ÖNE ÇIKAN TURNUVALAR -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-trophy"></i> Yaklaşan Turnuvalar</h2>
            <a href="<?= SITE_URL ?>/turnuvalar.php" class="btn btn-ghost btn-sm">Tümünü Gör <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="grid grid-2">
            <?php foreach ($oneCikanTurnuvalar as $turnuva): ?>
            <div class="turnuva-kart fade-in">
                <div class="turnuva-kart-banner">
                    <?php if (!empty($turnuva['banner_image'])): ?>
                        <img src="<?= SITE_URL ?>/<?= temizle($turnuva['banner_image']) ?>" alt="<?= temizle($turnuva['name']) ?>">
                    <?php else: ?>
                        <i class="fas fa-trophy oyun-ikon"></i>
                    <?php endif; ?>
                    <div class="turnuva-kart-durum"><?= durumBadge($turnuva['status']) ?></div>
                    <div class="turnuva-kart-oyun"><i class="fas fa-gamepad"></i> <?= temizle($turnuva['oyun_adi']) ?></div>
                </div>
                <div class="turnuva-kart-icerik">
                    <h3 class="turnuva-kart-baslik">
                        <a href="<?= SITE_URL ?>/turnuva.php?id=<?= $turnuva['id'] ?>"><?= temizle($turnuva['name']) ?></a>
                    </h3>
                    <div class="turnuva-kart-bilgi">
                        <div class="turnuva-bilgi-item"><i class="fas fa-calendar"></i> <?= tarihFormatla($turnuva['start_date'], 'short') ?></div>
                        <div class="turnuva-bilgi-item"><i class="fas fa-users"></i> <?= $turnuva['kayitli_takim'] ?>/<?= $turnuva['max_teams'] ?> Takım</div>
                        <div class="turnuva-bilgi-item"><i class="fas fa-sitemap"></i> <?= formatBadge($turnuva['format']) ?></div>
                        <div class="turnuva-bilgi-item"><i class="fas fa-gamepad"></i> <?= $turnuva['team_size'] ?>v<?= $turnuva['team_size'] ?></div>
                    </div>
                </div>
                <div class="turnuva-kart-footer">
                    <div class="turnuva-odul"><i class="fas fa-coins"></i> <?= paraFormatla($turnuva['prize_pool']) ?></div>
                    <a href="<?= SITE_URL ?>/turnuva.php?id=<?= $turnuva['id'] ?>" class="btn btn-primary btn-sm">Detaylar</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- SON HABERLER -->
<section class="section" style="background: var(--bg-secondary);">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-newspaper"></i> Son Haberler</h2>
            <a href="<?= SITE_URL ?>/haberler.php" class="btn btn-ghost btn-sm">Tüm Haberler <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="grid grid-3">
            <?php foreach ($sonHaberler as $haber): ?>
            <div class="haber-kart fade-in">
                <div class="haber-kart-resim">
                    <?php $hImg = haberResimURL($haber['featured_image']); ?>
                    <?php if ($hImg): ?>
                        <img src="<?= $hImg ?>" alt="<?= temizle($haber['title']) ?>">
                    <?php else: ?>
                        <i class="fas fa-newspaper ikon-placeholder"></i>
                    <?php endif; ?>
                    <div class="haber-kart-kategori"><?= durumBadge($haber['category'] === 'turnuva' ? 'warning' : ($haber['category'] === 'esports' ? 'info' : 'primary')) ?></div>
                </div>
                <div class="haber-kart-icerik">
                    <div class="haber-kart-tarih"><i class="fas fa-clock"></i> <?= tarihFormatla($haber['created_at'], 'relative') ?></div>
                    <h3 class="haber-kart-baslik">
                        <a href="<?= SITE_URL ?>/haber-detay.php?id=<?= $haber['id'] ?>"><?= temizle($haber['title']) ?></a>
                    </h3>
                    <p class="haber-kart-ozet"><?= temizle($haber['excerpt']) ?></p>
                </div>
                <div class="haber-kart-footer">
                    <span><i class="fas fa-eye"></i> <?= kisaSayi($haber['views']) ?></span>
                    <a href="<?= SITE_URL ?>/haber-detay.php?id=<?= $haber['id'] ?>">Devamını Oku <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- POPÜLER OYUNLAR -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-gamepad"></i> Popüler Oyunlar</h2>
        </div>
        <div class="oyun-grid">
            <?php foreach ($oyunlar as $oyun): 
                // Define premium glowing shadows and details for each game logo
                $gameGlows = [
                    'league-of-legends' => ['color' => '#c89b3c', 'alpha' => 'rgba(200, 155, 60, 0.08)', 'shadow' => 'rgba(200, 155, 60, 0.25)'],
                    'valorant'          => ['color' => '#ff4655', 'alpha' => 'rgba(255, 70, 85, 0.08)', 'shadow' => 'rgba(255, 70, 85, 0.25)'],
                    'cs2'               => ['color' => '#de9b35', 'alpha' => 'rgba(222, 155, 53, 0.08)', 'shadow' => 'rgba(222, 155, 53, 0.25)'],
                    'dota-2'            => ['color' => '#f53d3d', 'alpha' => 'rgba(245, 61, 61, 0.08)', 'shadow' => 'rgba(245, 61, 61, 0.25)'],
                    'ea-fc-25'          => ['color' => '#11f26a', 'alpha' => 'rgba(17, 242, 106, 0.08)', 'shadow' => 'rgba(17, 242, 106, 0.25)'],
                    'fortnite'          => ['color' => '#bf36ff', 'alpha' => 'rgba(191, 54, 255, 0.08)', 'shadow' => 'rgba(191, 54, 255, 0.25)'],
                    'pubg'              => ['color' => '#f2a900', 'alpha' => 'rgba(242, 169, 0, 0.08)', 'shadow' => 'rgba(242, 169, 0, 0.25)'],
                    'rocket-league'     => ['color' => '#008cff', 'alpha' => 'rgba(0, 140, 255, 0.08)', 'shadow' => 'rgba(0, 140, 255, 0.25)'],
                    'overwatch-2'       => ['color' => '#f99e1a', 'alpha' => 'rgba(249, 158, 26, 0.08)', 'shadow' => 'rgba(249, 158, 26, 0.25)'],
                    'apex-legends'      => ['color' => '#da292a', 'alpha' => 'rgba(218, 41, 42, 0.08)', 'shadow' => 'rgba(218, 41, 42, 0.25)'],
                ];
                $glow = $gameGlows[$oyun['slug']] ?? ['color' => 'var(--accent)', 'alpha' => 'var(--accent-light)', 'shadow' => 'var(--shadow-glow)'];
            ?>
            <a href="<?= SITE_URL ?>/turnuvalar.php?oyun=<?= $oyun['slug'] ?>" class="oyun-kategori-kart fade-in" style="--game-glow: <?= $glow['color'] ?>; --game-glow-alpha: <?= $glow['alpha'] ?>; --game-glow-shadow: <?= $glow['shadow'] ?>;">
                <div class="oyun-kategori-logo-container">
                    <?php if (file_exists(__DIR__ . "/assets/img/oyunlar/" . $oyun['slug'] . ".svg")): ?>
                        <img src="<?= SITE_URL ?>/assets/img/oyunlar/<?= $oyun['slug'] ?>.svg" alt="<?= temizle($oyun['name']) ?>" class="oyun-kategori-logo">
                    <?php else: ?>
                        <div style="font-size: 2.5rem;">🎮</div>
                    <?php endif; ?>
                </div>
                <h4 class="oyun-kategori-baslik"><?= temizle($oyun['name']) ?></h4>
                <span class="oyun-kategori-sayi"><?= $oyun['turnuva_sayisi'] ?> Turnuva</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
