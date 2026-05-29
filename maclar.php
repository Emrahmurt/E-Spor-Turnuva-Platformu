<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/kimlik.php';
require_once __DIR__ . '/includes/fonksiyonlar.php';

$sayfaBasligi = 'Maçlar';
require_once __DIR__ . '/includes/ust.php';

// Canlı Maçlar
$canliMaclar = $db->fetchAll("SELECT m.*, t1.name as takim1_adi, t1.logo as takim1_logo, t2.name as takim2_adi, t2.logo as takim2_logo, tr.name as turnuva_adi 
    FROM maclar m 
    LEFT JOIN takimlar t1 ON m.team1_id = t1.id 
    LEFT JOIN takimlar t2 ON m.team2_id = t2.id 
    LEFT JOIN turnuvalar tr ON m.tournament_id = tr.id 
    WHERE m.status = 'live' 
    ORDER BY m.scheduled_at ASC");

// Gelecek Maçlar
$gelecekMaclar = $db->fetchAll("SELECT m.*, t1.name as takim1_adi, t1.logo as takim1_logo, t2.name as takim2_adi, t2.logo as takim2_logo, tr.name as turnuva_adi 
    FROM maclar m 
    LEFT JOIN takimlar t1 ON m.team1_id = t1.id 
    LEFT JOIN takimlar t2 ON m.team2_id = t2.id 
    LEFT JOIN turnuvalar tr ON m.tournament_id = tr.id 
    WHERE m.status = 'scheduled' AND m.team1_id IS NOT NULL AND m.team2_id IS NOT NULL
    ORDER BY m.scheduled_at ASC LIMIT 10");

// Tamamlanan Maçlar
$tamamlananMaclar = $db->fetchAll("SELECT m.*, t1.name as takim1_adi, t1.logo as takim1_logo, t2.name as takim2_adi, t2.logo as takim2_logo, tr.name as turnuva_adi 
    FROM maclar m 
    LEFT JOIN takimlar t1 ON m.team1_id = t1.id 
    LEFT JOIN takimlar t2 ON m.team2_id = t2.id 
    LEFT JOIN turnuvalar tr ON m.tournament_id = tr.id 
    WHERE m.status = 'completed' 
    ORDER BY m.id DESC LIMIT 10");

?>

<!-- Kahraman Alanı -->
<section class="kahraman-alani" style="padding-top: var(--space-3xl); padding-bottom: var(--space-2xl);">
    <div class="container" style="text-align: center;">
        <h1 class="baslik">E-Spor <span class="vurgu">Maçları</span></h1>
        <p class="aciklama" style="max-width: 600px; margin: 0 auto;">Canlı yayınlanan, yaklaşan ve tamamlanan tüm e-spor maçlarını buradan takip edebilirsiniz.</p>
    </div>
</section>

<!-- Maçlar Bölümü -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        
        <!-- Canlı Maçlar -->
        <?php if (!empty($canliMaclar)): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-xl);">
                <h2 style="display:flex;align-items:center;gap:10px;">
                    <i class="fas fa-broadcast-tower" style="color:var(--live);"></i> Canlı Maçlar
                    <span class="badge badge-live">CANLI</span>
                </h2>
            </div>
            <div class="mac-grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(350px, 1fr));gap:var(--space-xl);margin-bottom:var(--space-3xl);">
                <?php foreach ($canliMaclar as $mac): ?>
                    <div class="canli-mac-kart" style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-md); transition:var(--transition-fast);">
                        <div style="padding:15px;text-align:center;border-bottom:1px solid var(--border-light);background:var(--bg-surface);">
                            <div style="font-size:0.8rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:5px;"><?= temizle($mac['turnuva_adi']) ?></div>
                            <div style="font-size:0.75rem;color:var(--live);font-weight:700;"><i class="fas fa-circle" style="font-size:8px;vertical-align:middle;margin-right:4px;"></i> CANLI</div>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:25px;">
                            <div style="text-align:center;flex:1;">
                                <div class="canli-mac-takim-logo" style="width:64px;height:64px;margin:0 auto 10px;border-radius:50%;background:var(--bg-surface);border:1px solid var(--border);padding:5px;">
                                    <img src="<?= takimLogoURL($mac['takim1_logo']) ?>" alt="Logo" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                                </div>
                                <div style="font-weight:700;font-size:0.95rem;"><?= temizle($mac['takim1_adi']) ?></div>
                            </div>
                            <div style="text-align:center;padding:0 15px;">
                                <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:5px;">
                                    <span class="canli-mac-skor-sayi" style="font-size:2rem;font-weight:800;color:var(--text-white);"><?= $mac['score1'] ?></span>
                                    <span style="color:var(--text-muted);font-size:1.2rem;">-</span>
                                    <span class="canli-mac-skor-sayi" style="font-size:2rem;font-weight:800;color:var(--text-white);"><?= $mac['score2'] ?></span>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-muted);">BO<?= $mac['best_of'] ?></div>
                            </div>
                            <div style="text-align:center;flex:1;">
                                <div class="canli-mac-takim-logo" style="width:64px;height:64px;margin:0 auto 10px;border-radius:50%;background:var(--bg-surface);border:1px solid var(--border);padding:5px;">
                                    <img src="<?= takimLogoURL($mac['takim2_logo']) ?>" alt="Logo" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                                </div>
                                <div style="font-weight:700;font-size:0.95rem;"><?= temizle($mac['takim2_adi']) ?></div>
                            </div>
                        </div>
                        <div style="padding:15px;border-top:1px solid var(--border-light);display:flex;gap:var(--space-sm);">
                            <a href="<?= SITE_URL ?>/mac.php?id=<?= $mac['id'] ?>" class="btn btn-ghost btn-sm" style="flex:1; border-radius:var(--radius-sm);"><i class="fas fa-eye"></i> Detaylar</a>
                            <?php if ($mac['stream_url']): ?>
                                <a href="<?= SITE_URL ?>/mac.php?id=<?= $mac['id'] ?>&tab=yayin" class="btn btn-primary btn-sm" style="flex:1; border-radius:var(--radius-sm);"><i class="fas fa-play"></i> Yayını İzle</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-2xl);">
            <!-- Yaklaşan Maçlar -->
            <div>
                <h3 style="margin-bottom:var(--space-lg);display:flex;align-items:center;gap:10px;">
                    <i class="far fa-clock" style="color:var(--accent);"></i> Yaklaşan Maçlar
                </h3>
                
                <?php if (empty($gelecekMaclar)): ?>
                    <div class="bos-durum">
                        <i class="far fa-calendar-alt"></i>
                        <p>Yaklaşan maç bulunmuyor.</p>
                    </div>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:15px;">
                        <?php foreach ($gelecekMaclar as $mac): ?>
                            <a href="<?= SITE_URL ?>/mac.php?id=<?= $mac['id'] ?>" class="mac-satir-link" style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);padding:15px;display:flex;align-items:center;transition:var(--transition-fast);text-decoration:none;color:inherit;">
                                <div style="width:80px;text-align:center;padding-right:15px;border-right:1px solid var(--border-light); flex-shrink:0;">
                                    <div style="font-size:0.8rem;color:var(--text-secondary);"><?= date('d M', strtotime($mac['scheduled_at'] ?: date('Y-m-d H:i:s'))) ?></div>
                                    <div style="font-size:1.1rem;font-weight:700;color:var(--text-primary);"><?= date('H:i', strtotime($mac['scheduled_at'] ?: date('Y-m-d H:i:s'))) ?></div>
                                </div>
                                <div style="flex:1;padding-left:15px;display:flex;justify-content:space-between;align-items:center;">
                                    <div style="display:flex;align-items:center;gap:10px;flex:1;">
                                        <img src="<?= takimLogoURL($mac['takim1_logo']) ?>" style="width:24px;height:24px;border-radius:50%;">
                                        <span style="font-weight:600;"><?= temizle($mac['takim1_adi']) ?></span>
                                    </div>
                                    <div style="color:var(--text-muted);font-size:0.8rem;font-weight:700;padding:0 15px;">VS</div>
                                    <div style="display:flex;align-items:center;gap:10px;flex:1;justify-content:flex-end;">
                                        <span style="font-weight:600;"><?= temizle($mac['takim2_adi']) ?></span>
                                        <img src="<?= takimLogoURL($mac['takim2_logo']) ?>" style="width:24px;height:24px;border-radius:50%;">
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tamamlanan Maçlar -->
            <div>
                <h3 style="margin-bottom:var(--space-lg);display:flex;align-items:center;gap:10px;">
                    <i class="fas fa-check-circle" style="color:var(--success);"></i> Sonuçlar
                </h3>
                
                <?php if (empty($tamamlananMaclar)): ?>
                    <div class="bos-durum">
                        <i class="fas fa-history"></i>
                        <p>Henüz tamamlanmış maç bulunmuyor.</p>
                    </div>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:15px;">
                        <?php foreach ($tamamlananMaclar as $mac): ?>
                            <a href="<?= SITE_URL ?>/mac.php?id=<?= $mac['id'] ?>" class="mac-satir-link" style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);padding:15px;display:flex;align-items:center;transition:var(--transition-fast);text-decoration:none;color:inherit;">
                                <div style="flex:1;display:flex;justify-content:flex-end;align-items:center;gap:10px;">
                                    <span style="font-weight:600; <?= $mac['winner_id'] == $mac['team1_id'] ? 'color:var(--text-primary);' : 'color:var(--text-muted);' ?>"><?= temizle($mac['takim1_adi'] ?: 'TBD') ?></span>
                                    <img src="<?= takimLogoURL($mac['takim1_logo']) ?>" style="width:24px;height:24px;border-radius:50%; opacity: <?= $mac['winner_id'] == $mac['team1_id'] ? '1' : '0.5' ?>;">
                                </div>
                                <div style="padding:0 20px;text-align:center;">
                                    <div style="background:var(--bg-surface);border:1px solid var(--border-light);padding:4px 12px;border-radius:20px;font-weight:700;letter-spacing:2px;font-size:0.9rem;">
                                        <span style="<?= $mac['winner_id'] == $mac['team1_id'] ? 'color:var(--success);' : '' ?>"><?= $mac['score1'] ?></span>
                                        <span style="color:var(--text-muted);">:</span>
                                        <span style="<?= $mac['winner_id'] == $mac['team2_id'] ? 'color:var(--success);' : '' ?>"><?= $mac['score2'] ?></span>
                                    </div>
                                </div>
                                <div style="flex:1;display:flex;align-items:center;gap:10px;">
                                    <img src="<?= takimLogoURL($mac['takim2_logo']) ?>" style="width:24px;height:24px;border-radius:50%; opacity: <?= $mac['winner_id'] == $mac['team2_id'] ? '1' : '0.5' ?>;">
                                    <span style="font-weight:600; <?= $mac['winner_id'] == $mac['team2_id'] ? 'color:var(--text-primary);' : 'color:var(--text-muted);' ?>"><?= temizle($mac['takim2_adi'] ?: 'TBD') ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
