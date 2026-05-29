<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/kimlik.php';
require_once __DIR__ . '/includes/fonksiyonlar.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . SITE_URL . '/takimlar.php'); exit; }

$takim = $db->fetch("SELECT t.*, g.name as oyun_adi, u.username as kaptan_adi
    FROM takimlar t LEFT JOIN oyunlar g ON t.game_id = g.id LEFT JOIN kullanicilar u ON t.captain_id = u.id
    WHERE t.id = ?", [$id]);
if (!$takim) { header('Location: ' . SITE_URL . '/takimlar.php'); exit; }

$sayfaBasligi = $takim['name'];
$uyeler = $db->fetchAll("SELECT tm.*, u.username, u.first_name, u.last_name, u.avatar, u.points, u.country
    FROM takim_uyeleri tm LEFT JOIN kullanicilar u ON tm.user_id = u.id WHERE tm.team_id = ? ORDER BY FIELD(tm.role,'captain','player','substitute','coach')", [$id]);
$turnuvalar = $db->fetchAll("SELECT tr.*, t.name as turnuva_adi, t.status as turnuva_durumu, t.prize_pool
    FROM turnuva_kayitlari tr LEFT JOIN turnuvalar t ON tr.tournament_id = t.id
    WHERE tr.team_id = ? ORDER BY tr.registered_at DESC", [$id]);
$tab = $_GET['tab'] ?? 'kadro';

$kullanici = mevcutKullanici();
$isMember = false;
$pendingInvite = null;
$memberCount = count($uyeler);

if ($kullanici) {
    $isMember = $db->fetch("SELECT id, role FROM takim_uyeleri WHERE team_id = ? AND user_id = ?", [$id, $kullanici['id']]);
    $pendingInvite = $db->fetch("SELECT id, type, status FROM takim_davetleri WHERE team_id = ? AND user_id = ? AND status = 'pending'", [$id, $kullanici['id']]);
}

// POST İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula($_POST['csrf_token'] ?? '')) {
    girisGerekli();
    $aksiyon = $_POST['aksiyon'] ?? '';

    if ($aksiyon === 'takim_katilma_talebi') {
        if ($isMember) {
            flashMesaj('Zaten bu takımın üyesisiniz.', 'error');
        } elseif ($pendingInvite) {
            flashMesaj('Zaten bekleyen bir davetiniz veya başvurunuz var.', 'error');
        } elseif ($memberCount >= 10) {
            flashMesaj('Takım kadrosu doludur (Maksimum 10 kişi).', 'error');
        } else {
            $mesaj = trim($_POST['basvuru_mesaji'] ?? '');
            
            $db->insert("INSERT INTO takim_davetleri (team_id, user_id, sender_id, type, message) VALUES (?,?,?,?,?)", [
                $id, $kullanici['id'], $kullanici['id'], 'request', $mesaj
            ]);

            // Kaptana Bildirim
            $title = "Yeni Takım Başvurusu";
            $msg = temizle($kullanici['username']) . " takımınıza katılmak istiyor!";
            $link = "panel.php?tab=takim_yonetim&id=" . $id;
            $db->query("INSERT INTO bildirimler (user_id, title, message, type, link) VALUES (?, ?, ?, 'team', ?)", [
                $takim['captain_id'], $title, $msg, $link
            ]);

            // Kaptana E-posta
            $captain = $db->fetch("SELECT * FROM kullanicilar WHERE id = ?", [$takim['captain_id']]);
            if ($captain && ($captain['eposta_takim'] ?? 1) && ($captain['eposta_bildirim'] ?? 1)) {
                $email_subject = "Yeni Takım Katılma Başvurusu: " . $takim['name'];
                $email_content = "<h2 style='color:#ffffff; margin:0 0 15px;'>Takım Başvurusu! 👥</h2>
                                  <p style='color:rgba(255,255,255,0.7); line-height:1.6;'>
                                    <strong>" . temizle($kullanici['username']) . "</strong>, <strong>" . temizle($takim['name']) . "</strong> takımınıza katılmak istiyor.
                                  </p>";
                if (!empty($mesaj)) {
                    $email_content .= "<div style='background:rgba(255,255,255,0.03); border-radius:8px; padding:15px; margin:15px 0; color:rgba(255,255,255,0.6); font-style:italic;'>
                                          \"" . temizle($mesaj) . "\"
                                       </div>";
                }
                $email_content .= "<div style='text-align:center; margin-top:20px;'>
                                      <a href='" . SITE_URL . "/panel.php?tab=takim_yonetim&id=" . $id . "' style='display:inline-block; background:#00b894; color:#fff; text-decoration:none; padding:12px 24px; border-radius:6px; font-weight:700;'>
                                          Başvuruyu İncele
                                      </a>
                                   </div>";
                epostaGonder($captain['email'], htmlspecialchars($captain['first_name'] ?: $captain['username']), $email_subject, $email_content, 'takim');
            }

            flashMesaj('Katılma talebiniz başarıyla takım kaptanına iletildi.', 'success');
        }
        header('Location: ' . SITE_URL . '/takim.php?id=' . $id); exit;
    }
}

require_once __DIR__ . '/includes/ust.php';
?>

<section class="sayfa-basligi">
    <div class="container sayfa-basligi-icerik">
        <div class="breadcrumb" style="margin-bottom:var(--space-sm);">
            <a href="<?= SITE_URL ?>">Ana Sayfa</a> <i class="fas fa-chevron-right"></i>
            <a href="<?= SITE_URL ?>/takimlar.php">Takımlar</a> <i class="fas fa-chevron-right"></i>
            <span><?= temizle($takim['name']) ?></span>
        </div>
    </div>
</section>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <?= flashGoster() ?>
        
        <div class="profil-header fade-in" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:var(--space-lg);">
            <div style="display:flex; align-items:center; gap:var(--space-lg); flex-wrap:wrap; flex:1;">
                <div class="profil-avatar">
                    <img src="<?= takimLogoURL($takim['logo']) ?>" alt="<?= temizle($takim['name']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: var(--radius-md);">
                </div>
                <div class="profil-bilgi">
                    <h1 class="profil-isim"><?= temizle($takim['name']) ?></h1>
                    <div class="profil-meta">
                        <span><i class="fas fa-gamepad"></i> <?= temizle($takim['oyun_adi']) ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?= temizle($takim['country'] ?? 'Türkiye') ?></span>
                        <span><i class="fas fa-crown"></i> <?= temizle($takim['kaptan_adi']) ?></span>
                        <span><i class="fas fa-users"></i> <?= count($uyeler) ?> Üye</span>
                    </div>
                    <div class="profil-istatistik">
                        <div class="profil-stat"><span class="profil-stat-sayi"><?= $takim['total_wins'] + $takim['total_losses'] ?></span><span class="profil-stat-etiket">Maç</span></div>
                        <div class="profil-stat"><span class="profil-stat-sayi"><?= $takim['total_wins'] ?></span><span class="profil-stat-etiket">Galibiyet</span></div>
                        <div class="profil-stat"><span class="profil-stat-sayi"><?= $takim['total_losses'] ?></span><span class="profil-stat-etiket">Mağlubiyet</span></div>
                        <div class="profil-stat"><span class="profil-stat-sayi"><?= kazanmaOrani($takim['total_wins'],$takim['total_losses']) ?></span><span class="profil-stat-etiket">Kazanma %</span></div>
                        <div class="profil-stat"><span class="profil-stat-sayi"><?= $takim['points'] ?></span><span class="profil-stat-etiket">Puan</span></div>
                    </div>
                </div>
            </div>
            
            <!-- Katılma / Ayrılma Buton Grubu -->
            <div style="flex-shrink:0;">
                <?php if (!$kullanici): ?>
                    <a href="<?= SITE_URL ?>/giris.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Başvuru için Giriş Yapın</a>
                <?php else: ?>
                    <?php if ($isMember): ?>
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:var(--space-xs);">
                            <span class="badge badge-success" style="padding:6px 12px; font-weight:600;"><i class="fas fa-check-circle"></i> Takım Üyesisiniz</span>
                            <?php if ($isMember['role'] !== 'captain'): ?>
                                <form method="POST" action="<?= SITE_URL ?>/panel.php" style="margin:0;" onsubmit="return confirm('Takımdan ayrılmak istediğinize emin misiniz?')">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="aksiyon" value="takimdan_ayril">
                                    <input type="hidden" name="team_id" value="<?= $id ?>">
                                    <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--danger); border:1px solid var(--danger); font-size:0.75rem;"><i class="fas fa-sign-out-alt"></i> Takımdan Ayrıl</button>
                                </form>
                            <?php else: ?>
                                <a href="<?= SITE_URL ?>/panel.php?tab=takim_yonetim&id=<?= $id ?>" class="btn btn-sm btn-neon"><i class="fas fa-cog"></i> Takımı Yönet</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php if ($pendingInvite): ?>
                            <?php if ($pendingInvite['type'] === 'invitation'): ?>
                                <div style="display:flex; flex-direction:column; align-items:center; gap:8px; background:var(--bg-surface); padding:10px; border-radius:var(--radius-md); border:1px solid var(--border-light);">
                                    <span style="font-size:0.75rem; color:var(--text-secondary); font-weight:600;"><i class="fas fa-envelope-open-text" style="color:var(--accent);"></i> Davet Edildiniz!</span>
                                    <div style="display:flex; gap:4px;">
                                        <form method="POST" action="<?= SITE_URL ?>/panel.php" style="margin:0;">
                                            <?= csrfInput() ?>
                                            <input type="hidden" name="aksiyon" value="takim_davet_cevap">
                                            <input type="hidden" name="davet_id" value="<?= $pendingInvite['id'] ?>">
                                            <input type="hidden" name="cevap" value="accept">
                                            <button type="submit" class="btn btn-sm" style="background:var(--success); color:white; border:none; padding:4px 8px; font-size:0.75rem; font-weight:600;"><i class="fas fa-check"></i> Kabul Et</button>
                                        </form>
                                        <form method="POST" action="<?= SITE_URL ?>/panel.php" style="margin:0;">
                                            <?= csrfInput() ?>
                                            <input type="hidden" name="aksiyon" value="takim_davet_cevap">
                                            <input type="hidden" name="davet_id" value="<?= $pendingInvite['id'] ?>">
                                            <input type="hidden" name="cevap" value="reject">
                                            <button type="submit" class="btn btn-sm" style="background:var(--danger); color:white; border:none; padding:4px 8px; font-size:0.75rem; font-weight:600;"><i class="fas fa-times"></i> Reddet</button>
                                        </form>
                                    </div>
                                </div>
                            <?php elseif ($pendingInvite['type'] === 'request'): ?>
                                <button type="button" class="btn btn-ghost" style="pointer-events:none; border-color:var(--border);" disabled><i class="fas fa-spinner fa-spin"></i> Katılma Talebi Beklemede</button>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($memberCount >= 10): ?>
                                <button type="button" class="btn btn-ghost" style="pointer-events:none;" disabled><i class="fas fa-ban"></i> Kadro Dolu</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-neon" onclick="openJoinRequestModal()"><i class="fas fa-paper-plane"></i> Katılma Talebi Gönder</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-menu">
            <a href="?id=<?= $id ?>&tab=kadro" class="tab-link <?= $tab==='kadro'?'aktif':'' ?>"><i class="fas fa-users"></i> Kadro</a>
            <a href="?id=<?= $id ?>&tab=turnuva" class="tab-link <?= $tab==='turnuva'?'aktif':'' ?>"><i class="fas fa-trophy"></i> Turnuvalar</a>
        </div>

        <?php if ($tab === 'kadro'): ?>
        <div class="grid grid-4">
            <?php foreach ($uyeler as $uye): ?>
            <div class="oyuncu-kart fade-in">
                <a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $uye['user_id'] ?>" class="oyuncu-kart-avatar">
                    <img src="<?= avatarURL($uye['avatar'] ?? '') ?>" alt="<?= temizle($uye['username']) ?>">
                </a>
                <h4 class="oyuncu-kart-isim"><a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $uye['user_id'] ?>"><?= temizle($uye['username']) ?></a></h4>
                <p class="oyuncu-kart-gercek-isim"><?= temizle(($uye['first_name'] ?? '') . ' ' . ($uye['last_name'] ?? '')) ?></p>
                <div class="oyuncu-kart-rol"><i class="fas fa-<?= $uye['role']==='captain'?'crown':'user' ?>"></i> <?= rolCevir($uye['role'], 'team') ?></div>
                <span class="oyuncu-kart-puan"><?= $uye['points'] ?></span>
                <span class="oyuncu-kart-puan-etiket">Puan</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <?php if (empty($turnuvalar)): ?>
            <div class="bos-durum"><i class="fas fa-trophy"></i><h3>Henüz turnuvaya katılım yok</h3></div>
        <?php else: ?>
            <div class="tablo-wrapper">
                <table class="tablo">
                    <thead><tr><th>Turnuva</th><th>Durum</th><th>Kayıt Durumu</th><th>Ödül</th><th>Tarih</th></tr></thead>
                    <tbody>
                        <?php foreach ($turnuvalar as $t): ?>
                        <tr>
                            <td class="wrap-text"><a href="<?= SITE_URL ?>/turnuva.php?id=<?= $t['tournament_id'] ?>" style="color:var(--accent);font-weight:600;"><?= temizle($t['turnuva_adi']) ?></a></td>
                            <td><?= durumBadge($t['turnuva_durumu']) ?></td>
                            <td><?= durumBadge($t['status']) ?></td>
                            <td style="color:var(--gold);font-weight:600;"><?= paraFormatla($t['prize_pool']) ?></td>
                            <td style="color:var(--text-secondary);"><?= tarihFormatla($t['registered_at'], 'short') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Katılma Talebi Modali -->
<div id="joinRequestModal" class="modal-wrapper">
    <div class="modal-icerik">
        <div class="modal-baslik">
            <h3><i class="fas fa-paper-plane" style="color:var(--accent)"></i> Katılma Talebi Gönder</h3>
            <button type="button" class="modal-kapat" onclick="closeJoinRequestModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="modal-form">
            <?= csrfInput() ?>
            <input type="hidden" name="aksiyon" value="takim_katilma_talebi">
            
            <div class="form-grup" style="margin-bottom:var(--space-lg);">
                <label class="form-label" for="basvuru_mesaji" style="display:block; margin-bottom:var(--space-xs); font-weight:600;">Takım Kaptanına Not (Opsiyonel)</label>
                <textarea name="basvuru_mesaji" id="basvuru_mesaji" class="form-input" rows="3" style="width:100%; padding:var(--space-sm); border:1px solid var(--border); border-radius:var(--radius-md); background:var(--bg-surface); color:var(--text-primary);" placeholder="Kendinizden bahsedin, neden bu takıma katılmak istiyorsunuz?"></textarea>
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:var(--space-md); margin-top:var(--space-lg);">
                <button type="button" class="btn btn-ghost" onclick="closeJoinRequestModal()">İptal</button>
                <button type="submit" class="btn btn-primary" style="padding:10px 20px; border:none; border-radius:var(--radius-md); font-weight:600;">Gönder</button>
            </div>
        </form>
    </div>
</div>

<script>
function openJoinRequestModal() {
    document.getElementById('joinRequestModal').classList.add('aktif');
}

function closeJoinRequestModal() {
    document.getElementById('joinRequestModal').classList.remove('aktif');
    document.getElementById('basvuru_mesaji').value = '';
}

// Dışarı tıklanınca kapatma
window.onclick = function(event) {
    var modal = document.getElementById('joinRequestModal');
    if (event.target == modal) {
        closeJoinRequestModal();
    }
}
</script>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
