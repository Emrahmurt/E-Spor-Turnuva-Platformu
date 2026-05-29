<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/kimlik.php';
require_once __DIR__ . '/includes/fonksiyonlar.php';
require_once __DIR__ . '/includes/eposta.php';
girisGerekli();

$kullanici = mevcutKullanici();
$tab = $_GET['tab'] ?? 'genel';
$sayfaBasligi = 'Panelim';

// If admin is editing another user
$targetUserId = (int)($_GET['id'] ?? 0);
if ($tab === 'profil' && $targetUserId && $targetUserId !== (int)($_SESSION['user_id'] ?? 0)) {
    adminGerekli(); // Ensure only admins can edit other users
    $targetUser = $db->fetch("SELECT * FROM kullanicilar WHERE id = ?", [$targetUserId]);
    if ($targetUser) {
        $kullanici = $targetUser; // Override $kullanici with target user
        $sayfaBasligi = $kullanici['username'] . ' - Profili Düzenle';
        $tab = 'profil'; // Only allow 'profil' tab when editing someone else
    }
}

// POST İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfDogrula($_POST['csrf_token'] ?? '')) {
    $aksiyon = $_POST['aksiyon'] ?? '';
    
    if ($aksiyon === 'profil_guncelle') {
        // Handle avatar upload
        $avatarFilename = $kullanici['avatar'];
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = dosyaYukle($_FILES['avatar'], 'avatars', MAX_AVATAR_SIZE);
            if ($upload['success']) {
                // Delete old avatar if not default and not system avatar
                if ($kullanici['avatar'] && $kullanici['avatar'] !== 'default-avatar.png' && strpos($kullanici['avatar'], 'avatar_') !== 0) {
                    $oldFile = UPLOADS_PATH . 'avatars' . DIRECTORY_SEPARATOR . $kullanici['avatar'];
                    if (file_exists($oldFile)) { @unlink($oldFile); }
                }
                $avatarFilename = $upload['filename'];
                if ($kullanici['id'] == $_SESSION['user_id']) {
                    $_SESSION['avatar'] = $avatarFilename;
                }
            } else {
                flashMesaj('Profil resmi yüklenemedi: ' . $upload['error'], 'error');
                $redirectUrl = SITE_URL . '/panel.php?tab=profil';
                if ($tab === 'profil' && isset($_GET['id'])) { $redirectUrl .= '&id=' . (int)$_GET['id']; }
                header('Location: ' . $redirectUrl); exit;
            }
        }

        $db->query("UPDATE kullanicilar SET first_name=?, last_name=?, bio=?, country=?, discord=?, steam_id=?, phone=?, avatar=? WHERE id=?", [
            trim($_POST['ad'] ?? ''), trim($_POST['soyad'] ?? ''), trim($_POST['bio'] ?? ''),
            trim($_POST['ulke'] ?? ''), trim($_POST['discord'] ?? ''), trim($_POST['steam_id'] ?? ''),
            trim($_POST['telefon'] ?? ''), $avatarFilename, $kullanici['id']
        ]);
        
        flashMesaj('Profil güncellendi!', 'success');
        $redirectUrl = SITE_URL . '/panel.php?tab=profil';
        if ($tab === 'profil' && isset($_GET['id'])) { $redirectUrl .= '&id=' . (int)$_GET['id']; }
        header('Location: ' . $redirectUrl); exit;
    }
    
    if ($aksiyon === 'sifre_degistir') {
        if ($tab === 'profil' && isset($_GET['id']) && (int)$_GET['id'] !== (int)$_SESSION['user_id']) {
            flashMesaj('Diğer kullanıcıların şifresini değiştiremezsiniz.', 'error');
            header('Location: ' . SITE_URL . '/panel.php?tab=profil&id=' . (int)$_GET['id']); exit;
        }
        $mevcutSifre = $_POST['mevcut_sifre'] ?? '';
        $yeniSifre = $_POST['yeni_sifre'] ?? '';
        $yeniSifreTekrar = $_POST['yeni_sifre_tekrar'] ?? '';
        
        if (!password_verify($mevcutSifre, $kullanici['password_hash'])) {
            flashMesaj('Mevcut şifreniz hatalı.', 'error');
        } elseif (strlen($yeniSifre) < 6) {
            flashMesaj('Yeni şifre en az 6 karakter olmalıdır.', 'error');
        } elseif ($yeniSifre !== $yeniSifreTekrar) {
            flashMesaj('Yeni şifreler eşleşmiyor.', 'error');
        } else {
            $db->query("UPDATE kullanicilar SET password_hash=? WHERE id=?", [password_hash($yeniSifre, PASSWORD_DEFAULT), $kullanici['id']]);
            flashMesaj('Şifreniz başarıyla değiştirildi!', 'success');
        }
        header('Location: ' . SITE_URL . '/panel.php?tab=ayarlar'); exit;
    }
    
    if ($aksiyon === 'eposta_tercihleri') {
        $db->query("UPDATE kullanicilar SET eposta_bildirim=?, eposta_turnuva=?, eposta_mac=?, eposta_takim=? WHERE id=?", [
            isset($_POST['eposta_bildirim']) ? 1 : 0,
            isset($_POST['eposta_turnuva']) ? 1 : 0,
            isset($_POST['eposta_mac']) ? 1 : 0,
            isset($_POST['eposta_takim']) ? 1 : 0,
            $kullanici['id']
        ]);
        flashMesaj('E-posta bildirim tercihleri güncellendi!', 'success');
        header('Location: ' . SITE_URL . '/panel.php?tab=ayarlar'); exit;
    }
    
    if ($aksiyon === 'takim_olustur') {
        $takimAdi = trim($_POST['takim_adi'] ?? '');
        $oyunId = (int)($_POST['oyun_id'] ?? 0);
        $aciklama = trim($_POST['aciklama'] ?? '');
        
        if ($takimAdi && $oyunId) {
            $slug = slugOlustur($takimAdi);
            $existing = $db->fetch("SELECT id FROM takimlar WHERE slug = ?", [$slug]);
            if ($existing) { $slug .= '-' . time(); }
            
            $takimId = $db->insert("INSERT INTO takimlar (name, slug, game_id, description, captain_id, country) VALUES (?,?,?,?,?,?)",
                [$takimAdi, $slug, $oyunId, $aciklama, $kullanici['id'], $kullanici['country'] ?? 'Türkiye']);
            $db->query("INSERT INTO takim_uyeleri (team_id, user_id, role) VALUES (?,?,?)", [$takimId, $kullanici['id'], 'captain']);
            flashMesaj('Takımınız başarıyla oluşturuldu!', 'success');
        } else {
            flashMesaj('Takım adı ve oyun seçimi zorunludur.', 'error');
        }
        header('Location: ' . SITE_URL . '/panel.php?tab=takimlar'); exit;
    }

    if ($aksiyon === 'takim_guncelle') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $takim = $db->fetch("SELECT * FROM takimlar WHERE id = ? AND captain_id = ?", [$teamId, $kullanici['id']]);
        
        if (!$takim) {
            flashMesaj('Buna yetkiniz yok.', 'error');
            header('Location: ' . SITE_URL . '/panel.php?tab=takimlar'); exit;
        }

        $takimAdi = trim($_POST['takim_adi'] ?? '');
        $aciklama = trim($_POST['aciklama'] ?? '');
        $ulke = trim($_POST['ulke'] ?? 'Türkiye');

        if ($takimAdi) {
            $logoFilename = $takim['logo'];
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload = dosyaYukle($_FILES['logo'], 'team-logos', MAX_AVATAR_SIZE);
                if ($upload['success']) {
                    // Delete old logo if not default
                    if ($takim['logo'] && $takim['logo'] !== 'default-team.png' && strpos($takim['logo'], 'assets/') !== 0) {
                        $oldFile = UPLOADS_PATH . 'team-logos' . DIRECTORY_SEPARATOR . $takim['logo'];
                        if (file_exists($oldFile)) { @unlink($oldFile); }
                    }
                    $logoFilename = $upload['filename'];
                } else {
                    flashMesaj('Takım logosu yüklenemedi: ' . $upload['error'], 'error');
                    header('Location: ' . SITE_URL . '/panel.php?tab=takim_yonetim&id=' . $teamId); exit;
                }
            }

            $db->query("UPDATE takimlar SET name = ?, description = ?, country = ?, logo = ? WHERE id = ?", [
                $takimAdi, $aciklama, $ulke, $logoFilename, $teamId
            ]);
            flashMesaj('Takım bilgileri güncellendi.', 'success');
        } else {
            flashMesaj('Takım adı boş olamaz.', 'error');
        }
        header('Location: ' . SITE_URL . '/panel.php?tab=takim_yonetim&id=' . $teamId); exit;
    }

    if ($aksiyon === 'takim_sil') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $takim = $db->fetch("SELECT * FROM takimlar WHERE id = ? AND captain_id = ?", [$teamId, $kullanici['id']]);
        
        if (!$takim) {
            flashMesaj('Buna yetkiniz yok.', 'error');
        } else {
            // Delete logo if not default
            if ($takim['logo'] && $takim['logo'] !== 'default-team.png' && strpos($takim['logo'], 'assets/') !== 0) {
                $oldFile = UPLOADS_PATH . 'team-logos' . DIRECTORY_SEPARATOR . $takim['logo'];
                if (file_exists($oldFile)) { @unlink($oldFile); }
            }
            $db->query("DELETE FROM takimlar WHERE id = ?", [$teamId]);
            flashMesaj('Takım tamamen silindi.', 'success');
        }
        header('Location: ' . SITE_URL . '/panel.php?tab=takimlar'); exit;
    }

    if ($aksiyon === 'takim_davet_et') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');

        $takim = $db->fetch("SELECT * FROM takimlar WHERE id = ? AND captain_id = ?", [$teamId, $kullanici['id']]);
        if (!$takim) {
            flashMesaj('Buna yetkiniz yok.', 'error');
            header('Location: ' . SITE_URL . '/panel.php?tab=takimlar'); exit;
        }

        $targetPlayer = $db->fetch("SELECT * FROM kullanicilar WHERE username = ?", [$username]);
        if (!$targetPlayer) {
            flashMesaj('Belirtilen kullanıcı bulunamadı.', 'error');
        } else {
            // Check member count
            $memberCount = $db->count("SELECT COUNT(*) FROM takim_uyeleri WHERE team_id = ?", [$teamId]);
            
            // Check membership
            $isMember = $db->fetch("SELECT id FROM takim_uyeleri WHERE team_id = ? AND user_id = ?", [$teamId, $targetPlayer['id']]);
            
            // Check pending invitation/request
            $pending = $db->fetch("SELECT id FROM takim_davetleri WHERE team_id = ? AND user_id = ? AND status = 'pending'", [$teamId, $targetPlayer['id']]);

            if ($isMember) {
                flashMesaj('Bu oyuncu zaten takımınızda.', 'error');
            } elseif ($pending) {
                flashMesaj('Bu oyuncu için zaten bekleyen bir davet veya başvuru var.', 'error');
            } elseif ($memberCount >= 10) {
                flashMesaj('Takım kadrosu doludur (Maksimum 10 kişi).', 'error');
            } else {
                // Send invitation
                $db->insert("INSERT INTO takim_davetleri (team_id, user_id, sender_id, type) VALUES (?,?,?,?)", [
                    $teamId, $targetPlayer['id'], $kullanici['id'], 'invitation'
                ]);

                // Notification
                $title = "Yeni Takım Daveti";
                $message = temizle($takim['name']) . " takımı sizi kadrosuna davet ediyor!";
                $link = "panel.php?tab=takimlar";
                $db->query("INSERT INTO bildirimler (user_id, title, message, type, link) VALUES (?, ?, ?, 'team', ?)", [
                    $targetPlayer['id'], $title, $message, $link
                ]);

                // Email
                if (($targetPlayer['eposta_takim'] ?? 1) && ($targetPlayer['eposta_bildirim'] ?? 1)) {
                    epostaTakimDavet($targetPlayer, $takim, $kullanici);
                }

                flashMesaj('Oyuncuya takım daveti başarıyla gönderildi.', 'success');
            }
        }
        header('Location: ' . SITE_URL . '/panel.php?tab=takim_yonetim&id=' . $teamId); exit;
    }

    if ($aksiyon === 'takim_davet_cevap') {
        $davetId = (int)($_POST['davet_id'] ?? 0);
        $cevap = $_POST['cevap'] ?? '';

        $davet = $db->fetch("SELECT td.*, t.name as takim_adi, t.captain_id FROM takim_davetleri td JOIN takimlar t ON td.team_id = t.id WHERE td.id = ? AND td.user_id = ? AND td.status = 'pending' AND td.type = 'invitation'", [$davetId, $kullanici['id']]);
        
        if (!$davet) {
            flashMesaj('Davet kaydı bulunamadı veya yetkiniz yok.', 'error');
        } else {
            if ($cevap === 'accept') {
                // Check limit
                $memberCount = $db->count("SELECT COUNT(*) FROM takim_uyeleri WHERE team_id = ?", [$davet['team_id']]);
                if ($memberCount >= 10) {
                    flashMesaj('Takım kontenjanı dolu olduğundan davet kabul edilemedi.', 'error');
                } else {
                    $db->query("UPDATE takim_davetleri SET status = 'accepted' WHERE id = ?", [$davetId]);
                    $db->query("INSERT INTO takim_uyeleri (team_id, user_id, role) VALUES (?,?,?)", [$davet['team_id'], $kullanici['id'], 'player']);

                    // Notify captain
                    $title = "Takım Daveti Kabul Edildi";
                    $message = temizle($kullanici['username']) . " takım davetinizi kabul etti!";
                    $link = "panel.php?tab=takim_yonetim&id=" . $davet['team_id'];
                    $db->query("INSERT INTO bildirimler (user_id, title, message, type, link) VALUES (?, ?, ?, 'team', ?)", [
                        $davet['captain_id'], $title, $message, $link
                    ]);

                    // Email captain
                    $captain = $db->fetch("SELECT * FROM kullanicilar WHERE id = ?", [$davet['captain_id']]);
                    if ($captain && ($captain['eposta_takim'] ?? 1) && ($captain['eposta_bildirim'] ?? 1)) {
                        epostaBildirim($captain, $title, $message, SITE_URL . '/' . $link);
                    }

                    flashMesaj('Daveti kabul ettiniz, takıma katıldınız!', 'success');
                }
            } elseif ($cevap === 'reject') {
                $db->query("UPDATE takim_davetleri SET status = 'rejected' WHERE id = ?", [$davetId]);
                
                // Notify captain
                $title = "Takım Daveti Reddedildi";
                $message = temizle($kullanici['username']) . " takım davetinizi reddetti.";
                $link = "panel.php?tab=takim_yonetim&id=" . $davet['team_id'];
                $db->query("INSERT INTO bildirimler (user_id, title, message, type, link) VALUES (?, ?, ?, 'team', ?)", [
                    $davet['captain_id'], $title, $message, $link
                ]);

                flashMesaj('Takım davetini reddettiniz.', 'success');
            }
        }
        header('Location: ' . SITE_URL . '/panel.php?tab=takimlar'); exit;
    }

    if ($aksiyon === 'takim_basvuru_cevap') {
        $davetId = (int)($_POST['davet_id'] ?? 0);
        $teamId = (int)($_POST['team_id'] ?? 0);
        $cevap = $_POST['cevap'] ?? '';

        $takim = $db->fetch("SELECT * FROM takimlar WHERE id = ? AND captain_id = ?", [$teamId, $kullanici['id']]);
        if (!$takim) {
            flashMesaj('Buna yetkiniz yok.', 'error');
            header('Location: ' . SITE_URL . '/panel.php?tab=takimlar'); exit;
        }

        $davet = $db->fetch("SELECT td.*, u.username FROM takim_davetleri td JOIN kullanicilar u ON td.user_id = u.id WHERE td.id = ? AND td.team_id = ? AND td.status = 'pending' AND td.type = 'request'", [$davetId, $teamId]);
        if (!$davet) {
            flashMesaj('Başvuru bulunamadı.', 'error');
        } else {
            if ($cevap === 'accept') {
                $memberCount = $db->count("SELECT COUNT(*) FROM takim_uyeleri WHERE team_id = ?", [$teamId]);
                if ($memberCount >= 10) {
                    flashMesaj('Takım kadrosu doludur (Maksimum 10 kişi).', 'error');
                } else {
                    $db->query("UPDATE takim_davetleri SET status = 'accepted' WHERE id = ?", [$davetId]);
                    $db->query("INSERT INTO takim_uyeleri (team_id, user_id, role) VALUES (?,?,?)", [$teamId, $davet['user_id'], 'player']);

                    // Notify player
                    $title = "Takım Başvurunuz Onaylandı";
                    $message = temizle($takim['name']) . " takımına yaptığınız katılma talebi onaylandı!";
                    $link = "panel.php?tab=takimlar";
                    $db->query("INSERT INTO bildirimler (user_id, title, message, type, link) VALUES (?, ?, ?, 'team', ?)", [
                        $davet['user_id'], $title, $message, $link
                    ]);

                    // Email player
                    $player = $db->fetch("SELECT * FROM kullanicilar WHERE id = ?", [$davet['user_id']]);
                    if ($player && ($player['eposta_takim'] ?? 1) && ($player['eposta_bildirim'] ?? 1)) {
                        epostaBildirim($player, $title, $message, SITE_URL . '/' . $link);
                    }

                    flashMesaj('Kullanıcının takıma katılımı onaylandı.', 'success');
                }
            } elseif ($cevap === 'reject') {
                $db->query("UPDATE takim_davetleri SET status = 'rejected' WHERE id = ?", [$davetId]);

                // Notify player
                $title = "Takım Başvurunuz Reddedildi";
                $message = temizle($takim['name']) . " takımına yaptığınız katılma talebi reddedildi.";
                $link = "panel.php?tab=takimlar";
                $db->query("INSERT INTO bildirimler (user_id, title, message, type, link) VALUES (?, ?, ?, 'team', ?)", [
                    $davet['user_id'], $title, $message, $link
                ]);

                flashMesaj('Kullanıcının takıma katılım başvurusu reddedildi.', 'success');
            }
        }
        header('Location: ' . SITE_URL . '/panel.php?tab=takim_yonetim&id=' . $teamId); exit;
    }

    if ($aksiyon === 'takim_davet_iptal') {
        $davetId = (int)($_POST['davet_id'] ?? 0);
        $teamId = (int)($_POST['team_id'] ?? 0);

        // Can cancel if captain of team or applicant
        $davet = $db->fetch("SELECT td.*, t.captain_id FROM takim_davetleri td JOIN takimlar t ON td.team_id = t.id WHERE td.id = ? AND td.status = 'pending'", [$davetId]);
        if (!$davet) {
            flashMesaj('Kayıt bulunamadı.', 'error');
        } else {
            if ($davet['captain_id'] == $kullanici['id'] || $davet['user_id'] == $kullanici['id']) {
                $db->query("UPDATE takim_davetleri SET status = 'cancelled' WHERE id = ?", [$davetId]);
                flashMesaj('İşlem iptal edildi.', 'success');
            } else {
                flashMesaj('Buna yetkiniz yok.', 'error');
            }
        }
        
        if ($davet && $davet['captain_id'] == $kullanici['id']) {
            header('Location: ' . SITE_URL . '/panel.php?tab=takim_yonetim&id=' . $teamId); exit;
        } else {
            header('Location: ' . SITE_URL . '/panel.php?tab=takimlar'); exit;
        }
    }

    if ($aksiyon === 'takimdan_ayril') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $membership = $db->fetch("SELECT * FROM takim_uyeleri WHERE team_id = ? AND user_id = ?", [$teamId, $kullanici['id']]);

        if (!$membership) {
            flashMesaj('Bu takımın üyesi değilsiniz.', 'error');
        } elseif ($membership['role'] === 'captain') {
            flashMesaj('Takım kaptanı takımdan ayrılamaz. Takımı silebilir veya kaptanlığı devredebilirsiniz.', 'error');
        } else {
            $db->query("DELETE FROM takim_uyeleri WHERE team_id = ? AND user_id = ?", [$teamId, $kullanici['id']]);
            
            $takim = $db->fetch("SELECT * FROM takimlar WHERE id = ?", [$teamId]);
            if ($takim) {
                // Notify captain
                $title = "Takımdan Ayrılan Oyuncu";
                $message = temizle($kullanici['username']) . " takımınızdan ayrıldı.";
                $link = "panel.php?tab=takim_yonetim&id=" . $teamId;
                $db->query("INSERT INTO bildirimler (user_id, title, message, type, link) VALUES (?, ?, ?, 'team', ?)", [
                    $takim['captain_id'], $title, $message, $link
                ]);
            }

            flashMesaj('Takımdan başarıyla ayrıldınız.', 'success');
        }
        header('Location: ' . SITE_URL . '/panel.php?tab=takimlar'); exit;
    }

    if ($aksiyon === 'takimdan_cikar') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $memberId = (int)($_POST['member_id'] ?? 0);

        $takim = $db->fetch("SELECT * FROM takimlar WHERE id = ? AND captain_id = ?", [$teamId, $kullanici['id']]);
        if (!$takim) {
            flashMesaj('Buna yetkiniz yok.', 'error');
        } else {
            $membership = $db->fetch("SELECT * FROM takim_uyeleri WHERE team_id = ? AND user_id = ?", [$teamId, $memberId]);
            if (!$membership) {
                flashMesaj('Bu üye zaten takımda bulunmuyor.', 'error');
            } elseif ($membership['role'] === 'captain') {
                flashMesaj('Takım kaptanı gruptan çıkarılamaz.', 'error');
            } else {
                $db->query("DELETE FROM takim_uyeleri WHERE team_id = ? AND user_id = ?", [$teamId, $memberId]);

                // Notify player
                $title = "Takımdan Çıkarıldınız";
                $message = temizle($takim['name']) . " takımındaki üyeliğiniz sonlandırıldı.";
                $link = "panel.php?tab=takimlar";
                $db->query("INSERT INTO bildirimler (user_id, title, message, type, link) VALUES (?, ?, ?, 'team', ?)", [
                    $memberId, $title, $message, $link
                ]);

                flashMesaj('Oyuncu takımdan çıkarıldı.', 'success');
            }
        }
        header('Location: ' . SITE_URL . '/panel.php?tab=takim_yonetim&id=' . $teamId); exit;
    }

    if ($aksiyon === 'uye_rol_guncelle') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $memberId = (int)($_POST['member_id'] ?? 0);
        $rol = trim($_POST['rol'] ?? '');
        
        $takim = $db->fetch("SELECT id FROM takimlar WHERE id = ? AND captain_id = ?", [$teamId, $kullanici['id']]);
        if (!$takim) {
            flashMesaj('Yetkiniz yok.', 'error');
        } elseif (!in_array($rol, ['player', 'substitute', 'coach'])) {
            flashMesaj('Geçersiz rol.', 'error');
        } else {
            $db->query("UPDATE takim_uyeleri SET role = ? WHERE team_id = ? AND user_id = ? AND role != 'captain'", [$rol, $teamId, $memberId]);
            flashMesaj('Üyenin takım rolü güncellendi.', 'success');
        }
        header('Location: ' . SITE_URL . '/panel.php?tab=takim_yonetim&id=' . $teamId); exit;
    }
}

// Veriler
$takimlarim = $db->fetchAll("SELECT t.*, g.name as oyun_adi, tm.role as uye_rolu FROM takim_uyeleri tm LEFT JOIN takimlar t ON tm.team_id = t.id LEFT JOIN oyunlar g ON t.game_id = g.id WHERE tm.user_id = ?", [$kullanici['id']]);
$turnuvalarim = $db->fetchAll("SELECT tr.*, t.name as turnuva_adi, t.status as turnuva_durumu, t.prize_pool, tm.name as takim_adi
    FROM turnuva_kayitlari tr LEFT JOIN turnuvalar t ON tr.tournament_id = t.id LEFT JOIN takimlar tm ON tr.team_id = tm.id
    WHERE tr.team_id IN (SELECT team_id FROM takim_uyeleri WHERE user_id = ?) ORDER BY tr.registered_at DESC", [$kullanici['id']]);
$filtre = $_GET['filtre'] ?? 'all';
if (!in_array($filtre, ['all', 'unread', 'read'])) {
    $filtre = 'all';
}
$limit = 10;
$sayfa = max(1, (int)($_GET['sayfa'] ?? 1));
$offset = ($sayfa - 1) * $limit;

$whereClause = "WHERE user_id = ?";
$params = [$kullanici['id']];

if ($filtre === 'unread') {
    $whereClause .= " AND is_read = 0";
} elseif ($filtre === 'read') {
    $whereClause .= " AND is_read = 1";
}

$toplamKayit = (int)$db->count("SELECT COUNT(*) FROM bildirimler $whereClause", $params);
$toplamSayfa = (int)ceil($toplamKayit / $limit);
if ($sayfa > $toplamSayfa && $toplamSayfa > 0) {
    $sayfa = $toplamSayfa;
    $offset = ($sayfa - 1) * $limit;
}

$bildirimler = $db->fetchAll("SELECT * FROM bildirimler $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset", $params);
$okunmamis = (int)$db->count("SELECT COUNT(*) FROM bildirimler WHERE user_id = ? AND is_read = 0", [$kullanici['id']]);
$oyunlar = $db->fetchAll("SELECT * FROM oyunlar WHERE is_active=1 ORDER BY name");

// Gelen Davetler & Gönderilen Başvurular
$gelen_davetler = $db->fetchAll("
    SELECT td.*, t.name as takim_adi, t.logo as takim_logo, u.username as davet_eden
    FROM takim_davetleri td
    JOIN takimlar t ON td.team_id = t.id
    JOIN kullanicilar u ON td.sender_id = u.id
    WHERE td.user_id = ? AND td.type = 'invitation' AND td.status = 'pending'
", [$kullanici['id']]);

$gonderilen_basvurular = $db->fetchAll("
    SELECT td.*, t.name as takim_adi, t.logo as takim_logo
    FROM takim_davetleri td
    JOIN takimlar t ON td.team_id = t.id
    WHERE td.user_id = ? AND td.type = 'request' AND td.status = 'pending'
", [$kullanici['id']]);

// Takım Yönetimi alt sayfası aktifse
$yonetilen_takim = null;
$roster = [];
$gelen_talepler = [];
$gonderilen_davetler = [];

if ($tab === 'takim_yonetim') {
    $teamId = (int)($_GET['id'] ?? 0);
    $yonetilen_takim = $db->fetch("SELECT t.*, g.name as oyun_adi FROM takimlar t LEFT JOIN oyunlar g ON t.game_id = g.id WHERE t.id = ? AND t.captain_id = ?", [$teamId, $kullanici['id']]);
    
    if (!$yonetilen_takim) {
        flashMesaj('Yönetmek istediğiniz takım bulunamadı veya kaptan değilsiniz.', 'error');
        header('Location: ' . SITE_URL . '/panel.php?tab=takimlar'); exit;
    }

    $roster = $db->fetchAll("
        SELECT tm.*, u.username, u.first_name, u.last_name, u.avatar, u.points
        FROM takim_uyeleri tm
        JOIN kullanicilar u ON tm.user_id = u.id
        WHERE tm.team_id = ?
        ORDER BY FIELD(tm.role, 'captain', 'player', 'substitute', 'coach'), tm.joined_at ASC
    ", [$teamId]);

    $gelen_talepler = $db->fetchAll("
        SELECT td.*, u.username, u.first_name, u.last_name, u.avatar, u.points
        FROM takim_davetleri td
        JOIN kullanicilar u ON td.user_id = u.id
        WHERE td.team_id = ? AND td.type = 'request' AND td.status = 'pending'
    ", [$teamId]);

    $gonderilen_davetler = $db->fetchAll("
        SELECT td.*, u.username, u.first_name, u.last_name, u.avatar, u.points
        FROM takim_davetleri td
        JOIN kullanicilar u ON td.user_id = u.id
        WHERE td.team_id = ? AND td.type = 'invitation' AND td.status = 'pending'
    ", [$teamId]);
}

require_once __DIR__ . '/includes/ust.php';
?>

<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <div class="dashboard-grid">
            <!-- Sidebar -->
            <div class="dashboard-sidebar">
                <div style="text-align:center;padding:var(--space-lg) 0;border-bottom:1px solid var(--border-light);margin-bottom:var(--space-md);">
                    <div style="width:60px;height:60px;border-radius:50%;background:var(--bg-surface);margin:0 auto var(--space-sm);display:flex;align-items:center;justify-content:center;border:2px solid var(--border);overflow:hidden;">
                        <img src="<?= avatarURL($kullanici['avatar'] ?? '') ?>" alt="Kullanıcı Avatarı" style="width:100%;height:100%;object-fit:cover;">
                    </div>
                    <h4 style="font-size:0.95rem;"><?= temizle($kullanici['username']) ?></h4>
                    <p style="color:var(--text-muted);font-size:0.8rem;"><?= temizle($kullanici['email']) ?></p>
                </div>
                <a href="?tab=genel" class="dashboard-menu-link <?= $tab==='genel'?'aktif':'' ?>"><i class="fas fa-tachometer-alt"></i> Genel Bakış</a>
                <a href="?tab=profil" class="dashboard-menu-link <?= $tab==='profil'?'aktif':'' ?>"><i class="fas fa-user-edit"></i> Profilim</a>
                <a href="?tab=takimlar" class="dashboard-menu-link <?= ($tab==='takimlar' || $tab==='takim_yonetim')?'aktif':'' ?>"><i class="fas fa-users"></i> Takımlarım</a>
                <a href="?tab=turnuvalar" class="dashboard-menu-link <?= $tab==='turnuvalar'?'aktif':'' ?>"><i class="fas fa-trophy"></i> Turnuvalarım</a>
                <a href="?tab=bildirimler" class="dashboard-menu-link <?= $tab==='bildirimler'?'aktif':'' ?>"><i class="fas fa-bell"></i> Bildirimler <?php if ($okunmamis): ?><span class="badge badge-danger" style="margin-left:auto;"><?= $okunmamis ?></span><?php endif; ?></a>
                <a href="?tab=ayarlar" class="dashboard-menu-link <?= $tab==='ayarlar'?'aktif':'' ?>"><i class="fas fa-cog"></i> Ayarlar</a>
            </div>

            <!-- İçerik -->
            <div class="dashboard-icerik">
                <?= flashGoster() ?>

                <?php if ($tab === 'genel'): ?>
                <h2 style="margin-bottom:var(--space-lg);">Hoş geldin, <?= temizle($kullanici['username']) ?>! 👋</h2>
                <div class="dashboard-stat-grid">
                    <div class="dashboard-stat-kart"><div class="dashboard-stat-ikon mor"><i class="fas fa-users"></i></div><div class="dashboard-stat-bilgi"><h3><?= count($takimlarim) ?></h3><span>Takım</span></div></div>
                    <div class="dashboard-stat-kart"><div class="dashboard-stat-ikon yesil"><i class="fas fa-trophy"></i></div><div class="dashboard-stat-bilgi"><h3><?= count($turnuvalarim) ?></h3><span>Turnuva</span></div></div>
                    <div class="dashboard-stat-kart"><div class="dashboard-stat-ikon kirmizi"><i class="fas fa-crosshairs"></i></div><div class="dashboard-stat-bilgi"><h3><?= $kullanici['total_wins'] ?></h3><span>Galibiyet</span></div></div>
                    <div class="dashboard-stat-kart"><div class="dashboard-stat-ikon mavi"><i class="fas fa-star"></i></div><div class="dashboard-stat-bilgi"><h3><?= $kullanici['points'] ?></h3><span>Puan</span></div></div>
                </div>

                <?php elseif ($tab === 'profil'): ?>
                <h2 style="margin-bottom:var(--space-lg);">Profil Düzenle</h2>
                <?php if (isset($_GET['id']) && adminMi()): ?>
                    <div class="alert alert-info" style="margin-bottom:var(--space-md);display:flex;align-items:center;justify-content:space-between;width:100%;gap:var(--space-md);">
                        <div>
                            <i class="fas fa-info-circle" style="color:var(--accent);margin-right:8px;"></i>
                            Şu an <strong><?= temizle($kullanici['username']) ?></strong> (ID: <?= $kullanici['id'] ?>) kullanıcısının profilini düzenliyorsunuz.
                        </div>
                        <a href="<?= SITE_URL ?>/yonetim/kullanicilar.php" class="btn btn-ghost btn-sm" style="padding:6px 12px;font-size:0.75rem;text-transform:none;letter-spacing:normal;"><i class="fas fa-users"></i> Kullanıcılara Dön</a>
                    </div>
                <?php endif; ?>
                <div class="kart" style="padding:var(--space-xl);">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfInput() ?>
                        <input type="hidden" name="aksiyon" value="profil_guncelle">
                        
                        <!-- Avatar Upload Section -->
                        <div style="display:flex;align-items:center;gap:var(--space-lg);margin-bottom:var(--space-lg);padding-bottom:var(--space-md);border-bottom:1px solid var(--border-light);flex-wrap:wrap;">
                            <div style="position:relative;width:90px;height:90px;border-radius:50%;border:3px solid var(--border);overflow:hidden;background:var(--bg-surface);flex-shrink:0;box-shadow:0 0 15px rgba(0,0,0,0.2);transition:var(--transition);" onmouseover="this.style.borderColor='var(--accent)';" onmouseout="this.style.borderColor='var(--border)';">
                                <img src="<?= avatarURL($kullanici['avatar'] ?? '') ?>" alt="Kullanıcı Avatarı" style="width:100%;height:100%;object-fit:cover;" id="avatarPreview">
                            </div>
                            <div class="form-grup" style="margin-bottom:0;flex:1;min-width:200px;">
                                <label class="form-label" style="margin-bottom:6px;font-weight:600;letter-spacing:0.02em;">Profil Fotoğrafı</label>
                                <div class="file-upload-wrapper" style="position:relative;overflow:hidden;display:inline-block;width:100%;">
                                    <input type="file" name="avatar" id="avatarFileInput" accept="image/*" style="position:absolute;left:0;top:0;opacity:0;cursor:pointer;width:100%;height:100%;z-index:2;" onchange="if(this.files[0]) { document.getElementById('avatarPreview').src = window.URL.createObjectURL(this.files[0]); document.getElementById('file-upload-btn-text').textContent = this.files[0].name; }">
                                    <button type="button" class="btn btn-ghost btn-sm" style="pointer-events:none;width:100%;justify-content:flex-start;text-align:left;gap:var(--space-md);border-style:dashed;border-width:2px;border-color:var(--border);padding:10px 16px;">
                                        <i class="fas fa-cloud-upload-alt" style="color:var(--accent);font-size:1.1rem;"></i>
                                        <span id="file-upload-btn-text" style="font-family:var(--font-heading);text-transform:none;letter-spacing:normal;font-weight:500;color:var(--text-secondary);">Yeni fotoğraf seçin veya sürükleyin...</span>
                                    </button>
                                </div>
                                <span style="font-size:0.75rem;color:var(--text-muted);display:block;margin-top:4px;"><i class="fas fa-info-circle" style="margin-right:2px;color:var(--accent);"></i> En fazla 2MB, sadece JPEG, PNG, WebP ve GIF kabul edilir.</span>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md);">
                            <div class="form-grup"><label class="form-label">Kullanıcı Adı</label><input type="text" class="form-input" value="<?= temizle($kullanici['username']) ?>" disabled></div>
                            <div class="form-grup"><label class="form-label">E-posta</label><input type="text" class="form-input" value="<?= temizle($kullanici['email']) ?>" disabled></div>
                            <div class="form-grup"><label class="form-label">Ad</label><input type="text" name="ad" class="form-input" value="<?= temizle($kullanici['first_name'] ?? '') ?>"></div>
                            <div class="form-grup"><label class="form-label">Soyad</label><input type="text" name="soyad" class="form-input" value="<?= temizle($kullanici['last_name'] ?? '') ?>"></div>
                            <div class="form-grup"><label class="form-label">Ülke</label><input type="text" name="ulke" class="form-input" value="<?= temizle($kullanici['country'] ?? '') ?>"></div>
                            <div class="form-grup"><label class="form-label">Telefon</label><input type="text" name="telefon" class="form-input" value="<?= temizle($kullanici['phone'] ?? '') ?>"></div>
                            <div class="form-grup"><label class="form-label">Discord</label><input type="text" name="discord" class="form-input" value="<?= temizle($kullanici['discord'] ?? '') ?>"></div>
                            <div class="form-grup"><label class="form-label">Steam ID</label><input type="text" name="steam_id" class="form-input" value="<?= temizle($kullanici['steam_id'] ?? '') ?>"></div>
                        </div>
                        <div class="form-grup"><label class="form-label">Biyografi</label><textarea name="bio" class="form-input" rows="4"><?= temizle($kullanici['bio'] ?? '') ?></textarea></div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Kaydet</button>
                    </form>
                </div>

                <?php elseif ($tab === 'takimlar'): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-lg);">
                    <h2>Takımlarım</h2>
                </div>

                <!-- Gelen Davetler -->
                <?php if (!empty($gelen_davetler)): ?>
                <div class="kart" style="padding:var(--space-lg); margin-bottom:var(--space-xl); border:1px solid var(--border-hover);">
                    <h3 style="margin-bottom:var(--space-md); font-size:1.1rem; color:var(--accent);"><i class="fas fa-envelope-open-text"></i> Gelen Takım Davetleri (<?= count($gelen_davetler) ?>)</h3>
                    <div class="tablo-wrapper">
                        <table class="tablo">
                            <thead>
                                <tr>
                                    <th>Takım</th>
                                    <th>Davet Eden</th>
                                    <th style="text-align:center; width:180px;">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gelen_davetler as $gd): ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <img src="<?= takimLogoURL($gd['takim_logo']) ?>" style="width:28px; height:28px; border-radius:var(--radius-sm); object-fit:cover; border:1px solid var(--border-light);">
                                                <span style="font-weight:600;"><?= temizle($gd['takim_adi']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= temizle($gd['davet_eden']) ?></td>
                                        <td style="text-align:center;">
                                            <div style="display:flex; gap:6px; justify-content:center;">
                                                <form method="POST" style="margin:0;">
                                                    <?= csrfInput() ?>
                                                    <input type="hidden" name="aksiyon" value="takim_davet_cevap">
                                                    <input type="hidden" name="davet_id" value="<?= $gd['id'] ?>">
                                                    <input type="hidden" name="cevap" value="accept">
                                                    <button type="submit" class="btn btn-sm" style="background:var(--success); color:white; border:none; padding:6px 12px; font-weight:600;"><i class="fas fa-check"></i> Kabul Et</button>
                                                </form>
                                                <form method="POST" style="margin:0;">
                                                    <?= csrfInput() ?>
                                                    <input type="hidden" name="aksiyon" value="takim_davet_cevap">
                                                    <input type="hidden" name="davet_id" value="<?= $gd['id'] ?>">
                                                    <input type="hidden" name="cevap" value="reject">
                                                    <button type="submit" class="btn btn-sm" style="background:var(--danger); color:white; border:none; padding:6px 12px; font-weight:600;"><i class="fas fa-times"></i> Reddet</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Gönderdiğim Başvurular -->
                <?php if (!empty($gonderilen_basvurular)): ?>
                <div class="kart" style="padding:var(--space-lg); margin-bottom:var(--space-xl);">
                    <h3 style="margin-bottom:var(--space-md); font-size:1.1rem;"><i class="fas fa-paper-plane"></i> Gönderdiğim Katılma Başvuruları (<?= count($gonderilen_basvurular) ?>)</h3>
                    <div class="tablo-wrapper">
                        <table class="tablo">
                            <thead>
                                <tr>
                                    <th>Takım</th>
                                    <th>Başvuru Tarihi</th>
                                    <th>Durum</th>
                                    <th style="text-align:center; width:120px;">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gonderilen_basvurular as $gb): ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <img src="<?= takimLogoURL($gb['takim_logo']) ?>" style="width:28px; height:28px; border-radius:var(--radius-sm); object-fit:cover; border:1px solid var(--border-light);">
                                                <span style="font-weight:600;"><?= temizle($gb['takim_adi']) ?></span>
                                            </div>
                                        </td>
                                        <td style="color:var(--text-muted); font-size:0.85rem;"><?= tarihFormatla($gb['created_at'], 'short') ?></td>
                                        <td><?= durumBadge($gb['status']) ?></td>
                                        <td style="text-align:center;">
                                            <form method="POST" style="margin:0;" onsubmit="return confirm('Başvurunuzu iptal etmek istediğinizden emin misiniz?')">
                                                <?= csrfInput() ?>
                                                <input type="hidden" name="aksiyon" value="takim_davet_iptal">
                                                <input type="hidden" name="davet_id" value="<?= $gb['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--danger); border:none; padding:6px 12px; font-weight:600;"><i class="fas fa-trash-alt"></i> İptal Et</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Mevcut Takımlarım -->
                <?php if (!empty($takimlarim)): ?>
                <div class="grid grid-3">
                    <?php foreach ($takimlarim as $t): ?>
                    <div class="takim-kart">
                        <div class="takim-kart-logo">
                            <img src="<?= takimLogoURL($t['logo']) ?>" alt="<?= temizle($t['name']) ?>">
                        </div>
                        <h3 class="takim-kart-isim"><a href="<?= SITE_URL ?>/takim.php?id=<?= $t['id'] ?>"><?= temizle($t['name']) ?></a></h3>
                        <div class="takim-kart-oyun"><?= temizle($t['oyun_adi']) ?></div>
                        <div class="oyuncu-kart-rol" style="margin-bottom:var(--space-md);"><i class="fas fa-<?= $t['uye_rolu']==='captain'?'crown':'user' ?>"></i> <?= rolCevir($t['uye_rolu'], 'team') ?></div>
                        
                        <div style="display:flex; gap:6px; justify-content:center; margin-top:var(--space-sm);">
                            <?php if ($t['uye_rolu'] === 'captain'): ?>
                                <a href="?tab=takim_yonetim&id=<?= $t['id'] ?>" class="btn btn-sm btn-neon" style="width:100%;"><i class="fas fa-cog"></i> Yönet</a>
                            <?php else: ?>
                                <form method="POST" style="margin:0; width:100%;" onsubmit="return confirm('Takımdan ayrılmak istediğinize emin misiniz?')">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="aksiyon" value="takimdan_ayril">
                                    <input type="hidden" name="team_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-sm" style="background:var(--danger); color:white; border:none; width:100%;"><i class="fas fa-sign-out-alt"></i> Ayrıl</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <div class="bos-durum" style="text-align:center; padding:var(--space-3xl) 0;">
                        <i class="fas fa-users" style="font-size:3rem; color:var(--text-muted); margin-bottom:var(--space-md); display:block;"></i>
                        <h3>Henüz bir takımınız yok</h3>
                        <p style="color:var(--text-muted); margin-top:var(--space-xs);">Bir takıma katılın veya aşağıdan kendiniz kurun.</p>
                    </div>
                <?php endif; ?>

                <!-- Takım Oluştur Formu -->
                <div class="kart" style="padding:var(--space-xl);margin-top:var(--space-xl);">
                    <h3 style="margin-bottom:var(--space-lg);"><i class="fas fa-plus-circle" style="color:var(--accent)"></i> Yeni Takım Oluştur</h3>
                    <form method="POST">
                        <?= csrfInput() ?>
                        <input type="hidden" name="aksiyon" value="takim_olustur">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md);">
                            <div class="form-grup"><label class="form-label">Takım Adı</label><input type="text" name="takim_adi" class="form-input" required></div>
                            <div class="form-grup"><label class="form-label">Oyun</label><select name="oyun_id" class="form-input" required><option value="">Seçiniz</option><?php foreach ($oyunlar as $o): ?><option value="<?= $o['id'] ?>"><?= temizle($o['name']) ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div class="form-grup"><label class="form-label">Açıklama</label><textarea name="aciklama" class="form-input" rows="3"></textarea></div>
                        <button type="submit" class="btn btn-neon"><i class="fas fa-plus"></i> Takım Oluştur</button>
                    </form>
                </div>

                <?php elseif ($tab === 'takim_yonetim'): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-lg);border-bottom:1px solid var(--border-light);padding-bottom:var(--space-md);">
                        <h2 style="font-size:1.5rem;"><i class="fas fa-users-cog" style="color:var(--accent)"></i> <?= temizle($yonetilen_takim['name']) ?> - Takım Yönetimi</h2>
                        <a href="?tab=takimlar" class="btn btn-ghost btn-sm" style="text-transform:none; letter-spacing:normal;"><i class="fas fa-chevron-left"></i> Takımlarıma Dön</a>
                    </div>

                    <!-- Layout Grid -->
                    <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap: var(--space-xl);" class="dashboard-tablo-taşıma-engeli">
                        
                        <!-- Sol Kolon: Kadro & Oyuncu Davet Et -->
                        <div>
                            <!-- Kadro -->
                            <div class="kart" style="padding:var(--space-lg); margin-bottom:var(--space-xl);">
                                <h3 style="margin-bottom:var(--space-md); border-bottom:1px solid var(--border-light); padding-bottom:var(--space-xs); font-size:1.1rem;"><i class="fas fa-users" style="color:var(--accent);"></i> Kadro (<?= count($roster) ?> / 10)</h3>
                                <div class="tablo-wrapper">
                                    <table class="tablo">
                                        <thead>
                                            <tr>
                                                <th>Oyuncu</th>
                                                <th>Rol</th>
                                                <th style="text-align:center;">İşlem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($roster as $r): ?>
                                                <tr>
                                                    <td>
                                                        <div style="display:flex; align-items:center; gap:8px;">
                                                            <img src="<?= avatarURL($r['avatar']) ?>" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                                                            <a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $r['user_id'] ?>" target="_blank" style="font-weight:600; color:inherit;"><?= temizle($r['username']) ?></a>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($r['role'] === 'captain'): ?>
                                                            <span class="badge badge-warning" style="display:inline-flex; align-items:center; gap:2px;"><i class="fas fa-crown" style="font-size:0.65rem;"></i> Kaptan</span>
                                                        <?php else: ?>
                                                            <form method="POST" style="margin:0; display:inline;">
                                                                <?= csrfInput() ?>
                                                                <input type="hidden" name="aksiyon" value="uye_rol_guncelle">
                                                                <input type="hidden" name="team_id" value="<?= $yonetilen_takim['id'] ?>">
                                                                <input type="hidden" name="member_id" value="<?= $r['user_id'] ?>">
                                                                <select name="rol" class="form-input" style="padding:2px 4px; font-size:0.75rem; width:auto; border-radius:var(--radius-sm);" onchange="this.form.submit()">
                                                                    <option value="player" <?= $r['role']==='player'?'selected':'' ?>>Oyuncu</option>
                                                                    <option value="substitute" <?= $r['role']==='substitute'?'selected':'' ?>>Yedek</option>
                                                                    <option value="coach" <?= $r['role']==='coach'?'selected':'' ?>>Koç</option>
                                                                </select>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align:center;">
                                                        <?php if ($r['role'] !== 'captain'): ?>
                                                            <form method="POST" style="margin:0; display:inline;" onsubmit="return confirm('Bu oyuncuyu takımdan çıkarmak istediğinize emin misiniz?')">
                                                                <?= csrfInput() ?>
                                                                <input type="hidden" name="aksiyon" value="takimdan_cikar">
                                                                <input type="hidden" name="team_id" value="<?= $yonetilen_takim['id'] ?>">
                                                                <input type="hidden" name="member_id" value="<?= $r['user_id'] ?>">
                                                                <button type="submit" class="btn btn-ghost btn-sm" style="padding:4px; border:none; color:var(--danger);" title="Çıkar">
                                                                    <i class="fas fa-user-minus"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span style="color:var(--text-muted); font-size:0.8rem;">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Davet Et -->
                            <div class="kart" style="padding:var(--space-lg);">
                                <h3 style="margin-bottom:var(--space-md); border-bottom:1px solid var(--border-light); padding-bottom:var(--space-xs); font-size:1.1rem;"><i class="fas fa-user-plus" style="color:var(--accent);"></i> Oyuncu Davet Et</h3>
                                <form method="POST" style="display:flex; gap:var(--space-sm); align-items:flex-end;">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="aksiyon" value="takim_davet_et">
                                    <input type="hidden" name="team_id" value="<?= $yonetilen_takim['id'] ?>">
                                    <div class="form-grup" style="margin-bottom:0; flex:1;">
                                        <label class="form-label" for="davet_username" style="margin-bottom:4px; font-size:0.8rem;">Kullanıcı Adı</label>
                                        <input type="text" name="username" id="davet_username" class="form-input" style="padding:8px 12px; font-size:0.85rem;" placeholder="Davet edilecek oyuncunun kullanıcı adı..." required>
                                    </div>
                                    <button type="submit" class="btn btn-primary" style="padding:9px 18px;"><i class="fas fa-paper-plane"></i> Davet Et</button>
                                </form>
                            </div>
                        </div>

                        <!-- Sağ Kolon: Talepler, Davetler, Ayarlar -->
                        <div>
                            <!-- Gelen Talepler -->
                            <div class="kart" style="padding:var(--space-lg); margin-bottom:var(--space-xl);">
                                <h3 style="margin-bottom:var(--space-md); border-bottom:1px solid var(--border-light); padding-bottom:var(--space-xs); font-size:1.1rem;"><i class="fas fa-user-clock" style="color:var(--accent);"></i> Katılma Talepleri (<?= count($gelen_talepler) ?>)</h3>
                                <?php if (empty($gelen_talepler)): ?>
                                    <p style="color:var(--text-muted); font-size:0.85rem; padding:var(--space-md) 0; text-align:center;">Bekleyen katılma talebi bulunmuyor.</p>
                                <?php else: ?>
                                    <div class="tablo-wrapper">
                                        <table class="tablo">
                                            <thead>
                                                <tr>
                                                    <th>Oyuncu</th>
                                                    <th>Not</th>
                                                    <th style="text-align:center; width:90px;">İşlem</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gelen_talepler as $gt): ?>
                                                    <tr>
                                                        <td>
                                                            <div style="display:flex; align-items:center; gap:8px;">
                                                                <img src="<?= avatarURL($gt['avatar']) ?>" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                                                                <a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $gt['user_id'] ?>" target="_blank" style="font-weight:600; color:inherit;"><?= temizle($gt['username']) ?></a>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($gt['message'])): ?>
                                                                <span style="font-size:0.8rem; cursor:help; text-decoration:underline dashed var(--border-hover);" title="<?= temizle($gt['message']) ?>"><?= strlen($gt['message']) > 20 ? temizle(substr($gt['message'], 0, 20)) . '...' : temizle($gt['message']) ?></span>
                                                            <?php else: ?>
                                                                <span style="color:var(--text-muted); font-size:0.8rem;">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td style="text-align:center;">
                                                            <div style="display:flex; gap:4px; justify-content:center;">
                                                                <form method="POST" style="margin:0;">
                                                                    <?= csrfInput() ?>
                                                                    <input type="hidden" name="aksiyon" value="takim_basvuru_cevap">
                                                                    <input type="hidden" name="team_id" value="<?= $yonetilen_takim['id'] ?>">
                                                                    <input type="hidden" name="davet_id" value="<?= $gt['id'] ?>">
                                                                    <input type="hidden" name="cevap" value="accept">
                                                                    <button type="submit" class="btn btn-sm" style="background:var(--success); color:white; border:none; padding:4px 8px; font-size:0.75rem;"><i class="fas fa-check"></i></button>
                                                                </form>
                                                                <form method="POST" style="margin:0;">
                                                                    <?= csrfInput() ?>
                                                                    <input type="hidden" name="aksiyon" value="takim_basvuru_cevap">
                                                                    <input type="hidden" name="team_id" value="<?= $yonetilen_takim['id'] ?>">
                                                                    <input type="hidden" name="davet_id" value="<?= $gt['id'] ?>">
                                                                    <input type="hidden" name="cevap" value="reject">
                                                                    <button type="submit" class="btn btn-sm" style="background:var(--danger); color:white; border:none; padding:4px 8px; font-size:0.75rem;"><i class="fas fa-times"></i></button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Gönderilen Davetler -->
                            <div class="kart" style="padding:var(--space-lg); margin-bottom:var(--space-xl);">
                                <h3 style="margin-bottom:var(--space-md); border-bottom:1px solid var(--border-light); padding-bottom:var(--space-xs); font-size:1.1rem;"><i class="fas fa-paper-plane" style="color:var(--accent);"></i> Gönderilen Davetler (<?= count($gonderilen_davetler) ?>)</h3>
                                <?php if (empty($gonderilen_davetler)): ?>
                                    <p style="color:var(--text-muted); font-size:0.85rem; padding:var(--space-md) 0; text-align:center;">Bekleyen davet bulunmuyor.</p>
                                <?php else: ?>
                                    <div class="tablo-wrapper">
                                        <table class="tablo">
                                            <thead>
                                                <tr>
                                                    <th>Oyuncu</th>
                                                    <th style="text-align:center; width:90px;">İşlem</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gonderilen_davetler as $gd): ?>
                                                    <tr>
                                                        <td>
                                                            <div style="display:flex; align-items:center; gap:8px;">
                                                                <img src="<?= avatarURL($gd['avatar']) ?>" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                                                                <a href="<?= SITE_URL ?>/oyuncu.php?id=<?= $gd['user_id'] ?>" target="_blank" style="font-weight:600; color:inherit;"><?= temizle($gd['username']) ?></a>
                                                            </div>
                                                        </td>
                                                        <td style="text-align:center;">
                                                            <form method="POST" style="margin:0; display:inline;" onsubmit="return confirm('Bu daveti iptal etmek istediğinizden emin misiniz?')">
                                                                <?= csrfInput() ?>
                                                                <input type="hidden" name="aksiyon" value="takim_davet_iptal">
                                                                <input type="hidden" name="team_id" value="<?= $yonetilen_takim['id'] ?>">
                                                                <input type="hidden" name="davet_id" value="<?= $gd['id'] ?>">
                                                                <button type="submit" class="btn btn-ghost btn-sm" style="padding:4px 8px; border:none; color:var(--danger); font-size:0.75rem; text-transform:none; letter-spacing:normal;" title="İptal Et">
                                                                    <i class="fas fa-times"></i> İptal
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Bilgileri Düzenle -->
                            <div class="kart" style="padding:var(--space-lg); margin-bottom:var(--space-xl);">
                                <h3 style="margin-bottom:var(--space-md); border-bottom:1px solid var(--border-light); padding-bottom:var(--space-xs); font-size:1.1rem;"><i class="fas fa-edit" style="color:var(--accent);"></i> Takım Bilgilerini Güncelle</h3>
                                <form method="POST" enctype="multipart/form-data">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="aksiyon" value="takim_guncelle">
                                    <input type="hidden" name="team_id" value="<?= $yonetilen_takim['id'] ?>">
                                    
                                    <div style="display:flex; align-items:center; gap:var(--space-md); margin-bottom:var(--space-md); flex-wrap:wrap;">
                                        <div style="width:60px; height:60px; border-radius:var(--radius-md); border:1px solid var(--border); overflow:hidden; background:var(--bg-surface); flex-shrink:0;">
                                            <img src="<?= takimLogoURL($yonetilen_takim['logo']) ?>" id="teamLogoPreview" style="width:100%; height:100%; object-fit:cover;">
                                        </div>
                                        <div class="form-grup" style="margin-bottom:0; flex:1; min-width:150px;">
                                            <label class="form-label" style="margin-bottom:2px; font-size:0.8rem;">Takım Logosu</label>
                                            <input type="file" name="logo" class="form-input" style="padding:5px 8px; font-size:0.8rem;" onchange="if(this.files[0]) { document.getElementById('teamLogoPreview').src = window.URL.createObjectURL(this.files[0]); }">
                                        </div>
                                    </div>
                                    
                                    <div class="form-grup">
                                        <label class="form-label" for="takim_adi">Takım Adı</label>
                                        <input type="text" name="takim_adi" id="takim_adi" class="form-input" value="<?= temizle($yonetilen_takim['name']) ?>" required>
                                    </div>
                                    
                                    <div class="form-grup">
                                        <label class="form-label" for="takim_ulke">Ülke</label>
                                        <input type="text" name="ulke" id="takim_ulke" class="form-input" value="<?= temizle($yonetilen_takim['country'] ?? 'Türkiye') ?>">
                                    </div>

                                    <div class="form-grup">
                                        <label class="form-label" for="takim_aciklama">Açıklama</label>
                                        <textarea name="aciklama" id="takim_aciklama" class="form-input" rows="3"><?= temizle($yonetilen_takim['description'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-neon btn-block"><i class="fas fa-save"></i> Bilgileri Güncelle</button>
                                </form>
                            </div>

                            <!-- Sil Alanı -->
                            <div class="kart" style="padding:var(--space-lg); border:1px solid rgba(255, 23, 68, 0.2); background:rgba(255, 23, 68, 0.02);">
                                <h3 style="margin-bottom:var(--space-xs); font-size:1.1rem; color:var(--live);"><i class="fas fa-exclamation-triangle"></i> Tehlikeli Alan</h3>
                                <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:var(--space-md);">Takımı silmek geri alınamaz bir işlemdir. Takım üyeleri gruptan çıkarılır ve turnuva kayıtları iptal edilir.</p>
                                <form method="POST" onsubmit="return confirm('Bu takımı tamamen silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="aksiyon" value="takim_sil">
                                    <input type="hidden" name="team_id" value="<?= $yonetilen_takim['id'] ?>">
                                    <button type="submit" class="btn" style="background:var(--live); color:white; border:none; width:100%;"><i class="fas fa-trash-alt"></i> Takımı Tamamen Sil</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php elseif ($tab === 'turnuvalar'): ?>
                <h2 style="margin-bottom:var(--space-lg);">Turnuvalarım</h2>
                <?php if (empty($turnuvalarim)): ?>
                    <div class="bos-durum"><i class="fas fa-trophy"></i><h3>Henüz turnuvaya katılmadınız</h3><p><a href="<?= SITE_URL ?>/turnuvalar.php" style="color:var(--accent);">Turnuvaları keşfedin</a></p></div>
                <?php else: ?>
                    <div class="tablo-wrapper"><table class="tablo"><thead><tr><th>Turnuva</th><th>Takım</th><th>Durum</th><th>Kayıt</th><th>Ödül</th></tr></thead><tbody>
                    <?php foreach ($turnuvalarim as $t): ?>
                        <tr><td class="wrap-text"><a href="<?= SITE_URL ?>/turnuva.php?id=<?= $t['tournament_id'] ?>" style="color:var(--accent);font-weight:600;"><?= temizle($t['turnuva_adi']) ?></a></td>
                        <td><?= temizle($t['takim_adi']) ?></td><td><?= durumBadge($t['turnuva_durumu']) ?></td><td><?= durumBadge($t['status']) ?></td>
                        <td style="color:var(--gold);font-weight:600;"><?= paraFormatla($t['prize_pool']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>

                <?php elseif ($tab === 'bildirimler'): ?>
                <style>
                .btn-bildirim-aksiyon:hover {
                    background: rgba(255, 255, 255, 0.08) !important;
                    color: var(--accent) !important;
                }
                .btn-bildirim-aksiyon.sil-btn:hover {
                    color: var(--danger) !important;
                    background: rgba(220, 53, 69, 0.1) !important;
                }
                .bildirim-link-click:hover .bildirim-kart-baslik {
                    color: var(--accent);
                }
                .bildirim-kart {
                    border: 1px solid rgba(255,255,255,0.05);
                    background: rgba(255, 255, 255, 0.01);
                }
                .bildirim-kart:hover {
                    border-color: rgba(var(--accent-rgb), 0.2);
                    transform: translateY(-1px);
                    box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
                }
                .bildirim-kart.okunmamis:hover {
                    box-shadow: 0 4px 15px rgba(var(--accent-rgb), 0.1) !important;
                }
                </style>

                <div class="bildirimler-baslik-alani" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-lg);flex-wrap:wrap;gap:var(--space-md);">
                    <h2 style="margin:0;"><i class="fas fa-bell" style="color:var(--accent);margin-right:8px;"></i>Bildirim Merkezi</h2>
                    <div class="toplu-islemler" style="display:flex;gap:var(--space-sm);">
                        <?php if ($okunmamis > 0): ?>
                            <button id="tumuOkunduBtn" class="btn btn-ghost btn-sm" style="border:1px solid rgba(var(--accent-rgb), 0.3);"><i class="fas fa-check-double"></i> Tümünü Okundu Yap</button>
                        <?php endif; ?>
                        <?php if ($toplamKayit > 0): ?>
                            <button id="tumuSilBtn" class="btn btn-ghost btn-sm btn-danger" style="border:1px solid rgba(var(--danger-rgb), 0.3);"><i class="fas fa-trash-alt"></i> Tümünü Sil</button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Filtre Butonları -->
                <div class="bildirim-filtreler" style="display:flex;gap:var(--space-xs);margin-bottom:var(--space-md);background:rgba(255,255,255,0.03);padding:4px;border-radius:var(--radius-lg);width:fit-content;border:1px solid rgba(255,255,255,0.05);height:fit-content;align-items:center;">
                    <a href="?tab=bildirimler&filtre=all" class="tab-link <?= $filtre==='all'?'aktif':'' ?>" style="padding:6px 16px;border-radius:var(--radius-md);font-size:0.85rem;font-weight:500;text-decoration:none;display:inline-block;transition:all var(--transition-fast);">Tümü (<?= (int)$db->count("SELECT COUNT(*) FROM bildirimler WHERE user_id = ?", [$kullanici['id']]) ?>)</a>
                    <a href="?tab=bildirimler&filtre=unread" class="tab-link <?= $filtre==='unread'?'aktif':'' ?>" style="padding:6px 16px;border-radius:var(--radius-md);font-size:0.85rem;font-weight:500;text-decoration:none;display:inline-block;transition:all var(--transition-fast);">Okunmamış (<?= $okunmamis ?>)</a>
                    <a href="?tab=bildirimler&filtre=read" class="tab-link <?= $filtre==='read'?'aktif':'' ?>" style="padding:6px 16px;border-radius:var(--radius-md);font-size:0.85rem;font-weight:500;text-decoration:none;display:inline-block;transition:all var(--transition-fast);">Okunmuş (<?= (int)$db->count("SELECT COUNT(*) FROM bildirimler WHERE user_id = ? AND is_read = 1", [$kullanici['id']]) ?>)</a>
                </div>

                <?php if (empty($bildirimler)): ?>
                    <div class="bos-durum" style="padding:var(--space-3xl) var(--space-xl);"><i class="fas fa-bell-slash" style="font-size:3rem;margin-bottom:var(--space-md);color:var(--text-muted);"></i><h3>Bildirim bulunmuyor</h3><p style="color:var(--text-muted);font-size:0.9rem;">Seçili filtreye uygun bildiriminiz bulunmamaktadır.</p></div>
                <?php else: ?>
                    <div class="bildirimler-listesi" style="display:flex;flex-direction:column;gap:var(--space-sm);">
                        <?php foreach ($bildirimler as $b): ?>
                            <?php 
                            $ikonClass = 'bell';
                            $ikonRenk = 'var(--accent)';
                            $bgRenk = 'var(--accent-light)';
                            
                            switch($b['type']) {
                                case 'tournament':
                                    $ikonClass = 'trophy';
                                    $ikonRenk = '#ffd700';
                                    $bgRenk = 'rgba(255, 215, 0, 0.1)';
                                    break;
                                case 'match':
                                    $ikonClass = 'gamepad';
                                    $ikonRenk = '#00ffff';
                                    $bgRenk = 'rgba(0, 255, 255, 0.1)';
                                    break;
                                case 'team':
                                    $ikonClass = 'users';
                                    $ikonRenk = '#ff00ff';
                                    $bgRenk = 'rgba(255, 0, 255, 0.1)';
                                    break;
                                case 'success':
                                    $ikonClass = 'check-circle';
                                    $ikonRenk = 'var(--success)';
                                    $bgRenk = 'rgba(40, 167, 69, 0.1)';
                                    break;
                                case 'warning':
                                    $ikonClass = 'exclamation-triangle';
                                    $ikonRenk = 'var(--warning)';
                                    $bgRenk = 'rgba(255, 193, 7, 0.1)';
                                    break;
                                case 'error':
                                    $ikonClass = 'times-circle';
                                    $ikonRenk = 'var(--danger)';
                                    $bgRenk = 'rgba(220, 53, 69, 0.1)';
                                    break;
                            }
                            ?>
                            <div class="kart bildirim-kart <?= !$b['is_read']?'okunmamis':'' ?>" data-id="<?= $b['id'] ?>" style="padding:var(--space-md) var(--space-lg);display:flex;align-items:center;gap:var(--space-md);transition:all var(--transition-normal);position:relative;<?= !$b['is_read']?'border-left:4px solid var(--accent);background:rgba(255,255,255,0.02);box-shadow:0 0 15px rgba(var(--accent-rgb), 0.05);':'' ?>">
                                <!-- İkon -->
                                <div style="width:40px;height:40px;border-radius:var(--radius-md);background:<?= $bgRenk ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-<?= $ikonClass ?>" style="color:<?= $ikonRenk ?>;font-size:1.1rem;"></i>
                                </div>
                                
                                <!-- İçerik -->
                                <div style="flex:1;min-width:0;">
                                    <?php if (!empty($b['link'])): ?>
                                        <a href="<?= temizle($b['link']) ?>" class="bildirim-link-click" data-id="<?= $b['id'] ?>" style="text-decoration:none;color:inherit;display:block;">
                                            <h4 class="bildirim-kart-baslik" style="font-size:0.95rem;margin-bottom:4px;font-weight:600;transition:color var(--transition-fast);"><?= temizle($b['title']) ?></h4>
                                            <p style="font-size:0.85rem;color:var(--text-secondary);line-height:1.4;margin:0;"><?= temizle($b['message']) ?></p>
                                        </a>
                                    <?php else: ?>
                                        <h4 style="font-size:0.95rem;margin-bottom:4px;font-weight:600;margin-top:0;"><?= temizle($b['title']) ?></h4>
                                        <p style="font-size:0.85rem;color:var(--text-secondary);line-height:1.4;margin:0;"><?= temizle($b['message']) ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Zaman ve Aksiyonlar -->
                                <div style="display:flex;align-items:center;gap:var(--space-md);flex-shrink:0;">
                                    <span style="color:var(--text-muted);font-size:0.75rem;white-space:nowrap;"><?= zamanOnce($b['created_at']) ?></span>
                                    <div class="bildirim-kart-aksiyonlar" style="display:flex;gap:4px;">
                                        <!-- Okundu / Okunmamış Yap Butonu -->
                                        <button class="btn-bildirim-aksiyon durum-degistir-btn" data-id="<?= $b['id'] ?>" data-read="<?= $b['is_read'] ?>" title="<?= $b['is_read']?'Okunmamış olarak işaretle':'Okundu olarak işaretle' ?>" style="background:none;border:none;color:var(--text-muted);cursor:pointer;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:all var(--transition-fast);">
                                            <i class="fas fa-<?= $b['is_read']?'eye-slash':'check' ?>"></i>
                                        </button>
                                        <!-- Sil Butonu -->
                                        <button class="btn-bildirim-aksiyon sil-btn" data-id="<?= $b['id'] ?>" title="Bildirimi Sil" style="background:none;border:none;color:var(--text-muted);cursor:pointer;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:all var(--transition-fast);">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Sayfalama -->
                    <div style="margin-top:var(--space-xl);display:flex;justify-content:center;">
                        <?= sayfalamaHTML($toplamKayit, $sayfa, $limit, '?tab=bildirimler&filtre=' . $filtre . '&') ?>
                    </div>
                <?php endif; ?>

                <?php elseif ($tab === 'ayarlar'): ?>
                <h2 style="margin-bottom:var(--space-lg);">Şifre Değiştir</h2>
                <div class="kart" style="padding:var(--space-xl);max-width:500px;">
                    <form method="POST">
                        <?= csrfInput() ?>
                        <input type="hidden" name="aksiyon" value="sifre_degistir">
                        <div class="form-grup">
                            <label class="form-label">Mevcut Şifre</label>
                            <div class="sifre-kapsayici">
                                <input type="password" name="mevcut_sifre" class="form-input" required>
                                <button type="button" class="sifre-goster-btn" title="Şifreyi Göster/Gizle"><i class="far fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="form-grup">
                            <label class="form-label">Yeni Şifre</label>
                            <div class="sifre-kapsayici">
                                <input type="password" name="yeni_sifre" class="form-input" required minlength="6">
                                <button type="button" class="sifre-goster-btn" title="Şifreyi Göster/Gizle"><i class="far fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="form-grup">
                            <label class="form-label">Yeni Şifre (Tekrar)</label>
                            <div class="sifre-kapsayici">
                                <input type="password" name="yeni_sifre_tekrar" class="form-input" required>
                                <button type="button" class="sifre-goster-btn" title="Şifreyi Göster/Gizle"><i class="far fa-eye"></i></button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Şifre Değiştir</button>
                    </form>
                </div>

                <!-- E-posta Bildirim Tercihleri -->
                <h2 style="margin-top:var(--space-2xl);margin-bottom:var(--space-lg);">
                    <i class="fas fa-envelope" style="color:var(--accent);margin-right:8px;"></i>E-posta Bildirimleri
                </h2>
                <div class="kart" style="padding:var(--space-xl);max-width:500px;">
                    <p style="color:var(--text-secondary);font-size:0.85rem;margin-bottom:var(--space-lg);line-height:1.6;">
                        Hangi durumlarda e-posta bildirimi almak istediğinizi ayarlayın.
                    </p>
                    <form method="POST">
                        <?= csrfInput() ?>
                        <input type="hidden" name="aksiyon" value="eposta_tercihleri">
                        
                        <div style="display:flex;flex-direction:column;gap:var(--space-md);">
                            <!-- Ana bildirim toggle -->
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-md);background:var(--bg-surface);border-radius:var(--radius-md);border:1px solid var(--border-light);">
                                <div>
                                    <div style="font-weight:600;font-size:0.9rem;margin-bottom:2px;">
                                        <i class="fas fa-bell" style="color:var(--accent);margin-right:6px;"></i>Genel E-posta Bildirimleri
                                    </div>
                                    <div style="color:var(--text-muted);font-size:0.75rem;">Tüm e-posta bildirimlerini açar/kapatır</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="eposta_bildirim" <?= ($kullanici['eposta_bildirim'] ?? 1) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <!-- Turnuva bildirimleri -->
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-md);background:var(--bg-surface);border-radius:var(--radius-md);border:1px solid var(--border-light);">
                                <div>
                                    <div style="font-weight:600;font-size:0.9rem;margin-bottom:2px;">
                                        <i class="fas fa-trophy" style="color:#f39c12;margin-right:6px;"></i>Turnuva Bildirimleri
                                    </div>
                                    <div style="color:var(--text-muted);font-size:0.75rem;">Kayıt onayı, ret ve turnuva güncellemeleri</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="eposta_turnuva" <?= ($kullanici['eposta_turnuva'] ?? 1) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <!-- Maç hatırlatmaları -->
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-md);background:var(--bg-surface);border-radius:var(--radius-md);border:1px solid var(--border-light);">
                                <div>
                                    <div style="font-weight:600;font-size:0.9rem;margin-bottom:2px;">
                                        <i class="fas fa-crosshairs" style="color:#e74c3c;margin-right:6px;"></i>Maç Hatırlatmaları
                                    </div>
                                    <div style="color:var(--text-muted);font-size:0.75rem;">Yaklaşan maç bildirimleri ve sonuçlar</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="eposta_mac" <?= ($kullanici['eposta_mac'] ?? 1) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <!-- Takım bildirimleri -->
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-md);background:var(--bg-surface);border-radius:var(--radius-md);border:1px solid var(--border-light);">
                                <div>
                                    <div style="font-weight:600;font-size:0.9rem;margin-bottom:2px;">
                                        <i class="fas fa-users" style="color:#00b894;margin-right:6px;"></i>Takım Bildirimleri
                                    </div>
                                    <div style="color:var(--text-muted);font-size:0.75rem;">Takım daveti ve üyelik değişiklikleri</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="eposta_takim" <?= ($kullanici['eposta_takim'] ?? 1) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top:var(--space-lg);">
                            <i class="fas fa-save"></i> Tercihleri Kaydet
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/alt.php'; ?>
