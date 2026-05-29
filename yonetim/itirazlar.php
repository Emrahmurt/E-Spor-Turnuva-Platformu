<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';

adminGerekli();

$tab = $_GET['tab'] ?? 'pending';
if (!in_array($tab, ['pending', 'resolved', 'all'])) {
    $tab = 'pending';
}

// POST İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula($_POST['csrf_token'] ?? '')) {
    $aksiyon = $_POST['aksiyon'] ?? '';
    
    if ($aksiyon === 'itiraz_coz') {
        $match_id = (int)$_POST['match_id'];
        $score1 = (int)$_POST['score1'];
        $score2 = (int)$_POST['score2'];
        $winner_id = (int)$_POST['winner_id'];
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        
        $mac = $db->fetch("SELECT * FROM maclar WHERE id = ?", [$match_id]);
        
        if (!$mac) {
            flashMesaj('Maç bulunamadı.', 'error');
            header('Location: ' . SITE_URL . '/yonetim/itirazlar.php?tab=' . $tab);
            exit;
        }
        
        if ($winner_id != $mac['team1_id'] && $winner_id != $mac['team2_id']) {
            flashMesaj('Geçersiz kazanan takımı seçtiniz.', 'error');
            header('Location: ' . SITE_URL . '/yonetim/itirazlar.php?tab=' . $tab);
            exit;
        }
        
        $db->query("
            UPDATE maclar 
            SET score1 = ?, score2 = ?, status = 'completed', winner_id = ?, completed_at = NOW(), dispute_status = 'resolved', notes = ?
            WHERE id = ?
        ", [$score1, $score2, $winner_id, $admin_notes, $match_id]);
        
        // Kazanan takım adını getir
        $winner_team = $db->fetch("SELECT name FROM takimlar WHERE id = ?", [$winner_id]);
        $winner_name = $winner_team ? $winner_team['name'] : 'Kazanan';
            
        $msg = "Maç itirazı hakem tarafından çözüldü! Resmi Skor: {$score1} - {$score2}. Kazanan: {$winner_name}.";
        if (!empty($admin_notes)) {
            $msg .= " Hakem Notu: " . $admin_notes;
        }
        $db->query("INSERT INTO canli_guncellemeler (match_id, message, event_type) VALUES (?, ?, 'match_end')", [$match_id, $msg]);
        
        // Bracket İlerletme Mantığı
        $next_round = (int)$mac['round'] + 1;
        $next_match_num = (int)ceil($mac['match_number'] / 2);
        $col = ($mac['match_number'] % 2 !== 0) ? 'team1_id' : 'team2_id';
        
        $next_mac = $db->fetch("SELECT id FROM maclar WHERE tournament_id = ? AND round = ? AND match_number = ?", 
            [$mac['tournament_id'], $next_round, $next_match_num]);
        
        if ($next_mac) {
            $db->query("UPDATE maclar SET $col = ? WHERE id = ?", [$winner_id, $next_mac['id']]);
        }
        
        // Kaptanlara bildirim gönder
        $t1_captain = $db->fetch("SELECT user_id FROM takim_uyeleri WHERE team_id = ? AND role = 'captain'", [$mac['team1_id']]);
        $t2_captain = $db->fetch("SELECT user_id FROM takim_uyeleri WHERE team_id = ? AND role = 'captain'", [$mac['team2_id']]);
        
        $title = "Hakem Kararı Açıklandı";
        $notif_msg = "Maçınızdaki uyuşmazlık hakem tarafından incelenmiş ve çözülmüştür. Sonuç: {$score1}-{$score2}, Kazanan: {$winner_name}.";
        
        if ($t1_captain) {
            $db->query("INSERT INTO bildirimler (user_id, title, message, type, link) VALUES (?, ?, ?, 'match', ?)", 
                [$t1_captain['user_id'], $title, $notif_msg, "mac.php?id=" . $match_id]);
        }
        if ($t2_captain) {
            $db->query("INSERT INTO bildirimler (user_id, title, message, type, link) VALUES (?, ?, ?, 'match', ?)", 
                [$t2_captain['user_id'], $title, $notif_msg, "mac.php?id=" . $match_id]);
        }
        
        flashMesaj('Uyuşmazlık başarıyla çözüldü, maç tamamlandı ve kazanan takım üst tura taşındı.', 'success');
        header('Location: ' . SITE_URL . '/yonetim/itirazlar.php?tab=' . $tab);
        exit;
    }
}

// Sayaçlar
$pendingCount = $db->count("SELECT COUNT(*) FROM maclar WHERE dispute_status = 'pending'");
$resolvedCount = $db->count("SELECT COUNT(*) FROM maclar WHERE dispute_status = 'resolved'");

// İtirazlı Maçları Çek
$where = "WHERE m.dispute_status = 'pending'";
if ($tab === 'resolved') {
    $where = "WHERE m.dispute_status = 'resolved'";
} elseif ($tab === 'all') {
    $where = "WHERE m.dispute_status != 'none'";
}

$disputes = $db->fetchAll("
    SELECT m.*, 
           t1.name as takim1_adi, t1.logo as takim1_logo, t1.id as takim1_id,
           t2.name as takim2_adi, t2.logo as takim2_logo, t2.id as takim2_id,
           tr.name as turnuva_adi
    FROM maclar m
    LEFT JOIN takimlar t1 ON m.team1_id = t1.id
    LEFT JOIN takimlar t2 ON m.team2_id = t2.id
    LEFT JOIN turnuvalar tr ON m.tournament_id = tr.id
    $where
    ORDER BY m.id DESC
");

foreach ($disputes as &$d) {
    $d['reports'] = $db->fetchAll("
        SELECT mr.*, u.username as captain_username
        FROM mac_raporlari mr
        JOIN kullanicilar u ON mr.reporter_id = u.id
        WHERE mr.match_id = ?
    ", [$d['id']]);
    
    $d['report1'] = null;
    $d['report2'] = null;
    foreach ($d['reports'] as $rep) {
        if ($rep['team_id'] == $d['team1_id']) {
            $d['report1'] = $rep;
        } elseif ($rep['team_id'] == $d['team2_id']) {
            $d['report2'] = $rep;
        }
    }
}
unset($d);

$sayfaBasligi = 'Maç İtirazları';
require_once dirname(__DIR__) . '/includes/ust.php';
?>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <div class="dashboard-grid">
            <!-- Sidebar -->
            <?php 
            $aktifSekme = 'itirazlar';
            require_once __DIR__ . '/sidebar.php'; 
            ?>

            <!-- İçerik -->
            <div class="dashboard-icerik">
                <?= flashGoster() ?>
                
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xl);padding-bottom:var(--space-lg);border-bottom:1px solid var(--border-light)">
                    <h2 style="font-size:1.5rem;">
                        <i class="fas fa-gavel" style="color:var(--accent)"></i> Maç İtirazları ve Hakem Paneli
                    </h2>
                </div>

                <!-- Tab Filtreleri -->
                <div style="display:flex;gap:var(--space-sm);margin-bottom:var(--space-lg);flex-wrap:wrap;border-bottom:1px solid var(--border-light);padding-bottom:var(--space-sm);">
                    <a href="?tab=pending" class="btn <?= $tab === 'pending' ? 'btn-primary' : 'btn-ghost' ?> btn-sm" style="display:flex; align-items:center; gap:6px;">
                        Bekleyenler 
                        <span class="badge" style="background:var(--live); color:var(--text-white); font-size:0.7rem; padding:2px 6px; border-radius:10px;"><?= $pendingCount ?></span>
                    </a>
                    <a href="?tab=resolved" class="btn <?= $tab === 'resolved' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">
                        Çözülenler (<?= $resolvedCount ?>)
                    </a>
                    <a href="?tab=all" class="btn <?= $tab === 'all' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">
                        Tümü
                    </a>
                </div>

                <?php if (empty($disputes)): ?>
                    <div class="bos-durum" style="padding:var(--space-3xl) 0; text-align:center;">
                        <i class="fas fa-balance-scale" style="font-size:3rem; color:var(--text-muted); margin-bottom:var(--space-md); display:block;"></i>
                        <h3>İtiraz kaydı bulunmuyor</h3>
                        <p style="color:var(--text-muted);margin-top:var(--space-xs);">Şu anda inceleme gerektiren herhangi bir maç itirazı bulunmamaktadır.</p>
                    </div>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:var(--space-lg);">
                        <?php foreach ($disputes as $d): ?>
                            <div class="kart" style="padding:var(--space-lg); border:1px solid var(--border-light);">
                                <!-- Header -->
                                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-light); padding-bottom:var(--space-sm); margin-bottom:var(--space-md); flex-wrap:wrap; gap:var(--space-xs);">
                                    <div>
                                        <strong style="color:var(--accent); font-size:1.05rem;"><?= temizle($d['turnuva_adi']) ?></strong>
                                        <span style="color:var(--text-muted); font-size:0.85rem; margin-left:var(--space-xs);">• Tur <?= $d['round'] ?> • Maç #<?= $d['match_number'] ?></span>
                                    </div>
                                    <div>
                                        <a href="<?= SITE_URL ?>/mac.php?id=<?= $d['id'] ?>" target="_blank" class="btn btn-ghost btn-xs" style="margin-right:8px; border-color:var(--border-light);"><i class="fas fa-external-link-alt"></i> Maç Sayfası</a>
                                        <span class="badge" style="background:<?= $d['dispute_status'] === 'pending' ? 'var(--live)' : 'var(--success)' ?>; color:white;">
                                            <?= $d['dispute_status'] === 'pending' ? 'İTİRAZ BEKLEMEDE' : 'İTİRAZ ÇÖZÜLDÜ' ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Dispute Body Grid -->
                                <div style="display:grid; grid-template-columns:1.2fr 1fr 1.2fr; gap:var(--space-lg); align-items:stretch;">
                                    <!-- Team 1 Report -->
                                    <div style="background:rgba(255,255,255,0.01); border:1px solid var(--border-light); border-radius:var(--radius-md); padding:var(--space-md); display:flex; flex-direction:column; justify-content:space-between; gap:var(--space-md);">
                                        <div>
                                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:var(--space-sm);">
                                                <img src="<?= takimLogoURL($d['takim1_logo']) ?>" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                                                <strong style="font-size:0.95rem; color:var(--text-white);"><?= temizle($d['takim1_adi']) ?></strong>
                                            </div>
                                            <?php if ($d['report1']): ?>
                                                <div style="font-size:0.9rem; margin-bottom:var(--space-xs);">
                                                    Bildirilen Skor: <strong style="color:var(--accent); font-size:1.15rem;"><?= $d['report1']['score1'] ?> - <?= $d['report1']['score2'] ?></strong>
                                                </div>
                                                <div style="font-size:0.82rem; color:var(--text-secondary); margin-bottom:var(--space-sm);">
                                                    Kaptan: <strong><?= temizle($d['report1']['captain_username']) ?></strong>
                                                </div>
                                                <?php if (!empty($d['report1']['notes'])): ?>
                                                    <div style="font-size:0.8rem; background:rgba(0,0,0,0.2); padding:8px; border-radius:var(--radius-sm); border-left:2px solid var(--accent); margin-bottom:var(--space-md); font-style:italic; color:var(--text-primary); max-width: 100%; word-break: break-word;">
                                                        "<?= temizle($d['report1']['notes']) ?>"
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p style="color:var(--text-muted); font-size:0.85rem; font-style:italic;">Bu takım kaptanı henüz skor bildirmedi.</p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($d['report1'] && !empty($d['report1']['proof_image'])): ?>
                                            <div>
                                                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:4px; font-weight:700;">MAÇ SONU EKRAN GÖRÜNTÜSÜ</div>
                                                <div style="width:100%; height:120px; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--border-light); cursor:pointer;" onclick="zoomImage('<?= SITE_URL ?>/assets/uploads/proofs/<?= $d['report1']['proof_image'] ?>')">
                                                    <img src="<?= SITE_URL ?>/assets/uploads/proofs/<?= $d['report1']['proof_image'] ?>" style="width:100%; height:100%; object-fit:cover; transition:var(--transition-fast);" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Resolution Form (Center Column) -->
                                    <div style="display:flex; flex-direction:column; justify-content:center; align-items:center; border-left:1px solid var(--border-light); border-right:1px solid var(--border-light); padding:0 var(--space-md); text-align:center; min-height: 100%;">
                                        <?php if ($d['dispute_status'] === 'pending'): ?>
                                            <form method="POST" style="width:100%;">
                                                <?= csrfInput() ?>
                                                <input type="hidden" name="aksiyon" value="itiraz_coz">
                                                <input type="hidden" name="match_id" value="<?= $d['id'] ?>">
                                                
                                                <h4 style="font-size:0.9rem; margin-bottom:var(--space-sm); font-weight:700; color:var(--text-white);"><i class="fas fa-gavel"></i> Hakem Kararı Girişi</h4>
                                                
                                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:var(--space-sm);">
                                                    <div>
                                                        <label class="form-label" style="font-size:0.72rem; text-align:left;"><?= temizle(strlen($d['takim1_adi']) > 10 ? substr($d['takim1_adi'], 0, 10) . '..' : $d['takim1_adi']) ?></label>
                                                        <input type="number" name="score1" class="form-input" style="padding:6px; text-align:center; font-weight:700;" min="0" required value="<?= $d['report1'] ? $d['report1']['score1'] : 0 ?>">
                                                    </div>
                                                    <div>
                                                        <label class="form-label" style="font-size:0.72rem; text-align:left;"><?= temizle(strlen($d['takim2_adi']) > 10 ? substr($d['takim2_adi'], 0, 10) . '..' : $d['takim2_adi']) ?></label>
                                                        <input type="number" name="score2" class="form-input" style="padding:6px; text-align:center; font-weight:700;" min="0" required value="<?= $d['report1'] ? $d['report1']['score2'] : 0 ?>">
                                                    </div>
                                                </div>
                                                
                                                <div class="form-grup" style="margin-bottom:var(--space-sm); text-align: left;">
                                                    <label class="form-label" style="font-size:0.75rem;">Resmi Kazanan</label>
                                                    <select name="winner_id" class="form-input" style="padding:6px; font-size:0.8rem; height: auto;" required>
                                                        <option value="">Seçiniz...</option>
                                                        <option value="<?= $d['takim1_id'] ?>"><?= temizle($d['takim1_adi']) ?></option>
                                                        <option value="<?= $d['takim2_id'] ?>"><?= temizle($d['takim2_adi']) ?></option>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-grup" style="margin-bottom:var(--space-md);">
                                                    <textarea name="admin_notes" class="form-input" style="height:55px; font-size:0.78rem; padding:6px; resize:none;" placeholder="Hakem Karar Notu (Opsiyonel)"></textarea>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-primary btn-sm btn-block" style="font-weight:700; padding:8px;"><i class="fas fa-check"></i> Uyuşmazlığı Çöz</button>
                                            </form>
                                        <?php else: ?>
                                            <div style="text-align:center;">
                                                <div style="width:44px; height:44px; border-radius:50%; background:rgba(46, 204, 113, 0.1); border:1px solid rgba(46, 204, 113, 0.3); display:flex; align-items:center; justify-content:center; margin:0 auto var(--space-sm);">
                                                    <i class="fas fa-check-circle" style="color:var(--success); font-size:1.5rem;"></i>
                                                </div>
                                                <h4 style="font-size:0.95rem; font-weight:700; color:var(--text-white); margin-bottom:2px;">Uyuşmazlık Çözüldü</h4>
                                                <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:var(--space-sm);">Karar hakem tarafından verilmiştir.</p>
                                                <div style="background:var(--bg-surface); padding:8px 12px; border-radius:var(--radius-sm); border:1px solid var(--border-light); font-size:0.85rem; font-weight:700; display:inline-block;">
                                                    Resmi Skor: <span style="color:var(--accent);"><?= $d['score1'] ?> - <?= $d['score2'] ?></span>
                                                </div>
                                                <?php if (!empty($d['notes'])): ?>
                                                    <div style="font-size:0.75rem; color:var(--text-secondary); margin-top:8px; font-style:italic; max-width:200px; word-break:break-word; background:rgba(0,0,0,0.2); padding:6px; border-radius:4px;">
                                                        "<?= temizle($d['notes']) ?>"
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Team 2 Report -->
                                    <div style="background:rgba(255,255,255,0.01); border:1px solid var(--border-light); border-radius:var(--radius-md); padding:var(--space-md); display:flex; flex-direction:column; justify-content:space-between; gap:var(--space-md);">
                                        <div>
                                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:var(--space-sm);">
                                                <img src="<?= takimLogoURL($d['takim2_logo']) ?>" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                                                <strong style="font-size:0.95rem; color:var(--text-white);"><?= temizle($d['takim2_adi']) ?></strong>
                                            </div>
                                            <?php if ($d['report2']): ?>
                                                <div style="font-size:0.9rem; margin-bottom:var(--space-xs);">
                                                    Bildirilen Skor: <strong style="color:var(--accent); font-size:1.15rem;"><?= $d['report2']['score1'] ?> - <?= $d['report2']['score2'] ?></strong>
                                                </div>
                                                <div style="font-size:0.82rem; color:var(--text-secondary); margin-bottom:var(--space-sm);">
                                                    Kaptan: <strong><?= temizle($d['report2']['captain_username']) ?></strong>
                                                </div>
                                                <?php if (!empty($d['report2']['notes'])): ?>
                                                    <div style="font-size:0.8rem; background:rgba(0,0,0,0.2); padding:8px; border-radius:var(--radius-sm); border-left:2px solid var(--accent); margin-bottom:var(--space-md); font-style:italic; color:var(--text-primary); max-width: 100%; word-break: break-word;">
                                                        "<?= temizle($d['report2']['notes']) ?>"
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p style="color:var(--text-muted); font-size:0.85rem; font-style:italic;">Bu takım kaptanı henüz skor bildirmedi.</p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($d['report2'] && !empty($d['report2']['proof_image'])): ?>
                                            <div>
                                                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:4px; font-weight:700;">MAÇ SONU EKRAN GÖRÜNTÜSÜ</div>
                                                <div style="width:100%; height:120px; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--border-light); cursor:pointer;" onclick="zoomImage('<?= SITE_URL ?>/assets/uploads/proofs/<?= $d['report2']['proof_image'] ?>')">
                                                    <img src="<?= SITE_URL ?>/assets/uploads/proofs/<?= $d['report2']['proof_image'] ?>" style="width:100%; height:100%; object-fit:cover; transition:var(--transition-fast);" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Resim Zoom Modali -->
<div id="imageZoomModal" class="modal-wrapper" style="background: rgba(0,0,0,0.9); z-index:1000;">
    <div style="position:relative; width:90%; max-width:1000px; margin:auto; display:flex; flex-direction:column; align-items:center; justify-content:center; height:100vh;">
        <button type="button" class="modal-kapat" onclick="closeZoomModal()" style="position:absolute; top:20px; right:20px; background:rgba(255,255,255,0.1); border:none; border-radius:50%; width:40px; height:40px; color:white; cursor:pointer; font-size:1.2rem; display:flex; align-items:center; justify-content:center;"><i class="fas fa-times"></i></button>
        <img id="zoomedImage" src="" style="max-width:100%; max-height:85vh; border-radius:var(--radius-md); box-shadow:0 0 30px rgba(0,0,0,0.8); object-fit:contain;">
    </div>
</div>

<script>
function zoomImage(src) {
    document.getElementById('zoomedImage').src = src;
    document.getElementById('imageZoomModal').classList.add('aktif');
}
function closeZoomModal() {
    document.getElementById('imageZoomModal').classList.remove('aktif');
}
window.addEventListener('click', function(event) {
    var modal = document.getElementById('imageZoomModal');
    if (event.target == modal) {
        closeZoomModal();
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/alt.php'; ?>
