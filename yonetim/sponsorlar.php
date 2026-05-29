<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';
adminGerekli();

$editSponsor = null;
$editId = (int)($_GET['edit_id'] ?? 0);
if ($editId) {
    $editSponsor = $db->fetch("SELECT * FROM sponsorlar WHERE id = ?", [$editId]);
}

// POST İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula($_POST['csrf_token'] ?? '')) {
    $aksiyon = $_POST['aksiyon'] ?? '';
    
    if ($aksiyon === 'ekle_guncelle') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (!$name) {
            flashMesaj('Sponsor adı boş olamaz.', 'error');
        } else {
            $logoFilename = '';
            if ($id) {
                // Güncelleme için eski logo
                $exist = $db->fetch("SELECT logo FROM sponsorlar WHERE id = ?", [$id]);
                $logoFilename = $exist ? $exist['logo'] : '';
            }
            
            // Logo yükleme
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload = dosyaYukle($_FILES['logo'], 'sponsors', MAX_AVATAR_SIZE);
                if ($upload['success']) {
                    // Eski logoyu sil
                    if ($id && $logoFilename) {
                        $oldFile = UPLOADS_PATH . 'sponsors' . DIRECTORY_SEPARATOR . $logoFilename;
                        if (file_exists($oldFile)) { @unlink($oldFile); }
                    }
                    $logoFilename = $upload['filename'];
                } else {
                    flashMesaj('Logo yüklenemedi: ' . $upload['error'], 'error');
                    header('Location: ' . SITE_URL . '/yonetim/sponsorlar.php' . ($id ? "?edit_id=$id" : ""));
                    exit;
                }
            }
            
            if ($id) {
                // Güncelle
                $db->query("UPDATE sponsorlar SET name = ?, logo = ?, link = ?, description = ? WHERE id = ?", [
                    $name, $logoFilename, $link, $description, $id
                ]);
                flashMesaj('Sponsor güncellendi!', 'success');
            } else {
                // Ekle
                if (!$logoFilename) {
                    flashMesaj('Yeni sponsor için logo görseli zorunludur.', 'error');
                    header('Location: ' . SITE_URL . '/yonetim/sponsorlar.php');
                    exit;
                }
                $db->insert("INSERT INTO sponsorlar (name, logo, link, description) VALUES (?, ?, ?, ?)", [
                    $name, $logoFilename, $link, $description
                ]);
                flashMesaj('Sponsor eklendi!', 'success');
            }
            header('Location: ' . SITE_URL . '/yonetim/sponsorlar.php');
            exit;
        }
    } elseif ($aksiyon === 'sil') {
        $sid = (int)($_POST['sponsor_id'] ?? 0);
        $exist = $db->fetch("SELECT logo FROM sponsorlar WHERE id = ?", [$sid]);
        if ($exist) {
            // Logoyu sil
            if ($exist['logo']) {
                $file = UPLOADS_PATH . 'sponsors' . DIRECTORY_SEPARATOR . $exist['logo'];
                if (file_exists($file)) { @unlink($file); }
            }
            $db->query("DELETE FROM sponsorlar WHERE id = ?", [$sid]);
            flashMesaj('Sponsor başarıyla silindi.', 'success');
        }
        header('Location: ' . SITE_URL . '/yonetim/sponsorlar.php');
        exit;
    }
}

$sponsorlar = $db->fetchAll("
    SELECT s.*, 
    (SELECT COUNT(*) FROM turnuva_sponsorlari WHERE sponsor_id = s.id) as turnuva_sayisi,
    (SELECT SUM(prize_contribution) FROM turnuva_sponsorlari WHERE sponsor_id = s.id) as toplam_katki
    FROM sponsorlar s ORDER BY s.name
");

$sayfaBasligi = 'Sponsor Yönetimi';
require_once dirname(__DIR__) . '/includes/ust.php';
?>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <div class="dashboard-grid">
            <!-- Sidebar -->
            <?php 
            $aktifSekme = 'sponsorlar';
            require_once __DIR__ . '/sidebar.php'; 
            ?>

            <!-- İçerik -->
            <div class="dashboard-icerik">
                <?= flashGoster() ?>
                
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xl);padding-bottom:var(--space-lg);border-bottom:1px solid var(--border-light)">
                    <h2 style="font-size:1.5rem;"><i class="fas fa-handshake" style="color:var(--accent)"></i> Sponsor Yönetimi (<?= count($sponsorlar) ?>)</h2>
                </div>

                <!-- Form Kartı (Ekle veya Düzenle) -->
                <div class="kart" style="padding:var(--space-xl);margin-bottom:var(--space-xl);">
                    <h3 style="margin-bottom:var(--space-md);color:var(--accent);">
                        <i class="fas fa-<?= $editSponsor ? 'edit' : 'plus-circle' ?>"></i>
                        <?= $editSponsor ? 'Sponsoru Düzenle (' . temizle($editSponsor['name']) . ')' : 'Yeni Sponsor Ekle' ?>
                    </h3>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfInput() ?>
                        <input type="hidden" name="aksiyon" value="ekle_guncelle">
                        <?php if ($editSponsor): ?>
                            <input type="hidden" name="id" value="<?= $editSponsor['id'] ?>">
                        <?php endif; ?>
                        
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md); margin-bottom:var(--space-md); flex-wrap:wrap;">
                            <div class="form-grup">
                                <label class="form-label">Sponsor Adı *</label>
                                <input type="text" name="name" class="form-input" value="<?= $editSponsor ? temizle($editSponsor['name']) : '' ?>" placeholder="Örn: SteelSeries" required>
                            </div>
                            
                            <div class="form-grup">
                                <label class="form-label">Web Sitesi URL (Opsiyonel)</label>
                                <input type="url" name="link" class="form-input" value="<?= $editSponsor ? temizle($editSponsor['link']) : '' ?>" placeholder="Örn: https://steelseries.com">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md); margin-bottom:var(--space-md); align-items:center;">
                            <div class="form-grup">
                                <label class="form-label">Sponsor Logosu <?= $editSponsor ? '(Değiştirmek istemiyorsanız boş bırakın)' : '*' ?></label>
                                <input type="file" name="logo" class="form-input" <?= $editSponsor ? '' : 'required' ?> accept="image/*">
                            </div>
                            
                            <?php if ($editSponsor && $editSponsor['logo']): ?>
                                <div style="display:flex; align-items:center; gap:var(--space-sm);">
                                    <span style="font-size:0.8rem; color:var(--text-muted);">Mevcut Logo:</span>
                                    <img src="<?= UPLOADS_URL . '/sponsors/' . $editSponsor['logo'] ?>" style="height:44px; max-width:120px; object-fit:contain; background:rgba(255,255,255,0.05); padding:4px; border-radius:var(--radius-sm); border:1px solid var(--border-light);">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-grup" style="margin-bottom:var(--space-md);">
                            <label class="form-label">Açıklama (Opsiyonel)</label>
                            <textarea name="description" class="form-input" rows="3" placeholder="Sponsor hakkında kısa bilgi..." style="resize:vertical;min-height:80px;"><?= $editSponsor ? temizle($editSponsor['description']) : '' ?></textarea>
                        </div>
                        
                        <div style="display:flex; gap:var(--space-sm); justify-content:flex-end;">
                            <?php if ($editSponsor): ?>
                                <a href="<?= SITE_URL ?>/yonetim/sponsorlar.php" class="btn btn-ghost"><i class="fas fa-times"></i> İptal</a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-<?= $editSponsor ? 'save' : 'plus' ?>"></i>
                                <?= $editSponsor ? 'Güncellemeyi Kaydet' : 'Sponsor Ekle' ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Sponsorlar Tablosu -->
                <div class="tablo-wrapper">
                    <table class="tablo">
                        <thead>
                            <tr>
                                <th style="width:60px;">Logo</th>
                                <th>Sponsor</th>
                                <th>Bağlantı</th>
                                <th>Desteklediği Turnuvalar</th>
                                <th>Toplam Katkı</th>
                                <th style="width:120px; text-align:center;">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($sponsorlar)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; color:var(--text-muted); padding:var(--space-lg);">
                                    Henüz sponsor eklenmemiş.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sponsorlar as $s): ?>
                                <tr>
                                    <td>
                                        <img src="<?= UPLOADS_URL . '/sponsors/' . $s['logo'] ?>" style="width:48px; height:32px; object-fit:contain; background:rgba(255,255,255,0.03); padding:2px; border-radius:var(--radius-sm); border:1px solid var(--border-light);">
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><?= temizle($s['name']) ?></div>
                                        <?php if ($s['description']): ?>
                                            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;" class="wrap-text"><?= temizle(mb_substr($s['description'], 0, 80)) ?>...</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="wrap-text">
                                        <?php if ($s['link']): ?>
                                            <a href="<?= temizle($s['link']) ?>" target="_blank" style="color:var(--accent); font-size:0.85rem;"><i class="fas fa-external-link-alt"></i> Git</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?= $s['turnuva_sayisi'] ?> Turnuva</span>
                                    </td>
                                    <td style="color:var(--gold); font-weight:600;">
                                        <?= paraFormatla($s['toplam_katki'] ?: 0) ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <div style="display:flex; gap:6px; justify-content:center; align-items:center;">
                                            <a href="?edit_id=<?= $s['id'] ?>" class="btn btn-ghost btn-sm" style="padding:4px 8px;" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Bu sponsoru silmek istediğinizden emin misiniz? Bu sponsora ait tüm turnuva ilişkileri ve katkılar silinecektir!')">
                                                <?= csrfInput() ?>
                                                <input type="hidden" name="aksiyon" value="sil">
                                                <input type="hidden" name="sponsor_id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="btn btn-ghost btn-sm" style="padding:4px 8px; color:var(--danger);" title="Sil">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/alt.php'; ?>
