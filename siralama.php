<?php
$sayfaBasligi = 'Sıralama';
require_once __DIR__ . '/includes/ust.php';

$tab = $_GET['tab'] ?? 'oyuncu';
$sayfa = max(1, (int)($_GET['sayfa'] ?? 1));
$offset = ($sayfa - 1) * 20;

if ($tab === 'takim') {
    $toplam = $db->count("SELECT COUNT(*) FROM takimlar WHERE is_active=1");
    $liste = $db->fetchAll("SELECT t.*, g.name as oyun_adi FROM takimlar t LEFT JOIN oyunlar g ON t.game_id = g.id WHERE t.is_active=1 ORDER BY t.points DESC LIMIT 20 OFFSET $offset");
} else {
    $toplam = $db->count("SELECT COUNT(*) FROM kullanicilar WHERE is_active=1 AND role IN ('player','user')");
    $liste = $db->fetchAll("SELECT * FROM kullanicilar WHERE is_active=1 AND role IN ('player','user') ORDER BY points DESC LIMIT 20 OFFSET $offset");
}
?>

<section class="sayfa-basligi">
    <div class="container sayfa-basligi-icerik">
        <h1><i class="fas fa-ranking-star" style="color:var(--accent)"></i> Sıralama Tablosu</h1>
        <div class="breadcrumb"><a href="<?= SITE_URL ?>">Ana Sayfa</a> <i class="fas fa-chevron-right"></i> <span>Sıralama</span></div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="tab-menu">
            <a href="?tab=oyuncu" class="tab-link <?= $tab==='oyuncu'?'aktif':'' ?>"><i class="fas fa-user-ninja"></i> Oyuncu Sıralaması</a>
            <a href="?tab=takim" class="tab-link <?= $tab==='takim'?'aktif':'' ?>"><i class="fas fa-shield-halved"></i> Takım Sıralaması</a>
        </div>

        <?php foreach ($liste as $i => $item):
            $sira = $offset + $i + 1;
            $topClass = $sira <= 3 ? ' top-' . $sira : '';
        ?>
        <div class="siralama-satir<?= $topClass ?> fade-in">
            <div class="siralama-sira">
                <?php if ($sira === 1): ?><i class="fas fa-crown" style="color:var(--gold)"></i>
                <?php elseif ($sira === 2): ?><i class="fas fa-medal" style="color:var(--silver)"></i>
                <?php elseif ($sira === 3): ?><i class="fas fa-medal" style="color:var(--bronze)"></i>
                <?php else: echo $sira; endif; ?>
            </div>
            <div class="siralama-bilgi">
                <div class="siralama-avatar">
                    <?php if ($tab === 'takim'): ?>
                        <img src="<?= takimLogoURL($item['logo'] ?? '') ?>" alt="<?= temizle($item['name']) ?>">
                    <?php else: ?>
                        <img src="<?= avatarURL($item['avatar'] ?? '') ?>" alt="<?= temizle($item['username']) ?>">
                    <?php endif; ?>
                </div>
                <div>
                    <div class="siralama-isim">
                        <?php if ($tab === 'takim'): ?>
                            <a href="<?= SITE_URL ?>/takim.php?id=<?= $item['id'] ?>"><?= temizle($item['name']) ?></a>
                        <?php else: ?>
                            <a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $item['id'] ?>"><?= temizle($item['username']) ?></a>
                        <?php endif; ?>
                    </div>
                    <div class="siralama-detay">
                        <?php if ($tab === 'takim'): ?>
                            <?= temizle($item['oyun_adi']) ?> • <?= temizle($item['country'] ?? 'TR') ?>
                        <?php else: ?>
                            <?= temizle(trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''))) ?: '—' ?> • <?= temizle($item['country'] ?? 'TR') ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="siralama-istatistikler">
                <div class="siralama-stat">
                    <span class="siralama-stat-sayi" style="color:var(--success);"><?= $item['total_wins'] ?></span>
                    <span class="siralama-stat-etiket">Galibiyet</span>
                </div>
                <div class="siralama-stat">
                    <span class="siralama-stat-sayi" style="color:var(--danger);"><?= $item['total_losses'] ?></span>
                    <span class="siralama-stat-etiket">Mağlubiyet</span>
                </div>
                <div class="siralama-stat">
                    <span class="siralama-stat-sayi"><?= kazanmaOrani($item['total_wins'],$item['total_losses']) ?></span>
                    <span class="siralama-stat-etiket">Oran</span>
                </div>
            </div>
            <div class="siralama-puan"><?= number_format($item['points'], 0, '', '.') ?></div>
        </div>
        <?php endforeach; ?>

        <?= sayfalamaHTML($toplam, $sayfa, 20, SITE_URL . "/siralama.php?tab=$tab&") ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
