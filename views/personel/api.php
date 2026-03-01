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
use App\Model\SystemLogModel;




$Tanimlamalar = new TanimlamalarModel();


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $Personel = new PersonelModel();
    $SystemLog = new SystemLogModel();
    $userId = $_SESSION['user_id'] ?? 0;
    $firma_id = $_SESSION['firma_id'] ?? 0;

    // Oturum kontrolü: firma_id yoksa hiçbir işlem yapma
    if (empty($firma_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Oturumunuz sona ermiş. Lütfen sayfayı yenileyip tekrar giriş yapın.']);
        exit;
    }

    if ($action == 'personel-kaydet') {
        try {

            // Form verilerini al
            $data = $_POST;
            $personel_id = $data['personel_id'];

            /**Personelin yaşı 15'ten küçük olmamalı */
            if (!empty($data['dogum_tarihi'])) {
                $dogumTarihi = DateTime::createFromFormat('d.m.Y', $data['dogum_tarihi']);
                if ($dogumTarihi) {
                    $bugun = new DateTime();
                    $yas = $bugun->diff($dogumTarihi)->y;
                    if ($yas < 15) {
                        throw new Exception("Personel yaşı 15'ten küçük olamaz (Mevcut yaş: $yas).");
                    }
                }
            }

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

                // şifreyi hash ile kaydet (sadece boş değilse)
                if ($key == 'sifre' && !empty($value)) {
                    $data[$key] = password_hash($value, PASSWORD_DEFAULT);
                } else if ($key == 'sifre' && empty($value)) {
                    unset($data['sifre']);
                }
            }

            /** Atlanacak Alanlar (ReadOnly / Geçmiş Tablosundan Yönetilen Alanlar) */
            // Kullanıcı arayüzden readonly özelliğini kaldırıp gönderse bile arka planda kabul etmiyoruz
            $atlanacak_alanlar = [
                'departman_gosterim',
                'departman',
                'gorev_gosterim',
                'gorev',
                'maas_durumu_gosterim',
                'maas_durumu',
                'maas_tutari_gosterim',
                'maas_tutari',
                'gunluk_ucret'
            ];
            foreach ($atlanacak_alanlar as $alan) {
                if (array_key_exists($alan, $data)) {
                    unset($data[$alan]);
                }
            }

            // Geri kalan validasyon ve dönüşümler
            foreach ($data as $key => $value) {
                /**Parasal tutarlar için money formatını kaldır */
                // NOT: maas_durumu alanı 'maas' içerdiği için para dönüşümüne giriyor, onu hariç tut
                if ($key !== 'maas_durumu' && (strpos($key, 'tutar') !== false || strpos($key, 'ucret') !== false || strpos($key, 'maas') !== false || strpos($key, 'sodexo') !== false)) {
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

            // İşten çıkış tarihi doluysa aktif_mi = 0 (Pasif), değilse 1 (Aktif)
            if (!empty($data['isten_cikis_tarihi']) && $data['isten_cikis_tarihi'] != '0000-00-00') {
                $data['aktif_mi'] = 0;
            } else {
                $data['aktif_mi'] = 1;
            }


            // Dizileri virgülle ayrılmış stringe çevir (Örn: departman)
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = implode(',', $value);
                }
            }

            // İşe giriş tarihi kontrolü (Mevcut ekip atamaları varsa)
            if ($personel_id > 0 && !empty($data['ise_giris_tarihi'])) {
                $earliestEkipDate = $Personel->getEarliestEkipAssignmentDate($personel_id);
                if ($earliestEkipDate && $data['ise_giris_tarihi'] > Date::Ymd($earliestEkipDate)) {
                    $formattedEarliest = Date::dmY($earliestEkipDate);
                    throw new Exception("İşe giriş tarihi, personelin mevcut ekip atamalarının en eskisi olan {$formattedEarliest} tarihinden sonra olamaz. Öncelikle ekip geçmişindeki tarihleri düzeltmelisiniz.");
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

            // Güncelleme ise eski verileri al (Log için)
            $oldData = null;
            if ($personel_id > 0) {
                $oldData = $Personel->find($personel_id);
            }

            $res = $Personel->saveWithAttr($data);
            $currentPid = ($personel_id > 0) ? $personel_id : Security::decrypt($res);

            // İşten çıkış tarihi varsa aktif ekip atamalarını ve görev geçmişini kapat
            if (!empty($data['isten_cikis_tarihi']) && $data['isten_cikis_tarihi'] != '0000-00-00') {
                $Personel->closeActiveEkipAssignments($currentPid, $data['isten_cikis_tarihi']);
                $Personel->closeActiveGorevGecmisi($currentPid, $data['isten_cikis_tarihi']);
            }

            $warningMessage = "";
            if (empty($data['isten_cikis_tarihi']) || $data['isten_cikis_tarihi'] == '0000-00-00') {
                $bugun = date('Y-m-d');
                $aktifGorev = $Personel->getAktifGorevGecmisi($currentPid);

                // Eğer aktif görev bulunamadıysa VEYA aktif görev bugün/geçmişte bitmişse (Devam eden açık bir kaydı yoksa)
                if (!$aktifGorev || (!empty($aktifGorev->bitis_tarihi) && $aktifGorev->bitis_tarihi <= $bugun)) {
                    $warningMessage = "<br><br><span class=\"text-danger fw-bold\">Ancak Personelin Aktif Görev (Maaş Tipi) kaydı bulunmamaktadır.<br>Lütfen ekleyiniz!</span>";
                }
            }

            if ($personel_id > 0) {
                // Güncelleme Logu
                $changes = [];
                if ($oldData) {
                    foreach ($data as $key => $value) {
                        // Bazı alanları loglamaya gerek yok veya özel karşılaştırma lazım
                        if (in_array($key, ['id', 'firma_id', 'guncelleme_tarihi', 'sifre']))
                            continue;

                        $oldValue = $oldData->$key ?? null;
                        $newValue = $value;

                        // Normalizasyon
                        $normOld = $oldValue;
                        $normNew = $newValue;

                        // Tarih normalizasyonu (tireleri kaldır ve 0000-00-00 kontrolü yap)
                        if (strpos($key, 'tarih') !== false) {
                            $normOld = str_replace('-', '', strval($oldValue));
                            $normNew = str_replace('-', '', strval($newValue));
                            if ($normOld === '00000000' || empty($normOld))
                                $normOld = '';
                            if ($normNew === '00000000' || empty($normNew))
                                $normNew = '';
                        }

                        // Sayısal normalizasyon (float karşılaştırması)
                        if (is_numeric($normOld) && is_numeric($normNew)) {
                            if (abs(floatval($normOld) - floatval($normNew)) < 0.00001) {
                                $normOld = $normNew; // Eşit kabul et
                            }
                        }

                        if (strval($normOld) !== strval($normNew)) {
                            $displayOld = ($oldValue === null || $oldValue === '' || $oldValue === '0000-00-00') ? 'Boş' : $oldValue;
                            $displayNew = ($newValue === null || $newValue === '' || $newValue === '0000-00-00') ? 'Boş' : $newValue;

                            if (strpos($key, 'tarih') !== false) {
                                $displayOld = ($normOld !== '') ? Date::dmY($oldValue) : 'Boş';
                                $displayNew = ($normNew !== '') ? Date::dmY($newValue) : 'Boş';
                            } elseif (is_numeric($normOld) && is_numeric($normNew)) {
                                // Tutar veya ücret ise formatla, değilse (id vb) olduğu gibi bırak
                                if (strpos($key, 'tutar') !== false || strpos($key, 'ucret') !== false || strpos($key, 'maas') !== false) {
                                    $displayOld = number_format(floatval($oldValue), 2, ',', '.') . ' ₺';
                                    $displayNew = number_format(floatval($newValue), 2, ',', '.') . ' ₺';
                                }
                            }

                            $changes[] = "$key: $displayOld -> $displayNew";
                        }
                    }
                }
                $changesStr = !empty($changes) ? implode(', ', $changes) : 'Değişiklik yok';
                $tcNo = $data['tc_kimlik_no'] ?? ($oldData->tc_kimlik_no ?? 'Bilinmeyen');
                $adiSoyadi = $data['adi_soyadi'] ?? ($oldData->adi_soyadi ?? '');
                $SystemLog->logAction($userId, 'Personel Güncelleme', "$tcNo kimlik numaralı $adiSoyadi isimli personelin verileri güncellendi (Güncellenen veriler: { $changesStr })", SystemLogModel::LEVEL_IMPORTANT);

                $message = "Personel başarıyla güncellendi.";
            } else {
                // Yeni Kayıt Logu
                $tcNo = $data['tc_kimlik_no'] ?? 'Bilinmeyen';
                $adiSoyadi = $data['adi_soyadi'] ?? '';
                $SystemLog->logAction($userId, 'Personel Kayıt', "Yeni personel eklendi: $tcNo - $adiSoyadi", SystemLogModel::LEVEL_IMPORTANT);

                $message = "Personel başarıyla kaydedildi.";
            }

            if (!empty($warningMessage)) {
                $message .= $warningMessage;
            }

            echo json_encode(['status' => 'success', 'message' => $message, 'id' => $personel_id]);

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'personel-sil') {
        try {
            $id = Security::decrypt($_POST['id']);

            $dependencyMessage = $Personel->checkDependencies($id);
            if ($dependencyMessage) {
                throw new Exception("Bu personel silinemez. " . $dependencyMessage);
            }

            $personel = $Personel->find($id);
            $tcNo = $personel->tc_kimlik_no ?? 'Bilinmeyen';
            $adiSoyadi = $personel->adi_soyadi ?? 'Bilinmeyen';
            $Personel->delete($id, false); // false: decrypt işlemi yapılmasın (id direkt geliyorsa)
            $SystemLog->logAction($userId, 'Personel Silme', "$tcNo kimlik numaralı $adiSoyadi isimli personel silindi.", SystemLogModel::LEVEL_IMPORTANT);
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

            $personel = $Personel->find($data['personel_id']);
            $adiSoyadi = $personel->adi_soyadi ?? 'Bilinmeyen';
            $tutar = number_format($saveData['tutar'], 2, ',', '.') . ' ₺';
            $SystemLog->logAction($userId, 'Ek Ödeme Ekleme', "$adiSoyadi isimli personele $tutar tutarında ek ödeme eklendi ({$saveData['aciklama']})", SystemLogModel::LEVEL_IMPORTANT);

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

            $personel = $Personel->find($data['personel_id']);
            $adiSoyadi = $personel->adi_soyadi ?? 'Bilinmeyen';
            $tutar = number_format($saveData['tutar'], 2, ',', '.') . ' ₺';
            $SystemLog->logAction($userId, 'Kesinti Ekleme', "$adiSoyadi isimli personelden $tutar tutarında kesinti tanımlandı ({$saveData['aciklama']})", SystemLogModel::LEVEL_IMPORTANT);

            echo json_encode(['status' => 'success', 'message' => 'Kesinti başarıyla eklendi.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'kesinti-onayla') {
        // Kesinti onaylama
        try {
            $kesinti_id = intval($_POST['kesinti_id'] ?? 0);

            // Debug log
            error_log("Kesinti Onayla - Gelen ID: " . ($_POST['kesinti_id'] ?? 'BOŞ') . " - intval: " . $kesinti_id);

            if (!$kesinti_id) {
                throw new Exception("Kesinti ID gerekli.");
            }

            $PersonelKesintileriModel = new \App\Model\PersonelKesintileriModel();
            $result = $PersonelKesintileriModel->updateKesinti($kesinti_id, [
                'durum' => 'onaylandi',
                'onaylayan_id' => $_SESSION['user_id'] ?? null,
                'onay_tarihi' => date('Y-m-d H:i:s')
            ]);

            error_log("Kesinti Onayla - Update sonucu: " . ($result ? 'true' : 'false'));

            echo json_encode(['status' => 'success', 'message' => 'Kesinti onaylandı.']);
        } catch (Exception $e) {
            error_log("Kesinti Onayla - Hata: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'kesinti-reddet') {
        // Kesinti reddetme
        try {
            $kesinti_id = intval($_POST['kesinti_id'] ?? 0);
            if (!$kesinti_id) {
                throw new Exception("Kesinti ID gerekli.");
            }

            $PersonelKesintileriModel = new \App\Model\PersonelKesintileriModel();
            $PersonelKesintileriModel->updateKesinti($kesinti_id, [
                'durum' => 'reddedildi',
                'onaylayan_id' => $_SESSION['user_id'] ?? null,
                'onay_tarihi' => date('Y-m-d H:i:s')
            ]);

            echo json_encode(['status' => 'success', 'message' => 'Kesinti reddedildi.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'kesinti-sil') {
        // Kesinti silme
        try {
            $kesinti_id = intval($_POST['kesinti_id'] ?? 0);
            if (!$kesinti_id) {
                throw new Exception("Kesinti ID gerekli.");
            }

            $PersonelKesintileriModel = new \App\Model\PersonelKesintileriModel();
            $PersonelKesintileriModel->updateKesinti($kesinti_id, [
                'silinme_tarihi' => date('Y-m-d H:i:s')
            ]);

            echo json_encode(['status' => 'success', 'message' => 'Kesinti silindi.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'kesinti-sonlandir') {
        // Sürekli kesinti sonlandırma
        try {
            $kesinti_id = intval($_POST['kesinti_id'] ?? 0);
            if (!$kesinti_id) {
                throw new Exception("Kesinti ID gerekli.");
            }

            $PersonelKesintileriModel = new \App\Model\PersonelKesintileriModel();
            $PersonelKesintileriModel->sonlandirSurekliKesinti($kesinti_id);

            echo json_encode(['status' => 'success', 'message' => 'Kesinti sonlandırıldı.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'get-details') {
        try {
            $id = Security::decrypt($_POST['id']) ?? 0;
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
            // Formatlamayı kapatarak ham verileri al (Tarihler sayısal gelir)
            $rows = $sheet->toArray(null, true, false);

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
                'dogum_tarihi' => ['doğum tarihi', 'dogum tarihi', 'dt', 'doğum tarihi (gg.aa.yyyy)'],
                'dogum_yeri_il' => ['doğum yeri il', 'dogum yeri il', 'doğum yeri (il)', 'dogum yeri (il)'],
                'dogum_yeri_ilce' => ['doğum yeri ilçe', 'dogum yeri ilce', 'doğum yeri (ilçe)', 'dogum yeri (ilce)'],
                'adres' => ['adres'],
                'cinsiyet' => ['cinsiyet'],
                'medeni_durum' => ['medeni durum'],
                'esi_calisiyor_mu' => ['eşi çalışıyor mu', 'esi calisiyor mu', 'eşi çalışıyor mu?', 'esi calisiyor mu?'],
                'seyahat_engeli' => ['seyahat engeli'],
                'ehliyet_sinifi' => ['ehliyet sınıfı', 'ehliyet sinifi'],
                'kan_grubu' => ['kan grubu'],
                'cep_telefonu' => ['telefon', 'cep telefonu', 'gsm', 'mobil', 'cep'],
                'sifre' => ['program şifresi', 'program sifresi', 'program şifre', 'program sifre', 'şifre', 'sifre', 'password'],
                'kaski_kullanici_adi' => ['kaski kullanıcı adı', 'kaski kullanici adi', 'kaski kullanıcı', 'kaski kullanici', 'kaski k.adı'],
                'kaski_sifre' => ['kaski şifre', 'kaski sifre', 'kaski şifresi', 'kaski sifresi', 'kaski password'],
                'cep_telefonu_2' => ['2. cep telefonu', 'telefon 2'],
                'email_adresi' => ['email', 'e-posta', 'mail', 'eposta', 'e-posta adresi'],
                'ayakkabi_numarasi' => ['ayakkabı no', 'ayakkabi no'],
                'ust_beden_no' => ['üst beden no', 'ust beden no'],
                'alt_beden_no' => ['alt beden no'],
                'referans_adi_soyadi' => ['referans adı soyadı', 'referans adi soyadi'],
                'referans_telefonu' => ['referans telefonu'],
                'referans_firma' => ['referans firma'],
                'acil_kisi_adi_soyadi' => ['acil durum kişisi', 'acil kisi adi soyadi'],
                'acil_kisi_yakinlik' => ['acil durum yakınlık', 'acil kisi yakinlik'],
                'acil_kisi_telefonu' => ['acil durum telefonu', 'acil kisi telefonu'],
                'aktif_mi' => ['aktif mi', 'durum', 'aktif mi?'],
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
                'sodexo' => ['sodexo ödemesi tutarı', 'sodexo odemesi tutari', 'sodexo'],
                'sodexo_kart_no' => ['sodexo kart no', 'sodexo kart numarası', 'sodexo kart', 'kart no', 'kart numarası'],
                'gunluk_ucret' => ['günlük ücret', 'gunluk ucret'],
                'bes_kesintisi_varmi' => ['bes kesintisi var mı?', 'bes kesintisi var mi?', 'bes kesintisi', 'bes'],
                'arac_kullanim' => ['araç kullanım', 'arac kullanim', 'araç', 'arac']
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

                    // Boş gelen veriyi veritabanına gönderme (mevcut veriyi temizlememesi için)
                    if ($val === '' || $val === null) {
                        continue;
                    }

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

                // Firma ID Kontrolü ve Varsayılan Atama (Sadece yeni kayıtlarda boş ise varsayılan ata)
                if (!$isUpdate && empty($newData['firma_id'])) {
                    if ($defaultFirmaId) {
                        $newData['firma_id'] = $defaultFirmaId;
                    }
                }

                // Varsayılan değerler (Sadece yeni kayıtlarda)
                // İşten çıkış tarihi doluysa aktif_mi = 0 (Pasif), değilse 1 (Aktif)
                if (!empty($newData['isten_cikis_tarihi']) && $newData['isten_cikis_tarihi'] != '0000-00-00') {
                    $newData['aktif_mi'] = 0;
                } else {
                    $newData['aktif_mi'] = 1;
                }

                // İşe giriş tarihi kontrolü (Güncelleme ise ve mevcut ekip atamaları varsa)
                if ($isUpdate && !empty($newData['ise_giris_tarihi'])) {
                    $earliestEkipDate = $Personel->getEarliestEkipAssignmentDate($existingId);
                    if ($earliestEkipDate && $newData['ise_giris_tarihi'] > Date::Ymd($earliestEkipDate)) {
                        $formattedEarliest = Date::dmY($earliestEkipDate);
                        $errorDetails[] = "Satır $rowNum ($name): İşe giriş tarihi ({$newData['ise_giris_tarihi']}), mevcut ekip atamalarından ({$formattedEarliest}) daha sonra olamaz.";
                        continue;
                    }
                }

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
                            'ekip_bolge' => $newData['ekip_bolge'] ?? null,
                            'tur_adi' => $ekipKodString,
                            'aciklama' => "Personel Yükleme sırasında otomatik tanımlandı",
                            'firma_id' => $_SESSION['firma_id']
                        ];
                        $encId = $Tanimlamalar->saveWithAttr($tanimData);
                        $ekipId = Security::decrypt($encId);
                    }

                    // Personel verisine ID'yi ata
                    $newData['ekip_no'] = $ekipId;

                    // Personel aktif mi? (Takım kontrolü için lazım)
                    $isNowAktif = false;
                    if (isset($newData['aktif_mi'])) {
                        $isNowAktif = ($newData['aktif_mi'] == 1);
                    } elseif ($isUpdate) {
                        $isNowAktif = ($existing[0]->aktif_mi == 1);
                    } else {
                        $isNowAktif = true; // Yeni kayıtta belirtilmemişse varsayılan aktiftir
                    }

                    // Bu ekip ID'sine sahip başka aktif personel var mı?
                    if ($isNowAktif) {
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
                    $res = $PersonelNew->saveWithAttr($newData);

                    // İşten çıkış tarihi varsa aktif ekip atamalarını kapat
                    if (!empty($newData['isten_cikis_tarihi']) && $newData['isten_cikis_tarihi'] != '0000-00-00') {
                        $currentPid = $isUpdate ? $existingId : Security::decrypt($res);
                        $PersonelNew->closeActiveEkipAssignments($currentPid, $newData['isten_cikis_tarihi']);
                    }

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

            // Personel Excel yükleme logla
            $SystemLog->logAction($userId, 'Personel Excel Yükleme', "Excel'den {$addedCount} personel eklendi, {$updatedCount} güncellendi.", SystemLogModel::LEVEL_IMPORTANT);

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
    } elseif ($action == 'get-unique-values') {
        try {
            $column = $_POST['column'] ?? '';
            $values = $Personel->getUniqueValues($column, $_POST);
            echo json_encode(['status' => 'success', 'data' => $values]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'personel-list') {
        try {
            $result = $Personel->getDataTable($_POST);

            // Verileri formatla (ID şifreleme, tarih formatlama vb.)
            $formattedData = [];
            foreach ($result['data'] as $row) {
                $enc_id = Security::encrypt($row->id);

                $dataRow = (array) $row;
                $dataRow['id'] = $enc_id;

                // Format specific fields
                $dataRow['ise_giris_tarihi'] = (!empty($row->ise_giris_tarihi) && $row->ise_giris_tarihi != '0000-00-00') ? Date::dmY($row->ise_giris_tarihi) : '';
                $dataRow['isten_cikis_tarihi'] = (!empty($row->isten_cikis_tarihi) && $row->isten_cikis_tarihi != '0000-00-00') ? Date::dmY($row->isten_cikis_tarihi) : '';
                $dataRow['dogum_tarihi'] = (!empty($row->dogum_tarihi) && $row->dogum_tarihi != '0000-00-00') ? Date::dmY($row->dogum_tarihi) : '';
                $dataRow['ekip_adi'] = $row->ekip_adi ?? 'YOK';
                $dataRow['ekip_bolge'] = $row->ekip_bolge ?? '---';

                $formattedData[] = $dataRow;
            }

            $result['data'] = $formattedData;
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'puantaj-manuel-kaydet') {
        try {
            $data = $_POST;
            $PuantajModel = new \App\Model\PuantajModel();

            $saveData = [
                'firma_id' => $_SESSION['firma_id'],
                'personel_id' => $data['personel_id'],
                'ekip_kodu' => $data['ekip_kodu'],
                'tarih' => Date::Ymd($data['tarih']),
                'is_emri_tipi' => $data['is_emri_tipi'],
                'is_emri_sonucu' => $data['is_emri_sonucu'],
                'sonuclanmis' => $data['sonuclanmis'],
                'acik_olanlar' => $data['acik_olanlar'],
                'aciklama' => ($data['aciklama'] ? $data['aciklama'] . ' (manuel giriş yapıldı)' : 'manuel giriş yapıldı'),
                'islem_id' => md5(date('Y-m-d H:i:s') . '|' . $data['personel_id'] . '|' . uniqid())
            ];

            $PuantajModel->saveWithAttr($saveData);
            echo json_encode(['status' => 'success', 'message' => 'İş kaydı başarıyla eklendi.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'ekip-gecmisi-ekle') {
        try {
            $data = $_POST;
            $Personel = new PersonelModel();

            $saveData = [
                'personel_id' => $data['personel_id'],
                'ekip_kodu_id' => $data['ekip_kodu_id'],
                'baslangic_tarihi' => Date::Ymd($data['baslangic_tarihi'], 'Y-m-d'),
                'bitis_tarihi' => !empty($data['bitis_tarihi']) ? Date::Ymd($data['bitis_tarihi'], 'Y-m-d') : null,
                'firma_id' => $_SESSION['firma_id'],
                'ekip_sefi_mi' => $data['ekip_sefi_mi'] ?? 0
            ];

            // Personelin işe giriş tarihini kontrol et
            $personelInfo = $Personel->find($saveData['personel_id']);
            if ($personelInfo && !empty($personelInfo->ise_giris_tarihi) && $personelInfo->ise_giris_tarihi != '0000-00-00') {
                if ($saveData['baslangic_tarihi'] < $personelInfo->ise_giris_tarihi) {
                    $formattedIseGiris = Date::dmY($personelInfo->ise_giris_tarihi);
                    throw new Exception("Ekip başlangıç tarihi, personelin işe giriş tarihinden ({$formattedIseGiris}) önce olamaz.");
                }
            }

            // Tarih Çakışması Kontrolü (Bireysel)
            if ($Personel->hasEkipOverlap($saveData['personel_id'], $saveData['ekip_kodu_id'], $saveData['baslangic_tarihi'], $saveData['bitis_tarihi'])) {
                throw new Exception("Seçilen tarih aralığı bu personel için mevcut bir ekip atamasıyla çakışıyor. Lütfen tarihleri kontrol edin.");
            }

            // Tarih Çakışması Kontrolü (Global - Ekip Kodu Başkasında mı?)
            $cakisanPersonel = $Personel->isEkipKoduAvailable($saveData['ekip_kodu_id'], $saveData['baslangic_tarihi'], $saveData['bitis_tarihi']);
            if ($cakisanPersonel) {
                throw new Exception("Seçilen tarih aralığında bu ekip kodu zaten '{$cakisanPersonel->adi_soyadi}' isimli personele tanımlıdır. Aynı tarihte bir ekip kodu birden fazla personele tanımlanamaz.");
            }

            $Personel->addEkipGecmisi($saveData);
            echo json_encode(['status' => 'success', 'message' => 'Ekip geçmişi başarıyla eklendi.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'ekip-gecmisi-sil') {
        try {
            $id = $_POST['id'];
            $Personel = new PersonelModel();

            // Kaydı getir
            $gecmis = $Personel->getSingleEkipGecmisi($id);
            if (!$gecmis) {
                throw new Exception("Kayıt bulunamadı.");
            }

            // Yapılan işler tablosunda kontrol et
            $stmt = $Personel->db->prepare("SELECT COUNT(*) FROM yapilan_isler WHERE personel_id = ? AND ekip_kodu_id = ? AND silinme_tarihi IS NULL");
            $stmt->execute([$gecmis->personel_id, $gecmis->ekip_kodu_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Bu ekip atamasına ait 'Yapılan İşler' kaydı bulunduğu için silemezsiniz.");
            }

            // Endeks okuma tablosunda kontrol et
            $stmt = $Personel->db->prepare("SELECT COUNT(*) FROM endeks_okuma WHERE personel_id = ? AND ekip_kodu_id = ? AND silinme_tarihi IS NULL");
            $stmt->execute([$gecmis->personel_id, $gecmis->ekip_kodu_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Bu ekip atamasına ait 'Endeks Okuma' kaydı bulunduğu için silemezsiniz.");
            }

            $Personel->deleteEkipGecmisi($id);
            echo json_encode(['status' => 'success', 'message' => 'Ekip geçmişi kaydı silindi.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'get-musait-ekipler') {
        try {
            $ekipler = $Tanimlamalar->getMusaitEkipKodlari();
            echo json_encode(['status' => 'success', 'data' => $ekipler]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'get-ekip-gecmisi') {
        try {
            $personel_id = $_POST['personel_id'] ?? 0;
            $gecmis = $Personel->getEkipGecmisi($personel_id);
            echo json_encode(['status' => 'success', 'data' => $gecmis]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'ekip-gecmisi-get') {
        try {
            $id = $_POST['id'];
            $data = $Personel->getSingleEkipGecmisi($id);
            if ($data) {
                // Tarihleri d.m.Y formatına çevir
                $data->baslangic_tarihi = Date::dmY($data->baslangic_tarihi);
                if ($data->bitis_tarihi) {
                    $data->bitis_tarihi = Date::dmY($data->bitis_tarihi);
                }
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Kayıt bulunamadı.']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'ekip-gecmisi-guncelle') {
        try {
            $data = $_POST;
            $saveData = [
                'id' => $data['id'],
                'ekip_kodu_id' => $data['ekip_kodu_id'],
                'baslangic_tarihi' => Date::Ymd($data['baslangic_tarihi'], 'Y-m-d'),
                'bitis_tarihi' => !empty($data['bitis_tarihi']) ? Date::Ymd($data['bitis_tarihi'], 'Y-m-d') : null,
                'ekip_sefi_mi' => $data['ekip_sefi_mi'] ?? 0
            ];

            // Mevcut kaydı bul (personel_id için)
            $oldGecmis = $Personel->getSingleEkipGecmisi($saveData['id']);
            if (!$oldGecmis) {
                throw new Exception("Kayıt bulunamadı.");
            }

            // Personelin işe giriş tarihini kontrol et
            $personelInfo = $Personel->find($oldGecmis->personel_id);
            if ($personelInfo && !empty($personelInfo->ise_giris_tarihi) && $personelInfo->ise_giris_tarihi != '0000-00-00') {
                if ($saveData['baslangic_tarihi'] < $personelInfo->ise_giris_tarihi) {
                    $formattedIseGiris = Date::dmY($personelInfo->ise_giris_tarihi);
                    throw new Exception("Ekip başlangıç tarihi, personelin işe giriş tarihinden ({$formattedIseGiris}) önce olamaz.");
                }
            }

            // Tarih Çakışması Kontrolü (Bireysel)
            if ($Personel->hasEkipOverlap($oldGecmis->personel_id, $saveData['ekip_kodu_id'], $saveData['baslangic_tarihi'], $saveData['bitis_tarihi'], $saveData['id'])) {
                throw new Exception("Seçilen tarih aralığı bu personel için mevcut bir ekip atamasıyla çakışıyor. Lütfen tarihleri kontrol edin.");
            }

            // Tarih Çakışması Kontrolü (Global - Ekip Kodu Başkasında mı?)
            $cakisanPersonel = $Personel->isEkipKoduAvailable($saveData['ekip_kodu_id'], $saveData['baslangic_tarihi'], $saveData['bitis_tarihi'], $saveData['id']);
            if ($cakisanPersonel) {
                throw new Exception("Seçilen tarih aralığında bu ekip kodu zaten '{$cakisanPersonel->adi_soyadi}' isimli personele tanımlıdır. Aynı tarihte bir ekip kodu birden fazla personele tanımlanamaz.");
            }

            $Personel->updateEkipGecmisi($saveData);
            echo json_encode(['status' => 'success', 'message' => 'Ekip geçmişi başarıyla güncellendi.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'get-gorev-gecmisi') {
        try {
            $personel_id = $_POST['personel_id'] ?? 0;
            $gecmis = $Personel->getGorevGecmisi($personel_id);
            echo json_encode(['status' => 'success', 'data' => $gecmis]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'get-aktif-gorev') {
        try {
            $personel_id = $_POST['personel_id'] ?? 0;
            $aktifKayit = $Personel->getAktifGorevGecmisi($personel_id);
            if ($aktifKayit) {
                echo json_encode(['status' => 'success', 'data' => $aktifKayit]);
            } else {
                // Kayıt yoksa personel tablosundan mevcut verileri dön
                $p = $Personel->find($personel_id);
                if ($p) {
                    echo json_encode([
                        'status' => 'success',
                        'data' => [
                            'departman' => $p->departman,
                            'gorev' => $p->gorev,
                            'maas_durumu' => $p->maas_durumu,
                            'maas_tutari' => $p->maas_tutari
                        ]
                    ]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Personel bulunamadı.']);
                }
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'gorev-gecmisi-ekle') {
        try {
            $data = $_POST;

            // Departman array ise virgülle ayır
            if (isset($data['departman']) && is_array($data['departman'])) {
                $data['departman'] = implode(',', $data['departman']);
            }

            $saveData = [
                'personel_id' => $data['personel_id'],
                'departman' => $data['departman'] ?? null,
                'gorev' => $data['gorev'] ?? null,
                'maas_durumu' => $data['maas_durumu'],
                'maas_tutari' => Helper::formattedMoneyToNumber($data['maas_tutari']),
                'baslangic_tarihi' => Date::Ymd($data['gorev_baslangic'] ?? $data['baslangic_tarihi'] ?? '', 'Y-m-d'),
                'bitis_tarihi' => !empty($data['gorev_bitis']) ? Date::Ymd($data['gorev_bitis'], 'Y-m-d') : (!empty($data['bitis_tarihi']) ? Date::Ymd($data['bitis_tarihi'], 'Y-m-d') : null),
                'aciklama' => $data['aciklama'] ?? null
            ];

            // Aktif görev kontrolü
            $aktifGorevCheck = $Personel->getAktifGorevGecmisi($data['personel_id']);
            if ($aktifGorevCheck) {
                throw new Exception("Personelin aktif bir görev geçmişi bulunmaktadır. Yeni bir görev kaydı eklemeden önce mevcut görev kaydına bitiş tarihi ekleyerek sonlandırmalısınız.");
            }

            $Personel->addGorevGecmisi($saveData);

            // Personel tablosunu aktif kayıtla senkronize et
            $Personel->syncPersonelFromGorevGecmisi($data['personel_id']);

            echo json_encode(['status' => 'success', 'message' => 'Görev geçmişi başarıyla eklendi.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'gorev-gecmisi-get') {
        try {
            $id = $_POST['id'];
            $data = $Personel->getSingleGorevGecmisi($id);
            if ($data) {
                $data->baslangic_tarihi = Date::dmY($data->baslangic_tarihi);
                if ($data->bitis_tarihi) {
                    $data->bitis_tarihi = Date::dmY($data->bitis_tarihi);
                }
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Kayıt bulunamadı.']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'gorev-gecmisi-guncelle') {
        try {
            $data = $_POST;

            // Departman array ise virgülle ayır
            if (isset($data['departman']) && is_array($data['departman'])) {
                $data['departman'] = implode(',', $data['departman']);
            }

            $saveData = [
                'id' => $data['id'],
                'departman' => $data['departman'] ?? null,
                'gorev' => $data['gorev'] ?? null,
                'maas_durumu' => $data['maas_durumu'],
                'maas_tutari' => Helper::formattedMoneyToNumber($data['maas_tutari']),
                'baslangic_tarihi' => Date::Ymd($data['gorev_baslangic'] ?? $data['baslangic_tarihi'] ?? '', 'Y-m-d'),
                'bitis_tarihi' => !empty($data['gorev_bitis']) ? Date::Ymd($data['gorev_bitis'], 'Y-m-d') : (!empty($data['bitis_tarihi']) ? Date::Ymd($data['bitis_tarihi'], 'Y-m-d') : null),
                'aciklama' => $data['aciklama'] ?? null
            ];

            // Aktif görev kontrolü (Güncellenen kayıt aktif hale geliyorsa)
            $personel_id = $data['personel_id'] ?? 0;
            if ($personel_id <= 0) {
                $gecmisKayit = $Personel->getSingleGorevGecmisi($saveData['id']);
                $personel_id = $gecmisKayit->personel_id ?? 0;
            }

            if ($personel_id > 0) {
                $bugun = date('Y-m-d');
                $isNewlyActive = ($saveData['baslangic_tarihi'] <= $bugun && ($saveData['bitis_tarihi'] === null || $saveData['bitis_tarihi'] >= $bugun));
                
                if ($isNewlyActive) {
                    $aktifGorev = $Personel->getAktifGorevGecmisi($personel_id);
                    if ($aktifGorev && $aktifGorev->id != $saveData['id']) {
                        throw new Exception("Personelin zaten aktif bir görev geçmişi bulunmaktadır. Bu kaydı aktif hale getirmek için önce diğer aktif kaydı sonlandırmalısınız.");
                    }
                }
            }

            $Personel->updateGorevGecmisi($saveData);

            // Personel tablosunu aktif kayıtla senkronize et
            $personel_id = $Personel->getSingleGorevGecmisi($data['id'])->personel_id ?? 0;
            if ($personel_id > 0) {
                $Personel->syncPersonelFromGorevGecmisi($personel_id);
            }

            echo json_encode(['status' => 'success', 'message' => 'Görev geçmişi başarıyla güncellendi.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } elseif ($action == 'gorev-gecmisi-sil') {
        try {
            $id = $_POST['id'];
            // Silinmeden önce personel_id'yi al
            $gecmisKayit = $Personel->getSingleGorevGecmisi($id);
            $personel_id = $gecmisKayit->personel_id ?? 0;

            $Personel->deleteGorevGecmisi($id);

            // Personel tablosunu aktif kayıtla senkronize et
            if ($personel_id > 0) {
                $Personel->syncPersonelFromGorevGecmisi($personel_id);
            }

            echo json_encode(['status' => 'success', 'message' => 'Görev geçmişi kaydı silindi.']);
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
            $type = $_GET['type'] ?? 'puantaj';
            $startDate = $_GET['start_date'] ?? '';
            $endDate = $_GET['end_date'] ?? '';

            $personel = $Personel->find($id);
            if (!$personel) {
                throw new Exception("Personel bulunamadı.");
            }

            $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (file_exists($vendorAutoload)) {
                require_once $vendorAutoload;
            } else {
                throw new Exception("Excel kütüphanesi bulunamadı.");
            }

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            if ($type === 'okuma') {
                $EndeksOkuma = new \App\Model\EndeksOkumaModel();
                $records = $EndeksOkuma->getFiltered($startDate, $endDate, $id);

                $headers = ['Bölgesi', 'Personel Adı', 'Sarfiyat', 'Ort. Sarfiyat', 'Tahakkuk', 'Ort. Tahakkuk', 'Okunan Gün', 'Okunan Abone', 'Ort. Abone', 'Perf. (%)', 'Tarih'];
                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue($col . '1', $header);
                    $col++;
                }

                $row = 2;
                foreach ($records as $r) {
                    $sheet->setCellValue('A' . $row, $r->bolge);
                    $sheet->setCellValue('B' . $row, $r->personel_adi ?: $r->kullanici_adi);
                    $sheet->setCellValue('C' . $row, $r->sarfiyat);
                    $sheet->setCellValue('D' . $row, $r->ort_sarfiyat_gunluk);
                    $sheet->setCellValue('E' . $row, $r->tahakkuk);
                    $sheet->setCellValue('F' . $row, $r->ort_tahakkuk_gunluk);
                    $sheet->setCellValue('G' . $row, $r->okunan_gun_sayisi);
                    $sheet->setCellValue('H' . $row, $r->okunan_abone_sayisi);
                    $sheet->setCellValue('I' . $row, $r->ort_okunan_abone_sayisi_gunluk);
                    $sheet->setCellValue('J' . $row, $r->okuma_performansi);
                    $sheet->setCellValue('K' . $row, $r->tarih);
                    $row++;
                }
            } else {
                $Puantaj = new \App\Model\PuantajModel();
                $records = $Puantaj->getFiltered($startDate, $endDate, $id, '', '');

                $headers = ['Tarih', 'İş Tipi', 'İş Emri Sonucu', 'Sonuçlanan', 'Açık Olanlar', 'Açıklama'];
                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue($col . '1', $header);
                    $col++;
                }

                $row = 2;
                foreach ($records as $r) {
                    $sheet->setCellValue('A' . $row, $r->tarih);
                    $sheet->setCellValue('B' . $row, $r->is_emri_tipi ?? '-');
                    $sheet->setCellValue('C' . $row, $r->is_emri_sonucu ?? '-');
                    $sheet->setCellValue('D' . $row, $r->sonuclanmis ?? '0');
                    $sheet->setCellValue('E' . $row, $r->acik_olanlar ?? '0');
                    $sheet->setCellValue('F' . $row, $r->aciklama ?? '-');
                    $row++;
                }
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