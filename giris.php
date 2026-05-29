<?php
$sayfaBasligi = 'Giriş Yap';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/kimlik.php';
require_once __DIR__ . '/includes/fonksiyonlar.php';

if (girisYapmisMi()) { header('Location: ' . SITE_URL . '/panel.php'); exit; }

$hata = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfDogrula($_POST['csrf_token'] ?? '')) {
        $hata = 'Geçersiz istek. Lütfen tekrar deneyin.';
    } else {
        $girisGirdisi = trim($_POST['giris_girdisi'] ?? '');
        $sifre = $_POST['sifre'] ?? '';
        if (empty($girisGirdisi) || empty($sifre)) {
            $hata = 'Kullanıcı adı/E-posta ve şifre alanları zorunludur.';
        } elseif (girisYap($girisGirdisi, $sifre)) {
            $yonlendir = $_GET['donecek'] ?? SITE_URL . '/panel.php';
            header('Location: ' . $yonlendir);
            exit;
        } else {
            $hata = 'Kullanıcı adı/E-posta veya şifre hatalı.';
        }
    }
}

require_once __DIR__ . '/includes/ust.php';
?>

<div class="auth-container">
    <div class="auth-kutu fade-in">
        <div class="auth-logo">
            <i class="fas fa-gamepad"></i>
            <h2>Giriş Yap</h2>
            <p>Hesabınıza giriş yapın</p>
        </div>

        <?php if ($hata): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= temizle($hata) ?></div>
        <?php endif; ?>

        <form method="POST" data-dogrula>
            <?= csrfInput() ?>
            <div class="form-grup">
                <label class="form-label">Kullanıcı Adı veya E-posta</label>
                <input type="text" name="giris_girdisi" class="form-input" placeholder="Kullanıcı adı veya e-posta" value="<?= temizle($_POST['giris_girdisi'] ?? '') ?>" required>
            </div>
            <div class="form-grup">
                <label class="form-label">Şifre</label>
                <div class="sifre-kapsayici">
                    <input type="password" name="sifre" class="form-input" placeholder="••••••••" required>
                    <button type="button" class="sifre-goster-btn" title="Şifreyi Göster/Gizle">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="form-grup" style="display:flex;justify-content:space-between;align-items:center;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;color:var(--text-secondary);cursor:pointer;">
                    <input type="checkbox" name="hatirla" style="accent-color:var(--accent)"> Beni hatırla
                </label>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">
                <i class="fas fa-sign-in-alt"></i> Giriş Yap
            </button>
        </form>

        <p class="auth-alt">Hesabınız yok mu? <a href="<?= SITE_URL ?>/kayit.php">Kayıt Ol</a></p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
