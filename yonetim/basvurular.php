<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';
require_once dirname(__DIR__) . '/includes/eposta.php';

adminGerekli();

$tab = $_GET['tab'] ?? 'pending';
if (!in_array($tab, ['pending', 'approved', 'rejected', 'all'])) {
    $tab = 'pending';
}

// POST İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula($_POST['csrf_token'] ?? '')) {
    $aksiyon = $_POST['aksiyon'] ?? '';
    $kayit_id = (int)($_POST['kayit_id'] ?? 0);

    if ($kayit_id > 0) {
        // Kayıt ve ilgili turnuva, takım, kaptan bilgilerini getir
        $kayit = $db->fetch("
            SELECT tk.*, t.name as takim_adi, t.captain_id, tu.name as turnuva_adi, tu.max_teams, tu.id as turnuva_id, tu.start_date, tu.prize_pool
            FROM turnuva_kayitlari tk
            JOIN takimlar t ON tk.team_id = t.id
            JOIN turnuvalar tu ON tk.tournament_id = tu.id
            WHERE tk.id = ?
        ", [$kayit_id]);

        if (!$kayit) {
            flashMesaj('Başvuru kaydı bulunamadı.', 'error');
            header('Location: ' . SITE_URL . '/yonetim/basvurular.php?tab=' . $tab);
            exit;
        }

        if ($kayit['status'] !== 'pending') {
            flashMesaj('Bu başvuru zaten işlenmiş.', 'error');
            header('Location: ' . SITE_URL . '/yonetim/basvurular.php?tab=' . $tab);
            exit;
        }

        $currentUser = mevcutKullanici();

        if ($aksiyon === 'basvuru_onayla') {
            // Kontenjan kontrolü
            $approvedCount = $db->count("SELECT COUNT(*) FROM turnuva_kayitlari WHERE tournament_id = ? AND status = 'approved'", [$kayit['turnuva_id']]);
            
            if ($approvedCount >= (int)$kayit['max_teams']) {
                flashMesaj('Bu turnuva için maksimum takım sayısına (' . $kayit['max_teams'] . ') ulaşılmıştır. Daha fazla onay yapılamaz.', 'error');
            } else {
                // Kaydı Güncelle
                $db->query("
                    UPDATE turnuva_kayitlari 
                    SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? 
                    WHERE id = ?
                ", [$currentUser['id'], $kayit_id]);

                // Kaptanı getir
                $captain = $db->fetch("SELECT * FROM kullanicilar WHERE id = ?", [$kayit['captain_id']]);
                if ($captain) {
                    // Sistem Bildirimi Ekle
                    $title = "Turnuva Başvurusu Onaylandı";
                    $message = temizle($kayit['takim_adi']) . " takımınızın " . temizle($kayit['turnuva_adi']) . " turnuvasına katılım başvurusu onaylanmıştır!";
                    $link = "turnuva.php?id=" . $kayit['turnuva_id'];
                    
                    $db->query("
                        INSERT INTO bildirimler (user_id, title, message, type, link) 
                        VALUES (?, ?, ?, 'tournament', ?)
                    ", [$kayit['captain_id'], $title, $message, $link]);

                    // E-posta gönder (ayarı açıksa)
                    if (($captain['eposta_turnuva'] ?? 1) && ($captain['eposta_bildirim'] ?? 1)) {
                        $turnuva_obj = [
                            'id' => $kayit['turnuva_id'],
                            'name' => $kayit['turnuva_adi'],
                            'start_date' => $kayit['start_date'],
                            'prize_pool' => $kayit['prize_pool']
                        ];
                        $takim_obj = [
                            'name' => $kayit['takim_adi']
                        ];
                        epostaTurnuvaOnay($captain, $turnuva_obj, $takim_obj);
                    }
                }

                flashMesaj('Başvuru başarıyla onaylandı ve takım kaptanına bildirim gönderildi.', 'success');
            }
        } 
        
        elseif ($aksiyon === 'basvuru_reddet') {
            $red_nedeni = trim($_POST['red_nedeni'] ?? '');

            // Kaydı Güncelle
            $db->query("
                UPDATE turnuva_kayitlari 
                SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ? 
                WHERE id = ?
            ", [$currentUser['id'], $kayit_id]);

            // Kaptanı getir
            $captain = $db->fetch("SELECT * FROM kullanicilar WHERE id = ?", [$kayit['captain_id']]);
            if ($captain) {
                // Sistem Bildirimi Ekle
                $title = "Turnuva Başvurusu Reddedildi";
                $message = temizle($kayit['takim_adi']) . " takımınızın " . temizle($kayit['turnuva_adi']) . " turnuvasına katılım başvurusu reddedilmiştir.";
                if (!empty($red_nedeni)) {
                    $message .= " Gerekçe: " . temizle($red_nedeni);
                }
                $link = "turnuva.php?id=" . $kayit['turnuva_id'];

                $db->query("
                    INSERT INTO bildirimler (user_id, title, message, type, link) 
                    VALUES (?, ?, ?, 'tournament', ?)
                ", [$kayit['captain_id'], $title, $message, $link]);

                // E-posta gönder (ayarı açıksa)
                if (($captain['eposta_turnuva'] ?? 1) && ($captain['eposta_bildirim'] ?? 1)) {
                    $turnuva_obj = [
                        'id' => $kayit['turnuva_id'],
                        'name' => $kayit['turnuva_adi']
                    ];
                    $takim_obj = [
                        'name' => $kayit['takim_adi']
                    ];
                    epostaTurnuvaRed($captain, $turnuva_obj, $takim_obj, $red_nedeni);
                }
            }

            flashMesaj('Başvuru reddedildi ve takım kaptanına bildirim gönderildi.', 'success');
        }
    } else {
        flashMesaj('Geçersiz başvuru ID.', 'error');
    }

    header('Location: ' . SITE_URL . '/yonetim/basvurular.php?tab=' . $tab);
    exit;
}

// Sayaçlar
$pendingCount = $db->count("SELECT COUNT(*) FROM turnuva_kayitlari WHERE status = 'pending'");
$approvedCount = $db->count("SELECT COUNT(*) FROM turnuva_kayitlari WHERE status = 'approved'");
$rejectedCount = $db->count("SELECT COUNT(*) FROM turnuva_kayitlari WHERE status = 'rejected'");

// Başvuruları Sorgula
$sql = "
    SELECT tk.*, t.name as takim_adi, t.logo as takim_logo, tu.name as turnuva_adi, tu.start_date, 
           u.username as captain_username, u.id as captain_id, 
           ru.username as reviewer_username
    FROM turnuva_kayitlari tk
    JOIN takimlar t ON tk.team_id = t.id
    JOIN kullanicilar u ON t.captain_id = u.id
    JOIN turnuvalar tu ON tk.tournament_id = tu.id
    LEFT JOIN kullanicilar ru ON tk.reviewed_by = ru.id
";

if ($tab !== 'all') {
    $sql .= " WHERE tk.status = ?";
    $basvurular = $db->fetchAll($sql . " ORDER BY tk.registered_at DESC", [$tab]);
} else {
    $basvurular = $db->fetchAll($sql . " ORDER BY tk.registered_at DESC");
}

$sayfaBasligi = 'Turnuva Başvuruları';
require_once dirname(__DIR__) . '/includes/ust.php';
?>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <div class="dashboard-grid">
            <!-- Sidebar -->
            <?php 
            $aktifSekme = 'basvurular';
            require_once __DIR__ . '/sidebar.php'; 
            ?>

            <!-- İçerik -->
            <div class="dashboard-icerik">
                <?= flashGoster() ?>
                
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xl);padding-bottom:var(--space-lg);border-bottom:1px solid var(--border-light)">
                    <h2 style="font-size:1.5rem;">
                        <i class="fas fa-file-signature" style="color:var(--accent)"></i> Turnuva Başvuruları Yönetimi
                    </h2>
                </div>

                <!-- Tab Filtreleri -->
                <div style="display:flex;gap:var(--space-sm);margin-bottom:var(--space-lg);flex-wrap:wrap;border-bottom:1px solid var(--border-light);padding-bottom:var(--space-sm);">
                    <a href="?tab=pending" class="btn <?= $tab === 'pending' ? 'btn-primary' : 'btn-ghost' ?> btn-sm" style="display:flex; align-items:center; gap:6px;">
                        Bekleyenler 
                        <span class="badge" style="background:var(--accent); color:var(--text-white); font-size:0.7rem; padding:2px 6px; border-radius:10px;"><?= $pendingCount ?></span>
                    </a>
                    <a href="?tab=approved" class="btn <?= $tab === 'approved' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">
                        Onaylananlar (<?= $approvedCount ?>)
                    </a>
                    <a href="?tab=rejected" class="btn <?= $tab === 'rejected' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">
                        Reddedilenler (<?= $rejectedCount ?>)
                    </a>
                    <a href="?tab=all" class="btn <?= $tab === 'all' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">
                        Tümü
                    </a>
                </div>

                <?php if (empty($basvurular)): ?>
                    <div class="bos-durum" style="padding:var(--space-3xl) 0; text-align:center;">
                        <i class="far fa-file-alt" style="font-size:3rem; color:var(--text-muted); margin-bottom:var(--space-md); display:block;"></i>
                        <h3>Başvuru bulunmuyor</h3>
                        <p style="color:var(--text-muted);margin-top:var(--space-xs);">Bu filtreye uygun herhangi bir turnuva başvurusu bulunamadı.</p>
                    </div>
                <?php else: ?>
                    <div class="tablo-wrapper">
                        <table class="tablo">
                            <thead>
                                <tr>
                                    <th style="padding: 10px 6px; width: 40px;">ID</th>
                                    <th style="padding: 10px 6px; min-width: 130px;">Takım</th>
                                    <th style="padding: 10px 6px; min-width: 150px;">Turnuva</th>
                                    <th style="padding: 10px 6px;">Kaptan</th>
                                    <th style="padding: 10px 6px;">Kayıt Notları</th>
                                    <th style="padding: 10px 6px;">Kayıt Tarihi</th>
                                    <th style="padding: 10px 6px;">Durum</th>
                                    <?php if ($tab !== 'pending'): ?>
                                        <th style="padding: 10px 6px;">İnceleyen</th>
                                    <?php endif; ?>
                                    <th style="padding: 10px 6px; text-align:center; min-width:150px;">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($basvurular as $b): ?>
                                <tr>
                                    <td style="padding: 8px 6px;">#<?= $b['id'] ?></td>
                                    <td style="padding: 8px 6px;">
                                        <div style="display:flex; align-items:center; gap:6px; min-width: 130px; white-space: normal;">
                                            <img src="<?= takimLogoURL($b['takim_logo']) ?>" style="width:24px; height:24px; border-radius:var(--radius-sm); object-fit:cover; border:1px solid var(--border-light); flex-shrink:0;">
                                            <a href="<?= SITE_URL ?>/takim.php?id=<?= $b['team_id'] ?>" target="_blank" style="font-weight:600; color:inherit; word-break: break-word; font-size:0.8rem;">
                                                <?= temizle($b['takim_adi']) ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td style="padding: 8px 6px;">
                                        <div style="min-width: 150px; white-space: normal;">
                                            <a href="<?= SITE_URL ?>/turnuva.php?id=<?= $b['tournament_id'] ?>" target="_blank" style="color:var(--accent); font-weight:500; word-break: break-word; font-size:0.8rem;">
                                                <?= temizle($b['turnuva_adi']) ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td style="padding: 8px 6px; white-space: nowrap;">
                                        <a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $b['captain_id'] ?>" target="_blank" style="color:var(--text-secondary); font-size:0.8rem;">
                                            <?= temizle($b['captain_username']) ?>
                                        </a>
                                    </td>
                                    <td style="padding: 8px 6px;">
                                        <?php if (!empty($b['notes'])): ?>
                                            <span style="font-size:0.8rem; color:var(--text-primary); cursor:help; text-decoration:underline dashed var(--border-hover);" title="<?= temizle($b['notes']) ?>">
                                                <?= strlen($b['notes']) > 20 ? temizle(substr($b['notes'], 0, 20)) . '...' : temizle($b['notes']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-size:0.8rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 8px 6px; font-size:0.78rem; color:var(--text-muted); white-space: nowrap;">
                                        <?= tarihFormatla($b['registered_at'], 'short') ?>
                                    </td>
                                    <td style="padding: 8px 6px;">
                                        <?= durumBadge($b['status']) ?>
                                    </td>
                                    <?php if ($tab !== 'pending'): ?>
                                        <td style="padding: 8px 6px; font-size:0.78rem; color:var(--text-muted);">
                                            <?php if ($b['reviewer_username']): ?>
                                                <span style="color:var(--text-primary); font-weight:500; font-size:0.8rem;"><?= temizle($b['reviewer_username']) ?></span>
                                                <br>
                                                <?= tarihFormatla($b['reviewed_at'], 'relative') ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td style="padding: 8px 6px; text-align:center;">
                                        <?php if ($b['status'] === 'pending'): ?>
                                            <div style="display:flex; gap:4px; justify-content:center; align-items:center; min-width:150px;">
                                                <form method="POST" style="margin:0; flex-shrink:0;">
                                                    <?= csrfInput() ?>
                                                    <input type="hidden" name="aksiyon" value="basvuru_onayla">
                                                    <input type="hidden" name="kayit_id" value="<?= $b['id'] ?>">
                                                    <button type="submit" class="btn btn-sm" style="background:var(--success); color:white; border:none; padding:5px 8px; font-size:0.72rem; font-weight:600; display:flex; align-items:center; gap:3px; white-space:nowrap; flex-shrink:0;" onclick="return confirm('Bu başvuruyu onaylamak istediğinizden emin misiniz?')">
                                                        <i class="fas fa-check" style="font-size: 0.7rem;"></i> Onayla
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-sm" style="background:var(--danger); color:white; border:none; padding:5px 8px; font-size:0.72rem; font-weight:600; display:flex; align-items:center; gap:3px; white-space:nowrap; flex-shrink:0;" onclick="openRejectionModal(<?= $b['id'] ?>)">
                                                    <i class="fas fa-times" style="font-size: 0.7rem;"></i> Reddet
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-size:0.75rem; white-space:nowrap;">İşlem Tamamlandı</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Reddetme Modali -->
<div id="rejectionModal" class="modal-wrapper">
    <div class="modal-icerik">
        <div class="modal-baslik">
            <h3><i class="fas fa-times-circle" style="color:var(--danger)"></i> Başvuruyu Reddet</h3>
            <button type="button" class="modal-kapat" onclick="closeRejectionModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="modal-form">
            <?= csrfInput() ?>
            <input type="hidden" name="aksiyon" value="basvuru_reddet">
            <input type="hidden" name="kayit_id" id="reject_kayit_id" value="">
            
            <div class="form-grup" style="margin-bottom:var(--space-lg);">
                <label class="form-label" for="red_nedeni" style="display:block; margin-bottom:var(--space-xs); font-weight:600;">Red Gerekçesi (İsteğe Bağlı)</label>
                <textarea name="red_nedeni" id="red_nedeni" class="form-input" rows="4" style="width:100%; padding:var(--space-sm); border:1px solid var(--border); border-radius:var(--radius-md); background:var(--bg-surface); color:var(--text-primary);" placeholder="Kayıt neden reddedildi? Kaptana sistem bildirimi ve e-posta olarak gönderilecektir..."></textarea>
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:var(--space-md); margin-top:var(--space-lg);">
                <button type="button" class="btn btn-ghost" onclick="closeRejectionModal()">Vazgeç</button>
                <button type="submit" class="btn" style="background:var(--danger); color:white; border:none; padding:10px 20px; border-radius:var(--radius-md); font-weight:600;">Reddet</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectionModal(kayitId) {
    document.getElementById('reject_kayit_id').value = kayitId;
    document.getElementById('rejectionModal').classList.add('aktif');
}

function closeRejectionModal() {
    document.getElementById('rejectionModal').classList.remove('aktif');
    document.getElementById('red_nedeni').value = '';
}

// Dışarı tıklanınca kapatma
window.onclick = function(event) {
    var modal = document.getElementById('rejectionModal');
    if (event.target == modal) {
        closeRejectionModal();
    }
}
</script>

<?php require_once dirname(__DIR__) . '/includes/alt.php'; ?>
