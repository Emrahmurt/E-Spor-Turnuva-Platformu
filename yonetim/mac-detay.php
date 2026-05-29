<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';
adminGerekli();

$match_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Maç detayını sorgula
$mac = $db->fetch("SELECT m.*, 
       t1.name as takim1_adi, t1.logo as takim1_logo, t1.id as takim1_id,
       t2.name as takim2_adi, t2.logo as takim2_logo, t2.id as takim2_id,
       tr.name as turnuva_adi, tr.id as turnuva_id, tr.slug as turnuva_slug
    FROM maclar m 
    LEFT JOIN takimlar t1 ON m.team1_id = t1.id 
    LEFT JOIN takimlar t2 ON m.team2_id = t2.id 
    LEFT JOIN turnuvalar tr ON m.tournament_id = tr.id 
    WHERE m.id = ?", [$match_id]);

if (!$mac) {
    header("Location: " . SITE_URL . "/yonetim/maclar.php");
    exit;
}

// POST İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula($_POST['csrf_token'] ?? '')) {
    $aksiyon = $_POST['aksiyon'] ?? '';

    // 1. Maç Genel Bilgilerini Güncelle
    if ($aksiyon === 'mac_bilgi_guncelle') {
        $score1 = (int)($_POST['score1'] ?? 0);
        $score2 = (int)($_POST['score2'] ?? 0);
        $status = $_POST['status'] ?? 'scheduled';
        $winner_id = $_POST['winner_id'] ? (int)$_POST['winner_id'] : null;
        $map_name = trim($_POST['map_name'] ?? '');
        $stream_url = trim($_POST['stream_url'] ?? '');
        $best_of = (int)($_POST['best_of'] ?? 1);
        $scheduled_at = $_POST['scheduled_at'] ? date('Y-m-d H:i:s', strtotime($_POST['scheduled_at'])) : null;

        if ($winner_id === 0) $winner_id = null;

        // Maçı güncelle
        $db->query("UPDATE maclar SET score1 = ?, score2 = ?, status = ?, winner_id = ?, map_name = ?, stream_url = ?, best_of = ?, scheduled_at = ? WHERE id = ?",
            [$score1, $score2, $status, $winner_id, $map_name, $stream_url, $best_of, $scheduled_at, $match_id]);

        // Maç tamamlandıysa ve kazanan varsa, sonraki tura taşı
        if ($status === 'completed' && $winner_id !== null) {
            $next_round = $mac['round'] + 1;
            $next_match_num = (int)ceil($mac['match_number'] / 2);
            $col = ($mac['match_number'] % 2 !== 0) ? 'team1_id' : 'team2_id';
            
            // Sonraki tur maçının varlığını kontrol et
            $next_mac = $db->fetch("SELECT id FROM maclar WHERE tournament_id = ? AND round = ? AND match_number = ?", 
                [$mac['turnuva_id'], $next_round, $next_match_num]);
            
            if ($next_mac) {
                $db->query("UPDATE maclar SET $col = ? WHERE id = ?", [$winner_id, $next_mac['id']]);
            }
        }

        flashMesaj('Maç genel bilgileri başarıyla güncellendi.', 'success');
        header("Location: " . SITE_URL . "/yonetim/mac-detay.php?id=" . $match_id);
        exit;
    }

    // 2. Canlı Olay Günlüğü (Timeline) Ekle
    if ($aksiyon === 'olay_ekle') {
        $event_type = $_POST['event_type'] ?? 'info';
        $team_id = $_POST['team_id'] ? (int)$_POST['team_id'] : null;
        $message = trim($_POST['message'] ?? '');

        if ($team_id === 0) $team_id = null;

        if (empty($message)) {
            flashMesaj('Lütfen bir olay açıklaması girin.', 'error');
        } else {
            $db->query("INSERT INTO canli_guncellemeler (match_id, message, event_type, team_id) VALUES (?, ?, ?, ?)",
                [$match_id, $message, $event_type, $team_id]);
            flashMesaj('Olay günlüğe başarıyla eklendi.', 'success');
        }
        header("Location: " . SITE_URL . "/yonetim/mac-detay.php?id=" . $match_id);
        exit;
    }

    // 3. Canlı Olay Sil
    if ($aksiyon === 'olay_sil') {
        $olay_id = (int)$_POST['olay_id'];
        $db->query("DELETE FROM canli_guncellemeler WHERE id = ? AND match_id = ?", [$olay_id, $match_id]);
        flashMesaj('Olay günlükten silindi.', 'success');
        header("Location: " . SITE_URL . "/yonetim/mac-detay.php?id=" . $match_id);
        exit;
    }

    // 4. Harita / Round Ekle veya Güncelle
    if ($aksiyon === 'harita_guncelle') {
        $round_number = (int)$_POST['round_number'];
        $map_name = trim($_POST['round_map_name'] ?? '');
        $team1_score = (int)($_POST['team1_score'] ?? 0);
        $team2_score = (int)($_POST['team2_score'] ?? 0);
        $winner_id = $_POST['round_winner_id'] ? (int)$_POST['round_winner_id'] : null;

        if ($winner_id === 0) $winner_id = null;

        // Bu round daha önce eklenmiş mi kontrol et
        $varMi = $db->fetch("SELECT id FROM mac_roundlari WHERE match_id = ? AND round_number = ?", [$match_id, $round_number]);

        if ($varMi) {
            $db->query("UPDATE mac_roundlari SET map_name = ?, team1_score = ?, team2_score = ?, winner_id = ? WHERE id = ?",
                [$map_name, $team1_score, $team2_score, $winner_id, $varMi['id']]);
        } else {
            $db->query("INSERT INTO mac_roundlari (match_id, round_number, map_name, team1_score, team2_score, winner_id) VALUES (?, ?, ?, ?, ?, ?)",
                [$match_id, $round_number, $map_name, $team1_score, $team2_score, $winner_id]);
        }

        flashMesaj('Harita/Tur bilgileri güncellendi.', 'success');
        header("Location: " . SITE_URL . "/yonetim/mac-detay.php?id=" . $match_id);
        exit;
    }

    // 5. Harita / Round Sil
    if ($aksiyon === 'harita_sil') {
        $round_id = (int)$_POST['round_id'];
        $db->query("DELETE FROM mac_roundlari WHERE id = ? AND match_id = ?", [$round_id, $match_id]);
        flashMesaj('Harita kaydı silindi.', 'success');
        header("Location: " . SITE_URL . "/yonetim/mac-detay.php?id=" . $match_id);
        exit;
    }
}

// Canlı olayları çek (en yeni en üstte)
$timeline = $db->fetchAll("SELECT * FROM canli_guncellemeler WHERE match_id = ? ORDER BY created_at DESC, id DESC", [$match_id]);

// Alt harita/round detaylarını çek
$map_rounds = $db->fetchAll("SELECT r.*, t.name as winner_name 
    FROM mac_roundlari r 
    LEFT JOIN takimlar t ON r.winner_id = t.id 
    WHERE r.match_id = ? 
    ORDER BY r.round_number ASC", [$match_id]);

$sayfaBasligi = 'Maç Canlı Konsolu';
require_once dirname(__DIR__) . '/includes/ust.php';
?>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <div class="dashboard-grid">
            <!-- Sidebar -->
            <?php 
            $aktifSekme = 'maclar';
            require_once __DIR__ . '/sidebar.php'; 
            ?>

            <!-- İçerik -->
            <div class="dashboard-icerik">
                <?= flashGoster() ?>
                
                <!-- Üst Bilgi Başlığı -->
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xl);padding-bottom:var(--space-lg);border-bottom:1px solid var(--border-light);flex-wrap:wrap;gap:var(--space-md);">
                    <div>
                        <span style="color:var(--text-secondary);font-size:0.85rem;">
                            <a href="<?= SITE_URL ?>/yonetim/maclar.php?turnuva_id=<?= $mac['turnuva_id'] ?>" style="color:var(--accent);"><i class="fas fa-arrow-left"></i> Fikstüre Dön</a>
                        </span>
                        <h2 style="font-size:1.5rem;margin-top:5px;">
                            <i class="fas fa-broadcast-tower" style="color:var(--live)"></i> Maç Canlı Konsolu
                        </h2>
                        <span style="color:var(--text-muted);font-size:0.85rem;"><?= temizle($mac['turnuva_adi']) ?> • Tur <?= $mac['round'] ?> • Maç #<?= $mac['match_number'] ?></span>
                    </div>
                    <div>
                        <a href="<?= SITE_URL ?>/mac.php?id=<?= $match_id ?>" target="_blank" class="btn btn-ghost btn-sm" style="border-color:var(--border-light); font-weight:600;">
                            <i class="fas fa-external-link-alt"></i> Kullanıcı Sayfası Önizle
                        </a>
                    </div>
                </div>

                <div class="live-console-grid">
                    
                    <!-- SOL KOLON: Skor, Durum ve Harita Yönetimi -->
                    <div>
                        <!-- Maç Skor ve Durum Güncelleme Formu -->
                        <div class="live-console-card">
                            <h3 style="font-size:1.1rem; margin-bottom:var(--space-md); border-bottom:1px solid var(--border-light); padding-bottom:var(--space-xs); color:var(--text-white);">
                                <i class="fas fa-edit"></i> Genel Maç Bilgileri
                            </h3>
                            
                            <form method="POST">
                                <?= csrfInput() ?>
                                <input type="hidden" name="aksiyon" value="mac_bilgi_guncelle">
                                
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md); margin-bottom:var(--space-md);">
                                    <div class="form-group">
                                        <label class="form-label" style="font-size:0.8rem; font-weight:700;"><?= temizle($mac['takim1_adi'] ?: 'Takım 1') ?> Skoru</label>
                                        <input type="number" name="score1" class="form-input" value="<?= $mac['score1'] ?>" min="0">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" style="font-size:0.8rem; font-weight:700;"><?= temizle($mac['takim2_adi'] ?: 'Takım 2') ?> Skoru</label>
                                        <input type="number" name="score2" class="form-input" value="<?= $mac['score2'] ?>" min="0">
                                    </div>
                                </div>
                                
                                <div class="form-group" style="margin-bottom:var(--space-md);">
                                    <label class="form-label">Maç Durumu</label>
                                    <select name="status" class="form-input" required>
                                        <option value="scheduled" <?= $mac['status']==='scheduled'?'selected':'' ?>>Planlandı (Scheduled)</option>
                                        <option value="live" <?= $mac['status']==='live'?'selected':'' ?>>Canlı (Live)</option>
                                        <option value="completed" <?= $mac['status']==='completed'?'selected':'' ?>>Tamamlandı (Completed)</option>
                                        <option value="cancelled" <?= $mac['status']==='cancelled'?'selected':'' ?>>İptal (Cancelled)</option>
                                        <option value="postponed" <?= $mac['status']==='postponed'?'selected':'' ?>>Ertelendi (Postponed)</option>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="margin-bottom:var(--space-md);">
                                    <label class="form-label">Maç Kazananı</label>
                                    <select name="winner_id" class="form-input">
                                        <option value="">Seçilmemiş</option>
                                        <?php if ($mac['takim1_id']): ?>
                                            <option value="<?= $mac['takim1_id'] ?>" <?= $mac['winner_id']==$mac['takim1_id']?'selected':'' ?>><?= temizle($mac['takim1_adi']) ?></option>
                                        <?php endif; ?>
                                        <?php if ($mac['takim2_id']): ?>
                                            <option value="<?= $mac['takim2_id'] ?>" <?= $mac['winner_id']==$mac['takim2_id']?'selected':'' ?>><?= temizle($mac['takim2_adi']) ?></option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="form-group" style="margin-bottom:var(--space-md);">
                                    <label class="form-label">Aktif Harita Adı</label>
                                    <input type="text" name="map_name" class="form-input" placeholder="örn: Mirage, Bind, dust_2" value="<?= temizle($mac['map_name']) ?>">
                                </div>

                                <div class="form-group" style="margin-bottom:var(--space-md);">
                                    <label class="form-label">Yayın Adresi (Twitch/YouTube URL)</label>
                                    <input type="url" name="stream_url" class="form-input" placeholder="https://twitch.tv/..." value="<?= temizle($mac['stream_url']) ?>">
                                </div>

                                <div style="display:grid; grid-template-columns:1fr 1.5fr; gap:var(--space-md); margin-bottom:var(--space-lg);">
                                    <div class="form-group">
                                        <label class="form-label">Seri Formatı</label>
                                        <select name="best_of" class="form-input">
                                            <option value="1" <?= $mac['best_of']==1?'selected':'' ?>>BO1</option>
                                            <option value="3" <?= $mac['best_of']==3?'selected':'' ?>>BO3</option>
                                            <option value="5" <?= $mac['best_of']==5?'selected':'' ?>>BO5</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Planlanan Zaman</label>
                                        <input type="datetime-local" name="scheduled_at" class="form-input" value="<?= $mac['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($mac['scheduled_at'])) : '' ?>">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-save"></i> Değişiklikleri Kaydet</button>
                            </form>
                        </div>

                        <!-- BO3 / BO5 Harita Yönetimi -->
                        <?php if ($mac['best_of'] > 1): ?>
                            <div class="live-console-card">
                                <h3 style="font-size:1.1rem; margin-bottom:var(--space-md); border-bottom:1px solid var(--border-light); padding-bottom:var(--space-xs); color:var(--text-white);">
                                    <i class="fas fa-map"></i> Seri Harita Detayları
                                </h3>

                                <div style="margin-bottom:var(--space-lg);">
                                    <table class="tablo" style="width:100%; font-size:0.85rem;">
                                        <thead>
                                            <tr>
                                                <th style="padding:8px;">#</th>
                                                <th style="padding:8px;">Harita</th>
                                                <th style="padding:8px; text-align:center;">Skor</th>
                                                <th style="padding:8px; text-align:center;">Aksiyon</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($map_rounds)): ?>
                                                <tr>
                                                    <td colspan="4" style="text-align:center; color:var(--text-muted); padding:10px;">Henüz harita bilgisi eklenmemiş.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($map_rounds as $mr): ?>
                                                    <tr>
                                                        <td style="padding:8px; font-weight:700;">#<?= $mr['round_number'] ?></td>
                                                        <td style="padding:8px; font-weight:600;"><?= temizle($mr['map_name']) ?></td>
                                                        <td style="padding:8px; text-align:center; font-weight:700;"><?= $mr['team1_score'] ?> - <?= $mr['team2_score'] ?></td>
                                                        <td style="padding:8px; text-align:center;">
                                                            <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Bu harita kaydını silmek istediğinize emin misiniz?')">
                                                                <?= csrfInput() ?>
                                                                <input type="hidden" name="aksiyon" value="harita_sil">
                                                                <input type="hidden" name="round_id" value="<?= $mr['id'] ?>">
                                                                <button type="submit" class="btn btn-ghost btn-sm" style="padding:4px 8px; border:none; color:var(--danger);"><i class="fas fa-trash-alt"></i></button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <form method="POST" style="border-top:1px solid var(--border-light); padding-top:var(--space-md);">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="aksiyon" value="harita_guncelle">
                                    
                                    <div style="font-weight:700; font-size:0.85rem; margin-bottom:var(--space-sm); color:var(--text-white);">Yeni Harita Ekle / Güncelle</div>
                                    
                                    <div style="display:grid; grid-template-columns:1fr 2fr; gap:var(--space-sm); margin-bottom:var(--space-sm);">
                                        <div class="form-group">
                                            <select name="round_number" class="form-input" style="padding:8px;" required>
                                                <?php for($i = 1; $i <= $mac['best_of']; $i++): ?>
                                                    <option value="<?= $i ?>">Harita #<?= $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <input type="text" name="round_map_name" class="form-input" style="padding:8px;" placeholder="Harita Adı (örn: Mirage)" required>
                                        </div>
                                    </div>

                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-sm); margin-bottom:var(--space-sm);">
                                        <div class="form-group">
                                            <input type="number" name="team1_score" class="form-input" style="padding:8px; text-align:center;" placeholder="<?= temizle(substr($mac['takim1_adi'] ?: 'T1', 0, 8)) ?> Skoru" min="0" required>
                                        </div>
                                        <div class="form-group">
                                            <input type="number" name="team2_score" class="form-input" style="padding:8px; text-align:center;" placeholder="<?= temizle(substr($mac['takim2_adi'] ?: 'T2', 0, 8)) ?> Skoru" min="0" required>
                                        </div>
                                    </div>

                                    <div class="form-group" style="margin-bottom:var(--space-md);">
                                        <select name="round_winner_id" class="form-input" style="padding:8px;">
                                            <option value="">Kazanan Takım Seçin</option>
                                            <?php if ($mac['takim1_id']): ?>
                                                <option value="<?= $mac['takim1_id'] ?>"><?= temizle($mac['takim1_adi']) ?></option>
                                            <?php endif; ?>
                                            <?php if ($mac['takim2_id']): ?>
                                                <option value="<?= $mac['takim2_id'] ?>"><?= temizle($mac['takim2_adi']) ?></option>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-ghost btn-sm btn-block" style="border-color:var(--accent); color:var(--accent); font-weight:600;"><i class="fas fa-plus"></i> Harita Kaydını Kaydet</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- SAĞ KOLON: Canlı Olay Günlüğü ve Konsol Olay Girişleri -->
                    <div>
                        <!-- Canlı Olay Ekleme Paneli -->
                        <div class="live-console-card">
                            <h3 style="font-size:1.1rem; margin-bottom:var(--space-md); border-bottom:1px solid var(--border-light); padding-bottom:var(--space-xs); color:var(--text-white);">
                                <i class="fas fa-keyboard"></i> Canlı Anlatım Girişi
                            </h3>

                            <!-- Hızlı Taslak Olay Şablon Butonları -->
                            <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:var(--space-md);">
                                <button type="button" class="btn btn-ghost btn-sm" style="padding:4px 8px; font-size:0.75rem; border-color:var(--border-light);" onclick="sablonDoldur('match_start', 'Maç başladı! Her iki takıma da başarılar dileriz.', 0)">🎬 Başlangıç</button>
                                <button type="button" class="btn btn-ghost btn-sm" style="padding:4px 8px; font-size:0.75rem; border-color:var(--border-light);" onclick="sablonDoldur('round_win', ' harita / raunt galibiyeti aldı!', 1)">⚔️ T1 Raund</button>
                                <button type="button" class="btn btn-ghost btn-sm" style="padding:4px 8px; font-size:0.75rem; border-color:var(--border-light);" onclick="sablonDoldur('round_win', ' harita / raunt galibiyeti aldı!', 2)">⚔️ T2 Raund</button>
                                <button type="button" class="btn btn-ghost btn-sm" style="padding:4px 8px; font-size:0.75rem; border-color:var(--border-light);" onclick="sablonDoldur('pause', 'Taktiksel mola alındı.', 0)">⏱️ Mola</button>
                                <button type="button" class="btn btn-ghost btn-sm" style="padding:4px 8px; font-size:0.75rem; border-color:var(--border-light);" onclick="sablonDoldur('resume', 'Mola bitti, maç devam ediyor.', 0)">▶️ Devam</button>
                                <button type="button" class="btn btn-ghost btn-sm" style="padding:4px 8px; font-size:0.75rem; border-color:var(--border-light);" onclick="sablonDoldur('match_end', 'Maç sona erdi! Kazanan: ', 0)">🏆 Bitiş</button>
                            </div>

                            <form method="POST" id="olayForm">
                                <?= csrfInput() ?>
                                <input type="hidden" name="aksiyon" value="olay_ekle">
                                
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md); margin-bottom:var(--space-md);">
                                    <div class="form-group">
                                        <label class="form-label">Olay Tipi</label>
                                        <select name="event_type" id="event_type" class="form-input" required>
                                            <option value="info">Genel Bilgi (Info)</option>
                                            <option value="match_start">Maç Başladı (Match Start)</option>
                                            <option value="round_win">Raunt / Harita Galibiyeti (Round Win)</option>
                                            <option value="kill">Skor / Önemli Skor (Kill)</option>
                                            <option value="pause">Mola (Pause)</option>
                                            <option value="resume">Devam Ediyor (Resume)</option>
                                            <option value="timeout">Zaman Aşımı / Teknik Mola (Timeout)</option>
                                            <option value="match_end">Maç Bitti (Match End)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">İlgili Takım</label>
                                        <select name="team_id" id="team_id" class="form-input">
                                            <option value="">Genel / Yok</option>
                                            <?php if ($mac['takim1_id']): ?>
                                                <option value="<?= $mac['takim1_id'] ?>"><?= temizle($mac['takim1_adi']) ?></option>
                                            <?php endif; ?>
                                            <?php if ($mac['takim2_id']): ?>
                                                <option value="<?= $mac['takim2_id'] ?>"><?= temizle($mac['takim2_adi']) ?></option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group" style="margin-bottom:var(--space-lg);">
                                    <label class="form-label">Olay Açıklama Metni</label>
                                    <textarea name="message" id="message" class="form-input" style="height:80px; resize:none;" placeholder="Örn: shadowhunter harika bir clutch ile raundu takımına kazandırdı!" required></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-paper-plane"></i> Olayı Yayına Gönder</button>
                            </form>
                        </div>

                        <!-- Son Eklenen Olaylar Listesi (Düzeltme & Silme Amaçlı) -->
                        <div class="live-console-card">
                            <h3 style="font-size:1.1rem; margin-bottom:var(--space-md); border-bottom:1px solid var(--border-light); padding-bottom:var(--space-xs); color:var(--text-white);">
                                <i class="fas fa-history"></i> Son Eklenen Olaylar
                            </h3>

                            <?php if (empty($timeline)): ?>
                                <p style="color:var(--text-muted); font-size:0.85rem; text-align:center;">Henüz olay girilmemiş.</p>
                            <?php else: ?>
                                <div style="display:flex; flex-direction:column; gap:8px; max-height:450px; overflow-y:auto; padding-right:5px;">
                                    <?php foreach ($timeline as $t): ?>
                                        <div style="background:var(--bg-surface); border:1px solid var(--border-light); border-radius:var(--radius-sm); padding:10px; display:flex; justify-content:space-between; align-items:center; gap:10px;">
                                            <div style="font-size:0.85rem;">
                                                <span class="badge" style="font-size:0.65rem; padding:2px 6px; margin-right:5px; vertical-align:middle; background:var(--bg-card); border:1px solid var(--border); color:var(--text-secondary);"><?= htmlspecialchars($t['event_type']) ?></span>
                                                <span style="color:var(--text-primary);"><?= temizle($t['message']) ?></span>
                                                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:3px;"><?= date('H:i:s', strtotime($t['created_at'])) ?></div>
                                            </div>
                                            <form method="POST" style="margin:0;" onsubmit="return confirm('Bu olay metnini silmek istediğinize emin misiniz?')">
                                                <?= csrfInput() ?>
                                                <input type="hidden" name="aksiyon" value="olay_sil">
                                                <input type="hidden" name="olay_id" value="<?= $t['id'] ?>">
                                                <button type="submit" class="btn btn-ghost btn-sm" style="padding:4px 8px; border:none; color:var(--danger);" title="Sil"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<script>
function sablonDoldur(tip, mesaj, takimNo) {
    document.getElementById('event_type').value = tip;
    
    var t1Name = "<?= $mac['takim1_id'] ? temizle($mac['takim1_adi']) : '' ?>";
    var t2Name = "<?= $mac['takim2_id'] ? temizle($mac['takim2_adi']) : '' ?>";
    
    var t1Id = "<?= $mac['takim1_id'] ?? 0 ?>";
    var t2Id = "<?= $mac['takim2_id'] ?? 0 ?>";
    
    var finalMsg = mesaj;
    var teamIdVal = "";
    
    if (takimNo === 1) {
        finalMsg = t1Name + mesaj;
        teamIdVal = t1Id;
    } else if (takimNo === 2) {
        finalMsg = t2Name + mesaj;
        teamIdVal = t2Id;
    }
    
    document.getElementById('team_id').value = teamIdVal;
    document.getElementById('message').value = finalMsg;
    document.getElementById('message').focus();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/alt.php'; ?>
