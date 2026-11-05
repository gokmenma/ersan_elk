<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Model\BordroDonemModel;
use App\Model\BordroPersonelModel;
use App\Model\BordroKapanisArsivModel;
use App\Model\BordroOnayKilidiModel;
use App\Model\PersonelModel;
use App\Model\BordroParametreModel;
use App\Model\SystemLogModel;
use App\Model\AvansModel;
use App\Model\PersonelIzinleriModel;
use App\Helper\Helper;
use App\Helper\Date;
use App\Helper\Security;
use App\Service\Gate;
use App\Model\PermissionPolicyModel;
use App\Model\PuantajMutabakatModel;

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
function bordroKilitliPersonelHaritasi(int $donemId): array
{
    $harita = [];
    foreach ((new BordroOnayKilidiModel())->donemKilitleri($donemId) as $kilit) {
        $harita[(int) $kilit['personel_id']] = (string) $kilit['adi_soyadi'];
    }
    return $harita;
}

function bordroKilitAtlamaMesaji(array $adlar): string
{
    if (!$adlar) {
        return '';
    }
    $adlar = array_values(array_unique($adlar));
    $liste = implode(', ', array_slice($adlar, 0, 5)) . (count($adlar) > 5 ? ' ve ' . (count($adlar) - 5) . ' kişi daha' : '');
    return ' ' . count($adlar) . ' personel bordrosunu onayladığı için kilitli olduğundan atlandı: ' . $liste . '.';
}

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
    $permissionPolicy = new PermissionPolicyModel();
    if ($permissionPolicy->isReady()) {
        Gate::authorizeApiPolicy('bordro/api', (string) $action);
        $hasBordroAccess = true;
    } else {
        $hasBordroAccess = $MenuModel->userCanAccessMenuLink($userId, 'bordro/parametreler')
            || $MenuModel->userCanAccessMenuLink($userId, 'bordro/list')
            || $MenuModel->userCanAccessMenuLink($userId, 'bordro/raporlar');
    }

    if (!$hasBordroAccess) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Bu işlemi gerçekleştirmek için yetkiniz bulunmamaktadır.']);
        exit;
    }

    try {
        if (str_starts_with($action, 'yayin-')) {
            try {
                require __DIR__ . '/partials/yayin-api.php';
            } catch (\DomainException $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            } catch (\Throwable $e) {
                error_log('Bordro yayın yönetimi: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Yayın işlemi tamamlanamadı.'], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }
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
                if ($kesintiInfo) {
                    (new BordroOnayKilidiModel())->dogrulaPersonel((int) $kesintiInfo->personel_id, (int) ($kesintiInfo->donem_id ?: $donem_id));
                }

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
                if ($odemeInfo) {
                    (new BordroOnayKilidiModel())->dogrulaPersonel((int) $odemeInfo->personel_id, (int) (($odemeInfo->donem_id ?? 0) ?: $donem_id));
                }

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

                (new BordroOnayKilidiModel())->dogrula($id);
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
                if (!$donem) {
                    throw new Exception('Dönem bulunamadı.');
                }
                // Maaş hesaplama ay içindeki taslak çalışma akışıdır. Ay sonu
                // snapshot'ı ve takip eden ayın 3'ündeki final mutabakat henüz
                // oluşmamış olsa da hesaplama yapılabilir. Mutabakat şartı
                // `donem-kapat` aksiyonunda sunucu tarafında zorunludur.
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

                $onayKilitleri = (new BordroOnayKilidiModel())->donemKilitleri($donem_id);
                $kilitAtlananlar = [];
                $personel_ids = array_values(array_filter(array_map('intval', $personel_ids), static function (int $bpId) use ($onayKilitleri, &$kilitAtlananlar): bool {
                    if (!isset($onayKilitleri[$bpId])) {
                        return true;
                    }
                    $kilitAtlananlar[] = (string) $onayKilitleri[$bpId]['adi_soyadi'];
                    return false;
                }));
                if (empty($personel_ids)) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Hesaplanacak personel kalmadı.' . bordroKilitAtlamaMesaji($kilitAtlananlar),
                        'kilit_atlanan' => count($kilitAtlananlar),
                    ], JSON_UNESCAPED_UNICODE);
                    $SystemLog->logAction($userId, 'Maaş Hesaplama', 'Seçilen personellerin tamamı onay kilidi nedeniyle atlandı (' . count($kilitAtlananlar) . ' kişi).', SystemLogModel::LEVEL_IMPORTANT);
                    break;
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
                    (new PuantajMutabakatModel())->refreshRecalculationState(
                        (int) ($_SESSION['firma_id'] ?? 0),
                        $donem_id,
                        substr((string) $donem->baslangic_tarihi, 0, 7)
                    );
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

                $message = "$hesaplananSayisi personelin maaşı hesaplandı." . bordroKilitAtlamaMesaji($kilitAtlananlar);
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
                    'gorev_gecmisi_eksik' => $BordroPersonel->gorev_gecmisi_eksik,
                    'kilit_atlanan' => count($kilitAtlananlar)
                ]);

                $SystemLog->logAction($userId, 'Maaş Hesaplama', "$hesaplananSayisi personelin maaşı hesaplandı." . (count($kilitAtlananlar) ? ' Onay kilidi nedeniyle atlanan: ' . count($kilitAtlananlar) . '.' : ''), SystemLogModel::LEVEL_IMPORTANT);
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
                if ($personel && $donemBilgi) {
                    $personel = $BordroPersonel->overrideWithHistoricalGorevGecmisi($personel, $donemBilgi->baslangic_tarihi, $donemBilgi->bitis_tarihi);
                }

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
                if (empty($hesap['hesaplanmis'])) {
                    throw new Exception('Bu personelin bordrosu henüz hesaplanmadı. Önce Maaş Hesapla işlemini çalıştırın.');
                }
                $sozlesmeAlacagiOzeti = $BordroPersonel->getSozlesmeAlacagiKalemleri($hesap, $bp);
                $puantajHakedisiGosterim = 0.0;
                foreach (($sozlesmeAlacagiOzeti['kalemler'] ?? []) as $sozlesmeKalemi) {
                    if (($sozlesmeKalemi['etiket'] ?? '') === 'Puantaj Hakedişi') {
                        $puantajHakedisiGosterim = (float) ($sozlesmeKalemi['tutar'] ?? 0);
                        break;
                    }
                }
                $muhasebeFazlaMesaiOzeti = $BordroPersonel->getMuhasebeFazlaMesaiNetOzeti($bp, $donemBilgi);
                $bankaDetayDagilimi = $BordroPersonel->getBankaOdemeDetayDagilimi($hesap, $muhasebeFazlaMesaiOzeti);
                $mealDeduction = floatval($hesap['mealAllowanceDeduction'] ?? 0);
                $spouseDeduction = floatval($hesap['spouseAllowanceDeduction'] ?? 0);
                $isInclusive = (bool) $hesap['isInclusive'];
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
                    $contractHakedisForRounding = $puantajHakedisiGosterim;
                    
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
                $snapshotEkOdemeNetleri = [];
                foreach (($detayData['ek_odemeler'] ?? []) as $snapshotEkOdeme) {
                    if (isset($snapshotEkOdeme['id'], $snapshotEkOdeme['net_etki'])) {
                        $snapshotEkOdemeNetleri[(int) $snapshotEkOdeme['id']] = (float) $snapshotEkOdeme['net_etki'];
                    }
                }

                foreach ($tumEkOdemeler as $odeme) {
                    $parsedAdet = 0;
                    if (preg_match('/\((\d+)\s*Adet/i', $odeme->aciklama ?? '', $adetMatch)) { $parsedAdet = intval($adetMatch[1]); }
                    $aciklama = (string)($odeme->aciklama ?? '');
                    $odemeTurLower = mb_strtolower((string)($odeme->tur ?? ''), 'UTF-8');
                    
                    if ((!empty($bp->yemek_yardimi_dahil) || $isPrimUsulu || $isInclusive) && ($odemeTurLower === 'yemek_yardimi_tum' || $odemeTurLower === 'yemek' || strpos($odemeTurLower, 'yemek') !== false) && $odemeTurLower !== 'yemek_yardimi_dengeleme') { continue; }
                    if (($odemeTurLower === 'es_yardimi' || strpos($odemeTurLower, 'es_yardimi') !== false || strpos($odemeTurLower, 'aile') !== false)) { continue; }
                    if (($odeme->tur ?? '') === 'yuvarlama_farki' || $aciklama === 'Yuvarlama Farkı') { continue; }
                    
                    if (strpos($aciklama, '[Nöbet]') === 0) {
                        $nobetGosterim = clone $odeme;
                        $nobetGosterim->tutar = floatval($odeme->tutar);
                        $nobetOdemeler[] = $nobetGosterim;
                        $toplamNobetTutar += floatval($nobetGosterim->tutar);
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
                                if (isset($jedo['id']) && $jedo['id'] == $odeme->id) {
                                    $hesaplananTutar = floatval($jedo['hesaplanan_tutar'] ?? $jedo['tutar']);
                                    $hesaplananAdet = intval($jedo['gun_sayisi'] ?? $parsedAdet);
                                    break; 
                                }
                            }
                        }
                        if (in_array($odemeTurLower, ['fazla_mesai', 'mesai'], true)) {
                            if (($snapshotEkOdemeNetleri[(int) $odeme->id] ?? null) === 0.0) {
                                continue;
                            }
                            $nobetGosterim = clone $odeme;
                            $nobetGosterim->tutar = $hesaplananTutar;
                            $nobetGosterim->aciklama = preg_match('/\(([^()]*Saat[^()]*)\)/u', $aciklama, $saatDetay)
                                ? '[Nöbet] Puantaj Nöbeti (' . $saatDetay[1] . ')'
                                : '[Nöbet] Ek Nöbet';
                            $nobetOdemeler[] = $nobetGosterim;
                            $toplamNobetTutar += $hesaplananTutar;
                            continue;
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
                    $gosterimYuvarlamaFarki = round($yuvarlamaFarki, 2);
                    if (abs($gosterimYuvarlamaFarki) >= 0.01) {
                        $toplamYuvarlamaFarki = $gosterimYuvarlamaFarki;
                    }
                }

                $displayToplamAlacak = round($toplamAlacak, 2);
                $kesintiTutarOzet = ($isNetMaas || $isPrimUsulu)
                    ? round($guncelKesintiGosterim, 2)
                    : round($toplamYasalKesinti + $guncelKesintiGosterim, 2);
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
                        
                        $adet = 0; $birimFiyat = ''; $birimEtiket = 'Adet';
                        if (preg_match('/(\d+)\s*Gün\s*x\s*\d+(?:[\.,]\d+)?\s*Saat\s*x\s*([0-9\.,]+)\s*₺?/iu', $detayMetin, $detayMatch)) {
                            $adet = intval($detayMatch[1]);
                            $birimFiyat = trim($detayMatch[2]);
                            $birimEtiket = 'Gün';
                        } elseif (preg_match('/(\d+(?:[\.,]\d+)?)\s*Saat\s*x\s*([0-9\.,]+)\s*₺?/iu', $detayMetin, $detayMatch)) {
                            $adet = (float) str_replace(',', '.', $detayMatch[1]);
                            $birimFiyat = trim($detayMatch[2]);
                            $birimEtiket = 'Saat';
                        } elseif (preg_match('/(\d+)\s*Adet\s*x\s*([0-9\.,]+)\s*₺?/iu', $detayMetin, $detayMatch)) {
                            $adet = intval($detayMatch[1]); 
                            $birimFiyat = trim($detayMatch[2]);
                        } elseif (preg_match('/(\d+)\s*Adet\s*x\s*([0-9\.,]+)\s*₺?/iu', $aciklama, $detayMatch)) {
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
                        
                        $fiyatKey = $birimEtiket . '|' . ($birimFiyat !== '' ? $birimFiyat : '__unknown__');
                        if (!isset($gruplanmis[$groupKey]['fiyat_kirilim'][$fiyatKey])) {
                            $gruplanmis[$groupKey]['fiyat_kirilim'][$fiyatKey] = ['birim_fiyat' => $birimFiyat, 'birim_etiket' => $birimEtiket, 'adet' => 0, 'tutar' => 0];
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
                    ? ' <span class="badge bg-light text-dark fw-normal ms-2">' . $puantajToplamIslemSayisi . ' Adet</span>'
                    : '';

                // ============================================================
                // HTML GENERATION: UNIFIED 2-COLUMN VIEW (OLD/COMPACT STYLE)
                // ============================================================
                $html = '<style>
                    .bordro-compact-view { font-family: "Inter", system-ui, -apple-system, sans-serif; }
                    .bordro-compact-view .main-card { border-radius: 16px; border: 1.5px solid #cbd5e1; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
                    .bordro-compact-view .header-glass { background: #ffffff; border: 1.5px solid #cbd5e1; padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
                    .bordro-compact-view .card-header-tint { padding: 14px 20px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid #cbd5e1; }
                    .bordro-compact-view .calculation-toggle { cursor:pointer; user-select:none; }
                    .bordro-compact-view .calculation-toggle:hover { filter:brightness(.985); }
                    .bordro-compact-view .calculation-toggle .panel-chevron { transition:transform .25s ease; font-size:1.25rem; }
                    .bordro-compact-view .calculation-toggle[aria-expanded="true"] .panel-chevron { transform:rotate(180deg); }
                    .bordro-compact-view .layout-switch { min-height:38px; white-space:nowrap; border-color: #cbd5e1; }
                    @media (min-width:1200px) {
                        .bordro-compact-view.layout-side .bordro-detail-card-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)) minmax(220px,.62fr); gap:1rem; margin:0; align-items:stretch; transition: all 0.25s ease; }
                        .bordro-compact-view.layout-side .bordro-calculation-column,
                        .bordro-compact-view.layout-side .calculation-panels { display:contents; }
                        .bordro-compact-view.layout-side .bordro-calculation-panel,
                        .bordro-compact-view.layout-side .bordro-deduction-column,
                        .bordro-compact-view.layout-side .bordro-net-result-column { display:flex; width:auto !important; padding:0 !important; }
                        .bordro-compact-view.layout-side .bordro-calculation-panel > .main-card { display:flex; flex-direction:column; width:100%; }
                        .bordro-compact-view.layout-side .bordro-deduction-column > .main-card { width:100%; }
                        .bordro-compact-view.layout-side .bordro-main-calculation-collapse.show { display:flex !important; flex:1; flex-direction:column; }
                        .bordro-compact-view.layout-side .bordro-main-calculation-collapse.show > .unified-table { display:flex; flex:1; flex-direction:column; }
                        .bordro-compact-view.layout-side .bordro-main-calculation-collapse.show > .unified-table > tbody { display:flex; flex:1; flex-direction:column; }
                        .bordro-compact-view.layout-side .bordro-main-calculation-collapse.show > .unified-table > tbody > tr:not(.collapse),
                        .bordro-compact-view.layout-side .bordro-main-calculation-collapse.show > .unified-table > tbody > tr.collapse.show { display:table; width:100%; table-layout:fixed; }
                        .bordro-compact-view.layout-side .bordro-main-calculation-collapse.show > .unified-table > tbody > tr.footer-row { margin-top:auto; }
                        .bordro-compact-view.layout-side .bordro-net-result-column .net-bottom-banner { display:flex; width:100%; height:100%; margin-top:0; padding:20px; background:#14243e; color:#f1f5f9; border:1.5px solid #2c4263; box-shadow:0 5px 15px rgba(15,23,42,.15); position:relative; border-radius:16px; }
                        .bordro-compact-view.layout-side .bordro-net-result-column .net-bottom-banner > .row { width:100%; margin:0; flex-direction:column; align-items:stretch !important; }
                        .bordro-compact-view.layout-side .bordro-net-result-column .net-bottom-banner .col-md-5,
                        .bordro-compact-view.layout-side .bordro-net-result-column .net-bottom-banner .col-md-7 { width:100%; padding:0 !important; border:0 !important; text-align:left !important; }
                        .bordro-compact-view.layout-side .bordro-net-result-column .net-bottom-banner .col-md-5 { margin-bottom:14px !important; }
                        .bordro-compact-view.layout-side .bordro-net-result-column .net-bottom-banner .text-white-50 { color:#bdcbe0 !important; }
                        .bordro-compact-view.layout-side .bordro-net-result-column .net-bottom-banner .net-value-xl { font-size:1.9rem; text-shadow:none; }
                        .bordro-compact-view.layout-side .bordro-net-result-column .net-bottom-banner .col-6 { width:100%; }
                        .bordro-compact-view.layout-side .bordro-net-result-column .dist-badge { min-height:58px; padding:10px 12px; background:rgba(255,255,255,.06); color:#f1f5f9; border-color:rgba(255,255,255,.16); flex-direction:row; justify-content:flex-start; gap:10px; }
                        .bordro-compact-view.layout-side .bordro-net-result-column .dist-badge:hover { background:rgba(255,255,255,.1); transform:none; }
                        .bordro-compact-view.layout-side .bordro-net-result-column .dist-badge i { margin:0 !important; }
                        .bordro-compact-view.layout-side .bordro-net-result-column .dist-badge .text-white-50 { margin-left:auto; }

                        /* TAM SATIR NET MAAS MODU (net-fullwidth): 3 Kolon Üstte, Net Kartı Altta Tam Satır */
                        .bordro-compact-view.layout-side.net-fullwidth .bordro-detail-card-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
                        .bordro-compact-view.layout-side.net-fullwidth .bordro-net-result-column { grid-column: 1 / -1 !important; width: 100% !important; }
                        .bordro-compact-view.layout-side.net-fullwidth .bordro-net-result-column .net-bottom-banner { padding: 22px 28px !important; margin-top: 6px !important; }
                        .bordro-compact-view.layout-side.net-fullwidth .bordro-net-result-column .net-bottom-banner > .row { flex-direction: row !important; align-items: center !important; }
                        .bordro-compact-view.layout-side.net-fullwidth .bordro-net-result-column .net-bottom-banner .col-md-5 { width: 38% !important; margin-bottom: 0 !important; border-right: 1px solid rgba(255,255,255,0.14) !important; padding-right: 20px !important; }
                        .bordro-compact-view.layout-side.net-fullwidth .bordro-net-result-column .net-bottom-banner .col-md-7 { width: 62% !important; padding-left: 20px !important; }
                        .bordro-compact-view.layout-side.net-fullwidth .bordro-net-result-column .net-bottom-banner .col-6 { width: 25% !important; }
                        .bordro-compact-view.layout-side.net-fullwidth .bordro-net-result-column .dist-badge { flex-direction: column !important; justify-content: center !important; align-items: center !important; text-align: center; }
                        .bordro-compact-view.layout-side.net-fullwidth .bordro-net-result-column .dist-badge .text-white-50 { margin-left: 0 !important; }
                    }
                    .bordro-compact-view .btn-toggle-net-expand {
                        position: absolute; top: 12px; right: 12px; width: 30px; height: 30px;
                        padding: 0; display: inline-flex; align-items: center; justify-content: center;
                        border-radius: 8px; background: rgba(255, 255, 255, 0.85); border: 1.5px solid #cbd5e1;
                        color: #475569; cursor: pointer; transition: all 0.2s ease; z-index: 10;
                    }
                    .bordro-compact-view .btn-toggle-net-expand:hover {
                        background: #ffffff; color: #0f172a; border-color: #94a3b8; transform: scale(1.05);
                    }
                    .bordro-compact-view .tint-hakedis { background: linear-gradient(to right, #f0fdf4, #ffffff); color: #166534; }
                    .bordro-compact-view .tint-resmi { background: linear-gradient(to right, #eff6ff, #ffffff); color: #1d4ed8; }
                    .bordro-compact-view .calculation-note { display:block; margin-top:3px; font-size:.72rem; font-weight:500; color:#94a3b8; }
                    .bordro-compact-view .section-row td { padding-top:10px; padding-bottom:8px; background:#f8fafc; color:#64748b; font-size:.72rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; border-bottom: 1px solid #cbd5e1; }
                    .bordro-compact-view .unified-table { width: 100%; margin-bottom: 0; border-collapse: separate; border-spacing: 0; }
                    .bordro-compact-view .unified-table td { padding: 12px 20px; vertical-align: middle; border-bottom: 1px solid #e2e8f0; font-size: 0.92rem; }
                    .bordro-compact-view .unified-table .parent-row { cursor: pointer; font-weight: 600; background: white; transition: all 0.2s ease; }
                    .bordro-compact-view .unified-table .parent-row:hover { background: #f8fafc; }
                    .bordro-compact-view .unified-table .child-row { background: #fafbfc; font-size: 0.85rem; color: #64748b; }
                    .bordro-compact-view .unified-table .child-row td { border-bottom-color: #e2e8f0; padding-top: 8px; padding-bottom: 8px; }
                    .bordro-compact-view .unified-table .footer-row { background: #f8fafc; font-weight: 800; font-size: 1.05rem; }
                    .bordro-compact-view .unified-table .footer-row td { border-bottom: none; border-top: 1.5px solid #cbd5e1; padding: 16px 20px; }
                    .bordro-compact-view .net-bottom-banner { 
                        background: #14243e;
                        color: white; border-radius: 16px; padding: 30px; margin-top: 25px; 
                        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.15);
                        position: relative;
                        border: 1.5px solid #2c4263;
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

                    /* macOS Dark Uyumu */
                    html[data-theme-preset="macos-dark"] .bordro-compact-view .main-card {
                        background: rgba(30, 32, 40, 0.6) !important;
                        border: 1px solid rgba(255, 255, 255, 0.16) !important;
                        backdrop-filter: blur(20px);
                    }
                    html[data-theme-preset="macos-dark"] .bordro-compact-view .header-glass {
                        background: rgba(255, 255, 255, 0.04) !important;
                        border: 1px solid rgba(255, 255, 255, 0.16) !important;
                        backdrop-filter: blur(16px);
                    }
                    html[data-theme-preset="macos-dark"] .bordro-compact-view .card-header-tint {
                        border-bottom-color: rgba(255, 255, 255, 0.14) !important;
                    }
                    html[data-theme-preset="macos-dark"] .bordro-compact-view .header-glass h5 {
                        color: #ffffff !important;
                    }
                    html[data-theme-preset="macos-dark"] .bordro-compact-view .unified-table td {
                        border-bottom-color: rgba(255, 255, 255, 0.1) !important;
                        color: #e2e8f0 !important;
                    }
                    html[data-theme-preset="macos-dark"] .bordro-compact-view .unified-table .parent-row {
                        background: rgba(30, 32, 40, 0.4) !important;
                        color: #f1f5f9 !important;
                    }
                    html[data-theme-preset="macos-dark"] .bordro-compact-view .unified-table .parent-row:hover {
                        background: rgba(255, 255, 255, 0.06) !important;
                    }
                    html[data-theme-preset="macos-dark"] .bordro-compact-view .unified-table .child-row {
                        background: rgba(20, 22, 28, 0.4) !important;
                        color: #94a3b8 !important;
                    }
                    html[data-theme-preset="macos-dark"] .bordro-compact-view .unified-table .footer-row {
                        background: rgba(255, 255, 255, 0.04) !important;
                        color: #ffffff !important;
                    }
                    html[data-theme-preset="macos-dark"] .bordro-compact-view .unified-table .footer-row td {
                        border-top-color: rgba(255, 255, 255, 0.14) !important;
                    }
                    html[data-theme-preset="macos-dark"] .bordro-compact-view .btn-toggle-net-expand {
                        background: rgba(255, 255, 255, 0.1) !important;
                        border-color: rgba(255, 255, 255, 0.18) !important;
                        color: #f1f5f9 !important;
                    }
                    html[data-theme-preset="macos-dark"] .bordro-compact-view .btn-toggle-net-expand:hover {
                        background: rgba(255, 255, 255, 0.2) !important;
                        color: #ffffff !important;
                    }
                    @media (min-width:1200px) {
                        html[data-theme-preset="macos-dark"] .bordro-compact-view.layout-side .bordro-net-result-column .net-bottom-banner {
                            background:#14243e; color:#f1f5f9; border-color:#2c4263;
                        }
                        html[data-theme-preset="macos-dark"] .bordro-compact-view.layout-side .bordro-net-result-column .dist-badge {
                            background:rgba(255,255,255,.04); color:#f1f5f9; border-color:rgba(255,255,255,.08);
                        }
                        html[data-theme-preset="macos-dark"] .bordro-compact-view.layout-side.net-fullwidth .bordro-net-result-column .net-bottom-banner .col-md-5 {
                            border-right-color: rgba(255, 255, 255, 0.14) !important;
                        }
                    }
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
                            <button type="button" class="btn btn-outline-secondary bg-white layout-switch btn-toggle-old-detail-layout" aria-pressed="false" title="Hakediş kartlarının yerleşimini değiştir">
                                <i class="bx bx-columns me-1"></i><span class="layout-switch-label">Alt Alta</span>
                            </button>
                            <div class="badge bg-light text-dark border py-2 px-3 d-flex flex-column align-items-end">
                                <small class="text-muted opacity-75" style="font-size: 10px;">GÜNLÜK ÜCRET</small>
                                <span class="fw-bold text-primary">' . ($gunlukUcret > 0 ? number_format($gunlukUcret, 2, ',', '.') . ' ₺' : '-') . '</span>
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
                                <small class="text-muted opacity-75" style="font-size: 10px;">' . ($isPrimUsulu ? 'PUANTAJ HAKEDİŞİ' : 'SÖZLEŞME HAKEDİŞİ') . '</small>
                                <span class="fw-bold text-primary">' . number_format($contractHakedisForRounding, 2, ',', '.') . ' ₺</span>
                            </div>
                          </div>';
                $html .= '</div>';

                // 2. MAIN ROW: 2 COLS
                $html .= '<div class="row g-3 bordro-detail-card-grid">';
                $html .= '<div class="col-lg-8 bordro-calculation-column"><div class="row g-3 calculation-panels">';

                $topRowValue = $modalBaseRowValue;
                $topRowLabel = "Asgari Ücret Hakedişi";

                // --- COLUMN 1: PERSONELIN KENDI ALACAGI ---
                $kendiAlacakPanelId = 'kendiAlacakPanel_' . intval($bp->id);
                $html .= '<div class="col-12 bordro-calculation-panel">';
                $html .= '<div class="main-card bg-white">';
                $html .= '<div class="card-header-tint tint-hakedis calculation-toggle" role="button" tabindex="0" data-collapse-target="#' . $kendiAlacakPanelId . '" aria-expanded="false" aria-controls="' . $kendiAlacakPanelId . '">
                            <span><i class="bx bx-calculator me-2"></i>SÖZLEŞME ALACAĞI HESABI <small class="d-block opacity-75 mt-1">Resmî + gayriresmî, kesinti öncesi</small></span>
                            <span class="d-flex align-items-center gap-2"><span class="badge rounded-pill bg-success">' . number_format($sozlesmeAlacagiOzeti['toplam'] ?? 0, 2, ',', '.') . ' ₺</span><i class="bx bx-chevron-down panel-chevron"></i></span>
                          </div>';
                $html .= '<div class="collapse bordro-main-calculation-collapse" data-panel-key="contract" id="' . $kendiAlacakPanelId . '"><table class="unified-table"><tbody>';
                
                $collBaseId = "cBaseHakedis_" . $bp->id;
                $asgariMatrarhGoster = false;
                $puantajIslemSatirlariHtml = '';
                if ($isPrimUsulu && !empty($puantajGruplu)) {
                    foreach ($puantajGruplu as $grup) {
                        foreach ($grup['fiyat_kirilim'] as $kirilim) {
                            $detStr = $kirilim['adet'] > 0 ? $kirilim['adet'] . ' ' . ($kirilim['birim_etiket'] ?? 'Adet') : '';
                            $birim = $kirilim['birim_fiyat'] !== '' ? ' × ' . $kirilim['birim_fiyat'] . ' ₺' : '';
                            $puantajIslemSatirlariHtml .= '<tr class="child-row collapse ' . $collBaseId . '">
                                        <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>' . htmlspecialchars($grup['ana'], ENT_QUOTES, 'UTF-8') . ' <small class="text-muted">' . htmlspecialchars($detStr . $birim, ENT_QUOTES, 'UTF-8') . '</small></td>
                                        <td class="text-end pe-4">' . number_format($kirilim['tutar'], 2, ',', '.') . ' ₺</td>
                                      </tr>';
                        }
                    }
                }

                if ($isInclusive) {
                    $sozlesmeHakedisToplamGosterim = $isPrimUsulu
                        ? round($contractHakedisForRounding, 2)
                        : ($contractHakedisForRounding > 0
                            ? round($contractHakedisForRounding, 2)
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

                    if ($sozlesmeHakedisToplamGosterim > 0 || $isPrimUsulu) {
                        $anaHakedisEtiketi = $isPrimUsulu ? 'Puantaj Hakedişi' : 'Sözleşme Hakedişi';
                        $anaHakedisIkonu = $isPrimUsulu ? 'bx-briefcase' : 'bx-file';
                        $anaHakedisAciklamasi = $isPrimUsulu
                            ? 'Dönem puantaj kazancı'
                            : number_format($gunlukUcret, 2, ',', '.') . ' ₺ × ' . $calismaGunu . ' gün';
                        $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collBaseId . '" aria-expanded="false">
                                    <td><div class="d-flex align-items-center"><i class="bx ' . $anaHakedisIkonu . ' me-2 text-dark opacity-75"></i><span>' . $anaHakedisEtiketi . '<span class="calculation-note">' . $anaHakedisAciklamasi . '</span></span>' . ($isPrimUsulu ? $puantajBaslikDetay : '') . '<span class="badge bg-light text-dark fw-normal ms-2">' . $calismaGunu . ' Gün</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                    <td class="text-end fw-bold text-dark">' . number_format($sozlesmeHakedisToplamGosterim, 2, ',', '.') . ' ₺</td>
                                  </tr>';

                        if ($sozlesmeTabanGosterim > 0 && !$asgariMatrarhGoster) {
                            $html .= '<tr class="child-row collapse ' . $collBaseId . '">
                                        <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Asgari Ücret Tabanı</td>
                                        <td class="text-end pe-4">' . number_format($sozlesmeTabanGosterim, 2, ',', '.') . ' ₺</td>
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
                        if ($ucretsizIzinGunu > 0) {
                            $html .= '<tr class="child-row collapse ' . $collBaseId . '">
                                        <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Ücretsiz İzin</td>
                                        <td class="text-end pe-4 text-warning">-' . $ucretsizIzinGunu . ' Gün</td>
                                      </tr>';
                        }
                        $html .= $puantajIslemSatirlariHtml;
                    }
                } else {
                    if ($isPrimUsulu) {
                        $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collBaseId . '" aria-expanded="false">
                                    <td><div class="d-flex align-items-center"><i class="bx bx-briefcase me-2 text-dark opacity-75"></i><span>Puantaj Hakedişi<span class="calculation-note">Dönem puantaj kazancı</span></span>' . $puantajBaslikDetay . '<span class="badge bg-light text-dark fw-normal ms-2">' . $calismaGunu . ' Gün</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                    <td class="text-end fw-bold text-dark">' . number_format($contractHakedisForRounding, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    } else if ($modalBaseRowValue > 0) {
                        $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collBaseId . '" aria-expanded="false">
                                    <td><div class="d-flex align-items-center"><i class="bx bx-receipt me-2 text-muted opacity-75"></i><span>' . $topRowLabel . '<span class="calculation-note">' . number_format($asgariUcretNet / 30, 2, ',', '.') . ' ₺ × ' . $calismaGunu . ' gün</span></span><span class="badge bg-light text-dark fw-normal ms-2">' . $calismaGunu . ' Gün</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                    <td class="text-end fw-bold text-dark">' . number_format($topRowValue, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                    if ($ucretsizIzinGunu > 0) {
                         $html .= '<tr class="child-row collapse ' . $collBaseId . '">
                                    <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Ücretsiz İzin</td>
                                    <td class="text-end pe-4 text-warning">-' . $ucretsizIzinGunu . ' Gün</td>
                                  </tr>';
                    }
                    $html .= $puantajIslemSatirlariHtml;
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

                // Prim usulünde gerçek puantaj toplamı yukarıdaki ana satırda gösterilir;
                // aynı kazancı ikinci kez ek ödeme satırı olarak yazma.
                if (!empty($puantajOdemeler) && !$isPrimUsulu) {
                    $collId = "colPuantaj_" . $bp->id;
                    $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collId . '" aria-expanded="false">
                                <td><div class="d-flex align-items-center"><i class="bx bx-briefcase me-2 text-success"></i><span>Puantaj Hakedişleri</span>' . $puantajBaslikDetay . '<i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                <td class="text-end text-success fw-bold">+' . number_format($toplamPuantajTutar, 2, ',', '.') . ' ₺</td>
                              </tr>';
                    foreach ($puantajGruplu as $grup) {
                        foreach ($grup['fiyat_kirilim'] as $kirilim) {
                            $detStr = $kirilim['adet'] > 0 ? $kirilim['adet'] . ' ' . ($kirilim['birim_etiket'] ?? 'Adet') : '';
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
                    $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collId . '" aria-expanded="true">
                                <td><div class="d-flex align-items-center"><i class="bx bx-time-five me-2 text-success"></i><span>Nöbet Ödemeleri</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                <td class="text-end text-success fw-bold">+' . number_format($toplamNobetTutar, 2, ',', '.') . ' ₺</td>
                              </tr>';
                    foreach ($nobetGruplu as $grup) {
                        foreach ($grup['fiyat_kirilim'] as $kirilim) {
                            $detStr = $kirilim['adet'] > 0 ? $kirilim['adet'] . ' ' . ($kirilim['birim_etiket'] ?? 'Adet') : '';
                            $birim = $kirilim['birim_fiyat'] !== '' ? ' x ' . $kirilim['birim_fiyat'] . ' ₺' : '';
                            $html .= '<tr class="child-row collapse show ' . $collId . '">
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
                            $detStr = $kirilim['adet'] > 0 ? $kirilim['adet'] . ' ' . ($kirilim['birim_etiket'] ?? 'Adet') : '';
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
                    $turParametre = $BordroParametre->getByKod((string) $tur, $donemBaslangicTarihi);
                    $turE = trim((string) ($turParametre->etiket ?? ''));
                    if ($turE === '') {
                        $turE = $ekOdemeTurEtiketleri[$tur] ?? ucfirst(str_replace('_', ' ', $tur));
                    }
                    $cId = "cEx_" . md5($tur);
                    $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $cId . '" aria-expanded="false">
                                <td><div class="d-flex align-items-center"><i class="bx bx-gift me-2 text-success"></i><span>' . htmlspecialchars($turE) . '</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                <td class="text-end text-success fw-bold">+' . number_format($edata['toplam'], 2, ',', '.') . ' ₺</td>
                              </tr>';
                    foreach ($edata['items'] as $it) {
                         $detayAciklama = (string) ($it->aciklama ?? '');
                         if ((string) $tur === 'hafta_ici_nobet'
                             && preg_match('/(\d+)\s*Gün\s*x\s*\d+\s*Saat\s*x\s*([0-9\.,]+)\s*₺?/iu', $detayAciklama, $nobetDetayEslesme)) {
                             $detayAciklama = intval($nobetDetayEslesme[1]) . ' Gün × ' . trim($nobetDetayEslesme[2]) . ' ₺';
                         }
                         $html .= '<tr class="child-row collapse ' . $cId . '">
                                    <td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>' . htmlspecialchars($detayAciklama, ENT_QUOTES, 'UTF-8') . '</td>
                                    <td class="text-end pe-4">+' . number_format($it->tutar, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                }

                // RTÇ / HTÇ satırları
                if ($htcGunModal > 0 && $nominalMaas > 0) {
                    $htcToplamTutar = round($nominalMaas / 30 * $htcGunModal, 2);
                    $collHtc = "cHTC_" . $bp->id;
                    $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collHtc . '" aria-expanded="false">
                                <td><div class="d-flex align-items-center"><i class="bx bx-calendar-x me-2 text-purple" style="color:#7367f0"></i><span>Hafta Tatili Çalışma<span class="calculation-note">' . number_format($gunlukUcret, 2, ',', '.') . ' ₺ × ' . $htcGunModal . ' gün</span></span><span class="badge fw-normal ms-2" style="background:#7367f0;color:#fff">' . $htcGunModal . ' Gün</span><i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i></div></td>
                                <td class="text-end fw-semibold" style="color:#7367f0">+' . number_format($htcToplamTutar, 2, ',', '.') . ' ₺</td>
                              </tr>';
                }

                if ($toplamYuvarlamaFarki != 0) {
                    $html .= '<tr class="parent-row">
                                <td><i class="bx bx-infinite me-2 text-muted opacity-75"></i>Yuvarlama Farkı</td>
                                <td class="text-end ' . ($toplamYuvarlamaFarki > 0 ? 'text-success' : 'text-danger') . '">' . ($toplamYuvarlamaFarki > 0 ? '+' : '') . number_format($toplamYuvarlamaFarki, 2, ',', '.') . ' ₺</td>
                              </tr>';
                }

                $html .= '<tr class="footer-row">
                            <td class="text-success fw-bold">KESİNTİ ÖNCESİ TOPLAM ALACAĞI</td>
                            <td class="text-end text-success fw-bolder">' . number_format($sozlesmeAlacagiOzeti['toplam'] ?? 0, 2, ',', '.') . ' ₺</td>
                          </tr>';
                $html .= '</tbody></table></div></div></div>';


                // --- COLUMN 2: RESMI ALACAK / BANKA HESABI ---
                $resmiAlacakPanelId = 'resmiAlacakPanel_' . intval($bp->id);
                $html .= '<div class="col-12 bordro-calculation-panel">';
                $html .= '<div class="main-card bg-white">';
                $html .= '<div class="card-header-tint tint-resmi calculation-toggle" role="button" tabindex="0" data-collapse-target="#' . $resmiAlacakPanelId . '" aria-expanded="false" aria-controls="' . $resmiAlacakPanelId . '">
                            <span><i class="bx bx-building-house me-2"></i>RESMÎ ALACAĞI HESABI <small class="d-block opacity-75 mt-1">Kesinti öncesi resmî hakediş</small></span>
                            <span class="d-flex align-items-center gap-2"><span class="badge rounded-pill bg-primary">' . number_format($bankaDetayDagilimi['brut_banka'], 2, ',', '.') . ' ₺</span><i class="bx bx-chevron-down panel-chevron"></i></span>
                          </div>';
                $html .= '<div class="collapse bordro-main-calculation-collapse" data-panel-key="official" id="' . $resmiAlacakPanelId . '"><table class="unified-table"><tbody>';
                $html .= '<tr class="section-row"><td colspan="2">Eklenenler</td></tr>';
                $html .= '<tr class="parent-row">
                            <td><i class="bx bx-calendar-check me-2 text-primary"></i>Asgari Ücret (Net)<span class="calculation-note">' . number_format($bankaDetayDagilimi['asgari_gunluk'], 2, ',', '.') . ' ₺ × ' . intval($bankaDetayDagilimi['asgari_gun']) . ' gün</span></td>
                            <td class="text-end fw-bold">' . number_format($bankaDetayDagilimi['asgari'], 2, ',', '.') . ' ₺</td>
                          </tr>';
                if ($bankaDetayDagilimi['yemek'] > 0) {
                    $yemekGunlukGosterim = $includedAllowanceFiiliGun > 0 ? round($bankaDetayDagilimi['yemek'] / $includedAllowanceFiiliGun, 2) : 0;
                    $html .= '<tr class="parent-row"><td><i class="bx bx-restaurant me-2 text-success"></i>Yemek Yardımı<span class="calculation-note">' . ($includedAllowanceFiiliGun > 0 ? number_format($yemekGunlukGosterim, 2, ',', '.') . ' ₺ × ' . $includedAllowanceFiiliGun . ' fiilî gün' : 'Resmî banka kalemi') . '</span></td><td class="text-end text-success fw-bold">+' . number_format($bankaDetayDagilimi['yemek'], 2, ',', '.') . ' ₺</td></tr>';
                }
                if ($bankaDetayDagilimi['es_yardimi'] > 0) {
                    $html .= '<tr class="parent-row"><td><i class="bx bx-group me-2 text-success"></i>Eş Yardımı</td><td class="text-end text-success fw-bold">+' . number_format($bankaDetayDagilimi['es_yardimi'], 2, ',', '.') . ' ₺</td></tr>';
                }
                foreach ($bankaDetayDagilimi['ek_odemeler'] as $bed) {
                    if ($bed['tutar'] > 0) {
                        $bedEtiket = (string) ($bed['etiket'] ?? 'Ek Ödeme');
                        $bedEtiketLower = mb_strtolower($bedEtiket, 'UTF-8');
                        $isFazlaMesai = strpos($bedEtiketLower, 'fazla mesai') !== false || strpos($bedEtiketLower, 'hafta içi') !== false;
                        $isHtc = strpos($bedEtiketLower, 'hafta tatili') !== false;
                        $detayAcilebilir = ($isFazlaMesai && floatval($muhasebeFazlaMesaiOzeti['saat'] ?? 0) > 0) || ($isHtc && $htcGunModal > 0);
                        $calismaDetayId = 'resmiCalismaDetay_' . intval($bp->id) . '_' . md5($bedEtiket);
                        $html .= '<tr class="parent-row"' . ($detayAcilebilir ? ' data-bs-toggle="collapse" data-bs-target=".' . $calismaDetayId . '" aria-expanded="false"' : '') . '><td><i class="bx bx-plus-circle me-2 text-success"></i>' . htmlspecialchars($bedEtiket, ENT_QUOTES, 'UTF-8') . ($detayAcilebilir ? '<i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i>' : '') . '</td><td class="text-end text-success fw-bold">+' . number_format($bed['tutar'], 2, ',', '.') . ' ₺</td></tr>';
                        if ($detayAcilebilir) {
                            if ($isFazlaMesai) {
                                $mesaiSaat = floatval($muhasebeFazlaMesaiOzeti['saat'] ?? 0);
                                $saatlikNet = $mesaiSaat > 0 ? round(floatval($bed['tutar']) / $mesaiSaat, 2) : 0.0;
                                $normalSaatlik = round(floatval($asgariUcretNet) / 225, 2);
                                $artirimliNotu = ($normalSaatlik > 0 && abs($saatlikNet - round($normalSaatlik * 1.5, 2)) < 0.05)
                                    ? ' (' . number_format($normalSaatlik, 2, ',', '.') . ' ₺ × 1,5)'
                                    : '';
                                $html .= '<tr class="child-row collapse ' . $calismaDetayId . '"><td class="ps-4"><i class="bx bx-time-five me-1 opacity-50"></i>Toplam Fazla Mesai<span class="calculation-note">' . number_format($saatlikNet, 2, ',', '.') . ' ₺ × ' . number_format($mesaiSaat, 2, ',', '.') . ' saat' . $artirimliNotu . '</span></td><td class="text-end pe-4 fw-semibold">' . number_format($mesaiSaat, 2, ',', '.') . ' Saat</td></tr>';
                                foreach ([
                                    ['Nöbetten Gelen', 'nobet_saat', 'nobet_tutar'],
                                    ['Puantaj Fazla Mesaisi', 'puantaj_saat', 'puantaj_tutar'],
                                ] as [$kaynakEtiket, $kaynakSaatKey, $kaynakTutarKey]) {
                                    $kaynakSaat = floatval($muhasebeFazlaMesaiOzeti[$kaynakSaatKey] ?? 0);
                                    if ($kaynakSaat > 0) {
                                        $html .= '<tr class="child-row collapse ' . $calismaDetayId . '"><td class="ps-4"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>' . $kaynakEtiket . '<span class="calculation-note">' . number_format($kaynakSaat, 2, ',', '.') . ' Saat</span></td><td class="text-end pe-4">+' . number_format(floatval($muhasebeFazlaMesaiOzeti[$kaynakTutarKey] ?? 0), 2, ',', '.') . ' ₺</td></tr>';
                                    }
                                }
                            } else {
                                $htcGunlukNet = $htcGunModal > 0 ? round(floatval($bed['tutar']) / $htcGunModal, 2) : 0.0;
                                $html .= '<tr class="child-row collapse ' . $calismaDetayId . '"><td class="ps-4"><i class="bx bx-calendar-check me-1 opacity-50"></i>Hafta Tatili Çalışması<span class="calculation-note">' . number_format($htcGunlukNet, 2, ',', '.') . ' ₺ × ' . $htcGunModal . ' gün</span></td><td class="text-end pe-4 fw-semibold">' . $htcGunModal . ' Gün</td></tr>';
                            }
                        }
                    }
                }
                $html .= '<tr class="footer-row"><td class="text-primary fw-bold">KESİNTİ ÖNCESİ RESMÎ ALACAK</td><td class="text-end text-primary fw-bolder">' . number_format($bankaDetayDagilimi['brut_banka'], 2, ',', '.') . ' ₺</td></tr>';

                $html .= '</tbody></table></div></div></div>';
                $html .= '</div></div>';

                // --- RIGHT COLUMN: KESINTILER ---
                $personelKesintiOzeti = $BordroPersonel->getPersonelKesintiOzeti($bp);
                $html .= '<div class="col-lg-4 bordro-deduction-column">';
                $html .= '<div class="main-card bg-white">';
                $html .= '<div class="card-header-tint" style="background:linear-gradient(to right,#fef2f2,#fff);color:#991b1b;">
                            <span><i class="bx bx-minus-circle me-2"></i>KESİNTİLER</span>
                            <span class="badge rounded-pill bg-danger">' . ($personelKesintiOzeti['toplam'] > 0 ? '-' : '') . number_format($personelKesintiOzeti['toplam'], 2, ',', '.') . ' ₺</span>
                          </div>';
                $html .= '<table class="unified-table"><tbody>';
                if (!empty($personelKesintiOzeti['kalemler'])) {
                    $kIndex = 0;
                    foreach ($personelKesintiOzeti['kalemler'] as $bankaKesinti) {
                        $kIndex++;
                        $kesintiAciklama = trim((string) ($bankaKesinti['aciklama'] ?? ''));
                        $kesintiEtiket = trim((string) ($bankaKesinti['etiket'] ?? ''));

                        if ($kesintiEtiket === 'Diğer banka kesintileri' && !empty($kesintilerGruplanmis)) {
                            $ilkGrup = reset($kesintilerGruplanmis);
                            if ($ilkGrup && !empty($ilkGrup->etiket)) {
                                $kesintiEtiket = $ilkGrup->etiket;
                            }
                        }
                        $collKesintiId = "colKesinti_" . $bp->id . "_" . $kIndex;
                        $resmiKesintiTutar = floatval($bankaKesinti['resmi_kesinti'] ?? 0);
                        $eldenKesintiTutar = floatval($bankaKesinti['elden_kesinti'] ?? 0);

                        $html .= '<tr class="parent-row" data-bs-toggle="collapse" data-bs-target=".' . $collKesintiId . '" aria-expanded="false" style="cursor:pointer;">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bx bx-minus-circle me-2 text-danger"></i>
                                            <span class="fw-semibold">' . htmlspecialchars($kesintiEtiket, ENT_QUOTES, 'UTF-8') . '</span>
                                            <i class="bx bx-chevron-down ms-1 text-muted rotate-icon"></i>
                                        </div>'
                                        . ($kesintiAciklama !== '' ? '<div class="calculation-note text-muted ms-4 mt-1" style="font-size:0.8rem; line-height:1.3;"><i class="bx bx-info-circle me-1 opacity-75"></i>' . htmlspecialchars($kesintiAciklama, ENT_QUOTES, 'UTF-8') . '</div>' : '') . '
                                    </td>
                                    <td class="text-end text-danger fw-bold align-middle">-' . number_format($bankaKesinti['tutar'], 2, ',', '.') . ' ₺</td>
                                  </tr>';

                        $html .= '<tr class="child-row collapse ' . $collKesintiId . '">
                                    <td class="ps-4 text-muted"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Resmî Tutardan Kesilen</td>
                                    <td class="text-end pe-4 text-danger fw-semibold">-' . number_format($resmiKesintiTutar, 2, ',', '.') . ' ₺</td>
                                  </tr>';

                        $html .= '<tr class="child-row collapse ' . $collKesintiId . '">
                                    <td class="ps-4 text-muted"><i class="bx bx-subdirectory-right me-1 opacity-50"></i>Elden Kesilen</td>
                                    <td class="text-end pe-4 text-warning fw-semibold">-' . number_format($eldenKesintiTutar, 2, ',', '.') . ' ₺</td>
                                  </tr>';
                    }
                } else {
                    $html .= '<tr><td colspan="2" class="text-center py-4 text-muted"><i class="bx bx-smile fs-4 d-block mb-1 opacity-50"></i>Kesinti bulunmuyor.</td></tr>';
                }
                $html .= '<tr class="footer-row"><td class="text-danger fw-bold">TOPLAM KESİNTİ</td><td class="text-end text-danger fw-bolder">' . ($personelKesintiOzeti['toplam'] > 0 ? '-' : '') . number_format($personelKesintiOzeti['toplam'], 2, ',', '.') . ' ₺</td></tr>';
                $html .= '</tbody></table></div></div>';

                // 3. BOTTOM HERO: NET SALARY + DISTRIBUTION
                $html .= '<div class="col-12 bordro-net-result-column"><div class="net-bottom-banner position-relative">';
                $html .= '<button type="button" class="btn btn-toggle-net-expand" id="btnToggleNetExpand" title="Altta tam satır yap" aria-label="Ödenecek net maaş kartını altta tam satır yap">
                            <svg class="net-expand-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition:transform .25s ease;">
                                <line x1="7" y1="7" x2="17" y2="17"></line>
                                <polyline points="17 7 17 17 7 17"></polyline>
                            </svg>
                          </button>';
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
                $html .= '</div></div></div>'; // Close Row + Banner + Result Column
                $html .= '</div>'; // Close Main Row (Main Columns)

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
                require __DIR__ . '/partials/detay-api.php';
                break;

            // Dönemi Kapat
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

                $mutabakatBlok = (new PuantajMutabakatModel())->closureBlockReason(
                    (int) ($_SESSION['firma_id'] ?? 0),
                    (string) $donem->baslangic_tarihi,
                    (string) $donem->bitis_tarihi
                );
                if ($mutabakatBlok !== null) {
                    echo json_encode([
                        'status' => 'validation_error',
                        'title' => 'Puantaj Mutabakatı Tamamlanmadı',
                        'message' => $mutabakatBlok,
                        'error_count' => 1,
                        'errors' => [['personel' => 'Dönem mutabakatı', 'detay' => $mutabakatBlok]],
                    ], JSON_UNESCAPED_UNICODE);
                    break;
                }

                // Mali/snapshot tutarsızlıkları hukuki kayıt bütünlüğü nedeniyle
                // bloklayıcıdır ve force_close ile geçilemez.
                $kapanisHatalari = array_merge(
                    $BordroPersonel->validateDonemKapanis(
                        $donem_id,
                        (int) ($_SESSION['firma_id'] ?? 0)
                    ),
                    (new \App\Model\BordroYayinModel())->onayliDokumMutabakatHatalari(
                        (int) ($_SESSION['firma_id'] ?? 0),
                        $donem_id
                    )
                );
                if (!empty($kapanisHatalari)) {
                    $hataSatirlari = array_map(static function (string $hata): array {
                        $parcalar = explode(':', $hata, 2);
                        return [
                            'personel' => trim($parcalar[0] ?? 'Bordro kaydı'),
                            'detay' => trim($parcalar[1] ?? $hata),
                        ];
                    }, $kapanisHatalari);
                    echo json_encode([
                        'status' => 'validation_error',
                        'title' => 'Bordro Mutabakatı Tamamlanmadı',
                        'message' => 'Dönemi kapatmadan önce aşağıdaki mali farkları düzeltin ve ilgili personelleri yeniden hesaplayın.',
                        'error_count' => count($hataSatirlari),
                        'errors' => $hataSatirlari,
                    ], JSON_UNESCAPED_UNICODE);
                    break;
                }

                // Döneme ait onaylanmamış avans ve izinleri kontrol et
                $warnings = [];

                // Onaylanmamış avansları kontrol et
                $avansQuery = $BordroDonem->getDb()->prepare("
                    SELECT COUNT(*) as count, SUM(pa.tutar) as toplam
                    FROM personel_avanslari pa
                    JOIN personel p ON pa.personel_id = p.id
                    WHERE pa.durum = 'beklemede' 
                    AND pa.silinme_tarihi IS NULL
                    AND p.silinme_tarihi IS NULL
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
                    SELECT COUNT(*) as count, SUM(DATEDIFF(pi.bitis_tarihi, pi.baslangic_tarihi) + 1) as toplam_gun
                    FROM personel_izinleri pi
                    JOIN personel p ON pi.personel_id = p.id
                    LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
                    WHERE pi.onay_durumu = 'beklemede' 
                    AND pi.silinme_tarihi IS NULL
                    AND p.silinme_tarihi IS NULL
                    AND p.firma_id = ?
                    AND (t.kisa_kod IS NULL OR (t.kisa_kod NOT IN ('X', 'x') AND (t.normal_mesai_sayilir IS NULL OR t.normal_mesai_sayilir = 0)))
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
                $arsivSonucu = (new BordroKapanisArsivModel())->arsivleVeKapat(
                    (int) ($_SESSION['firma_id'] ?? 0),
                    $donem_id,
                    (int) $userId,
                    (string) ($_SESSION['user_full_name'] ?? ($_SESSION['user']->adi_soyadi ?? 'Sistem'))
                );

                $message = 'Dönem kapatıldı ve değişmez bordro arşivi oluşturuldu (Sürüm ' . (int) $arsivSonucu['surum_no'] . ').';
                if (!empty($warnings)) {
                    $message .= ' (Uyarılar göz ardı edildi)';
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => $message,
                    'archive' => [
                        'version' => (int) $arsivSonucu['surum_no'],
                        'hash' => (string) $arsivSonucu['manifest_hash'],
                        'document_count' => (int) $arsivSonucu['dokum_sayisi'],
                    ],
                ]);

                $SystemLog->logAction($userId, 'Maaş Dönem Kapama', "Dönem kapatıldı ve arşivlendi (ID: $donem_id, Sürüm: {$arsivSonucu['surum_no']}, Hash: {$arsivSonucu['manifest_hash']}).", SystemLogModel::LEVEL_IMPORTANT);
                break;

            // Dönemi Aç
            case 'donem-ac':
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem.');
                }

                \App\Helper\BordroYayinGuvenlik::csrfDogrula();
                if (!$MenuModel->userCanAccessMenuLink($userId, 'bordro/list')) throw new Exception('Bordro yönetim yetkisi bulunamadı.');
                $acilisSonucu = (new \App\Model\BordroYayinModel())->yenidenAc((int) ($_SESSION['firma_id'] ?? 0), $donem_id, (int) $userId);
                $acilisMesaji = 'Dönem açıldı. Artık bu dönemde değişiklik yapılabilir.';
                if ($acilisSonucu['korunan'] > 0) {
                    $acilisMesaji .= ' ' . $acilisSonucu['korunan'] . ' personelin onaylı bordrosu kilitli tutuldu; yeniden hesaplamada atlanacak.';
                }
                if ($acilisSonucu['revize'] > 0) {
                    $acilisMesaji .= ' ' . $acilisSonucu['revize'] . ' onaylanmamış/itirazlı döküm revizyona alındı.';
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => $acilisMesaji,
                    'onay_kilidi' => $acilisSonucu,
                ], JSON_UNESCAPED_UNICODE);

                $SystemLog->logAction($userId, 'Maaş Dönem Açma', "Dönem tekrar açıldı (ID: $donem_id). Onay kilidiyle korunan: {$acilisSonucu['korunan']}, revizyona alınan döküm: {$acilisSonucu['revize']}.", SystemLogModel::LEVEL_IMPORTANT);
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
                (new BordroOnayKilidiModel())->dogrula($id);

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

                $BordroPersonel->saveAcikDonemManuelDagilim((int) ($_SESSION['firma_id'] ?? 0), $id, [$banka, $sodexo, $diger, $elden]);

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
                (new BordroOnayKilidiModel())->dogrula($id);

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

                $sqlIds = $BordroPersonel->getDb()->prepare("SELECT id FROM bordro_personel WHERE donem_id = ? AND silinme_tarihi IS NULL");
                $sqlIds->execute([$donem_id]);
                $onayKilitleri = (new BordroOnayKilidiModel())->donemKilitleri($donem_id);
                $ids = [];
                $kilitAtlananlar = [];
                foreach (array_map('intval', $sqlIds->fetchAll(PDO::FETCH_COLUMN)) as $bpId) {
                    if (isset($onayKilitleri[$bpId])) {
                        $kilitAtlananlar[] = (string) $onayKilitleri[$bpId]['adi_soyadi'];
                    } else {
                        $ids[] = $bpId;
                    }
                }

                $hesaplayanId = $_SESSION['user_id'] ?? 0;
                $hesaplayanAdSoyad = $_SESSION['user_full_name'] ?? 'Sistem';

                if ($ids) {
                    $resetSql = $BordroPersonel->getDb()->prepare("
                        UPDATE bordro_personel 
                        SET dagitim_manuel = 0, sodexo_manuel = 0, banka_odemesi = 0, sodexo_odemesi = 0, diger_odeme = 0, elden_odeme = 0
                        WHERE id = ? AND donem_id = ? AND silinme_tarihi IS NULL
                    ");
                    foreach ($ids as $id) {
                        $resetSql->execute([$id, $donem_id]);
                        $BordroPersonel->hesaplaMaas($id, $hesaplayanId, $hesaplayanAdSoyad);
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => count($ids) . ' personelin ödeme dağılımı varsayılana döndürüldü ve maaşı tekrar hesaplandı.' . bordroKilitAtlamaMesaji($kilitAtlananlar)
                ], JSON_UNESCAPED_UNICODE);
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
                (new BordroOnayKilidiModel())->dogrulaPersonel($personel_id, $donem_id);

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
                    if ($BordroPersonel->addEkOdeme($personel_id, $donem_id, $aciklama, $tutar, $tur, $tarih, $userId)) {
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
                $bankaMatrahinaEkle = isset($_POST['banka_matrahina_ekle']) ? intval($_POST['banka_matrahina_ekle']) : 1;

                if ($personel_id <= 0 || $donem_id <= 0) {
                    throw new Exception('Geçersiz personel veya dönem.');
                }

                // Dönem kapalı mı kontrolü
                $donem = $BordroDonem->getDonemById($donem_id);
                if ($donem && $donem->kapali_mi == 1) {
                    throw new Exception('Bu dönem kapatılmış. Kapalı dönemlere kesinti eklenemez.');
                }
                (new BordroOnayKilidiModel())->dogrulaPersonel($personel_id, $donem_id);

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
                        SET aciklama = ?, tutar = ?, tur = ?, tarih = ?, banka_matrahina_ekle = ?
                        WHERE id = ?
                    ");
                    if ($sql->execute([$aciklama, $tutar, $tur, $tarih, $bankaMatrahinaEkle, $id])) {
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
                    if ($BordroPersonel->addKesinti($personel_id, $donem_id, $aciklama, $tutar, $tur, 'onaylandi', null, $tarih, $userId, $bankaMatrahinaEkle)) {
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
                    BordroParametreModel::clearRequestCache();
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
                    BordroParametreModel::clearRequestCache();
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

                BordroParametreModel::clearRequestCache();
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
                        $excelKilitleri ??= bordroKilitliPersonelHaritasi((int) $donem_id);
                        if (isset($excelKilitleri[(int) $personel_id])) {
                            $excelKilitAtlanan[] = $excelKilitleri[(int) $personel_id];
                            continue;
                        }

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
                        'message' => "$eklenenSayisi personelin gelir kayıtları yüklendi ve maaşları hesaplandı." . bordroKilitAtlamaMesaji($excelKilitAtlanan ?? [])
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
                        $excelKilitleri ??= bordroKilitliPersonelHaritasi((int) $donem_id);
                        if (isset($excelKilitleri[(int) $personel_id])) {
                            $excelKilitAtlanan[] = $excelKilitleri[(int) $personel_id];
                            continue;
                        }

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
                        'message' => "$eklenenSayisi personelin kesinti kayıtları yüklendi ve maaşları hesaplandı." . bordroKilitAtlamaMesaji($excelKilitAtlanan ?? [])
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
                        $excelKilitleri ??= bordroKilitliPersonelHaritasi((int) $donem_id);
                        if (isset($excelKilitleri[(int) $personel_id])) {
                            $excelKilitAtlanan[] = $excelKilitleri[(int) $personel_id];
                            continue;
                        }

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
                        $BordroPersonel->saveAcikDonemManuelDagilim(
                            (int) ($_SESSION['firma_id'] ?? 0),
                            (int) $bp_id,
                            [$banka, $sodexo, $diger, $elden]
                        );
                        $guncellenenSayisi++;
                    }

                    echo json_encode([
                        'status' => 'success',
                        'message' => "$guncellenenSayisi personelin ödeme dağıtımları güncellendi." . bordroKilitAtlamaMesaji($excelKilitAtlanan ?? [])
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
                    AND personel_ids IS NOT NULL
                    AND (aciklama != 'Manuel Düşüm' OR aciklama IS NULL)
                    AND " . \App\Model\KacakKontrolModel::hakedisKosulu() . "
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

            // Tablo Gövdesi ve Özet Alanını Yeniden Render Et (sayfa yenilemesiz güncelleme)
            case 'tablo-yenile':
                $donem_id = intval($_POST['donem_id'] ?? 0);
                if ($donem_id <= 0) {
                    throw new Exception('Dönem bilgisi bulunamadı.');
                }

                $selectedDonem = $BordroDonem->getDonemById($donem_id);
                if (!$selectedDonem) {
                    throw new Exception('Dönem bulunamadı.');
                }

                $personeller = $BordroPersonel->getPersonellerByDonem($donem_id);
                $donemKapali = $selectedDonem->kapali_mi ?? 0;
                $selectedAy = date('m', strtotime($selectedDonem->baslangic_tarihi));
                $selectedYil = date('Y', strtotime($selectedDonem->baslangic_tarihi));

                include __DIR__ . '/partials/hesaplama.php';

                ob_start();
                include __DIR__ . '/partials/ozet-kartlar.php';
                $ozetHtml = ob_get_clean();

                ob_start();
                include __DIR__ . '/partials/tablo-satirlari.php';
                $tbodyHtml = ob_get_clean();

                echo json_encode([
                    'status' => 'success',
                    'ozet_html' => $ozetHtml,
                    'tbody_html' => $tbodyHtml,
                    'donem_adi' => $selectedDonem->donem_adi,
                    'donem_kapali' => (int) $donemKapali,
                    'personel_gorsun' => (int) ($selectedDonem->personel_gorsun ?? 0),
                    'personel_sayisi' => count($personeller)
                ]);
                break;

            // Yapay Zeka Destekli Bordro Denetimi ve Risk Analizi
            case 'ai-bordro-audit':
                $donem_id = intval($_POST['donem_id'] ?? 0);
                $compare_donem_id = !empty($_POST['compare_donem_id']) ? intval($_POST['compare_donem_id']) : null;
                $personel_id = !empty($_POST['personel_id']) ? intval($_POST['personel_id']) : null;
                $use_llm = isset($_POST['use_llm']) ? (bool)$_POST['use_llm'] : true;

                if ($donem_id <= 0) {
                    throw new Exception('Denetlenecek bordro dönemi seçilmedi.');
                }

                $auditService = new \App\Service\BordroAiAuditService();
                $auditResult = $auditService->auditDonem($donem_id, $personel_id, $use_llm, $compare_donem_id);

                if (!$auditResult['success']) {
                    throw new Exception($auditResult['message'] ?? 'Bordro denetimi gerçekleştirilemedi.');
                }

                $SystemLog->logAction(
                    $userId,
                    'Bordro AI Denetimi',
                    "Dönem ID: {$donem_id} için AI Bordro Denetimi çalıştırıldı. Sağlık Skoru: %{$auditResult['summary']['saglik_skoru']}",
                    \App\Model\SystemLogModel::LEVEL_INFO
                );

                echo json_encode([
                    'status' => 'success',
                    'data' => $auditResult
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
