<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';
adminGerekli();

$tournament_id = (int)($_GET['turnuva_id'] ?? 0);

if (!$tournament_id) {
    $turnuvalar = $db->fetchAll("SELECT t.*, g.name as oyun_adi,
        (SELECT COUNT(*) FROM turnuva_kayitlari WHERE tournament_id = t.id AND status = 'approved') as onayli_takim,
        (SELECT COUNT(*) FROM maclar WHERE tournament_id = t.id) as mac_sayisi
        FROM turnuvalar t LEFT JOIN oyunlar g ON t.game_id = g.id ORDER BY t.created_at DESC");
        
    $sayfaBasligi = 'Maç ve Fikstür Yönetimi';
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
                    
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xl);padding-bottom:var(--space-lg);border-bottom:1px solid var(--border-light)">
                        <h2 style="font-size:1.5rem;"><i class="fas fa-gamepad" style="color:var(--accent)"></i> Fikstür ve Maç Yönetimi</h2>
                    </div>

                    <p style="color:var(--text-secondary);margin-bottom:var(--space-lg);">Fikstür oluşturmak, maç skorlarını girmek ve kazanan takımları belirlemek için lütfen aşağıdan bir turnuva seçin:</p>

                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:var(--space-md);">
                        <?php foreach ($turnuvalar as $t): ?>
                            <div class="kart" style="padding:var(--space-lg);display:flex;flex-direction:column;justify-content:space-between;height:100%;">
                                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:var(--space-sm);">
                                    <span class="badge badge-primary" style="font-size:0.65rem;"><?= temizle($t['oyun_adi']) ?></span>
                                    <?= durumBadge($t['status']) ?>
                                </div>
                                <h3 style="font-size:1.15rem;margin-bottom:var(--space-xs);"><?= temizle($t['name']) ?></h3>
                                <div style="color:var(--text-muted);font-size:0.8rem;margin-bottom:var(--space-lg);flex:1;">
                                    <div style="margin-bottom:4px;"><i class="fas fa-users" style="color:var(--accent);width:16px;"></i> Onaylı Takım: <strong><?= $t['onayli_takim'] ?> / <?= $t['max_teams'] ?></strong></div>
                                    <div><i class="fas fa-sitemap" style="color:var(--accent);width:16px;"></i> Fikstür: <strong><?= $t['mac_sayisi'] > 0 ? $t['mac_sayisi'] . ' Maç Oluşturulmuş' : 'Oluşturulmamış' ?></strong></div>
                                </div>
                                <div>
                                    <a href="?turnuva_id=<?= $t['id'] ?>" class="btn <?= $t['mac_sayisi'] > 0 ? 'btn-primary' : 'btn-ghost' ?> btn-sm btn-block" style="text-align:center;">
                                        <?= $t['mac_sayisi'] > 0 ? '<i class="fas fa-edit"></i> Fikstürü Yönet' : '<i class="fas fa-plus"></i> Fikstür Oluştur' ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
    require_once dirname(__DIR__) . '/includes/alt.php';
    exit;
}

$turnuva = $db->fetch("SELECT t.*, g.name as oyun_adi FROM turnuvalar t LEFT JOIN oyunlar g ON t.game_id = g.id WHERE t.id = ?", [$tournament_id]);
if (!$turnuva) {
    header('Location: ' . SITE_URL . '/yonetim/turnuvalar.php');
    exit;
}

// POST İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula($_POST['csrf_token'] ?? '')) {
    $aksiyon = $_POST['aksiyon'] ?? '';

    // 1. Fikstür / Eşleşme Oluşturma
    if ($aksiyon === 'fikstur_olustur') {
        // Zaten maç var mı kontrol et
        $varMi = $db->count("SELECT COUNT(*) FROM maclar WHERE tournament_id = ?", [$tournament_id]);
        if ($varMi > 0) {
            flashMesaj('Bu turnuva için zaten fikstür oluşturulmuş!', 'error');
        } else {
            // Kayıtlı ve onaylı takımları çek
            $takimlar = $db->fetchAll("SELECT team_id FROM turnuva_kayitlari WHERE tournament_id = ? AND status = 'approved' ORDER BY seed ASC, registered_at ASC", [$tournament_id]);
            $team_count = count($takimlar);

            if ($team_count < 2) {
                flashMesaj('Fikstür oluşturmak için en az 2 onaylı takım kaydı gereklidir.', 'error');
            } else {
                // En yakın 2'nin kuvvetini bul (4, 8, 16, 32...)
                $slots = 2;
                while ($slots < $team_count) {
                    $slots *= 2;
                }

                $team_ids = array_map(fn($t) => $t['team_id'], $takimlar);
                
                // Kalan slotları null ile doldur (byes)
                while (count($team_ids) < $slots) {
                    $team_ids[] = null;
                }

                // Eşleşme turlarını hesapla ve oluştur
                $rounds_count = log($slots, 2);
                $round_matches = $slots / 2;

                // Tüm turları boş şablon olarak insert et (Round 1 hariç)
                // Round 1 maçlarını takım idleri ile doldurarak insert et
                $db->query("START TRANSACTION");
                try {
                    $insert_stmt = "INSERT INTO maclar (tournament_id, round, match_number, team1_id, team2_id, status) VALUES (?, ?, ?, ?, ?, ?)";
                    
                    // Raund 1'i oluştur
                    for ($m = 1; $m <= $round_matches; $m++) {
                        $t1 = $team_ids[2 * $m - 2];
                        $t2 = $team_ids[2 * $m - 1];
                        
                        // Eğer t2 boşsa (bye durumu), t1 hükmen kazanmış sayılır ve Round 2'ye otomatik geçer
                        $status = ($t1 !== null && $t2 === null) ? 'completed' : 'scheduled';
                        $winner = ($status === 'completed') ? $t1 : null;
                        
                        $db->query("INSERT INTO maclar (tournament_id, round, match_number, team1_id, team2_id, winner_id, score1, score2, status) VALUES (?, 1, ?, ?, ?, ?, ?, 0, ?)", 
                            [$tournament_id, $m, $t1, $t2, $winner, ($status === 'completed' ? 1 : 0), $status]);
                    }

                    // Sonraki turları boş (TBD) olarak oluştur
                    $current_round_matches = $round_matches;
                    for ($r = 2; $r <= $rounds_count; $r++) {
                        $current_round_matches /= 2;
                        for ($m = 1; $m <= $current_round_matches; $m++) {
                            $db->query($insert_stmt, [$tournament_id, $r, $m, null, null, 'scheduled']);
                        }
                    }

                    // Hükmen geçen (bye) takımları bir sonraki tura taşı
                    $byeMatches = $db->fetchAll("SELECT * FROM maclar WHERE tournament_id = ? AND round = 1 AND status = 'completed'", [$tournament_id]);
                    foreach ($byeMatches as $bye) {
                        $next_round = 2;
                        $next_match_num = ceil($bye['match_number'] / 2);
                        $col = ($bye['match_number'] % 2 !== 0) ? 'team1_id' : 'team2_id';
                        $db->query("UPDATE maclar SET $col = ? WHERE tournament_id = ? AND round = ? AND match_number = ?", 
                            [$bye['winner_id'], $tournament_id, $next_round, $next_match_num]);
                    }

                    $db->query("COMMIT");
                    flashMesaj('Fikstür başarıyla oluşturuldu!', 'success');
                } catch (Exception $e) {
                    $db->query("ROLLBACK");
                    flashMesaj('Eşleşmeler oluşturulurken bir hata oluştu: ' . $e->getMessage(), 'error');
                }
            }
        }
        header('Location: ' . SITE_URL . '/yonetim/maclar.php?turnuva_id=' . $tournament_id);
        exit;
    }

    // 2. Maç Güncelleme
    if ($aksiyon === 'mac_guncelle') {
        $match_id = (int)$_POST['match_id'];
        $score1 = (int)$_POST['score1'];
        $score2 = (int)$_POST['score2'];
        $status = $_POST['status'];
        $winner_id = $_POST['winner_id'] ? (int)$_POST['winner_id'] : null;

        if ($winner_id === 0) $winner_id = null;

        // Maçı güncelle
        $db->query("UPDATE maclar SET score1=?, score2=?, status=?, winner_id=? WHERE id=? AND tournament_id=?", 
            [$score1, $score2, $status, $winner_id, $match_id, $tournament_id]);

        // Eğer maç tamamlandıysa ve kazanan varsa, kazananı sonraki tura taşı
        if ($status === 'completed' && $winner_id !== null) {
            $mac = $db->fetch("SELECT * FROM maclar WHERE id = ?", [$match_id]);
            if ($mac) {
                $next_round = $mac['round'] + 1;
                $next_match_num = (int)ceil($mac['match_number'] / 2);
                $col = ($mac['match_number'] % 2 !== 0) ? 'team1_id' : 'team2_id';
                
                // Sonraki tur maçının varlığını kontrol et (eğer final maçı değilse)
                $next_mac = $db->fetch("SELECT id FROM maclar WHERE tournament_id = ? AND round = ? AND match_number = ?", 
                    [$tournament_id, $next_round, $next_match_num]);
                
                if ($next_mac) {
                    $db->query("UPDATE maclar SET $col = ? WHERE id = ?", [$winner_id, $next_mac['id']]);
                }
            }
        }

        flashMesaj('Maç başarıyla güncellendi.', 'success');
        header('Location: ' . SITE_URL . '/yonetim/maclar.php?turnuva_id=' . $tournament_id);
        exit;
    }

    // 3. Fikstürü Sıfırlama
    if ($aksiyon === 'fikstur_sifirla') {
        $db->query("DELETE FROM maclar WHERE tournament_id = ?", [$tournament_id]);
        flashMesaj('Turnuva fikstürü başarıyla sıfırlandı.', 'success');
        header('Location: ' . SITE_URL . '/yonetim/maclar.php?turnuva_id=' . $tournament_id);
        exit;
    }
}

// Maçları çek
$maclar = $db->fetchAll("SELECT m.*, t1.name as takim1_adi, t1.logo as takim1_logo, t2.name as takim2_adi, t2.logo as takim2_logo 
    FROM maclar m 
    LEFT JOIN takimlar t1 ON m.team1_id = t1.id 
    LEFT JOIN takimlar t2 ON m.team2_id = t2.id 
    WHERE m.tournament_id = ? 
    ORDER BY m.round ASC, m.match_number ASC", [$tournament_id]);

// Onaylı kayıtlı takımların sayısını al
$onayliTakimSayisi = $db->count("SELECT COUNT(*) FROM turnuva_kayitlari WHERE tournament_id = ? AND status = 'approved'", [$tournament_id]);

$sayfaBasligi = 'Fikstür Yönetimi';
require_once dirname(__DIR__) . '/includes/ust.php';
?>

<style>
    .match-edit-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 15px; margin-bottom: var(--space-md); }
    .match-edit-grid { display: grid; grid-template-columns: 2fr 0.8fr 0.8fr 2fr 2fr 1fr; gap: 12px; align-items: center; }
    @media (max-width: 992px) {
        .match-edit-grid { grid-template-columns: 1fr; gap: 10px; }
        .match-edit-grid > div { text-align: left !important; justify-content: flex-start !important; }
    }
</style>

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
                
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xl);padding-bottom:var(--space-lg);border-bottom:1px solid var(--border-light);flex-wrap:wrap;gap:var(--space-md);">
                    <div>
                        <span style="color:var(--text-secondary);font-size:0.85rem;"><a href="<?= SITE_URL ?>/yonetim/turnuvalar.php" style="color:var(--accent);"><i class="fas fa-arrow-left"></i> Turnuvalara Dön</a></span>
                        <h2 style="font-size:1.5rem;margin-top:5px;"><i class="fas fa-project-diagram" style="color:var(--accent)"></i> <?= temizle($turnuva['name']) ?> — Fikstür</h2>
                    </div>
                    <div style="display:flex;gap:var(--space-sm);">
                        <?php if (empty($maclar)): ?>
                            <form method="POST" style="margin:0;">
                                <?= csrfInput() ?>
                                <input type="hidden" name="aksiyon" value="fikstur_olustur">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sitemap"></i> Fikstür Oluştur (<?= $onayliTakimSayisi ?> Takım)</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="margin:0;" onsubmit="return confirm('Bu işlem TÜM maçları ve girilen skorları silecektir. Emin misiniz?')">
                                <?= csrfInput() ?>
                                <input type="hidden" name="aksiyon" value="fikstur_sifirla">
                                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger); border-color:rgba(255,23,68,0.2);"><i class="fas fa-trash-alt"></i> Fikstürü Sıfırla</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($maclar)): ?>
                    <div class="bos-durum" style="padding:var(--space-3xl) 0;">
                        <i class="fas fa-project-diagram"></i>
                        <h3>Henüz eşleşme oluşturulmadı</h3>
                        <p style="color:var(--text-muted);margin-top:var(--space-xs);">Fikstürü oluşturmak için yukarıdaki "Fikstür Oluştur" butonunu tıklayın. Onaylanmış en az 2 takım gereklidir.</p>
                    </div>
                <?php else: ?>
                    <?php
                    // Rauntlara göre grupla
                    $roundlar = [];
                    foreach ($maclar as $mac) {
                        $roundlar[$mac['round']][] = $mac;
                    }
                    ?>

                    <?php foreach ($roundlar as $round => $roundMaclari): ?>
                        <h3 style="margin: var(--space-xl) 0 var(--space-md); color: var(--accent);"><i class="fas fa-chevron-circle-right"></i> Tur <?= $round ?></h3>
                        
                        <?php foreach ($roundMaclari as $mac): ?>
                            <div class="match-edit-card">
                                <form method="POST" style="margin: 0;">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="aksiyon" value="mac_guncelle">
                                    <input type="hidden" name="match_id" value="<?= $mac['id'] ?>">
                                    
                                    <div class="match-edit-grid">
                                        <!-- Takım 1 -->
                                        <div style="display:flex;align-items:center;gap:10px;font-weight:600;">
                                            <?php if ($mac['team1_id']): ?>
                                                <div class="bracket-takim-logo" style="width:24px;height:24px;border-radius:50%;overflow:hidden;flex-shrink:0;">
                                                    <img src="<?= takimLogoURL($mac['takim1_logo']) ?>" alt="Logo" style="width:100%;height:100%;object-fit:cover;">
                                                </div>
                                                <span><?= temizle($mac['takim1_adi']) ?></span>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted);font-style:italic;">Belli Değil</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Skor 1 -->
                                        <div>
                                            <input type="number" name="score1" class="form-input" style="padding:6px;text-align:center;" value="<?= $mac['score1'] ?? 0 ?>" min="0" <?= !$mac['team1_id'] ? 'disabled' : '' ?>>
                                        </div>

                                        <!-- Skor 2 -->
                                        <div>
                                            <input type="number" name="score2" class="form-input" style="padding:6px;text-align:center;" value="<?= $mac['score2'] ?? 0 ?>" min="0" <?= !$mac['team2_id'] ? 'disabled' : '' ?>>
                                        </div>

                                        <!-- Takım 2 -->
                                        <div style="display:flex;align-items:center;gap:10px;font-weight:600;">
                                            <?php if ($mac['team2_id']): ?>
                                                <div class="bracket-takim-logo" style="width:24px;height:24px;border-radius:50%;overflow:hidden;flex-shrink:0;">
                                                    <img src="<?= takimLogoURL($mac['takim2_logo']) ?>" alt="Logo" style="width:100%;height:100%;object-fit:cover;">
                                                </div>
                                                <span><?= temizle($mac['takim2_adi']) ?></span>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted);font-style:italic;">Belli Değil</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Durum & Kazanan Seçme -->
                                        <div style="display:flex;gap:10px;align-items:center;justify-content:flex-end;">
                                            <select name="status" class="form-input" style="padding:6px;width:auto;" required>
                                                <option value="scheduled" <?= $mac['status']==='scheduled'?'selected':'' ?>>Planlandı</option>
                                                <option value="live" <?= $mac['status']==='live'?'selected':'' ?>>Canlı</option>
                                                <option value="completed" <?= $mac['status']==='completed'?'selected':'' ?>>Tamamlandı</option>
                                                <option value="cancelled" <?= $mac['status']==='cancelled'?'selected':'' ?>>İptal</option>
                                            </select>

                                            <select name="winner_id" class="form-input" style="padding:6px;width:auto;" <?= (!$mac['team1_id'] || !$mac['team2_id']) ? 'disabled' : '' ?>>
                                                <option value="">Kazanan Seçiniz</option>
                                                <?php if ($mac['team1_id']): ?>
                                                    <option value="<?= $mac['team1_id'] ?>" <?= $mac['winner_id']==$mac['team1_id']?'selected':'' ?>><?= temizle($mac['takim1_adi']) ?></option>
                                                <?php endif; ?>
                                                <?php if ($mac['team2_id']): ?>
                                                    <option value="<?= $mac['team2_id'] ?>" <?= $mac['winner_id']==$mac['team2_id']?'selected':'' ?>><?= temizle($mac['takim2_adi']) ?></option>
                                                <?php endif; ?>
                                            </select>

                                            <button type="submit" class="btn btn-primary btn-sm" style="padding:6px 12px;" <?= (!$mac['team1_id'] || !$mac['team2_id']) ? 'disabled' : '' ?>><i class="fas fa-save"></i> Kaydet</button>
                                        </div>
                                        
                                        <!-- Canlı Yönetim Bağlantısı -->
                                        <div style="text-align:right;">
                                            <a href="mac-detay.php?id=<?= $mac['id'] ?>" class="btn btn-ghost btn-sm" style="padding:6px 10px; border-radius:var(--radius-sm); border-color:var(--accent); color:var(--accent); font-weight:600; display:inline-flex; align-items:center; gap:4px; <?= (!$mac['team1_id'] || !$mac['team2_id']) ? 'pointer-events:none; opacity:0.5;' : '' ?>" title="Canlı Yayını ve Olayları Yönet">
                                                <i class="fas fa-cog"></i> Canlı Yönet
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/alt.php'; ?>
