<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';
adminGerekli();

// POST İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula($_POST['csrf_token'] ?? '')) {
    $aksiyon = $_POST['aksiyon'] ?? '';

    if ($aksiyon === 'yorum_sil') {
        $yorum_id = (int)($_POST['yorum_id'] ?? 0);
        if ($yorum_id) {
            $db->query("DELETE FROM haber_yorumlar WHERE id = ?", [$yorum_id]);
            flashMesaj('Yorum başarıyla silindi.', 'success');
        } else {
            flashMesaj('Geçersiz yorum ID.', 'error');
        }
        header('Location: ' . SITE_URL . '/yonetim/yorumlar.php');
        exit;
    }
}

// Tüm yorumları sorgula
$yorumlar = $db->fetchAll("SELECT y.*, u.username, u.avatar, n.title as haber_basligi, n.slug as haber_slug 
    FROM haber_yorumlar y 
    JOIN kullanicilar u ON y.user_id = u.id 
    JOIN haberler n ON y.news_id = n.id 
    ORDER BY y.created_at DESC");

$sayfaBasligi = 'Yorum Yönetimi';
require_once dirname(__DIR__) . '/includes/ust.php';
?>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <div class="dashboard-grid">
            <!-- Sidebar -->
            <?php 
            $aktifSekme = 'yorumlar';
            require_once __DIR__ . '/sidebar.php'; 
            ?>

            <!-- İçerik -->
            <div class="dashboard-icerik">
                <?= flashGoster() ?>
                
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xl);padding-bottom:var(--space-lg);border-bottom:1px solid var(--border-light)">
                    <h2 style="font-size:1.5rem;"><i class="fas fa-comments" style="color:var(--accent)"></i> Yorum Moderasyonu</h2>
                </div>

                <?php if (empty($yorumlar)): ?>
                    <div class="bos-durum" style="padding:var(--space-3xl) 0;">
                        <i class="far fa-comments"></i>
                        <h3>Henüz yorum bulunmuyor</h3>
                        <p style="color:var(--text-muted);margin-top:var(--space-xs);">Haberler altına yapılmış herhangi bir yorum bulunamadı.</p>
                    </div>
                <?php else: ?>
                    <table class="tablo" style="width:100%;">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th style="min-width: 150px;">Yazar</th>
                                <th style="min-width: 200px;">Haber</th>
                                <th style="min-width: 250px;">Yorum</th>
                                <th style="min-width: 130px;">Tarih</th>
                                <th style="width: 80px; text-align:center;">Aksiyon</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($yorumlar as $y): ?>
                                <tr>
                                    <td>#<?= $y['id'] ?></td>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:8px; min-width: 150px; white-space: nowrap; flex-shrink: 0;">
                                            <img src="<?= avatarURL($y['avatar']) ?>" style="width:24px; height:24px; border-radius:50%; object-fit:cover; flex-shrink:0;">
                                            <a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $y['user_id'] ?>" target="_blank" style="font-weight:600; color:inherit;"><?= temizle($y['username']) ?></a>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="min-width: 200px; white-space: normal;">
                                            <a href="<?= SITE_URL ?>/haber-detay.php?id=<?= $y['news_id'] ?>" target="_blank" style="color:var(--accent); font-weight:500; word-break: break-word;"><?= temizle($y['haber_basligi']) ?></a>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="min-width: 250px; white-space: normal; word-break: break-word; font-size:0.85rem; color:var(--text-primary);">
                                            <?= nl2br(temizle($y['comment'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="min-width: 130px; white-space: nowrap; font-size:0.8rem; color:var(--text-muted);">
                                            <?= tarihFormatla($y['created_at'], 'relative') ?>
                                        </div>
                                    </td>
                                    <td style="text-align:center;">
                                        <form method="POST" style="margin:0; display:inline;" onsubmit="return confirm('Bu yorumu silmek istediğinizden emin misiniz?')">
                                            <?= csrfInput() ?>
                                            <input type="hidden" name="aksiyon" value="yorum_sil">
                                            <input type="hidden" name="yorum_id" value="<?= $y['id'] ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm" style="padding:6px; border:none; color:var(--danger); display: inline-flex; align-items: center; justify-content: center;" title="Sil">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/alt.php'; ?>
