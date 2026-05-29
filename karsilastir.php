<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/kimlik.php';
require_once __DIR__ . '/includes/fonksiyonlar.php';

// Tüm aktif oyuncuları listele (dropdown için)
$oyuncular = $db->fetchAll("SELECT id, username, first_name, last_name FROM kullanicilar WHERE is_active = 1 AND role IN ('user', 'player') ORDER BY username ASC");

$p1_id = (int)($_GET['p1'] ?? 0);
$p2_id = (int)($_GET['p2'] ?? 0);

$p1 = null;
$p2 = null;

if ($p1_id) {
    $p1 = $db->fetch("SELECT * FROM kullanicilar WHERE id = ? AND is_active = 1", [$p1_id]);
    if ($p1) {
        $p1['takim_sayisi'] = $db->count("SELECT COUNT(*) FROM takim_uyeleri WHERE user_id = ?", [$p1_id]);
        $p1['toplam_mac'] = $p1['total_wins'] + $p1['total_losses'];
        $p1['kazanma_orani'] = $p1['toplam_mac'] > 0 ? round(($p1['total_wins'] / $p1['toplam_mac']) * 100, 1) : 0;
    }
}

if ($p2_id) {
    $p2 = $db->fetch("SELECT * FROM kullanicilar WHERE id = ? AND is_active = 1", [$p2_id]);
    if ($p2) {
        $p2['takim_sayisi'] = $db->count("SELECT COUNT(*) FROM takim_uyeleri WHERE user_id = ?", [$p2_id]);
        $p2['toplam_mac'] = $p2['total_wins'] + $p2['total_losses'];
        $p2['kazanma_orani'] = $p2['toplam_mac'] > 0 ? round(($p2['total_wins'] / $p2['toplam_mac']) * 100, 1) : 0;
    }
}

$sayfaBasligi = 'Oyuncu Karşılaştırma';
require_once __DIR__ . '/includes/ust.php';
?>

<section class="section" style="padding-top:var(--space-2xl);">
    <div class="container">
        <div style="margin-bottom:var(--space-xl); text-align:center;">
            <h1 style="font-size:2.2rem; font-family:var(--font-heading); margin-bottom:var(--space-xs);"><i class="fas fa-balance-scale" style="color:var(--accent)"></i> Oyuncu Karşılaştırma</h1>
            <p style="color:var(--text-secondary);">İki oyuncuyu yan yana kıyaslayın ve detaylı performans istatistiklerini analiz edin.</p>
        </div>

        <!-- Oyuncu Seçim Alanı -->
        <form method="GET" action="" id="karsilastirForm">
            <div class="compare-select-container">
                <div class="compare-select-box">
                    <label class="form-label" style="font-weight:600;">1. Oyuncu</label>
                    <select name="p1" class="form-input" onchange="this.form.submit()" style="cursor:pointer; background:var(--bg-surface);">
                        <option value="">Oyuncu Seçiniz...</option>
                        <?php foreach ($oyuncular as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= $p1_id === (int)$o['id'] ? 'selected' : '' ?>>
                                <?= temizle($o['username']) ?> (<?= temizle($o['first_name'] . ' ' . $o['last_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="compare-vs-badge">VS</div>
                
                <div class="compare-select-box">
                    <label class="form-label" style="font-weight:600;">2. Oyuncu</label>
                    <select name="p2" class="form-input" onchange="this.form.submit()" style="cursor:pointer; background:var(--bg-surface);">
                        <option value="">Oyuncu Seçiniz...</option>
                        <?php foreach ($oyuncular as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= $p2_id === (int)$o['id'] ? 'selected' : '' ?>>
                                <?= temizle($o['username']) ?> (<?= temizle($o['first_name'] . ' ' . $o['last_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <?php if ($p1 && $p2): ?>
            <!-- Oyuncu Bilgi Kartları -->
            <div class="compare-grid">
                <!-- 1. Oyuncu Kartı -->
                <div class="compare-card fade-in">
                    <div class="compare-avatar">
                        <img src="<?= avatarURL($p1['avatar'] ?? '') ?>" alt="<?= temizle($p1['username']) ?>">
                    </div>
                    <h2 class="compare-name"><a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $p1['id'] ?>" style="color:inherit;"><?= temizle($p1['username']) ?></a></h2>
                    <div class="compare-realname"><?= temizle(($p1['first_name'] ?? '') . ' ' . ($p1['last_name'] ?? '')) ?></div>
                    
                    <div class="compare-meta">
                        <?php if ($p1['country']): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?= temizle($p1['country']) ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-calendar"></i> <?= tarihFormatla($p1['created_at'], 'short') ?></span>
                    </div>

                    <?php if ($p1['bio']): ?>
                        <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:var(--space-md); font-style:italic;">
                            "<?= temizle($p1['bio']) ?>"
                        </p>
                    <?php endif; ?>

                    <div class="compare-socials">
                        <?php if ($p1['discord']): ?>
                            <a href="#" class="compare-social-btn" title="Discord: <?= temizle($p1['discord']) ?>"><i class="fab fa-discord"></i></a>
                        <?php endif; ?>
                        <?php if ($p1['steam_id']): ?>
                            <a href="https://steamcommunity.com/profiles/<?= temizle($p1['steam_id']) ?>" target="_blank" class="compare-social-btn" title="Steam"><i class="fab fa-steam"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Oyuncu Kartı -->
                <div class="compare-card fade-in">
                    <div class="compare-avatar">
                        <img src="<?= avatarURL($p2['avatar'] ?? '') ?>" alt="<?= temizle($p2['username']) ?>">
                    </div>
                    <h2 class="compare-name"><a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $p2['id'] ?>" style="color:inherit;"><?= temizle($p2['username']) ?></a></h2>
                    <div class="compare-realname"><?= temizle(($p2['first_name'] ?? '') . ' ' . ($p2['last_name'] ?? '')) ?></div>
                    
                    <div class="compare-meta">
                        <?php if ($p2['country']): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?= temizle($p2['country']) ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-calendar"></i> <?= tarihFormatla($p2['created_at'], 'short') ?></span>
                    </div>

                    <?php if ($p2['bio']): ?>
                        <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:var(--space-md); font-style:italic;">
                            "<?= temizle($p2['bio']) ?>"
                        </p>
                    <?php endif; ?>

                    <div class="compare-socials">
                        <?php if ($p2['discord']): ?>
                            <a href="#" class="compare-social-btn" title="Discord: <?= temizle($p2['discord']) ?>"><i class="fab fa-discord"></i></a>
                        <?php endif; ?>
                        <?php if ($p2['steam_id']): ?>
                            <a href="https://steamcommunity.com/profiles/<?= temizle($p2['steam_id']) ?>" target="_blank" class="compare-social-btn" title="Steam"><i class="fab fa-steam"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- İstatistik Karşılaştırma Tablosu / Barları -->
            <div class="compare-stats-container fade-in">
                <h3 style="font-family:var(--font-heading); font-size:1.2rem; text-align:center; margin-bottom:var(--space-xl); border-bottom:1px solid var(--border-light); padding-bottom:var(--space-md);">
                    <i class="fas fa-chart-bar" style="color:var(--accent)"></i> Performans Kıyaslama
                </h3>

                <?php
                // Karşılaştırma barlarını çizmek için yardımcı fonksiyon
                function karsilastirBar($v1, $v2, $colorClass = 'winner-highlight') {
                    $total = $v1 + $v2;
                    if ($total == 0) {
                        $p1 = 50;
                        $p2 = 50;
                    } else {
                        $p1 = round(($v1 / $total) * 100);
                        $p2 = round(($v2 / $total) * 100);
                    }
                    ?>
                    <div class="compare-bar-container">
                        <div class="compare-bar-left <?= $v1 > $v2 ? $colorClass : '' ?>" style="width: <?= $p1 ?>%;"></div>
                        <div class="compare-bar-right <?= $v2 > $v1 ? $colorClass : '' ?>" style="width: <?= $p2 ?>%;"></div>
                    </div>
                    <?php
                }
                ?>

                <!-- PUAN -->
                <div class="compare-stat-row">
                    <div class="compare-stat-label">Puan</div>
                    <div class="compare-stat-values">
                        <div class="compare-val <?= $p1['points'] > $p2['points'] ? 'winner' : '' ?>"><?= $p1['points'] ?></div>
                        <div class="compare-val <?= $p2['points'] > $p1['points'] ? 'winner' : '' ?>"><?= $p2['points'] ?></div>
                    </div>
                    <?php karsilastirBar($p1['points'], $p2['points']); ?>
                </div>

                <!-- TOPLAM MAÇ -->
                <div class="compare-stat-row">
                    <div class="compare-stat-label">Toplam Maç</div>
                    <div class="compare-stat-values">
                        <div class="compare-val <?= $p1['toplam_mac'] > $p2['toplam_mac'] ? 'winner' : '' ?>"><?= $p1['toplam_mac'] ?></div>
                        <div class="compare-val <?= $p2['toplam_mac'] > $p1['toplam_mac'] ? 'winner' : '' ?>"><?= $p2['toplam_mac'] ?></div>
                    </div>
                    <?php karsilastirBar($p1['toplam_mac'], $p2['toplam_mac']); ?>
                </div>

                <!-- GALİBİYET -->
                <div class="compare-stat-row">
                    <div class="compare-stat-label">Galibiyet</div>
                    <div class="compare-stat-values">
                        <div class="compare-val <?= $p1['total_wins'] > $p2['total_wins'] ? 'winner-green' : '' ?>" style="<?= $p1['total_wins'] > $p2['total_wins'] ? 'color:var(--success)' : '' ?>"><?= $p1['total_wins'] ?></div>
                        <div class="compare-val <?= $p2['total_wins'] > $p1['total_wins'] ? 'winner-green' : '' ?>" style="<?= $p2['total_wins'] > $p1['total_wins'] ? 'color:var(--success)' : '' ?>"><?= $p2['total_wins'] ?></div>
                    </div>
                    <?php karsilastirBar($p1['total_wins'], $p2['total_wins'], 'winner-highlight-green'); ?>
                </div>

                <!-- MAĞLUBİYET -->
                <div class="compare-stat-row">
                    <div class="compare-stat-label">Mağlubiyet</div>
                    <div class="compare-stat-values">
                        <div class="compare-val" style="<?= $p1['total_losses'] < $p2['total_losses'] && $p1['toplam_mac'] > 0 ? 'color:var(--success)' : '' ?>"><?= $p1['total_losses'] ?></div>
                        <div class="compare-val" style="<?= $p2['total_losses'] < $p1['total_losses'] && $p2['toplam_mac'] > 0 ? 'color:var(--success)' : '' ?>"><?= $p2['total_losses'] ?></div>
                    </div>
                    <?php 
                    // Mağlubiyette düşük olan avantajlıdır
                    karsilastirBar($p2['total_losses'], $p1['total_losses'], 'winner-highlight-green'); 
                    ?>
                </div>

                <!-- KAZANMA ORANI -->
                <div class="compare-stat-row">
                    <div class="compare-stat-label">Kazanma Oranı</div>
                    <div class="compare-stat-values">
                        <div class="compare-val <?= $p1['kazanma_orani'] > $p2['kazanma_orani'] ? 'winner-green' : '' ?>" style="<?= $p1['kazanma_orani'] > $p2['kazanma_orani'] ? 'color:var(--success)' : '' ?>">%<?= $p1['kazanma_orani'] ?></div>
                        <div class="compare-val <?= $p2['kazanma_orani'] > $p1['kazanma_orani'] ? 'winner-green' : '' ?>" style="<?= $p2['kazanma_orani'] > $p1['kazanma_orani'] ? 'color:var(--success)' : '' ?>">%<?= $p2['kazanma_orani'] ?></div>
                    </div>
                    <?php karsilastirBar($p1['kazanma_orani'], $p2['kazanma_orani'], 'winner-highlight-green'); ?>
                </div>

                <!-- TAKIM SAYISI -->
                <div class="compare-stat-row">
                    <div class="compare-stat-label">Üye Olunan Takım</div>
                    <div class="compare-stat-values">
                        <div class="compare-val <?= $p1['takim_sayisi'] > $p2['takim_sayisi'] ? 'winner' : '' ?>"><?= $p1['takim_sayisi'] ?></div>
                        <div class="compare-val <?= $p2['takim_sayisi'] > $p1['takim_sayisi'] ? 'winner' : '' ?>"><?= $p2['takim_sayisi'] ?></div>
                    </div>
                    <?php karsilastirBar($p1['takim_sayisi'], $p2['takim_sayisi']); ?>
                </div>
            </div>

        <?php else: ?>
            <!-- Boş Durum (Oyuncu Seçilmedi) -->
            <div class="compare-stats-container compare-empty-state fade-in">
                <i class="fas fa-users-cog"></i>
                <h2>Karşılaştırmaya Başlayın</h2>
                <p style="margin-top:var(--space-sm); color:var(--text-secondary); max-width:500px; margin-left:auto; margin-right:auto;">
                    Yukarıdaki kutulardan kıyaslamak istediğiniz iki farklı oyuncuyu seçin. İki oyuncuyu seçtiğinizde performans grafikleri anlık olarak çizilecektir.
                </p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
