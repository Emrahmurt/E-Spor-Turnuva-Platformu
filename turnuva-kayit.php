<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/kimlik.php';
require_once __DIR__ . '/includes/fonksiyonlar.php';
require_once __DIR__ . '/includes/eposta.php';
girisGerekli();

$turnuvaId = (int)($_GET['turnuva_id'] ?? 0);
if (!$turnuvaId) { header('Location: ' . SITE_URL . '/turnuvalar.php'); exit; }

$turnuva = $db->fetch("SELECT t.*, g.name as oyun_adi FROM turnuvalar t LEFT JOIN oyunlar g ON t.game_id = g.id WHERE t.id = ?", [$turnuvaId]);
if (!$turnuva || $turnuva['status'] !== 'registration') { flashMesaj('Bu turnuvaya kayıt yapılamaz.', 'error'); header('Location: ' . SITE_URL . '/turnuva.php?id=' . $turnuvaId); exit; }

$kullanici = mevcutKullanici();
$takimlarim = $db->fetchAll("SELECT t.* FROM takim_uyeleri tm LEFT JOIN takimlar t ON tm.team_id = t.id WHERE tm.user_id = ? AND tm.role = 'captain' AND t.game_id = ?", [$kullanici['id'], $turnuva['game_id']]);

$sayfaBasligi = 'Turnuva Kaydı';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula($_POST['csrf_token'] ?? '')) {
    $takimId = (int)($_POST['takim_id'] ?? 0);
    $notlar = trim($_POST['notlar'] ?? '');
    
    if (!$takimId) { flashMesaj('Lütfen bir takım seçiniz.', 'error'); }
    else {
        $zatenKayitli = $db->fetch("SELECT id FROM turnuva_kayitlari WHERE tournament_id = ? AND team_id = ?", [$turnuvaId, $takimId]);
        if ($zatenKayitli) { flashMesaj('Bu takım zaten kayıtlı.', 'error'); }
        else {
            $kayitliSayisi = $db->count("SELECT COUNT(*) FROM turnuva_kayitlari WHERE tournament_id = ? AND status IN ('pending','approved')", [$turnuvaId]);
            if ($kayitliSayisi >= $turnuva['max_teams']) { flashMesaj('Turnuva kontenjanı dolmuştur.', 'error'); }
            else {
                $db->insert("INSERT INTO turnuva_kayitlari (tournament_id, team_id, notes) VALUES (?,?,?)", [$turnuvaId, $takimId, $notlar]);
                
                // Turnuva kayıt e-postası gönder
                $kayitTakim = $db->fetch("SELECT * FROM takimlar WHERE id = ?", [$takimId]);
                if ($kullanici && $turnuva && $kayitTakim && ($kullanici['eposta_turnuva'] ?? 1)) {
                    epostaTurnuvaKayit($kullanici, $turnuva, $kayitTakim);
                }
                
                flashMesaj('Turnuva kaydınız alınmıştır! Onay bekleniyor.', 'success');
                header('Location: ' . SITE_URL . '/turnuva.php?id=' . $turnuvaId); exit;
            }
        }
    }
}

require_once __DIR__ . '/includes/ust.php';
?>

<section class="sayfa-basligi">
    <div class="container sayfa-basligi-icerik">
        <h1><i class="fas fa-clipboard-check" style="color:var(--accent)"></i> Turnuva Kaydı</h1>
        <div class="breadcrumb">
            <a href="<?= SITE_URL ?>">Ana Sayfa</a> <i class="fas fa-chevron-right"></i>
            <a href="<?= SITE_URL ?>/turnuva.php?id=<?= $turnuvaId ?>"><?= temizle($turnuva['name']) ?></a> <i class="fas fa-chevron-right"></i>
            <span>Kayıt</span>
        </div>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:700px;">
        <?= flashGoster() ?>
        
        <div class="kart" style="padding:var(--space-xl);margin-bottom:var(--space-xl);">
            <h3 style="margin-bottom:var(--space-md);"><?= temizle($turnuva['name']) ?></h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-sm);color:var(--text-secondary);font-size:0.85rem;">
                <span><i class="fas fa-gamepad" style="color:var(--accent)"></i> <?= temizle($turnuva['oyun_adi']) ?></span>
                <span><i class="fas fa-coins" style="color:var(--gold)"></i> <?= paraFormatla($turnuva['prize_pool']) ?></span>
                <span><i class="fas fa-sitemap" style="color:var(--accent)"></i> <?= formatBadge($turnuva['format']) ?></span>
                <span><i class="fas fa-calendar" style="color:var(--accent)"></i> <?= tarihFormatla($turnuva['start_date'],'short') ?></span>
            </div>
        </div>

        <?php if (empty($takimlarim)): ?>
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Bu oyunda kaptan olduğunuz bir takımınız bulunmuyor. Önce <a href="<?= SITE_URL ?>/panel.php?tab=takimlar" style="color:var(--accent);font-weight:600;">bir takım oluşturun</a>.</div>
        <?php else: ?>
            <div class="kart" style="padding:var(--space-xl);">
                <h3 style="margin-bottom:var(--space-lg);"><i class="fas fa-edit" style="color:var(--accent)"></i> Kayıt Formu</h3>
                <form method="POST">
                    <?= csrfInput() ?>
                    <div class="form-grup">
                        <label class="form-label">Takım Seçin</label>
                        <select name="takim_id" class="form-input" required>
                            <option value="">-- Takım Seçiniz --</option>
                            <?php foreach ($takimlarim as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= temizle($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grup">
                        <label class="form-label">Notlar (Opsiyonel)</label>
                        <textarea name="notlar" class="form-input" rows="3" placeholder="Ek bilgi veya notlarınız..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-neon btn-block btn-lg"><i class="fas fa-check-circle"></i> Kayıt Ol</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
