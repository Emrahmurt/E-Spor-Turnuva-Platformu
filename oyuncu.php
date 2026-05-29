<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/kimlik.php';
require_once __DIR__ . '/includes/fonksiyonlar.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . SITE_URL . '/oyuncular.php'); exit; }

$oyuncu = $db->fetch("SELECT * FROM kullanicilar WHERE id = ?", [$id]);
if (!$oyuncu) { header('Location: ' . SITE_URL . '/oyuncular.php'); exit; }

// Profil Güncelleme POST işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksiyon']) && $_POST['aksiyon'] === 'profil_guncelle') {
    if (girisYapmisMi() && ($_SESSION['user_id'] == $oyuncu['id'] || adminMi())) {
        if (csrfDogrula($_POST['csrf_token'] ?? '')) {
            $avatarFilename = $oyuncu['avatar'];
            
            // Avatar yükleme
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload = dosyaYukle($_FILES['avatar'], 'avatars', MAX_AVATAR_SIZE);
                if ($upload['success']) {
                    // Varsayılan ve sistem avatarları dışındakileri temizle
                    if ($oyuncu['avatar'] && $oyuncu['avatar'] !== 'default-avatar.png' && strpos($oyuncu['avatar'], 'avatar_') !== 0) {
                        $oldFile = UPLOADS_PATH . 'avatars' . DIRECTORY_SEPARATOR . $oyuncu['avatar'];
                        if (file_exists($oldFile)) { @unlink($oldFile); }
                    }
                    $avatarFilename = $upload['filename'];
                    if ($oyuncu['id'] == $_SESSION['user_id']) {
                        $_SESSION['avatar'] = $avatarFilename;
                    }
                } else {
                    flashMesaj('Profil resmi yüklenemedi: ' . $upload['error'], 'error');
                    header('Location: ' . SITE_URL . '/oyuncu.php?id=' . $id);
                    exit;
                }
            }

            $db->query("UPDATE kullanicilar SET first_name=?, last_name=?, bio=?, country=?, discord=?, steam_id=?, phone=?, avatar=? WHERE id=?", [
                trim($_POST['ad'] ?? ''), trim($_POST['soyad'] ?? ''), trim($_POST['bio'] ?? ''),
                trim($_POST['ulke'] ?? ''), trim($_POST['discord'] ?? ''), trim($_POST['steam_id'] ?? ''),
                trim($_POST['telefon'] ?? ''), $avatarFilename, $oyuncu['id']
            ]);

            flashMesaj('Profil başarıyla güncellendi!', 'success');
            header('Location: ' . SITE_URL . '/oyuncu.php?id=' . $id);
            exit;
        } else {
            flashMesaj('Güvenlik doğrulaması başarısız oldu.', 'error');
        }
    } else {
        flashMesaj('Bu işlemi yapmaya yetkiniz yok.', 'error');
    }
}

$sayfaBasligi = $oyuncu['username'];
$takimlar = $db->fetchAll("SELECT tm.*, t.name as takim_adi, t.logo, t.slug, t.points as takim_puan, g.name as oyun_adi
    FROM takim_uyeleri tm LEFT JOIN takimlar t ON tm.team_id = t.id LEFT JOIN oyunlar g ON t.game_id = g.id
    WHERE tm.user_id = ? ORDER BY tm.joined_at DESC", [$id]);

require_once __DIR__ . '/includes/ust.php';
?>

<section class="sayfa-basligi">
    <div class="container sayfa-basligi-icerik">
        <div class="breadcrumb" style="margin-bottom:var(--space-sm);">
            <a href="<?= SITE_URL ?>">Ana Sayfa</a> <i class="fas fa-chevron-right"></i>
            <a href="<?= SITE_URL ?>/oyuncular.php">Oyuncular</a> <i class="fas fa-chevron-right"></i>
            <span><?= temizle($oyuncu['username']) ?></span>
        </div>
    </div>
</section>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <div class="profil-header fade-in">
            <div class="profil-avatar">
                <img src="<?= avatarURL($oyuncu['avatar'] ?? '') ?>" alt="<?= temizle($oyuncu['username']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
            </div>
            <div class="profil-bilgi">
                <div style="display:flex;align-items:center;gap:var(--space-md);flex-wrap:wrap;margin-bottom:6px;">
                    <h1 class="profil-isim" style="margin-bottom:0;"><?= temizle($oyuncu['username']) ?></h1>
                    <?php if (girisYapmisMi() && ($_SESSION['user_id'] == $oyuncu['id'] || adminMi())): ?>
                        <button onclick="document.getElementById('editProfileModal').classList.add('aktif');" class="btn btn-ghost btn-sm" style="padding:6px 12px;font-size:0.8rem; border-radius:var(--radius-sm);"><i class="fas fa-user-edit"></i> Profili Düzenle</button>
                    <?php endif; ?>
                </div>
                <p style="color:var(--text-secondary);margin-bottom:var(--space-sm);"><?= temizle(trim(($oyuncu['first_name'] ?? '') . ' ' . ($oyuncu['last_name'] ?? ''))) ?></p>
                <div class="profil-meta">
                    <?php if ($oyuncu['country']): ?><span><i class="fas fa-map-marker-alt"></i> <?= temizle($oyuncu['country']) ?></span><?php endif; ?>
                    <?php if ($oyuncu['discord']): ?><span><i class="fab fa-discord"></i> <?= temizle($oyuncu['discord']) ?></span><?php endif; ?>
                    <?php if ($oyuncu['steam_id']): ?><span><i class="fab fa-steam"></i> <?= temizle($oyuncu['steam_id']) ?></span><?php endif; ?>
                    <span><i class="fas fa-calendar"></i> <?= tarihFormatla($oyuncu['created_at'], 'short') ?></span>
                </div>
                <div class="profil-istatistik">
                    <div class="profil-stat"><span class="profil-stat-sayi"><?= $oyuncu['total_wins'] + $oyuncu['total_losses'] ?></span><span class="profil-stat-etiket">Maç</span></div>
                    <div class="profil-stat"><span class="profil-stat-sayi"><?= $oyuncu['total_wins'] ?></span><span class="profil-stat-etiket">Galibiyet</span></div>
                    <div class="profil-stat"><span class="profil-stat-sayi"><?= $oyuncu['total_losses'] ?></span><span class="profil-stat-etiket">Mağlubiyet</span></div>
                    <div class="profil-stat"><span class="profil-stat-sayi"><?= kazanmaOrani($oyuncu['total_wins'],$oyuncu['total_losses']) ?></span><span class="profil-stat-etiket">Kazanma %</span></div>
                    <div class="profil-stat"><span class="profil-stat-sayi"><?= $oyuncu['points'] ?></span><span class="profil-stat-etiket">Puan</span></div>
                </div>
            </div>
        </div>

        <?php if ($oyuncu['bio']): ?>
        <div class="kart fade-in" style="padding:var(--space-xl);margin-bottom:var(--space-xl);">
            <h3 style="margin-bottom:var(--space-md);"><i class="fas fa-user" style="color:var(--accent)"></i> Hakkında</h3>
            <p style="color:var(--text-secondary);line-height:1.8;"><?= nl2br(temizle($oyuncu['bio'])) ?></p>
        </div>
        <?php endif; ?>

        <h3 style="margin-bottom:var(--space-lg);"><i class="fas fa-users" style="color:var(--accent)"></i> Takımları</h3>
        <?php if (empty($takimlar)): ?>
            <div class="bos-durum"><i class="fas fa-users"></i><h3>Henüz bir takıma üye değil</h3></div>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($takimlar as $t): ?>
                <div class="takim-kart fade-in">
                    <div class="takim-kart-logo">
                        <?php if (!empty($t['logo']) && $t['logo'] !== 'default-team.png'): ?>
                            <img src="<?= SITE_URL ?>/<?= temizle($t['logo']) ?>" alt="<?= temizle($t['takim_adi']) ?>">
                        <?php else: ?>
                            <i class="fas fa-shield-halved"></i>
                        <?php endif; ?>
                    </div>
                    <h3 class="takim-kart-isim"><a href="<?= SITE_URL ?>/takim.php?id=<?= $t['team_id'] ?>"><?= temizle($t['takim_adi']) ?></a></h3>
                    <div class="takim-kart-oyun"><?= temizle($t['oyun_adi']) ?></div>
                    <div class="oyuncu-kart-rol" style="margin:var(--space-sm) auto;"><i class="fas fa-<?= $t['role']==='captain'?'crown':'user' ?>"></i> <?= rolCevir($t['role'], 'team') ?></div>
                    <div class="takim-kart-istatistik">
                        <div class="takim-stat"><span class="takim-stat-sayi"><?= $t['takim_puan'] ?></span><span class="takim-stat-etiket">Takım Puan</span></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Profil Düzenleme Modali -->
<?php if (girisYapmisMi() && ($_SESSION['user_id'] == $oyuncu['id'] || adminMi())): ?>
<div class="modal-wrapper" id="editProfileModal">
    <div class="modal-icerik">
        <div class="modal-baslik">
            <h3><i class="fas fa-user-edit" style="color:var(--accent); margin-right:6px;"></i> Profili Düzenle</h3>
            <button class="modal-kapat" onclick="document.getElementById('editProfileModal').classList.remove('aktif');">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="modal-form">
            <?= csrfInput() ?>
            <input type="hidden" name="aksiyon" value="profil_guncelle">

            <!-- Avatar Yükleme Alanı -->
            <div style="display:flex; justify-content:center; margin-bottom:var(--space-lg);">
                <div class="avatar-upload-zone" style="position:relative; width:100px; height:100px; border-radius:50%; border:2px dashed var(--accent); overflow:hidden; background:var(--bg-surface); display:flex; align-items:center; justify-content:center; transition:var(--transition-fast);">
                    <img src="<?= avatarURL($oyuncu['avatar'] ?? '') ?>" alt="Kullanıcı Avatarı" style="width:100%; height:100%; object-fit:cover;" id="avatarPreview">
                    <div class="avatar-upload-overlay" style="position:absolute; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); display:flex; flex-direction:column; align-items:center; justify-content:center; opacity:0; transition:var(--transition-fast); color:var(--text-white); font-size:0.7rem; pointer-events:none;">
                        <i class="fas fa-camera" style="font-size:1.1rem; margin-bottom:2px;"></i>
                        <span>Değiştir</span>
                    </div>
                    <input type="file" name="avatar" id="avatarFileInput" accept="image/*" style="position:absolute; left:0; top:0; opacity:0; cursor:pointer; width:100%; height:100%; z-index:2;" onchange="if(this.files[0]) { document.getElementById('avatarPreview').src = window.URL.createObjectURL(this.files[0]); }">
                </div>
            </div>

            <!-- Form Alanları Grid -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md); margin-bottom:var(--space-md);">
                <div class="form-group">
                    <label class="form-label">Ad</label>
                    <input type="text" name="ad" class="form-input" value="<?= temizle($oyuncu['first_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Soyad</label>
                    <input type="text" name="soyad" class="form-input" value="<?= temizle($oyuncu['last_name'] ?? '') ?>">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md); margin-bottom:var(--space-md);">
                <div class="form-group">
                    <label class="form-label">Telefon</label>
                    <input type="text" name="telefon" class="form-input" value="<?= temizle($oyuncu['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Ülke</label>
                    <input type="text" name="ulke" class="form-input" value="<?= temizle($oyuncu['country'] ?? '') ?>">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md); margin-bottom:var(--space-md);">
                <div class="form-group">
                    <label class="form-label">Discord Kullanıcı Adı</label>
                    <input type="text" name="discord" class="form-input" value="<?= temizle($oyuncu['discord'] ?? '') ?>" placeholder="kullanici#0000 veya kullanici">
                </div>
                <div class="form-group">
                    <label class="form-label">Steam ID / Profil Linki</label>
                    <input type="text" name="steam_id" class="form-input" value="<?= temizle($oyuncu['steam_id'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:var(--space-lg);">
                <label class="form-label">Biyografi</label>
                <textarea name="bio" class="form-input" style="height:100px; resize:none;" placeholder="Kendinizden bahsedin..."><?= temizle($oyuncu['bio'] ?? '') ?></textarea>
            </div>

            <div style="display:flex; gap:var(--space-sm); justify-content:flex-end;">
                <button type="button" class="btn btn-ghost btn-sm" style="border-radius:var(--radius-sm);" onclick="document.getElementById('editProfileModal').classList.remove('aktif');">İptal</button>
                <button type="submit" class="btn btn-primary btn-sm" style="border-radius:var(--radius-sm);"><i class="fas fa-save"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
// ESC tuşuna basınca kapat
window.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        var modal = document.getElementById('editProfileModal');
        if (modal) {
            modal.classList.remove('aktif');
        }
    }
});

// Dışarıya tıklayınca kapat
var modal = document.getElementById('editProfileModal');
if (modal) {
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.classList.remove('aktif');
        }
    });
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
