<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/kimlik.php';
cikisYap();
header('Location: ' . SITE_URL . '/index.php');
exit;
