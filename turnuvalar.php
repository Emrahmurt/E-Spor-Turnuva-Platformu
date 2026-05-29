<?php
$sayfaBasligi = 'Turnuvalar';
$sayfaAciklama = 'E-spor turnuvalarını keşfedin, kayıt olun ve rekabet edin.';
require_once __DIR__ . '/includes/ust.php';

// Filtreler
$arama = trim($_GET['arama'] ?? '');
$oyunSlug = $_GET['oyun'] ?? '';
$durum = $_GET['durum'] ?? '';
$format = $_GET['format'] ?? '';
$sayfa = max(1, (int)($_GET['sayfa'] ?? 1));
$offset = ($sayfa - 1) * ITEMS_PER_PAGE;

// Sorgu oluştur
$where = ["1=1"];
$params = [];

if ($arama) {
    $where[] = "t.name LIKE ?";
    $params[] = "%$arama%";
}
if ($oyunSlug) {
    $where[] = "g.slug = ?";
    $params[] = $oyunSlug;
}
if ($durum) {
    $where[] = "t.status = ?";
    $params[] = $durum;
}
if ($format) {
    $where[] = "t.format = ?";
    $params[] = $format;
}

$whereSQL = implode(' AND ', $where);

$toplam = $db->count("SELECT COUNT(*) FROM turnuvalar t LEFT JOIN oyunlar g ON t.game_id = g.id WHERE $whereSQL", $params);

$turnuvalar = $db->fetchAll("SELECT t.*, g.name as oyun_adi, g.slug as oyun_slug,
    (SELECT COUNT(*) FROM turnuva_kayitlari WHERE tournament_id = t.id AND status='approved') as kayitli_takim
    FROM turnuvalar t
    LEFT JOIN oyunlar g ON t.game_id = g.id
    WHERE $whereSQL
    ORDER BY t.is_featured DESC, t.start_date DESC
    LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset", $params);

$oyunlar = $db->fetchAll("SELECT * FROM oyunlar WHERE is_active=1 ORDER BY name");

$baseUrl = SITE_URL . '/turnuvalar.php?' . http_build_query(array_filter(['arama'=>$arama,'oyun'=>$oyunSlug,'durum'=>$durum,'format'=>$format])) . '&';
?>

<section class="sayfa-basligi">
    <div class="container sayfa-basligi-icerik">
        <h1><i class="fas fa-trophy" style="color:var(--accent)"></i> Turnuvalar</h1>
        <div class="breadcrumb">
            <a href="<?= SITE_URL ?>">Ana Sayfa</a> <i class="fas fa-chevron-right"></i> <span>Turnuvalar</span>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <!-- Filtre Bar -->
        <form class="filtre-bar" method="GET">
            <div class="filtre-arama">
                <i class="fas fa-search"></i>
                <input type="text" name="arama" placeholder="Turnuva ara..." value="<?= temizle($arama) ?>">
            </div>
            <select name="oyun" class="filtre-select">
                <option value="">Tüm Oyunlar</option>
                <?php foreach ($oyunlar as $o): ?>
                    <option value="<?= $o['slug'] ?>" <?= $oyunSlug === $o['slug'] ? 'selected' : '' ?>><?= temizle($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="durum" class="filtre-select">
                <option value="">Tüm Durumlar</option>
                <option value="registration" <?= $durum==='registration'?'selected':'' ?>>Kayıt Açık</option>
                <option value="upcoming" <?= $durum==='upcoming'?'selected':'' ?>>Yaklaşıyor</option>
                <option value="ongoing" <?= $durum==='ongoing'?'selected':'' ?>>Devam Ediyor</option>
                <option value="completed" <?= $durum==='completed'?'selected':'' ?>>Tamamlandı</option>
            </select>
            <select name="format" class="filtre-select">
                <option value="">Tüm Formatlar</option>
                <option value="single_elimination" <?= $format==='single_elimination'?'selected':'' ?>>Tek Eleme</option>
                <option value="double_elimination" <?= $format==='double_elimination'?'selected':'' ?>>Çift Eleme</option>
                <option value="round_robin" <?= $format==='round_robin'?'selected':'' ?>>Lig</option>
                <option value="swiss" <?= $format==='swiss'?'selected':'' ?>>Swiss</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filtrele</button>
        </form>

        <?php if (empty($turnuvalar)): ?>
            <div class="bos-durum">
                <i class="fas fa-trophy"></i>
                <h3>Turnuva Bulunamadı</h3>
                <p>Arama kriterlerinize uygun turnuva bulunamadı.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-2">
                <?php foreach ($turnuvalar as $t): ?>
                <div class="turnuva-kart fade-in">
                    <div class="turnuva-kart-banner">
                        <?php if (!empty($t['banner_image'])): ?>
                            <img src="<?= SITE_URL ?>/<?= temizle($t['banner_image']) ?>" alt="<?= temizle($t['name']) ?>">
                        <?php else: ?>
                            <i class="fas fa-trophy oyun-ikon"></i>
                        <?php endif; ?>
                        <div class="turnuva-kart-durum"><?= durumBadge($t['status']) ?></div>
                        <div class="turnuva-kart-oyun"><i class="fas fa-gamepad"></i> <?= temizle($t['oyun_adi']) ?></div>
                    </div>
                    <div class="turnuva-kart-icerik">
                        <h3 class="turnuva-kart-baslik">
                            <a href="<?= SITE_URL ?>/turnuva.php?id=<?= $t['id'] ?>"><?= temizle($t['name']) ?></a>
                        </h3>
                        <div class="turnuva-kart-bilgi">
                            <div class="turnuva-bilgi-item"><i class="fas fa-calendar"></i> <?= tarihFormatla($t['start_date'], 'short') ?></div>
                            <div class="turnuva-bilgi-item"><i class="fas fa-users"></i> <?= $t['kayitli_takim'] ?>/<?= $t['max_teams'] ?> Takım</div>
                            <div class="turnuva-bilgi-item"><i class="fas fa-sitemap"></i> <?= formatBadge($t['format']) ?></div>
                            <div class="turnuva-bilgi-item"><i class="fas fa-gamepad"></i> <?= $t['team_size'] ?>v<?= $t['team_size'] ?></div>
                        </div>
                    </div>
                    <div class="turnuva-kart-footer">
                        <div class="turnuva-odul"><i class="fas fa-coins"></i> <?= paraFormatla($t['prize_pool']) ?></div>
                        <a href="<?= SITE_URL ?>/turnuva.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm">Detaylar</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?= sayfalamaHTML($toplam, $sayfa, ITEMS_PER_PAGE, $baseUrl) ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
