<?php
// Çıktı tamponlamasını başlat (Olası boşluk veya hata mesajlarının Excel dosyasını bozmasını engellemek için)
ob_start();

// Hataları ekrana basmayı kapat, sadece logla
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Autoloader'ı dahil et
    $autoloaderPath = dirname(__DIR__, 2) . '/Autoloader.php';
    if (!file_exists($autoloaderPath)) {
        throw new Exception("Autoloader bulunamadı: $autoloaderPath");
    }
    require_once $autoloaderPath;

    // Composer Autoloader
    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    } else {
        throw new Exception("Excel kütüphanesi (vendor/autoload.php) bulunamadı.");
    }

    // PhpSpreadsheet kontrolü
    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        throw new Exception("PhpSpreadsheet sınıfı bulunamadı. Lütfen 'composer install' komutunu çalıştırın.");
    }

    // Oturum kontrolü
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['firma_id'])) {
        throw new Exception("Oturum bilgisi bulunamadı. Lütfen tekrar giriş yapın.");
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Personel Listesi');

    // Başlıklar ve Veri Tipleri
    $columns = [
        'Firma' => 'firma_id',
        'TC Kimlik No' => 'tc_kimlik_no',
        'Adı Soyadı' => 'adi_soyadi',
        'Anne Adı' => 'anne_adi',
        'Baba Adı' => 'baba_adi',
        'Doğum Tarihi (GG.AA.YYYY)' => 'dogum_tarihi',
        'Doğum Yeri (İl)' => 'dogum_yeri_il',
        'Doğum Yeri (İlçe)' => 'dogum_yeri_ilce',
        'Adres' => 'adres',
        'Cinsiyet' => 'cinsiyet',
        'Medeni Durum' => 'medeni_durum',
        'Eşi Çalışıyor mu?' => 'esi_calisiyor_mu',
        'Seyahat Engeli' => 'seyahat_engeli',
        'Ehliyet Sınıfı' => 'ehliyet_sinifi',
        'Kan Grubu' => 'kan_grubu',
        'Cep Telefonu' => 'cep_telefonu',
        'Program Şifre' => 'sifre',
        '2. Cep Telefonu' => 'cep_telefonu_2',
        'E-posta Adresi' => 'email_adresi',
        'Ayakkabı No' => 'ayakkabi_numarasi',
        'Üst Beden No' => 'ust_beden_no',
        'Alt Beden No' => 'alt_beden_no',
        'Referans Adı Soyadı' => 'referans_adi_soyadi',
        'Referans Telefonu' => 'referans_telefonu',
        'Referans Firma' => 'referans_firma',
        'Acil Durum Kişisi' => 'acil_kisi_adi_soyadi',
        'Acil Durum Yakınlık' => 'acil_kisi_yakinlik',
        'Acil Durum Telefonu' => 'acil_kisi_telefonu',
        'Aktif mi?' => 'aktif_mi',
        'İşe Giriş Tarihi' => 'ise_giris_tarihi',
        'İşten Çıkış Tarihi' => 'isten_cikis_tarihi',
        'SGK No' => 'sgk_no',
        'SGK Yapılan Firma' => 'sgk_yapilan_firma',
        'Personel Sınıfı' => 'personel_sinifi',
        'Departman' => 'departman',
        'Görev' => 'gorev',
        'Ekip Bölge' => 'ekip_bolge',
        'Takım' => 'ekip_no',
        'DSS Sınıfı Üst' => 'dss_sinifi_ust',
        'DSS Sınıfı Alt' => 'dss_sinifi_alt',
        'Banka' => 'banka',
        'IBAN Numarası' => 'iban_numarasi',
        'Maaş Durumu' => 'maas_durumu',
        'Maaş Tutarı' => 'maas_tutari',
        'Sodexo Ödemesi Tutarı' => 'sodexo',
        'Sodexo Kart No' => 'sodexo_kart_no',
        'Günlük Ücret' => 'gunluk_ucret',
        'Bes Kesintisi Var mı?' => 'bes_kesintisi_varmi',
    ];

    // Başlıkları yaz ve sütun indekslerini kaydet
    $colIndex = 1;
    $fieldToCol = [];
    foreach ($columns as $header => $dbField) {
        $fieldToCol[$dbField] = $colIndex;
        // Sütun harfini bul (Örn: 1 -> A, 2 -> B)
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);

        // Hücreye değeri yaz
        $sheet->setCellValue($columnLetter . '1', $header);

        // Sütun genişliğini otomatik ayarla
        $sheet->getColumnDimension($columnLetter)->setAutoSize(true);

        // Başlık stili
        $style = $sheet->getStyle($columnLetter . '1');
        $style->getFont()->setBold(true);

        // TC Kimlik No sütunu için sarı arka plan (önemli - güncelleme anahtarı)
        if ($dbField === 'tc_kimlik_no') {
            $style->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFFF99'); // Açık sarı arka plan
        } else {
            $style->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFCCCCCC'); // Gri arka plan
        }

        $colIndex++;
    }

    // Dropdown Verileri Hazırla
    $firmaListStr = "";
    $firmaIdToName = []; // ID -> Firma Adı eşleştirmesi
    try {
        // Firmalar veritabanından çekiliyor
        if (class_exists('\App\Model\FirmaModel')) {
            $FirmaModel = new \App\Model\FirmaModel();
            $firmalar = $FirmaModel->option();

            if ($firmalar && count($firmalar) > 0) {
                $firmaList = [];
                foreach ($firmalar as $f) {
                    // Virgül içeren firma adlarını temizle veya yönet
                    $cleanName = str_replace(',', ' ', $f->firma_adi);
                    $firmaList[] = $cleanName;
                    $firmaIdToName[$f->id] = $cleanName; // ID -> Firma Adı eşleştirmesi
                }
                // Excel formülü için listeyi stringe çevir (maksimum 255 karakter sınırlaması olabilir, dikkat!)
                // Çok uzun listelerde ayrı bir sayfaya yazıp referans vermek daha iyidir ama şimdilik basit tutalım.
                $firmaListStr = '"' . implode(',', $firmaList) . '"';
            }
        }
    } catch (Exception $e) {
        // Veritabanı hatası olursa dropdown boş kalsın, işlem durmasın
        error_log("Firma listesi çekilemedi: " . $e->getMessage());
    }

    // Mevcut personelleri çek (LEFT JOIN ile firma_adi ve ekip_adi dahil)
    $personeller = [];
    try {
        if (class_exists('\App\Model\PersonelModel')) {
            $PersonelModel = new \App\Model\PersonelModel();
            $personeller = $PersonelModel->all(); // firma_adi ve ekip_adi JOIN ile geliyor
        }
    } catch (Exception $e) {
        error_log("Personel listesi çekilemedi: " . $e->getMessage());
    }

    // Personel verilerini Excel'e yaz
    $currentRow = 2;
    if ($personeller && count($personeller) > 0) {
        foreach ($personeller as $personel) {
            foreach ($columns as $header => $dbField) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fieldToCol[$dbField]);
                $value = '';

                if (isset($personel->$dbField)) {
                    $value = $personel->$dbField;
                }

                // Özel alan dönüşümleri
                if ($dbField === 'firma_id') {
                    // Firma adı JOIN ile geldi
                    $value = $personel->firma_adi ?? $value;
                } elseif ($dbField === 'ekip_no') {
                    // Ekip adı JOIN ile geldi
                    $value = $personel->ekip_adi ?? $value;
                } elseif ($dbField === 'dogum_tarihi' || $dbField === 'ise_giris_tarihi' || $dbField === 'isten_cikis_tarihi') {
                    // Tarih formatını düzelt (YYYY-MM-DD -> DD.MM.YYYY)
                    if (!empty($value) && $value !== '0000-00-00' && $value !== null) {
                        $date = \DateTime::createFromFormat('Y-m-d', $value);
                        if ($date) {
                            $value = $date->format('d.m.Y');
                        }
                    } else {
                        $value = '';
                    }
                } elseif ($dbField === 'cinsiyet') {
                    // Cinsiyet dönüşümü
                    if ($value === 'E' || $value === 'e') {
                        $value = 'Erkek';
                    } elseif ($value === 'K' || $value === 'k') {
                        $value = 'Kadın';
                    }
                } elseif ($dbField === 'medeni_durum') {
                    // Medeni durum dönüşümü
                    if ($value === 'E' || $value === 'e') {
                        $value = 'Evli';
                    } elseif ($value === 'B' || $value === 'b') {
                        $value = 'Bekar';
                    }
                } elseif ($dbField === 'esi_calisiyor_mu' || $dbField === 'bes_kesintisi_varmi') {
                    // Evet/Hayır dönüşümü
                    if ($value === '1' || $value === 1) {
                        $value = 'Evet';
                    } elseif ($value === '0' || $value === 0) {
                        $value = 'Hayır';
                    }
                } elseif ($dbField === 'seyahat_engeli') {
                    // Var/Yok dönüşümü
                    if ($value === '1' || $value === 1) {
                        $value = 'Var';
                    } elseif ($value === '0' || $value === 0) {
                        $value = 'Yok';
                    }
                } elseif ($dbField === 'sifre') {
                    // Şifre alanını boş bırak (güvenlik için)
                    $value = '';
                }

                $sheet->setCellValue($col . $currentRow, $value);
            }
            $currentRow++;
        }
    }

    // Satır sayısını belirle (mevcut personel + yeni eklenebilecek 50 satır)
    $rowCount = max($currentRow + 50, 100);

    // Başlık satırını dondur (kaydırırken görünür kalsın)
    $sheet->freezePane('A2');

    // TC Kimlik No sütununu vurgula (güncelleme anahtarı olduğu için)
    $tcColumnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fieldToCol['tc_kimlik_no']);
    if ($personeller && count($personeller) > 0) {
        for ($row = 2; $row < $currentRow; $row++) {
            $sheet->getStyle($tcColumnLetter . $row)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFFDE7'); // Açık sarı arka plan
        }
    }

    // Diğer Enumlar
    $cinsiyetList = '"Erkek,Kadın"';
    $medeniList = '"Evli,Bekar"';
    $evetHayirList = '"Evet,Hayır"';
    $varYokList = '"Var,Yok"';
    $personelSinifiList = '"Beyaz Yaka,Mavi Yaka"';
    $maasDurumuList = '"Brüt,Net"';
    $aktifList = '"1,0"';

    // Veri Doğrulama (Data Validation) - 100 satır için uygula
    for ($i = 2; $i <= $rowCount; $i++) {
        // Firma
        if (!empty($firmaListStr) && isset($fieldToCol['firma_id'])) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fieldToCol['firma_id']);
            $objValidation = $sheet->getCell($col . $i)->getDataValidation();
            $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $objValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
            $objValidation->setAllowBlank(true);
            $objValidation->setShowInputMessage(true);
            $objValidation->setShowErrorMessage(true);
            $objValidation->setShowDropDown(true);
            $objValidation->setFormula1($firmaListStr);
        }

        // Cinsiyet
        if (isset($fieldToCol['cinsiyet'])) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fieldToCol['cinsiyet']);
            $objValidation = $sheet->getCell($col . $i)->getDataValidation();
            $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $objValidation->setAllowBlank(true);
            $objValidation->setShowDropDown(true);
            $objValidation->setFormula1($cinsiyetList);
        }

        // Medeni Durum
        if (isset($fieldToCol['medeni_durum'])) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fieldToCol['medeni_durum']);
            $objValidation = $sheet->getCell($col . $i)->getDataValidation();
            $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $objValidation->setAllowBlank(true);
            $objValidation->setShowDropDown(true);
            $objValidation->setFormula1($medeniList);
        }

        // Eşi Çalışıyor mu
        if (isset($fieldToCol['esi_calisiyor_mu'])) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fieldToCol['esi_calisiyor_mu']);
            $objValidation = $sheet->getCell($col . $i)->getDataValidation();
            $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $objValidation->setAllowBlank(true);
            $objValidation->setShowDropDown(true);
            $objValidation->setFormula1($evetHayirList);
        }

        // Seyahat Engeli
        if (isset($fieldToCol['seyahat_engeli'])) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fieldToCol['seyahat_engeli']);
            $objValidation = $sheet->getCell($col . $i)->getDataValidation();
            $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $objValidation->setAllowBlank(true);
            $objValidation->setShowDropDown(true);
            $objValidation->setFormula1($varYokList);
        }

        // Aktif mi
        if (isset($fieldToCol['aktif_mi'])) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fieldToCol['aktif_mi']);
            $objValidation = $sheet->getCell($col . $i)->getDataValidation();
            $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $objValidation->setAllowBlank(true);
            $objValidation->setShowDropDown(true);
            $objValidation->setFormula1($aktifList);
        }

        // Personel Sınıfı
        if (isset($fieldToCol['personel_sinifi'])) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fieldToCol['personel_sinifi']);
            $objValidation = $sheet->getCell($col . $i)->getDataValidation();
            $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $objValidation->setAllowBlank(true);
            $objValidation->setShowDropDown(true);
            $objValidation->setFormula1($personelSinifiList);
        }

        // Maaş Durumu
        if (isset($fieldToCol['maas_durumu'])) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fieldToCol['maas_durumu']);
            $objValidation = $sheet->getCell($col . $i)->getDataValidation();
            $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $objValidation->setAllowBlank(true);
            $objValidation->setShowDropDown(true);
            $objValidation->setFormula1($maasDurumuList);
        }

        // BES Kesintisi
        if (isset($fieldToCol['bes_kesintisi_varmi'])) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fieldToCol['bes_kesintisi_varmi']);
            $objValidation = $sheet->getCell($col . $i)->getDataValidation();
            $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $objValidation->setAllowBlank(true);
            $objValidation->setShowDropDown(true);
            $objValidation->setFormula1($evetHayirList);
        }
    }

    // Çıktı tamponunu temizle (Böylece sadece Excel dosyası gider)
    ob_end_clean();

    // Dosya adını hazırla (tarih ile birlikte)
    $fileName = 'personel_listesi_' . date('Y-m-d_His') . '.xlsx';

    // Dosya İndirme Headerları
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    // HTTP/1.1
    header('Pragma: public');

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    // Hata durumunda tamponu temizle ve hatayı göster
    if (ob_get_length())
        ob_end_clean();

    // Hata sayfasını HTML olarak göster (kullanıcı daha rahat okusun)
    header('Content-Type: text/html; charset=utf-8');
    echo '<div style="font-family: Arial, sans-serif; padding: 20px; border: 1px solid #f44336; background-color: #ffebee; color: #c62828; border-radius: 5px;">';
    echo '<h3>Şablon Oluşturulurken Hata Oluştu</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<button onclick="window.history.back()" style="padding: 10px 20px; background: #555; color: white; border: none; cursor: pointer;">Geri Dön</button>';
    echo '</div>';
}
?>