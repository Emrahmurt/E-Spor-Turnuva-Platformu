<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/kimlik.php';
require_once __DIR__ . '/includes/fonksiyonlar.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . SITE_URL . '/turnuvalar.php'); exit; }

$turnuva = $db->fetch("SELECT t.*, g.name as oyun_adi, g.slug as oyun_slug, u.username as olusturan
    FROM turnuvalar t LEFT JOIN oyunlar g ON t.game_id = g.id LEFT JOIN kullanicilar u ON t.created_by = u.id
    WHERE t.id = ?", [$id]);

if (!$turnuva) { header('Location: ' . SITE_URL . '/turnuvalar.php'); exit; }

// Sponsorları ve ödül katkılarını yükle
$sponsors = $db->fetchAll("
    SELECT s.*, ts.prize_contribution 
    FROM turnuva_sponsorlari ts 
    JOIN sponsorlar s ON ts.sponsor_id = s.id 
    WHERE ts.tournament_id = ?
    ORDER BY s.name
", [$id]);

$totalPrizeContribution = 0.0;
foreach ($sponsors as $s) {
    $totalPrizeContribution += (float)$s['prize_contribution'];
}
$toplamOdulHavuzu = (float)$turnuva['prize_pool'] + $totalPrizeContribution;

// Görüntüleme sayısını artır
$db->query("UPDATE turnuvalar SET views = views + 1 WHERE id = ?", [$id]);

$sayfaBasligi = $turnuva['name'];
$sayfaAciklama = mb_substr(strip_tags($turnuva['description']), 0, 160);

// Kayıtlı takımlar
$kayitliTakimlar = $db->fetchAll("SELECT tr.*, t.name as takim_adi, t.logo, t.slug, t.points, g2.name as takim_oyun
    FROM turnuva_kayitlari tr
    LEFT JOIN takimlar t ON tr.team_id = t.id LEFT JOIN oyunlar g2 ON t.game_id = g2.id
    WHERE tr.tournament_id = ? ORDER BY tr.seed ASC, tr.registered_at ASC", [$id]);

// Maçlar
$maclar = $db->fetchAll("SELECT m.*, t1.name as takim1_adi, t1.logo as takim1_logo,
    t2.name as takim2_adi, t2.logo as takim2_logo
    FROM maclar m LEFT JOIN takimlar t1 ON m.team1_id = t1.id LEFT JOIN takimlar t2 ON m.team2_id = t2.id
    WHERE m.tournament_id = ? ORDER BY m.round ASC, m.match_number ASC", [$id]);

// Round'lara göre grupla
$roundlar = [];
foreach ($maclar as $mac) { $roundlar[$mac['round']][] = $mac; }

$tab = $_GET['tab'] ?? 'genel';

require_once __DIR__ . '/includes/ust.php';
?>

<section class="sayfa-basligi">
    <div class="container sayfa-basligi-icerik">
        <div class="breadcrumb" style="margin-bottom:var(--space-sm);">
            <a href="<?= SITE_URL ?>">Ana Sayfa</a> <i class="fas fa-chevron-right"></i>
            <a href="<?= SITE_URL ?>/turnuvalar.php">Turnuvalar</a> <i class="fas fa-chevron-right"></i>
            <span><?= temizle($turnuva['name']) ?></span>
        </div>
        <h1><?= temizle($turnuva['name']) ?></h1>
        <div style="display:flex;gap:var(--space-md);align-items:center;margin-top:var(--space-sm);flex-wrap:wrap;">
            <?= durumBadge($turnuva['status']) ?>
            <span style="color:var(--text-secondary);font-size:0.9rem;"><i class="fas fa-gamepad" style="color:var(--accent)"></i> <?= temizle($turnuva['oyun_adi']) ?></span>
            <span style="color:var(--text-secondary);font-size:0.9rem;"><i class="fas fa-sitemap" style="color:var(--accent)"></i> <?= formatBadge($turnuva['format']) ?></span>
            <span style="color:var(--gold);font-size:1.1rem;font-family:var(--font-heading);font-weight:700;"><i class="fas fa-coins"></i> <?= paraFormatla($toplamOdulHavuzu) ?></span>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <!-- Tab Menü -->
        <div class="tab-menu">
            <a href="?id=<?= $id ?>&tab=genel" class="tab-link <?= $tab==='genel'?'aktif':'' ?>"><i class="fas fa-info-circle"></i> Genel Bakış</a>
            <a href="?id=<?= $id ?>&tab=eslesme" class="tab-link <?= $tab==='eslesme'?'aktif':'' ?>"><i class="fas fa-project-diagram"></i> Eşleşmeler</a>
            <a href="?id=<?= $id ?>&tab=katilimci" class="tab-link <?= $tab==='katilimci'?'aktif':'' ?>"><i class="fas fa-users"></i> Katılımcılar (<?= count($kayitliTakimlar) ?>)</a>
            <a href="?id=<?= $id ?>&tab=kurallar" class="tab-link <?= $tab==='kurallar'?'aktif':'' ?>"><i class="fas fa-gavel"></i> Kurallar</a>
        </div>

        <?php if ($tab === 'genel'): ?>
        <!-- GENEL BAKIŞ -->
        <div class="grid grid-2" style="grid-template-columns: 2fr 1fr;">
            <div>
                <div class="kart" style="padding:var(--space-xl);">
                    <h3 style="margin-bottom:var(--space-md);"><i class="fas fa-info-circle" style="color:var(--accent)"></i> Turnuva Hakkında</h3>
                    <div style="color:var(--text-secondary);line-height:1.8;"><?= nl2br(temizle($turnuva['description'])) ?></div>
                </div>

                <!-- Ödül Derece Dağılımı -->
                <?php if (!empty($turnuva['prize_distribution'])): ?>
                <div class="kart" style="padding:var(--space-xl); margin-top:var(--space-lg);">
                    <h3 style="margin-bottom:var(--space-lg); display:flex; align-items:center; gap:8px;"><i class="fas fa-trophy" style="color:var(--gold)"></i> Ödül Derece Dağılımı</h3>
                    <div style="color:var(--text-secondary); line-height:1.8; white-space:pre-line; background:rgba(255,255,255,0.01); border:1px solid var(--border-light); padding:var(--space-md); border-radius:var(--radius-md);">
                        <?= temizle($turnuva['prize_distribution']) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sponsor Listesi -->
                <?php if (!empty($sponsors)): ?>
                <div class="kart" style="padding:var(--space-xl); margin-top:var(--space-lg);">
                    <h3 style="margin-bottom:var(--space-lg); display:flex; align-items:center; gap:8px;"><i class="fas fa-handshake" style="color:var(--accent)"></i> Turnuva Sponsorları</h3>
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:var(--space-md);">
                        <?php foreach ($sponsors as $s): ?>
                            <a href="<?= $s['link'] ? temizle($s['link']) : '#' ?>" <?= $s['link'] ? 'target="_blank"' : '' ?> class="sponsor-kart" style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:var(--space-md); border-radius:var(--radius-md); border:1px solid var(--border-light); background:rgba(255,255,255,0.01); text-decoration:none; color:inherit; transition:all var(--transition-normal); text-align:center;">
                                <img src="<?= UPLOADS_URL . '/sponsors/' . $s['logo'] ?>" alt="<?= temizle($s['name']) ?>" style="height:50px; max-width:150px; object-fit:contain; filter:grayscale(20%) contrast(110%); transition:filter var(--transition-fast); margin-bottom:var(--space-xs);">
                                <span style="font-weight:600; font-size:0.9rem; margin-top:var(--space-xs);"><?= temizle($s['name']) ?></span>
                                <?php if ($s['prize_contribution'] > 0): ?>
                                    <span style="font-size:0.75rem; color:var(--neon); font-weight:600; margin-top:4px;">Ödül Katkısı: <?= paraFormatla($s['prize_contribution']) ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <style>
                .sponsor-kart:hover {
                    border-color: rgba(var(--accent-rgb), 0.3) !important;
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(var(--accent-rgb), 0.08) !important;
                    background: rgba(var(--accent-rgb), 0.01) !important;
                }
                .sponsor-kart:hover img {
                    filter: grayscale(0%) contrast(100%) !important;
                }
                </style>
                <?php endif; ?>
            </div>
            <div>
                <div class="kart" style="padding:var(--space-xl);">
                    <h3 style="margin-bottom:var(--space-lg);"><i class="fas fa-clipboard-list" style="color:var(--accent)"></i> Detaylar</h3>
                    <div style="display:flex;flex-direction:column;gap:var(--space-md);">
                        <div style="display:flex;justify-content:space-between;padding-bottom:var(--space-sm);border-bottom:1px solid var(--border-light);">
                            <span style="color:var(--text-secondary);">Format</span>
                            <span style="font-weight:600;"><?= formatBadge($turnuva['format']) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding-bottom:var(--space-sm);border-bottom:1px solid var(--border-light);">
                            <span style="color:var(--text-secondary);">Takım Boyutu</span>
                            <span style="font-weight:600;"><?= $turnuva['team_size'] ?>v<?= $turnuva['team_size'] ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding-bottom:var(--space-sm);border-bottom:1px solid var(--border-light);">
                            <span style="color:var(--text-secondary);">Maks. Takım</span>
                            <span style="font-weight:600;"><?= $turnuva['max_teams'] ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding-bottom:var(--space-sm);border-bottom:1px solid var(--border-light);">
                            <span style="color:var(--text-secondary);">Ana Ödül Havuzu</span>
                            <span style="font-weight:600;"><?= paraFormatla($turnuva['prize_pool']) ?></span>
                        </div>
                        <?php if ($totalPrizeContribution > 0): ?>
                        <div style="display:flex;justify-content:space-between;padding-bottom:var(--space-sm);border-bottom:1px solid var(--border-light);">
                            <span style="color:var(--text-secondary);">Sponsor Katkısı</span>
                            <span style="font-weight:600;color:var(--neon);"><?= paraFormatla($totalPrizeContribution) ?></span>
                        </div>
                        <?php endif; ?>
                        <div style="display:flex;justify-content:space-between;padding-bottom:var(--space-sm);border-bottom:1px solid var(--border-light);">
                            <span style="color:var(--text-secondary);">Toplam Ödül</span>
                            <span style="font-weight:700;color:var(--gold);"><?= paraFormatla($toplamOdulHavuzu) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding-bottom:var(--space-sm);border-bottom:1px solid var(--border-light);">
                            <span style="color:var(--text-secondary);">Giriş Ücreti</span>
                            <span style="font-weight:600;"><?= $turnuva['entry_fee'] > 0 ? paraFormatla($turnuva['entry_fee']) : 'Ücretsiz' ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding-bottom:var(--space-sm);border-bottom:1px solid var(--border-light);">
                            <span style="color:var(--text-secondary);">Başlangıç</span>
                            <span style="font-weight:600;"><?= tarihFormatla($turnuva['start_date']) ?></span>
                        </div>
                        <?php if ($turnuva['end_date']): ?>
                        <div style="display:flex;justify-content:space-between;padding-bottom:var(--space-sm);border-bottom:1px solid var(--border-light);">
                            <span style="color:var(--text-secondary);">Bitiş</span>
                            <span style="font-weight:600;"><?= tarihFormatla($turnuva['end_date']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--text-secondary);">Kayıtlı Takım</span>
                            <span style="font-weight:600;"><?= count(array_filter($kayitliTakimlar, fn($k)=>$k['status']==='approved')) ?>/<?= $turnuva['max_teams'] ?></span>
                        </div>
                    </div>
                    <?php if ($turnuva['status'] === 'registration' && girisYapmisMi()): ?>
                        <a href="<?= SITE_URL ?>/turnuva-kayit.php?turnuva_id=<?= $id ?>" class="btn btn-neon btn-block" style="margin-top:var(--space-lg);">
                            <i class="fas fa-plus-circle"></i> Turnuvaya Katıl
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php elseif ($tab === 'eslesme'): ?>
        <!-- EŞLEŞMELER -->
        <?php if (empty($maclar)): ?>
            <div class="bos-durum"><i class="fas fa-project-diagram"></i><h3>Henüz eşleşme oluşturulmadı</h3></div>
        <?php else: ?>
            <?php
            $round1MacCount = count($roundlar[1] ?? []);
            $bracketHeight = max(340, $round1MacCount * 140 + 60);
            $toplamRound = max(array_keys($roundlar));
            ?>
            <div class="bracket-wrapper">
                <div class="bracket" style="height: <?= $bracketHeight ?>px;">
                    <?php foreach ($roundlar as $round => $roundMaclari): ?>
                    <div class="bracket-round round-<?= $round ?> <?= ($round == $toplamRound) ? 'round-final' : '' ?>">
                        <div class="bracket-round-baslik">
                            <?php
                            if ($round == $toplamRound) echo 'Final';
                            elseif ($round == $toplamRound - 1) echo 'Yarı Final';
                            else echo "$round. Tur";
                            ?>
                        </div>
                        <?php foreach ($roundMaclari as $mac): ?>
                        <div class="bracket-mac" onclick="location.href='<?= SITE_URL ?>/mac.php?id=<?= $mac['id'] ?>'" style="cursor:pointer;" title="Maç detaylarını gör">
                            <div class="bracket-takim <?= $mac['winner_id'] == $mac['team1_id'] && $mac['winner_id'] ? 'kazanan' : '' ?>">
                                <div class="bracket-takim-logo">
                                    <?php if (!empty($mac['takim1_logo']) && $mac['takim1_logo'] !== 'default-team.png'): ?>
                                        <img src="<?= SITE_URL ?>/<?= temizle($mac['takim1_logo']) ?>" alt="<?= temizle($mac['takim1_adi']) ?>">
                                    <?php else: ?>
                                        <i class="fas fa-shield-halved"></i>
                                    <?php endif; ?>
                                </div>
                                <span class="bracket-takim-isim">
                                    <?php if ($mac['team1_id']): ?>
                                        <a href="<?= SITE_URL ?>/takim.php?id=<?= $mac['team1_id'] ?>"><?= temizle($mac['takim1_adi']) ?></a>
                                    <?php else: ?>
                                        Belli Değil
                                    <?php endif; ?>
                                </span>
                                <span class="bracket-takim-skor"><?= $mac['score1'] ?></span>
                            </div>
                            <div class="bracket-takim <?= $mac['winner_id'] == $mac['team2_id'] && $mac['winner_id'] ? 'kazanan' : '' ?>">
                                <div class="bracket-takim-logo">
                                    <?php if (!empty($mac['takim2_logo']) && $mac['takim2_logo'] !== 'default-team.png'): ?>
                                        <img src="<?= SITE_URL ?>/<?= temizle($mac['takim2_logo']) ?>" alt="<?= temizle($mac['takim2_adi']) ?>">
                                    <?php else: ?>
                                        <i class="fas fa-shield-halved"></i>
                                    <?php endif; ?>
                                </div>
                                <span class="bracket-takim-isim">
                                    <?php if ($mac['team2_id']): ?>
                                        <a href="<?= SITE_URL ?>/takim.php?id=<?= $mac['team2_id'] ?>"><?= temizle($mac['takim2_adi']) ?></a>
                                    <?php else: ?>
                                        Belli Değil
                                    <?php endif; ?>
                                </span>
                                <span class="bracket-takim-skor"><?= $mac['score2'] ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php elseif ($tab === 'katilimci'): ?>
        <!-- KATILIMCILAR -->
        <?php if (empty($kayitliTakimlar)): ?>
            <div class="bos-durum"><i class="fas fa-users"></i><h3>Henüz katılımcı yok</h3></div>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($kayitliTakimlar as $i => $k): ?>
                <div class="takim-kart fade-in">
                    <div class="takim-kart-logo">
                        <?php if (!empty($k['logo']) && $k['logo'] !== 'default-team.png'): ?>
                            <img src="<?= SITE_URL ?>/<?= temizle($k['logo']) ?>" alt="<?= temizle($k['takim_adi']) ?>">
                        <?php else: ?>
                            <i class="fas fa-shield-halved"></i>
                        <?php endif; ?>
                    </div>
                    <h3 class="takim-kart-isim"><a href="<?= SITE_URL ?>/takim.php?id=<?= $k['team_id'] ?>"><?= temizle($k['takim_adi']) ?></a></h3>
                    <div class="takim-kart-oyun"><?= durumBadge($k['status']) ?></div>
                    <div class="takim-kart-istatistik">
                        <div class="takim-stat"><span class="takim-stat-sayi"><?= $k['points'] ?></span><span class="takim-stat-etiket">Puan</span></div>
                        <div class="takim-stat"><span class="takim-stat-sayi">#<?= $k['seed'] ?? ($i+1) ?></span><span class="takim-stat-etiket">Sıra</span></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php elseif ($tab === 'kurallar'): ?>
        <!-- KURALLAR -->
        <div class="kart" style="padding:var(--space-xl);">
            <h3 style="margin-bottom:var(--space-lg);"><i class="fas fa-gavel" style="color:var(--accent)"></i> Turnuva Kuralları</h3>
            <?php if ($turnuva['rules']): ?>
                <div style="color:var(--text-secondary);line-height:2;white-space:pre-line;"><?= temizle($turnuva['rules']) ?></div>
            <?php else: ?>
                <p style="color:var(--text-muted);">Kurallar henüz eklenmemiş.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
