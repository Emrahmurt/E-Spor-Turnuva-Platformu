<?php
$sayfaBasligi = 'Takımlar';
require_once __DIR__ . '/includes/ust.php';

$arama = trim($_GET['arama'] ?? '');
$oyunId = (int)($_GET['oyun'] ?? 0);
$sayfa = max(1, (int)($_GET['sayfa'] ?? 1));
$offset = ($sayfa - 1) * ITEMS_PER_PAGE;

$where = ["t.is_active = 1"];
$params = [];
if ($arama) { $where[] = "t.name LIKE ?"; $params[] = "%$arama%"; }
if ($oyunId) { $where[] = "t.game_id = ?"; $params[] = $oyunId; }
$whereSQL = implode(' AND ', $where);

$toplam = $db->count("SELECT COUNT(*) FROM takimlar t WHERE $whereSQL", $params);
$takimlar = $db->fetchAll("SELECT t.*, g.name as oyun_adi,
    (SELECT COUNT(*) FROM takim_uyeleri WHERE team_id = t.id) as uye_sayisi
    FROM takimlar t LEFT JOIN oyunlar g ON t.game_id = g.id
    WHERE $whereSQL ORDER BY t.points DESC LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset", $params);
$oyunlar = $db->fetchAll("SELECT * FROM oyunlar WHERE is_active=1 ORDER BY name");
?>

<section class="sayfa-basligi">
    <div class="container sayfa-basligi-icerik">
        <h1><i class="fas fa-users" style="color:var(--accent)"></i> Takımlar</h1>
        <div class="breadcrumb"><a href="<?= SITE_URL ?>">Ana Sayfa</a> <i class="fas fa-chevron-right"></i> <span>Takımlar</span></div>
    </div>
</section>

<section class="section">
    <div class="container">
        <form class="filtre-bar" method="GET">
            <div class="filtre-arama"><i class="fas fa-search"></i><input type="text" name="arama" placeholder="Takım ara..." value="<?= temizle($arama) ?>"></div>
            <select name="oyun" class="filtre-select">
                <option value="">Tüm Oyunlar</option>
                <?php foreach ($oyunlar as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $oyunId==$o['id']?'selected':'' ?>><?= temizle($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filtrele</button>
        </form>

        <?php if (empty($takimlar)): ?>
            <div class="bos-durum"><i class="fas fa-users"></i><h3>Takım bulunamadı</h3></div>
        <?php else: ?>
            <div class="grid grid-4">
                <?php foreach ($takimlar as $t): ?>
                <div class="takim-kart fade-in">
                    <div class="takim-kart-logo">
                        <?php if (!empty($t['logo']) && $t['logo'] !== 'default-team.png'): ?>
                            <img src="<?= SITE_URL ?>/<?= temizle($t['logo']) ?>" alt="<?= temizle($t['name']) ?>">
                        <?php else: ?>
                            <i class="fas fa-shield-halved"></i>
                        <?php endif; ?>
                    </div>
                    <h3 class="takim-kart-isim"><a href="<?= SITE_URL ?>/takim.php?id=<?= $t['id'] ?>"><?= temizle($t['name']) ?></a></h3>
                    <div class="takim-kart-oyun"><?= temizle($t['oyun_adi']) ?></div>
                    <div class="takim-kart-istatistik">
                        <div class="takim-stat"><span class="takim-stat-sayi"><?= $t['total_wins'] + $t['total_losses'] ?></span><span class="takim-stat-etiket">Maç</span></div>
                        <div class="takim-stat"><span class="takim-stat-sayi"><?= $t['total_wins'] ?></span><span class="takim-stat-etiket">Galibiyet</span></div>
                        <div class="takim-stat"><span class="takim-stat-sayi"><?= $t['total_losses'] ?></span><span class="takim-stat-etiket">Mağlubiyet</span></div>
                        <div class="takim-stat"><span class="takim-stat-sayi"><?= $t['points'] ?></span><span class="takim-stat-etiket">Puan</span></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?= sayfalamaHTML($toplam, $sayfa, ITEMS_PER_PAGE, SITE_URL . '/takimlar.php?' . http_build_query(array_filter(['arama'=>$arama,'oyun'=>$oyunId])) . '&') ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
