<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';
adminGerekli();

$takimlar = $db->fetchAll("SELECT t.*, g.name as oyun_adi, u.username as kaptan,
    (SELECT COUNT(*) FROM takim_uyeleri WHERE team_id = t.id) as uye_sayisi
    FROM takimlar t LEFT JOIN oyunlar g ON t.game_id = g.id LEFT JOIN kullanicilar u ON t.captain_id = u.id ORDER BY t.points DESC");

$sayfaBasligi = 'Takım Yönetimi';
require_once dirname(__DIR__) . '/includes/ust.php';
?>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <div class="dashboard-grid">
            <!-- Sidebar -->
            <?php 
            $aktifSekme = 'takimlar';
            require_once __DIR__ . '/sidebar.php'; 
            ?>

            <!-- İçerik -->
            <div class="dashboard-icerik">
                <?= flashGoster() ?>
                
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xl);padding-bottom:var(--space-lg);border-bottom:1px solid var(--border-light)">
                    <h2 style="font-size:1.5rem;"><i class="fas fa-shield-halved" style="color:var(--accent)"></i> Takım Yönetimi (<?= count($takimlar) ?>)</h2>
                </div>

                <div class="tablo-wrapper">
                    <table class="tablo">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Takım</th>
                                <th>Oyun</th>
                                <th>Kaptan</th>
                                <th>Üye</th>
                                <th>G/M</th>
                                <th>Puan</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($takimlar as $t): ?>
                            <tr>
                                <td><?= $t['id'] ?></td>
                                <td class="wrap-text">
                                    <a href="<?= SITE_URL ?>/takim.php?id=<?= $t['id'] ?>" style="color:var(--accent);font-weight:600;" target="_blank">
                                        <?= temizle($t['name']) ?>
                                    </a>
                                </td>
                                <td><?= temizle($t['oyun_adi']) ?></td>
                                <td><?= temizle($t['kaptan']) ?></td>
                                <td><?= $t['uye_sayisi'] ?></td>
                                <td>
                                    <span style="color:var(--success);font-weight:600;"><?= $t['total_wins'] ?></span> / 
                                    <span style="color:var(--danger);font-weight:600;"><?= $t['total_losses'] ?></span>
                                </td>
                                <td style="font-weight:700;color:var(--neon);"><?= $t['points'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once dirname(__DIR__) . '/includes/alt.php'; ?>
