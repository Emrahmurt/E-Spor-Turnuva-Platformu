<?php
/**
 * E-Spor Turnuva Platformu - E-posta Bildirim Sistemi
 * 
 * Profesyonel HTML e-posta şablonları ile bildirim gönderir.
 * SMTP veya PHP mail() fonksiyonu destekler.
 */

// E-posta Ayarları
define('EPOSTA_GONDEREN_AD', SITE_NAME);
define('EPOSTA_GONDEREN_ADRES', 'noreply@esporturnuva.com');
define('EPOSTA_YANITLA', 'info@esporturnuva.com');

// SMTP Ayarları (isteğe bağlı - boş bırakılırsa PHP mail() kullanılır)
define('SMTP_HOST', '');       // ör: 'smtp.gmail.com'
define('SMTP_PORT', 587);      // ör: 587 (TLS) veya 465 (SSL)
define('SMTP_USER', '');       // ör: 'email@gmail.com'
define('SMTP_PASS', '');       // ör: 'uygulama-sifresi'
define('SMTP_SIFRELEME', 'tls'); // 'tls' veya 'ssl'

/**
 * Ana e-posta gönderme fonksiyonu
 */
function epostaGonder($aliciEmail, $aliciAd, $konu, $icerik, $tip = 'bildirim') {
    $html = epostaSablonu($konu, $icerik, $tip);
    
    // E-posta log'la
    epostaLogla($aliciEmail, $konu, $tip);
    
    // SMTP varsa SMTP kullan, yoksa PHP mail()
    if (!empty(SMTP_HOST)) {
        return smtpGonder($aliciEmail, $aliciAd, $konu, $html);
    }
    
    return phpMailGonder($aliciEmail, $aliciAd, $konu, $html);
}

/**
 * PHP mail() ile gönderme
 */
function phpMailGonder($aliciEmail, $aliciAd, $konu, $htmlIcerik) {
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . EPOSTA_GONDEREN_AD . ' <' . EPOSTA_GONDEREN_ADRES . '>';
    $headers[] = 'Reply-To: ' . EPOSTA_YANITLA;
    $headers[] = 'X-Mailer: EsporTurnuva/1.0';
    
    return @mail(
        $aliciAd ? "$aliciAd <$aliciEmail>" : $aliciEmail,
        '=?UTF-8?B?' . base64_encode($konu) . '?=',
        $htmlIcerik,
        implode("\r\n", $headers)
    );
}

/**
 * SMTP ile gönderme (fsockopen tabanlı, harici kütüphane gerektirmez)
 */
function smtpGonder($aliciEmail, $aliciAd, $konu, $htmlIcerik) {
    try {
        $smtp = @fsockopen(
            (SMTP_SIFRELEME === 'ssl' ? 'ssl://' : '') . SMTP_HOST, 
            SMTP_PORT, 
            $errno, $errstr, 15
        );
        
        if (!$smtp) {
            error_log("SMTP bağlantı hatası: $errstr ($errno)");
            // Fallback: PHP mail()
            return phpMailGonder($aliciEmail, $aliciAd, $konu, $htmlIcerik);
        }
        
        // SMTP komutları
        smtpOku($smtp);
        smtpKomut($smtp, "EHLO " . gethostname());
        
        if (SMTP_SIFRELEME === 'tls') {
            smtpKomut($smtp, "STARTTLS");
            stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            smtpKomut($smtp, "EHLO " . gethostname());
        }
        
        // Kimlik doğrulama
        if (!empty(SMTP_USER)) {
            smtpKomut($smtp, "AUTH LOGIN");
            smtpKomut($smtp, base64_encode(SMTP_USER));
            smtpKomut($smtp, base64_encode(SMTP_PASS));
        }
        
        smtpKomut($smtp, "MAIL FROM:<" . EPOSTA_GONDEREN_ADRES . ">");
        smtpKomut($smtp, "RCPT TO:<$aliciEmail>");
        smtpKomut($smtp, "DATA");
        
        // E-posta içeriği
        $mesaj = "From: " . EPOSTA_GONDEREN_AD . " <" . EPOSTA_GONDEREN_ADRES . ">\r\n";
        $mesaj .= "To: $aliciAd <$aliciEmail>\r\n";
        $mesaj .= "Subject: =?UTF-8?B?" . base64_encode($konu) . "?=\r\n";
        $mesaj .= "MIME-Version: 1.0\r\n";
        $mesaj .= "Content-Type: text/html; charset=UTF-8\r\n";
        $mesaj .= "Reply-To: " . EPOSTA_YANITLA . "\r\n";
        $mesaj .= "\r\n";
        $mesaj .= $htmlIcerik . "\r\n.\r\n";
        
        fwrite($smtp, $mesaj);
        smtpOku($smtp);
        
        smtpKomut($smtp, "QUIT");
        fclose($smtp);
        
        return true;
    } catch (Exception $e) {
        error_log("SMTP hata: " . $e->getMessage());
        return phpMailGonder($aliciEmail, $aliciAd, $konu, $htmlIcerik);
    }
}

function smtpKomut($smtp, $komut) {
    fwrite($smtp, $komut . "\r\n");
    return smtpOku($smtp);
}

function smtpOku($smtp) {
    $yanit = '';
    while ($satir = fgets($smtp, 515)) {
        $yanit .= $satir;
        if (substr($satir, 3, 1) == ' ') break;
    }
    return $yanit;
}

/**
 * E-posta log kaydı
 */
function epostaLogla($email, $konu, $tip) {
    global $db;
    try {
        $db->query(
            "INSERT INTO eposta_log (alici_email, konu, tip, gonderim_tarihi) VALUES (?, ?, ?, NOW())",
            [$email, $konu, $tip]
        );
    } catch (Exception $e) {
        // Log tablosu yoksa sessizce geç
    }
}

// =====================================================
// E-POSTA ŞABLONLARI
// =====================================================

/**
 * Ana HTML e-posta şablonu
 */
function epostaSablonu($konu, $icerik, $tip = 'bildirim') {
    $renkler = [
        'bildirim' => ['bg' => '#6c5ce7', 'ikon' => '🔔'],
        'turnuva'  => ['bg' => '#f39c12', 'ikon' => '🏆'],
        'mac'      => ['bg' => '#e74c3c', 'ikon' => '⚔️'],
        'takim'    => ['bg' => '#00b894', 'ikon' => '👥'],
        'hosgeldin' => ['bg' => '#6c5ce7', 'ikon' => '🎮'],
        'sifre'    => ['bg' => '#e17055', 'ikon' => '🔐'],
    ];
    
    $renk = $renkler[$tip] ?? $renkler['bildirim'];
    $yil = date('Y');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$konu}</title>
</head>
<body style="margin:0;padding:0;background-color:#0a0a0f;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0a0a0f;padding:20px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
                    
                    <!-- HEADER -->
                    <tr>
                        <td style="background:linear-gradient(135deg, {$renk['bg']}, #2d3436);padding:30px 40px;border-radius:16px 16px 0 0;text-align:center;">
                            <div style="font-size:40px;margin-bottom:10px;">{$renk['ikon']}</div>
                            <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:0.5px;">
                                E-SPOR<span style="color:#00f5d4;">TURNUVA</span>
                            </h1>
                            <p style="margin:5px 0 0;color:rgba(255,255,255,0.7);font-size:13px;">
                                Profesyonel E-Spor Turnuva Platformu
                            </p>
                        </td>
                    </tr>
                    
                    <!-- İÇERİK -->
                    <tr>
                        <td style="background-color:#12121a;padding:35px 40px;border-left:1px solid rgba(255,255,255,0.05);border-right:1px solid rgba(255,255,255,0.05);">
                            {$icerik}
                        </td>
                    </tr>
                    
                    <!-- FOOTER -->
                    <tr>
                        <td style="background-color:#0d0d15;padding:25px 40px;border-radius:0 0 16px 16px;border:1px solid rgba(255,255,255,0.05);border-top:none;text-align:center;">
                            <p style="margin:0 0 10px;color:rgba(255,255,255,0.4);font-size:12px;">
                                Bu e-posta <strong>E-Spor Turnuva</strong> platformu tarafından otomatik gönderilmiştir.
                            </p>
                            <p style="margin:0 0 10px;color:rgba(255,255,255,0.3);font-size:11px;">
                                Bildirimleri kapatmak için <a href="#" style="color:{$renk['bg']};text-decoration:none;">hesap ayarlarınızı</a> düzenleyin.
                            </p>
                            <div style="margin-top:15px;padding-top:15px;border-top:1px solid rgba(255,255,255,0.05);">
                                <a href="#" style="color:rgba(255,255,255,0.3);text-decoration:none;margin:0 8px;font-size:16px;">🎮</a>
                                <a href="#" style="color:rgba(255,255,255,0.3);text-decoration:none;margin:0 8px;font-size:16px;">💬</a>
                                <a href="#" style="color:rgba(255,255,255,0.3);text-decoration:none;margin:0 8px;font-size:16px;">📺</a>
                            </div>
                            <p style="margin:10px 0 0;color:rgba(255,255,255,0.2);font-size:10px;">
                                &copy; {$yil} E-Spor Turnuva. Tüm hakları saklıdır.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

// =====================================================
// HAZIR BİLDİRİM FONKSİYONLARI
// =====================================================

/**
 * Hoş geldin e-postası (kayıt sonrası)
 */
function epostaHosgeldin($kullanici) {
    $ad = htmlspecialchars($kullanici['first_name'] ?: $kullanici['username']);
    $panelUrl = SITE_URL . '/panel.php';
    $turnuvalarUrl = SITE_URL . '/turnuvalar.php';
    
    $icerik = <<<HTML
    <h2 style="margin:0 0 15px;color:#ffffff;font-size:20px;">Hoş Geldin, {$ad}! 🎮</h2>
    <p style="color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 20px;">
        <strong>E-Spor Turnuva</strong> ailesine katıldığın için teşekkür ederiz! 
        Artık turnuvalara katılabilir, takım kurabilir ve rakiplerinle yarışabilirsin.
    </p>
    
    <div style="background:rgba(108,92,231,0.1);border:1px solid rgba(108,92,231,0.2);border-radius:12px;padding:20px;margin:20px 0;">
        <h3 style="margin:0 0 12px;color:#6c5ce7;font-size:15px;">📋 Hızlı Başlangıç</h3>
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding:6px 0;color:rgba(255,255,255,0.6);font-size:14px;">
                    <span style="color:#00f5d4;margin-right:8px;">✓</span> Profilini düzenle ve avatarını yükle
                </td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:rgba(255,255,255,0.6);font-size:14px;">
                    <span style="color:#00f5d4;margin-right:8px;">✓</span> Discord ve Steam hesaplarını bağla
                </td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:rgba(255,255,255,0.6);font-size:14px;">
                    <span style="color:#00f5d4;margin-right:8px;">✓</span> Bir takım oluştur veya mevcut takıma katıl
                </td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:rgba(255,255,255,0.6);font-size:14px;">
                    <span style="color:#00f5d4;margin-right:8px;">✓</span> Aktif turnuvaları keşfet ve kayıt ol
                </td>
            </tr>
        </table>
    </div>
    
    <div style="text-align:center;margin:25px 0 10px;">
        <a href="{$panelUrl}" style="display:inline-block;background:linear-gradient(135deg,#6c5ce7,#a29bfe);color:#fff;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:700;font-size:14px;letter-spacing:0.5px;">
            🚀 Panelime Git
        </a>
    </div>
    <div style="text-align:center;margin:10px 0;">
        <a href="{$turnuvalarUrl}" style="color:#6c5ce7;text-decoration:none;font-size:13px;">
            Turnuvaları Keşfet →
        </a>
    </div>
HTML;
    
    return epostaGonder(
        $kullanici['email'],
        $ad,
        'Hoş Geldin, ' . $ad . '! 🎮 E-Spor Turnuva',
        $icerik,
        'hosgeldin'
    );
}

/**
 * Turnuva kayıt onayı e-postası
 */
function epostaTurnuvaKayit($kullanici, $turnuva, $takim) {
    $ad = htmlspecialchars($kullanici['first_name'] ?: $kullanici['username']);
    $turnuvaAdi = htmlspecialchars($turnuva['name']);
    $takimAdi = htmlspecialchars($takim['name']);
    $turnuvaUrl = SITE_URL . '/turnuva.php?id=' . $turnuva['id'];
    $tarih = tarihFormatla($turnuva['start_date'], 'short');
    $odul = paraFormatla($turnuva['prize_pool']);
    
    $icerik = <<<HTML
    <h2 style="margin:0 0 15px;color:#ffffff;font-size:20px;">Turnuva Kaydınız Alındı! 🏆</h2>
    <p style="color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 20px;">
        Merhaba <strong>{$ad}</strong>, <strong style="color:#f39c12;">{$turnuvaAdi}</strong> turnuvasına 
        <strong style="color:#00f5d4;">{$takimAdi}</strong> takımı ile kaydınız başarıyla alınmıştır.
    </p>
    
    <div style="background:rgba(243,156,18,0.08);border:1px solid rgba(243,156,18,0.2);border-radius:12px;padding:20px;margin:20px 0;">
        <h3 style="margin:0 0 15px;color:#f39c12;font-size:15px;">📌 Turnuva Detayları</h3>
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding:8px 0;color:rgba(255,255,255,0.5);font-size:13px;width:120px;">Turnuva</td>
                <td style="padding:8px 0;color:#ffffff;font-size:14px;font-weight:600;">{$turnuvaAdi}</td>
            </tr>
            <tr>
                <td style="padding:8px 0;color:rgba(255,255,255,0.5);font-size:13px;">Takım</td>
                <td style="padding:8px 0;color:#00f5d4;font-size:14px;font-weight:600;">{$takimAdi}</td>
            </tr>
            <tr>
                <td style="padding:8px 0;color:rgba(255,255,255,0.5);font-size:13px;">Başlangıç</td>
                <td style="padding:8px 0;color:#ffffff;font-size:14px;">{$tarih}</td>
            </tr>
            <tr>
                <td style="padding:8px 0;color:rgba(255,255,255,0.5);font-size:13px;">Ödül Havuzu</td>
                <td style="padding:8px 0;color:#ffd700;font-size:14px;font-weight:700;">{$odul}</td>
            </tr>
            <tr>
                <td style="padding:8px 0;color:rgba(255,255,255,0.5);font-size:13px;">Durum</td>
                <td style="padding:8px 0;">
                    <span style="background:rgba(243,156,18,0.2);color:#f39c12;padding:4px 12px;border-radius:6px;font-size:12px;font-weight:600;">
                        ⏳ Onay Bekliyor
                    </span>
                </td>
            </tr>
        </table>
    </div>
    
    <div style="background:rgba(255,255,255,0.03);border-radius:8px;padding:14px 18px;margin:15px 0;">
        <p style="margin:0;color:rgba(255,255,255,0.5);font-size:13px;line-height:1.6;">
            <strong style="color:rgba(255,255,255,0.7);">ℹ️ Not:</strong> Kaydınız yöneticiler tarafından onaylandığında size tekrar bildirim gönderilecektir. 
            Turnuva başlamadan önce takım kadronuzun eksiksiz olduğundan emin olun.
        </p>
    </div>
    
    <div style="text-align:center;margin:25px 0 10px;">
        <a href="{$turnuvaUrl}" style="display:inline-block;background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:700;font-size:14px;">
            🏆 Turnuva Sayfası
        </a>
    </div>
HTML;
    
    return epostaGonder(
        $kullanici['email'],
        $ad,
        '🏆 Turnuva Kaydınız Alındı: ' . $turnuvaAdi,
        $icerik,
        'turnuva'
    );
}

/**
 * Turnuva kayıt onaylandı e-postası
 */
function epostaTurnuvaOnay($kullanici, $turnuva, $takim) {
    $ad = htmlspecialchars($kullanici['first_name'] ?: $kullanici['username']);
    $turnuvaAdi = htmlspecialchars($turnuva['name']);
    $takimAdi = htmlspecialchars($takim['name']);
    $turnuvaUrl = SITE_URL . '/turnuva.php?id=' . $turnuva['id'];
    $tarih = tarihFormatla($turnuva['start_date'], 'short');
    
    $icerik = <<<HTML
    <h2 style="margin:0 0 15px;color:#ffffff;font-size:20px;">Kaydınız Onaylandı! ✅</h2>
    <p style="color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 20px;">
        Tebrikler <strong>{$ad}</strong>! <strong style="color:#00b894;">{$takimAdi}</strong> takımınızın 
        <strong style="color:#f39c12;">{$turnuvaAdi}</strong> turnuvasına kaydı <strong style="color:#00b894;">onaylanmıştır</strong>.
    </p>
    
    <div style="background:rgba(0,184,148,0.08);border:1px solid rgba(0,184,148,0.25);border-radius:12px;padding:20px;margin:20px 0;text-align:center;">
        <div style="font-size:48px;margin-bottom:10px;">🎉</div>
        <h3 style="margin:0 0 5px;color:#00b894;font-size:18px;">Turnuvaya Hazırsınız!</h3>
        <p style="margin:0;color:rgba(255,255,255,0.5);font-size:13px;">
            Başlangıç: <strong style="color:#fff;">{$tarih}</strong>
        </p>
    </div>
    
    <div style="background:rgba(255,255,255,0.03);border-radius:8px;padding:14px 18px;margin:15px 0;">
        <p style="margin:0;color:rgba(255,255,255,0.5);font-size:13px;line-height:1.6;">
            <strong style="color:rgba(255,255,255,0.7);">📢 Hatırlatma:</strong> Turnuva eşleşmeleri ve maç programı, 
            turnuva başladığında otomatik olarak oluşturulacaktır. Takım kadronuzun hazır olduğundan emin olun.
        </p>
    </div>
    
    <div style="text-align:center;margin:25px 0 10px;">
        <a href="{$turnuvaUrl}" style="display:inline-block;background:linear-gradient(135deg,#00b894,#00cec9);color:#fff;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:700;font-size:14px;">
            🏆 Turnuvayı Görüntüle
        </a>
    </div>
HTML;
    
    return epostaGonder(
        $kullanici['email'],
        $ad,
        '✅ Turnuva Kaydınız Onaylandı: ' . $turnuvaAdi,
        $icerik,
        'turnuva'
    );
}

/**
 * Turnuva kayıt reddedildi e-postası
 */
function epostaTurnuvaRed($kullanici, $turnuva, $takim, $neden = '') {
    $ad = htmlspecialchars($kullanici['first_name'] ?: $kullanici['username']);
    $turnuvaAdi = htmlspecialchars($turnuva['name']);
    $takimAdi = htmlspecialchars($takim['name']);
    $turnuvalarUrl = SITE_URL . '/turnuvalar.php';
    $nedenHTML = $neden ? '<p style="margin:10px 0 0;color:rgba(255,255,255,0.6);font-size:13px;"><strong>Gerekçe:</strong> ' . htmlspecialchars($neden) . '</p>' : '';
    
    $icerik = <<<HTML
    <h2 style="margin:0 0 15px;color:#ffffff;font-size:20px;">Kayıt Reddedildi ❌</h2>
    <p style="color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 20px;">
        Merhaba <strong>{$ad}</strong>, üzgünüz ancak <strong style="color:#e74c3c;">{$takimAdi}</strong> takımınızın 
        <strong>{$turnuvaAdi}</strong> turnuvasına kaydı reddedilmiştir.
    </p>
    
    <div style="background:rgba(231,76,60,0.08);border:1px solid rgba(231,76,60,0.2);border-radius:12px;padding:20px;margin:20px 0;">
        <div style="color:#e74c3c;font-size:14px;font-weight:600;">⚠️ Kayıt Durumu: Reddedildi</div>
        {$nedenHTML}
    </div>
    
    <p style="color:rgba(255,255,255,0.5);font-size:13px;line-height:1.6;">
        Diğer turnuvalara göz atabilir veya daha fazla bilgi için bizimle iletişime geçebilirsiniz.
    </p>
    
    <div style="text-align:center;margin:25px 0 10px;">
        <a href="{$turnuvalarUrl}" style="display:inline-block;background:linear-gradient(135deg,#6c5ce7,#a29bfe);color:#fff;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:700;font-size:14px;">
            🔍 Diğer Turnuvaları Keşfet
        </a>
    </div>
HTML;
    
    return epostaGonder(
        $kullanici['email'],
        $ad,
        '❌ Turnuva Kaydınız Reddedildi: ' . $turnuvaAdi,
        $icerik,
        'turnuva'
    );
}

/**
 * Maç hatırlatma e-postası
 */
function epostaMacHatirlatma($kullanici, $mac, $takim, $rakipTakim) {
    $ad = htmlspecialchars($kullanici['first_name'] ?: $kullanici['username']);
    $takimAdi = htmlspecialchars($takim['name']);
    $rakipAdi = htmlspecialchars($rakipTakim['name']);
    $macTarihi = tarihFormatla($mac['scheduled_at'] ?? $mac['started_at'], 'long');
    
    $icerik = <<<HTML
    <h2 style="margin:0 0 15px;color:#ffffff;font-size:20px;">Maçınız Yaklaşıyor! ⚔️</h2>
    <p style="color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 20px;">
        Merhaba <strong>{$ad}</strong>, yaklaşan bir maçınız var!
    </p>
    
    <div style="background:rgba(231,76,60,0.06);border:1px solid rgba(231,76,60,0.15);border-radius:16px;padding:25px;margin:20px 0;text-align:center;">
        <div style="display:inline-block;margin:0 15px;">
            <div style="width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,0.05);margin:0 auto 8px;display:flex;align-items:center;justify-content:center;">
                <span style="font-size:24px;">🛡️</span>
            </div>
            <div style="color:#00f5d4;font-size:14px;font-weight:700;">{$takimAdi}</div>
        </div>
        <div style="display:inline-block;margin:0 15px;">
            <div style="color:rgba(255,255,255,0.3);font-size:24px;font-weight:800;">VS</div>
        </div>
        <div style="display:inline-block;margin:0 15px;">
            <div style="width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,0.05);margin:0 auto 8px;display:flex;align-items:center;justify-content:center;">
                <span style="font-size:24px;">🛡️</span>
            </div>
            <div style="color:#e74c3c;font-size:14px;font-weight:700;">{$rakipAdi}</div>
        </div>
        <div style="margin-top:15px;padding-top:15px;border-top:1px solid rgba(255,255,255,0.05);">
            <span style="color:rgba(255,255,255,0.5);font-size:13px;">📅 {$macTarihi}</span>
        </div>
    </div>
    
    <div style="background:rgba(255,171,0,0.08);border-radius:8px;padding:14px 18px;margin:15px 0;">
        <p style="margin:0;color:rgba(255,255,255,0.6);font-size:13px;line-height:1.6;">
            <strong style="color:#ffab00;">⚡ Hazırlık:</strong> Takım arkadaşlarınızla iletişimde kalın, 
            strateji belirleyin ve maç saatine hazır olun. İyi şanslar!
        </p>
    </div>
HTML;
    
    return epostaGonder(
        $kullanici['email'],
        $ad,
        '⚔️ Maçınız Yaklaşıyor: ' . $takimAdi . ' vs ' . $rakipAdi,
        $icerik,
        'mac'
    );
}

/**
 * Takıma davet e-postası
 */
function epostaTakimDavet($kullanici, $takim, $davetEden) {
    $ad = htmlspecialchars($kullanici['first_name'] ?: $kullanici['username']);
    $takimAdi = htmlspecialchars($takim['name']);
    $davetEdenAd = htmlspecialchars($davetEden['username']);
    $panelUrl = SITE_URL . '/panel.php?tab=takimlar';
    
    $icerik = <<<HTML
    <h2 style="margin:0 0 15px;color:#ffffff;font-size:20px;">Takım Daveti! 👥</h2>
    <p style="color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 20px;">
        Merhaba <strong>{$ad}</strong>! <strong style="color:#00f5d4;">{$davetEdenAd}</strong> sizi 
        <strong style="color:#6c5ce7;">{$takimAdi}</strong> takımına davet etti.
    </p>
    
    <div style="background:rgba(0,184,148,0.08);border:1px solid rgba(0,184,148,0.2);border-radius:12px;padding:20px;margin:20px 0;text-align:center;">
        <div style="font-size:40px;margin-bottom:10px;">🤝</div>
        <h3 style="margin:0;color:#00b894;font-size:16px;">{$takimAdi}</h3>
        <p style="margin:5px 0 0;color:rgba(255,255,255,0.4);font-size:12px;">
            Davet eden: {$davetEdenAd}
        </p>
    </div>
    
    <div style="text-align:center;margin:25px 0 10px;">
        <a href="{$panelUrl}" style="display:inline-block;background:linear-gradient(135deg,#00b894,#00cec9);color:#fff;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:700;font-size:14px;">
            ✅ Daveti Görüntüle
        </a>
    </div>
HTML;
    
    return epostaGonder(
        $kullanici['email'],
        $ad,
        '👥 Takım Davetiyesi: ' . $takimAdi,
        $icerik,
        'takim'
    );
}

/**
 * Genel bildirim e-postası (veritabanındaki bildirimlerle entegre)
 */
function epostaBildirim($kullanici, $baslik, $mesaj, $link = '') {
    $ad = htmlspecialchars($kullanici['first_name'] ?: $kullanici['username']);
    $baslikHTML = htmlspecialchars($baslik);
    $mesajHTML = htmlspecialchars($mesaj);
    
    $butonHTML = $link ? '<div style="text-align:center;margin:25px 0 10px;">
        <a href="' . $link . '" style="display:inline-block;background:linear-gradient(135deg,#6c5ce7,#a29bfe);color:#fff;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:700;font-size:14px;">
            🔗 Detayları Gör
        </a>
    </div>' : '';
    
    $icerik = <<<HTML
    <h2 style="margin:0 0 15px;color:#ffffff;font-size:20px;">{$baslikHTML}</h2>
    <p style="color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 20px;">
        Merhaba <strong>{$ad}</strong>,
    </p>
    <div style="background:rgba(108,92,231,0.08);border:1px solid rgba(108,92,231,0.2);border-radius:12px;padding:20px;margin:20px 0;">
        <p style="margin:0;color:rgba(255,255,255,0.7);font-size:14px;line-height:1.7;">
            {$mesajHTML}
        </p>
    </div>
    {$butonHTML}
HTML;
    
    return epostaGonder(
        $kullanici['email'],
        $ad,
        $baslik,
        $icerik,
        'bildirim'
    );
}
