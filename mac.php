<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/kimlik.php';
require_once __DIR__ . '/includes/fonksiyonlar.php';

$match_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Maç detayını sorgula
$mac = $db->fetch("SELECT m.*, 
       t1.name as takim1_adi, t1.logo as takim1_logo, t1.id as takim1_id,
       t2.name as takim2_adi, t2.logo as takim2_logo, t2.id as takim2_id,
       tr.name as turnuva_adi, tr.slug as turnuva_slug,
       g.name as oyun_adi
    FROM maclar m 
    LEFT JOIN takimlar t1 ON m.team1_id = t1.id 
    LEFT JOIN takimlar t2 ON m.team2_id = t2.id 
    LEFT JOIN turnuvalar tr ON m.tournament_id = tr.id 
    LEFT JOIN oyunlar g ON tr.game_id = g.id
    WHERE m.id = ?", [$match_id]);

if (!$mac) {
    header("Location: " . SITE_URL . "/maclar.php");
    exit;
}

// Kaptanlık Kontrolü ve Rapor Bilgisi Sorgulama
$kullaniciId = $_SESSION['user_id'] ?? 0;
$kaptanlikKupasi = null;
$kaptanRaporu = null;

if ($kullaniciId > 0 && ($mac['status'] === 'scheduled' || $mac['status'] === 'live')) {
    if ($mac['takim1_id']) {
        $t1_captain = $db->fetch("SELECT user_id FROM takim_uyeleri WHERE team_id = ? AND role = 'captain'", [$mac['takim1_id']]);
        if ($t1_captain && $t1_captain['user_id'] == $kullaniciId) {
            $kaptanlikKupasi = [
                'team_id' => $mac['takim1_id'],
                'team_name' => $mac['takim1_adi'],
                'own_team_num' => 1,
                'opp_team_name' => $mac['takim2_adi'] ?: 'Belli Değil',
                'opp_team_num' => 2
            ];
        }
    }
    if (!$kaptanlikKupasi && $mac['takim2_id']) {
        $t2_captain = $db->fetch("SELECT user_id FROM takim_uyeleri WHERE team_id = ? AND role = 'captain'", [$mac['takim2_id']]);
        if ($t2_captain && $t2_captain['user_id'] == $kullaniciId) {
            $kaptanlikKupasi = [
                'team_id' => $mac['takim2_id'],
                'team_name' => $mac['takim2_adi'],
                'own_team_num' => 2,
                'opp_team_name' => $mac['takim1_adi'] ?: 'Belli Değil',
                'opp_team_num' => 1
            ];
        }
    }
    
    if ($kaptanlikKupasi) {
        $kaptanRaporu = $db->fetch("SELECT * FROM mac_raporlari WHERE match_id = ? AND reporter_id = ?", [$match_id, $kullaniciId]);
    }
}

// POST İşlemleri (Skor Bildirimi)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula($_POST['csrf_token'] ?? '')) {
    $aksiyon = $_POST['aksiyon'] ?? '';
    
    if ($aksiyon === 'skor_bildir') {
        if (!girisYapmisMi()) {
            flashMesaj('Bu işlemi gerçekleştirmek için giriş yapmalısınız.', 'error');
            header("Location: " . SITE_URL . "/mac.php?id=" . $match_id);
            exit;
        }
        
        if (!$kaptanlikKupasi) {
            flashMesaj('Bu maçın skorunu bildirme yetkiniz bulunmamaktadır.', 'error');
            header("Location: " . SITE_URL . "/mac.php?id=" . $match_id);
            exit;
        }
        
        if ($mac['status'] === 'completed' || $mac['status'] === 'cancelled') {
            flashMesaj('Bu maç zaten sonuçlanmış veya iptal edilmiş.', 'error');
            header("Location: " . SITE_URL . "/mac.php?id=" . $match_id);
            exit;
        }
        
        if ($kaptanRaporu) {
            flashMesaj('Bu maç için zaten skor bildiriminde bulundunuz.', 'error');
            header("Location: " . SITE_URL . "/mac.php?id=" . $match_id);
            exit;
        }
        
        $score1 = (int)($_POST['score1'] ?? 0);
        $score2 = (int)($_POST['score2'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        if ($score1 < 0 || $score2 < 0) {
            flashMesaj('Skorlar negatif değer alamaz.', 'error');
            header("Location: " . SITE_URL . "/mac.php?id=" . $match_id);
            exit;
        }
        
        if (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
            flashMesaj('Lütfen kanıt olarak maç sonu skor ekran görüntüsü yükleyin.', 'error');
            header("Location: " . SITE_URL . "/mac.php?id=" . $match_id);
            exit;
        }
        
        $upload = dosyaYukle($_FILES['proof_image'], 'proofs', 5 * 1024 * 1024);
        if (!$upload['success']) {
            flashMesaj('Kanıt yükleme hatası: ' . $upload['error'], 'error');
            header("Location: " . SITE_URL . "/mac.php?id=" . $match_id);
            exit;
        }
        
        $proof_filename = $upload['filename'];
        
        $db->query("
            INSERT INTO mac_raporlari (match_id, reporter_id, team_id, score1, score2, proof_image, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ", [$match_id, $kullaniciId, $kaptanlikKupasi['team_id'], $score1, $score2, $proof_filename, $notes]);
        
        // Karşılaştırma ve Fikstür İlerletme Mantığı
        $opp_team_id = $kaptanlikKupasi['own_team_num'] === 1 ? $mac['takim2_id'] : $mac['takim1_id'];
        $opp_capt_id = null;
        if ($opp_team_id) {
            $opp_capt = $db->fetch("SELECT user_id FROM takim_uyeleri WHERE team_id = ? AND role = 'captain'", [$opp_team_id]);
            if ($opp_capt) {
                $opp_capt_id = $opp_capt['user_id'];
            }
        }
        
        $other_report = null;
        if ($opp_capt_id) {
            $other_report = $db->fetch("SELECT * FROM mac_raporlari WHERE match_id = ? AND reporter_id = ?", [$match_id, $opp_capt_id]);
        }
        
        if ($other_report) {
            if ($other_report['score1'] == $score1 && $other_report['score2'] == $score2) {
                $winner_id = null;
                if ($score1 > $score2) {
                    $winner_id = $mac['takim1_id'];
                } elseif ($score2 > $score1) {
                    $winner_id = $mac['takim2_id'];
                }
                
                $db->query("
                    UPDATE maclar 
                    SET score1 = ?, score2 = ?, status = 'completed', winner_id = ?, completed_at = NOW(), dispute_status = 'none'
                    WHERE id = ?
                ", [$score1, $score2, $winner_id, $match_id]);
                
                $winner_name = $winner_id == $mac['takim1_id'] ? $mac['takim1_adi'] : $mac['takim2_adi'];
                $msg = "Maç sona erdi! Skor: {$score1} - {$score2}. Kazanan: {$winner_name}. (Kaptanlar tarafından onaylandı)";
                $db->query("INSERT INTO canli_guncellemeler (match_id, message, event_type) VALUES (?, ?, 'match_end')", [$match_id, $msg]);
                
                $next_round = (int)$mac['round'] + 1;
                $next_match_num = (int)ceil($mac['match_number'] / 2);
                $col = ($mac['match_number'] % 2 !== 0) ? 'team1_id' : 'team2_id';
                
                $next_mac = $db->fetch("SELECT id FROM maclar WHERE tournament_id = ? AND round = ? AND match_number = ?", 
                    [$mac['tournament_id'], $next_round, $next_match_num]);
                
                if ($next_mac) {
                    $db->query("UPDATE maclar SET $col = ? WHERE id = ?", [$winner_id, $next_mac['id']]);
                }
                
                $t1_members = $db->fetchAll("SELECT user_id FROM takim_uyeleri WHERE team_id = ?", [$mac['takim1_id']]);
                $t2_members = $db->fetchAll("SELECT user_id FROM takim_uyeleri WHERE team_id = ?", [$mac['takim2_id']]);
                $all_members = array_unique(array_merge(
                    array_map(fn($m) => $m['user_id'], $t1_members),
                    array_map(fn($m) => $m['user_id'], $t2_members)
                ));
                
                $title = "Maç Sonucu Onaylandı";
                $notif_msg = "{$mac['takim1_adi']} vs {$mac['takim2_adi']} maçınız {$score1}:{$score2} skoruyla kaptanlar tarafından onaylandı.";
                foreach ($all_members as $uid) {
                    $db->query("INSERT INTO bildirimler (user_id, title, message, type, link) VALUES (?, ?, ?, 'match', ?)", 
                        [$uid, $title, $notif_msg, "mac.php?id=" . $match_id]);
                }
                
                flashMesaj('Her iki takım kaptanının skor bildirimleri eşleşti. Maç otomatik olarak sonuçlandırıldı!', 'success');
            } else {
                $db->query("UPDATE maclar SET dispute_status = 'pending' WHERE id = ?", [$match_id]);
                
                $msg = "Maç kaptanları farklı skorlar bildirdi! Uyuşmazlık nedeniyle itiraz süreci başlatıldı, hakem incelemesi bekleniyor.";
                $db->query("INSERT INTO canli_guncellemeler (match_id, message, event_type) VALUES (?, ?, 'info')", [$match_id, $msg]);
                
                $title = "Maç Skorunda Uyuşmazlık";
                $notif_msg = "Oynadığınız maçta diğer takım kaptanı farklı bir skor bildirdi. İtiraz süreci başlatıldı, hakemler karar verecektir.";
                
                $db->query("INSERT INTO bildirimler (user_id, title, message, type, link) VALUES (?, ?, ?, 'match', ?)", 
                    [$kullaniciId, $title, $notif_msg, "mac.php?id=" . $match_id]);
                if ($opp_capt_id) {
                    $db->query("INSERT INTO bildirimler (user_id, title, message, type, link) VALUES (?, ?, ?, 'match', ?)", 
                        [$opp_capt_id, $title, $notif_msg, "mac.php?id=" . $match_id]);
                }
                
                flashMesaj('Dikkat: Diğer takım kaptanının bildirdiği skor sizinkiyle uyuşmuyor! İtiraz kaydı oluşturuldu, hakem incelemesi bekleniyor.', 'warning');
            }
        } else {
            flashMesaj('Skor bildiriminiz kaydedildi. Diğer takım kaptanının da bildirimi tamamlandığında sonuç otomatik olarak işlenecektir.', 'success');
        }
        
        header("Location: " . SITE_URL . "/mac.php?id=" . $match_id);
        exit;
    }
}

$sayfaBasligi = temizle($mac['takim1_adi'] ?: 'Belli Değil') . ' vs ' . temizle($mac['takim2_adi'] ?: 'Belli Değil') . ' | Maç Detayı';
require_once __DIR__ . '/includes/ust.php';

// Kadroları sorgula
$team1_players = [];
if ($mac['takim1_id']) {
    $team1_players = $db->fetchAll("SELECT tu.*, u.username, u.first_name, u.last_name, u.avatar 
        FROM takim_uyeleri tu 
        JOIN kullanicilar u ON tu.user_id = u.id 
        WHERE tu.team_id = ? 
        ORDER BY CASE WHEN tu.role = 'captain' THEN 1 ELSE 2 END ASC, u.username ASC", [$mac['takim1_id']]);
}

$team2_players = [];
if ($mac['takim2_id']) {
    $team2_players = $db->fetchAll("SELECT tu.*, u.username, u.first_name, u.last_name, u.avatar 
        FROM takim_uyeleri tu 
        JOIN kullanicilar u ON tu.user_id = u.id 
        WHERE tu.team_id = ? 
        ORDER BY CASE WHEN tu.role = 'captain' THEN 1 ELSE 2 END ASC, u.username ASC", [$mac['takim2_id']]);
}

// Canlı güncellemeleri sorgula (en yeni en üstte)
$timeline = $db->fetchAll("SELECT * FROM canli_guncellemeler WHERE match_id = ? ORDER BY created_at DESC, id DESC", [$match_id]);

// Alt harita/round detaylarını sorgula
$map_rounds = $db->fetchAll("SELECT r.*, t.name as winner_name 
    FROM mac_roundlari r 
    LEFT JOIN takimlar t ON r.winner_id = t.id 
    WHERE r.match_id = ? 
    ORDER BY r.round_number ASC", [$match_id]);

// Maç Durumu Çevirici
$durum = macDurumCevir($mac['status']);

function macDurumCevir($status) {
    switch ($status) {
        case 'scheduled': return ['text' => 'Planlandı', 'class' => 'warning'];
        case 'live': return ['text' => 'Canlı', 'class' => 'live'];
        case 'completed': return ['text' => 'Tamamlandı', 'class' => 'success'];
        case 'cancelled': return ['text' => 'İptal Edildi', 'class' => 'danger'];
        case 'postponed': return ['text' => 'Ertelendi', 'class' => 'info'];
        default: return ['text' => 'Bilinmiyor', 'class' => 'secondary'];
    }
}

// Olay Tipi İkon Belirleme
function olayIkonu($type) {
    switch ($type) {
        case 'match_start': return '<i class="fas fa-play"></i>';
        case 'match_end': return '<i class="fas fa-trophy"></i>';
        case 'round_win': return '<i class="fas fa-crosshairs"></i>';
        case 'kill': return '<i class="fas fa-skull"></i>';
        case 'objective': return '<i class="fas fa-flag"></i>';
        case 'pause': return '<i class="fas fa-pause"></i>';
        case 'resume': return '<i class="fas fa-play-circle"></i>';
        case 'timeout': return '<i class="fas fa-stopwatch"></i>';
        default: return '<i class="fas fa-info-circle"></i>';
    }
}

// Yayın Embed Kodu Oluşturucu
function getEmbedCode($url) {
    if (empty($url)) return '';
    
    // Twitch channel embed
    if (preg_match('/(?:https?:\/\/)?(?:www\.)?twitch\.tv\/([a-zA-Z0-9_]+)/i', $url, $matches)) {
        $channel = $matches[1];
        $parent = $_SERVER['HTTP_HOST'];
        $parent = explode(':', $parent)[0];
        return '<iframe src="https://player.twitch.tv/?channel=' . htmlspecialchars($channel) . '&parent=' . htmlspecialchars($parent) . '&muted=false" frameborder="0" allowfullscreen="true" scrolling="no"></iframe>';
    }
    
    // YouTube embed
    if (preg_match('/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/i', $url, $matches)) {
        $videoId = $matches[1];
        return '<iframe src="https://www.youtube.com/embed/' . htmlspecialchars($videoId) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    }
    if (preg_match('/(?:https?:\/\/)?(?:www\.)?youtube\.com\/live\/([a-zA-Z0-9_-]+)/i', $url, $matches)) {
        $videoId = $matches[1];
        return '<iframe src="https://www.youtube.com/embed/' . htmlspecialchars($videoId) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    }
    if (preg_match('/(?:https?:\/\/)?(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]+)/i', $url, $matches)) {
        $videoId = $matches[1];
        return '<iframe src="https://www.youtube.com/embed/' . htmlspecialchars($videoId) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    }
    
    // Gömülemezse link göster
    return '<div class="bos-durum" style="padding:var(--space-xl);"><i class="fas fa-play"></i><p>Canlı yayın adresi pencere içine gömülemedi. Harici olarak izlemek için <a href="' . htmlspecialchars($url) . '" target="_blank" class="vurgu" style="font-weight:700;">buraya tıklayarak yayına gidebilirsiniz</a>.</p></div>';
}
?>

<section class="sayfa-basligi">
    <div class="container sayfa-basligi-icerik">
        <h1><i class="fas fa-gamepad" style="color:var(--accent)"></i> Maç Detayı</h1>
        <div class="breadcrumb">
            <a href="<?= SITE_URL ?>">Ana Sayfa</a> 
            <i class="fas fa-chevron-right"></i> 
            <a href="<?= SITE_URL ?>/maclar.php">Maçlar</a> 
            <i class="fas fa-chevron-right"></i> 
            <span>Maç Detayı</span>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        
        <!-- Maç Büyük Skor Kartı -->
        <div class="mac-detay-skor-kart">
            <div style="text-align:center; margin-bottom: var(--space-md);">
                <a href="<?= SITE_URL ?>/turnuva.php?slug=<?= $mac['turnuva_slug'] ?>" style="font-size:0.9rem; color:var(--text-secondary); font-weight:600; text-transform:uppercase; letter-spacing:1px; hover:color:var(--accent);">
                    <i class="fas fa-trophy" style="color:var(--accent); margin-right:4px;"></i> <?= temizle($mac['turnuva_adi']) ?>
                </a>
            </div>
            
            <div class="mac-detay-skor-grid">
                <!-- Takım 1 -->
                <div class="mac-detay-takim">
                    <a href="<?= $mac['takim1_id'] ? SITE_URL . '/takim.php?id=' . $mac['takim1_id'] : '#' ?>" class="mac-detay-logo">
                        <img src="<?= takimLogoURL($mac['takim1_logo']) ?>" alt="Takım Logosu">
                    </a>
                    <div class="mac-detay-isim">
                        <?= temizle($mac['takim1_adi'] ?: 'Belli Değil') ?>
                    </div>
                </div>
                
                <!-- Skor / Merkez -->
                <div class="mac-detay-skor-merkez">
                    <span class="badge badge-<?= $durum['class'] ?>" style="margin-bottom:var(--space-sm); font-size:0.75rem; letter-spacing:1px; font-weight:700;">
                        <?= $durum['text'] ?>
                    </span>
                    
                    <div class="mac-detay-skor-rakamlar">
                        <?php if ($mac['status'] === 'scheduled'): ?>
                            <span class="mac-detay-vs">VS</span>
                        <?php else: ?>
                            <span class="mac-detay-skor <?= ($mac['status'] === 'completed' && $mac['winner_id'] == $mac['team1_id']) ? 'winner' : '' ?>">
                                <?= $mac['score1'] ?>
                            </span>
                            <span style="font-size: 2rem; color: var(--text-muted); font-weight: 300;">:</span>
                            <span class="mac-detay-skor <?= ($mac['status'] === 'completed' && $mac['winner_id'] == $mac['team2_id']) ? 'winner' : '' ?>">
                                <?= $mac['score2'] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div style="font-size:0.8rem; color:var(--text-secondary); margin-top:5px; font-weight:500;">
                        BO<?= $mac['best_of'] ?> <?= $mac['map_name'] ? '• ' . temizle($mac['map_name']) : '' ?>
                    </div>
                    
                    <?php if ($mac['scheduled_at']): ?>
                        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:5px;">
                            <i class="far fa-calendar-alt"></i> <?= date('d.m.Y H:i', strtotime($mac['scheduled_at'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Takım 2 -->
                <div class="mac-detay-takim">
                    <a href="<?= $mac['takim2_id'] ? SITE_URL . '/takim.php?id=' . $mac['takim2_id'] : '#' ?>" class="mac-detay-logo">
                        <img src="<?= takimLogoURL($mac['takim2_logo']) ?>" alt="Takım Logosu">
                    </a>
                    <div class="mac-detay-isim">
                        <?= temizle($mac['takim2_adi'] ?: 'Belli Değil') ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($kaptanlikKupasi): ?>
            <div class="kart" style="padding:var(--space-md) var(--space-lg); margin-top:var(--space-md); margin-bottom:var(--space-lg); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:var(--space-sm); border-left:4px solid var(--accent); background:rgba(108, 92, 231, 0.05);">
                <div style="display:flex; align-items:center; gap:var(--space-sm);">
                    <div style="width:36px; height:36px; border-radius:50%; background:var(--accent-light); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-crown" style="color:var(--accent);"></i>
                    </div>
                    <div>
                        <h4 style="font-size:0.95rem; margin-bottom:2px; font-weight:700; color:var(--text-white);">Takım Kaptanı Alanı</h4>
                        <p style="font-size:0.82rem; color:var(--text-secondary);">
                            <?php if ($kaptanRaporu): ?>
                                Skor bildiriminiz alındı: <strong><?= $kaptanRaporu['score1'] ?> - <?= $kaptanRaporu['score2'] ?></strong>. 
                                <?php if ($mac['dispute_status'] === 'pending'): ?>
                                    <span style="color:var(--danger); font-weight:700;"><i class="fas fa-exclamation-triangle"></i> Skor uyuşmazlığı nedeniyle itiraz süreci başlatıldı. Hakem incelemesi bekleniyor.</span>
                                <?php else: ?>
                                    Diğer takım kaptanının bildirimi bekleniyor.
                                <?php endif; ?>
                            <?php else: ?>
                                <strong><?= temizle($kaptanlikKupasi['team_name']) ?></strong> takımı adına bu maçın skorunu bildirebilirsiniz.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <?php if (!$kaptanRaporu): ?>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openReportModal()" style="font-weight:700;">
                        <i class="fas fa-clipboard-check"></i> Skor Bildir
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$kaptanlikKupasi && $mac['dispute_status'] === 'pending'): ?>
            <div class="kart" style="padding:var(--space-md) var(--space-lg); margin-top:var(--space-md); margin-bottom:var(--space-lg); display:flex; align-items:center; gap:var(--space-sm); border-left:4px solid var(--danger); background:rgba(255, 82, 82, 0.05);">
                <div style="width:36px; height:36px; border-radius:50%; background:rgba(255, 82, 82, 0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i>
                </div>
                <div>
                    <h4 style="font-size:0.95rem; margin-bottom:2px; font-weight:700; color:var(--text-white);">Maç İtiraz Aşamasında</h4>
                    <p style="font-size:0.82rem; color:var(--text-secondary);">Takım kaptanlarının farklı skor bildirmesi nedeniyle maç askıya alınmıştır. Hakemler nihai sonucu belirleyecektir.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Sekmeler -->
        <div class="tab-menu">
            <button class="tab-link aktif" onclick="sekmeDegistir(event, 'canli')">
                <i class="fas fa-broadcast-tower"></i> Canlı Anlatım
            </button>
            <button class="tab-link" onclick="sekmeDegistir(event, 'kadro')">
                <i class="fas fa-users"></i> Oyuncu Kadroları
            </button>
            <?php if ($mac['stream_url']): ?>
                <button class="tab-link" onclick="sekmeDegistir(event, 'yayin')">
                    <i class="fab fa-twitch"></i> Canlı Yayın
                </button>
            <?php endif; ?>
            <?php if (!empty($map_rounds) || $mac['best_of'] > 1): ?>
                <button class="tab-link" onclick="sekmeDegistir(event, 'haritalar')">
                    <i class="fas fa-map"></i> Harita Detayları
                </button>
            <?php endif; ?>
        </div>

        <!-- Canlı Anlatım Akışı Sekmesi -->
        <div id="canli" class="tab-icerik aktif">
            <h3 style="margin-bottom:var(--space-md); color:var(--text-white);">
                <i class="fas fa-stream" style="color:var(--accent);"></i> Olay Günlüğü
            </h3>
            
            <?php if (empty($timeline)): ?>
                <div class="bos-durum" style="padding: var(--space-2xl);">
                    <i class="fas fa-history"></i>
                    <p>Bu maça ait henüz bir canlı güncelleme veya olay günlüğü girilmemiş.</p>
                </div>
            <?php else: ?>
                <div class="timeline-container">
                    <?php foreach ($timeline as $olay): ?>
                        <div class="timeline-item <?= htmlspecialchars($olay['event_type']) ?>">
                            <div class="timeline-icon">
                                <?= olayIkonu($olay['event_type']) ?>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-text">
                                    <?= temizle($olay['message']) ?>
                                </div>
                                <div class="timeline-time">
                                    <?= date('H:i:s', strtotime($olay['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Oyuncu Kadroları Sekmesi -->
        <div id="kadro" class="tab-icerik">
            <div class="lineup-grid">
                <!-- Takım 1 Kadro -->
                <div>
                    <h4 class="lineup-team-title">
                        <img src="<?= takimLogoURL($mac['takim1_logo']) ?>" style="width:28px; height:28px; border-radius:50%;">
                        <?= temizle($mac['takim1_adi'] ?: 'Takım 1') ?>
                    </h4>
                    
                    <?php if (empty($team1_players)): ?>
                        <div class="bos-durum"><i class="fas fa-users"></i><p>Kadro bilgisi bulunmuyor.</p></div>
                    <?php else: ?>
                        <div class="lineup-players-list">
                            <?php foreach ($team1_players as $p): ?>
                                <div class="lineup-player-card">
                                    <img src="<?= avatarURL($p['avatar']) ?>" alt="Kullanıcı Avatarı" class="lineup-player-avatar">
                                    <div class="lineup-player-info">
                                        <div class="lineup-player-username">
                                            <a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $p['user_id'] ?>" style="color:inherit; font-weight:inherit;"><?= temizle($p['username']) ?></a>
                                        </div>
                                        <div class="lineup-player-realname"><?= temizle($p['first_name'] . ' ' . $p['last_name']) ?></div>
                                    </div>
                                    <span class="lineup-player-role <?= $p['role'] === 'captain' ? 'captain' : '' ?>">
                                        <?= rolCevir($p['role']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Takım 2 Kadro -->
                <div>
                    <h4 class="lineup-team-title">
                        <img src="<?= takimLogoURL($mac['takim2_logo']) ?>" style="width:28px; height:28px; border-radius:50%;">
                        <?= temizle($mac['takim2_adi'] ?: 'Takım 2') ?>
                    </h4>
                    
                    <?php if (empty($team2_players)): ?>
                        <div class="bos-durum"><i class="fas fa-users"></i><p>Kadro bilgisi bulunmuyor.</p></div>
                    <?php else: ?>
                        <div class="lineup-players-list">
                            <?php foreach ($team2_players as $p): ?>
                                <div class="lineup-player-card">
                                    <img src="<?= avatarURL($p['avatar']) ?>" alt="Kullanıcı Avatarı" class="lineup-player-avatar">
                                    <div class="lineup-player-info">
                                        <div class="lineup-player-username">
                                            <a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $p['user_id'] ?>" style="color:inherit; font-weight:inherit;"><?= temizle($p['username']) ?></a>
                                        </div>
                                        <div class="lineup-player-realname"><?= temizle($p['first_name'] . ' ' . $p['last_name']) ?></div>
                                    </div>
                                    <span class="lineup-player-role <?= $p['role'] === 'captain' ? 'captain' : '' ?>">
                                        <?= rolCevir($p['role']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Canlı Yayın Sekmesi -->
        <?php if ($mac['stream_url']): ?>
            <div id="yayin" class="tab-icerik">
                <h3 style="margin-bottom:var(--space-md); color:var(--text-white);"><i class="fab fa-twitch" style="color:var(--accent);"></i> Canlı Maç Yayını</h3>
                <div class="stream-embed-responsive">
                    <?= getEmbedCode($mac['stream_url']) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Haritalar/Rounds Sekmesi -->
        <?php if (!empty($map_rounds) || $mac['best_of'] > 1): ?>
            <div id="haritalar" class="tab-icerik">
                <h3 style="margin-bottom:var(--space-md); color:var(--text-white);"><i class="fas fa-map-marked-alt" style="color:var(--accent);"></i> Seri Haritaları</h3>
                
                <?php if (empty($map_rounds)): ?>
                    <div class="bos-durum">
                        <i class="fas fa-map"></i>
                        <p>Henüz harita skorları girilmemiş.</p>
                    </div>
                <?php else: ?>
                    <div class="rounds-list">
                        <?php foreach ($map_rounds as $mr): ?>
                            <div class="round-card">
                                <div class="round-map-name">
                                    <span style="color:var(--text-secondary); margin-right:8px; font-size:0.9rem;">#<?= $mr['round_number'] ?></span>
                                    <?= temizle($mr['map_name'] ?: 'Harita') ?>
                                </div>
                                <div class="round-scores">
                                    <span style="color: <?= $mr['winner_id'] == $mac['team1_id'] ? 'var(--success)' : 'var(--text-muted)' ?>"><?= $mr['team1_score'] ?></span>
                                    <span style="color:var(--text-muted)">-</span>
                                    <span style="color: <?= $mr['winner_id'] == $mac['team2_id'] ? 'var(--success)' : 'var(--text-muted)' ?>"><?= $mr['team2_score'] ?></span>
                                </div>
                                <div>
                                    <?php if ($mr['winner_id']): ?>
                                        <span class="round-winner-badge" style="background: rgba(46, 204, 113, 0.1); border: 1px solid rgba(46, 204, 113, 0.3); color: var(--success);">
                                            Kazanan: <?= temizle($mr['winner_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="round-winner-badge" style="background: var(--bg-surface); border: 1px solid var(--border-light); color: var(--text-muted);">
                                            Skor Girilmemiş
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<script>
function sekmeDegistir(evt, sekmeAdi) {
    var i, tabcontent, tablinks;
    
    // Tüm içerikleri gizle
    tabcontent = document.getElementsByClassName("tab-icerik");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].classList.remove("aktif");
    }
    
    // Tüm sekmelerin aktifliğini kaldır
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("aktif");
    }
    
    // Hedef sekme içeriğini göster ve butonu aktif yap
    document.getElementById(sekmeAdi).classList.add("aktif");
    evt.currentTarget.classList.add("aktif");
}
</script>

<?php if ($kaptanlikKupasi && !$kaptanRaporu): ?>
<!-- Skor Bildirme Modali -->
<div id="reportScoreModal" class="modal-wrapper">
    <div class="modal-icerik" style="max-width: 500px;">
        <div class="modal-baslik">
            <h3><i class="fas fa-clipboard-check" style="color:var(--accent)"></i> Maç Skoru Bildir</h3>
            <button type="button" class="modal-kapat" onclick="closeReportModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="modal-form" style="margin-top:var(--space-md);">
            <?= csrfInput() ?>
            <input type="hidden" name="aksiyon" value="skor_bildir">
            
            <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:var(--space-md);">
                Lütfen maçtaki resmi harita/raunt skorlarını giriniz ve doğruluğunu ispatlamak için maç sonu skor tablosu ekran görüntüsünü yükleyiniz.
            </p>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md); margin-bottom:var(--space-md);">
                <div class="form-grup">
                    <label class="form-label" for="report_score1" style="font-weight:600; display:block; margin-bottom:var(--space-xs);">
                        <?= temizle($mac['takim1_adi']) ?> Skoru
                    </label>
                    <input type="number" name="score1" id="report_score1" class="form-input" min="0" required style="width:100%;">
                </div>
                <div class="form-grup">
                    <label class="form-label" for="report_score2" style="font-weight:600; display:block; margin-bottom:var(--space-xs);">
                        <?= temizle($mac['takim2_adi']) ?> Skoru
                    </label>
                    <input type="number" name="score2" id="report_score2" class="form-input" min="0" required style="width:100%;">
                </div>
            </div>

            <div class="form-grup" style="margin-bottom:var(--space-md);">
                <label class="form-label" style="font-weight:600; display:block; margin-bottom:var(--space-xs);">Ekran Görüntüsü Kanıtı <span class="zorunlu">*</span></label>
                <div style="position:relative; display:flex; flex-direction:column; align-items:center; justify-content:center; border:2px dashed var(--border); border-radius:var(--radius-md); padding:var(--space-lg); background:var(--bg-surface); cursor:pointer; transition:var(--transition-fast); text-align:center;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'" onclick="document.getElementById('proof_image').click();">
                    <i class="fas fa-cloud-upload-alt" style="font-size:2rem; color:var(--text-muted); margin-bottom:8px;"></i>
                    <span id="upload-label" style="font-size:0.85rem; color:var(--text-primary); font-weight:600;">Dosya Seçin veya Sürükleyin</span>
                    <span style="font-size:0.72rem; color:var(--text-muted); margin-top:2px;">PNG, JPG, JPEG veya WEBP (Maks: 5MB)</span>
                    <input type="file" name="proof_image" id="proof_image" accept="image/*" required style="display:none;" onchange="dosyaSecildi(this)">
                </div>
            </div>

            <div class="form-grup" style="margin-bottom:var(--space-lg);">
                <label class="form-label" for="report_notes" style="font-weight:600; display:block; margin-bottom:var(--space-xs);">Açıklama / Notlar (İsteğe Bağlı)</label>
                <textarea name="notes" id="report_notes" class="form-input" rows="3" placeholder="Hakemlerin veya diğer takımın bilmesini istediğiniz bir detay varsa yazabilirsiniz..." style="width:100%; resize:none; padding:8px;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:var(--space-md);">
                <button type="button" class="btn btn-ghost btn-sm" onclick="closeReportModal()">Vazgeç</button>
                <button type="submit" class="btn btn-primary btn-sm" style="font-weight:700;">Gönder</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReportModal() {
    document.getElementById('reportScoreModal').classList.add('aktif');
}
function closeReportModal() {
    document.getElementById('reportScoreModal').classList.remove('aktif');
}
function dosyaSecildi(input) {
    if (input.files && input.files[0]) {
        var label = document.getElementById('upload-label');
        label.innerHTML = "Seçilen Dosya: <strong style='color:var(--accent);'>" + input.files[0].name + "</strong>";
    }
}
window.addEventListener('click', function(event) {
    var modal = document.getElementById('reportScoreModal');
    if (event.target == modal) {
        closeReportModal();
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
