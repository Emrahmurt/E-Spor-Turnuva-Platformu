<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';

header('Content-Type: application/json; charset=utf-8');

if (!girisYapmisMi()) { jsonYanit(['error' => 'Giriş yapmalısınız'], 401); }

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksiyon = $_POST['action'] ?? '';
    $bildirimId = (int)($_POST['id'] ?? 0);
    
    if ($aksiyon === 'okundu') {
        if ($bildirimId) {
            $db->query("UPDATE bildirimler SET is_read = 1 WHERE id = ? AND user_id = ?", [$bildirimId, $userId]);
        } else {
            $db->query("UPDATE bildirimler SET is_read = 1 WHERE user_id = ?", [$userId]);
        }
        jsonYanit(['success' => true]);
    } elseif ($aksiyon === 'okunmamis') {
        if ($bildirimId) {
            $db->query("UPDATE bildirimler SET is_read = 0 WHERE id = ? AND user_id = ?", [$bildirimId, $userId]);
        } else {
            $db->query("UPDATE bildirimler SET is_read = 0 WHERE user_id = ?", [$userId]);
        }
        jsonYanit(['success' => true]);
    } elseif ($aksiyon === 'sil') {
        if ($bildirimId) {
            $db->query("DELETE FROM bildirimler WHERE id = ? AND user_id = ?", [$bildirimId, $userId]);
        } else {
            $db->query("DELETE FROM bildirimler WHERE user_id = ?", [$userId]);
        }
        jsonYanit(['success' => true]);
    }
}

$filtre = $_GET['filtre'] ?? 'all';
$limit = min(50, max(1, (int)($_GET['limit'] ?? ($_GET['son'] ?? 10))));
$sayfa = max(1, (int)($_GET['sayfa'] ?? 1));
$offset = ($sayfa - 1) * $limit;

$whereClause = "WHERE user_id = ?";
$params = [$userId];

if ($filtre === 'unread') {
    $whereClause .= " AND is_read = 0";
} elseif ($filtre === 'read') {
    $whereClause .= " AND is_read = 1";
}

$toplamKayit = (int)$db->count("SELECT COUNT(*) FROM bildirimler $whereClause", $params);
$toplamSayfa = (int)ceil($toplamKayit / $limit);

$bildirimler = $db->fetchAll("SELECT * FROM bildirimler $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset", $params);

foreach ($bildirimler as &$b) {
    $b['zaman_once'] = zamanOnce($b['created_at']);
}

$okunmamisSayisi = (int)$db->count("SELECT COUNT(*) FROM bildirimler WHERE user_id = ? AND is_read = 0", [$userId]);

jsonYanit([
    'bildirimler' => $bildirimler,
    'okunmamis' => $okunmamisSayisi,
    'toplam_kayit' => $toplamKayit,
    'toplam_sayfa' => $toplamSayfa,
    'mevcut_sayfa' => $sayfa,
    'limit' => $limit
]);

