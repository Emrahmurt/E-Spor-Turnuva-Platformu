<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';
adminGerekli();

// Oyun ekleme / güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula($_POST['csrf_token'] ?? '')) {
    $aksiyon = $_POST['aksiyon'] ?? '';
    if ($aksiyon === 'ekle') {
        $ad = trim($_POST['name'] ?? '');
        if ($ad) {
            $db->insert("INSERT INTO oyunlar (name, slug, description, is_active) VALUES (?,?,?,1)", [$ad, slugOlustur($ad), trim($_POST['description'] ?? '')]);
            flashMesaj('Oyun eklendi!', 'success');
        }
    } elseif ($aksiyon === 'toggle') {
        $gid = (int)($_POST['game_id'] ?? 0);
        $db->query("UPDATE oyunlar SET is_active = NOT is_active WHERE id = ?", [$gid]);
        flashMesaj('Oyun durumu değiştirildi.', 'success');
    }
    header('Location: ' . SITE_URL . '/yonetim/oyunlar.php'); exit;
}

$oyunlar = $db->fetchAll("SELECT g.*, (SELECT COUNT(*) FROM turnuvalar WHERE game_id = g.id) as turnuva_sayisi FROM oyunlar g ORDER BY g.name");

$sayfaBasligi = 'Oyun Yönetimi';
require_once dirname(__DIR__) . '/includes/ust.php';
?>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <div class="dashboard-grid">
            <!-- Sidebar -->
            <?php 
            $aktifSekme = 'oyunlar';
            require_once __DIR__ . '/sidebar.php'; 
            ?>

            <!-- İçerik -->
            <div class="dashboard-icerik">
                <?= flashGoster() ?>
                
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xl);padding-bottom:var(--space-lg);border-bottom:1px solid var(--border-light)">
                    <h2 style="font-size:1.5rem;"><i class="fas fa-gamepad" style="color:var(--accent)"></i> Oyun Yönetimi (<?= count($oyunlar) ?>)</h2>
                </div>

                <div class="kart" style="padding:var(--space-xl);margin-bottom:var(--space-xl);">
                    <h3 style="margin-bottom:var(--space-md);"><i class="fas fa-plus-circle" style="color:var(--neon)"></i> Yeni Oyun Ekle</h3>
                    <form method="POST" style="display:flex;gap:var(--space-md);align-items:flex-end;flex-wrap:wrap;">
                        <?= csrfInput() ?>
                        <input type="hidden" name="aksiyon" value="ekle">
                        <div class="form-grup" style="flex:1;min-width:200px;margin:0;">
                            <label class="form-label">Oyun Adı</label>
                            <input type="text" name="name" class="form-input" placeholder="Örn: Counter-Strike 2" required>
                        </div>
                        <div class="form-grup" style="flex:2;min-width:300px;margin:0;">
                            <label class="form-label">Açıklama (Opsiyonel)</label>
                            <input type="text" name="description" class="form-input" placeholder="Oyun hakkında kısa bilgi...">
                        </div>
                        <button type="submit" class="btn btn-primary" style="height:44px;"><i class="fas fa-plus"></i> Ekle</button>
                    </form>
                </div>

                <div class="tablo-wrapper">
                    <table class="tablo">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Oyun</th>
                                <th>Slug</th>
                                <th>Turnuva Sayısı</th>
                                <th>Durum</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($oyunlar as $o): ?>
                            <tr>
                                <td><?= $o['id'] ?></td>
                                <td class="wrap-text" style="font-weight:600;"><?= temizle($o['name']) ?></td>
                                <td style="color:var(--text-muted);font-size:0.8rem;"><?= $o['slug'] ?></td>
                                <td><?= $o['turnuva_sayisi'] ?></td>
                                <td>
                                    <span class="badge badge-<?= $o['is_active'] ? 'success' : 'danger' ?>">
                                        <?= $o['is_active'] ? 'Aktif' : 'Pasif' ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="aksiyon" value="toggle">
                                        <input type="hidden" name="game_id" value="<?= $o['id'] ?>">
                                        <button type="submit" class="btn btn-ghost btn-sm" style="padding:4px 8px;" title="<?= $o['is_active'] ? 'Gizle / Devre Dışı Bırak' : 'Göster / Aktifleştir' ?>">
                                            <i class="fas fa-<?= $o['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                        </button>
                                    </form>
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
