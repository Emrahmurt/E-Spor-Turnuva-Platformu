<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';
adminGerekli();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula($_POST['csrf_token'] ?? '')) {
    $aksiyon = $_POST['aksiyon'] ?? '';
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($aksiyon === 'rol' && $uid) {
        $db->query("UPDATE kullanicilar SET role = ? WHERE id = ?", [$_POST['rol'], $uid]);
        flashMesaj('Kullanıcı rolü güncellendi.', 'success');
    } elseif ($aksiyon === 'durum' && $uid) {
        $db->query("UPDATE kullanicilar SET is_active = ? WHERE id = ?", [$_POST['aktif'] ? 1 : 0, $uid]);
        flashMesaj('Kullanıcı durumu güncellendi.', 'success');
    }
    header('Location: ' . SITE_URL . '/yonetim/kullanicilar.php'); exit;
}

$kullanicilar = $db->fetchAll("SELECT * FROM kullanicilar ORDER BY created_at DESC");

$sayfaBasligi = 'Kullanıcı Yönetimi';
require_once dirname(__DIR__) . '/includes/ust.php';
?>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <div class="dashboard-grid">
            <!-- Sidebar -->
            <?php 
            $aktifSekme = 'kullanicilar';
            require_once __DIR__ . '/sidebar.php'; 
            ?>

            <!-- İçerik -->
            <div class="dashboard-icerik">
                <?= flashGoster() ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xl);padding-bottom:var(--space-lg);border-bottom:1px solid var(--border-light)">
                    <h2 style="font-size:1.5rem;"><i class="fas fa-users" style="color:var(--accent)"></i> Kullanıcı Yönetimi (<?= count($kullanicilar) ?>)</h2>
                </div>
                
                <div class="tablo-wrapper">
                    <table class="tablo">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kullanıcı</th>
                                <th>E-posta</th>
                                <th>Rol</th>
                                <th>Puan</th>
                                <th>Durum</th>
                                <th>Kayıt</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($kullanicilar as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td class="wrap-text">
                                    <a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $u['id'] ?>" style="color:var(--accent);font-weight:600;" target="_blank">
                                        <?= temizle($u['username']) ?>
                                    </a>
                                </td>
                                <td style="font-size:0.8rem;color:var(--text-secondary);"><?= temizle($u['email']) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="aksiyon" value="rol">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <select name="rol" onchange="this.form.submit()" style="background:var(--bg-surface);color:var(--text-primary);border:1px solid var(--border);padding:4px 8px;border-radius:var(--radius-sm);font-size:0.8rem;cursor:pointer;">
                                            <?php foreach (['user','player','moderator','admin'] as $r): ?>
                                                <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= rolCevir($r, 'user') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td style="font-weight:600;color:var(--neon);"><?= $u['points'] ?></td>
                                <td>
                                    <span class="badge badge-<?= $u['is_active'] ? 'success' : 'danger' ?>">
                                        <?= $u['is_active'] ? 'Aktif' : 'Pasif' ?>
                                    </span>
                                </td>
                                <td style="font-size:0.8rem;color:var(--text-muted);"><?= tarihFormatla($u['created_at'], 'short') ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="aksiyon" value="durum">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="aktif" value="<?= $u['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-ghost btn-sm" style="padding:4px 8px;" title="<?= $u['is_active'] ? 'Deaktif Et' : 'Aktif Et' ?>">
                                            <i class="fas fa-<?= $u['is_active'] ? 'ban' : 'check' ?>"></i>
                                        </button>
                                    </form>
                                    <a href="<?= SITE_URL ?>/panel.php?tab=profil&id=<?= $u['id'] ?>" class="btn btn-ghost btn-sm" style="padding:4px 8px;" title="Profili Düzenle">
                                        <i class="fas fa-edit"></i>
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
