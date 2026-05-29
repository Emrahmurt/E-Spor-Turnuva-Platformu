<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/kimlik.php';
require_once __DIR__ . '/includes/fonksiyonlar.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . SITE_URL . '/haberler.php'); exit; }

// Yorum Gönderme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksiyon']) && $_POST['aksiyon'] === 'yorum_ekle') {
    girisGerekli(); // Yorum yapmak için giriş zorunlu
    if (csrfDogrula($_POST['csrf_token'] ?? '')) {
        $comment = trim($_POST['comment'] ?? '');
        if (empty($comment)) {
            flashMesaj('Yorum alanı boş bırakılamaz.', 'error');
        } else {
            $db->query("INSERT INTO haber_yorumlar (news_id, user_id, comment) VALUES (?, ?, ?)", [$id, $_SESSION['user_id'], $comment]);
            flashMesaj('Yorumunuz başarıyla eklendi.', 'success');
        }
        header('Location: ' . SITE_URL . '/haber-detay.php?id=' . $id . '#yorumlar');
        exit;
    } else {
        flashMesaj('Güvenlik doğrulaması başarısız oldu.', 'error');
        header('Location: ' . SITE_URL . '/haber-detay.php?id=' . $id);
        exit;
    }
}

$haber = $db->fetch("SELECT n.*, u.username as yazar, u.avatar as yazar_avatar FROM haberler n LEFT JOIN kullanicilar u ON n.author_id = u.id WHERE n.id = ? AND n.is_published = 1", [$id]);
if (!$haber) { header('Location: ' . SITE_URL . '/haberler.php'); exit; }

$db->query("UPDATE haberler SET views = views + 1 WHERE id = ?", [$id]);
$sayfaBasligi = $haber['title'];

$ilgiliHaberler = $db->fetchAll("SELECT * FROM haberler WHERE category = ? AND id != ? AND is_published = 1 ORDER BY created_at DESC LIMIT 3", [$haber['category'], $id]);

// Yorumları sorgula
$yorumlar = $db->fetchAll("SELECT y.*, u.username, u.avatar 
    FROM haber_yorumlar y 
    JOIN kullanicilar u ON y.user_id = u.id 
    WHERE y.news_id = ? 
    ORDER BY y.created_at DESC", [$id]);
$yorumSayisi = count($yorumlar);

require_once __DIR__ . '/includes/ust.php';
?>

<section class="sayfa-basligi">
    <div class="container sayfa-basligi-icerik">
        <div class="breadcrumb">
            <a href="<?= SITE_URL ?>">Ana Sayfa</a> <i class="fas fa-chevron-right"></i>
            <a href="<?= SITE_URL ?>/haberler.php">Haberler</a> <i class="fas fa-chevron-right"></i>
            <span><?= temizle(mb_substr($haber['title'], 0, 40)) ?>...</span>
        </div>
    </div>
</section>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <div style="max-width:800px;margin:0 auto;">
            <div class="fade-in">
                <span class="badge badge-primary" style="margin-bottom:var(--space-md);"><?= temizle(kategoriCevir($haber['category'])) ?></span>
                <h1 style="font-size:2rem;margin-bottom:var(--space-lg);line-height:1.3;"><?= temizle($haber['title']) ?></h1>
                <div style="display:flex;align-items:center;gap:var(--space-lg);margin-bottom:var(--space-xl);padding-bottom:var(--space-lg);border-bottom:1px solid var(--border-light);">
                    <div style="display:flex;align-items:center;gap:var(--space-sm);">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--bg-surface);display:flex;align-items:center;justify-content:center;"><i class="fas fa-user" style="color:var(--text-muted);font-size:0.8rem;"></i></div>
                        <span style="font-weight:600;font-size:0.9rem;"><?= temizle($haber['yazar']) ?></span>
                    </div>
                    <span style="color:var(--text-muted);font-size:0.85rem;"><i class="fas fa-calendar"></i> <?= tarihFormatla($haber['created_at']) ?></span>
                    <span style="color:var(--text-muted);font-size:0.85rem;"><i class="fas fa-eye"></i> <?= number_format($haber['views']) ?> görüntüleme</span>
                </div>
            </div>

            <!-- Banner -->
            <div class="kart fade-in" style="margin-bottom:var(--space-xl);overflow:hidden;">
                <?php $haberResim = haberResimURL($haber['featured_image']); ?>
                <?php if ($haberResim): ?>
                    <img src="<?= $haberResim ?>" alt="<?= temizle($haber['title']) ?>" style="width:100%;height:350px;object-fit:cover;display:block;">
                <?php else: ?>
                    <div style="height:350px;background:linear-gradient(135deg,var(--bg-surface),var(--accent-light));display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-newspaper" style="font-size:4rem;color:var(--accent);opacity:0.3;"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- İçerik -->
            <div class="fade-in" style="color:var(--text-secondary);line-height:2;font-size:1rem;">
                <?= $haber['content'] ?>
            </div>

            <!-- Etiketler -->
            <?php if ($haber['tags']): ?>
            <div style="margin-top:var(--space-xl);padding-top:var(--space-lg);border-top:1px solid var(--border-light);">
                <span style="color:var(--text-muted);font-size:0.85rem;margin-right:var(--space-sm);">Etiketler:</span>
                <?php foreach (explode(',', $haber['tags']) as $tag): ?>
                    <span class="badge badge-secondary" style="margin-right:4px;"><?= temizle(trim($tag)) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Paylaşım -->
            <div style="margin-top:var(--space-xl);display:flex;gap:var(--space-sm);">
                <span style="color:var(--text-muted);font-size:0.9rem;margin-right:var(--space-sm);">Paylaş:</span>
                <?php $paylasUrl = urlencode(SITE_URL . '/haber-detay.php?id=' . $id); $paylasBaslik = urlencode($haber['title']); ?>
                <a href="https://twitter.com/intent/tweet?url=<?= $paylasUrl ?>&text=<?= $paylasBaslik ?>" target="_blank" class="btn btn-ghost btn-sm" style="padding:6px 12px;"><i class="fab fa-twitter"></i></a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $paylasUrl ?>" target="_blank" class="btn btn-ghost btn-sm" style="padding:6px 12px;"><i class="fab fa-facebook"></i></a>
                <a href="https://api.whatsapp.com/send?text=<?= $paylasBaslik ?>%20<?= $paylasUrl ?>" target="_blank" class="btn btn-ghost btn-sm" style="padding:6px 12px;"><i class="fab fa-whatsapp"></i></a>
            </div>

            <!-- Yorumlar Bölümü -->
            <div class="yorum-alani" id="yorumlar">
                <h3 style="color:var(--text-white); font-size:1.2rem; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-comments" style="color:var(--accent);"></i> Yorumlar (<?= $yorumSayisi ?>)
                </h3>

                <!-- Yorum Ekleme Formu -->
                <div class="yorum-form-kapsayici" style="margin-top:var(--space-lg);">
                    <?php if (girisYapmisMi()): ?>
                        <form method="POST" action="">
                            <?= csrfInput() ?>
                            <input type="hidden" name="aksiyon" value="yorum_ekle">
                            <div class="form-group" style="margin-bottom:var(--space-md);">
                                <textarea name="comment" class="form-input" style="height:80px; resize:none; padding:12px;" placeholder="Bu haber hakkında ne düşünüyorsun? Saygılı ve yapıcı yorumlar yazmaya özen gösterin..." required></textarea>
                            </div>
                            <div style="display:flex; justify-content:flex-end;">
                                <button type="submit" class="btn btn-primary btn-sm" style="border-radius:var(--radius-sm);"><i class="fas fa-paper-plane"></i> Yorum Gönder</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div style="text-align:center; padding:var(--space-md); color:var(--text-secondary);">
                            <p style="margin-bottom:var(--space-md); font-size:0.9rem;">Yorum yapabilmek için lütfen giriş yapın veya kayıt olun.</p>
                            <div style="display:inline-flex; gap:var(--space-sm);">
                                <a href="<?= SITE_URL ?>/giris.php" class="btn btn-ghost btn-sm" style="border-radius:var(--radius-sm);"><i class="fas fa-sign-in-alt"></i> Giriş Yap</a>
                                <a href="<?= SITE_URL ?>/kayit.php" class="btn btn-primary btn-sm" style="border-radius:var(--radius-sm);"><i class="fas fa-user-plus"></i> Kayıt Ol</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Yorum Listesi -->
                <div class="yorum-liste">
                    <?php if (empty($yorumlar)): ?>
                        <div class="bos-durum" style="padding:var(--space-xl); border:1px dashed var(--border); border-radius:var(--radius-md);">
                            <i class="far fa-comment-dots"></i>
                            <p>İlk yorumu siz yapın!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($yorumlar as $y): ?>
                            <div class="yorum-kart">
                                <img src="<?= avatarURL($y['avatar']) ?>" alt="Kullanıcı Avatarı" class="yorum-avatar">
                                <div class="yorum-icerik-kutu">
                                    <div class="yorum-ust">
                                        <span class="yorum-yazar">
                                            <a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $y['user_id'] ?>"><?= temizle($y['username']) ?></a>
                                        </span>
                                        <span class="yorum-tarih">
                                            <i class="far fa-clock"></i> <?= tarihFormatla($y['created_at'], 'relative') ?>
                                        </span>
                                    </div>
                                    <div class="yorum-yazi">
                                        <?= nl2br(temizle($y['comment'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- İlgili Haberler -->
        <?php if (!empty($ilgiliHaberler)): ?>
        <div style="margin-top:var(--space-3xl);">
            <h2 class="section-title"><i class="fas fa-newspaper"></i> İlgili Haberler</h2>
            <div class="grid grid-3" style="margin-top:var(--space-lg);">
                <?php foreach ($ilgiliHaberler as $ih): ?>
                <div class="haber-kart fade-in">
                    <div class="haber-kart-resim">
                        <?php $hImg = haberResimURL($ih['featured_image']); ?>
                        <?php if ($hImg): ?>
                            <img src="<?= $hImg ?>" alt="<?= temizle($ih['title']) ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <i class="fas fa-newspaper ikon-placeholder"></i>
                        <?php endif; ?>
                    </div>
                    <div class="haber-kart-icerik">
                        <div class="haber-kart-tarih"><i class="fas fa-clock"></i> <?= tarihFormatla($ih['created_at'],'relative') ?></div>
                        <h3 class="haber-kart-baslik"><a href="<?= SITE_URL ?>/haber-detay.php?id=<?= $ih['id'] ?>"><?= temizle($ih['title']) ?></a></h3>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
