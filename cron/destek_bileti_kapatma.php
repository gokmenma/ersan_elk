<?php
/**
 * Otomatik Destek Talebi Kapatma Cron Script'i
 * 
 * Bu script her saat başında çalıştırılmalıdır.
 * 24 saattir işlem görmeyen ve durumu 'kapali' olmayan destek taleplerini otomatik olarak kapatır.
 * İlgili kullanıcıya mail olarak bildirir.
 * 
 * Windows Task Scheduler için:
 * Program: C:\xampp\php\php.exe
 * Arguments: C:\xampp\htdocs\ersan_elk\cron\destek_bileti_kapatma.php
 * 
 * Linux Crontab için:
 * 0 * * * * /usr/bin/php /path/to/ersan_elk/cron/destek_bileti_kapatma.php >> /var/log/destek_bileti_kapatma.log 2>&1
 */

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Zaman dilimi
date_default_timezone_set('Europe/Istanbul');

// Log dosyası setup
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/destek_bileti_kapatma_' . date('Y-m') . '.log';

/**
 * Log yazar
 */
function writeLog($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

writeLog("=== Destek Talebi Kapatma İşlemi Başladı ===", $logFile);

try {
    // Proje kök dizinini ekle (Autoloader için)
    require_once dirname(__DIR__) . '/bootstrap.php';
    
    $db = getDbConnection();
    
    // 24 saatten eski ve kapalı olmayan talepleri getir
    // Hem users hem de personel tablosuyla joinliyoruz ki emaillere ulaşalım
    $sql = "SELECT db.*, 
                   COALESCE(u.email_adresi, p.email_adresi) as email,
                   COALESCE(u.adi_soyadi, p.adi_soyadi) as adi_soyadi
            FROM destek_biletleri db
            LEFT JOIN users u ON db.user_id = u.id
            LEFT JOIN personel p ON db.personel_id = p.id
            WHERE db.durum != 'kapali' 
            AND db.guncelleme_tarihi < (NOW() - INTERVAL 1 DAY)";
            
    $stmt = $db->query($sql);
    $tickets = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    $count = count($tickets);
    if ($count > 0) {
        writeLog("{$count} adet kapatılacak talep bulundu.", $logFile);
        
        foreach ($tickets as $ticket) {
            try {
                $db->beginTransaction();

                // Talebi kapat
                $updateSql = "UPDATE destek_biletleri 
                              SET durum = 'kapali', 
                                  kapatan_user_id = 0, -- 0: Sistem
                                  kapatma_tarihi = NOW(),
                                  guncelleme_tarihi = NOW() 
                              WHERE id = ?";
                $db->prepare($updateSql)->execute([$ticket->id]);

                // Sistem mesajı ekle
                $mesajSql = "INSERT INTO destek_bilet_mesajlari (bilet_id, gonderen_tip, gonderen_id, mesaj) 
                             VALUES (?, 'sistem', 0, 'Talep 24 saat hareketsiz kaldığı için sistem tarafından otomatik kapatıldı.')";
                $db->prepare($mesajSql)->execute([$ticket->id]);

                $db->commit();
                
                writeLog("Talebi Kapatıldı: [{$ticket->ref_no}] {$ticket->konu} (User: {$ticket->adi_soyadi})", $logFile);
                
                // Mail gönder
                if (!empty($ticket->email)) {
                    try {
                        $konu = "Destek Talebiniz Otomatik Kapatıldı: {$ticket->ref_no}";
                        $icerik = "
                            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                                <div style='background: #f8f9fa; padding: 20px; border-radius: 5px; border: 1px solid #dee2e6;'>
                                    <h2 style='color: #0d6efd;'>Sayın {$ticket->adi_soyadi},</h2>
                                    <p><strong>{$ticket->ref_no}</strong> numaralı ve <strong>'{$ticket->konu}'</strong> konulu destek talebiniz, son 24 saat içinde herhangi bir işlem görmediği için sistem tarafından otomatik olarak <strong>kapatılmıştır</strong>.</p>
                                    <p>Sorununuz devam ediyorsa veya eklemek istediğiniz bilgiler varsa lütfen yeni bir destek talebi oluşturunuz.</p>
                                    <div style='margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 13px; color: #666;'>
                                        Ersan Elektrik Destek Sistemi<br>
                                        <small>Bu e-posta sistem tarafından otomatik gönderilmiştir. Lütfen yanıtlamayınız.</small>
                                    </div>
                                </div>
                            </div>
                        ";
                        
                        // MailGonderService::gonder kullanırken firma_id CLI'da varsayılan 1 alınır
                        \App\Service\MailGonderService::gonder(
                            [$ticket->email], 
                            $konu, 
                            $icerik
                        );
                        writeLog("Mail gönderildi: {$ticket->email}", $logFile);
                    } catch (\Exception $me) {
                        writeLog("Mail gönderim hatası (Referans: {$ticket->ref_no}): " . $me->getMessage(), $logFile);
                    }
                } else {
                    writeLog("Mail adresi bulunamadı, bildirim gönderilemedi (Referans: {$ticket->ref_no})", $logFile);
                }

            } catch (\Exception $innerEx) {
                $db->rollBack();
                writeLog("Veritabanı hatası (Referans: {$ticket->ref_no}): " . $innerEx->getMessage(), $logFile);
            }
        }
    } else {
        writeLog("Kapatılacak kriterlere uygun talep bulunamadı.", $logFile);
    }
    
    writeLog("=== İşlem Tamamlandı ===", $logFile);
    
    if (php_sapi_name() === 'cli') {
        echo "İşlem tamamlandı. {$count} talep işlendi.\n";
    }

} catch (\Exception $e) {
    $errorMsg = "KRİTİK HATA: " . $e->getMessage();
    writeLog($errorMsg, $logFile);
    if (php_sapi_name() === 'cli') {
        echo $errorMsg . "\n";
    }
}
