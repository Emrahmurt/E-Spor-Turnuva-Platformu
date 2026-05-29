<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';
adminGerekli();

$haberler = $db->fetchAll("SELECT n.*, u.username as yazar FROM haberler n LEFT JOIN kullanicilar u ON n.author_id = u.id ORDER BY n.created_at DESC");

if (isset($_GET['sil']) && csrfDogrula($_GET['token'] ?? '')) {
    $db->query("DELETE FROM haberler WHERE id = ?", [(int)$_GET['sil']]);
    flashMesaj('Haber silindi.', 'success');
    header('Location: ' . SITE_URL . '/yonetim/haberler.php'); exit;
}

$sayfaBasligi = 'Haber Yönetimi';
require_once dirname(__DIR__) . '/includes/ust.php';
?>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <div class="dashboard-grid">
            <!-- Sidebar -->
            <?php 
            $aktifSekme = 'haberler';
            require_once __DIR__ . '/sidebar.php'; 
            ?>

            <!-- İçerik -->
            <div class="dashboard-icerik">
                <?= flashGoster() ?>
                
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xl);padding-bottom:var(--space-lg);border-bottom:1px solid var(--border-light)">
                    <h2 style="font-size:1.5rem;"><i class="fas fa-newspaper" style="color:var(--accent)"></i> Haber Yönetimi (<?= count($haberler) ?>)</h2>
                    <a href="<?= SITE_URL ?>/yonetim/haber-duzenle.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Yeni Haber
                    </a>
                </div>

                <div class="tablo-wrapper">
                    <table class="tablo">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Başlık</th>
                                <th>Kategori</th>
                                <th>Yazar</th>
                                <th>Görüntüleme</th>
                                <th>Durum</th>
                                <th>Tarih</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($haberler as $h): ?>
                            <tr>
                                <td><?= $h['id'] ?></td>
                                <td class="wrap-text">
                                    <a href="<?= SITE_URL ?>/haber-detay.php?id=<?= $h['id'] ?>" style="color:var(--accent);font-weight:600;" target="_blank">
                                        <?= temizle($h['title']) ?>
                                    </a>
                                    <?= $h['is_featured'] ? ' ⭐' : '' ?>
                                </td>
                                <td><span class="badge badge-primary"><?= temizle(kategoriCevir($h['category'])) ?></span></td>
                                <td><?= temizle($h['yazar']) ?></td>
                                <td><?= number_format($h['views']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $h['is_published'] ? 'success' : 'warning' ?>">
                                        <?= $h['is_published'] ? 'Yayında' : 'Taslak' ?>
                                    </span>
                                </td>
                                <td style="font-size:0.8rem;color:var(--text-muted);"><?= tarihFormatla($h['created_at'], 'short') ?></td>
                                <td>
                                    <a href="<?= SITE_URL ?>/yonetim/haber-duzenle.php?id=<?= $h['id'] ?>" class="btn btn-ghost btn-sm" style="padding:4px 8px;" title="Düzenle">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?sil=<?= $h['id'] ?>&token=<?= csrfToken() ?>" class="btn btn-ghost btn-sm" style="padding:4px 8px;color:var(--danger);" onclick="return confirm('Silmek istediğinize emin misiniz?')" title="Sil">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/alt.php'; ?>
