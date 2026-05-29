<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { jsonYanit(['error' => 'En az 2 karakter giriniz'], 400); }

$arama = "%$q%";

$turnuvalar = $db->fetchAll("SELECT id, name, status FROM turnuvalar WHERE name LIKE ? LIMIT 5", [$arama]);
$takimlar = $db->fetchAll("SELECT id, name FROM takimlar WHERE name LIKE ? LIMIT 5", [$arama]);
$oyuncular = $db->fetchAll("SELECT id, username, first_name, last_name FROM kullanicilar WHERE (username LIKE ? OR first_name LIKE ? OR last_name LIKE ?) AND is_active=1 LIMIT 5", [$arama, $arama, $arama]);
$haberler = $db->fetchAll("SELECT id, title FROM haberler WHERE title LIKE ? AND is_published=1 LIMIT 5", [$arama]);

jsonYanit([
    'turnuvalar' => $turnuvalar,
    'takimlar' => $takimlar,
    'oyuncular' => $oyuncular,
    'haberler' => $haberler,
    'toplam' => count($turnuvalar) + count($takimlar) + count($oyuncular) + count($haberler)
]);
