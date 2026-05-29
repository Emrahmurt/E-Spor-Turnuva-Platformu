<?php
$sayfaBasligi = 'Kayıt Ol';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/kimlik.php';
require_once __DIR__ . '/includes/fonksiyonlar.php';
require_once __DIR__ . '/includes/eposta.php';

if (girisYapmisMi()) { header('Location: ' . SITE_URL . '/panel.php'); exit; }

$hata = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfDogrula($_POST['csrf_token'] ?? '')) {
        $hata = 'Geçersiz istek.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $ad = trim($_POST['ad'] ?? '');
        $soyad = trim($_POST['soyad'] ?? '');
        $sifre = $_POST['sifre'] ?? '';
        $sifreTekrar = $_POST['sifre_tekrar'] ?? '';

        if (empty($username) || empty($email) || empty($sifre)) {
            $hata = 'Kullanıcı adı, e-posta ve şifre zorunludur.';
        } elseif (strlen($username) < 3) {
            $hata = 'Kullanıcı adı en az 3 karakter olmalıdır.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $hata = 'Geçerli bir e-posta adresi giriniz.';
        } elseif (strlen($sifre) < 6) {
            $hata = 'Şifre en az 6 karakter olmalıdır.';
        } elseif ($sifre !== $sifreTekrar) {
            $hata = 'Şifreler eşleşmiyor.';
        } else {
            $sonuc = kayitOl($username, $email, $sifre, $ad, $soyad);
            if ($sonuc['success']) {
                // Hoş geldin e-postası gönder
                $yeniKullanici = $db->fetch("SELECT * FROM kullanicilar WHERE id = ?", [$sonuc['user_id']]);
                if ($yeniKullanici) {
                    epostaHosgeldin($yeniKullanici);
                }
                
                flashMesaj('Hesabınız başarıyla oluşturuldu! Şimdi giriş yapabilirsiniz.', 'success');
                header('Location: ' . SITE_URL . '/giris.php');
                exit;
            } else {
                $hata = $sonuc['error'];
            }
        }
    }
}

require_once __DIR__ . '/includes/ust.php';
?>

<div class="auth-container">
    <div class="auth-kutu fade-in">
        <div class="auth-logo">
            <i class="fas fa-gamepad"></i>
            <h2>Hesap Oluştur</h2>
            <p>E-spor maceranıza başlayın</p>
        </div>

        <?php if ($hata): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= temizle($hata) ?></div>
        <?php endif; ?>

        <form method="POST" data-dogrula>
            <?= csrfInput() ?>
            <div class="form-grup">
                <label class="form-label">Kullanıcı Adı</label>
                <input type="text" name="username" class="form-input" placeholder="ornek_oyuncu" value="<?= temizle($_POST['username'] ?? '') ?>" required minlength="3">
            </div>
            <div class="form-grup">
                <label class="form-label">E-posta Adresi</label>
                <input type="email" name="email" class="form-input" placeholder="ornek@email.com" value="<?= temizle($_POST['email'] ?? '') ?>" required>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md);">
                <div class="form-grup">
                    <label class="form-label">Ad</label>
                    <input type="text" name="ad" class="form-input" placeholder="Adınız" value="<?= temizle($_POST['ad'] ?? '') ?>">
                </div>
                <div class="form-grup">
                    <label class="form-label">Soyad</label>
                    <input type="text" name="soyad" class="form-input" placeholder="Soyadınız" value="<?= temizle($_POST['soyad'] ?? '') ?>">
                </div>
            </div>
            <div class="form-grup">
                <label class="form-label">Şifre</label>
                <div class="sifre-kapsayici">
                    <input type="password" name="sifre" class="form-input" placeholder="En az 6 karakter" required minlength="6">
                    <button type="button" class="sifre-goster-btn" title="Şifreyi Göster/Gizle">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="form-grup">
                <label class="form-label">Şifre Tekrar</label>
                <div class="sifre-kapsayici">
                    <input type="password" name="sifre_tekrar" class="form-input" placeholder="Şifrenizi tekrar giriniz" required>
                    <button type="button" class="sifre-goster-btn" title="Şifreyi Göster/Gizle">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">
                <i class="fas fa-user-plus"></i> Kayıt Ol
            </button>
        </form>

        <p class="auth-alt">Zaten hesabınız var mı? <a href="<?= SITE_URL ?>/giris.php">Giriş Yap</a></p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
