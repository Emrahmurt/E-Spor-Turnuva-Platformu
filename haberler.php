<?php
$sayfaBasligi = 'Haberler';
require_once __DIR__ . '/includes/ust.php';

$kategori = $_GET['kategori'] ?? '';
$arama = trim($_GET['arama'] ?? '');
$sayfa = max(1, (int)($_GET['sayfa'] ?? 1));
$offset = ($sayfa - 1) * ITEMS_PER_PAGE;

$where = ["n.is_published = 1"];
$params = [];
if ($kategori) { $where[] = "n.category = ?"; $params[] = $kategori; }
if ($arama) { $where[] = "(n.title LIKE ? OR n.content LIKE ?)"; $params = array_merge($params, ["%$arama%","%$arama%"]); }
$whereSQL = implode(' AND ', $where);

$toplam = $db->count("SELECT COUNT(*) FROM haberler n WHERE $whereSQL", $params);
$haberler = $db->fetchAll("SELECT n.*, u.username as yazar FROM haberler n LEFT JOIN kullanicilar u ON n.author_id = u.id WHERE $whereSQL ORDER BY n.is_featured DESC, n.created_at DESC LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset", $params);
$oneCikan = (!$kategori && !$arama && $sayfa === 1) ? $db->fetch("SELECT n.*, u.username as yazar FROM haberler n LEFT JOIN kullanicilar u ON n.author_id = u.id WHERE n.is_published=1 AND n.is_featured=1 ORDER BY n.created_at DESC LIMIT 1") : null;
?>

<section class="sayfa-basligi">
    <div class="container sayfa-basligi-icerik">
        <h1><i class="fas fa-newspaper" style="color:var(--accent)"></i> Haberler</h1>
        <div class="breadcrumb"><a href="<?= SITE_URL ?>">Ana Sayfa</a> <i class="fas fa-chevron-right"></i> <span>Haberler</span></div>
    </div>
</section>

<section class="section">
    <div class="container">
        <form class="filtre-bar" method="GET">
            <div class="filtre-arama"><i class="fas fa-search"></i><input type="text" name="arama" placeholder="Haber ara..." value="<?= temizle($arama) ?>"></div>
            <select name="kategori" class="filtre-select">
                <option value="">Tüm Kategoriler</option>
                <option value="haberler" <?= $kategori==='haberler'?'selected':'' ?>>Haberler</option>
                <option value="turnuva" <?= $kategori==='turnuva'?'selected':'' ?>>Turnuva</option>
                <option value="esports" <?= $kategori==='esports'?'selected':'' ?>>E-Spor</option>
                <option value="rehber" <?= $kategori==='rehber'?'selected':'' ?>>Rehber</option>
                <option value="duyuru" <?= $kategori==='duyuru'?'selected':'' ?>>Duyuru</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filtrele</button>
        </form>

        <?php if ($oneCikan): ?>
        <div class="kart fade-in" style="margin-bottom:var(--space-xl);display:grid;grid-template-columns:1fr 1fr;overflow:hidden;">
            <div style="background:linear-gradient(135deg,var(--bg-surface),var(--accent-light));display:flex;align-items:center;justify-content:center;min-height:300px;overflow:hidden;">
                <?php $hImg = haberResimURL($oneCikan['featured_image']); ?>
                <?php if ($hImg): ?>
                    <img src="<?= $hImg ?>" alt="<?= temizle($oneCikan['title']) ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <i class="fas fa-newspaper" style="font-size:4rem;color:var(--accent);opacity:0.3;"></i>
                <?php endif; ?>
            </div>
            <div style="padding:var(--space-2xl);">
                <span class="badge badge-warning" style="margin-bottom:var(--space-md);">ÖNE ÇIKAN</span>
                <h2 style="margin-bottom:var(--space-md);"><a href="<?= SITE_URL ?>/haber-detay.php?id=<?= $oneCikan['id'] ?>"><?= temizle($oneCikan['title']) ?></a></h2>
                <p style="color:var(--text-secondary);line-height:1.7;margin-bottom:var(--space-lg);"><?= temizle($oneCikan['excerpt']) ?></p>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="color:var(--text-muted);font-size:0.85rem;"><i class="fas fa-user"></i> <?= temizle($oneCikan['yazar']) ?> • <?= tarihFormatla($oneCikan['created_at'],'relative') ?></span>
                    <a href="<?= SITE_URL ?>/haber-detay.php?id=<?= $oneCikan['id'] ?>" class="btn btn-primary btn-sm">Oku <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($haberler)): ?>
            <div class="bos-durum"><i class="fas fa-newspaper"></i><h3>Haber bulunamadı</h3></div>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($haberler as $h): ?>
                <div class="haber-kart fade-in">
                    <div class="haber-kart-resim">
                        <?php $hImg = haberResimURL($h['featured_image']); ?>
                        <?php if ($hImg): ?>
                            <img src="<?= $hImg ?>" alt="<?= temizle($h['title']) ?>">
                        <?php else: ?>
                            <i class="fas fa-newspaper ikon-placeholder"></i>
                        <?php endif; ?>
                        <div class="haber-kart-kategori"><span class="badge badge-primary"><?= temizle(kategoriCevir($h['category'])) ?></span></div>
                    </div>
                    <div class="haber-kart-icerik">
                        <div class="haber-kart-tarih"><i class="fas fa-clock"></i> <?= tarihFormatla($h['created_at'],'relative') ?></div>
                        <h3 class="haber-kart-baslik"><a href="<?= SITE_URL ?>/haber-detay.php?id=<?= $h['id'] ?>"><?= temizle($h['title']) ?></a></h3>
                        <p class="haber-kart-ozet"><?= temizle($h['excerpt']) ?></p>
                    </div>
                    <div class="haber-kart-footer">
                        <span><i class="fas fa-eye"></i> <?= kisaSayi($h['views']) ?></span>
                        <a href="<?= SITE_URL ?>/haber-detay.php?id=<?= $h['id'] ?>">Devamını Oku <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?= sayfalamaHTML($toplam, $sayfa, ITEMS_PER_PAGE, SITE_URL . '/haberler.php?' . http_build_query(array_filter(['kategori'=>$kategori,'arama'=>$arama])) . '&') ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
