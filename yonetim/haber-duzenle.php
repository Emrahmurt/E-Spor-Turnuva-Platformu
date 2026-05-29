<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';
adminGerekli();

$id = (int)($_GET['id'] ?? 0);
$haber = $id ? $db->fetch("SELECT * FROM haberler WHERE id = ?", [$id]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula($_POST['csrf_token'] ?? '')) {
    $data = [
        trim($_POST['title']), slugOlustur(trim($_POST['title'])), trim($_POST['content']),
        trim($_POST['excerpt']), $_POST['category'], isset($_POST['is_featured'])?1:0,
        isset($_POST['is_published'])?1:0, trim($_POST['tags'] ?? '')
    ];
    if ($id) {
        $data[] = $id;
        $db->query("UPDATE haberler SET title=?,slug=?,content=?,excerpt=?,category=?,is_featured=?,is_published=?,tags=? WHERE id=?", $data);
        flashMesaj('Haber güncellendi!', 'success');
    } else {
        $data[] = $_SESSION['user_id'];
        $db->insert("INSERT INTO haberler (title,slug,content,excerpt,category,is_featured,is_published,tags,author_id) VALUES (?,?,?,?,?,?,?,?,?)", $data);
        flashMesaj('Haber oluşturuldu!', 'success');
    }
    header('Location: ' . SITE_URL . '/yonetim/haberler.php'); exit;
}

$sayfaBasligi = $id ? 'Haber Düzenle' : 'Yeni Haber';
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
                    <h2 style="font-size:1.5rem;"><i class="fas fa-<?= $id?'edit':'plus-circle' ?>" style="color:var(--accent)"></i> <?= $id?'Haber Düzenle':'Yeni Haber' ?></h2>
                </div>

                <div class="kart" style="padding:var(--space-xl);">
                    <form method="POST">
                        <?= csrfInput() ?>
                        <div class="form-grup">
                            <label class="form-label">Başlık *</label>
                            <input type="text" name="title" class="form-input" value="<?= temizle($haber['title'] ?? '') ?>" required>
                        </div>
                        
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md);flex-wrap:wrap;">
                            <div class="form-grup">
                                <label class="form-label">Kategori</label>
                                <select name="category" class="form-input">
                                    <?php foreach (['haberler','turnuva','esports','rehber','duyuru'] as $k): ?>
                                        <option value="<?= $k ?>" <?= ($haber['category'] ?? 'haberler')===$k?'selected':'' ?>><?= kategoriCevir($k) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-grup">
                                <label class="form-label">Etiketler (virgülle ayırın)</label>
                                <input type="text" name="tags" class="form-input" value="<?= temizle($haber['tags'] ?? '') ?>" placeholder="örn: esports, valorant, turnuva">
                            </div>
                        </div>

                        <div class="form-grup">
                            <label class="form-label">Özet</label>
                            <textarea name="excerpt" class="form-input" rows="2" placeholder="Haberin kısa özeti..."><?= temizle($haber['excerpt'] ?? '') ?></textarea>
                        </div>

                        <div class="form-grup">
                            <label class="form-label">İçerik *</label>
                            <textarea name="content" class="form-input" rows="12" required placeholder="Haber içeriğini buraya girin (HTML etiketleri kullanılabilir)..."><?= temizle($haber['content'] ?? '') ?></textarea>
                        </div>

                        <div style="display:flex;gap:var(--space-lg);margin-bottom:var(--space-lg);user-select:none;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" name="is_published" <?= ($haber['is_published'] ?? 1)?'checked':'' ?> style="accent-color:var(--accent)"> Yayınla
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" name="is_featured" <?= ($haber['is_featured'] ?? 0)?'checked':'' ?> style="accent-color:var(--accent)"> Öne Çıkar
                            </label>
                        </div>

                        <div style="display:flex;gap:var(--space-md);margin-top:var(--space-lg);">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $id?'Güncelle':'Oluştur' ?></button>
                            <a href="<?= SITE_URL ?>/yonetim/haberler.php" class="btn btn-ghost">İptal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/alt.php'; ?>
