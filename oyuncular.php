<?php
$sayfaBasligi = 'Oyuncular';
require_once __DIR__ . '/includes/ust.php';

$arama = trim($_GET['arama'] ?? '');
$sayfa = max(1, (int)($_GET['sayfa'] ?? 1));
$offset = ($sayfa - 1) * ITEMS_PER_PAGE;

$where = ["u.is_active = 1", "u.role IN ('player','user')"];
$params = [];
if ($arama) { $where[] = "(u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)"; $params = array_merge($params, ["%$arama%","%$arama%","%$arama%"]); }
$whereSQL = implode(' AND ', $where);

$toplam = $db->count("SELECT COUNT(*) FROM kullanicilar u WHERE $whereSQL", $params);
$oyuncular = $db->fetchAll("SELECT u.* FROM kullanicilar u WHERE $whereSQL ORDER BY u.points DESC LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset", $params);
?>

<section class="sayfa-basligi">
    <div class="container sayfa-basligi-icerik">
        <h1><i class="fas fa-user-ninja" style="color:var(--accent)"></i> Oyuncular</h1>
        <div class="breadcrumb"><a href="<?= SITE_URL ?>">Ana Sayfa</a> <i class="fas fa-chevron-right"></i> <span>Oyuncular</span></div>
    </div>
</section>

<section class="section">
    <div class="container">
        <form class="filtre-bar" method="GET">
            <div class="filtre-arama"><i class="fas fa-search"></i><input type="text" name="arama" placeholder="Oyuncu ara..." value="<?= temizle($arama) ?>"></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Ara</button>
            <a href="<?= SITE_URL ?>/karsilastir.php" class="btn btn-ghost btn-sm" style="margin-left:auto; display:flex; align-items:center; gap:8px;"><i class="fas fa-balance-scale" style="color:var(--accent);"></i> Oyuncuları Karşılaştır</a>
        </form>

        <?php if (empty($oyuncular)): ?>
            <div class="bos-durum"><i class="fas fa-user-ninja"></i><h3>Oyuncu bulunamadı</h3></div>
        <?php else: ?>
            <div class="grid grid-4">
                <?php foreach ($oyuncular as $o): ?>
                <div class="oyuncu-kart fade-in">
                    <a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $o['id'] ?>" class="oyuncu-kart-avatar">
                        <img src="<?= avatarURL($o['avatar'] ?? '') ?>" alt="<?= temizle($o['username']) ?>">
                    </a>
                    <h4 class="oyuncu-kart-isim"><a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $o['id'] ?>"><?= temizle($o['username']) ?></a></h4>
                    <p class="oyuncu-kart-gercek-isim"><?= temizle(trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? ''))) ?: '—' ?></p>
                    <?php if ($o['country']): ?><div class="oyuncu-kart-rol"><i class="fas fa-map-marker-alt"></i> <?= temizle($o['country']) ?></div><?php endif; ?>
                    <span class="oyuncu-kart-puan"><?= $o['points'] ?></span>
                    <span class="oyuncu-kart-puan-etiket">Puan</span>
                    <div style="display:flex;justify-content:center;gap:var(--space-md);margin-top:var(--space-sm);font-size:0.8rem;color:var(--text-secondary);">
                        <span style="color:var(--success);"><?= $o['total_wins'] ?>G</span>
                        <span style="color:var(--danger);"><?= $o['total_losses'] ?>M</span>
                        <span><?= kazanmaOrani($o['total_wins'],$o['total_losses']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?= sayfalamaHTML($toplam, $sayfa, ITEMS_PER_PAGE, SITE_URL . '/oyuncular.php?' . ($arama ? "arama=$arama&" : '')) ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
