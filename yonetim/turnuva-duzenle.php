<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';
adminGerekli();

$id = (int)($_GET['id'] ?? 0);
$turnuva = $id ? $db->fetch("SELECT * FROM turnuvalar WHERE id = ?", [$id]) : null;
$oyunlar = $db->fetchAll("SELECT * FROM oyunlar WHERE is_active=1 ORDER BY name");
$allSponsors = $db->fetchAll("SELECT * FROM sponsorlar ORDER BY name");

$linkedSponsorsMap = [];
if ($id) {
    $linkedSponsors = $db->fetchAll("SELECT sponsor_id, prize_contribution FROM turnuva_sponsorlari WHERE tournament_id = ?", [$id]);
    foreach ($linkedSponsors as $ls) {
        $linkedSponsorsMap[$ls['sponsor_id']] = (float)$ls['prize_contribution'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula($_POST['csrf_token'] ?? '')) {
    $data = [
        trim($_POST['name']), slugOlustur(trim($_POST['name'])), (int)$_POST['game_id'],
        trim($_POST['description']), trim($_POST['rules']), $_POST['format'],
        (int)$_POST['team_size'], (int)$_POST['max_teams'], (float)$_POST['prize_pool'],
        (float)$_POST['entry_fee'], $_POST['start_date'], $_POST['end_date'] ?: null,
        $_POST['reg_start'] ?: null, $_POST['reg_end'] ?: null, $_POST['status'],
        isset($_POST['is_featured']) ? 1 : 0, trim($_POST['stream_url'] ?? '')
    ];

    if ($id) {
        $data[] = $id;
        $db->query("UPDATE turnuvalar SET name=?,slug=?,game_id=?,description=?,rules=?,format=?,team_size=?,max_teams=?,prize_pool=?,entry_fee=?,start_date=?,end_date=?,registration_start=?,registration_end=?,status=?,is_featured=?,stream_url=? WHERE id=?", $data);
        
        // Sponsor ilişkilerini güncelle
        $db->query("DELETE FROM turnuva_sponsorlari WHERE tournament_id = ?", [$id]);
        $sponsorsInput = $_POST['sponsors'] ?? [];
        $contributionsInput = $_POST['sponsor_contribution'] ?? [];
        foreach ($sponsorsInput as $sid) {
            $sid = (int)$sid;
            $contribution = (float)($contributionsInput[$sid] ?? 0.0);
            $db->query("INSERT INTO turnuva_sponsorlari (tournament_id, sponsor_id, prize_contribution) VALUES (?, ?, ?)", [$id, $sid, $contribution]);
        }
        
        flashMesaj('Turnuva güncellendi!', 'success');
    } else {
        $data[] = $_SESSION['user_id'];
        $newId = $db->insert("INSERT INTO turnuvalar (name,slug,game_id,description,rules,format,team_size,max_teams,prize_pool,entry_fee,start_date,end_date,registration_start,registration_end,status,is_featured,stream_url,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", $data);
        
        // Sponsor ilişkilerini kaydet
        $sponsorsInput = $_POST['sponsors'] ?? [];
        $contributionsInput = $_POST['sponsor_contribution'] ?? [];
        foreach ($sponsorsInput as $sid) {
            $sid = (int)$sid;
            $contribution = (float)($contributionsInput[$sid] ?? 0.0);
            $db->query("INSERT INTO turnuva_sponsorlari (tournament_id, sponsor_id, prize_contribution) VALUES (?, ?, ?)", [$newId, $sid, $contribution]);
        }
        
        flashMesaj('Turnuva oluşturuldu!', 'success');
    }
    header('Location: ' . SITE_URL . '/yonetim/turnuvalar.php'); exit;
}

$sayfaBasligi = $id ? 'Turnuva Düzenle' : 'Yeni Turnuva';
require_once dirname(__DIR__) . '/includes/ust.php';
?>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <div class="dashboard-grid">
            <!-- Sidebar -->
            <?php 
            $aktifSekme = 'turnuvalar';
            require_once __DIR__ . '/sidebar.php'; 
            ?>

            <!-- İçerik -->
            <div class="dashboard-icerik">
                <?= flashGoster() ?>
                
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xl);padding-bottom:var(--space-lg);border-bottom:1px solid var(--border-light)">
                    <h2 style="font-size:1.5rem;"><i class="fas fa-<?= $id ? 'edit' : 'plus-circle' ?>" style="color:var(--accent)"></i> <?= $id ? 'Turnuva Düzenle' : 'Yeni Turnuva' ?></h2>
                </div>

                <div class="kart" style="padding:var(--space-xl);">
                    <form method="POST">
                        <?= csrfInput() ?>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md);flex-wrap:wrap;">
                            <div class="form-grup">
                                <label class="form-label">Turnuva Adı *</label>
                                <input type="text" name="name" class="form-input" value="<?= temizle($turnuva['name'] ?? '') ?>" required>
                            </div>
                            <div class="form-grup">
                                <label class="form-label">Oyun *</label>
                                <select name="game_id" class="form-input" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($oyunlar as $o): ?>
                                        <option value="<?= $o['id'] ?>" <?= ($turnuva['game_id'] ?? '') == $o['id'] ? 'selected' : '' ?>><?= temizle($o['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-grup">
                                <label class="form-label">Format</label>
                                <select name="format" class="form-input">
                                    <?php foreach (['single_elimination'=>'Tek Eleme','double_elimination'=>'Çift Eleme','round_robin'=>'Lig','swiss'=>'Swiss'] as $k=>$v): ?>
                                        <option value="<?= $k ?>" <?= ($turnuva['format'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-grup">
                                <label class="form-label">Durum</label>
                                <select name="status" class="form-input">
                                    <?php foreach (['draft'=>'Taslak','upcoming'=>'Yaklaşıyor','registration'=>'Kayıt Açık','ongoing'=>'Devam Ediyor','completed'=>'Tamamlandı','cancelled'=>'İptal'] as $k=>$v): ?>
                                        <option value="<?= $k ?>" <?= ($turnuva['status'] ?? 'draft') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-grup">
                                <label class="form-label">Takım Boyutu</label>
                                <input type="number" name="team_size" class="form-input" value="<?= $turnuva['team_size'] ?? 5 ?>" min="1">
                            </div>
                            <div class="form-grup">
                                <label class="form-label">Maks. Takım</label>
                                <input type="number" name="max_teams" class="form-input" value="<?= $turnuva['max_teams'] ?? 16 ?>" min="2">
                            </div>
                            <div class="form-grup">
                                <label class="form-label">Ödül Havuzu (₺)</label>
                                <input type="number" name="prize_pool" class="form-input" value="<?= $turnuva['prize_pool'] ?? 0 ?>" step="0.01">
                            </div>
                            <div class="form-grup">
                                <label class="form-label">Giriş Ücreti (₺)</label>
                                <input type="number" name="entry_fee" class="form-input" value="<?= $turnuva['entry_fee'] ?? 0 ?>" step="0.01">
                            </div>
                            <div class="form-grup">
                                <label class="form-label">Başlangıç Tarihi *</label>
                                <input type="datetime-local" name="start_date" class="form-input" value="<?= $turnuva ? date('Y-m-d\TH:i', strtotime($turnuva['start_date'])) : '' ?>" required>
                            </div>
                            <div class="form-grup">
                                <label class="form-label">Bitiş Tarihi</label>
                                <input type="datetime-local" name="end_date" class="form-input" value="<?= $turnuva && $turnuva['end_date'] ? date('Y-m-d\TH:i', strtotime($turnuva['end_date'])) : '' ?>">
                            </div>
                            <div class="form-grup">
                                <label class="form-label">Kayıt Başlangıcı</label>
                                <input type="datetime-local" name="reg_start" class="form-input" value="<?= $turnuva && $turnuva['registration_start'] ? date('Y-m-d\TH:i', strtotime($turnuva['registration_start'])) : '' ?>">
                            </div>
                            <div class="form-grup">
                                <label class="form-label">Kayıt Bitişi</label>
                                <input type="datetime-local" name="reg_end" class="form-input" value="<?= $turnuva && $turnuva['registration_end'] ? date('Y-m-d\TH:i', strtotime($turnuva['registration_end'])) : '' ?>">
                            </div>
                        </div>
                        <div class="form-grup">
                            <label class="form-label">Açıklama</label>
                            <textarea name="description" class="form-input" rows="4"><?= temizle($turnuva['description'] ?? '') ?></textarea>
                        </div>
                        <div class="form-grup">
                            <label class="form-label">Kurallar</label>
                            <textarea name="rules" class="form-input" rows="4"><?= temizle($turnuva['rules'] ?? '') ?></textarea>
                        </div>
                        <div class="form-grup">
                            <label class="form-label">Yayın URL</label>
                            <input type="url" name="stream_url" class="form-input" value="<?= temizle($turnuva['stream_url'] ?? '') ?>">
                        </div>

                        <!-- Turnuva Sponsorları Seçimi -->
                        <div class="form-grup" style="grid-column: 1 / -1; margin-top:var(--space-md); border-top:1px solid var(--border-light); padding-top:var(--space-md);">
                            <label class="form-label" style="font-size:1.1rem; color:var(--accent); margin-bottom:var(--space-md); display:flex; align-items:center; gap:8px;"><i class="fas fa-handshake"></i> Turnuva Sponsorları</label>
                            
                            <?php if (empty($allSponsors)): ?>
                                <p style="color:var(--text-muted); font-size:0.85rem; margin:0;">Sistemde kayıtlı sponsor bulunmamaktadır. <a href="<?= SITE_URL ?>/yonetim/sponsorlar.php" style="color:var(--accent); font-weight:600; text-decoration:none;"><i class="fas fa-plus-circle"></i> Sponsor Yönetimi</a> sayfasından yeni sponsorlar ekleyebilirsiniz.</p>
                            <?php else: ?>
                                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:var(--space-md);">
                                    <?php foreach ($allSponsors as $s): ?>
                                        <?php 
                                        $isSelected = isset($linkedSponsorsMap[$s['id']]);
                                        $contribution = $isSelected ? $linkedSponsorsMap[$s['id']] : 0;
                                        ?>
                                        <div class="kart" style="padding:var(--space-sm) var(--space-md); display:flex; align-items:center; gap:var(--space-sm); border:1px solid <?= $isSelected ? 'rgba(var(--accent-rgb), 0.3)' : 'var(--border-light)' ?>; background:<?= $isSelected ? 'rgba(var(--accent-rgb), 0.02)' : 'rgba(255,255,255,0.01)' ?>; margin:0; transition:all var(--transition-fast);">
                                            <input type="checkbox" name="sponsors[]" value="<?= $s['id'] ?>" id="sp_<?= $s['id'] ?>" <?= $isSelected ? 'checked' : '' ?> style="accent-color:var(--accent); width:18px; height:18px; cursor:pointer;" onchange="this.closest('.kart').style.borderColor = this.checked ? 'rgba(var(--accent-rgb), 0.3)' : 'var(--border-light)';" />
                                            
                                            <img src="<?= UPLOADS_URL . '/sponsors/' . $s['logo'] ?>" style="width:40px; height:24px; object-fit:contain; border-radius:2px; background:rgba(255,255,255,0.05); padding:2px; border:1px solid var(--border-light);">
                                            
                                            <div style="flex:1; min-width:0;">
                                                <label for="sp_<?= $s['id'] ?>" style="font-weight:600; font-size:0.85rem; cursor:pointer; display:block;"><?= temizle($s['name']) ?></label>
                                                <input type="number" name="sponsor_contribution[<?= $s['id'] ?>]" class="form-input" value="<?= $contribution ?>" placeholder="Ödül Katkısı (₺)" style="height:32px; font-size:0.8rem; padding:4px 8px; margin-top:4px;" min="0" step="0.01">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-grup">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;user-select:none;">
                                <input type="checkbox" name="is_featured" <?= ($turnuva['is_featured'] ?? 0) ? 'checked' : '' ?> style="accent-color:var(--accent);"> Öne Çıkan Turnuva
                            </label>
                        </div>
                        <div style="display:flex;gap:var(--space-md);margin-top:var(--space-lg);">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $id ? 'Güncelle' : 'Oluştur' ?></button>
                            <a href="<?= SITE_URL ?>/yonetim/turnuvalar.php" class="btn btn-ghost">İptal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/alt.php'; ?>
