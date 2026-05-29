<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';
adminGerekli();

// Silme
if (isset($_GET['sil']) && csrfDogrula($_GET['token'] ?? '')) {
    $db->query("DELETE FROM turnuvalar WHERE id = ?", [(int)$_GET['sil']]);
    flashMesaj('Turnuva silindi.', 'success');
    header('Location: ' . SITE_URL . '/yonetim/turnuvalar.php'); exit;
}

$durum = $_GET['durum'] ?? '';
$where = $durum ? "WHERE t.status = ?" : "";
$params = $durum ? [$durum] : [];

$turnuvalar = $db->fetchAll("SELECT t.*, g.name as oyun_adi,
    (SELECT COUNT(*) FROM turnuva_kayitlari WHERE tournament_id = t.id) as kayit_sayisi
    FROM turnuvalar t LEFT JOIN oyunlar g ON t.game_id = g.id $where ORDER BY t.created_at DESC", $params);

$sayfaBasligi = 'Turnuva Yönetimi';
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
                    <h2 style="font-size:1.5rem;"><i class="fas fa-trophy" style="color:var(--accent)"></i> Turnuva Yönetimi (<?= count($turnuvalar) ?>)</h2>
                    <a href="<?= SITE_URL ?>/yonetim/turnuva-duzenle.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Yeni Turnuva
                    </a>
                </div>

                <div style="display:flex;gap:var(--space-sm);margin-bottom:var(--space-lg);flex-wrap:wrap;">
                    <a href="?durum=" class="btn <?= !$durum ? 'btn-primary' : 'btn-ghost' ?> btn-sm">Tümü</a>
                    <a href="?durum=registration" class="btn <?= $durum === 'registration' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">Kayıt Açık</a>
                    <a href="?durum=ongoing" class="btn <?= $durum === 'ongoing' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">Devam Eden</a>
                    <a href="?durum=upcoming" class="btn <?= $durum === 'upcoming' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">Yaklaşan</a>
                    <a href="?durum=completed" class="btn <?= $durum === 'completed' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">Tamamlanan</a>
                </div>

                <div class="tablo-wrapper">
                    <table class="tablo">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Turnuva</th>
                                <th>Oyun</th>
                                <th>Format</th>
                                <th>Takım</th>
                                <th>Ödül</th>
                                <th>Durum</th>
                                <th>Tarih</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($turnuvalar as $t): ?>
                            <tr>
                                <td><?= $t['id'] ?></td>
                                <td class="wrap-text">
                                    <a href="<?= SITE_URL ?>/turnuva.php?id=<?= $t['id'] ?>" style="color:var(--accent);font-weight:600;" target="_blank">
                                        <?= temizle($t['name']) ?>
                                    </a>
                                    <?= $t['is_featured'] ? ' ⭐' : '' ?>
                                </td>
                                <td><?= temizle($t['oyun_adi']) ?></td>
                                <td><?= formatBadge($t['format']) ?></td>
                                <td><?= $t['kayit_sayisi'] ?>/<?= $t['max_teams'] ?></td>
                                <td style="color:var(--gold);font-weight:600;"><?= paraFormatla($t['prize_pool']) ?></td>
                                <td><?= durumBadge($t['status']) ?></td>
                                <td style="color:var(--text-muted);font-size:0.8rem;"><?= tarihFormatla($t['start_date'],'short') ?></td>
                                <td>
                                    <a href="<?= SITE_URL ?>/yonetim/turnuva-duzenle.php?id=<?= $t['id'] ?>" class="btn btn-ghost btn-sm" style="padding:4px 8px;" title="Düzenle">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?= SITE_URL ?>/yonetim/maclar.php?turnuva_id=<?= $t['id'] ?>" class="btn btn-ghost btn-sm" style="padding:4px 8px;" title="Maçlar">
                                        <i class="fas fa-project-diagram"></i>
                                    </a>
                                    <a href="?sil=<?= $t['id'] ?>&token=<?= csrfToken() ?>" class="btn btn-ghost btn-sm" style="padding:4px 8px;color:var(--danger);" onclick="return confirm('Silmek istediğinize emin misiniz?')" title="Sil">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
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
