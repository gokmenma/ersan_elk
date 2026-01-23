<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';


use App\Model\PersonelModel;
use App\Helper\Security;
use App\Helper\Helper;
use App\Helper\Date;
use App\Model\TanimlamalarModel;




$Tanimlamalar = new TanimlamalarModel();


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $Personel = new PersonelModel();

    if ($action == 'personel-kaydet') {
        try {

            // Form verilerini al
            $data = $_POST;
            $personel_id = $data['personel_id'];


            // Dosya Yükleme İşlemi
            if (isset($_FILES['resim_yolu'])) {
                // HATA AYIKLAMA LOGU
                $debugLog = dirname(__DIR__, 2) . '/debug_upload.txt';
                $logContent = "--- Upload Start ---\n";
                $logContent .= "FILES: " . print_r($_FILES, true) . "\n";

                if ($_FILES['resim_yolu']['error'] == 0) {
                    // Mutlak yol tanımlaması
                    $baseDir = dirname(__DIR__, 2); // c:\xampp\htdocs\ersan_elk

                    // Yol ayırıcılarını sisteme uygun hale getir
                    $uploadDir = $baseDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR;

                    $logContent .= "Upload Dir: " . $uploadDir . "\n";
                    $logContent .= "Writable: " . (is_writable($baseDir) ? 'Yes' : 'No') . "\n";

                    if (!file_exists($uploadDir)) {
                        if (!mkdir($uploadDir, 0777, true)) {
                            $logContent .= "Mkdir Failed\n";
                            file_put_contents($debugLog, $logContent, FILE_APPEND);
                            throw new Exception("Klasör oluşturulamadı: " . $uploadDir);
                        }
                    }

                    $fileExtension = pathinfo($_FILES['resim_yolu']['name'], PATHINFO_EXTENSION);
                    $fileName = uniqid('personel_') . '.' . $fileExtension;
                    $uploadPath = $uploadDir . $fileName;

                    $logContent .= "Target Path: " . $uploadPath . "\n";

                    if (move_uploaded_file($_FILES['resim_yolu']['tmp_name'], $uploadPath)) {
                        // Veritabanına kaydedilecek yol (URL formatında olmalı, ters eğik çizgi değil)
                        $data['resim_yolu'] = 'assets/images/users/' . $fileName;
                        $logContent .= "Move Success\n";
                    } else {
                        $error = error_get_last();
                        $logContent .= "Move Failed: " . ($error['message'] ?? 'Unknown') . "\n";
                        file_put_contents($debugLog, $logContent, FILE_APPEND);
                        throw new Exception("Dosya yüklenemedi. Hedef: " . $uploadPath . " Hata: " . ($error['message'] ?? 'Bilinmeyen hata'));
                    }
                } elseif ($_FILES['resim_yolu']['error'] != 4) { // 4 = Dosya seçilmedi hatası (bunu yoksay)
                    $logContent .= "Upload Error Code: " . $_FILES['resim_yolu']['error'] . "\n";
                    file_put_contents($debugLog, $logContent, FILE_APPEND);
                    // Diğer hataları raporla (Örn: boyut sınırı)
                    throw new Exception("Dosya yükleme hatası. Hata Kodu: " . $_FILES['resim_yolu']['error']);
                }

                file_put_contents($debugLog, $logContent, FILE_APPEND);
            }

            // Action alanını veritabanına kaydetmemek için çıkar
            unset($data['action']);
            unset($data['personel_id']);
            $data["id"] = $personel_id;
            $data["firma_id"] = $_SESSION['firma_id'];


            // Boş string değerleri null yap
            foreach ($data as $key => $value) {
                if ($value === '' && $key != "sifre") {
                    $data[$key] = null;
                }
                /** tarih ise formatını değiştir */
                if (strpos($key, 'tarih') !== false) {
                    $data[$key] = Date::Ymd($value);
                }

                //şifreyi hash ile kaydet (sadece boş değilse)
                if ($key == 'sifre' && !empty($value)) {
                    $data[$key] = password_hash($value, PASSWORD_DEFAULT);
                } else if ($key == 'sifre' && empty($value)) {
                    unset($data['sifre']);
                }

                /**Parasal tutarlar için money formatını kaldır */
                if (strpos($key, 'tutar') !== false || $key == 'gunluk_ucret') {
                    $data[$key] = Helper::formattedMoneyToNumber($value);
                }

                // Evet/Hayır -> 1/0 dönüşümü (tinyint alanlar için)
                if (in_array($key, ['bes_kesintisi_varmi', 'aktif_mi'])) {
                    if (mb_strtolower($value, 'UTF-8') == 'evet' || $value === '1' || $value === 1) {
                        $data[$key] = 1;
                    } elseif (mb_strtolower($value, 'UTF-8') == 'hayır' || mb_strtolower($value, 'UTF-8') == 'hayir' || $value === '0' || $value === 0) {
                        $data[$key] = 0;
                    }
                }
            }


            //echo json_encode($data); exit();

            // Ekip kodu kontrolü - Aynı ekip kodunda aktif personel var mı?
            $ekip_no = $data['ekip_no'] ?? null;
            $aktif_mi = $data['aktif_mi'] ?? 1;

            // Eğer ekip kodu varsa ve personel aktif olarak kaydediliyorsa kontrol et
            if (!empty($ekip_no) && $aktif_mi == 1) {
                $exclude_id = ($personel_id > 0) ? $personel_id : null;
                $mevcutPersonel = $Personel->getAktifPersonelByEkipNo($ekip_no, $exclude_id);

                if ($mevcutPersonel) {
                    throw new Exception("Bu ekip kodunda ({$ekip_no}) zaten aktif bir personel bulunmaktadır: {$mevcutPersonel->adi_soyadi}. Aynı ekip kodunda birden fazla aktif personel olamaz.");
                }
            }

            $Personel->saveWithAttr($data);

            if ($personel_id > 0) {
                // Güncelleme
                $message = "Personel başarıyla güncellendi.";
            } else {
                // Yeni Kayıt
                $message = "Personel başarıyla kaydedildi.";
            }

            echo json_encode(['status' => 'success', 'message' => $message, 'id' => $personel_id]);

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'personel-sil') {
        try {
            $id = $_POST['id'];
            $Personel->delete($id, false); // false: decrypt işlemi yapılmasın (id direkt geliyorsa)
            echo json_encode(['status' => 'success', 'message' => 'Personel başarıyla silindi.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'manual-gelir-ekle') {
        try {
            $data = $_POST;
            $PersonelEkOdemelerModel = new \App\Model\PersonelEkOdemelerModel();

            $saveData = [
                'personel_id' => $data['personel_id'],
                'donem_id' => $data['donem_id'],
                'tur' => $data['tur'] ?? 'diger',
                'aciklama' => $data['aciklama'],
                'tutar' => Helper::formattedMoneyToNumber($data['tutar']),
                'durum' => $data['durum'] ?? 'onaylandi',
                'tekrar_tipi' => 'tek_sefer',
                'aktif' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $PersonelEkOdemelerModel->saveWithAttr($saveData);
            echo json_encode(['status' => 'success', 'message' => 'Gelir başarıyla eklendi.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'manual-kesinti-ekle') {
        try {
            $data = $_POST;
            $PersonelKesintileriModel = new \App\Model\PersonelKesintileriModel();

            $saveData = [
                'personel_id' => $data['personel_id'],
                'donem_id' => $data['donem_id'],
                'tur' => $data['tur'] ?? 'diger',
                'aciklama' => $data['aciklama'],
                'tutar' => Helper::formattedMoneyToNumber($data['tutar']),
                'durum' => $data['durum'] ?? 'onaylandi',
                'tekrar_tipi' => 'tek_sefer',
                'aktif' => 1,
                'olusturma_tarihi' => date('Y-m-d H:i:s')
            ];

            $PersonelKesintileriModel->saveWithAttr($saveData);
            echo json_encode(['status' => 'success', 'message' => 'Kesinti başarıyla eklendi.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'get-details') {
        try {
            $id = $_POST['id'] ?? 0;
            $personel = $Personel->find($id);

            if ($personel) {
                // Resim yolu kontrolü
                if (empty($personel->resim_yolu)) {
                    $personel->resim_yolu = 'assets/images/users/user-dummy-img.jpg'; // Varsayılan resim
                }
                echo json_encode(['status' => 'success', 'data' => $personel]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Personel bulunamadı.']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'excel-upload') {
        try {
            // Composer Autoloader'ı dahil et
            $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (file_exists($vendorAutoload)) {
                require_once $vendorAutoload;
            } else {
                throw new Exception("Excel kütüphanesi (vendor/autoload.php) bulunamadı.");
            }

            if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] != 0) {
                throw new Exception("Dosya yüklenemedi veya dosya seçilmedi.");
            }

            $inputFileName = $_FILES['excel_file']['tmp_name'];

            // PhpSpreadsheet kontrolü
            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                throw new Exception("PhpSpreadsheet kütüphanesi yüklü değil.");
            }

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                throw new Exception("Excel dosyası boş veya sadece başlık satırı içeriyor.");
            }

            // Başlıkları al (1. satır) ve temizle
            $headers = array_map(function ($h) {
                // Türkçe karakter düzeltmesi ve küçük harfe çevirme
                $h = str_replace(['İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'], ['i', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], $h ?? '');
                return mb_strtolower(trim($h), 'UTF-8');
            }, $rows[0]);

            // Sütun eşleştirme haritası
            $columnMap = [
                'firma_id' => ['firma', 'firma adı', 'firma adi'],
                'tc_kimlik_no' => ['tc', 'tc kimlik', 'tc kimlik no', 'tckn', 'tc no', 'kimlik no', 'tc kimlik numarası'],
                'adi_soyadi' => ['ad soyad', 'adi soyadi', 'ad', 'isim', 'personel adı', 'ad ve soyad', 'adı soyadı'],
                'anne_adi' => ['anne adı', 'anne adi'],
                'baba_adi' => ['baba adı', 'baba adi'],
                'dogum_tarihi' => ['doğum tarihi', 'dogum tarihi', 'dt'],
                'dogum_yeri_il' => ['doğum yeri il', 'dogum yeri il'],
                'dogum_yeri_ilce' => ['doğum yeri ilçe', 'dogum yeri ilce'],
                'adres' => ['adres'],
                'cinsiyet' => ['cinsiyet'],
                'medeni_durum' => ['medeni durum'],
                'esi_calisiyor_mu' => ['eşi çalışıyor mu', 'esi calisiyor mu'],
                'seyahat_engeli' => ['seyahat engeli'],
                'ehliyet_sinifi' => ['ehliyet sınıfı', 'ehliyet sinifi'],
                'kan_grubu' => ['kan grubu'],
                'cep_telefonu' => ['telefon', 'cep telefonu', 'gsm', 'mobil', 'cep'],
                'sifre' => ['program şifresi', 'program sifresi', 'program şifre', 'program sifre', 'şifre', 'sifre', 'password'],
                'cep_telefonu_2' => ['2. cep telefonu', 'telefon 2'],
                'email_adresi' => ['email', 'e-posta', 'mail', 'eposta'],
                'ayakkabi_numarasi' => ['ayakkabı no', 'ayakkabi no'],
                'ust_beden_no' => ['üst beden no', 'ust beden no'],
                'alt_beden_no' => ['alt beden no'],
                'referans_adi_soyadi' => ['referans adı soyadı', 'referans adi soyadi'],
                'referans_telefonu' => ['referans telefonu'],
                'referans_firma' => ['referans firma'],
                'acil_kisi_adi_soyadi' => ['acil durum kişisi', 'acil kisi adi soyadi'],
                'acil_kisi_yakinlik' => ['acil durum yakınlık', 'acil kisi yakinlik'],
                'acil_kisi_telefonu' => ['acil durum telefonu', 'acil kisi telefonu'],
                'aktif_mi' => ['aktif mi', 'durum'],
                'ise_giris_tarihi' => ['işe giriş tarihi', 'ise giris tarihi'],
                'isten_cikis_tarihi' => ['işten çıkış tarihi', 'isten cikis tarihi'],
                'sgk_no' => ['sgk no'],
                'sgk_yapilan_firma' => ['sgk yapılan firma', 'sgk yapilan firma'],
                'personel_sinifi' => ['personel sınıfı', 'personel sinifi'],
                'departman' => ['departman', 'birim', 'bölüm'],
                'gorev' => ['görev', 'unvan', 'pozisyon'],
                'ekip_bolge' => ['ekip bölge', 'ekip bolge', 'bölge', 'bolge', 'bölge adı', 'bolge adi'],
                'ekip_no' => ['takım', 'takim', 'ekip no', 'ekip_no', 'ekip kodu', 'ekip kod'],
                'banka' => ['banka', 'banka adı', 'banka adi'],
                'iban_numarasi' => ['iban numarası', 'iban no', 'iban', 'iban numarasi', 'ıban numarası', 'ıban no', 'ıban', 'ıban numarasi'],
                'maas_durumu' => ['maaş durumu', 'maas durumu', 'maaş tipi', 'maas tipi'],
                'maas_tutari' => ['maaş tutarı', 'maas tutari', 'maaş'],
                'gunluk_ucret' => ['günlük ücret', 'gunluk ucret'],
                'bes_kesintisi_varmi' => ['bes kesintisi var mı?', 'bes kesintisi var mi?', 'bes kesintisi', 'bes']
            ];

            $colIndices = [];
            foreach ($columnMap as $dbCol => $possibleNames) {
                foreach ($possibleNames as $name) {
                    $index = array_search($name, $headers);
                    if ($index !== false) {
                        $colIndices[$dbCol] = $index;
                        break;
                    }
                }
            }

            if (!isset($colIndices['tc_kimlik_no'])) {
                throw new Exception("Zorunlu olan 'TC Kimlik No' sütunu Excel dosyasında bulunamadı. Lütfen sütun başlıklarını kontrol edin.");
            }

            // Firma ID'si için lookup hazırlığı (Firma Adı -> ID)
            $FirmaModel = new \App\Model\FirmaModel();
            $firmalar = $FirmaModel->option(); // id, firma_adi
            $firmaMap = [];
            foreach ($firmalar as $f) {
                // Türkçe karakter uyumlu küçük harfe çevirme
                $key = $f->firma_adi;
                $key = str_replace(['İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'], ['i', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], $key);
                $key = mb_strtolower($key, 'UTF-8');
                $firmaMap[trim($key)] = $f->id;
            }

            // Varsayılan Firma (Session'dan)
            $defaultFirmaId = $_SESSION['firma_id'] ?? $_SESSION['firma_id'] ?? null;

            $addedCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $errorDetails = [];

            // Verileri işle (2. satırdan başla)
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $rowNum = $i + 1;

                // Boş satır kontrolü (TC yoksa atla)
                $tcIndex = $colIndices['tc_kimlik_no'];
                $tcNo = isset($row[$tcIndex]) ? trim($row[$tcIndex]) : '';

                // İsim bilgisini al (Hata raporunda kullanmak için)
                $nameIndex = $colIndices['adi_soyadi'] ?? -1;
                $name = ($nameIndex >= 0 && isset($row[$nameIndex])) ? trim($row[$nameIndex]) : 'Bilinmeyen İsim';

                if (empty($tcNo))
                    continue;

                // TC Kimlik kontrolü (Veritabanında var mı?)
                $existing = $Personel->where('tc_kimlik_no', $tcNo);
                $isUpdate = false;
                $existingId = null;

                // Eğer kayıt varsa güncelleme moduna geç
                if (!empty($existing) && count($existing) > 0) {
                    $isUpdate = true;
                    $existingId = $existing[0]->id;
                }

                // Yeni kayıt verilerini hazırla
                $newData = [];
                $newData['tc_kimlik_no'] = $tcNo;

                if ($isUpdate) {
                    $newData['id'] = $existingId;
                }

                foreach ($colIndices as $dbCol => $index) {
                    if ($dbCol == 'tc_kimlik_no')
                        continue;

                    $val = isset($row[$index]) ? trim($row[$index]) : null;

                    // Tarih düzeltme
                    if (in_array($dbCol, ['dogum_tarihi', 'ise_giris_tarihi', 'isten_cikis_tarihi']) && !empty($val)) {
                        $val = Date::convertExcelDate($val);
                    }

                    // Firma ID Dönüşümü
                    if ($dbCol == 'firma_id') {
                        if (!empty($val)) {
                            $searchVal = str_replace(['İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'], ['i', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], $val);
                            $searchVal = mb_strtolower($searchVal, 'UTF-8');
                            $searchVal = trim($searchVal);

                            if (isset($firmaMap[$searchVal])) {
                                $val = $firmaMap[$searchVal];
                            } else {
                                $val = null;
                            }
                        }
                    }

                    /**Şifreyi hash ile kaydet */
                    if ($dbCol == 'sifre') {
                        if (!empty($val)) {
                            $val = password_hash($val, PASSWORD_DEFAULT);
                        }
                    }

                    // Para formatı düzeltme
                    if (in_array($dbCol, ['maas_tutari', 'gunluk_ucret']) && !empty($val)) {
                        $val = Helper::formattedMoneyToNumber($val);
                    }

                    // Evet/Hayır -> 1/0 dönüşümü (tinyint alanlar için)
                    if (in_array($dbCol, ['bes_kesintisi_varmi', 'aktif_mi'])) {
                        if (mb_strtolower($val, 'UTF-8') == 'evet' || $val === '1' || $val === 1) {
                            $val = 1;
                        } elseif (mb_strtolower($val, 'UTF-8') == 'hayır' || mb_strtolower($val, 'UTF-8') == 'hayir' || $val === '0' || $val === 0) {
                            $val = 0;
                        }
                    }

                    $newData[$dbCol] = $val;
                }

                // Firma ID Kontrolü ve Varsayılan Atama
                if (empty($newData['firma_id'])) {
                    if ($defaultFirmaId) {
                        $newData['firma_id'] = $defaultFirmaId;
                    }
                }

                // Varsayılan değerler
                if (!isset($newData['aktif_mi']))
                    $newData['aktif_mi'] = 1;

                // Ekip kodu işlemleri
                $ekipKodString = $newData['ekip_no'] ?? null;
                if (!empty($ekipKodString)) {
                    // Tanımlamalarda bu ekip kodu var mı?
                    $ekipKodRecord = $Tanimlamalar->getEkipKodId($ekipKodString);

                    if ($ekipKodRecord) {
                        $ekipId = $ekipKodRecord->id;
                    } else {
                        // Tanımlamalarda yoksa yeni ekle
                        $tanimData = [
                            'id' => 0,
                            'grup' => 'ekip_kodu',
                            'ekip_bolge' => $newData['ekip_bolge'],
                            'tur_adi' => $ekipKodString,
                            'aciklama' => "Personel Yükleme sırasında otomatik tanımlandı",
                            'firma_id' => $_SESSION['firma_id']
                        ];
                        $encId = $Tanimlamalar->saveWithAttr($tanimData);
                        $ekipId = Security::decrypt($encId);
                    }

                    // Personel verisine ID'yi ata
                    $newData['ekip_no'] = $ekipId;

                    // Bu ekip ID'sine sahip başka aktif personel var mı?
                    if ($newData['aktif_mi'] == 1) {
                        // Güncelleme ise mevcut ID'yi hariç tutarak kontrol et
                        $mevcutPersonel = $Personel->getAktifPersonelByEkipNo($ekipId, $existingId);
                        if ($mevcutPersonel) {
                            $skippedCount++;
                            $errorDetails[] = "Satır $rowNum ($name): Bu ekip kodunda ({$ekipKodString}) zaten aktif bir personel bulunmaktadır: {$mevcutPersonel->adi_soyadi}";
                            continue;
                        }
                    }
                }





                try {
                    $PersonelNew = new PersonelModel();
                    $PersonelNew->saveWithAttr($newData);

                    if ($isUpdate) {
                        $updatedCount++;
                    } else {
                        $addedCount++;
                    }
                } catch (Exception $e) {
                    $errorDetails[] = "Satır $rowNum ($name): Kayıt " . ($isUpdate ? "güncellenirken" : "eklenirken") . " hata oluştu - " . $e->getMessage();
                }
            }

            $responseMessage = "İşlem tamamlandı.\nBaşarıyla Eklenen: $addedCount\nBaşarıyla Güncellenen: $updatedCount";
            if ($skippedCount > 0 || count($errorDetails) > 0) {
                $totalErrors = count($errorDetails);
                $responseMessage .= "\nAtlanan/Hatalı: " . $totalErrors;
            }

            // Hata detaylarını da gönder
            echo json_encode([
                'status' => 'success',
                'message' => $responseMessage,
                'errors' => $errorDetails
            ]);

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'update-login-info') {
        try {
            $personel_id = $_POST['personel_id'] ?? 0;
            $sifre = $_POST['sifre'] ?? '';
            $sifre_confirm = $_POST['sifre_confirm'] ?? '';

            if (empty($sifre) || empty($sifre_confirm)) {
                throw new Exception("Şifre alanları boş bırakılamaz.");
            }

            if ($sifre !== $sifre_confirm) {
                throw new Exception("Şifreler eşleşmiyor.");
            }

            if (strlen($sifre) < 6) {
                throw new Exception("Şifre en az 6 karakter olmalıdır.");
            }

            $personel = $Personel->find($personel_id);
            if (!$personel) {
                throw new Exception("Personel bulunamadı.");
            }

            $hashed_password = password_hash($sifre, PASSWORD_DEFAULT);

            // Personel tablosunu güncelle
            $updateData = [
                'id' => $personel->id,
                'sifre' => $hashed_password
            ];

            $Personel->saveWithAttr($updateData);
            $message = "Personel giriş şifresi başarıyla güncellendi.";

            echo json_encode(['status' => 'success', 'message' => $message]);

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'get-ekip-kodlari-by-bolge') {
        try {
            $bolge = $_POST['bolge'] ?? '';
            $personel_id = $_POST['personel_id'] ?? 0;

            $PersonelModel = new PersonelModel();
            $personel = $personel_id > 0 ? $PersonelModel->find($personel_id) : null;
            $mevcutEkipNo = $personel->ekip_no ?? null;

            $Tanimlamalar = new TanimlamalarModel();
            $ekip_kodlari = $Tanimlamalar->getMusaitEkipKodlariByBolge($bolge, $mevcutEkipNo);

            echo json_encode(['status' => 'success', 'data' => $ekip_kodlari]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Geçersiz işlem.']);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $action = $_GET['action'] ?? '';
    $Personel = new PersonelModel();

    if ($action == 'export-puantaj') {
        try {
            $id = $_GET['id'] ?? 0;
            $personel = $Personel->find($id);
            if (!$personel) {
                throw new Exception("Personel bulunamadı.");
            }

            $ekip_no = $personel->ekip_no ?? '';
            $ise_giris = $personel->ise_giris_tarihi ?? '';
            $isten_cikis = $personel->isten_cikis_tarihi ?? date('Y-m-d');

            $sql = "SELECT * FROM yapilan_isler WHERE ekip_kodu = ? AND tarih >= ? AND tarih <= ? ORDER BY tarih DESC";
            $stmt = $Personel->getDb()->prepare($sql);
            $stmt->execute([$ekip_no, $ise_giris, $isten_cikis]);
            $isler = $stmt->fetchAll(PDO::FETCH_OBJ);

            // Excel oluşturma
            $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (file_exists($vendorAutoload)) {
                require_once $vendorAutoload;
            } else {
                throw new Exception("Excel kütüphanesi bulunamadı.");
            }

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Başlıklar
            $headers = ['Tarih', 'İş Emri No', 'İş Tipi', 'Açıklama', 'Süre/Miktar', 'Durum'];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '1', $header);
                $col++;
            }

            // Veriler
            $row = 2;
            foreach ($isler as $is) {
                $sheet->setCellValue('A' . $row, $is->tarih);
                $sheet->setCellValue('B' . $row, $is->is_emri_no ?? '-');
                $sheet->setCellValue('C' . $row, $is->is_emri_tipi ?? '-');
                $sheet->setCellValue('D' . $row, $is->aciklama ?? '-');
                $sheet->setCellValue('E' . $row, $is->miktar ?? '-');
                $sheet->setCellValue('F' . $row, $is->onay_durumu ?? 'Beklemede');
                $row++;
            }

            // Basit slugify (Helper::slugify yoksa)
            $slugify = function ($text) {
                $find = ['İ', 'ı', 'ğ', 'Ğ', 'ü', 'Ü', 'ş', 'Ş', 'ö', 'Ö', 'ç', 'Ç'];
                $replace = ['i', 'i', 'g', 'g', 'u', 'u', 's', 's', 'o', 'o', 'c', 'c'];
                $text = str_replace($find, $replace, $text);
                $text = preg_replace('/[^a-zA-Z0-9]/', '_', $text);
                return strtolower($text);
            };

            $fileName = "Puantaj_" . $slugify($personel->adi_soyadi) . "_" . date('Ymd') . ".xlsx";

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            exit;

        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
}
?>