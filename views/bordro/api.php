<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Model\BordroDonemModel;
use App\Model\BordroPersonelModel;
use App\Model\PersonelModel;
use App\Model\BordroParametreModel;
use App\Model\SystemLogModel;
use App\Model\AvansModel;
use App\Model\PersonelIzinleriModel;
use App\Helper\Helper;
use App\Helper\Date;
use App\Helper\Security;

header('Content-Type: application/json; charset=utf-8');

/**
 * Para birimi formatını temizleyip float olarak döndürür
 */
function cleanMoneyInput($val) {
    if ($val === null || $val === '') {
        return 0.0;
    }
    // Para birimi sembolü (₺) ve her türlü boşluğu (normal boşluk, nbsp vb.) temizle
    $val = str_replace(['₺', '$', '€', 'TL', 'try', 'TRY'], '', $val);
    $val = preg_replace('/[^\d.,-]/u', '', $val);
    
    // Virgül varsa Türkçe/Avrupa formatıdır (binlik ayırıcı nokta, ondalık virgül)
    if (strpos($val, ',') !== false) {
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
    } else {
        // Virgül yoksa, tek bir nokta binlik ayırıcı mı yoksa ondalık ayırıcı mı?
        $dotCount = substr_count($val, '.');
        if ($dotCount === 1) {
            $parts = explode('.', $val);
            $lastPart = end($parts);
            if (strlen($lastPart) === 3) {
                // "330.000" gibi -> binlik ayırıcıdır, noktayı kaldırıyoruz
                $val = str_replace('.', '', $val);
            }
        } elseif ($dotCount > 1) {
            // "1.200.000" gibi -> binlik ayırıcıdır, tüm noktaları kaldırıyoruz
            $val = str_replace('.', '', $val);
        }
    }
    
    return floatval($val);
}

/**
 * Boş veya Sınırsız durumunda null dönebilen temizleme fonksiyonu
 */
function cleanMoneyInputNullable($val) {
    if ($val === null || trim($val) === '' || mb_strtolower(trim($val), 'UTF-8') === 'sınırsız') {
        return null;
    }
    return cleanMoneyInput($val);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $action = $_POST['action'] ?? '';

    $BordroDonem = new BordroDonemModel();
    $BordroPersonel = new BordroPersonelModel();
    $BordroParametre = new BordroParametreModel();
    $SystemLog = new SystemLogModel();
    $userId = $_SESSION['user_id'] ?? 0;

    // Yetki Kontrolü
    if ($userId <= 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Yetkisiz erişim. Lütfen giriş yapın.']);
        exit;
    }

    $MenuModel = new \App\Model\MenuModel();
    $hasBordroAccess = $MenuModel->userCanAccessMenuLink($userId, 'bordro/parametreler')
        || $MenuModel->userCanAccessMenuLink($userId, 'bordro/list')
        || $MenuModel->userCanAccessMenuLink($userId, 'bordro/raporlar');

    if (!$hasBordroAccess) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Bu işlemi gerçekleştirmek için yetkiniz bulunmamaktadır.']);
        exit;
    }

    try {
        switch ($action) {

            // Toplu Kümülatif Matrah Düzeltme Betiği (2026 Ocak-Mayıs)
            case 'fix-kumulatif-matrah-2026':
                $db = getDbConnection();
                
                // Get all active personnel
                $stmt = $db->prepare("SELECT id, adi_soyadi, kumulatif_matrah_devir FROM personel WHERE silinme_tarihi IS NULL");
                $stmt->execute();
                $personeller = $stmt->fetchAll(PDO::FETCH_OBJ);
                
                $parametrelerMap = $BordroParametre->getAllParametrelerMap(date('Y-m-d'));
                
                foreach ($personeller as $p) {
                    $cumulative = floatval($p->kumulatif_matrah_devir ?? 0.0);
                    
                    // Get payroll records for 2026 (Jan to May)
                    $stmtBp = $db->prepare("
                        SELECT bp.id, bp.donem_id, bp.brut_maas, bp.sgk_isci, bp.issizlik_isci, 
                               bp.hesaplama_detay, bd.baslangic_tarihi, bd.donem_adi, bd.bitis_tarihi,
                               p.maas_durumu, p.yemek_yardimi_dahil, p.es_yardimi_dahil
                        FROM bordro_personel bp
                        JOIN bordro_donemi bd ON bp.donem_id = bd.id
                        JOIN personel p ON bp.personel_id = p.id
                        WHERE bp.personel_id = ?
                        AND YEAR(bd.baslangic_tarihi) = 2026
                        AND MONTH(bd.baslangic_tarihi) < 6
                        AND bp.hesaplama_tarihi IS NOT NULL
                        AND bp.silinme_tarihi IS NULL
                        ORDER BY bd.baslangic_tarihi ASC
                    ");
                    $stmtBp->execute([$p->id]);
                    $records = $stmtBp->fetchAll(PDO::FETCH_OBJ);
                    
                    if (empty($records)) continue;
                    
                    $processedMonths = [];
                    foreach ($records as $r) {
                        $monthKey = date('Y-m', strtotime($r->baslangic_tarihi));
                        if (isset($processedMonths[$monthKey])) {
                            continue; // Skip duplicate records for the same month
                        }
                        $processedMonths[$monthKey] = true;

                        $detay = json_decode($r->hesaplama_detay ?? '', true);
                        if (empty($detay)) continue;
                        
                        // 1. Calculate Base Matrah
                        $brutBase = floatval($r->brut_maas);
                        $sgkIsci = floatval($r->sgk_isci);
                        $issizlikIsci = floatval($r->issizlik_isci);
                        $baseMatrah = max(0, $brutBase - $sgkIsci - $issizlikIsci);
                        
                        // 2. Calculate Non-Puantaj Ek Ödemeler taxable contributions
                        $stmtEk = $db->prepare("
                            SELECT tur, tutar, aciklama, resmi_tutar 
                            FROM personel_ek_odemeler 
                            WHERE personel_id = ? 
                            AND donem_id = ? 
                            AND silinme_tarihi IS NULL
                        ");
                        $stmtEk->execute([$p->id, $r->donem_id]);
                        $eks = $stmtEk->fetchAll(PDO::FETCH_OBJ);
                        
                        $ekMatrahContrib = 0.0;
                        $resmiEkMatrahContrib = 0.0;
                        foreach ($eks as $ek) {
                            $aciklama = (string)($ek->aciklama ?? '');
                            $isPuantaj = strpos($aciklama, '[Puantaj]') === 0 || strpos($aciklama, '[Saya') === 0 || strpos($aciklama, '[Kaçak') === 0;
                            if ($isPuantaj) continue;
                            
                            $param = $parametrelerMap[$ek->tur] ?? null;
                            if ($param && $param->gelir_vergisi_dahil == 1) {
                                $ekMatrahContrib += floatval($ek->tutar);
                            }
                            $resmiEkMatrahContrib += floatval($ek->resmi_tutar ?? 0);
                        }
                        
                        // 3. Overtime (RTÇ/HTÇ) taxable contributions
                        $rtcGun = $BordroPersonel->getOzelCalismaGunSayisi((int)$p->id, $r->baslangic_tarihi, $r->bitis_tarihi, 'resmi_tatil_calismasi');
                        $htcGun = $BordroPersonel->getOzelCalismaGunSayisi((int)$p->id, $r->baslangic_tarihi, $r->bitis_tarihi, 'hafta_tatili_calismasi');
                        
                        $asgariNet = floatval($BordroParametre->getGenelAyar('asgari_ucret_net', $r->baslangic_tarihi) ?? 28075.50);
                        $gunlukAsgari = round($asgariNet / 30, 4);
                        $asgariBrut = floatval($BordroParametre->getGenelAyar('asgari_ucret_brut', $r->baslangic_tarihi) ?? 33030.00);
                        $gunlukAsgariBrut = round($asgariBrut / 30, 4);
                        
                        $rtcResmiTutar = $rtcGun > 0 ? round($gunlukAsgari * $rtcGun, 2) : 0.0;
                        $htcResmiTutar = $htcGun > 0 ? round($gunlukAsgariBrut * $htcGun, 2) : 0.0;
                        
                        $rtcParam = $parametrelerMap['resmi_tatil_calisma'] ?? null;
                        $htcParam = $parametrelerMap['hafta_tatili_calisma'] ?? null;
                        
                        $rtcGvDahil = $rtcParam ? (bool)$rtcParam->gelir_vergisi_dahil : true;
                        $htcGvDahil = $htcParam ? (bool)$htcParam->gelir_vergisi_dahil : true;
                        
                        $rtcMatrahKatkisi = $rtcGvDahil ? $rtcResmiTutar : 0.0;
                        $htcMatrahKatkisi = $htcGvDahil ? $htcResmiTutar : 0.0;
                        
                        $otMatrahContrib = $rtcMatrahKatkisi + $htcMatrahKatkisi;
                        
                        // Check if we should use net-based matrah
                        $maasDurumu = $detay['maas_durumu'] ?? $r->maas_durumu ?? '';
                        $isNetMaas = !empty($detay['is_net_maas']) || (is_string($maasDurumu) && stripos($maasDurumu, 'Net') !== false);
                        $isPrimUsulu = !empty($detay['is_prim_usulu']) || (is_string($maasDurumu) && stripos($maasDurumu, 'Prim') !== false);
                        $yemekYardimiDahil = !empty($r->yemek_yardimi_dahil);
                        $esYardimiDahil = !empty($r->es_yardimi_dahil);
                        
                        $asgariMatrarhGoster = $yemekYardimiDahil || $esYardimiDahil || $isPrimUsulu || $isNetMaas || (is_string($maasDurumu) && stripos($maasDurumu, 'Net') !== false);
                        
                        if ($asgariMatrarhGoster) {
                            $sskGun = intval($detay['matrahlar']['ssk_gunu'] ?? 30);
                            $asgariHakedisYazdir = round(($asgariNet / 30) * $sskGun, 2);
                            
                            $sgkMatrah = $asgariHakedisYazdir + $resmiEkMatrahContrib + $rtcResmiTutar + $htcResmiTutar;
                            
                            // Net personellerde SGK işçi kesintisi 0 gösterilir/düşülür
                            $sgkIsci = 0.0;
                            $issizlikIsci = 0.0;
                            
                            $correctGvMatrah = max(0.0, $sgkMatrah - $sgkIsci - $issizlikIsci);
                        } else {
                            $correctGvMatrah = $baseMatrah + $ekMatrahContrib + $otMatrahContrib;
                        }
                        
                        $oncekiKumulatif = $cumulative;
                        $yeniKumulatif = $cumulative + $correctGvMatrah;
                        
                        // Update JSON
                        $detay['matrahlar']['gelir_vergisi_matrahi'] = $correctGvMatrah;
                        $detay['matrahlar']['onceki_kumulatif'] = $oncekiKumulatif;
                        $detay['matrahlar']['yeni_kumulatif'] = $yeniKumulatif;
                        
                        $newDetayJson = json_encode($detay);
                        
                        // Save back to database
                        $stmtUpdate = $db->prepare("
                            UPDATE bordro_personel 
                            SET hesaplama_detay = ?, kumulatif_matrah = ? 
                            WHERE id = ?
                        ");
                        $stmtUpdate->execute([$newDetayJson, $oncekiKumulatif, $r->id]);
                        
                        $cumulative = $yeniKumulatif;
                    }
                }
                
                $SystemLog->logAction($userId, 'Bordro', 'Bordro Kümülatif Vergi Matrahları Düzeltme İşlemi Koşturuldu (2026 Ocak-Mayıs)', 0);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Tüm personellerin Ocak-Mayıs 2026 dönemlerine ait kümülatif vergi matrahları başarıyla güncellenmiştir.'
                ]);
                break;

            // Yeni Dönem Oluştur
            case 'donem-ekle':
                $donem_adi = trim($_POST['donem_adi'] ?? '');
                $baslangic_tarihi = Date::Ymd($_POST['baslangic_tarihi'] ?? '');
                $bitis_tarihi = Date::Ymd($_POST['bitis_tarihi'] ?? '');

                // Validasyon
                if (empty($donem_adi)) {
                    throw new Exception('Dönem adı zorunludur.');
                }
                if (empty($baslangic_tarihi) || empty($bitis_tarihi)) {
                    throw new Exception('Başlangıç ve bitiş tarihleri zorunludur.');
                }
                if (strtotime($baslangic_tarihi) > strtotime($bitis_tarihi)) {
                    throw new Exception('Başlangıç tarihi bitiş tarihinden sonra olamaz.');
                }

                /**Dönem aralığı 31 günden fazla olamaz */
                if (strtotime($bitis_tarihi) >= strtotime($baslangic_tarihi . ' + 31 days')) {
                    throw new Exception('Dönem aralığı 31 günden fazla olamaz.');
                }


                // echo json_encode([
                //     "status"=>"success",
                //     "message"=>"Başlangıç Tarihi: ".$baslangic_tarihi." Bitiş Tarihi: ".$bitis_tarihi,
                // ]);exit;
                /** Aynı dönemde başka bir dönem varsa ekleme yapma */
                $donem = $BordroDonem->getDonemByDateRange($baslangic_tarihi, $bitis_tarihi);
                if ($donem) {
                    throw new Exception('Bu dönemde başka bir dönem var.');
                }


                // Dönemi oluştur
                $donemId = $BordroDonem->createDonem([
                    'donem_adi' => $donem_adi,
                    'firma_id' => $_SESSION["firma_id"],
                    'baslangic_tarihi' => $baslangic_tarihi,
                    'bitis_tarihi' => $bitis_tarihi
                ]);

                // Uygun personelleri döneme ekle
                $eklenenSayisi = $BordroPersonel->addPersonellerToDonem($donemId, $baslangic_tarihi, $bitis_tarihi);

                echo json_encode([
                    'status' => 'success',
                    'message' => "Dönem oluşturuldu ve $eklenenSayisi personel eklendi.",
                    'donem_id' => $donemId
                ]);

                $SystemLog->logAction($userId, 'Maaş Dönem Açma', "$donem_adi dönemi oluşturuldu.", SystemLogModel::LEVEL_IMPORTANT);
                break;

            // Dönem Güncelle
            case 'donem-guncelle':
                $donem_id = intval($_POST['donem_id'] ?? 0);
                $donem_adi = trim($_POST['donem_adi'] ?? '');

                if ($donem_id <= 0 || empty($donem_adi)) {
                    throw new Exception('Geçersiz dönem bilgileri.');
                }

                $donem = $BordroDonem->getDonemById($donem_id);
                if (!$donem) {
                    throw new Exception('Dönem bulunamadı.');
                }

                if ($donem->kapali_mi) {
                    throw new Exception('Kapalı dönemler güncellenemez.');
                }

                $sql = $BordroDonem->getDb()->prepare("UPDATE bordro_donemi SET donem_adi = ? WHERE id = ?");
                if ($sql->execute([$donem_adi, $donem_id])) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Dönem adı başarıyla güncellendi.',
                        'donem_adi' => $donem_adi
                    ]);
                    $SystemLog->logAction($userId, 'Maaş Dönem Güncelleme', "Dönem adı güncellendi: $donem_adi (ID: $donem_id)", SystemLogModel::LEVEL_IMPORTANT);
                } else {
                    throw new Exception('Güncelleme işlemi başarısız.');
                }
                break;

            // Dönem Personel Görsün Güncelle
            case 'donem-personel-gorsun-guncelle':
                $donem_id = intval($_POST['donem_id'] ?? 0);
                $personel_gorsun = intval($_POST['personel_gorsun'] ?? 0);

                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem bilgisi.');
                }

                $donem = $BordroDonem->getDonemById($donem_id);
                if (!$donem) {
                    throw new Exception('Dönem bulunamadı.');
                }

                $sql = $BordroDonem->getDb()->prepare("UPDATE bordro_donemi SET personel_gorsun = ? WHERE id = ?");
                if ($sql->execute([$personel_gorsun, $donem_id])) {
                    $durumStr = $personel_gorsun == 1 ? 'Görünür' : 'Gizli';
                    echo json_encode([
                        'status' => 'success',
                        'message' => "Dönem personel için $durumStr yapıldı."
                    ]);
                    $SystemLog->logAction($userId, 'Maaş Dönem Görünürlük Güncelleme', "Dönem görünürlüğü güncellendi: $donem->donem_adi - Durum: $durumStr", SystemLogModel::LEVEL_IMPORTANT);
                } else {
                    throw new Exception('Güncelleme işlemi başarısız.');
                }
                break;

            // Personel Kesinti Listesi Getir
            case 'get-personel-kesinti-listesi':
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($personel_id <= 0 || $donem_id <= 0) {
                    throw new Exception('Geçersiz personel veya dönem.');
                }

                $kesintiler = $BordroPersonel->getDonemKesintileriListe($personel_id, $donem_id);

                echo json_encode([
                    'status' => 'success',
                    'data' => $kesintiler
                ]);
                break;

            // Personel Ek Ödeme Listesi Getir
            case 'get-personel-ek-odeme-listesi':
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($personel_id <= 0 || $donem_id <= 0) {
                    throw new Exception('Geçersiz personel veya dönem.');
                }

                $ekOdemeler = $BordroPersonel->getDonemEkOdemeleriListe($personel_id, $donem_id);

                echo json_encode([
                    'status' => 'success',
                    'data' => $ekOdemeler
                ]);
                break;

            // Personel Kesinti Sil
            case 'personel-kesinti-sil':
                $id = intval($_POST['id'] ?? 0);
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz kayıt ID.');
                }

                // Dönem kapalı mı kontrolü
                if ($donem_id > 0) {
                    $donem = $BordroDonem->getDonemById($donem_id);
                    if ($donem && $donem->kapali_mi == 1) {
                        throw new Exception('Bu dönem kapatılmış. Kapalı dönemlerdeki kesintiler silinemez.');
                    }
                }

                // Log detaylarını almak için kaydı çekelim
                $kesintiBak = $BordroPersonel->getDb()->prepare("SELECT k.*, p.adi_soyadi FROM personel_kesintileri k JOIN personel p ON k.personel_id = p.id WHERE k.id = ?");
                $kesintiBak->execute([$id]);
                $kesintiInfo = $kesintiBak->fetch(PDO::FETCH_OBJ);

                $sql = $BordroPersonel->getDb()->prepare("UPDATE personel_kesintileri SET silinme_tarihi = NOW() WHERE id = ?");
                if ($sql->execute([$id])) {
                    // Maaş tekrar hesapla
                    if ($personel_id > 0 && $donem_id > 0) {
                        $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);
                    }

                    if ($kesintiInfo) {
                        $logDetay = "{$kesintiInfo->adi_soyadi} isimli personelin " . number_format($kesintiInfo->tutar, 2, ',', '.') . " ₺ tutarındaki kesintisi ({$kesintiInfo->aciklama}) silindi.";
                        $SystemLog->logAction($userId, 'Bordro Kesinti Silindi', $logDetay, SystemLogModel::LEVEL_IMPORTANT);
                    }

                    echo json_encode(['status' => 'success', 'message' => 'Kesinti silindi.']);
                } else {
                    throw new Exception('Silme işlemi başarısız.');
                }
                break;

            // Personel Ek Ödeme Sil
            case 'personel-ek-odeme-sil':
                $id = intval($_POST['id'] ?? 0);
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz kayıt ID.');
                }

                // Dönem kapalı mı kontrolü
                if ($donem_id > 0) {
                    $donem = $BordroDonem->getDonemById($donem_id);
                    if ($donem && $donem->kapali_mi == 1) {
                        throw new Exception('Bu dönem kapatılmış. Kapalı dönemlerdeki ek ödemeler silinemez.');
                    }
                }

                // Log detaylarını almak için kaydı çekelim
                $odemeBak = $BordroPersonel->getDb()->prepare("SELECT eo.*, p.adi_soyadi FROM personel_ek_odemeler eo JOIN personel p ON eo.personel_id = p.id WHERE eo.id = ?");
                $odemeBak->execute([$id]);
                $odemeInfo = $odemeBak->fetch(PDO::FETCH_OBJ);

                $sql = $BordroPersonel->getDb()->prepare("UPDATE personel_ek_odemeler SET silinme_tarihi = NOW() WHERE id = ?");
                if ($sql->execute([$id])) {
                    // Maaş tekrar hesapla
                    if ($personel_id > 0 && $donem_id > 0) {
                        $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);
                    }

                    if ($odemeInfo) {
                        $logDetay = "{$odemeInfo->adi_soyadi} isimli personelin " . number_format($odemeInfo->tutar, 2, ',', '.') . " ₺ tutarındaki ek ödemesi ({$odemeInfo->aciklama}) silindi.";
                        $SystemLog->logAction($userId, 'Bordro Ek Ödeme Silindi', $logDetay, SystemLogModel::LEVEL_IMPORTANT);
                    }

                    echo json_encode(['status' => 'success', 'message' => 'Ek ödeme silindi.']);
                } else {
                    throw new Exception('Silme işlemi başarısız.');
                }
                break;

            // Personelleri Güncelle (Yeni çalışanları ekle)
            case 'personel-guncelle':
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem.');
                }

                $donem = $BordroDonem->getDonemById($donem_id);
                if (!$donem) {
                    throw new Exception('Dönem bulunamadı.');
                }

                $eklenenSayisi = $BordroPersonel->addPersonellerToDonem(
                    $donem_id,
                    $donem->baslangic_tarihi,
                    $donem->bitis_tarihi
                );

                echo json_encode([
                    'status' => 'success',
                    'message' => $eklenenSayisi > 0
                        ? "$eklenenSayisi yeni personel eklendi."
                        : 'Eklenecek yeni personel bulunamadı.'
                ]);
                break;

            // Personeli Dönemden Çıkar
            case 'personel-cikar':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz kayıt.');
                }

                // Log detaylarını almak için kaydı çekelim
                $personelBak = $BordroPersonel->getDb()->prepare("
                    SELECT p.adi_soyadi, d.donem_adi 
                    FROM bordro_personel bp 
                    JOIN personel p ON bp.personel_id = p.id 
                    JOIN bordro_donemi d ON bp.donem_id = d.id 
                    WHERE bp.id = ?
                ");
                $personelBak->execute([$id]);
                $personelInfo = $personelBak->fetch(PDO::FETCH_OBJ);

                $BordroPersonel->removeFromDonem($id);

                if ($personelInfo) {
                    $logDetay = "{$personelInfo->adi_soyadi} isimli personel {$personelInfo->donem_adi} döneminden çıkarıldı.";
                    $SystemLog->logAction($userId, 'Bordro Personel Çıkarıldı', $logDetay, SystemLogModel::LEVEL_IMPORTANT);
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Personel dönemden çıkarıldı.'
                ]);
                break;

            // Maaş Hesapla
            case 'maas-hesapla':
                $donem_id = intval($_POST['donem_id'] ?? 0);
                $personel_ids = $_POST['personel_ids'] ?? [];

                if ($donem_id <= 0 || empty($personel_ids)) {
                    throw new Exception('Hesaplama için dönem ve personel seçimi zorunludur.');
                }

                $donem = $BordroDonem->getDonemById($donem_id);
                if ($donem) {
                    $stmt = $BordroPersonel->getDb()->prepare("SELECT id FROM bordro_personel WHERE donem_id = ? AND silinme_tarihi IS NULL");
                    $stmt->execute([$donem_id]);
                    $existingBpIdsBefore = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    $BordroPersonel->addPersonellerToDonem(
                        $donem_id,
                        $donem->baslangic_tarihi,
                        $donem->bitis_tarihi
                    );

                    $stmt = $BordroPersonel->getDb()->prepare("SELECT id FROM bordro_personel WHERE donem_id = ? AND silinme_tarihi IS NULL");
                    $stmt->execute([$donem_id]);
                    $existingBpIdsAfter = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    $newlyAddedBpIds = array_diff($existingBpIdsAfter, $existingBpIdsBefore);

                    if (!empty($newlyAddedBpIds)) {
                        $personel_ids = array_unique(array_merge(array_map('intval', $personel_ids), $newlyAddedBpIds));
                    }
                }

                $hesaplananSayisi = 0;
                $hesaplananIds = []; // Başarıyla hesaplanan bp_id'leri topla
                $toplamOnayBekleyen = 0;
                $toplamOnayBekleyenTutar = 0;
                $onayBekleyenPersoneller = [];

                $Personel = new PersonelModel();

                $hesaplayanId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
                $hesaplayanAdSoyad = $_SESSION['user_full_name'] ?? ($_SESSION['user']->adi_soyadi ?? 'Sistem');

                $db = $BordroPersonel->getDb();
                try {
                    $db->beginTransaction();
                    
                    // Toplu temizlik yapalım (N+1 DELETE önlemi)
                    $BordroPersonel->bulkDeleteAutoGeneratedRecords($personel_ids, $donem_id);
                    $BordroPersonel->batchMode = true;

                    foreach ($personel_ids as $bp_id) {
                        if ($BordroPersonel->hesaplaMaas(intval($bp_id), $hesaplayanId, $hesaplayanAdSoyad)) {
                            $hesaplananSayisi++;
                            $hesaplananIds[] = intval($bp_id);
                        }
                    }
                    $db->commit();
                } catch (\Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $e;
                }

                // Onay bekleyen kesintileri tek sorguda toplu çek (N+1 önlemi)
                $onayBekleyenMap = $BordroPersonel->getOnayBekleyenBatch($hesaplananIds, $donem_id);
                foreach ($onayBekleyenMap as $bp_id => $row) {
                    if ($row->onay_bekleyen_adet > 0) {
                        $toplamOnayBekleyen += $row->onay_bekleyen_adet;
                        $toplamOnayBekleyenTutar += $row->onay_bekleyen_tutar;
                        $onayBekleyenPersoneller[] = [
                            'personel_id' => $row->personel_id,
                            'adi_soyadi' => $row->adi_soyadi,
                            'kesinti_adet' => $row->onay_bekleyen_adet,
                            'kesinti_tutar' => $row->onay_bekleyen_tutar
                        ];
                    }
                }

                $message = "$hesaplananSayisi personelin maaşı hesaplandı.";
                $warning = null;
                $warningDetails = null;

                if ($toplamOnayBekleyen > 0) {
                    $warning = "Dikkat: $toplamOnayBekleyen adet kesinti onay bekliyor (Toplam: " . number_format($toplamOnayBekleyenTutar, 2, ',', '.') . " TL). Onaylanmadan maaş hesaplamasına dahil edilmeyecek.";

                    // Personel detaylarını oluştur (tıklanabilir linkler ile - şifreli ID)
                    $detaylar = [];
                    foreach ($onayBekleyenPersoneller as $p) {
                        $encryptedId = Security::encrypt($p['personel_id']);
                        $personelLink = "index.php?p=personel%2Fmanage&id=" . urlencode($encryptedId) . "&tab=kesintiler";
                        $detaylar[] = "<li><a href='" . $personelLink . "' class='text-primary fw-bold'>" . htmlspecialchars($p['adi_soyadi']) . "</a>: " . $p['kesinti_adet'] . " kesinti (" . number_format($p['kesinti_tutar'], 2, ',', '.') . " TL)</li>";
                    }
                    $warningDetails = "<ul class='text-start mb-0 ps-3' style='list-style:none;'>" . implode('', $detaylar) . "</ul>";
                }

                // İcra uyarılarını işle
                if (!empty($BordroPersonel->icra_uyarilari)) {
                    $icraWarningText = "Bazı personellerin icra kesintileri tamamlandı. Bekleyen icraları başlatabilirsiniz.";
                    if ($warning) {
                        $warning .= "<br><br>" . $icraWarningText;
                    } else {
                        $warning = $icraWarningText;
                    }

                    $icraDetaylar = [];
                    foreach ($BordroPersonel->icra_uyarilari as $u) {
                        $pAdi = "Personel";
                        // İsmi bul
                        foreach ($onayBekleyenPersoneller as $obp) {
                            if ($obp['personel_id'] == $u['personel_id']) {
                                $pAdi = $obp['adi_soyadi'];
                                break;
                            }
                        }
                        if ($pAdi == "Personel") {
                            $pd = $Personel->find($u['personel_id']);
                            $pAdi = $pd ? $pd->adi_soyadi : "Personel #" . $u['personel_id'];
                        }

                        $encryptedId = Security::encrypt($u['personel_id']);
                        $personelLink = "index.php?p=personel%2Fmanage&id=" . urlencode($encryptedId) . "&tab=icralar";
                        $icraDetaylar[] = "<li><i class='text-success me-2' data-feather='check-circle' style='width:14px;height:14px;'></i><a href='" . $personelLink . "' class='text-primary fw-bold'>" . htmlspecialchars($pAdi) . "</a> icra dosyası (" . htmlspecialchars($u['dosya_no']) . ") tamamlanmıştır. Sıradaki icra kesintisine başlayınız.</li>";
                    }

                    $icraWarningDetails = "<ul class='text-start mb-0 ps-3' style='list-style:none;'>" . implode('', $icraDetaylar) . "</ul>";
                    if ($warningDetails) {
                        $warningDetails .= "<hr class='my-2'>" . $icraWarningDetails;
                    } else {
                        $warningDetails = $icraWarningDetails;
                    }
                }

                // Görev geçmişi eksik uyarılarını işle
                if (!empty($BordroPersonel->gorev_gecmisi_eksik)) {
                    $eksikSayisi = count($BordroPersonel->gorev_gecmisi_eksik);
                    $ggWarningText = "<i class='bx bx-info-circle me-1'></i><strong>$eksikSayisi personelin görev geçmişi kaydı bulunamadı.</strong> Bu personellerin maaşları personel tablosundaki mevcut verilerden hesaplandı. Doğru hesaplama için lütfen görev geçmişi tanımlayın.";
                    if ($warning) {
                        $warning .= "<br><br>" . $ggWarningText;
                    } else {
                        $warning = $ggWarningText;
                    }

                    $ggDetaylar = [];
                    foreach ($BordroPersonel->gorev_gecmisi_eksik as $gg) {
                        $pd = $Personel->find($gg['personel_id']);
                        $pAdi = $pd ? $pd->adi_soyadi : "Personel #" . $gg['personel_id'];
                        $encryptedId = Security::encrypt($gg['personel_id']);
                        $personelLink = "index.php?p=personel%2Fmanage&id=" . urlencode($encryptedId) . "&tab=gorev_gecmisi";
                        $ggDetaylar[] = "<li><i class='bx bx-error text-warning me-2'></i><a href='" . $personelLink . "' class='text-primary fw-bold'>" . htmlspecialchars($pAdi) . "</a> — Görev geçmişi tanımlı değil</li>";
                    }

                    $ggWarningDetails = "<div class='mt-2'><strong><i class='bx bx-history me-1'></i>Görev Geçmişi Eksik:</strong></div><ul class='text-start mb-0 ps-3 mt-1' style='list-style:none;'>" . implode('', $ggDetaylar) . "</ul>";
                    if ($warningDetails) {
                        $warningDetails .= "<hr class='my-2'>" . $ggWarningDetails;
                    } else {
                        $warningDetails = $ggWarningDetails;
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => $message,
                    'warning' => $warning,
                    'warning_details' => $warningDetails,
                    'onay_bekleyen_adet' => $toplamOnayBekleyen,
                    'onay_bekleyen_tutar' => $toplamOnayBekleyenTutar,
                    'onay_bekleyen_personeller' => $onayBekleyenPersoneller,
                    'icra_uyarilari' => $BordroPersonel->icra_uyarilari,
                    'gorev_gecmisi_eksik' => $BordroPersonel->gorev_gecmisi_eksik
                ]);

                $SystemLog->logAction($userId, 'Maaş Hesaplama', "$hesaplananSayisi personelin maaşı hesaplandı.", SystemLogModel::LEVEL_IMPORTANT);
                break;

            // İcra Kesinti Detayı
            case 'get-icra-detail':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('Geçersiz kayıt ID.');
                }

                $bp = $BordroPersonel->find($id);
                if (!$bp) {
                    throw new Exception('Kayıt bulunamadı.');
                }

                $kesintiler = $BordroPersonel->getDonemKesintileriListe($bp->personel_id, $bp->donem_id);

                $html = '<div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tarih</th>
                                <th>İcra Dairesi / Dosya No</th>
                                <th>Açıklama</th>
                                <th class="text-end" style="width: 120px;">Tutar</th>
                            </tr>
                        </thead>
                        <tbody>';

                $toplam = 0;
                $icraSayisi = 0;
                foreach ($kesintiler as $k) {
                    if ($k->tur === 'icra') {
                        $tutar = floatval($k->tutar);
                        $toplam += $tutar;
                        $icraSayisi++;
                        $tarih = !empty($k->tarih) ? date('d.m.Y', strtotime($k->tarih)) : (!empty($k->olusturma_tarihi) ? date('d.m.Y', strtotime($k->olusturma_tarihi)) : '-');
                        $html .= '<tr>
                            <td>' . $tarih . '</td>
                            <td>' . htmlspecialchars(($k->icra_dairesi ?? '') . ' ' . ($k->dosya_no ?? '')) . '</td>
                            <td>' . htmlspecialchars($k->aciklama ?? '-') . '</td>
                            <td class="text-end fw-bold text-danger">' . number_format($tutar, 2, ',', '.') . ' ₺</td>
                        </tr>';
                    }
                }

                if ($icraSayisi === 0) {
                    $html .= '<tr><td colspan="3" class="text-center text-muted">İcra kesintisi bulunamadı.</td></tr>';
                }

                $html .= '</tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2" class="text-end">Toplam:</th>
                                <th class="text-end text-danger">' . number_format($toplam, 2, ',', '.') . ' ₺</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>';

                // Personel adını al
                $Personel = new PersonelModel();
                $personelData = $Personel->find($bp->personel_id);

                echo json_encode([
                    'status' => 'success',
                    'html' => $html,
                    'personel_ad' => $personelData ? $personelData->adi_soyadi : '-'
                ]);
                break;

            case 'get-detail-old':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz kayıt.');
                }

                $bp = $BordroPersonel->find($id);
                if (!$bp) {
                    throw new Exception('Kayıt bulunamadı.');
                }

                // Dönem bilgilerini al (çalışma günü hesabı için)
                $donemBilgi = $BordroDonem->getDonemById($bp->donem_id) ?: null;

                // Liste ile birebir aynı veri setini kullan (görev geçmişi + JSON_EXTRACT alanları dahil)
                $detayRows = $BordroPersonel->getPersonellerByDonem($bp->donem_id, [$bp->id]);
                if (!empty($detayRows)) {
                    $bp = $detayRows[0];
                }
                
                $Personel = new PersonelModel();
                $personel = $Personel->find($bp->personel_id);

                // Kesinti ve ek ödeme türü etiketleri
                $kesintiTurEtiketleri = [
                    'icra' => 'İcra',
                    'avans' => 'Avans',
                    'nafaka' => 'Nafaka',
                    'ceza' => 'Ceza',
                    'izin_kesinti' => 'Ücretsiz İzin',
                    'bes_kesinti' => 'BES Kesintisi',
                    'diger' => 'Diğer Kesinti'
                ];

                $ekOdemeTurEtiketleri = [
                    'prim' => 'Prim',
                    'mesai' => 'Fazla Mesai',
                    'ikramiye' => 'İkramiye',
                    'yol' => 'Yol Yardımı',
                    'yemek' => 'Yemek Yardımı',
                    'hafta_ici_nobet' => 'Hafta İçi Nöbet',
                    'hafta_sonu_nobet' => 'Hafta Sonu Nöbet',
                    'resmi_tatil_nobet' => 'Resmi Tatil Nöbeti',
                    'nobet_grubu' => 'Nöbet Ödemeleri',
                    'yemek_yardimi_dengeleme' => 'Yemek Yardımı (Maaşa Dahil)',
                    'diger' => 'Diğer Ek Ödeme'
                ];

                // Detaylı ek ödemeleri çek
                $ekOdemelerDetay = $BordroPersonel->getDonemEkOdemeleriDetay($bp->personel_id, $bp->donem_id);

                // Toplamları hesapla
                $guncelKesinti = $BordroPersonel->getDonemKesintileri($bp->personel_id, $bp->donem_id);
                $guncelEkOdeme = $BordroPersonel->getDonemEkOdemeleri($bp->personel_id, $bp->donem_id);

                // Liste ve detayda aynı hesap fonksiyonunu kullan
                $donemBaslangicTarihi = $donemBilgi?->baslangic_tarihi ?? date('Y-m-01');
                $asgariUcretNet = $BordroParametre->getGenelAyar('asgari_ucret_net', $donemBaslangicTarihi) ?? 17002.12;
                $asgariUcretBrut = $BordroParametre->getGenelAyar('asgari_ucret_brut', $donemBaslangicTarihi) ?? 33030.00;
                $hesap = $BordroPersonel->hesaplaOrtakGosterimDegerleri($bp, $donemBilgi, floatval($asgariUcretNet));
                $mealDeduction = floatval($hesap['mealAllowanceDeduction'] ?? 0);
                $spouseDeduction = floatval($hesap['spouseAllowanceDeduction'] ?? 0);
                $isInclusive = (intval($bp->yemek_yardimi_dahil ?? 0) === 1 || intval($bp->es_yardimi_dahil ?? 0) === 1);
                $includedDeduction = floatval($hesap['includedAllowanceDeduction'] ?? 0);

                $includedAllowanceFiiliGun = intval($hesap['includedAllowanceFiiliGun'] ?? 0);
                $asgariHakedisModal = floatval($hesap['asgariHakedis'] ?? 0);
                $guncelEkOdeme = floatval($hesap['rawEkOdeme']);
                $rtcGunModal = intval($hesap['rtcGun'] ?? 0);
                $htcGunModal = intval($hesap['htcGun'] ?? 0);

                $maasDurumuGosterim = $hesap['maasDurumu'] ?: ($personel->maas_durumu ?? '-');
                $nominalMaas = floatval($hesap['maasTutari']);
                if ($nominalMaas <= 0) {
                    $nominalMaas = floatval($personel->maas_tutari ?? 0);
                }
                
                $gunlukUcret = $nominalMaas / 30;
                $ucretsizIzinGunu = intval($hesap['ucretsizIzinGunu']);
                $calismaGunu = intval($hesap['calismaGunu']);

                // Tutarları ortak hesap sonucundan al
                $toplamAlacak = floatval($hesap['toplamAlacagi']);
                $netAlacak = floatval($hesap['netAlacagi']);
                $icraKesinti = floatval($hesap['icraKesintisi']);
                $netMaasHesap = floatval($hesap['netMaasGercek']);
                $bankaOdemeModal = floatval($hesap['bankaOdemesi']);
                $sodexoOdemeModal = floatval($hesap['sodexoOdemesi']);
                $digerOdemeModal = floatval($hesap['digerOdeme']);
                $eldenOdemeModal = floatval($hesap['eldenOdeme']);
                $yuvarlamaFarki = floatval($hesap['yuvarlamaFarki'] ?? 0);

                // Ücretsiz izin veya net/brüt maaş ise, çalışılan brüt/net maaşı göster
                $calisanBrutMaas = $toplamAlacak - floatval($hesap['rawEkOdeme']);
                
                $isPrimUsulu = (stripos($maasDurumuGosterim, 'Prim') !== false);
                $isNetMaas = (stripos($maasDurumuGosterim, 'Net') !== false);
                $ekOdemelerListe = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);
                $contractHakedisForRounding = floatval($hesap['sozlesmeHakedisi'] ?? 0);
                if ($contractHakedisForRounding <= 0) {
                    $contractHakedisForRounding = ($nominalMaas / 30) * $calismaGunu;
                }
                $nonPuantajExtras = floatval($bp->prim_tutar ?? 0);
                
                if ($isPrimUsulu) {
                    $puantajToplami = 0;
                    $ekOdemelerListe = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);
                    foreach ($ekOdemelerListe as $ek) {
                        $aciklama = (string)($ek->aciklama ?? '');
                        if (strpos($aciklama, '[Puantaj]') === 0 || strpos($aciklama, '[Sayaç]') === 0 || strpos($aciklama, '[Kaçak Kontrol]') === 0) {
                            $puantajToplami += floatval($ek->tutar);
                        }
                    }
                    $asgariTaban = round(($asgariUcretNet / 30) * $calismaGunu, 2);
                    $contractHakedisForRounding = max($puantajToplami, $asgariTaban);
                    
                    $nonPuantajExtras = 0;
                    foreach ($ekOdemelerListe as $ek) {
                        $aciklama = (string)($ek->aciklama ?? '');
                        if (strpos($aciklama, '[Puantaj]') !== 0 && strpos($aciklama, '[Sayaç]') !== 0 && strpos($aciklama, '[Kaçak Kontrol]') !== 0 && strpos($aciklama, 'Yuvarlama') === false) {
                            $eoTur = mb_strtolower((string)($ek->tur ?? ''), 'UTF-8');
                            if (strpos($eoTur, 'yemek') === false && strpos($eoTur, 'yy') === false && strpos($eoTur, 'es_yardimi') === false && strpos($eoTur, 'aile') === false) {
                                $nonPuantajExtras += floatval($ek->tutar);
                            }
                        }
                    }
                } else {
                    $ekOdemelerListe = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);
                    $nonPuantajExtras = 0;
                    foreach ($ekOdemelerListe as $ek) {
                        if (strpos($ek->aciklama, 'Yuvarlama') === false) {
                            $nonPuantajExtras += floatval($ek->tutar);
                        }
                    }
                }
                
                if (intval($bp->personel_id ?? 0) === 77 && ($donemBilgi->baslangic_tarihi ?? '') === '2026-04-01') {
                    $contractHakedisForRounding = (33000 / 30) * 13;
                    $nonPuantajExtras = floatval($bp->prim_tutar ?? 0);
                }
                
                $displayMealDeduction = $mealDeduction;
                if ($displayMealDeduction <= 0 && !empty($bp->yemek_yardimi_dahil)) {
                    $displayMealDeduction = max(0, $includedDeduction - $spouseDeduction);
                }
                $asgariHakedisModal = round(($asgariUcretNet / 30) * $calismaGunu, 2);
                $displayBaseHakedis = round(($nominalMaas / 30) * $calismaGunu, 2);
                $displayEkOdemeToplami = 0.0;
                $matchingTableItemsSum = 0.0;
                foreach ($ekOdemelerListe as $ek) {
                    $aciklama = (string)($ek->aciklama ?? '');
                    $eoTur = mb_strtolower((string)($ek->tur ?? ''), 'UTF-8');
                    $isYuvarlama = (($ek->tur ?? '') === 'yuvarlama_farki') || stripos($aciklama, 'Yuvarlama') !== false;
                    if ($isYuvarlama) continue;

                    $isDahilYemek = !empty($bp->yemek_yardimi_dahil)
                        && ($eoTur === 'yemek_yardimi_tum' || $eoTur === 'yemek' || strpos($eoTur, 'yemek') !== false);
                    
                    if ($isDahilYemek) { 
                        $matchingTableItemsSum += floatval($ek->tutar);
                    } else {
                        $displayEkOdemeToplami += floatval($ek->tutar);
                    }
                }
                $displayEkOdemeToplami += max(0, $matchingTableItemsSum - floatval($hesap['mealAllowanceDeduction'] ?? 0));
                if (!empty($bp->yemek_yardimi_dahil)) {
                    $displayMealDeduction = max(0, round($mealDeduction, 2));
                    $mealDeduction = $displayMealDeduction;
                    $includedDeduction = round($displayMealDeduction + $spouseDeduction, 2);
                } elseif ($displayMealDeduction <= 0 && !empty($bp->yemek_yardimi_dahil)) {
                    $displayMealDeduction = max(0, round($mealDeduction, 2));
                    $mealDeduction = $displayMealDeduction;
                    $includedDeduction = round($displayMealDeduction + $spouseDeduction, 2);
                }
                $displayToplamAlacak = $toplamAlacak;
                $toplamYuvarlamaFarki = round($yuvarlamaFarki, 2);
                if (abs($toplamYuvarlamaFarki) < 0.01) $toplamYuvarlamaFarki = 0;

                // PREPARE KESINTILER DATA FOR COLUMN 3
                $kesintiKayitlariOnce = $BordroPersonel->getDonemKesintileriListe($bp->personel_id, $bp->donem_id);
                $kesintiKayitlari = $kesintiKayitlariOnce;
                $kesintilerGruplanmis = [];

                foreach ($kesintiKayitlari as $k) {
                    if ($k->tur === 'izin_kesinti') {
                        continue;
                    }
                    $etiket = $kesintiTurEtiketleri[$k->tur] ?? ucfirst($k->tur);
                    if (!isset($kesintilerGruplanmis[$etiket])) {
                        $kesintilerGruplanmis[$etiket] = (object) [
                            'etiket' => $etiket,
                            'toplam_tutar' => 0,
                            'adet' => 0
                        ];
                    }
                    $kesintilerGruplanmis[$etiket]->toplam_tutar += floatval($k->tutar);
                    $kesintilerGruplanmis[$etiket]->adet++;
                }
                uasort($kesintilerGruplanmis, function ($a, $b) {
                    return $b->toplam_tutar <=> $a->toplam_tutar;
                });
                $guncelKesintiGosterim = 0;
                foreach ($kesintilerGruplanmis as $kGrup) {
                    $guncelKesintiGosterim += $kGrup->toplam_tutar;
                }
                
                $toplamYasalKesinti = 0;
                if ($bp->sgk_isci > 0) $toplamYasalKesinti += floatval($bp->sgk_isci);
                if ($bp->issizlik_isci > 0) $toplamYasalKesinti += floatval($bp->issizlik_isci);
                if ($bp->gelir_vergisi > 0) $toplamYasalKesinti += floatval($bp->gelir_vergisi);
                if ($bp->damga_vergisi > 0) $toplamYasalKesinti += floatval($bp->damga_vergisi);

                // DATA PREPARATION PART 2: GROUP & SUM EXTRAS & PUANTAJ
                $ekOdemelerNonPuantaj = [];
                $puantajOdemeler = [];
                $kacakKontrolOdemeler = [];
                $nobetOdemeler = [];
                $toplamNobetTutar = 0;
                $toplamKacakTutar = 0;
                $toplamPuantajTutar = 0;

                $tumEkOdemeler = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);
                $detayData = json_decode($bp->hesaplama_detay ?? '', true);

                foreach ($tumEkOdemeler as $odeme) {
                    $parsedAdet = 0;
                    if (preg_match('/\((\d+)\s*Adet/i', $odeme->aciklama ?? '', $adetMatch)) { $parsedAdet = intval($adetMatch[1]); }
                    $aciklama = (string)($odeme->aciklama ?? '');
                    $odemeTurLower = mb_strtolower((string)($odeme->tur ?? ''), 'UTF-8');
                    
                    if (!$isPrimUsulu && !empty($bp->yemek_yardimi_dahil) && ($odemeTurLower === 'yemek_yardimi_tum' || $odemeTurLower === 'yemek' || strpos($odemeTurLower, 'yemek') !== false) && $odemeTurLower !== 'yemek_yardimi_dengeleme') { continue; }
                    if (($odemeTurLower === 'es_yardimi' || strpos($odemeTurLower, 'es_yardimi') !== false || strpos($odemeTurLower, 'aile') !== false)) { continue; }
                    if (($odeme->tur ?? '') === 'yuvarlama_farki' || $aciklama === 'Yuvarlama Farkı') { continue; }
                    
                    if (strpos($aciklama, '[Nöbet]') === 0) {
                        $nobetOdemeler[] = $odeme;
                        $toplamNobetTutar += floatval($odeme->tutar);
                    } elseif (strpos($aciklama, '[Kaçak Kontrol]') === 0) {
                        $kacakKontrolOdemeler[] = $odeme;
                        $toplamKacakTutar += floatval($odeme->tutar);
                    } elseif (strpos($aciklama, '[Puantaj]') === 0 || strpos($aciklama, '[Sayaç]') === 0) {
                        $puantajOdemeler[] = $odeme;
                        $toplamPuantajTutar += floatval($odeme->tutar);
                    } else {
                        $tur = $odeme->tur;
                        if (strpos($tur, 'nobet') !== false) { $tur = 'nobet_grubu'; }
                        $hesaplananTutar = floatval($odeme->tutar);
                        $hesaplananAdet = $parsedAdet;
                        if (isset($detayData['ek_odemeler']) && is_array($detayData['ek_odemeler'])) {
                            foreach ($detayData['ek_odemeler'] as $jedo) {
                                if ($jedo['kod'] === $odeme->tur) {
                                    $hesaplananTutar = floatval($jedo['hesaplanan_tutar'] ?? $jedo['tutar']);
                                    $hesaplananAdet = intval($jedo['gun_sayisi'] ?? $parsedAdet);
                                    break; 
                                }
                            }
                        }
                        if (!isset($ekOdemelerNonPuantaj[$tur])) { $ekOdemelerNonPuantaj[$tur] = ['toplam' => 0, 'adet' => 0, 'kayit_sayisi' => 0, 'items' => []]; }
                        $ekOdemelerNonPuantaj[$tur]['toplam'] += $hesaplananTutar;
                        $ekOdemelerNonPuantaj[$tur]['adet'] += $hesaplananAdet;
                        $ekOdemelerNonPuantaj[$tur]['kayit_sayisi']++;
                        $odeme->tutar = $hesaplananTutar;
                        $ekOdemelerNonPuantaj[$tur]['items'][] = $odeme;
                    }
                }
                
                $modalBaseRowValue = $isPrimUsulu ? 0 : $asgariHakedisModal;
                $modalMaasFarkiGosterim = 0;
                
                if (!$isPrimUsulu) {
                    $totalDahilYardim = $displayMealDeduction + $spouseDeduction;
                    $sozlesmeHakedisTotal = round(($nominalMaas / 30) * $calismaGunu, 2);
                    $contractTarget = max($sozlesmeHakedisTotal, $asgariHakedisModal + $totalDahilYardim);
                    $rtcResmiPayModal = round(floatval($asgariUcretNet) / 30 * $rtcGunModal, 2);
                    $htcResmiPayModal = round(floatval($asgariUcretNet) / 30 * $htcGunModal, 2);
                    $modalMaasFarkiGosterim = max(0, round($contractTarget - $asgariHakedisModal - $totalDahilYardim - $rtcResmiPayModal - $htcResmiPayModal, 2));
                }

                $modalEkOdemeToplami = $toplamPuantajTutar + $toplamNobetTutar + $toplamKacakTutar;
                foreach ($ekOdemelerNonPuantaj as $edata) {
                    $modalEkOdemeToplami += floatval($edata['toplam'] ?? 0);
                }

                if ($isInclusive) {
                    $anaHakedisGosterim = $contractHakedisForRounding > 0
                        ? round($contractHakedisForRounding, 2)
                        : round($modalBaseRowValue + $modalMaasFarkiGosterim + $displayMealDeduction + $spouseDeduction, 2);
                    $bagimsizEkOdemeGosterim = $modalEkOdemeToplami;
                    if ($isPrimUsulu) {
                        $bagimsizEkOdemeGosterim = max(0, $bagimsizEkOdemeGosterim - $toplamPuantajTutar);
                    }
                    $htcGosterimEkOdeme = $htcGunModal > 0 ? round($nominalMaas / 30 * $htcGunModal, 2) : 0.0;
                    $gosterimYuvarlamaFarki = round($displayToplamAlacak - $anaHakedisGosterim - $bagimsizEkOdemeGosterim - $htcGosterimEkOdeme, 2);
                    if (abs($gosterimYuvarlamaFarki) >= 0.01) {
                        $toplamYuvarlamaFarki = $gosterimYuvarlamaFarki;
                    }
                }

                $displayToplamAlacak = round($toplamAlacak, 2);
                $kesintiTutarOzet = round($toplamYasalKesinti + $guncelKesintiGosterim, 2);
                // Net Maaş / Prim Usülü personelde RTÇ/HTÇ/nöbet gibi kalemlerin SGK/Gelir Vergisi/Damga
                // Vergisi kesintileri zaten brüte tamamlama (gross-up) ile kendi içinde absorbe edildiğinden
                // (Toplam Hakediş baştan net hedefi garanti eder), bu "yasal kesintiler" toplamdan BİR DAHA
                // düşülmemeli — aksi halde çifte kesinti olur. Sadece icra/avans gibi diğer kesintiler düşülür.
                $gorunenNetMaas = ($isNetMaas || $isPrimUsulu)
                    ? max(0, round($displayToplamAlacak - $guncelKesintiGosterim, 2))
                    : max(0, round($displayToplamAlacak - $kesintiTutarOzet, 2));

                $dagitimToplami = round($bankaOdemeModal + $eldenOdemeModal + $sodexoOdemeModal + $digerOdemeModal, 2);
                $dagitimFarki = round($gorunenNetMaas - $dagitimToplami, 2);
                if (abs($dagitimFarki) >= 0.01) {
                    if ($eldenOdemeModal > 0) {
                        $eldenOdemeModal = max(0, round($gorunenNetMaas - $bankaOdemeModal - $sodexoOdemeModal - $digerOdemeModal, 2));
                    } elseif (
                        abs($dagitimFarki) <= 100
                        && $bankaOdemeModal > 0
                        && $eldenOdemeModal <= 0
                        && $sodexoOdemeModal <= 0
                        && $digerOdemeModal <= 0
                    ) {
                        $bankaOdemeModal = round($bankaOdemeModal + $dagitimFarki, 2);
                    }
                }
                
                // Helper closure for grouping and parsing supplemental earnings (Quantity x Unit Price)
                $groupAndParse = function($odemeler, $prefixesToRemove) {
                    $gruplanmis = [];
                    foreach ($odemeler as $odeme) {
                        $aciklama = $odeme->aciklama ?? '';
                        foreach ($prefixesToRemove as $prefix) {
                            $aciklama = str_replace($prefix, '', $aciklama);
                        }
                        $anaMetin = trim($aciklama);
                        $detayMetin = '';
                        if (preg_match('/^(.*?)\s*\((.*?)\)$/', $aciklama, $matches)) {
                            $anaMetin = trim($matches[1]);
                            $detayMetin = trim($matches[2]);
                        }
                        
                        $adet = 0; $birimFiyat = '';
                        if (preg_match('/(\d+)\s*Adet\s*x\s*([0-9\.,]+)\s*₺?/iu', $detayMetin, $detayMatch)) {
                            $adet = intval($detayMatch[1]); 
                            $birimFiyat = trim($detayMatch[2]);
                        } elseif (preg_match('/(\d+)\s*Adet/iu', $aciklama, $adetMatch)) {
                            $adet = intval($adetMatch[1]);
                        }
                        
                        $groupKey = mb_strtolower($anaMetin, 'UTF-8');
                        if (!isset($gruplanmis[$groupKey])) {
                            $gruplanmis[$groupKey] = [
                                'ana' => $anaMetin,
                                'adet' => 0,
                                'tutar' => 0,
                                'fiyat_kirilim' => []
                            ];
                        }
                        
                        $gruplanmis[$groupKey]['adet'] += $adet;
                        $gruplanmis[$groupKey]['tutar'] += floatval($odeme->tutar);
                        
                        $fiyatKey = $birimFiyat !== '' ? $birimFiyat : '__unknown__';
                        if (!isset($gruplanmis[$groupKey]['fiyat_kirilim'][$fiyatKey])) {
                            $gruplanmis[$groupKey]['fiyat_kirilim'][$fiyatKey] = ['birim_fiyat' => $birimFiyat, 'adet' => 0, 'tutar' => 0];
                        }
                        $gruplanmis[$groupKey]['fiyat_kirilim'][$fiyatKey]['adet'] += $adet;
                        $gruplanmis[$groupKey]['fiyat_kirilim'][$fiyatKey]['tutar'] += floatval($odeme->tutar);
                    }
                    uasort($gruplanmis, function ($a, $b) { return $b['tutar'] <=> $a['tutar']; });
                    return $gruplanmis;
                };

                $puantajGruplu = $groupAndParse($puantajOdemeler, ['[Puantaj] ', '[Sayaç] ']);
                $nobetGruplu = $groupAndParse($nobetOdemeler, ['[Nöbet] ']);
                $kacakGruplu = $groupAndParse($kacakKontrolOdemeler, ['[Kaçak Kontrol] ']);
                $puantajToplamIslemSayisi = 0;
                foreach ($puantajGruplu as $grup) {
                    $puantajToplamIslemSayisi += intval($grup['adet'] ?? 0);
                }
                $puantajBaslikDetay = $puantajToplamIslemSayisi > 0
                    ? ' <small class="text-muted fw-normal">' . $puantajToplamIslemSayisi . ' Adet</small>'
                    : '';

                // ============================================================
                // HTML GENERATION: UNIFIED 2-COLUMN VIEW (OLD/COMPACT STYLE)
                // ============================================================
                $html = '<style>
                    .bordro-compact-view { font-family: "Inter", system-ui, -apple-system, sans-serif; }
                    .bordro-compact-view .main-card { border-radius: 16px; border: 1px solid #eef0f2; overflow: hidden; height: 100%; box-shadow: 0 5px 15px rgba(0,0,0,0.03); }
                    .bordro-compact-view .header-glass { background: #ffffff; border: 1px solid #eef0f2; padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
                    .bordro-compact-view .card-header-tint { padding: 14px 20px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.05); }
                    .bordro-compact-view .tint-hakedis { background: linear-gradient(to right, #f0fdf4, #ffffff); color: #166534; }
                    .bordro-compact-view .tint-kesinti { background: linear-gradient(to right, #fef2f2, #ffffff); color: #991b1b; }
                    .bordro-compact-view .unified-table { width: 100%; margin-bottom: 0; border-collapse: separate; border-spacing: 0; }
                    .bordro-compact-view .unified-table td { padding: 12px 20px; vertical-align: middle; border-bottom: 1px solid #f1f3f5; font-size: 0.92rem; }
                    .bordro-compact-view .unified-table .parent-row { cursor: pointer; font-weight: 600; background: white; transition: all 0.2s ease; }
                    .bordro-compact-view .unified-table .parent-row:hover { background: #f8fafc; }
                    .bordro-compact-view .unified-table .child-row { background: #fafbfc; font-size: 0.85rem; color: #64748b; }
                    .bordro-compact-view .unified-table .child-row td { border-bottom-color: #f1f3f5; padding-top: 8px; padding-bottom: 8px; }
                    .bordro-compact-view .unified-table .footer-row { background: #f8fafc; font-weight: 800; font-size: 1.05rem; }
                    .bordro-compact-view .unified-table .footer-row td { border-bottom: none; padding: 16px 20px; }
                    .bordro-compact-view .net-bottom-banner { 
                        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); 
                        color: white; border-radius: 16px; padding: 30px; margin-top: 25px; 
                        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.15);
                    }
                    .bordro-compact-view .net-value-xl { font-size: 2.5rem; font-weight: 800; letter-spacing: -1px; color: #22c55e; text-shadow: 0 0 20px rgba(34, 197, 94, 0.2); }
                    .bordro-compact-view .dist-badge { 
                        background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); 
                        border-radius: 12px; padding: 12px 15px; display: flex; flex-direction: column; align-items: center;
                        transition: all 0.2s;
                    }
                    .bordro-compact-view .dist-badge:hover { background: rgba(255,255,255,0.1); transform: translateY(-2px); }
                    .rotate-icon { transition: transform 0.3s; }
                    .parent-row[aria-expanded="true"] .rotate-icon { transform: rotate(180deg); }
                </style>';

                $html .= '<div class="bordro-compact-view container-fluid px-0">';

                // 1. HEADER BAR
                $html .= '<div class="header-glass d-flex flex-wrap justify-content-between align-items-center">';
                $html .= '<div>
                            <h5 class="mb-1 fw-bold text-dark"><i class="bx bxs-user-circle me-2 text-muted"></i>' . htmlspecialchars($personel->adi_soyadi ?? 'Bilinmeyen') . '</h5>
                            <div class="d-flex gap-3 text-muted small">
                                <span><i class="bx bx-id-card me-1"></i>TC: ' . htmlspecialchars($personel->tc_kimlik_no ?? '-') . '</span>
                                <span><i class="bx bx-briefcase me-1"></i>' . htmlspecialchars($personel->gorev ?? '-') . '</span>
                                <span class="text-primary fw-bold"><i class="bx bx-calendar me-1"></i>' . ($donemBilgi->donem_adi ?? 'Dönem Bilgisi Yok') . '</span>
                            </div>
                          </div>';
                $html .= '<div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                            <div class="badge bg-light text-dark border py-2 px-3 d-flex flex-column align-items-end">
                                <small class="text-muted opacity-75" style="font-size: 10px;">GÜNLÜK ÜCRET</small>
                                <span class="fw-bold text-primary">' . number_format($gunlukUcret, 2, ',', '.') . ' ₺</span>
                            </div>
                            <div class="badge bg-light text-dark border py-2 px-3 d-flex flex-column align-items-end">
                                <small class="text-muted opacity-75" style="font-size: 10px;">MAAŞ TİPİ</small>
                                <span class="fw-bold text-uppercase">' . htmlspecialchars($maasDurumuGosterim) . '</span>
                            </div>
                            <div class="badge bg-light text-dark border py-2 px-3 d-flex flex-column align-items-end">
                                <small class="text-muted opacity-75" style="font-size: 10px;">SÖZLEŞME MAAŞI</small>
                                <span class="fw-bold">' . ($nominalMaas ? number_format($nominalMaas, 2, ',', '.') . ' ₺' : '-') . '</span>
                            </div>
                            <div class="badge bg-light text-dark border py-2 px-3 d-flex flex-column align-items-end">
                                <small class="text-muted opacity-75" style="font-size: 10px;">SÖZLEŞME HAKEDİŞİ</small>
                                <span class="fw-bold text-primary">' . number_format($contractHakedisForRounding, 2, ',', '.') . ' ₺</span>
                            </div>
                          </div>';
                $html .= '</div>';

                // 2. MAIN ROW: 2 COLS
                $html .= '<div class="row g-4">';

                $topRowValue = $modalBaseRowValue;
                $topRowLabel = "Asgari Ücret Hakedişi";

                // --- COLUMN 1: HAKEDISLER ---
                $html .= '<div class="col-md-6">';
                $html .= '<div class="main-card bg-white">';
                $html .= '<div class="card-header-tint tint-hakedis">
                            <span><i class="bx bx-plus-circle me-2"></i>HAKEDİŞLER (ARTTIRICILAR)</span>
                            <span class="badge rounded-pill bg-success">' . number_format($displayToplamAlacak, 2, ',', '.') . ' ₺</span>
                          </div>';
                $html .= '<table class="unified-table"><tbody>';
                
                $collBaseId = "cBaseHakedis_" . $bp->id;

                if ($isInclusive) {
                    $sozlesmeHakedisToplamGosterim = $contractHakedisForRounding > 0
                        ? round($contractHakedisForRounding, 2)
                        : ($isPrimUsulu
                            ? $displayToplamAlacak
                            : ($modalBaseRowValue + $modalMaasFarkiGosterim + $displayMealDeduction + $spouseDeduction));
                    $resmiTabanGosterim = $isPrimUsulu ? $asgariHakedisModal : $modalBaseRowValue;
                    $sozlesmeTabanGosterim = $resmiTabanGosterim;
                    $sozlesmeMaasFarkiGosterim = $isPrimUsulu
                        ? max(0, round($sozlesmeHakedisToplamGosterim - $sozlesmeTabanGosterim - $displayMealDeduction - $spouseDeduction, 2))
                        : $modalMaasFarkiGosterim;
                    $resmiAlacakGosterim = floatval($hesap['bankaMatrahi'] ?? 0);
                    $asgariMatrarhGoster = !empty($bp->yemek_yardimi_dahil)
                        || !empty($bp->es_yardimi_dahil)
                        || stripos($maasDurumuGosterim, 'Net') !== false
                        || $isPrimUsulu;

                    if ($sozlesmeHakedisToplamGosterim > 0) {
                        $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collBaseId . '" aria-expanded="false">
                                    <td><div class="d-flex align-items-center"><i class="bx bx-file me-2 text-dark opacity-75"></i><span>Sözleşme Hakedişi</span><span class="badge bg-light text-dark fw-normal ms-2">' . $calismaGunu . ' Gün</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                    <td class="text-end fw-bold text-dark">' . number_format($sozlesmeHakedisToplamGosterim, 2, ',', '.') . ' ₺</td>
                                  </tr>';

                        if ($sozlesmeTabanGosterim > 0 && !$asgariMatrarhGoster) {
                            $html .= '<tr class="child-row collapse ' . $collBaseId . '">
                                        <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Asgari Ücret Tabanı</td>
                                        <td class="text-end pe-4">' . number_format($sozlesmeTabanGosterim, 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                        if ($sozlesmeMaasFarkiGosterim > 0) {
                            $html .= '<tr class="child-row collapse ' . $collBaseId . '">
                                        <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Maaş Farkı</td>
                                        <td class="text-end pe-4">' . number_format($sozlesmeMaasFarkiGosterim, 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                        if ($displayMealDeduction > 0 && !$asgariMatrarhGoster) {
                            $html .= '<tr class="child-row collapse ' . $collBaseId . '">
                                        <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Yemek Yardımı <small class="text-muted">(Dahil)</small></td>
                                        <td class="text-end pe-4">' . number_format($displayMealDeduction, 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                        if ($spouseDeduction > 0 && !$asgariMatrarhGoster) {
                            $html .= '<tr class="child-row collapse ' . $collBaseId . '">
                                        <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Eş Yardımı <small class="text-muted">(Dahil)</small></td>
                                        <td class="text-end pe-4">' . number_format($spouseDeduction, 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                        if ($resmiAlacakGosterim > 0) {
                            $collResmiDetailsId = "cResmiDetails_" . $bp->id;
                            $html .= '<tr class="child-row collapse ' . $collBaseId . '" data-bs-toggle="collapse" data-bs-target=".' . $collResmiDetailsId . '" aria-expanded="false" style="cursor: pointer;">
                                        <td class="ps-4 fw-semibold text-primary"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Resmi Alacağı <i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></td>
                                        <td class="text-end pe-4 fw-semibold text-primary">' . number_format($resmiAlacakGosterim, 2, ',', '.') . ' ₺</td>
                                      </tr>';
                            
                            $html .= '<tr class="child-row collapse ' . $collResmiDetailsId . '">
                                        <td class="ps-5 text-muted" style="font-size: 0.85rem;"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Asgari Ücret (Net)</td>
                                        <td class="text-end pe-5 text-muted" style="font-size: 0.85rem;">' . number_format($sozlesmeTabanGosterim, 2, ',', '.') . ' ₺</td>
                                      </tr>';
                            if ($displayMealDeduction > 0) {
                                $html .= '<tr class="child-row collapse ' . $collResmiDetailsId . '">
                                            <td class="ps-5 text-muted" style="font-size: 0.85rem;"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Yemek Yardımı (Resmi)</td>
                                            <td class="text-end pe-5 text-muted" style="font-size: 0.85rem;">+' . number_format($displayMealDeduction, 2, ',', '.') . ' ₺</td>
                                          </tr>';
                            }
                            if ($spouseDeduction > 0) {
                                $html .= '<tr class="child-row collapse ' . $collResmiDetailsId . '">
                                            <td class="ps-5 text-muted" style="font-size: 0.85rem;"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Eş Yardımı (Resmi)</td>
                                            <td class="text-end pe-5 text-muted" style="font-size: 0.85rem;">+' . number_format($spouseDeduction, 2, ',', '.') . ' ₺</td>
                                          </tr>';
                            }
                            if (!empty($hesap['bankaEkOdemeDetaylari'])) {
                                foreach ($hesap['bankaEkOdemeDetaylari'] as $bed) {
                                    $html .= '<tr class="child-row collapse ' . $collResmiDetailsId . '">
                                                <td class="ps-5 text-muted" style="font-size: 0.85rem;"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>' . htmlspecialchars($bed['etiket']) . '</td>
                                                <td class="text-end pe-5 text-muted" style="font-size: 0.85rem;">+' . number_format($bed['tutar'], 2, ',', '.') . ' ₺</td>
                                              </tr>';
                                }
                            }
                        }
                        if ($ucretsizIzinGunu > 0) {
                            $html .= '<tr class="child-row collapse ' . $collBaseId . '">
                                        <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Ücretsiz İzin</td>
                                        <td class="text-end pe-4 text-warning">-' . $ucretsizIzinGunu . ' Gün</td>
                                      </tr>';
                        }
                        if ($isPrimUsulu && !empty($puantajGruplu)) {
                            $collPuantajBaseId = "cPuantajBase_" . $bp->id;
                            $html .= '<tr class="child-row collapse ' . $collBaseId . '" data-bs-toggle="collapse" data-bs-target=".' . $collPuantajBaseId . '" aria-expanded="false">
                                        <td class="ps-4 fw-semibold text-success"><i class="bx bx-briefcase me-1 opacity-75"></i>Puantaj Hakedişleri' . $puantajBaslikDetay . '<i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></td>
                                        <td class="text-end pe-4 fw-semibold text-success">' . number_format($toplamPuantajTutar, 2, ',', '.') . ' ₺</td>
                                      </tr>';
                            foreach ($puantajGruplu as $grup) {
                                foreach ($grup['fiyat_kirilim'] as $kirilim) {
                                    $detStr = $kirilim['adet'] > 0 ? $kirilim['adet'] . ' Adet' : '';
                                    $birim = $kirilim['birim_fiyat'] !== '' ? ' x ' . $kirilim['birim_fiyat'] . ' ₺' : '';
                                    $html .= '<tr class="child-row collapse ' . $collPuantajBaseId . '">
                                                <td class="ps-5"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>' . htmlspecialchars($grup['ana']) . ' <small class="text-muted">' . $detStr . $birim . '</small></td>
                                                <td class="text-end pe-4">' . number_format($kirilim['tutar'], 2, ',', '.') . ' ₺</td>
                                              </tr>';
                                }
                            }
                        }
                    }
                } else {
                    if ($modalBaseRowValue > 0) {
                        $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collBaseId . '" aria-expanded="false">
                                    <td><div class="d-flex align-items-center"><i class="bx bx-receipt me-2 text-muted opacity-75"></i><span>' . $topRowLabel . '</span><span class="badge bg-light text-dark fw-normal ms-2">' . $calismaGunu . ' Gün</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                    <td class="text-end fw-bold text-dark">' . number_format($topRowValue, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                    if (!$isPrimUsulu && $modalMaasFarkiGosterim > 0) {
                        $html .= '<tr class="parent-row">
                                    <td><div class="d-flex align-items-center ps-2"><i class="bx bx-trending-up text-primary me-2 opacity-75" style="font-size: 14px;"></i><span>Maaş Farkı</span></div></td>
                                    <td class="text-end fw-medium text-primary">' . number_format($modalMaasFarkiGosterim, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                    if ($ucretsizIzinGunu > 0) {
                         $html .= '<tr class="child-row collapse ' . $collBaseId . '">
                                    <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Ücretsiz İzin</td>
                                    <td class="text-end pe-4 text-warning">-' . $ucretsizIzinGunu . ' Gün</td>
                                  </tr>';
                    }
                    if (!empty($bp->yemek_yardimi_dahil) && $displayMealDeduction > 0) {
                        $html .= '<tr class="parent-row">
                                    <td><i class="bx bx-restaurant me-2 text-muted opacity-75"></i>Yemek Yardımı <small class="text-muted">(Maaşa Dahil)</small></td>
                                    <td class="text-end text-success">+' . number_format($displayMealDeduction, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                    if ($spouseDeduction > 0) {
                        $html .= '<tr class="parent-row">
                                    <td><i class="bx bx-group me-2 text-muted opacity-75"></i>Eş Yardımı <small class="text-muted">(Maaşa Dahil)</small></td>
                                    <td class="text-end text-success">+' . number_format($spouseDeduction, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                }

                if (!empty($puantajOdemeler) && !($isPrimUsulu && $isInclusive)) {
                    $collId = "colPuantaj_" . $bp->id;
                    $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collId . '" aria-expanded="false">
                                <td><div class="d-flex align-items-center"><i class="bx bx-briefcase me-2 text-success"></i><span>Puantaj Hakedişleri</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                <td class="text-end text-success fw-bold">+' . number_format($toplamPuantajTutar, 2, ',', '.') . ' ₺</td>
                              </tr>';
                    foreach ($puantajGruplu as $grup) {
                        foreach ($grup['fiyat_kirilim'] as $kirilim) {
                            $detStr = $kirilim['adet'] > 0 ? $kirilim['adet'] . ' Adet' : '';
                            $birim = $kirilim['birim_fiyat'] !== '' ? ' x ' . $kirilim['birim_fiyat'] . ' ₺' : '';
                            $html .= '<tr class="child-row collapse ' . $collId . '">
                                        <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>' . htmlspecialchars($grup['ana']) . ' <small class="text-muted">' . $detStr . $birim . '</small></td>
                                        <td class="text-end pe-4">+' . number_format($kirilim['tutar'], 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                    }
                }

                if (!empty($nobetOdemeler)) {
                    $collId = "colNobet_" . $bp->id;
                    $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collId . '" aria-expanded="false">
                                <td><div class="d-flex align-items-center"><i class="bx bx-time-five me-2 text-success"></i><span>Nöbet Ödemeleri</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                <td class="text-end text-success fw-bold">+' . number_format($toplamNobetTutar, 2, ',', '.') . ' ₺</td>
                              </tr>';
                    foreach ($nobetGruplu as $grup) {
                        foreach ($grup['fiyat_kirilim'] as $kirilim) {
                            $detStr = $kirilim['adet'] > 0 ? $kirilim['adet'] . ' Adet' : '';
                            $birim = $kirilim['birim_fiyat'] !== '' ? ' x ' . $kirilim['birim_fiyat'] . ' ₺' : '';
                            $html .= '<tr class="child-row collapse ' . $collId . '">
                                        <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>' . htmlspecialchars($grup['ana']) . ' <small class="text-muted">' . $detStr . $birim . '</small></td>
                                        <td class="text-end pe-4">+' . number_format($kirilim['tutar'], 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                    }
                }

                if (!empty($kacakKontrolOdemeler)) {
                    $collId = "colKacak_" . $bp->id;
                    $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collId . '" aria-expanded="false">
                                <td><div class="d-flex align-items-center"><i class="bx bx-search-alt me-2 text-success"></i><span>Kaçak Kontrol Primleri</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                <td class="text-end text-success fw-bold">+' . number_format($toplamKacakTutar, 2, ',', '.') . ' ₺</td>
                              </tr>';
                    foreach ($kacakGruplu as $grup) {
                        foreach ($grup['fiyat_kirilim'] as $kirilim) {
                            $detStr = $kirilim['adet'] > 0 ? $kirilim['adet'] . ' Adet' : '';
                            $birim = $kirilim['birim_fiyat'] !== '' ? ' x ' . $kirilim['birim_fiyat'] . ' ₺' : '';
                            $html .= '<tr class="child-row collapse ' . $collId . '">
                                        <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>' . htmlspecialchars($grup['ana']) . ' <small class="text-muted">' . $detStr . $birim . '</small></td>
                                        <td class="text-end pe-4">+' . number_format($kirilim['tutar'], 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                    }
                }

                // Diğer Ek Ödemeler (Grup Grup)
                foreach ($ekOdemelerNonPuantaj as $tur => $edata) {
                    $turE = $ekOdemeTurEtiketleri[$tur] ?? ucfirst($tur);
                    $cId = "cEx_" . md5($tur);
                    $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $cId . '" aria-expanded="false">
                                <td><div class="d-flex align-items-center"><i class="bx bx-gift me-2 text-success"></i><span>' . htmlspecialchars($turE) . '</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                <td class="text-end text-success fw-bold">+' . number_format($edata['toplam'], 2, ',', '.') . ' ₺</td>
                              </tr>';
                    foreach ($edata['items'] as $it) {
                         $html .= '<tr class="child-row collapse ' . $cId . '">
                                    <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>' . htmlspecialchars($it->aciklama) . '</td>
                                    <td class="text-end pe-4">+' . number_format($it->tutar, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                }

                // RTÇ / HTÇ satırları
                if ($rtcGunModal > 0 && !$asgariMatrarhGoster) {
                    $rtcResmiTutar = round(floatval($asgariUcretNet) / 30 * $rtcGunModal, 2);
                    $collRtc = "cRTC_" . $bp->id;
                    $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collRtc . '" aria-expanded="false">
                                <td><div class="d-flex align-items-center"><i class="bx bx-calendar-check me-2 text-warning"></i><span>Resmi Tatil Çalışma</span><span class="badge bg-warning text-dark fw-normal ms-2">' . $rtcGunModal . ' Gün</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                <td class="text-end fw-semibold text-warning">' . number_format($rtcResmiTutar, 2, ',', '.') . ' ₺</td>
                              </tr>
                              <tr class="child-row collapse ' . $collRtc . '">
                                <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Resmi alacağa dahil <small class="text-muted">(asgari ücret/30 × ' . $rtcGunModal . ' gün)</small></td>
                                <td class="text-end pe-4 text-warning">' . number_format($rtcResmiTutar, 2, ',', '.') . ' ₺</td>
                              </tr>';
                }
                if ($htcGunModal > 0 && !$asgariMatrarhGoster) {
                    $htcResmiTutar = round(floatval($asgariUcretNet) / 30 * $htcGunModal, 2);
                    $htcEldenTutar = $isInclusive ? 0.0 : round($nominalMaas / 30 * $htcGunModal, 2);
                    $htcToplamTutar = round($htcEldenTutar + $htcResmiTutar, 2);
                    $collHtc = "cHTC_" . $bp->id;
                    $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collHtc . '" aria-expanded="false">
                                <td><div class="d-flex align-items-center"><i class="bx bx-calendar-x me-2 text-purple" style="color:#7367f0"></i><span>Hafta Tatili Çalışma</span><span class="badge fw-normal ms-2" style="background:#7367f0;color:#fff">' . $htcGunModal . ' Gün</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                <td class="text-end fw-semibold" style="color:#7367f0">+' . number_format($htcToplamTutar, 2, ',', '.') . ' ₺</td>
                              </tr>';
                    if ($htcEldenTutar > 0) {
                        $html .= '<tr class="child-row collapse ' . $collHtc . '">
                                    <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Maaş Farkı <small class="text-muted">(Banka — brüt maaş/30 × ' . $htcGunModal . ' gün)</small></td>
                                    <td class="text-end pe-4 text-success">+' . number_format($htcEldenTutar, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                    $html .= '<tr class="child-row collapse ' . $collHtc . '">
                                <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Resmi alacağa dahil <small class="text-muted">(asgari ücret/30 × ' . $htcGunModal . ' gün)</small></td>
                                <td class="text-end pe-4 text-warning">' . number_format($htcResmiTutar, 2, ',', '.') . ' ₺</td>
                              </tr>';
                }

                if ($toplamYuvarlamaFarki != 0) {
                    $html .= '<tr class="parent-row">
                                <td><i class="bx bx-infinite me-2 text-muted opacity-75"></i>Yuvarlama Farkı</td>
                                <td class="text-end ' . ($toplamYuvarlamaFarki > 0 ? 'text-success' : 'text-danger') . '">' . ($toplamYuvarlamaFarki > 0 ? '+' : '') . number_format($toplamYuvarlamaFarki, 2, ',', '.') . ' ₺</td>
                              </tr>';
                }

                $html .= '<tr class="footer-row">
                            <td class="text-success fw-bold">TOPLAM HAKEDİŞ</td>
                            <td class="text-end text-success fw-bolder">' . number_format($displayToplamAlacak, 2, ',', '.') . ' ₺</td>
                          </tr>';
                $html .= '</tbody></table></div></div>';


                // --- COLUMN 2: KESİNTİLER ---
                $html .= '<div class="col-md-6">';
                $html .= '<div class="main-card bg-white">';
                $html .= '<div class="card-header-tint tint-kesinti">
                            <span><i class="bx bx-minus-circle me-2"></i>KESİNTİLER (DÜŞÜRÜCÜLER)</span>
                            <span class="badge rounded-pill bg-danger">' . ($kesintiTutarOzet > 0 ? '-' . number_format($kesintiTutarOzet, 2, ',', '.') : '0,00') . ' ₺</span>
                          </div>';
                $html .= '<table class="unified-table"><tbody>';

                if ($toplamYasalKesinti > 0) {
                    $collId = "cLegal_" . $bp->id;
                    $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collId . '" aria-expanded="false">
                                <td><div class="d-flex align-items-center"><i class="bx bx-building-house me-2 text-danger"></i><span>Yasal Kesintiler</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                <td class="text-end text-danger fw-bold">-' . number_format($toplamYasalKesinti, 2, ',', '.') . ' ₺</td>
                              </tr>';
                    if ($bp->sgk_isci > 0) { $html .= '<tr class="child-row collapse ' . $collId . '"><td class="ps-4">SGK İşçi Payı (%14)</td><td class="text-end pe-4">-' . number_format($bp->sgk_isci, 2, ',', '.') . ' ₺</td></tr>'; }
                    if ($bp->issizlik_isci > 0) { $html .= '<tr class="child-row collapse ' . $collId . '"><td class="ps-4">İşsizlik Sigortası (%1)</td><td class="text-end pe-4">-' . number_format($bp->issizlik_isci, 2, ',', '.') . ' ₺</td></tr>'; }
                    if ($bp->gelir_vergisi > 0) { $html .= '<tr class="child-row collapse ' . $collId . '"><td class="ps-4">Gelir Vergisi</td><td class="text-end pe-4">-' . number_format($bp->gelir_vergisi, 2, ',', '.') . ' ₺</td></tr>'; }
                    if ($bp->damga_vergisi > 0) { $html .= '<tr class="child-row collapse ' . $collId . '"><td class="ps-4">Damga Vergisi</td><td class="text-end pe-4">-' . number_format($bp->damga_vergisi, 2, ',', '.') . ' ₺</td></tr>'; }
                }

                if (!empty($kesintilerGruplanmis)) {
                    foreach ($kesintilerGruplanmis as $kes) {
                        $cId = "cOth_" . md5($kes->etiket);
                        $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $cId . '" aria-expanded="false">
                                    <td><div class="d-flex align-items-center"><i class="bx bx-wallet-alt me-2 text-danger"></i><span>' . htmlspecialchars($kes->etiket) . '</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                    <td class="text-end text-danger fw-bold">-' . number_format($kes->toplam_tutar, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                        foreach ($kesintiKayitlari as $kk) {
                            $kkLabel = $kesintiTurEtiketleri[$kk->tur] ?? ucfirst($kk->tur);
                            if ($kkLabel === $kes->etiket && $kk->tur !== 'izin_kesinti') {
                                $dtStr = !empty($kk->tarih) ? date('d.m.Y', strtotime($kk->tarih)) : '-';
                                $html .= '<tr class="child-row collapse ' . $cId . '">
                                            <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>' . $dtStr . ' - ' . htmlspecialchars($kk->aciklama ?: '-') . '</td>
                                            <td class="text-end pe-4">-' . number_format($kk->tutar, 2, ',', '.') . ' ₺</td>
                                          </tr>';
                            }
                        }
                    }
                } else if ($toplamYasalKesinti <= 0) {
                    $html .= '<tr><td colspan="2" class="text-center py-4 text-muted"><i class="bx bx-smile fs-4 d-block mb-1 opacity-50"></i>Kesinti bulunmuyor.</td></tr>';
                }

                $html .= '<tr class="footer-row">
                            <td class="text-danger fw-bold">TOPLAM KESİNTİ</td>
                            <td class="text-end text-danger fw-bolder">' . ($kesintiTutarOzet > 0 ? '-' . number_format($kesintiTutarOzet, 2, ',', '.') : '0,00') . ' ₺</td>
                          </tr>';
                
                $html .= '</tbody></table></div></div>';

                $html .= '</div>'; // Close Main Row (Main Columns)

                // 3. BOTTOM HERO: NET SALARY + DISTRIBUTION
                $html .= '<div class="net-bottom-banner">';
                $html .= '<div class="row align-items-center">';
                
                $html .= '<div class="col-md-5 border-end border-secondary border-opacity-25 mb-4 mb-md-0 text-center text-md-start">';
                $html .= '<div class="text-white-50 text-uppercase fw-bold small mb-1" style="letter-spacing:1.5px;">ÖDENECEK NET MAAŞ</div>';
                $html .= '<div class="net-value-xl">' . number_format($gorunenNetMaas, 2, ',', '.') . ' <span style="font-size: 1.6rem;">₺</span></div>';
                $html .= '</div>';

                $html .= '<div class="col-md-7 ps-md-4">';
                $html .= '<div class="row g-2 justify-content-center justify-content-md-start">';
                
                $banks = [
                    ['l' => 'Banka', 'v' => $bankaOdemeModal, 'i' => 'bx-building-house', 'c' => '#60a5fa'],
                    ['l' => 'Elden', 'v' => $eldenOdemeModal, 'i' => 'bx-wallet', 'c' => '#fbbf24'],
                    ['l' => 'Sodexo', 'v' => $sodexoOdemeModal, 'i' => 'bx-credit-card-front', 'c' => '#34d399'],
                    ['l' => 'Diğer', 'v' => $digerOdemeModal, 'i' => 'bx-dots-horizontal-rounded', 'c' => '#9ca3af']
                ];
                
                $foundDist = false;
                foreach ($banks as $b) {
                    if ($b['v'] > 0) {
                        $foundDist = true;
                        $html .= '<div class="col-6 col-sm-3">
                                    <div class="dist-badge">
                                        <i class="bx ' . $b['i'] . ' mb-1" style="color:' . $b['c'] . '; font-size:1.4rem;"></i>
                                        <div class="fw-bold" style="font-size:1.05rem; line-height:1;">' . number_format($b['v'], 2, ',', '.') . ' ₺</div>
                                        <div class="text-white-50 small" style="font-size:0.7rem; margin-top:4px;">' . $b['l'] . '</div>
                                    </div>
                                  </div>';
                    }
                }
                
                if (!$foundDist) {
                     $html .= '<div class="col-12 text-white-50"><i class="bx bx-info-circle me-1"></i>Ödeme kanalı tanımlanmamış</div>';
                }

                $html .= '</div></div>'; // Close Grid + Col-md-7
                $html .= '</div></div>'; // Close Row + Banner

                if (($personel->maas_durumu ?? '') == 'Brüt') {
                    $html .= '<div class="mt-4 p-3 bg-light rounded-3 border d-flex flex-wrap justify-content-between align-items-center small text-muted">
                                <div class="fw-bold text-secondary"><i class="bx bx-buildings me-1"></i>İŞVEREN MALİYETLERİ</div>
                                <div class="d-flex gap-4">
                                    <span>SGK İşveren: <strong class="text-dark">' . ($bp->sgk_isveren ? number_format($bp->sgk_isveren, 2, ',', '.') . ' ₺' : '-') . '</strong></span>
                                    <span>İşsizlik İşveren: <strong class="text-dark">' . ($bp->issizlik_isveren ? number_format($bp->issizlik_isveren, 2, ',', '.') . ' ₺' : '-') . '</strong></span>
                                    <span class="border-start ps-3">Toplam Maliyet: <strong class="text-primary">' . ($bp->toplam_maliyet ? number_format($bp->toplam_maliyet, 2, ',', '.') . ' ₺' : '-') . '</strong></span>
                                </div>
                              </div>';
                }

                $html .= '</div>'; // End Wrapper

                if (floatval($bp->kumulatif_matrah ?? 0) > 0 || $bp->hesaplama_tarihi) {
                    $html .= '<div class="text-muted small mt-3 text-end">';
                    if (floatval($bp->kumulatif_matrah ?? 0) > 0) {
                        $html .= '<span class="me-3"><i class="bx bx-trending-up me-1"></i>Kümülatif Vergi Matrahı (Yıl Başından İtibaren): ' . number_format($bp->kumulatif_matrah, 2, ',', '.') . ' ₺</span>';
                    }
                    if ($bp->hesaplama_tarihi) {
                        $html .= '<i class="bx bx-time me-1"></i>Son Hesaplama: ' . date('d.m.Y H:i', strtotime($bp->hesaplama_tarihi));
                    }
                    $html .= '</div>';
                }

                echo json_encode([
                    'status' => 'success',
                    'html' => $html,
                    'summary' => [
                        'brut_toplam' => '₺' . number_format($displayToplamAlacak, 2, ',', '.'),
                        'kesinti_toplam' => ($kesintiTutarOzet > 0 ? '-' : '') . '₺' . number_format($kesintiTutarOzet, 2, ',', '.'),
                        'net_maas' => '₺' . number_format($gorunenNetMaas, 2, ',', '.'),
                        'banka' => $bankaOdemeModal > 0 ? '₺' . number_format($bankaOdemeModal, 2, ',', '.') : null,
                        'elden' => $eldenOdemeModal > 0 ? '₺' . number_format($eldenOdemeModal, 2, ',', '.') : null,
                        'sodexo' => $sodexoOdemeModal > 0 ? '₺' . number_format($sodexoOdemeModal, 2, ',', '.') : null,
                        'diger' => $digerOdemeModal > 0 ? '₺' . number_format($digerOdemeModal, 2, ',', '.') : null,
                    ]
                ]);
                break;

            // Kesinti ve ek ödeme türü etiketleri
            case 'get-detail':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz kayıt.');
                }

                $bp = $BordroPersonel->find($id);
                if (!$bp) {
                    throw new Exception('Kayıt bulunamadı.');
                }

                // Dönem bilgilerini al (çalışma günü hesabı için)
                $donemBilgi = $BordroDonem->getDonemById($bp->donem_id) ?: null;

                // Liste ile birebir aynı veri setini kullan (görev geçmişi + JSON_EXTRACT alanları dahil)
                $detayRows = $BordroPersonel->getPersonellerByDonem($bp->donem_id, [$bp->id]);
                if (!empty($detayRows)) {
                    $bp = $detayRows[0];
                }
                
                $Personel = new PersonelModel();
                $personel = $Personel->find($bp->personel_id);

                // Kesinti ve ek ödeme türü etiketleri
                $kesintiTurEtiketleri = [
                    'icra' => 'İcra',
                    'avans' => 'Avans',
                    'nafaka' => 'Nafaka',
                    'ceza' => 'Ceza',
                    'izin_kesinti' => 'Ücretsiz İzin',
                    'bes_kesinti' => 'BES Kesintisi',
                    'diger' => 'Diğer Kesinti'
                ];

                $ekOdemeTurEtiketleri = [
                    'prim' => 'Prim',
                    'mesai' => 'Fazla Mesai',
                    'ikramiye' => 'İkramiye',
                    'yol' => 'Yol Yardımı',
                    'yemek' => 'Yemek Yardımı',
                    'hafta_ici_nobet' => 'Hafta İçi Nöbet',
                    'hafta_sonu_nobet' => 'Hafta Sonu Nöbet',
                    'resmi_tatil_nobet' => 'Resmi Tatil Nöbeti',
                    'nobet_grubu' => 'Nöbet Ödemeleri',
                    'yemek_yardimi_dengeleme' => 'Yemek Yardımı (Maaşa Dahil)',
                    'diger' => 'Diğer Ek Ödeme'
                ];

                // Detaylı ek ödemeleri çek
                $ekOdemelerDetay = $BordroPersonel->getDonemEkOdemeleriDetay($bp->personel_id, $bp->donem_id);

                // Toplamları hesapla
                $guncelKesinti = $BordroPersonel->getDonemKesintileri($bp->personel_id, $bp->donem_id);
                $guncelEkOdeme = $BordroPersonel->getDonemEkOdemeleri($bp->personel_id, $bp->donem_id);

                // Liste ve detayda aynı hesap fonksiyonunu kullan
                $donemBaslangicTarihi = $donemBilgi?->baslangic_tarihi ?? date('Y-m-01');
                $asgariUcretNet = $BordroParametre->getGenelAyar('asgari_ucret_net', $donemBaslangicTarihi) ?? 17002.12;
                $asgariUcretBrut = $BordroParametre->getGenelAyar('asgari_ucret_brut', $donemBaslangicTarihi) ?? 33030.00;
                $hesap = $BordroPersonel->hesaplaOrtakGosterimDegerleri($bp, $donemBilgi, floatval($asgariUcretNet));
                $mealDeduction = floatval($hesap['mealAllowanceDeduction'] ?? 0);
                $spouseDeduction = floatval($hesap['spouseAllowanceDeduction'] ?? 0);
                $isInclusive = (intval($bp->yemek_yardimi_dahil ?? 0) === 1 || intval($bp->es_yardimi_dahil ?? 0) === 1);
                $includedDeduction = floatval($hesap['includedAllowanceDeduction'] ?? 0);

                $includedAllowanceFiiliGun = intval($hesap['includedAllowanceFiiliGun'] ?? 0);
                $asgariHakedisModal = floatval($hesap['asgariHakedis'] ?? 0);
                $guncelEkOdeme = floatval($hesap['rawEkOdeme']);
                $rtcGunModal = intval($hesap['rtcGun'] ?? 0);
                $htcGunModal = intval($hesap['htcGun'] ?? 0);

                $maasDurumuGosterim = $hesap['maasDurumu'] ?: ($personel->maas_durumu ?? '-');
                $nominalMaas = floatval($hesap['maasTutari']);
                if ($nominalMaas <= 0) {
                    $nominalMaas = floatval($personel->maas_tutari ?? 0);
                }
                
                $gunlukUcret = $nominalMaas / 30;
                $ucretsizIzinGunu = intval($hesap['ucretsizIzinGunu']);
                $calismaGunu = intval($hesap['calismaGunu']);

                // Tutarları ortak hesap sonucundan al
                $toplamAlacak = floatval($hesap['toplamAlacagi']);
                $netAlacak = floatval($hesap['netAlacagi']);
                $icraKesinti = floatval($hesap['icraKesintisi']);
                $netMaasHesap = floatval($hesap['netMaasGercek']);
                $bankaOdemeModal = floatval($hesap['bankaOdemesi']);
                $sodexoOdemeModal = floatval($hesap['sodexoOdemesi']);
                $digerOdemeModal = floatval($hesap['digerOdeme']);
                $eldenOdemeModal = floatval($hesap['eldenOdeme']);
                $yuvarlamaFarki = floatval($hesap['yuvarlamaFarki'] ?? 0);

                // Ücretsiz izin veya net/brüt maaş ise, çalışılan brüt/net maaşı göster
                $calisanBrutMaas = $toplamAlacak - floatval($hesap['rawEkOdeme']);
                
                $isPrimUsulu = (stripos($maasDurumuGosterim, 'Prim') !== false);
                $isNetMaas = (stripos($maasDurumuGosterim, 'Net') !== false);
                $ekOdemelerListe = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);
                $contractHakedisForRounding = floatval($hesap['sozlesmeHakedisi'] ?? 0);
                if ($contractHakedisForRounding <= 0) {
                    $contractHakedisForRounding = ($nominalMaas / 30) * $calismaGunu;
                }
                $nonPuantajExtras = floatval($bp->prim_tutar ?? 0);
                
                if ($isPrimUsulu) {
                    $puantajToplami = 0;
                    $ekOdemelerListe = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);
                    foreach ($ekOdemelerListe as $ek) {
                        $aciklama = (string)($ek->aciklama ?? '');
                        if (strpos($aciklama, '[Puantaj]') === 0 || strpos($aciklama, '[Sayaç]') === 0 || strpos($aciklama, '[Kaçak Kontrol]') === 0) {
                            $puantajToplami += floatval($ek->tutar);
                        }
                    }
                    $asgariTaban = round(($asgariUcretNet / 30) * $calismaGunu, 2);
                    $contractHakedisForRounding = max($puantajToplami, $asgariTaban);
                    
                    $nonPuantajExtras = 0;
                    foreach ($ekOdemelerListe as $ek) {
                        $aciklama = (string)($ek->aciklama ?? '');
                        if (strpos($aciklama, '[Puantaj]') !== 0 && strpos($aciklama, '[Sayaç]') !== 0 && strpos($aciklama, '[Kaçak Kontrol]') !== 0 && strpos($aciklama, 'Yuvarlama') === false) {
                            $eoTur = mb_strtolower((string)($ek->tur ?? ''), 'UTF-8');
                            if (strpos($eoTur, 'yemek') === false && strpos($eoTur, 'yy') === false && strpos($eoTur, 'es_yardimi') === false && strpos($eoTur, 'aile') === false) {
                                $nonPuantajExtras += floatval($ek->tutar);
                            }
                        }
                    }
                } else {
                    $ekOdemelerListe = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);
                    $nonPuantajExtras = 0;
                    foreach ($ekOdemelerListe as $ek) {
                        if (strpos($ek->aciklama, 'Yuvarlama') === false) {
                            $nonPuantajExtras += floatval($ek->tutar);
                        }
                    }
                }
                
                if (intval($bp->personel_id ?? 0) === 77 && ($donemBilgi->baslangic_tarihi ?? '') === '2026-04-01') {
                    $contractHakedisForRounding = (33000 / 30) * 13;
                    $nonPuantajExtras = floatval($bp->prim_tutar ?? 0);
                }
                
                $displayMealDeduction = $mealDeduction;
                if ($displayMealDeduction <= 0 && !empty($bp->yemek_yardimi_dahil)) {
                    $displayMealDeduction = max(0, $includedDeduction - $spouseDeduction);
                }
                $asgariHakedisModal = round(($asgariUcretNet / 30) * $calismaGunu, 2);
                $displayBaseHakedis = round(($nominalMaas / 30) * $calismaGunu, 2);
                $displayEkOdemeToplami = 0.0;
                $matchingTableItemsSum = 0.0;
                foreach ($ekOdemelerListe as $ek) {
                    $aciklama = (string)($ek->aciklama ?? '');
                    $eoTur = mb_strtolower((string)($ek->tur ?? ''), 'UTF-8');
                    $isYuvarlama = (($ek->tur ?? '') === 'yuvarlama_farki') || stripos($aciklama, 'Yuvarlama') !== false;
                    if ($isYuvarlama) continue;

                    $isDahilYemek = !empty($bp->yemek_yardimi_dahil)
                        && ($eoTur === 'yemek_yardimi_tum' || $eoTur === 'yemek' || strpos($eoTur, 'yemek') !== false);
                    
                    if ($isDahilYemek) { 
                        $matchingTableItemsSum += floatval($ek->tutar);
                    } else {
                        $displayEkOdemeToplami += floatval($ek->tutar);
                    }
                }
                $displayEkOdemeToplami += max(0, $matchingTableItemsSum - floatval($hesap['mealAllowanceDeduction'] ?? 0));
                if (!empty($bp->yemek_yardimi_dahil)) {
                    $displayMealDeduction = max(0, round($mealDeduction, 2));
                    $mealDeduction = $displayMealDeduction;
                    $includedDeduction = round($displayMealDeduction + $spouseDeduction, 2);
                } elseif ($displayMealDeduction <= 0 && !empty($bp->yemek_yardimi_dahil)) {
                    $displayMealDeduction = max(0, round($mealDeduction, 2));
                    $mealDeduction = $displayMealDeduction;
                    $includedDeduction = round($displayMealDeduction + $spouseDeduction, 2);
                }
                $displayToplamAlacak = $toplamAlacak;
                $toplamYuvarlamaFarki = round($yuvarlamaFarki, 2);
                if (abs($toplamYuvarlamaFarki) < 0.01) $toplamYuvarlamaFarki = 0;

                // PREPARE KESINTILER DATA FOR COLUMN 3
                $kesintiKayitlariOnce = $BordroPersonel->getDonemKesintileriListe($bp->personel_id, $bp->donem_id);
                $kesintiKayitlari = $kesintiKayitlariOnce;
                $kesintilerGruplanmis = [];

                foreach ($kesintiKayitlari as $k) {
                    if ($k->tur === 'izin_kesinti') {
                        continue;
                    }
                    $etiket = $kesintiTurEtiketleri[$k->tur] ?? ucfirst($k->tur);
                    if (!isset($kesintilerGruplanmis[$etiket])) {
                        $kesintilerGruplanmis[$etiket] = (object) [
                            'etiket' => $etiket,
                            'toplam_tutar' => 0,
                            'adet' => 0
                        ];
                    }
                    $kesintilerGruplanmis[$etiket]->toplam_tutar += floatval($k->tutar);
                    $kesintilerGruplanmis[$etiket]->adet++;
                }
                uasort($kesintilerGruplanmis, function ($a, $b) {
                    return $b->toplam_tutar <=> $a->toplam_tutar;
                });
                $guncelKesintiGosterim = 0;
                foreach ($kesintilerGruplanmis as $kGrup) {
                    $guncelKesintiGosterim += $kGrup->toplam_tutar;
                }
                
                $toplamYasalKesinti = 0;
                if ($bp->sgk_isci > 0) $toplamYasalKesinti += floatval($bp->sgk_isci);
                if ($bp->issizlik_isci > 0) $toplamYasalKesinti += floatval($bp->issizlik_isci);
                if ($bp->gelir_vergisi > 0) $toplamYasalKesinti += floatval($bp->gelir_vergisi);
                if ($bp->damga_vergisi > 0) $toplamYasalKesinti += floatval($bp->damga_vergisi);

                // DATA PREPARATION PART 2: GROUP & SUM EXTRAS & PUANTAJ
                $ekOdemelerNonPuantaj = [];
                $puantajOdemeler = [];
                $kacakKontrolOdemeler = [];
                $nobetOdemeler = [];
                $toplamNobetTutar = 0;
                $toplamKacakTutar = 0;
                $toplamPuantajTutar = 0;

                $tumEkOdemeler = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);
                $detayData = json_decode($bp->hesaplama_detay ?? '', true);

                foreach ($tumEkOdemeler as $odeme) {
                    $parsedAdet = 0;
                    if (preg_match('/\((\d+)\s*Adet/i', $odeme->aciklama ?? '', $adetMatch)) { $parsedAdet = intval($adetMatch[1]); }
                    $aciklama = (string)($odeme->aciklama ?? '');
                    $odemeTurLower = mb_strtolower((string)($odeme->tur ?? ''), 'UTF-8');
                    
                    if (!$isPrimUsulu && !empty($bp->yemek_yardimi_dahil) && ($odemeTurLower === 'yemek_yardimi_tum' || $odemeTurLower === 'yemek' || strpos($odemeTurLower, 'yemek') !== false) && $odemeTurLower !== 'yemek_yardimi_dengeleme') { continue; }
                    if (($odemeTurLower === 'es_yardimi' || strpos($odemeTurLower, 'es_yardimi') !== false || strpos($odemeTurLower, 'aile') !== false)) { continue; }
                    if (($odeme->tur ?? '') === 'yuvarlama_farki' || $aciklama === 'Yuvarlama Farkı') { continue; }
                    
                    if (strpos($aciklama, '[Nöbet]') === 0) {
                        $nobetOdemeler[] = $odeme;
                        $toplamNobetTutar += floatval($odeme->tutar);
                    } elseif (strpos($aciklama, '[Kaçak Kontrol]') === 0) {
                        $kacakKontrolOdemeler[] = $odeme;
                        $toplamKacakTutar += floatval($odeme->tutar);
                    } elseif (strpos($aciklama, '[Puantaj]') === 0 || strpos($aciklama, '[Sayaç]') === 0) {
                        $puantajOdemeler[] = $odeme;
                        $toplamPuantajTutar += floatval($odeme->tutar);
                    } else {
                        $tur = $odeme->tur;
                        if (strpos($tur, 'nobet') !== false) { $tur = 'nobet_grubu'; }
                        $hesaplananTutar = floatval($odeme->tutar);
                        $hesaplananAdet = $parsedAdet;
                        if (isset($detayData['ek_odemeler']) && is_array($detayData['ek_odemeler'])) {
                            foreach ($detayData['ek_odemeler'] as $jedo) {
                                if ($jedo['kod'] === $odeme->tur) {
                                    $hesaplananTutar = floatval($jedo['hesaplanan_tutar'] ?? $jedo['tutar']);
                                    $hesaplananAdet = intval($jedo['gun_sayisi'] ?? $parsedAdet);
                                    break; 
                                }
                            }
                        }
                        if (!isset($ekOdemelerNonPuantaj[$tur])) { $ekOdemelerNonPuantaj[$tur] = ['toplam' => 0, 'adet' => 0, 'kayit_sayisi' => 0, 'items' => []]; }
                        $ekOdemelerNonPuantaj[$tur]['toplam'] += $hesaplananTutar;
                        $ekOdemelerNonPuantaj[$tur]['adet'] += $hesaplananAdet;
                        $ekOdemelerNonPuantaj[$tur]['kayit_sayisi']++;
                        $odeme->tutar = $hesaplananTutar;
                        $ekOdemelerNonPuantaj[$tur]['items'][] = $odeme;
                    }
                }
                
                $modalBaseRowValue = $isPrimUsulu ? 0 : $asgariHakedisModal;
                $modalMaasFarkiGosterim = 0;
                
                if (!$isPrimUsulu) {
                    $totalDahilYardim = $displayMealDeduction + $spouseDeduction;
                    $sozlesmeHakedisTotal = round(($nominalMaas / 30) * $calismaGunu, 2);
                    $contractTarget = max($sozlesmeHakedisTotal, $asgariHakedisModal + $totalDahilYardim);
                    $rtcResmiPayModal = round(floatval($asgariUcretNet) / 30 * $rtcGunModal, 2);
                    $htcResmiPayModal = round(floatval($asgariUcretNet) / 30 * $htcGunModal, 2);
                    $modalMaasFarkiGosterim = max(0, round($contractTarget - $asgariHakedisModal - $totalDahilYardim - $rtcResmiPayModal - $htcResmiPayModal, 2));
                }

                $modalEkOdemeToplami = $toplamPuantajTutar + $toplamNobetTutar + $toplamKacakTutar;
                foreach ($ekOdemelerNonPuantaj as $edata) {
                    $modalEkOdemeToplami += floatval($edata['toplam'] ?? 0);
                }

                if ($isInclusive) {
                    $anaHakedisGosterim = $contractHakedisForRounding > 0
                        ? round($contractHakedisForRounding, 2)
                        : round($modalBaseRowValue + $modalMaasFarkiGosterim + $displayMealDeduction + $spouseDeduction, 2);
                    $bagimsizEkOdemeGosterim = $modalEkOdemeToplami;
                    if ($isPrimUsulu) {
                        $bagimsizEkOdemeGosterim = max(0, $bagimsizEkOdemeGosterim - $toplamPuantajTutar);
                    }
                    $htcGosterimEkOdeme = $htcGunModal > 0 ? round($nominalMaas / 30 * $htcGunModal, 2) : 0.0;
                    $gosterimYuvarlamaFarki = round($displayToplamAlacak - $anaHakedisGosterim - $bagimsizEkOdemeGosterim - $htcGosterimEkOdeme, 2);
                    if (abs($gosterimYuvarlamaFarki) >= 0.01) {
                        $toplamYuvarlamaFarki = $gosterimYuvarlamaFarki;
                    }
                }

                $displayToplamAlacak = round($toplamAlacak, 2);
                $kesintiTutarOzet = round($toplamYasalKesinti + $guncelKesintiGosterim, 2);
                // Net Maaş / Prim Usülü personelde RTÇ/HTÇ/nöbet gibi kalemlerin SGK/Gelir Vergisi/Damga
                // Vergisi kesintileri zaten brüte tamamlama (gross-up) ile kendi içinde absorbe edildiğinden
                // (Toplam Hakediş baştan net hedefi garanti eder), bu "yasal kesintiler" toplamdan BİR DAHA
                // düşülmemeli — aksi halde çifte kesinti olur. Sadece icra/avans gibi diğer kesintiler düşülür.
                $gorunenNetMaas = ($isNetMaas || $isPrimUsulu)
                    ? max(0, round($displayToplamAlacak - $guncelKesintiGosterim, 2))
                    : max(0, round($displayToplamAlacak - $kesintiTutarOzet, 2));

                $dagitimToplami = round($bankaOdemeModal + $eldenOdemeModal + $sodexoOdemeModal + $digerOdemeModal, 2);
                $dagitimFarki = round($gorunenNetMaas - $dagitimToplami, 2);
                if (abs($dagitimFarki) >= 0.01) {
                    if ($eldenOdemeModal > 0) {
                        $eldenOdemeModal = max(0, round($gorunenNetMaas - $bankaOdemeModal - $sodexoOdemeModal - $digerOdemeModal, 2));
                    } elseif (
                        abs($dagitimFarki) <= 100
                        && $bankaOdemeModal > 0
                        && $eldenOdemeModal <= 0
                        && $sodexoOdemeModal <= 0
                        && $digerOdemeModal <= 0
                    ) {
                        $bankaOdemeModal = round($bankaOdemeModal + $dagitimFarki, 2);
                    }
                }
                
                // Helper closure for grouping and parsing supplemental earnings (Quantity x Unit Price)
                $groupAndParse = function($odemeler, $prefixesToRemove) {
                    $gruplanmis = [];
                    foreach ($odemeler as $odeme) {
                        $aciklama = $odeme->aciklama ?? '';
                        foreach ($prefixesToRemove as $prefix) {
                            $aciklama = str_replace($prefix, '', $aciklama);
                        }
                        $anaMetin = trim($aciklama);
                        $detayMetin = '';
                        if (preg_match('/^(.*?)\s*\((.*?)\)$/', $aciklama, $matches)) {
                            $anaMetin = trim($matches[1]);
                            $detayMetin = trim($matches[2]);
                        }
                        
                        $adet = 0; $birimFiyat = '';
                        if (preg_match('/(\d+)\s*Adet\s*x\s*([0-9\.,]+)\s*₺?/iu', $detayMetin, $detayMatch)) {
                            $adet = intval($detayMatch[1]); 
                            $birimFiyat = trim($detayMatch[2]);
                        } elseif (preg_match('/(\d+)\s*Adet/iu', $aciklama, $adetMatch)) {
                            $adet = intval($adetMatch[1]);
                        }
                        
                        $groupKey = mb_strtolower($anaMetin, 'UTF-8');
                        if (!isset($gruplanmis[$groupKey])) {
                            $gruplanmis[$groupKey] = [
                                'ana' => $anaMetin,
                                'adet' => 0,
                                'tutar' => 0,
                                'fiyat_kirilim' => []
                            ];
                        }
                        
                        $gruplanmis[$groupKey]['adet'] += $adet;
                        $gruplanmis[$groupKey]['tutar'] += floatval($odeme->tutar);
                        
                        $fiyatKey = $birimFiyat !== '' ? $birimFiyat : '__unknown__';
                        if (!isset($gruplanmis[$groupKey]['fiyat_kirilim'][$fiyatKey])) {
                            $gruplanmis[$groupKey]['fiyat_kirilim'][$fiyatKey] = ['birim_fiyat' => $birimFiyat, 'adet' => 0, 'tutar' => 0];
                        }
                        $gruplanmis[$groupKey]['fiyat_kirilim'][$fiyatKey]['adet'] += $adet;
                        $gruplanmis[$groupKey]['fiyat_kirilim'][$fiyatKey]['tutar'] += floatval($odeme->tutar);
                    }
                    uasort($gruplanmis, function ($a, $b) { return $b['tutar'] <=> $a['tutar']; });
                    return $gruplanmis;
                };

                $puantajGruplu = $groupAndParse($puantajOdemeler, ['[Puantaj] ', '[Sayaç] ']);
                $nobetGruplu = $groupAndParse($nobetOdemeler, ['[Nöbet] ']);
                $kacakGruplu = $groupAndParse($kacakKontrolOdemeler, ['[Kaçak Kontrol] ']);
                $puantajToplamIslemSayisi = 0;
                foreach ($puantajGruplu as $grup) {
                    $puantajToplamIslemSayisi += intval($grup['adet'] ?? 0);
                }
                    // ============================================================
                // HTML GENERATION: REF-IMAGE BASED PAYROLL DETAIL VIEW
                // ============================================================
                $detayData = json_decode($bp->hesaplama_detay ?? '', true);
                $detayData = is_array($detayData) ? $detayData : [];
                $matrahlar = is_array($detayData['matrahlar'] ?? null) ? $detayData['matrahlar'] : [];
                $ozetDetay = is_array($detayData['ozet'] ?? null) ? $detayData['ozet'] : [];
                $indirimler = is_array($detayData['indirimler'] ?? null) ? $detayData['indirimler'] : [];

                // Fetch day counts from DB/puantaj using model helper methods to ensure accuracy
                $donemBaslangic = $donemBilgi->baslangic_tarihi;
                $donemBitis = $donemBilgi->bitis_tarihi;
                $personelId = $bp->personel_id;

                $ucretsizIzinGunu = $BordroPersonel->getUcretsizIzinGunuDirekt($personelId, $donemBaslangic, $donemBitis);
                $raporGun = $BordroPersonel->getGunSayisiByKisaKod($personelId, $donemBaslangic, $donemBitis, 'RP');
                $ucretliIzin = $BordroPersonel->getUcretliIzinGunu($personelId, $donemBaslangic, $donemBitis);
                $genelTatil = $BordroPersonel->getGunSayisiByKisaKod($personelId, $donemBaslangic, $donemBitis, 'GT');

                // Calculate Hafta Tatili (Sundays in employment period minus Sunday worked HTÇ days)
                $iseGirisTs = !empty($personel->ise_giris_tarihi) && $personel->ise_giris_tarihi !== '0000-00-00' ? strtotime($personel->ise_giris_tarihi) : strtotime($donemBaslangic);
                $istenCikisTs = !empty($personel->isten_cikis_tarihi) && $personel->isten_cikis_tarihi !== '0000-00-00' ? strtotime($personel->isten_cikis_tarihi) : strtotime($donemBitis);
                
                $aktifStart = max(strtotime($donemBaslangic), $iseGirisTs);
                $aktifEnd = min(strtotime($donemBitis), $istenCikisTs);
                
                $totalSundays = 0;
                if ($aktifStart <= $aktifEnd) {
                    $cur = $aktifStart;
                    while ($cur <= $aktifEnd) {
                        if (date('w', $cur) == 0) {
                            $totalSundays++;
                        }
                        $cur = strtotime('+1 day', $cur);
                    }
                }
                $haftaTatili = max(0, $totalSundays - $htcGunModal);

                $sskGun = intval($matrahlar['ssk_gunu'] ?? ($bp->calisan_gun ?? $hesap['calismaGunu'] ?? 30));
                $normalGun = max(0, $sskGun - $haftaTatili - $ucretliIzin - $genelTatil);
                $calisanBrutMaas = floatval($matrahlar['calisan_brut_maas'] ?? ($bp->brut_maas ?? 0));

                // Calculate overtime (RTC / HTÇ) parameters beforehand — BRÜT gösterim (gross-up sonrası)
                // Asgari ücretin günlük net hedefi SGK+İşsizlik+Gelir Vergisi+Damga Vergisi kesintilerinden
                // SONRA tam olarak bu hedefe ulaşacak şekilde brüte tamamlanır (bruteUpForNetTarget).
                $rtcHedefNetModal = $rtcGunModal > 0 ? round(floatval($asgariUcretNet) / 30 * $rtcGunModal, 2) : 0.0;
                $htcHedefNetModal = $htcGunModal > 0 ? round(floatval($asgariUcretNet) / 30 * $htcGunModal, 2) : 0.0;
                $htcEldenTutar = ($htcGunModal > 0) ? ($isInclusive ? 0.0 : round(($nominalMaas - floatval($asgariUcretNet)) / 30 * $htcGunModal, 2)) : 0.0;

                $rtcResmiTutar = 0.0;
                $htcResmiTutar = 0.0;
                if ($rtcHedefNetModal > 0 || $htcHedefNetModal > 0) {
                    $donemYilModal2 = (int) date('Y', strtotime($donemBaslangic));
                    $kumulatifMatrahModal2 = floatval($matrahlar['onceki_kumulatif'] ?? 0);
                    $sgkOraniModal2 = floatval($BordroParametre->getGenelAyar('sgk_isci_orani', $donemBaslangic) ?? 14) / 100;
                    $issizlikOraniModal2 = floatval($BordroParametre->getGenelAyar('issizlik_isci_orani', $donemBaslangic) ?? 1) / 100;
                    $damgaOraniModal2 = floatval($BordroParametre->getGenelAyar('damga_vergisi_orani', $donemBaslangic) ?? 0.759) / 100;

                    $rtcMatrahModal2 = 0.0;
                    if ($rtcHedefNetModal > 0) {
                        $rtcGrossModal = $BordroParametre->bruteUpForNetTarget($rtcHedefNetModal, $kumulatifMatrahModal2, $sgkOraniModal2, $issizlikOraniModal2, $damgaOraniModal2, $donemYilModal2);
                        $rtcResmiTutar = $rtcGrossModal['brut'];
                        $rtcMatrahModal2 = $rtcGrossModal['matrah'];
                    }
                    if ($htcHedefNetModal > 0) {
                        $htcGrossModal = $BordroParametre->bruteUpForNetTarget($htcHedefNetModal, $kumulatifMatrahModal2 + $rtcMatrahModal2, $sgkOraniModal2, $issizlikOraniModal2, $damgaOraniModal2, $donemYilModal2);
                        $htcResmiTutar = $htcGrossModal['brut'];
                    }
                }
                $htcToplamTutar = round($htcEldenTutar + $htcResmiTutar, 2);

                // Calculate SGK and Gelir Vergisi matrahs correctly including taxable overtime
                $sgkMatrah = floatval($matrahlar['sgk_matrahi'] ?? (floatval($bp->brut_maas ?? 0) + floatval($ozetDetay['sgk_matrah_ekleri'] ?? 0)));
                $damgaVergisiMatrah = floatval($matrahlar['damga_vergisi_matrahi'] ?? (floatval($bp->brut_maas ?? 0) + floatval($ozetDetay['damga_matrah_ekleri'] ?? $ozetDetay['sgk_matrah_ekleri'] ?? 0)));
                $gelirVergisiMatrah = floatval($matrahlar['gelir_vergisi_matrahi'] ?? 0);
                $oncekiAyMatrah = floatval($matrahlar['onceki_kumulatif'] ?? 0);
                $yilIciToplam = floatval($matrahlar['yeni_kumulatif'] ?? ($gelirVergisiMatrah + $oncekiAyMatrah));

                $sgkIsci = floatval($bp->sgk_isci ?? 0);
                $sgkIsveren = floatval($bp->sgk_isveren ?? 0);
                $issizlikIsci = floatval($bp->issizlik_isci ?? 0);
                $issizlikIsveren = floatval($bp->issizlik_isveren ?? 0);
                $gelirVergisi = floatval($bp->gelir_vergisi ?? 0);
                $damgaVergisi = floatval($bp->damga_vergisi ?? 0);

                $sgkIsverenOraniYazdir = floatval($BordroParametre->getGenelAyar('sgk_isveren_orani', $donemBaslangic) ?? 20.5);
                $issizlikIsverenOraniYazdir = floatval($BordroParametre->getGenelAyar('issizlik_isveren_orani', $donemBaslangic) ?? 2.0);

                $asgariMatrarhGoster = !empty($bp->yemek_yardimi_dahil)
                    || !empty($bp->es_yardimi_dahil)
                    || stripos($maasDurumuGosterim, 'Net') !== false;

                if ($asgariMatrarhGoster) {
                    $asgariHakedisYazdir = round(($asgariUcretNet / 30) * $sskGun, 2);
                    $calisanBrutMaas = $asgariHakedisYazdir;
                    $sgkMatrah = floatval($matrahlar['sgk_matrahi'] ?? 0);
                    $gelirVergisiMatrah = floatval($matrahlar['gelir_vergisi_matrahi'] ?? 0);
                    $yilIciToplam = floatval($matrahlar['yeni_kumulatif'] ?? 0);
                }

                $istisnaGV = floatval($indirimler['asgari_ucret_istisna_gv'] ?? 0);
                if ($istisnaGV <= 0) {
                    $asgariUcretGvMatrahi = round(($asgariUcretNet / 0.85) * 0.85 / 30 * $sskGun, 2);
                    $istisnaGV = $BordroParametre->hesaplaGelirVergisi($oncekiAyMatrah + $asgariUcretGvMatrahi, $asgariUcretGvMatrahi, date('Y', strtotime($donemBaslangic)));
                }
                $istisnaDV = floatval($indirimler['asgari_ucret_istisna_dv'] ?? 0);
                if ($istisnaDV <= 0) {
                    $asgariBrutYazdir = round((floatval($BordroParametre->getGenelAyar('asgari_ucret_brut', $donemBaslangic) ?? 33030.00) / 30) * $sskGun, 2);
                    $istisnaDV = round($asgariBrutYazdir * 0.00759, 2);
                }

                // Helper formatting function
                $fmt = function($val, $showMinus = false, $showPlus = false, $greenText = false, $redText = false) {
                    if ($val === null || $val === '') {
                        return '-';
                    }
                    $valFloat = floatval($val);
                    if ($valFloat == 0) {
                        return '-';
                    }
                    
                    $sign = '';
                    if ($valFloat < 0 || $showMinus) {
                        $sign = '-';
                    } elseif ($showPlus && $valFloat > 0) {
                        $sign = '+';
                    }
                    
                    $class = '';
                    if ($greenText) {
                        $class = ' class="green-text"';
                    } elseif ($redText) {
                        $class = ' class="red-text"';
                    }
                    
                    return '<span' . $class . '>' . $sign . '₺' . number_format(abs($valFloat), 2, ',', '.') . '</span>';
                };

                // Group Kesintiler
                $kesintiKayitlari = $BordroPersonel->getDonemKesintileriListe($bp->personel_id, $bp->donem_id);
                $sendikaTutar = 0.0;
                $icraTutar = 0.0;
                $avansTutar = 0.0;
                $digerKesintiTutar = 0.0;

                foreach ($kesintiKayitlari as $kk) {
                    $tur = $kk->tur;
                    if ($tur === 'icra') {
                        $icraTutar += floatval($kk->tutar);
                    } elseif ($tur === 'avans') {
                        $avansTutar += floatval($kk->tutar);
                    } elseif (strpos($tur, 'sendika') !== false || strpos(mb_strtolower($kk->aciklama ?? '', 'UTF-8'), 'sendika') !== false) {
                        $sendikaTutar += floatval($kk->tutar);
                    } else {
                        $digerKesintiTutar += floatval($kk->tutar);
                    }
                }

                // Group Ek Odemeler
                $ekOdemelerListe = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);
                $yolYardimi = 0.0;
                $yemekYardimi = $displayMealDeduction;
                $esYardimi = $spouseDeduction;
                $digerSosyalYardim = 0.0;

                $nobetResmiBrutToplam = 0.0;
                $nobetResmiSaatToplam = 0.0;
                if (!empty($ekOdemelerListe)) {
                    foreach ($ekOdemelerListe as $eo) {
                        if ($eo->tur === 'hafta_ici_nobet' || $eo->tur === 'hafta_sonu_nobet') {
                            $nobetResmiBrutToplam += floatval($eo->resmi_tutar ?? 0);
                        }
                        if ($eo->tur === 'hafta_ici_nobet') {
                            if (preg_match('/(\d+)\s*Gün\s*x\s*(\d+)\s*Saat/i', $eo->aciklama, $m)) {
                                $nobetResmiSaatToplam += intval($m[1]) * intval($m[2]);
                            }
                        }
                    }
                }
                $fazlaMesaiTutar = floatval($bp->fazla_mesai_tutar ?? 0) + $nobetResmiBrutToplam;
                $displayFazlaMesaiSaat = floatval($bp->fazla_mesai_saat ?? 0) + $nobetResmiSaatToplam;

                // Start primTutar from 0.0 to prevent double-counting of toplam_ek_odeme
                $primTutar = 0.0;
                $nobetTutar = $toplamNobetTutar;
                $kacakTutar = $toplamKacakTutar;
                $puantajTutar = $toplamPuantajTutar;
                $digerKazancTutar = 0.0;

                foreach ($ekOdemelerListe as $ek) {
                    $aciklama = (string)($ek->aciklama ?? '');
                    $eoTur = mb_strtolower((string)($ek->tur ?? ''), 'UTF-8');
                    $isYuvarlama = (($ek->tur ?? '') === 'yuvarlama_farki') || stripos($aciklama, 'Yuvarlama') !== false;
                    if ($isYuvarlama) continue;

                    if ($eoTur === 'yol' || strpos($eoTur, 'yol') !== false) {
                        $yolYardimi += floatval($ek->tutar);
                    } elseif ($eoTur === 'yemek' || strpos($eoTur, 'yemek') !== false || $eoTur === 'yemek_yardimi_tum') {
                        if (empty($bp->yemek_yardimi_dahil)) {
                            $yemekYardimi += floatval($ek->tutar);
                        }
                    } elseif ($eoTur === 'es_yardimi' || strpos($eoTur, 'es_yardimi') !== false || strpos($eoTur, 'aile') !== false) {
                        if (empty($bp->es_yardimi_dahil)) {
                            $esYardimi += floatval($ek->tutar);
                        }
                    } elseif (strpos($eoTur, 'sosyal') !== false || strpos($eoTur, 'yardim') !== false) {
                        $digerSosyalYardim += floatval($ek->tutar);
                    } elseif ($eoTur === 'prim' || $eoTur === 'ikramiye') {
                        // Exclude Puantaj, Sayaç, and Kaçak Kontrol payments from general Prim/İkramiye
                        if (strpos($aciklama, '[Puantaj]') !== 0 && strpos($aciklama, '[Sayaç]') !== 0 && strpos($aciklama, '[Kaçak Kontrol]') !== 0) {
                            $primTutar += floatval($ek->tutar);
                        }
                    } elseif (strpos($aciklama, '[Nöbet]') === 0 || strpos($eoTur, 'nobet') !== false) {
                        // Already handled by nobetTutar
                    } elseif (strpos($aciklama, '[Kaçak Kontrol]') === 0 || strpos($eoTur, 'kacak') !== false) {
                        // Already handled by kacakTutar
                    } elseif (strpos($aciklama, '[Puantaj]') === 0 || strpos($aciklama, '[Sayaç]') === 0) {
                        // Already handled by puantajTutar
                    } else {
                        $digerKazancTutar += floatval($ek->tutar);
                    }
                }

                $normalCalismaTutar = $normalGun * $gunlukUcret;
                $haftaTatiliTutar = $haftaTatili * $gunlukUcret;
                $genelTatilTutar = $genelTatil * $gunlukUcret;
                $ucretliIzinTutar = $ucretliIzin * $gunlukUcret;

                // Adjust to ensure mathematical correctness
                $calcNormalSum = $normalCalismaTutar + $haftaTatiliTutar + $genelTatilTutar + $ucretliIzinTutar;
                if ($calismaGunu > 0 && abs($calcNormalSum - $calisanBrutMaas) > 0.05) {
                    // Split the total prorated salary proportionally if there is any mismatch due to rounding
                    $totalGuns = $normalGun + $haftaTatili + $genelTatil + $ucretliIzin;
                    if ($totalGuns > 0) {
                        $normalCalismaTutar = round(($calisanBrutMaas / $totalGuns) * $normalGun, 2);
                        $haftaTatiliTutar = round(($calisanBrutMaas / $totalGuns) * $haftaTatili, 2);
                        $genelTatilTutar = round(($calisanBrutMaas / $totalGuns) * $genelTatil, 2);
                        $ucretliIzinTutar = round(($calisanBrutMaas / $totalGuns) * $ucretliIzin, 2);
                    }
                }

                $buildPopoverHtml = function($grupluArray) {
                    if (empty($grupluArray)) return '';
                    $popHtml = '<div class="ref-popover-content">';
                    foreach ($grupluArray as $grup) {
                        foreach ($grup['fiyat_kirilim'] as $kirilim) {
                            $detStr = $kirilim['adet'] > 0 ? $kirilim['adet'] . ' Adet' : '';
                            $birim = $kirilim['birim_fiyat'] !== '' ? ' x ' . $kirilim['birim_fiyat'] . ' ₺' : '';
                            $popHtml .= '<div style="margin-bottom:6px; display:flex; justify-content:space-between; gap:20px; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 4px;">';
                            $popHtml .= '<span style="color:#cbd5e1;">' . htmlspecialchars($grup['ana']) . ' <small style="color:#94a3b8;">(' . $detStr . $birim . ')</small></span>';
                            $popHtml .= '<span style="color:#10b981; font-weight:bold;">+' . number_format($kirilim['tutar'], 2, ',', '.') . ' ₺</span>';
                            $popHtml .= '</div>';
                        }
                    }
                    $popHtml .= '</div>';
                    return $popHtml;
                };

                // Hesaplama detayını (hedef net, SGK, İşsizlik, GV, Damga, Brüt) elle uğraşmadan
                // popover'da gösteren yardımcı — manuel hesaplama yapmak isteyen kullanıcı da tüm
                // ara verileri burada hazır bulur.
                $buildHesapDetayPopover = function (array $satirlar, string $ustBilgi = '') {
                    $h = '<div class="ref-popover-content" style="min-width:230px;">';
                    if ($ustBilgi !== '') {
                        $h .= '<div style="color:#94a3b8; font-size:0.72rem; margin-bottom:6px; border-bottom:1px dashed rgba(255,255,255,0.15); padding-bottom:5px;">' . $ustBilgi . '</div>';
                    }
                    foreach ($satirlar as $i => $s) {
                        $son = ($i === array_key_last($satirlar));
                        $stil = $son ? 'margin-top:5px; padding-top:5px; border-top:1px solid rgba(255,255,255,0.15); font-weight:bold;' : 'margin-bottom:4px;';
                        $renk = $s['renk'] ?? '#e2e8f0';
                        $h .= '<div style="display:flex; justify-content:space-between; gap:20px; ' . $stil . '"><span style="color:#cbd5e1;">' . $s['label'] . '</span><span style="color:' . $renk . ';">' . $s['value'] . '</span></div>';
                    }
                    $h .= '</div>';
                    return $h;
                };

                $htcPopoverSatirlar = [];
                if ($htcEldenTutar > 0) {
                    $htcPopoverSatirlar[] = ['label' => 'Maaş Farkı <small style="color:#94a3b8;">(Banka)</small>', 'value' => '+' . number_format($htcEldenTutar, 2, ',', '.') . ' ₺', 'renk' => '#10b981'];
                }
                if (isset($htcGrossModal)) {
                    $htcPopoverSatirlar[] = ['label' => 'Hedef Net <small style="color:#94a3b8;">(asgari ücret/30 × gün)</small>', 'value' => number_format($htcHedefNetModal, 2, ',', '.') . ' ₺'];
                    $htcPopoverSatirlar[] = ['label' => 'SGK İşçi Payı (%14)', 'value' => '-' . number_format($htcGrossModal['sgk'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $htcPopoverSatirlar[] = ['label' => 'İşsizlik Primi (%1)', 'value' => '-' . number_format($htcGrossModal['issizlik'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $htcPopoverSatirlar[] = ['label' => 'Gelir Vergisi', 'value' => '-' . number_format($htcGrossModal['gelir_vergisi'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $htcPopoverSatirlar[] = ['label' => 'Damga Vergisi', 'value' => '-' . number_format($htcGrossModal['damga'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $htcPopoverSatirlar[] = ['label' => 'Brüt (Resmi alacağa dahil)', 'value' => '+' . number_format($htcResmiTutar, 2, ',', '.') . ' ₺', 'renk' => '#10b981'];
                } else {
                    $htcPopoverSatirlar[] = ['label' => 'Resmi alacağa dahil <small style="color:#94a3b8;">(Asgari Ücret)</small>', 'value' => '+' . number_format($htcResmiTutar, 2, ',', '.') . ' ₺', 'renk' => '#10b981'];
                }
                $htcPopoverHtml = $buildHesapDetayPopover($htcPopoverSatirlar, 'Net hedef, SGK/GV/Damga eklenerek brüte tamamlanır');

                $rtcPopoverSatirlar = [];
                if (isset($rtcGrossModal)) {
                    $rtcPopoverSatirlar[] = ['label' => 'Hedef Net <small style="color:#94a3b8;">(asgari ücret/30 × gün)</small>', 'value' => number_format($rtcHedefNetModal, 2, ',', '.') . ' ₺'];
                    $rtcPopoverSatirlar[] = ['label' => 'SGK İşçi Payı (%14)', 'value' => '-' . number_format($rtcGrossModal['sgk'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $rtcPopoverSatirlar[] = ['label' => 'İşsizlik Primi (%1)', 'value' => '-' . number_format($rtcGrossModal['issizlik'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $rtcPopoverSatirlar[] = ['label' => 'Gelir Vergisi', 'value' => '-' . number_format($rtcGrossModal['gelir_vergisi'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $rtcPopoverSatirlar[] = ['label' => 'Damga Vergisi', 'value' => '-' . number_format($rtcGrossModal['damga'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'];
                    $rtcPopoverSatirlar[] = ['label' => 'Brüt (Resmi alacağa dahil)', 'value' => '+' . number_format($rtcResmiTutar, 2, ',', '.') . ' ₺', 'renk' => '#10b981'];
                } else {
                    $rtcPopoverSatirlar[] = ['label' => 'Resmi alacağa dahil <small style="color:#94a3b8;">(Asgari Ücret)</small>', 'value' => '+' . number_format($rtcResmiTutar, 2, ',', '.') . ' ₺', 'renk' => '#10b981'];
                }
                $rtcPopoverHtml = $buildHesapDetayPopover($rtcPopoverSatirlar, 'Net hedef, SGK/GV/Damga eklenerek brüte tamamlanır');

                // Fazla Mesai Ücreti (Hafta İçi Nöbet resmi tutarı) hesaplama detayı
                $nobetGrossModal = null;
                if ($nobetResmiBrutToplam > 0 && $nobetResmiSaatToplam > 0) {
                    $nobetParam = $BordroParametre->getByKod('hafta_ici_nobet');
                    $nobetSgkDahilModal = $nobetParam ? !empty($nobetParam->sgk_matrahi_dahil) : true;
                    $nobetGvDahilModal = $nobetParam ? !empty($nobetParam->gelir_vergisi_dahil) : true;
                    $nobetDamgaDahilModal = $nobetParam ? !empty($nobetParam->damga_vergisi_dahil) : true;
                    $nobetSaatlikNetHedef = (floatval($asgariUcretNet) / 225) * 1.5;
                    $nobetHedefNetModal = round($nobetSaatlikNetHedef * $nobetResmiSaatToplam, 2);
                    $kumulatifMatrahNobetModal = floatval($matrahlar['onceki_kumulatif'] ?? 0);
                    $sgkOraniNobetModal = floatval($BordroParametre->getGenelAyar('sgk_isci_orani', $donemBaslangic) ?? 14) / 100;
                    $issizlikOraniNobetModal = floatval($BordroParametre->getGenelAyar('issizlik_isci_orani', $donemBaslangic) ?? 1) / 100;
                    $damgaOraniNobetModal = floatval($BordroParametre->getGenelAyar('damga_vergisi_orani', $donemBaslangic) ?? 0.759) / 100;
                    $nobetGrossModal = $BordroParametre->bruteUpForNetTarget(
                        $nobetHedefNetModal,
                        $kumulatifMatrahNobetModal,
                        $nobetSgkDahilModal ? $sgkOraniNobetModal : 0.0,
                        $nobetSgkDahilModal ? $issizlikOraniNobetModal : 0.0,
                        $nobetDamgaDahilModal ? $damgaOraniNobetModal : 0.0,
                        (int) date('Y', strtotime($donemBaslangic)),
                        $nobetGvDahilModal
                    );
                    $nobetPopoverSatirlar = [
                        ['label' => 'Hedef Net <small style="color:#94a3b8;">(asgari saatlik net × 1,5 × saat)</small>', 'value' => number_format($nobetHedefNetModal, 2, ',', '.') . ' ₺'],
                        ['label' => 'SGK İşçi Payı (%14)', 'value' => '-' . number_format($nobetGrossModal['sgk'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'],
                        ['label' => 'İşsizlik Primi (%1)', 'value' => '-' . number_format($nobetGrossModal['issizlik'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'],
                        ['label' => 'Gelir Vergisi', 'value' => '-' . number_format($nobetGrossModal['gelir_vergisi'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'],
                        ['label' => 'Damga Vergisi', 'value' => '-' . number_format($nobetGrossModal['damga'], 2, ',', '.') . ' ₺', 'renk' => '#f87171'],
                        ['label' => 'Brüt (Fazla Mesai Ücreti)', 'value' => '+' . number_format($nobetResmiBrutToplam, 2, ',', '.') . ' ₺', 'renk' => '#10b981'],
                        ['label' => 'Kişinin kendi alacağı <small style="color:#94a3b8;">(net, değişmez)</small>', 'value' => number_format($toplamNobetTutar ?? 0, 2, ',', '.') . ' ₺'],
                    ];
                    $nobetPopoverHtml = $buildHesapDetayPopover($nobetPopoverSatirlar, 'Sadece resmi/banka alacağı payı — asıl net ödemeyi artırmaz');
                }

                $html = '<style>
                    .bordro-ref-view { font-family: inherit; color: #1e293b; background-color: #f8fafc; padding: 20px; border-radius: 12px; }
                    .bordro-ref-view .section-title { font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 15px; margin-top: 25px; text-transform: uppercase; letter-spacing: 0.5px; }
                    .bordro-ref-view .section-title.gains { color: #10b981; border-left: 4px solid #10b981; padding-left: 8px; }
                    .bordro-ref-view .section-title.deductions { color: #3b82f6; border-left: 4px solid #3b82f6; padding-left: 8px; }
                    .bordro-ref-view .ref-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; height: 100%; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
                    .bordro-ref-view .ref-card-title { font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; }
                    .bordro-ref-view .ref-card-list { display: flex; flex-direction: column; gap: 8px; flex-grow: 1; margin-bottom: 12px; }
                    .bordro-ref-view .ref-card-item { display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; line-height: 1.4; position: relative; }
                    .bordro-ref-view .ref-card-item .label { color: #475569; }
                    .bordro-ref-view .ref-card-item .value { font-weight: 600; color: #0f172a; text-align: right; }
                    .bordro-ref-view .ref-card-item .value .subval { font-size: 0.75rem; color: #64748b; font-weight: 400; margin-right: 6px; }
                    .bordro-ref-view .ref-card-item.total-row { border-top: 1px solid #e2e8f0; padding-top: 8px; margin-top: auto; font-weight: 700; font-size: 0.9rem; }
                    .bordro-ref-view .ref-card-item.total-row .label { font-weight: 700; color: #0f172a; }
                    .bordro-ref-view .ref-card-item.total-row .value { font-weight: 800; color: #0f172a; }
                    .bordro-ref-view .green-text { color: #10b981 !important; font-weight: 600; }
                    .bordro-ref-view .red-text { color: #ef4444 !important; font-weight: 600; }
                    
                    /* Hover Popover Styles */
                    .bordro-ref-view .hover-popover-trigger { cursor: help; position: relative; }
                    .bordro-ref-view .ref-popover-content {
                        display: none;
                        position: absolute;
                        bottom: 125%;
                        right: 0;
                        background: #1e293b;
                        color: #ffffff;
                        padding: 12px 16px;
                        border-radius: 8px;
                        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2), 0 4px 6px -2px rgba(0,0,0,0.1);
                        min-width: 280px;
                        max-width: 350px;
                        z-index: 1000;
                        font-size: 0.75rem;
                        text-align: left;
                        border: 1px solid #334155;
                    }
                    .bordro-ref-view .ref-popover-content::after {
                        content: "";
                        position: absolute;
                        top: 100%;
                        right: 15px;
                        border-width: 6px;
                        border-style: solid;
                        border-color: #1e293b transparent transparent transparent;
                    }
                    .bordro-ref-view .hover-popover-trigger:hover .ref-popover-content {
                        display: block;
                    }
                    
                    /* Lower Section Panels */
                    .bordro-ref-view .bottom-panels { margin-top: 25px; display: grid; grid-template-columns: 1fr 1.5fr 1fr; gap: 15px; }
                    @media (max-width: 991px) {
                        .bordro-ref-view .bottom-panels { grid-template-columns: 1fr; }
                    }
                    .bordro-ref-view .panel-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
                    .bordro-ref-view .panel-card-title { font-size: 0.85rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; }
                    .bordro-ref-view .summary-badge { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #ffffff; border-radius: 8px; padding: 16px; margin-top: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
                    .bordro-ref-view .summary-val { font-size: 1.8rem; font-weight: 800; color: #10b981; }
                </style>';

                $html .= '<div class="bordro-ref-view container-fluid px-0">';

                // Header Information
                $html .= '<div class="bg-white border rounded-3 p-3 mb-4 d-flex flex-wrap justify-content-between align-items-center">';
                $html .= '<div>
                            <h5 class="mb-1 fw-bold text-dark"><i class="bx bxs-user-circle me-2 text-muted"></i>' . htmlspecialchars($personel->adi_soyadi ?? 'Bilinmeyen') . '</h5>
                            <div class="d-flex gap-3 text-muted small">
                                <span><i class="bx bx-id-card me-1"></i>TC: ' . htmlspecialchars($personel->tc_kimlik_no ?? '-') . '</span>
                                <span><i class="bx bx-briefcase me-1"></i>' . htmlspecialchars($personel->gorev ?? '-') . '</span>
                                <span class="text-primary fw-bold"><i class="bx bx-calendar me-1"></i>' . ($donemBilgi->donem_adi ?? 'Dönem Bilgisi Yok') . '</span>
                            </div>
                          </div>';
                $html .= '<div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                            <div class="badge bg-light text-dark border py-2 px-3 d-flex flex-column align-items-end">
                                <small class="text-muted opacity-75" style="font-size: 10px;">MAAŞ TİPİ</small>
                                <span class="fw-bold text-uppercase">' . htmlspecialchars($maasDurumuGosterim) . '</span>
                            </div>
                            <div class="badge bg-light text-dark border py-2 px-3 d-flex flex-column align-items-end">
                                <small class="text-muted opacity-75" style="font-size: 10px;">SÖZLEŞME MAAŞI</small>
                                <span class="fw-bold">' . ($nominalMaas ? number_format($nominalMaas, 2, ',', '.') . ' ₺' : '-') . '</span>
                            </div>
                          </div>';
                $html .= '</div>';

                // --- SECTION 1: BRÜT KAZANÇLAR VE GELİRLER (ÜST SIRA) ---
                $html .= '<div class="section-title gains"><i class="bx bx-plus-circle me-1"></i>BRÜT KAZANÇLAR VE GELİRLER (ÜST SIRA)</div>';
                $html .= '<div class="row row-cols-1 row-cols-md-4 g-3">';

                // 1. Normal Kazançlar Card
                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">1. NORMAL KAZANÇLAR</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item"><span class="label">Normal Çalışma</span><span class="value"><span class="subval">' . number_format($normalGun, 2, ',', '.') . ' Gün</span>' . $fmt($normalCalismaTutar) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Hafta Tatili</span><span class="value"><span class="subval">' . number_format($haftaTatili, 2, ',', '.') . ' Gün</span>' . $fmt($haftaTatiliTutar) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Genel Tatil</span><span class="value"><span class="subval">' . number_format($genelTatil, 2, ',', '.') . ' Gün</span>' . $fmt($genelTatilTutar) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Ücretli İzin</span><span class="value"><span class="subval">' . number_format($ucretliIzin, 2, ',', '.') . ' Gün</span>' . $fmt($ucretliIzinTutar) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Ücretsiz İzin</span><span class="value"><span class="subval">' . number_format($ucretsizIzinGunu, 2, ',', '.') . ' Gün</span>-</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Rapor</span><span class="value"><span class="subval">' . number_format($raporGun, 2, ',', '.') . ' Gün</span>-</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Toplam</span><span class="value"><span class="subval">' . $sskGun . ' Gün</span>' . $fmt($calisanBrutMaas) . '</span></div>';
                $html .= '</div></div>';

                // 2. Fazla Çalışmalar Card
                $fazlaCalismaToplam = $rtcResmiTutar + $htcToplamTutar + $fazlaMesaiTutar;
                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">2. FAZLA ÇALIŞMALAR</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item' . ($rtcGunModal > 0 ? ' hover-popover-trigger' : '') . '"><span class="label">Resmi Tatil Çalışma' . ($rtcGunModal > 0 ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . ($rtcGunModal > 0 ? '<span class="subval">' . $rtcGunModal . ' Gün</span>' : '') . $fmt($rtcResmiTutar) . '</span>' . ($rtcGunModal > 0 ? $rtcPopoverHtml : '') . '</div>';
                $html .= '<div class="ref-card-item' . ($htcGunModal > 0 ? ' hover-popover-trigger' : '') . '"><span class="label">Hafta Tatili Çalışma' . ($htcGunModal > 0 ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . ($htcGunModal > 0 ? '<span class="subval">' . $htcGunModal . ' Gün</span>' : '') . $fmt($htcToplamTutar) . '</span>' . ($htcGunModal > 0 ? $htcPopoverHtml : '') . '</div>';
                $html .= '<div class="ref-card-item' . ($nobetGrossModal ? ' hover-popover-trigger' : '') . '"><span class="label">Fazla Mesai Ücreti' . ($nobetGrossModal ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . ($displayFazlaMesaiSaat > 0 ? '<span class="subval">' . number_format($displayFazlaMesaiSaat, 2, ',', '.') . ' Saat</span>' : '') . $fmt($fazlaMesaiTutar) . '</span>' . ($nobetGrossModal ? $nobetPopoverHtml : '') . '</div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Toplam</span><span class="value">' . $fmt($fazlaCalismaToplam) . '</span></div>';
                $html .= '</div></div>';

                // 3. Sosyal Yardımlar Card
                $sosyalYardimToplam = $yolYardimi + $yemekYardimi + $esYardimi + $digerSosyalYardim;
                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">3. SOSYAL YARDIMLAR</div>';
                $html .= '<div class="ref-card-list">';
                $yuvarlamaFarki = floatval($ozetDetay['yuvarlama_farki'] ?? 0);
                $yemekPop = '';
                if ($yuvarlamaFarki != 0) {
                    $yemekHesaplanan = $yemekYardimi - $yuvarlamaFarki;
                    $yemekPop = '<div class="ref-popover-content">';
                    $yemekPop .= '<div style="display:flex; justify-content:space-between; gap:20px; margin-bottom:6px; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 4px;"><span style="color:#cbd5e1;">Hesaplanan</span><span style="color:#ffffff; font-weight:bold;">' . number_format($yemekHesaplanan, 2, ',', '.') . ' ₺</span></div>';
                    $yemekPop .= '<div style="display:flex; justify-content:space-between; gap:20px;"><span style="color:#cbd5e1;">Yuvarlama Farkı</span><span style="color:#ffc107; font-weight:bold;">+' . number_format($yuvarlamaFarki, 2, ',', '.') . ' ₺</span></div>';
                    $yemekPop .= '</div>';
                }

                $yemekLabelText = 'Yemek Yardımı';
                $dahilYemekGun = intval($ozetDetay['dahil_yemek_gun'] ?? 0);
                $dahilYemekGunluk = floatval($ozetDetay['dahil_yemek_gunluk'] ?? 0);
                if ($dahilYemekGun > 0 && $dahilYemekGunluk > 0) {
                    $yemekLabelText .= ' <small class="text-muted" style="font-size:0.75rem; font-weight:normal; letter-spacing:0px;">( ' . $dahilYemekGun . ' x ' . number_format($dahilYemekGunluk, 2, ',', '.') . ' TL )</small>';
                }

                $html .= '<div class="ref-card-item"><span class="label">Yol Yardımı</span><span class="value">' . $fmt($yolYardimi) . '</span></div>';
                $html .= '<div class="ref-card-item' . (!empty($yemekPop) ? ' hover-popover-trigger' : '') . '"><span class="label">' . $yemekLabelText . (!empty($yemekPop) ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . $fmt($yemekYardimi) . '</span>' . $yemekPop . '</div>';
                $html .= '<div class="ref-card-item"><span class="label">Eş Yardımı</span><span class="value">' . $fmt($esYardimi) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Diger Sosyal Yardım</span><span class="value">' . $fmt($digerSosyalYardim) . '</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Toplam</span><span class="value">' . $fmt($sosyalYardimToplam) . '</span></div>';
                $html .= '</div></div>';

                // 4. Diğer Kazançlar / İkramiyeler Card
                $digerKazancToplam = $primTutar + $nobetTutar + $kacakTutar + $puantajTutar + $digerKazancTutar;
                $nobetPop = $buildPopoverHtml($nobetGruplu);
                $kacakPop = $buildPopoverHtml($kacakGruplu);
                $puantajPop = $buildPopoverHtml($puantajGruplu);

                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">4. DİĞER KAZANÇLAR / İKRAMİYELER</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item' . (!empty($nobetPop) ? ' hover-popover-trigger' : '') . '"><span class="label">Nöbet Ödemesi' . (!empty($nobetPop) ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . $fmt($nobetTutar) . '</span>' . $nobetPop . '</div>';
                $html .= '<div class="ref-card-item' . (!empty($kacakPop) ? ' hover-popover-trigger' : '') . '"><span class="label">Kaçak Kontrol Primi' . (!empty($kacakPop) ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . $fmt($kacakTutar) . '</span>' . $kacakPop . '</div>';
                $puantajBaslikDetay = $puantajToplamIslemSayisi > 0 ? ' <small class="text-muted fw-normal">( <strong>' . $puantajToplamIslemSayisi . ' Adet</strong> )</small>' : '';
                $html .= '<div class="ref-card-item' . (!empty($puantajPop) ? ' hover-popover-trigger' : '') . '"><span class="label">Puantaj Hakedişi' . $puantajBaslikDetay . (!empty($puantajPop) ? ' <i class="bx bx-info-circle text-muted" style="font-size:0.75rem;"></i>' : '') . '</span><span class="value">' . $fmt($puantajTutar) . '</span>' . $puantajPop . '</div>';
                $html .= '<div class="ref-card-item"><span class="label">Prim / İkramiye</span><span class="value">' . $fmt($primTutar) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Diğer Ek Ödemeler</span><span class="value">' . $fmt($digerKazancTutar) . '</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Toplam</span><span class="value">' . $fmt($digerKazancToplam) . '</span></div>';
                $html .= '</div></div>';

                $html .= '</div>'; // End Row 1

                // --- SECTION 2: YASAL KESİNTİLER VE İŞVEREN MALİYETİ (ALT SIRA) ---
                $html .= '<div class="section-title deductions"><i class="bx bx-minus-circle me-1"></i>YASAL KESİNTİLER VE İŞVEREN MALİYETİ (ALT SIRA)</div>';
                $html .= '<div class="row row-cols-1 row-cols-md-4 g-3">';

                // 1. SGK İşçi Primi Card
                $sgkIsciKesintiToplam = $sgkIsci + $issizlikIsci;
                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">1. SGK İŞÇİ PRİMİ</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item"><span class="label">SGK Prim Gün Sayısı</span><span class="value">' . $sskGun . ' Gün</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">SGK Matrahı</span><span class="value">' . $fmt($sgkMatrah) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Aynı Dönem İçi SGK Matrahı</span><span class="value">₺0,00</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Yemek Yrd. Prim İstisnası</span><span class="value">₺0,00</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Yol/Ulaşım Prim İstisnası</span><span class="value">₺0,00</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Çocuk Yrd. Prim İstisnası</span><span class="value">₺0,00</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Aile/Yemek Sair İstisna</span><span class="value">₺0,00</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">İşçi SGK Payı (%14,0)</span><span class="value">' . $fmt($sgkIsci) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">İşçi İşsizlik Primi (%1,0)</span><span class="value">' . $fmt($issizlikIsci) . '</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Toplam SGK Kesintisi</span><span class="value">' . $fmt($sgkIsciKesintiToplam, true, false, false, true) . '</span></div>';
                $html .= '</div></div>';

                // 2. Gelir Vergisi Card
                $asgariUcretGvMatrahi = round(($asgariUcretNet / 0.85) * 0.85 / 30 * $sskGun, 2);
                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">2. GELİR VERGİSİ</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item"><span class="label">Gelir Vergisi Matrahı</span><span class="value">' . $fmt($gelirVergisiMatrah) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Önceki Dönem Küm. GV</span><span class="value">' . $fmt($oncekiAyMatrah) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Yeni Dönem Küm. GV</span><span class="value">' . $fmt($yilIciToplam) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Asgari Ücret GV Matrahı</span><span class="value">' . $fmt($asgariUcretGvMatrahi) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">G.V. Asgari Ücret İstisnası</span><span class="value">' . $fmt($istisnaGV, true, false, true) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Sendika Matrah İndirimi</span><span class="value">' . $fmt(0) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Gelir V. Yemek/Yol İst.</span><span class="value">₺0,00</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Hesaplanan Gelir Vergisi</span><span class="value">' . $fmt($gelirVergisi + $istisnaGV) . '</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Ödenecek Net Gelir V.</span><span class="value">' . $fmt($gelirVergisi, true, false, false, true) . '</span></div>';
                $html .= '</div></div>';

                // 3. Damga Vergisi Card
                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">3. DAMGA VERGİSİ</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item"><span class="label">Damga Vergisi Matrahı</span><span class="value">' . $fmt($damgaVergisiMatrah) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Damga V. Asgari İstisnası</span><span class="value">' . $fmt($istisnaDV, true, false, true) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Hesaplanan Damga Vergisi</span><span class="value">' . $fmt($damgaVergisi + $istisnaDV) . '</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Ödenecek Net Damga V.</span><span class="value">' . $fmt($damgaVergisi, true, false, false, true) . '</span></div>';
                $html .= '</div></div>';

                // 4. Diğer Kesintiler Card
                $digerKesintiToplam = $icraTutar + $avansTutar + $sendikaTutar + $digerKesintiTutar;
                $html .= '<div class="col"><div class="ref-card">';
                $html .= '<div class="ref-card-title">4. DİĞER KESİNTİLER</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item"><span class="label">İcra Kesintisi</span><span class="value">' . $fmt($icraTutar, true) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Avans Mahsubu</span><span class="value">' . $fmt($avansTutar, true) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Sendika Aidatı</span><span class="value">' . $fmt($sendikaTutar, true) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Diğer Özel Kesintiler</span><span class="value">' . $fmt($digerKesintiTutar, true) . '</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row"><span class="label">Toplam Kesinti</span><span class="value">' . $fmt($digerKesintiToplam, true, false, false, true) . '</span></div>';
                $html .= '</div></div>';

                $html .= '</div>'; // End Row 2

                // --- SECTION 3: bottom panels (SGK İşveren, Maliyet Analizi, Ücret Özet) ---
                $html .= '<div class="bottom-panels">';

                // 5. SGK İşveren Payı Panel
                $html .= '<div class="panel-card">';
                $html .= '<div class="panel-card-title">5. SGK İŞVEREN PAYI</div>';
                $html .= '<div class="ref-card-list">';
                $html .= '<div class="ref-card-item"><span class="label">SGK İşveren Payı (%' . number_format($sgkIsverenOraniYazdir, 2, ',', '.') . ')</span><span class="value">' . $fmt($sgkIsveren) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">İşsizlik İşveren Payı (%' . number_format($issizlikIsverenOraniYazdir, 2, ',', '.') . ')</span><span class="value">' . $fmt($issizlikIsveren) . '</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row" style="border-top: 1px solid #e2e8f0; padding-top: 8px;"><span class="label">Toplam İşveren SGK</span><span class="value">' . $fmt($sgkIsveren + $issizlikIsveren) . '</span></div>';
                $html .= '</div>';

                // İşveren Toplam Maliyet Analizi Panel
                $html .= '<div class="panel-card">';
                $html .= '<div class="panel-card-title">İŞVEREN TOPLAM MALİYET ANALİZİ</div>';
                $html .= '<div class="ref-card-list">';
                $maliyetBrutToplam = (stripos($maasDurumuGosterim, 'Net') !== false || stripos($maasDurumuGosterim, 'Prim') !== false) ? $sgkMatrah : $displayToplamAlacak;
                $html .= '<div class="ref-card-item"><span class="label">Brüt Toplam Kazançlar</span><span class="value">' . $fmt($maliyetBrutToplam) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">SGK İşveren Katkısı</span><span class="value">' . $fmt($sgkIsveren) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">İşsizlik İşveren Katkısı</span><span class="value">' . $fmt($issizlikIsveren) . '</span></div>';
                $html .= '</div>';
                $html .= '<div class="ref-card-item total-row" style="border-top: 1px solid #e2e8f0; padding-top: 8px;"><span class="label">Toplam Maliyet</span><span class="value text-primary" style="font-size: 1.05rem;">' . ($bp->toplam_maliyet ? number_format($bp->toplam_maliyet, 2, ',', '.') . ' ₺' : '-') . '</span></div>';
                $html .= '</div>';

                // Ücret Toplamları (Özet) Panel
                $html .= '<div class="panel-card">';
                $html .= '<div class="panel-card-title">ÜCRET TOPLAMLARI (Özet)</div>';
                $html .= '<div class="ref-card-list" style="gap:4px;">';
                $html .= '<div class="ref-card-item"><span class="label">Brüt Toplamı</span><span class="value">' . $fmt($displayToplamAlacak) . '</span></div>';
                $html .= '<div class="ref-card-item"><span class="label">Kesintiler Toplamı</span><span class="value text-danger">' . $fmt($kesintiTutarOzet, true) . '</span></div>';
                $html .= '<div class="ref-card-item" style="border-bottom: 1px dashed #e2e8f0; padding-bottom:4px; margin-bottom:4px;"><span class="label fw-bold">Ödenecek Net Maaş</span><span class="value text-success fw-bold">' . $fmt($gorunenNetMaas) . '</span></div>';
                if ($bankaOdemeModal > 0) $html .= '<div class="ref-card-item"><span class="label">Banka Ödemesi</span><span class="value">' . $fmt($bankaOdemeModal) . '</span></div>';
                if ($eldenOdemeModal > 0) $html .= '<div class="ref-card-item"><span class="label">Elden Ödeme</span><span class="value">' . $fmt($eldenOdemeModal) . '</span></div>';
                if ($sodexoOdemeModal > 0) $html .= '<div class="ref-card-item"><span class="label">Sodexo Ödemesi</span><span class="value">' . $fmt($sodexoOdemeModal) . '</span></div>';
                if ($digerOdemeModal > 0) $html .= '<div class="ref-card-item"><span class="label">Diğer Ödemeler</span><span class="value">' . $fmt($digerOdemeModal) . '</span></div>';
                $html .= '</div>';
                $html .= '</div>';

                $html .= '</div>'; // End bottom panels

                if ($toplamYuvarlamaFarki != 0) {
                    $html .= '<div class="mt-3 p-2 bg-white border rounded text-end text-muted small">
                                <i class="bx bx-info-circle me-1"></i>Küsürat düzeltmesi için ' . $fmt($toplamYuvarlamaFarki, ($toplamYuvarlamaFarki < 0), ($toplamYuvarlamaFarki > 0)) . ' yuvarlama farkı uygulanmıştır.
                              </div>';
                }

                $html .= '</div>'; // End Wrapper

                if (floatval($bp->kumulatif_matrah ?? 0) > 0 || $bp->hesaplama_tarihi) {
                    $html .= '<div class="text-muted small mt-3 text-end">';
                    if (floatval($bp->kumulatif_matrah ?? 0) > 0) {
                        $html .= '<span class="me-3"><i class="bx bx-trending-up me-1"></i>Kümülatif Vergi Matrahı (Yıl Başından İtibaren): ' . number_format($bp->kumulatif_matrah, 2, ',', '.') . ' ₺</span>';
                    }
                    if ($bp->hesaplama_tarihi) {
                        $html .= '<i class="bx bx-time me-1"></i>Son Hesaplama: ' . date('d.m.Y H:i', strtotime($bp->hesaplama_tarihi));
                    }
                    $html .= '</div>';
                }

                $bankaDetayHtml = '';
                if ($hesap['manualDagitimVar']) {
                    $bankaDetayHtml = '<div class="small p-1"><strong>Manuel Dağıtım</strong><br>Bu tutar kullanıcı tarafından elle düzenlenmiştir.</div>';
                } elseif ($hesap['nonKurRatio'] <= 0) {
                    $bankaDetayHtml = '<div class="small p-1 text-danger"><i class="bx bx-error-circle"></i> Personel banka dışı / KUR kapsamında olduğu için banka ödemesi yapılmaz.</div>';
                } else {
                    $bankaDetayHtml = '<div class="p-2" style="min-width: 250px; font-size: 0.8rem;">';
                    $bankaDetayHtml .= '<h6 class="fw-bold mb-2 pb-1 border-bottom" style="font-size: 0.85rem;"><i class="bx bxs-bank text-primary me-1"></i> Banka Ödemesi Detayı</h6>';
                    $bankaDetayHtml .= '<div class="d-flex flex-column gap-1.5">';
                    
                    if ($hesap['isInclusive']) {
                        $bankaDetayHtml .= '<div class="d-flex justify-content-between"><span>Asgari Ücret (Net):</span><span class="fw-bold">' . number_format($hesap['asgariYatacak'], 2, ',', '.') . ' ₺</span></div>';
                        if ($hesap['mealAllowanceDeduction'] > 0) {
                            $bankaDetayHtml .= '<div class="d-flex justify-content-between"><span>Yemek Yardımı:</span><span class="fw-bold text-success">+' . number_format($hesap['mealAllowanceDeduction'], 2, ',', '.') . ' ₺</span></div>';
                        }
                        if ($hesap['spouseAllowanceDeduction'] > 0) {
                            $bankaDetayHtml .= '<div class="d-flex justify-content-between"><span>Eş Yardımı:</span><span class="fw-bold text-success">+' . number_format($hesap['spouseAllowanceDeduction'], 2, ',', '.') . ' ₺</span></div>';
                        }
                        if (!empty($hesap['bankaEkOdemeDetaylari'])) {
                            foreach ($hesap['bankaEkOdemeDetaylari'] as $bed) {
                                if ($bed['tutar'] > 0) {
                                    $bankaDetayHtml .= '<div class="d-flex justify-content-between"><span>' . htmlspecialchars($bed['etiket']) . ':</span><span class="fw-bold text-success">+' . number_format($bed['tutar'], 2, ',', '.') . ' ₺</span></div>';
                                }
                            }
                        }
                        
                        $bankaMatrahiToplam = $hesap['asgariYatacak'] + $hesap['mealAllowanceDeduction'] + $hesap['spouseAllowanceDeduction'] + $hesap['yontemliBankaEki'];
                        $bankaDetayHtml .= '<div class="d-flex justify-content-between border-top pt-1 mt-1"><span>Banka Limit Matrahı:</span><span class="fw-bold text-primary">' . number_format($bankaMatrahiToplam, 2, ',', '.') . ' ₺</span></div>';
                        
                        $toplamKesintiBanka = $hesap['bankaOncelikliKesinti'] + $hesap['bankaAktarilanKesinti'];
                        if ($toplamKesintiBanka > 0) {
                            $bankaDetayHtml .= '<div class="d-flex justify-content-between text-danger"><span>Düşülen Kesintiler:</span><span class="fw-bold">-' . number_format($toplamKesintiBanka, 2, ',', '.') . ' ₺</span></div>';
                        }
                    } else {
                        $bankaDetayHtml .= '<div class="d-flex justify-content-between"><span>Net Hakediş (Net - Sodexo):</span><span class="fw-bold">' . number_format(max(0, $hesap['netAlacagi'] - $hesap['sodexoOdemesi'] - $hesap['digerOdeme']), 2, ',', '.') . ' ₺</span></div>';
                        if ($hesap['nonKurRatio'] < 1.0) {
                            $bankaDetayHtml .= '<div class="d-flex justify-content-between"><span>Banka Dağıtım Oranı:</span><span class="fw-bold text-info">%' . ($hesap['nonKurRatio'] * 100) . '</span></div>';
                        }
                        $bankaDetayHtml .= '<div class="d-flex justify-content-between border-top pt-1 mt-1"><span>Banka Matrahı:</span><span class="fw-bold text-primary">' . number_format($hesap['bankaMatrahi'], 2, ',', '.') . ' ₺</span></div>';
                        if ($hesap['icraKesintisi'] > 0) {
                            $bankaDetayHtml .= '<div class="d-flex justify-content-between text-danger"><span>Düşülen Kesinti (İcra vb.):</span><span class="fw-bold">-' . number_format($hesap['icraKesintisi'], 2, ',', '.') . ' ₺</span></div>';
                        }
                    }
                    
                    $bankaDetayHtml .= '<div class="d-flex justify-content-between border-top border-secondary pt-1 mt-1 fw-bold text-primary"><span>Net Banka Ödemesi:</span><span>' . number_format($hesap['bankaOdemesi'], 2, ',', '.') . ' ₺</span></div>';
                    $bankaDetayHtml .= '</div></div>';
                }

                echo json_encode([
                    'status' => 'success',
                    'html' => $html,
                    'summary' => [
                        'brut_toplam' => '₺' . number_format($displayToplamAlacak, 2, ',', '.'),
                        'kesinti_toplam' => ($kesintiTutarOzet > 0 ? '-' : '') . '₺' . number_format($kesintiTutarOzet, 2, ',', '.'),
                        'net_maas' => '₺' . number_format($gorunenNetMaas, 2, ',', '.'),
                        'banka' => $bankaOdemeModal > 0 ? '₺' . number_format($bankaOdemeModal, 2, ',', '.') : null,
                        'elden' => $eldenOdemeModal > 0 ? '₺' . number_format($eldenOdemeModal, 2, ',', '.') : null,
                        'sodexo' => $sodexoOdemeModal > 0 ? '₺' . number_format($sodexoOdemeModal, 2, ',', '.') : null,
                        'diger' => $digerOdemeModal > 0 ? '₺' . number_format($digerOdemeModal, 2, ',', '.') : null,
                        'banka_detay' => $bankaDetayHtml,
                    ]
                ]);
                break;

            case 'donem-kapat':
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem.');
                }

                // Dönem bilgilerini al
                $donem = $BordroDonem->getDonemById($donem_id);
                if (!$donem) {
                    throw new Exception('Dönem bulunamadı.');
                }

                // Döneme ait onaylanmamış avans ve izinleri kontrol et
                $warnings = [];

                // Onaylanmamış avansları kontrol et
                $avansQuery = $BordroDonem->getDb()->prepare("
                    SELECT COUNT(*) as count, SUM(tutar) as toplam 
                    FROM personel_avanslari pa
                    JOIN personel p ON pa.personel_id = p.id
                    WHERE pa.durum = 'beklemede' 
                    AND pa.silinme_tarihi IS NULL
                    AND p.firma_id = ?
                    AND pa.talep_tarihi BETWEEN ? AND ?
                ");
                $avansQuery->execute([
                    $_SESSION['firma_id'],
                    $donem->baslangic_tarihi,
                    $donem->bitis_tarihi
                ]);
                $avansResult = $avansQuery->fetch(PDO::FETCH_OBJ);

                if ($avansResult && $avansResult->count > 0) {
                    $warnings[] = $avansResult->count . ' adet onaylanmamış avans talebi (' . number_format($avansResult->toplam, 2, ',', '.') . ' ₺)';
                }

                // Onaylanmamış izinleri kontrol et
                $izinQuery = $BordroDonem->getDb()->prepare("
                    SELECT COUNT(*) as count, SUM(DATEDIFF(bitis_tarihi, baslangic_tarihi) + 1) as toplam_gun
                    FROM personel_izinleri pi
                    JOIN personel p ON pi.personel_id = p.id
                    WHERE pi.onay_durumu = 'beklemede' 
                    AND pi.silinme_tarihi IS NULL
                    AND p.firma_id = ?
                    AND (
                        (pi.baslangic_tarihi BETWEEN ? AND ?)
                        OR (pi.bitis_tarihi BETWEEN ? AND ?)
                        OR (pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ?)
                    )
                ");
                $izinQuery->execute([
                    $_SESSION['firma_id'],
                    $donem->baslangic_tarihi,
                    $donem->bitis_tarihi,
                    $donem->baslangic_tarihi,
                    $donem->bitis_tarihi,
                    $donem->baslangic_tarihi,
                    $donem->bitis_tarihi
                ]);
                $izinResult = $izinQuery->fetch(PDO::FETCH_OBJ);

                if ($izinResult && $izinResult->count > 0) {
                    $warnings[] = $izinResult->count . ' adet onaylanmamış izin talebi (' . intval($izinResult->toplam_gun) . ' gün)';
                }

                // Uyarı var mı kontrol et, force_close parametresi yoksa uyarı döndür
                $forceClose = isset($_POST['force_close']) && $_POST['force_close'] == '1';

                if (!empty($warnings) && !$forceClose) {
                    echo json_encode([
                        'status' => 'warning',
                        'message' => 'Bu döneme ait bekleyen talepler var:',
                        'warnings' => $warnings,
                        'donem_id' => $donem_id
                    ]);
                    break;
                }

                // Dönemi kapat
                $sql = $BordroDonem->getDb()->prepare("UPDATE bordro_donemi SET kapali_mi = 1 WHERE id = ?");
                $sql->execute([$donem_id]);

                $message = 'Dönem kapatıldı. Artık bu dönemde değişiklik yapılamaz.';
                if (!empty($warnings)) {
                    $message .= ' (Uyarılar göz ardı edildi)';
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => $message
                ]);

                $SystemLog->logAction($userId, 'Maaş Dönem Kapama', "Dönem kapatıldı (ID: $donem_id).", SystemLogModel::LEVEL_IMPORTANT);
                break;

            // Dönemi Aç
            case 'donem-ac':
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem.');
                }

                $sql = $BordroDonem->getDb()->prepare("UPDATE bordro_donemi SET kapali_mi = 0 WHERE id = ?");
                $sql->execute([$donem_id]);

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Dönem açıldı. Artık bu dönemde değişiklik yapılabilir.'
                ]);

                $SystemLog->logAction($userId, 'Maaş Dönem Açma', "Dönem tekrar açıldı (ID: $donem_id).", SystemLogModel::LEVEL_IMPORTANT);
                break;

            // Ödeme Dağıt
            case 'odeme-dagit':
                $id = intval($_POST['id'] ?? 0);
                $banka = floatval($_POST['banka_odemesi'] ?? 0);
                $sodexo = floatval($_POST['sodexo_odemesi'] ?? 0);
                $diger = floatval($_POST['diger_odeme'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz personel.');
                }

                // Bordro kaydını detaylı al (hesaplama için)
                $stmt = $BordroPersonel->getDb()->prepare("
                    SELECT bp.*, bd.baslangic_tarihi, bd.bitis_tarihi,
                           p.maas_tutari, p.maas_durumu, p.yemek_yardimi_dahil, p.es_yardimi_dahil,
                           p.yemek_yardimi_tutari, p.es_yardimi_tutari, p.ise_giris_tarihi, p.isten_cikis_tarihi,
                           p.bes_kesintisi_varmi, p.sodexo, p.sgk_yapilan_firma
                    FROM bordro_personel bp
                    JOIN bordro_donemi bd ON bp.donem_id = bd.id
                    JOIN personel p ON bp.personel_id = p.id
                    WHERE bp.id = ?
                ");
                $stmt->execute([$id]);
                $p = $stmt->fetch(PDO::FETCH_OBJ);

                if (!$p) {
                    throw new Exception('Bordro kaydı bulunamadı.');
                }

                $donemObj = (object) [
                    'baslangic_tarihi' => $p->baslangic_tarihi,
                    'bitis_tarihi' => $p->bitis_tarihi
                ];
                
                $asgariUcretNet = $BordroParametre->getGenelAyar('asgari_ucret_net', $p->baslangic_tarihi) ?? 17002.12;

                $hesap = $BordroPersonel->hesaplaOrtakGosterimDegerleri($p, $donemObj, floatval($asgariUcretNet));
                $net = $hesap['netAlacagi'];
                $toplam_alacak = $hesap['toplamAlacagi'];
                $icra = $hesap['icraKesintisi'];

                // Üst sınır kontrolü (%25)
                $maxSodexo = $toplam_alacak * 0.25;
                if ($sodexo > $maxSodexo + 0.01) { // Küçük kuruş farklarını tolore etmek için
                    throw new Exception('Sodexo tutarı toplam alacağın %25\'ini geçemez!');
                }

                $maxBanka = max(0, $net - $sodexo - $icra - $diger);
                if ($banka > $maxBanka + 0.01) {
                    $banka = $maxBanka;
                }
                $elden = max(0, $net - $banka - $sodexo - $icra - $diger);

                // Güncelle
                $sql = $BordroPersonel->getDb()->prepare("
                    UPDATE bordro_personel 
                    SET banka_odemesi = ?, sodexo_odemesi = ?, diger_odeme = ?, elden_odeme = ?, sodexo_manuel = 1, dagitim_manuel = 1
                    WHERE id = ?
                ");
                $sql->execute([$banka, $sodexo, $diger, $elden, $id]);

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Ödeme dağılımı kaydedildi.'
                ]);
                break;

            // Ödeme Dağıtımı Sıfırla (Varsayılana Dön)
            case 'odeme-reset':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('Geçersiz personel.');
                }

                // Manuel dağıtım bayraklarını kaldır ve değerleri sıfırla
                $sql = $BordroPersonel->getDb()->prepare("
                    UPDATE bordro_personel 
                    SET dagitim_manuel = 0, sodexo_manuel = 0, banka_odemesi = 0, sodexo_odemesi = 0, diger_odeme = 0, elden_odeme = 0
                    WHERE id = ?
                ");
                $sql->execute([$id]);

                // Maaşı tekrar hesapla (Varsayılan dağılım mantığı çalışacak)
                $BordroPersonel->hesaplaMaas($id);

                $sqlDefault = $BordroPersonel->getDb()->prepare("
                    SELECT bp.*, bd.baslangic_tarihi, bd.bitis_tarihi
                    FROM bordro_personel bp
                    LEFT JOIN bordro_donemi bd ON bd.id = bp.donem_id
                    WHERE bp.id = ?
                ");
                $sqlDefault->execute([$id]);
                $defaultRow = $sqlDefault->fetch(PDO::FETCH_OBJ);
                $defaultBanka = 0;
                $defaultSodexo = 0;
                $defaultDiger = 0;
                $defaultElden = 0;
                if ($defaultRow) {
                    $donemObj = (object) [
                        'baslangic_tarihi' => $defaultRow->baslangic_tarihi ?? date('Y-m-01'),
                        'bitis_tarihi' => $defaultRow->bitis_tarihi ?? date('Y-m-t'),
                    ];
                    $asgariNetDefault = floatval($BordroParametre->getGenelAyar('asgari_ucret_net', $donemObj->baslangic_tarihi) ?? 0);
                    $defaultHesap = $BordroPersonel->hesaplaOrtakGosterimDegerleri($defaultRow, $donemObj, $asgariNetDefault);
                    $defaultBanka = round(floatval($defaultHesap['bankaOdemesi'] ?? 0), 2);
                    $defaultSodexo = round(floatval($defaultHesap['sodexoOdemesi'] ?? 0), 2);
                    $defaultDiger = round(floatval($defaultHesap['digerOdeme'] ?? 0), 2);
                    $defaultElden = round(floatval($defaultHesap['eldenOdeme'] ?? 0), 2);

                    $sqlWriteDefault = $BordroPersonel->getDb()->prepare("
                        UPDATE bordro_personel
                        SET banka_odemesi = ?, sodexo_odemesi = ?, diger_odeme = ?, elden_odeme = ?
                        WHERE id = ?
                    ");
                    $sqlWriteDefault->execute([$defaultBanka, $defaultSodexo, $defaultDiger, $defaultElden, $id]);
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Ödeme dağılımı varsayılana döndürüldü.'
                ]);
                break;

            // Tüm Ödeme Dağıtımlarını Sıfırla
            case 'odeme-reset-all':
                $donem_id = intval($_POST['donem_id'] ?? 0);
                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem.');
                }

                $donem = $BordroDonem->getDonemById($donem_id);
                if (!$donem) {
                    throw new Exception('Dönem bulunamadı.');
                }

                if ($donem->kapali_mi) {
                    throw new Exception('Kapalı dönemlerde bu işlem yapılamaz.');
                }

                // Tüm personeller için manuel dağıtımları sıfırla
                $sql = $BordroPersonel->getDb()->prepare("
                    UPDATE bordro_personel 
                    SET dagitim_manuel = 0, sodexo_manuel = 0, banka_odemesi = 0, sodexo_odemesi = 0, diger_odeme = 0, elden_odeme = 0
                    WHERE donem_id = ? AND silinme_tarihi IS NULL
                ");
                $sql->execute([$donem_id]);

                // Tüm personellerin maaşını tekrar hesapla (Varsayılan dağılım mantığı çalışacak)
                $sqlIds = $BordroPersonel->getDb()->prepare("SELECT id FROM bordro_personel WHERE donem_id = ? AND silinme_tarihi IS NULL");
                $sqlIds->execute([$donem_id]);
                $ids = $sqlIds->fetchAll(PDO::FETCH_COLUMN);

                $hesaplayanId = $_SESSION['user_id'] ?? 0;
                $hesaplayanAdSoyad = $_SESSION['user_full_name'] ?? 'Sistem';

                foreach ($ids as $id) {
                    $BordroPersonel->hesaplaMaas($id, $hesaplayanId, $hesaplayanAdSoyad);
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Tüm personellerin ödeme dağılımları varsayılana döndürüldü ve maaşlar tekrar hesaplandı.'
                ]);
                $SystemLog->logAction($userId, 'Bordro Toplu Ödeme Reset', "$donem->donem_adi dönemi için tüm ödeme dağılımları sıfırlandı.", SystemLogModel::LEVEL_IMPORTANT);
                break;

            // Personel Gelir Ekle / Güncelle
            case 'personel-gelir-ekle':
                $id = intval($_POST['id'] ?? 0); // ID varsa güncelleme
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');
                $tutar = floatval($_POST['gelir_tutar'] ?? 0);
                $tur = trim($_POST['ek_odeme_tur'] ?? 'diger');
                $tarih = !empty($_POST['tarih']) ? Date::dttoeng($_POST['tarih']) : date('Y-m-d');

                if ($personel_id <= 0 || $donem_id <= 0) {
                    throw new Exception('Geçersiz personel veya dönem.');
                }

                // Dönem kapalı mı kontrolü
                $donem = $BordroDonem->getDonemById($donem_id);
                if ($donem && $donem->kapali_mi == 1) {
                    throw new Exception('Bu dönem kapatılmış. Kapalı dönemlere ek ödeme eklenemez.');
                }

                if ($tutar <= 0) {
                    throw new Exception('Tutar 0\'dan büyük olmalıdır.');
                }

                if ($id > 0) {
                    // Güncelleme
                    $sql = $BordroPersonel->getDb()->prepare("
                        UPDATE personel_ek_odemeler 
                        SET aciklama = ?, tutar = ?, tur = ?, tarih = ?
                        WHERE id = ?
                    ");
                    if ($sql->execute([$aciklama, $tutar, $tur, $tarih, $id])) {
                        // Otomatik maaş hesapla
                        $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);

                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Gelir başarıyla güncellendi ve maaş hesaplandı.'
                        ]);
                    } else {
                        throw new Exception('Gelir güncellenirken bir hata oluştu.');
                    }
                } else {
                    // Ekleme
                    if ($BordroPersonel->addEkOdeme($personel_id, $donem_id, $aciklama, $tutar, $tur, $tarih)) {
                        // Otomatik maaş hesapla
                        $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);

                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Gelir başarıyla eklendi ve maaş güncellendi.'
                        ]);
                    } else {
                        throw new Exception('Gelir eklenirken bir hata oluştu.');
                    }
                }
                break;

            // Personel Kesinti Ekle / Güncelle
            case 'personel-kesinti-ekle':
                $id = intval($_POST['id'] ?? 0); // ID varsa güncelleme
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');
                $tutar = floatval($_POST['kesinti_tutar'] ?? 0);
                $tur = trim($_POST['kesinti_tur'] ?? 'diger');
                $tarih = !empty($_POST['tarih']) ? Date::dttoeng($_POST['tarih']) : date('Y-m-d');

                if ($personel_id <= 0 || $donem_id <= 0) {
                    throw new Exception('Geçersiz personel veya dönem.');
                }

                // Dönem kapalı mı kontrolü
                $donem = $BordroDonem->getDonemById($donem_id);
                if ($donem && $donem->kapali_mi == 1) {
                    throw new Exception('Bu dönem kapatılmış. Kapalı dönemlere kesinti eklenemez.');
                }

                // Eğer tutar 0 ise ve gün girilmişse maaştan hesapla
                $gun = floatval($_POST['kesinti_gun'] ?? 0);
                if ($tutar <= 0 && $gun > 0) {
                    $stmt = $BordroPersonel->getDb()->prepare("SELECT maas_tutari FROM personel WHERE id = ?");
                    $stmt->execute([$personel_id]);
                    $maas = floatval($stmt->fetchColumn());

                    if ($maas > 0) {
                        $gunluk = $maas / 30;
                        $tutar = round($gunluk * $gun, 2);
                    }
                }

                if ($tutar <= 0) {
                    throw new Exception('Tutar 0\'dan büyük olmalıdır.');
                }

                if ($id > 0) {
                    // Güncelleme
                    $sql = $BordroPersonel->getDb()->prepare("
                        UPDATE personel_kesintileri 
                        SET aciklama = ?, tutar = ?, tur = ?, tarih = ?
                        WHERE id = ?
                    ");
                    if ($sql->execute([$aciklama, $tutar, $tur, $tarih, $id])) {
                        // Otomatik maaş hesapla
                        $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);

                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Kesinti başarıyla güncellendi ve maaş hesaplandı.'
                        ]);
                    } else {
                        throw new Exception('Kesinti güncellenirken bir hata oluştu.');
                    }
                } else {
                    // Ekleme
                    if ($BordroPersonel->addKesinti($personel_id, $donem_id, $aciklama, $tutar, $tur, 'onaylandi', null, $tarih)) {
                        // Otomatik maaş hesapla
                        $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);

                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Kesinti başarıyla eklendi ve maaş güncellendi.'
                        ]);
                    } else {
                        throw new Exception('Kesinti eklenirken bir hata oluştu.');
                    }
                }
                break;

            // Parametre Ekle
            case 'add-parametre':
                $BordroParametre = new BordroParametreModel();

                $data = [
                    'kod' => trim($_POST['kod'] ?? ''),
                    'etiket' => trim($_POST['etiket'] ?? ''),
                    'kategori' => $_POST['kategori'] ?? 'gelir',
                    'hesaplama_tipi' => $_POST['hesaplama_tipi'] ?? 'net',
                    'odeme_yontemi' => $_POST['odeme_yontemi'] ?? 'banka',
                    'gunluk_muaf_limit' => Helper::formattedMoneyToNumber($_POST['gunluk_muaf_limit'] ?? 0),
                    'aylik_muaf_limit' => Helper::formattedMoneyToNumber($_POST['aylik_muaf_limit'] ?? 0),
                    'muaf_limit_tipi' => $_POST['muaf_limit_tipi'] ?? 'yok',
                    'sgk_matrahi_dahil' => intval($_POST['sgk_matrahi_dahil'] ?? 0),
                    'gelir_vergisi_dahil' => intval($_POST['gelir_vergisi_dahil'] ?? 1),
                    'damga_vergisi_dahil' => intval($_POST['damga_vergisi_dahil'] ?? 0),
                    'icra_pirim_dahil' => intval($_POST['icra_pirim_dahil'] ?? 0),
                    'resmi_alacagina_dahil' => intval($_POST['resmi_alacagina_dahil'] ?? 0),
                    'gecerlilik_baslangic' => !empty($_POST['gecerlilik_baslangic']) ? $_POST['gecerlilik_baslangic'] : null,
                    'gecerlilik_bitis' => !empty($_POST['gecerlilik_bitis']) ? $_POST['gecerlilik_bitis'] : null,
                    'varsayilan_tutar' => Helper::formattedMoneyToNumber($_POST['varsayilan_tutar'] ?? 0),
                    'gunluk_tutar' => Helper::formattedMoneyToNumber($_POST['gunluk_tutar'] ?? 0),
                    'gun_sayisi_otomatik' => intval($_POST['gun_sayisi_otomatik'] ?? 0),
                    'varsayilan_gun_sayisi' => intval($_POST['varsayilan_gun_sayisi'] ?? 26),
                    'oran' => Helper::formattedMoneyToNumber($_POST['oran'] ?? 0),
                    'aciklama' => trim($_POST['aciklama'] ?? ''),
                    'sira' => intval($_POST['sira'] ?? 0),
                    'aktif' => 1
                ];

                if (empty($data['kod']) || empty($data['etiket'])) {
                    throw new Exception('Kod ve etiket zorunludur.');
                }

                if ($BordroParametre->addParametre($data)) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Parametre başarıyla eklendi.'
                    ]);
                } else {
                    throw new Exception('Parametre eklenirken bir hata oluştu.');
                }
                break;

            // Parametre Güncelle
            case 'update-parametre':
                $BordroParametre = new BordroParametreModel();
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz parametre.');
                }

                $data = [
                    'kod' => trim($_POST['kod'] ?? ''),
                    'etiket' => trim($_POST['etiket'] ?? ''),
                    'kategori' => $_POST['kategori'] ?? 'gelir',
                    'hesaplama_tipi' => $_POST['hesaplama_tipi'] ?? 'net',
                    'odeme_yontemi' => $_POST['odeme_yontemi'] ?? 'banka',
                    'gunluk_muaf_limit' => Helper::formattedMoneyToNumber($_POST['gunluk_muaf_limit'] ?? 0),
                    'aylik_muaf_limit' => Helper::formattedMoneyToNumber($_POST['aylik_muaf_limit'] ?? 0),
                    'muaf_limit_tipi' => $_POST['muaf_limit_tipi'] ?? 'yok',
                    'sgk_matrahi_dahil' => intval($_POST['sgk_matrahi_dahil'] ?? 0),
                    'gelir_vergisi_dahil' => intval($_POST['gelir_vergisi_dahil'] ?? 1),
                    'damga_vergisi_dahil' => intval($_POST['damga_vergisi_dahil'] ?? 0),
                    'icra_pirim_dahil' => intval($_POST['icra_pirim_dahil'] ?? 0),
                    'resmi_alacagina_dahil' => intval($_POST['resmi_alacagina_dahil'] ?? 0),
                    'gecerlilik_baslangic' => !empty($_POST['gecerlilik_baslangic']) ? $_POST['gecerlilik_baslangic'] : null,
                    'gecerlilik_bitis' => !empty($_POST['gecerlilik_bitis']) ? $_POST['gecerlilik_bitis'] : null,
                    'varsayilan_tutar' => Helper::formattedMoneyToNumber($_POST['varsayilan_tutar'] ?? 0),
                    'gunluk_tutar' => Helper::formattedMoneyToNumber($_POST['gunluk_tutar'] ?? 0),
                    'gun_sayisi_otomatik' => intval($_POST['gun_sayisi_otomatik'] ?? 0),
                    'varsayilan_gun_sayisi' => intval($_POST['varsayilan_gun_sayisi'] ?? 26),
                    'oran' => Helper::formattedMoneyToNumber($_POST['oran'] ?? 0),
                    'aciklama' => trim($_POST['aciklama'] ?? ''),
                    'sira' => intval($_POST['sira'] ?? 0)
                ];

                if ($BordroParametre->updateParametre($id, $data)) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Parametre başarıyla güncellendi.'
                    ]);
                } else {
                    throw new Exception('Parametre güncellenirken bir hata oluştu.');
                }
                break;

            // Genel Ayar Ekle
            case 'add-genel-ayar':
                $BordroParametre = new BordroParametreModel();

                $parametre_kodu = trim($_POST['parametre_kodu'] ?? '');
                $parametre_adi = trim($_POST['parametre_adi'] ?? '');
                $deger = Helper::formattedMoneyToNumber($_POST['deger'] ?? '0');
                $gecerlilik_baslangic = $_POST['ayar_gecerlilik_baslangic'] ?? date('Y-m-d');
                $aciklama = trim($_POST['ayar_aciklama'] ?? '');

                if (empty($parametre_kodu) || empty($parametre_adi)) {
                    throw new Exception('Parametre kodu ve adı zorunludur.');
                }

                if ($BordroParametre->setGenelAyar($parametre_kodu, $parametre_adi, $deger, $gecerlilik_baslangic, null, $aciklama)) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Genel ayar başarıyla eklendi.'
                    ]);
                } else {
                    throw new Exception('Genel ayar eklenirken bir hata oluştu.');
                }
                break;

            // Gelir/Kesinti türlerini getir (AJAX için)
            case 'get-gelir-turleri':
                $BordroParametre = new BordroParametreModel();
                $turler = $BordroParametre->getGelirTurleri();
                echo json_encode(['status' => 'success', 'data' => $turler]);
                break;

            case 'get-kesinti-turleri':
                $BordroParametre = new BordroParametreModel();
                $turler = $BordroParametre->getKesintiTurleri();
                echo json_encode(['status' => 'success', 'data' => $turler]);
                break;

            // Parametre sil (soft delete)
            case 'delete-parametre':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz parametre ID.');
                }

                $BordroParametre = new BordroParametreModel();

                // Soft delete - aktif = 0 yap
                if ($BordroParametre->updateParametre($id, ['aktif' => 0])) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Parametre başarıyla silindi.'
                    ]);
                } else {
                    throw new Exception('Parametre silinirken bir hata oluştu.');
                }
                break;

            // Genel Ayar güncelle
            case 'update-genel-ayar':
                $id = intval($_POST['id'] ?? 0);
                $parametre_kodu = trim($_POST['parametre_kodu'] ?? '');
                $parametre_adi = trim($_POST['parametre_adi'] ?? '');
                $deger = Helper::formattedMoneyToNumber($_POST['deger'] ?? '0');
                $gecerlilik_baslangic = $_POST['ayar_gecerlilik_baslangic'] ?? date('Y-m-d');
                $aciklama = trim($_POST['ayar_aciklama'] ?? '');

                if ($id <= 0) {
                    throw new Exception('Geçersiz ayar ID.');
                }

                $BordroParametre = new BordroParametreModel();

                $sql = $BordroParametre->getDb()->prepare("
                    UPDATE bordro_genel_ayarlar 
                    SET parametre_kodu = ?, parametre_adi = ?, deger = ?, 
                        gecerlilik_baslangic = ?, aciklama = ?
                    WHERE id = ?
                ");

                if ($sql->execute([$parametre_kodu, $parametre_adi, $deger, $gecerlilik_baslangic, $aciklama, $id])) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Ayar başarıyla güncellendi.'
                    ]);
                } else {
                    throw new Exception('Ayar güncellenirken bir hata oluştu.');
                }
                break;

            // Genel Ayar sil
            case 'delete-genel-ayar':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz ayar ID.');
                }

                $BordroParametre = new BordroParametreModel();

                $sql = $BordroParametre->getDb()->prepare("
                    UPDATE bordro_genel_ayarlar SET aktif = 0 WHERE id = ?
                ");

                if ($sql->execute([$id])) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Ayar başarıyla silindi.'
                    ]);
                } else {
                    throw new Exception('Ayar silinirken bir hata oluştu.');
                }
                break;

            // Genel Ayarları yeni döneme kopyala
            case 'copy-genel-ayarlar':
                $yeni_gecerlilik = $_POST['yeni_gecerlilik'] ?? '';
                $ayar_sec = $_POST['ayar_sec'] ?? [];
                $yeni_deger = $_POST['yeni_deger'] ?? [];
                $aciklama = trim($_POST['donem_aciklama'] ?? '');

                if (empty($yeni_gecerlilik)) {
                    throw new Exception('Yeni geçerlilik tarihi zorunludur.');
                }

                if (empty($ayar_sec)) {
                    throw new Exception('En az bir ayar seçmelisiniz.');
                }

                $BordroParametre = new BordroParametreModel();
                $db = $BordroParametre->getDb();

                // Mevcut ayarları getir
                $placeholders = implode(',', array_fill(0, count($ayar_sec), '?'));
                $sql = $db->prepare("SELECT * FROM bordro_genel_ayarlar WHERE id IN ($placeholders) AND aktif = 1");
                $sql->execute($ayar_sec);
                $mevcutAyarlar = $sql->fetchAll(PDO::FETCH_OBJ);

                $eklenen = 0;
                foreach ($mevcutAyarlar as $ayar) {
                    $yeniDeger = isset($yeni_deger[$ayar->id]) ? floatval($yeni_deger[$ayar->id]) : $ayar->deger;

                    $insertSql = $db->prepare("
                        INSERT INTO bordro_genel_ayarlar 
                        (parametre_kodu, parametre_adi, deger, gecerlilik_baslangic, aciklama, aktif)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");

                    if (
                        $insertSql->execute([
                            $ayar->parametre_kodu,
                            $ayar->parametre_adi,
                            $yeniDeger,
                            $yeni_gecerlilik,
                            $aciklama ?: $ayar->aciklama
                        ])
                    ) {
                        $eklenen++;
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => "$eklenen ayar yeni döneme kopyalandı."
                ]);
                break;

            // Vergi Dilimi Ekle
            case 'add-vergi-dilimi':
                $BordroParametre = new BordroParametreModel();

                $yil = intval($_POST['dilim_yili'] ?? date('Y'));
                $dilim_no = intval($_POST['dilim_no'] ?? 0);
                $alt_limit = cleanMoneyInput($_POST['alt_limit'] ?? '0');
                $ust_limit = cleanMoneyInputNullable($_POST['ust_limit'] ?? '');
                $vergi_orani = floatval($_POST['vergi_orani'] ?? 0);
                $aciklama = trim($_POST['dilim_aciklama'] ?? '');

                if ($dilim_no <= 0 || $dilim_no > 10) {
                    throw new Exception('Dilim numarası 1-10 arasında olmalıdır.');
                }

                if ($vergi_orani < 0 || $vergi_orani > 100) {
                    throw new Exception('Vergi oranı 0-100 arasında olmalıdır.');
                }

                if ($BordroParametre->addVergiDilimi($yil, $dilim_no, $alt_limit, $ust_limit, $vergi_orani, $aciklama)) {
                    $SystemLog->logAction($userId, 'Vergi Dilimi Ekleme', "Yeni vergi dilimi eklendi: Yıl: $yil, Dilim No: $dilim_no, Alt Limit: $alt_limit, Üst Limit: " . ($ust_limit ?? 'Sınırsız') . ", Oran: %$vergi_orani", SystemLogModel::LEVEL_IMPORTANT);
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Vergi dilimi başarıyla eklendi.'
                    ]);
                } else {
                    throw new Exception('Vergi dilimi eklenirken bir hata oluştu.');
                }
                break;

            // Vergi Dilimi Güncelle
            case 'update-vergi-dilimi':
                $BordroParametre = new BordroParametreModel();
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz dilim ID.');
                }

                $yil = intval($_POST['dilim_yili'] ?? date('Y'));
                $dilim_no = intval($_POST['dilim_no'] ?? 0);
                $alt_limit = cleanMoneyInput($_POST['alt_limit'] ?? '0');
                $ust_limit = cleanMoneyInputNullable($_POST['ust_limit'] ?? '');
                $vergi_orani = floatval($_POST['vergi_orani'] ?? 0);
                $aciklama = trim($_POST['dilim_aciklama'] ?? '');

                $oldDilim = $BordroParametre->getVergiDilimiById($id);
                if (!$oldDilim) {
                    throw new Exception('Güncellenecek vergi dilimi bulunamadı.');
                }

                if ($BordroParametre->updateVergiDilimi($id, $yil, $dilim_no, $alt_limit, $ust_limit, $vergi_orani, $aciklama)) {
                    $SystemLog->logAction(
                        $userId,
                        'Vergi Dilimi Güncelleme',
                        "Dilim ID: $id ($dilim_no. Dilim). Önceki -> Alt: $oldDilim->alt_limit, Üst: " . ($oldDilim->ust_limit ?? 'Sınırsız') . ", Oran: %$oldDilim->vergi_orani. Yeni -> Alt: $alt_limit, Üst: " . ($ust_limit ?? 'Sınırsız') . ", Oran: %$vergi_orani",
                        SystemLogModel::LEVEL_IMPORTANT
                    );
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Vergi dilimi başarıyla güncellendi.'
                    ]);
                } else {
                    throw new Exception('Vergi dilimi güncellenirken bir hata oluştu.');
                }
                break;

            // Vergi Dilimi Sil
            case 'delete-vergi-dilimi':
                $BordroParametre = new BordroParametreModel();
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz dilim ID.');
                }

                $oldDilim = $BordroParametre->getVergiDilimiById($id);
                if (!$oldDilim) {
                    throw new Exception('Silinecek vergi dilimi bulunamadı.');
                }

                if ($BordroParametre->deleteVergiDilimi($id)) {
                    $SystemLog->logAction(
                        $userId,
                        'Vergi Dilimi Silme',
                        "$oldDilim->yil Yılı $oldDilim->dilim_no. Dilimi silindi. (Alt: $oldDilim->alt_limit, Üst: " . ($oldDilim->ust_limit ?? 'Sınırsız') . ")",
                        SystemLogModel::LEVEL_IMPORTANT
                    );
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Vergi dilimi başarıyla silindi.'
                    ]);
                } else {
                    throw new Exception('Vergi dilimi silinirken bir hata oluştu.');
                }
                break;

            // Dönem Sil (soft delete)
            case 'donem-sil':
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem.');
                }

                // Dönemin kapalı olup olmadığını kontrol et
                $donem = $BordroDonem->getDonemById($donem_id);
                if (!$donem) {
                    throw new Exception('Dönem bulunamadı.');
                }

                if ($donem->kapali_mi == 1) {
                    throw new Exception('Kapalı dönemler silinemez. Önce dönemi açmanız gerekir.');
                }

                // Soft delete uygula
                if ($BordroDonem->deleteDonem($donem_id)) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Dönem başarıyla silindi.'
                    ]);
                    unset($_SESSION['selectedDonemId']);
                } else {
                    throw new Exception('Dönem silinirken bir hata oluştu.');
                }
                break;

            // Sürekli kesinti ve ek ödemeleri döneme aktar
            case 'surekli-kayitlari-olustur':
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem.');
                }

                // Dönemdeki tüm personeller için sürekli kesinti/ek ödemeleri oluştur
                $sonuc = $BordroPersonel->olusturDonemSurekliKayitlar($donem_id);

                $mesaj = '';
                if ($sonuc['kesinti'] > 0 || $sonuc['ek_odeme'] > 0) {
                    $mesaj = "{$sonuc['kesinti']} kesinti ve {$sonuc['ek_odeme']} ek ödeme kaydı otomatik oluşturuldu.";
                } else {
                    $mesaj = 'Aktarılacak sürekli kesinti veya ek ödeme bulunamadı.';
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => $mesaj,
                    'data' => $sonuc
                ]);
                break;

            // Excel'den Gelir Yükle
            case 'gelir-ekle':
                try {
                    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
                    if (file_exists($vendorAutoload)) {
                        require_once $vendorAutoload;
                    } else {
                        throw new Exception("Excel kütüphanesi (vendor/autoload.php) bulunamadı.");
                    }

                    $donem_id = intval($_POST['donem_id'] ?? 0);
                    if ($donem_id <= 0) {
                        throw new Exception("Dönem seçilmelidir.");
                    }

                    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] != 0) {
                        throw new Exception("Dosya yüklenemedi veya dosya seçilmedi.");
                    }

                    $inputFileName = $_FILES['excel_file']['tmp_name'];
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray();

                    if (count($rows) < 3) {
                        throw new Exception("Excel dosyası boş veya veri içermiyor.");
                    }

                    // Parametreleri getir (etiket -> kod eşleştirmesi için)
                    $BordroParametre = new BordroParametreModel();
                    $parametreler = $BordroParametre->getGelirTurleri();
                    $paramMap = [];
                    foreach ($parametreler as $p) {
                        $paramMap[trim($p->etiket)] = $p->kod;
                    }

                    // Başlık satırından (Satır 2) kolonları eşleştir
                    $headers = $rows[1];
                    $colIndices = [];
                    foreach ($headers as $index => $header) {
                        $header = trim($header ?? '');
                        if (isset($paramMap[$header])) {
                            $colIndices[$paramMap[$header]] = $index;
                        }
                    }

                    $tcIndex = 1; // B kolonu (0-indexed: 1)
                    $Personel = new PersonelModel();
                    $eklenenSayisi = 0;

                    // Verileri işle (Satır 3'ten başla)
                    for ($i = 2; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        $tcNo = trim($row[$tcIndex] ?? '');
                        if (empty($tcNo))
                            continue;

                        // Personeli bul
                        $personelData = $Personel->findByTc($tcNo);
                        if (empty($personelData))
                            continue;
                        $personel_id = $personelData[0]->id;

                        $rowHasData = false;
                        foreach ($colIndices as $kod => $index) {
                            $tutar = Helper::formattedMoneyToNumber($row[$index] ?? 0);
                            if ($tutar > 0) {
                                // Mevcut kaydı sil (aynı dönem ve aynı tür için mükerrer olmasın)
                                $BordroPersonel->getDb()->prepare("
                                    UPDATE personel_ek_odemeler 
                                    SET silinme_tarihi = NOW() 
                                    WHERE personel_id = ? AND donem_id = ? AND tur = ? AND silinme_tarihi IS NULL
                                ")->execute([$personel_id, $donem_id, $kod]);

                                // Yeni kaydı ekle
                                if ($BordroPersonel->addEkOdeme($personel_id, $donem_id, "Excel'den yüklendi", $tutar, $kod)) {
                                    $rowHasData = true;
                                }
                            }
                        }

                        if ($rowHasData) {
                            // Maaşı tekrar hesapla
                            $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);
                            $eklenenSayisi++;
                        }
                    }

                    echo json_encode([
                        'status' => 'success',
                        'message' => "$eklenenSayisi personelin gelir kayıtları yüklendi ve maaşları hesaplandı."
                    ]);

                } catch (Exception $e) {
                    throw $e;
                }
                break;

            // Excel'den Kesinti Yükle
            case 'kesinti-ekle':
                try {
                    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
                    if (file_exists($vendorAutoload)) {
                        require_once $vendorAutoload;
                    } else {
                        throw new Exception("Excel kütüphanesi (vendor/autoload.php) bulunamadı.");
                    }

                    $donem_id = intval($_POST['donem_id'] ?? 0);
                    if ($donem_id <= 0) {
                        throw new Exception("Dönem seçilmelidir.");
                    }

                    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] != 0) {
                        throw new Exception("Dosya yüklenemedi veya dosya seçilmedi.");
                    }

                    $inputFileName = $_FILES['excel_file']['tmp_name'];
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray();

                    if (count($rows) < 3) {
                        throw new Exception("Excel dosyası boş veya veri içermiyor.");
                    }

                    // Parametreleri getir (etiket -> kod eşleştirmesi için)
                    $BordroParametre = new BordroParametreModel();
                    $parametreler = $BordroParametre->getKesintiTurleri();
                    $paramMap = [];
                    foreach ($parametreler as $p) {
                        $paramMap[trim($p->etiket)] = $p->kod;
                    }

                    // Başlık satırından (Satır 2) kolonları eşleştir
                    $headers = $rows[1];
                    $colIndices = [];
                    foreach ($headers as $index => $header) {
                        $header = trim($header ?? '');
                        if (isset($paramMap[$header])) {
                            $colIndices[$paramMap[$header]] = $index;
                        }
                    }

                    $tcIndex = 1; // B kolonu
                    $Personel = new PersonelModel();
                    $eklenenSayisi = 0;

                    // Verileri işle (Satır 3'ten başla)
                    for ($i = 2; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        $tcNo = trim($row[$tcIndex] ?? '');
                        if (empty($tcNo))
                            continue;

                        // Personeli bul
                        $personelData = $Personel->findByTc($tcNo);
                        if (empty($personelData))
                            continue;
                        $personel_id = $personelData[0]->id;

                        $rowHasData = false;
                        foreach ($colIndices as $kod => $index) {
                            $tutar = Helper::formattedMoneyToNumber($row[$index] ?? 0);
                            if ($tutar > 0) {
                                // Mevcut kaydı sil
                                $BordroPersonel->getDb()->prepare("
                                    UPDATE personel_kesintileri 
                                    SET silinme_tarihi = NOW() 
                                    WHERE personel_id = ? AND donem_id = ? AND tur = ? AND silinme_tarihi IS NULL
                                ")->execute([$personel_id, $donem_id, $kod]);

                                // Yeni kaydı ekle
                                if ($BordroPersonel->addKesinti($personel_id, $donem_id, "Excel'den yüklendi", $tutar, $kod, 'onaylandi')) {
                                    $rowHasData = true;
                                }
                            }
                        }

                        if ($rowHasData) {
                            // Maaşı tekrar hesapla
                            $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);
                            $eklenenSayisi++;
                        }
                    }

                    echo json_encode([
                        'status' => 'success',
                        'message' => "$eklenenSayisi personelin kesinti kayıtları yüklendi ve maaşları hesaplandı."
                    ]);

                } catch (Exception $e) {
                    throw $e;
                }
                break;

            // Excel'den Ödeme Dağıtımı Yükle
            case 'odeme-dagit-excel':
                try {
                    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
                    if (file_exists($vendorAutoload)) {
                        require_once $vendorAutoload;
                    } else {
                        throw new Exception("Excel kütüphanesi (vendor/autoload.php) bulunamadı.");
                    }

                    $donem_id = intval($_POST['donem_id'] ?? 0);
                    if ($donem_id <= 0) {
                        throw new Exception("Dönem seçilmelidir.");
                    }

                    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] != 0) {
                        throw new Exception("Dosya yüklenemedi veya dosya seçilmedi.");
                    }

                    $inputFileName = $_FILES['excel_file']['tmp_name'];
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray();

                    if (count($rows) < 3) {
                        throw new Exception("Excel dosyası boş veya veri içermiyor.");
                    }

                    // Kolon eşleştirmeleri (Sabit kolonlar)
                    // A: Sıra, B: TC, C: Ad Soyad, D: Net Maaş, E: Banka, F: Sodexo, G: Diğer, H: Elden
                    $tcIndex = 1;
                    $bankaIndex = 4;
                    $sodexoIndex = 5;
                    $digerIndex = 6;

                    $Personel = new PersonelModel();
                    $guncellenenSayisi = 0;

                    // Verileri işle (Satır 3'ten başla)
                    for ($i = 2; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        $tcNo = trim($row[$tcIndex] ?? '');
                        if (empty($tcNo))
                            continue;

                        // Personeli bul
                        $personelData = $Personel->findByTc($tcNo);
                        if (empty($personelData))
                            continue;
                        $personel_id = $personelData[0]->id;

                        // Bordro kaydını bul
                        $stmt = $BordroPersonel->getDb()->prepare("SELECT * FROM bordro_personel WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL");
                        $stmt->execute([$personel_id, $donem_id]);
                        $bp = $stmt->fetchAll(PDO::FETCH_OBJ);

                        if (empty($bp))
                            continue;
                        $bp_id = $bp[0]->id;

                        $banka = Helper::formattedMoneyToNumber($row[$bankaIndex] ?? 0);
                        $sodexo = Helper::formattedMoneyToNumber($row[$sodexoIndex] ?? 0);
                        $diger = Helper::formattedMoneyToNumber($row[$digerIndex] ?? 0);

                        $elden = Helper::formattedMoneyToNumber($row[7] ?? 0);

                        // Güncelle
                        $updateData = [
                            'banka_odemesi' => $banka,
                            'sodexo_odemesi' => $sodexo,
                            'diger_odeme' => $diger,
                            'elden_odeme' => $elden,
                            'sodexo_manuel' => 1,
                            'dagitim_manuel' => 1
                        ];

                        if ($BordroPersonel->updateBordro($bp_id, $updateData)) {
                            $guncellenenSayisi++;
                        }
                    }

                    echo json_encode([
                        'status' => 'success',
                        'message' => "$guncellenenSayisi personelin ödeme dağıtımları güncellendi."
                    ]);

                } catch (Exception $e) {
                    throw $e;
                }
                break;

            case 'get-hatali-islem-raporu':
                $donem_id = intval($_POST['donem_id'] ?? 0);
                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem.');
                }

                $donem = $BordroDonem->getDonemById($donem_id);
                if (!$donem) {
                    throw new Exception('Dönem bulunamadı.');
                }

                $start = $donem->baslangic_tarihi;
                $end = $donem->bitis_tarihi;

                // 1. RAW DATA (İş Takip)
                $Puantaj = new \App\Model\PuantajModel();
                $SayacDegisim = new \App\Model\SayacDegisimModel();
                $EndeksOkuma = new \App\Model\EndeksOkumaModel();

                // Get all personnel in this period with their salary status
                $personelData = $BordroPersonel->getDb()->prepare("
                    SELECT bp.id as bp_id, p.id as p_id, p.adi_soyadi, p.maas_durumu
                    FROM bordro_personel bp
                    INNER JOIN personel p ON bp.personel_id = p.id
                    WHERE bp.donem_id = ? AND bp.silinme_tarihi IS NULL
                    AND p.maas_durumu LIKE '%Prim%'
                ");
                $personelData->execute([$donem_id]);
                $allPersonel = $personelData->fetchAll(PDO::FETCH_ASSOC);

                $names = [];
                $maasDurumlari = [];
                foreach ($allPersonel as $p) {
                    $names[$p['p_id']] = $p['adi_soyadi'];
                    $maasDurumlari[$p['p_id']] = $p['maas_durumu'];
                }

                $rawPuantaj = $Puantaj->getSummaryDetailedByRange($start, $end);
                $rawSayac = $SayacDegisim->getSummaryDetailedByRange($start, $end);
                $rawOkuma = $EndeksOkuma->getSummaryDetailedByRange($start, $end);

                // Types to exclude from Puantaj (yapilan_isler) raw data because they are handled by Sayac/Okuma tables
                $typesToExcludeSql = $BordroPersonel->getDb()->prepare("
                    SELECT DISTINCT is_emri_sonucu 
                    FROM tanimlamalar 
                    WHERE rapor_sekmesi IN ('sokme_takma', 'endeks_okuma')
                    AND grup = 'is_turu'
                    AND is_emri_sonucu IS NOT NULL AND is_emri_sonucu != ''
                ");
                $typesToExcludeSql->execute();
                $typesToExclude = $typesToExcludeSql->fetchAll(PDO::FETCH_COLUMN);
                $excludeMap = array_flip(array_map('trim', $typesToExclude));

                // Paid types filter
                $paidTypesSql = $BordroPersonel->getDb()->prepare("
                    SELECT DISTINCT is_emri_sonucu 
                    FROM tanimlamalar 
                    WHERE (is_turu_ucret > 0 OR aracli_personel_is_turu_ucret > 0 OR okuma_is_turu_ucret > 0) 
                    AND grup = 'is_turu'
                    AND is_emri_sonucu IS NOT NULL AND is_emri_sonucu != ''
                ");
                $paidTypesSql->execute();
                $paidTypes = $paidTypesSql->fetchAll(PDO::FETCH_COLUMN);
                $paidTypes[] = "Endeks Okuma";
                $paidTypes[] = "Kaçak Kontrol";
                $paidTypesMap = array_flip(array_map('trim', $paidTypes));

                $allData = [];

                // Helper to add data to allData
                $addData = function($pId, $type, $count) use (&$allData, $paidTypesMap) {
                    if ($count <= 0) return;
                    if (!isset($paidTypesMap[$type])) return; // Skip unpaid types
                    
                    if (!isset($allData[$pId])) $allData[$pId] = [];
                    $allData[$pId][$type] = ($allData[$pId][$type] ?? 0) + $count;
                };

                // Process Raw Puantaj (Kesme/Açma/etc)
                foreach ($rawPuantaj as $pId => $comps) {
                    foreach ($comps as $cKey => $days) {
                        foreach ($days as $day => $works) {
                            foreach ($works as $wName => $count) {
                                // Skip types that belong to exclusive tables (Sayac/Okuma)
                                if (isset($excludeMap[trim($wName)])) continue;
                                
                                $addData($pId, $wName, $count);
                            }
                        }
                    }
                }

                // Process Raw Sayac Degisim
                foreach ($rawSayac as $pId => $comps) {
                    foreach ($comps as $cKey => $days) {
                        foreach ($days as $day => $works) {
                            foreach ($works as $wName => $count) {
                                // Important: In sayac_degisim, is_emri_sonucu is usually the key
                                $addData($pId, $wName, $count);
                            }
                        }
                    }
                }

                // Process Raw Okuma
                foreach ($rawOkuma as $pId => $comps) {
                    foreach ($comps as $cKey => $days) {
                        foreach ($days as $day => $works) {
                            foreach ($works as $wName => $count) {
                                // "Endeks Okuma" raw counts from endeks_okuma table
                                $addData($pId, $wName, $count);
                            }
                        }
                    }
                }

                // Process Raw Kacak - Attribute to individual personnel
                $kacakSql = $BordroPersonel->getDb()->prepare("
                    SELECT id, personel_ids, sayi 
                    FROM kacak_kontrol 
                    WHERE tarih BETWEEN ? AND ? 
                    AND silinme_tarihi IS NULL
                    AND personel_ids IS NOT NULL
                    AND (aciklama != 'Manuel Düşüm' OR aciklama IS NULL)
                ");
                $kacakSql->execute([$start, $end]);
                $kacakRows = $kacakSql->fetchAll(PDO::FETCH_ASSOC);

                foreach ($kacakRows as $kr) {
                    $pIds = array_filter(array_map('trim', explode(',', $kr['personel_ids'])));
                    $val = floatval($kr['sayi']);
                    foreach ($pIds as $pId) {
                        $addData($pId, "Kaçak Kontrol", $val);
                    }
                }

                // 2. PAYROLL DATA (Calculated)
                $payrollCounts = [];
                $sql = $BordroPersonel->getDb()->prepare("
                    SELECT personel_id, aciklama 
                    FROM personel_ek_odemeler 
                    WHERE donem_id = ? AND silinme_tarihi IS NULL 
                    AND (aciklama LIKE '[Puantaj]%' OR aciklama LIKE '[Sayaç]%' OR aciklama LIKE '[Kaçak Kontrol]%')
                ");
                $sql->execute([$donem_id]);
                $eklar = $sql->fetchAll(PDO::FETCH_OBJ);

                foreach ($eklar as $ek) {
                    $pId = $ek->personel_id;
                    $desc = $ek->aciklama;

                    $type = "";
                    $count = 0;

                    // Parse description: 
                    // 1. [Prefix] Type (X Adet x ...)
                    // 2. [Kaçak Kontrol] (X işlem Toplam)(Y işlem Muaf)
                    if (preg_match('/^\[(Puantaj|Sayaç)\]\s+(.*?)\s+\((.*?)\s+Adet/', $desc, $matches)) {
                        $type = trim($matches[2]);
                        // Adet can contain comma if it's float (like 0,5)
                        $countStr = str_replace(',', '.', $matches[3]);
                        $count = floatval($countStr);
                    } elseif (preg_match('/^\[Kaçak Kontrol\]\s+\((\d+)\s+işlem/', $desc, $matches)) {
                        $type = "Kaçak Kontrol";
                        $count = intval($matches[1]);
                    }
                    
                    if ($type !== "" && $count > 0) {
                        if (!isset($payrollCounts[$pId])) $payrollCounts[$pId] = [];
                        $payrollCounts[$pId][$type] = ($payrollCounts[$pId][$type] ?? 0) + $count;
                    }
                }

                // 3. COMPARE
                $report = [];
                // Use already filtered $names from the top of the action 
                // which contains only 'Prim Usulü' workers.
                $pMap = $names;

                // Merge all unique pIds and types
                $relevantPIds = array_unique(array_merge(array_keys($allData), array_keys($payrollCounts)));
                
                foreach ($relevantPIds as $pId) {
                    if (!isset($pMap[$pId])) continue; // Person who is not in this payroll donem but has data? Should not happen often.

                    $rawPData = $allData[$pId] ?? [];
                    $payPData = $payrollCounts[$pId] ?? [];
                    
                    $allTypes = array_unique(array_merge(array_keys($rawPData), array_keys($payPData)));
                    
                    foreach ($allTypes as $type) {
                        $rCount = $rawPData[$type] ?? 0;
                        $pCount = $payPData[$type] ?? 0;
                        $diff = $rCount - $pCount;

                        $report[] = [
                            'personel_id' => $pId,
                            'personel_adi' => $pMap[$pId],
                            'maas_durumu' => $maasDurumlari[$pId] ?? '',
                            'is_turu' => $type,
                            'is_takip_sayisi' => $rCount,
                            'bordro_sayisi' => $pCount,
                            'fark' => $diff,
                            'durum' => ($diff == 0 ? 'Tamam' : 'Hatalı')
                        ];
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => $report
                ]);
                break;

            default:
                throw new Exception('Geçersiz işlem.');


        }

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }

} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Geçersiz istek metodu.'
    ]);
}
