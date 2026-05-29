<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/kimlik.php';
require_once dirname(__DIR__) . '/includes/fonksiyonlar.php';

header('Content-Type: application/json; charset=utf-8');

$macId = (int)($_GET['mac_id'] ?? 0);
if (!$macId) { jsonYanit(['error' => 'Maç ID gerekli'], 400); }

$mac = $db->fetch("SELECT m.*, t1.name as takim1_adi, t1.logo as takim1_logo, t2.name as takim2_adi, t2.logo as takim2_logo, tr.name as turnuva_adi
    FROM maclar m LEFT JOIN takimlar t1 ON m.team1_id = t1.id LEFT JOIN takimlar t2 ON m.team2_id = t2.id LEFT JOIN turnuvalar tr ON m.tournament_id = tr.id
    WHERE m.id = ?", [$macId]);

if (!$mac) { jsonYanit(['error' => 'Maç bulunamadı'], 404); }

$guncellemeler = $db->fetchAll("SELECT lu.*, t.name as takim_adi FROM canli_guncellemeler lu LEFT JOIN takimlar t ON lu.team_id = t.id WHERE lu.match_id = ? ORDER BY lu.created_at DESC LIMIT 50", [$macId]);

foreach ($guncellemeler as &$g) { $g['zaman_once'] = zamanOnce($g['created_at']); }

jsonYanit(['mac' => $mac, 'guncellemeler' => $guncellemeler]);
