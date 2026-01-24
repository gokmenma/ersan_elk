<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\BordroDonemModel;
use App\Model\BordroPersonelModel;
use App\Model\PersonelModel;
use App\Model\BordroParametreModel;
use App\Helper\Helper;
use App\Helper\Date;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    $BordroDonem = new BordroDonemModel();
    $BordroPersonel = new BordroPersonelModel();

    try {
        switch ($action) {

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

                $sql = $BordroPersonel->getDb()->prepare("UPDATE personel_kesintileri SET silinme_tarihi = NOW() WHERE id = ?");
                if ($sql->execute([$id])) {
                    // Maaş tekrar hesapla
                    if ($personel_id > 0 && $donem_id > 0) {
                        $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);
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

                $sql = $BordroPersonel->getDb()->prepare("UPDATE personel_ek_odemeler SET silinme_tarihi = NOW() WHERE id = ?");
                if ($sql->execute([$id])) {
                    // Maaş tekrar hesapla
                    if ($personel_id > 0 && $donem_id > 0) {
                        $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);
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

                $BordroPersonel->removeFromDonem($id);

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

                $hesaplananSayisi = 0;

                foreach ($personel_ids as $bp_id) {
                    if ($BordroPersonel->hesaplaMaas(intval($bp_id))) {
                        $hesaplananSayisi++;
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => "$hesaplananSayisi personelin maaşı hesaplandı."
                ]);
                break;

            // Bordro Detayı Getir
            case 'get-detail':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz kayıt.');
                }

                $bp = $BordroPersonel->find($id);
                if (!$bp) {
                    throw new Exception('Kayıt bulunamadı.');
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
                    'diger' => 'Diğer Ek Ödeme'
                ];

                // Detaylı kesinti ve ek ödemeleri çek
                $kesintilerDetay = $BordroPersonel->getDonemKesintileriDetay($bp->personel_id, $bp->donem_id);
                $ekOdemelerDetay = $BordroPersonel->getDonemEkOdemeleriDetay($bp->personel_id, $bp->donem_id);

                // Toplamları hesapla
                $guncelKesinti = $BordroPersonel->getDonemKesintileri($bp->personel_id, $bp->donem_id);
                $guncelEkOdeme = $BordroPersonel->getDonemEkOdemeleri($bp->personel_id, $bp->donem_id);

                // Günlük ücret ve çalışma günü hesapla
                $brutMaas = floatval($bp->brut_maas ?? 0);
                $gunlukUcret = $brutMaas / 30; // Sabit 30 güne böl

                // Ücretsiz izin gün sayısını hesapla
                $izinKesintileri = $BordroPersonel->getDonemKesintileriListe($bp->personel_id, $bp->donem_id);
                $ucretsizIzinGunu = 0;
                foreach ($izinKesintileri as $kesinti) {
                    if (strpos($kesinti->aciklama ?? '', '[Ücretsiz İzin]') === 0) {
                        // Açıklamadan gün sayısını çıkar: "[Ücretsiz İzin] İzin Adı (X gün x Y ₺)"
                        if (preg_match('/\((\d+)\s*gün/', $kesinti->aciklama, $matches)) {
                            $ucretsizIzinGunu += intval($matches[1]);
                        }
                    }
                }
                $calismaGunu = 30 - $ucretsizIzinGunu;

                // HTML oluştur
                $html = '<div class="row">';

                // Personel Bilgileri
                $html .= '<div class="col-md-6">';
                $html .= '<h6 class="border-bottom pb-2 mb-3"><i class="bx bx-user me-1"></i>Personel Bilgileri</h6>';
                $html .= '<table class="table table-sm">';
                $html .= '<tr><td class="text-muted">Ad Soyad:</td><td class="fw-bold">' . htmlspecialchars($personel->adi_soyadi ?? '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">TC Kimlik:</td><td>' . htmlspecialchars($personel->tc_kimlik_no ?? '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">Departman:</td><td>' . htmlspecialchars($personel->departman ?? '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">Görev:</td><td>' . htmlspecialchars($personel->gorev ?? '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">İşe Giriş:</td><td>' . ($personel->ise_giris_tarihi ? date('d.m.Y', strtotime($personel->ise_giris_tarihi)) : '-') . '</td></tr>';
                $html .= '</table>';
                $html .= '</div>';

                // Maaş Özeti
                $html .= '<div class="col-md-6">';
                $html .= '<h6 class="border-bottom pb-2 mb-3"><i class="bx bx-money me-1"></i>Maaş Özeti</h6>';
                $html .= '<table class="table table-sm">';
                $html .= '<tr><td class="text-muted">' . $personel->maas_durumu . ' Maaş:</td><td class="fw-bold text-primary">' . ($bp->brut_maas ? number_format($bp->brut_maas, 2, ',', '.') . ' ₺' : '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">Günlük Ücret:</td><td class="text-secondary">' . number_format($gunlukUcret, 2, ',', '.') . ' ₺ <small class="text-muted">(' . $personel->maas_durumu . ' / 30)</small></td></tr>';
                $html .= '<tr><td class="text-muted">Çalışma Günü:</td><td class="' . ($ucretsizIzinGunu > 0 ? 'text-warning' : 'text-secondary') . '">' . $calismaGunu . ' gün' . ($ucretsizIzinGunu > 0 ? ' <small class="text-muted">(-' . $ucretsizIzinGunu . ' izin)</small>' : '') . '</td></tr>';

                // Ücretsiz izin varsa, çalışılan brüt maaşı da göster
                if ($ucretsizIzinGunu > 0) {
                    $izinKesintisiTutar = $gunlukUcret * $ucretsizIzinGunu;
                    $calisanBrutMaas = floatval($bp->brut_maas ?? 0) - $izinKesintisiTutar;
                    $html .= '<tr class="table-warning"><td class="text-muted">Çalışılan Brüt:</td><td class="fw-bold text-warning">' . number_format($calisanBrutMaas, 2, ',', '.') . ' ₺ <small class="text-muted">(SGK matrahı)</small></td></tr>';
                }

                $html .= '<tr><td class="text-muted">Toplam Ek Ödeme:</td><td class="text-success fw-medium">+' . number_format($guncelEkOdeme, 2, ',', '.') . ' ₺</td></tr>';
                $html .= '<tr><td class="text-muted">Toplam Kesinti:</td><td class="text-danger fw-medium">-' . number_format($guncelKesinti + floatval($bp->sgk_isci) + floatval($bp->issizlik_isci) + floatval($bp->gelir_vergisi) + floatval($bp->damga_vergisi), 2, ',', '.') . ' ₺</td></tr>';
                $html .= '<tr class="table-success"><td class="fw-bold">Net Maaş:</td><td class="fw-bold text-success fs-5">' . ($bp->net_maas ? number_format($bp->net_maas, 2, ',', '.') . ' ₺' : '-') . '</td></tr>';
                $html .= '</table>';
                $html .= '</div>';

                $html .= '</div>'; // İlk row kapandı

                // Kesintiler ve Ek Ödemeler - Detaylı
                $html .= '<div class="row mt-4">';

                // YASAL KESİNTİLER
                $html .= '<div class="col-md-4">';
                $html .= '<div class="card border-danger h-100">';
                $html .= '<div class="card-header bg-danger text-white py-2">';
                $html .= '<i class="bx bx-building me-1"></i> Yasal Kesintiler';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<table class="table table-sm mb-0">';
                $html .= '<tbody>';

                $toplamYasalKesinti = 0;

                if ($bp->sgk_isci > 0) {
                    $html .= '<tr><td class="ps-3">SGK İşçi (%14)</td><td class="text-end pe-3 text-danger">-' . number_format($bp->sgk_isci, 2, ',', '.') . ' ₺</td></tr>';
                    $toplamYasalKesinti += floatval($bp->sgk_isci);
                }
                if ($bp->issizlik_isci > 0) {
                    $html .= '<tr><td class="ps-3">İşsizlik İşçi (%1)</td><td class="text-end pe-3 text-danger">-' . number_format($bp->issizlik_isci, 2, ',', '.') . ' ₺</td></tr>';
                    $toplamYasalKesinti += floatval($bp->issizlik_isci);
                }
                if ($bp->gelir_vergisi > 0) {
                    $html .= '<tr><td class="ps-3">Gelir Vergisi</td><td class="text-end pe-3 text-danger">-' . number_format($bp->gelir_vergisi, 2, ',', '.') . ' ₺</td></tr>';
                    $toplamYasalKesinti += floatval($bp->gelir_vergisi);
                }
                if ($bp->damga_vergisi > 0) {
                    $html .= '<tr><td class="ps-3">Damga Vergisi</td><td class="text-end pe-3 text-danger">-' . number_format($bp->damga_vergisi, 2, ',', '.') . ' ₺</td></tr>';
                    $toplamYasalKesinti += floatval($bp->damga_vergisi);
                }

                $html .= '<tr class="table-light"><td class="ps-3 fw-bold">Toplam</td><td class="text-end pe-3 fw-bold text-danger">-' . number_format($toplamYasalKesinti, 2, ',', '.') . ' ₺</td></tr>';
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';

                // DİĞER KESİNTİLER (avans, icra, nafaka vs.)
                $html .= '<div class="col-md-4">';
                $html .= '<div class="card border-warning h-100">';
                $html .= '<div class="card-header bg-warning text-dark py-2">';
                $html .= '<i class="bx bx-minus-circle me-1"></i> Diğer Kesintiler';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<table class="table table-sm mb-0">';
                $html .= '<tbody>';

                if (empty($kesintilerDetay)) {
                    $html .= '<tr><td class="text-center text-muted py-3" colspan="2"><i class="bx bx-check-circle me-1"></i>Kesinti yok</td></tr>';
                } else {
                    foreach ($kesintilerDetay as $kesinti) {
                        $turEtiket = $kesintiTurEtiketleri[$kesinti->tur] ?? ucfirst($kesinti->tur);
                        $adetStr = $kesinti->adet > 1 ? ' <small class="text-muted">(' . $kesinti->adet . ' adet)</small>' : '';
                        $html .= '<tr><td class="ps-3">' . htmlspecialchars($turEtiket) . $adetStr . '</td><td class="text-end pe-3 text-danger">-' . number_format($kesinti->toplam_tutar, 2, ',', '.') . ' ₺</td></tr>';
                    }
                }

                $html .= '<tr class="table-light"><td class="ps-3 fw-bold">Toplam</td><td class="text-end pe-3 fw-bold text-danger">-' . number_format($guncelKesinti, 2, ',', '.') . ' ₺</td></tr>';
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';

                // EK ÖDEMELER
                $html .= '<div class="col-md-4">';
                $html .= '<div class="card border-success h-100">';
                $html .= '<div class="card-header bg-success text-white py-2">';
                $html .= '<i class="bx bx-plus-circle me-1"></i> Ek Ödemeler';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<table class="table table-sm mb-0">';
                $html .= '<tbody>';

                // Puantaj dışı ek ödemeleri grupla
                $ekOdemelerNonPuantaj = [];
                $puantajOdemeler = [];

                // Tüm ek ödemeleri (listeli) al
                $tumEkOdemeler = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);

                foreach ($tumEkOdemeler as $odeme) {
                    if (strpos($odeme->aciklama ?? '', '[Puantaj]') === 0) {
                        // Puantaj ödemesi - ayrı göster
                        $puantajOdemeler[] = $odeme;
                    } else {
                        // Diğer ödemeler - grupla
                        $tur = $odeme->tur;
                        if (!isset($ekOdemelerNonPuantaj[$tur])) {
                            $ekOdemelerNonPuantaj[$tur] = ['toplam' => 0, 'adet' => 0];
                        }
                        $ekOdemelerNonPuantaj[$tur]['toplam'] += floatval($odeme->tutar);
                        $ekOdemelerNonPuantaj[$tur]['adet']++;
                    }
                }

                if (empty($ekOdemelerNonPuantaj) && empty($puantajOdemeler)) {
                    $html .= '<tr><td class="text-center text-muted py-3" colspan="2"><i class="bx bx-info-circle me-1"></i>Ek ödeme yok</td></tr>';
                } else {
                    // Önce normal ek ödemeleri göster
                    foreach ($ekOdemelerNonPuantaj as $tur => $data) {
                        $turEtiket = $ekOdemeTurEtiketleri[$tur] ?? ucfirst($tur);
                        $adetStr = $data['adet'] > 1 ? ' <small class="text-muted">(' . $data['adet'] . ' adet)</small>' : '';
                        $html .= '<tr><td class="ps-3">' . htmlspecialchars($turEtiket) . $adetStr . '</td><td class="text-end pe-3 text-success">+' . number_format($data['toplam'], 2, ',', '.') . ' ₺</td></tr>';
                    }

                    // Puantaj ödemelerini ayrı ayrı göster
                    if (!empty($puantajOdemeler)) {
                        $html .= '<tr><td colspan="2" class="ps-3 pt-2 pb-1"><small class="text-muted fw-medium"><i class="bx bx-briefcase me-1"></i>Puantaj Ödemeleri</small></td></tr>';
                        foreach ($puantajOdemeler as $puantaj) {
                            // [Puantaj] AÇMA İŞ EMRİ (1 Adet) formatından temiz açıklama çıkar
                            $aciklama = str_replace('[Puantaj] ', '', $puantaj->aciklama ?? '');
                            $html .= '<tr><td class="ps-4 small">' . htmlspecialchars($aciklama) . '</td><td class="text-end pe-3 text-success">+' . number_format($puantaj->tutar, 2, ',', '.') . ' ₺</td></tr>';
                        }
                    }
                }

                $html .= '<tr class="table-light"><td class="ps-3 fw-bold">Toplam</td><td class="text-end pe-3 fw-bold text-success">+' . number_format($guncelEkOdeme, 2, ',', '.') . ' ₺</td></tr>';
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';

                $html .= '</div>'; // Kesinti/Ek ödeme row kapandı

                // İşveren Maliyetleri
                $html .= '<div class="row mt-4">';
                $html .= '<div class="col-12">';
                $html .= '<h6 class="border-bottom pb-2 mb-3"><i class="bx bx-building me-1"></i>İşveren Maliyetleri</h6>';
                $html .= '<div class="row text-center">';
                $html .= '<div class="col-md-4"><div class="border rounded p-3"><small class="text-muted d-block">SGK İşveren (%20.5)</small><span class="fs-5 fw-bold text-warning">' . ($bp->sgk_isveren ? number_format($bp->sgk_isveren, 2, ',', '.') . ' ₺' : '-') . '</span></div></div>';
                $html .= '<div class="col-md-4"><div class="border rounded p-3"><small class="text-muted d-block">İşsizlik İşveren (%2)</small><span class="fs-5 fw-bold text-warning">' . ($bp->issizlik_isveren ? number_format($bp->issizlik_isveren, 2, ',', '.') . ' ₺' : '-') . '</span></div></div>';
                $html .= '<div class="col-md-4"><div class="border rounded p-3 bg text-white-primary bg-opacity-10"><small class="text-muted d-block">Toplam Maliyet</small><span class="fs-5 fw-bold text-primary">' . ($bp->toplam_maliyet ? number_format($bp->toplam_maliyet, 2, ',', '.') . ' ₺' : '-') . '</span></div></div>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';

                // Ödeme Dağılımı
                $eldenOdeme = ($bp->net_maas ?? 0) - ($bp->banka_odemesi ?? 0) - ($bp->sodexo_odemesi ?? 0) - ($bp->diger_odeme ?? 0);
                if ($bp->banka_odemesi > 0 || $bp->sodexo_odemesi > 0 || $bp->diger_odeme > 0 || $eldenOdeme > 0) {
                    $html .= '<div class="row mt-4">';
                    $html .= '<div class="col-12">';
                    $html .= '<h6 class="border-bottom pb-2 mb-3"><i class="bx bx-wallet me-1"></i>Ödeme Dağılımı</h6>';
                    $html .= '<div class="row text-center">';
                    $html .= '<div class="col-md-3"><div class="border rounded p-3"><small class="text-muted d-block">Banka</small><span class="fs-5 fw-bold text-primary">' . number_format($bp->banka_odemesi ?? 0, 2, ',', '.') . ' ₺</span></div></div>';
                    $html .= '<div class="col-md-3"><div class="border rounded p-3"><small class="text-muted d-block">Sodexo</small><span class="fs-5 fw-bold text-info">' . number_format($bp->sodexo_odemesi ?? 0, 2, ',', '.') . ' ₺</span></div></div>';
                    $html .= '<div class="col-md-3"><div class="border rounded p-3"><small class="text-muted d-block">Diğer</small><span class="fs-5 fw-bold text-secondary">' . number_format($bp->diger_odeme ?? 0, 2, ',', '.') . ' ₺</span></div></div>';
                    $html .= '<div class="col-md-3"><div class="border rounded p-3 bg-warning bg-opacity-10"><small class="text-muted d-block">Elden</small><span class="fs-5 fw-bold text-warning">' . number_format($eldenOdeme, 2, ',', '.') . ' ₺</span></div></div>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                }

                if ($bp->hesaplama_tarihi) {
                    $html .= '<div class="text-muted small mt-3 text-end"><i class="bx bx-time me-1"></i>Son Hesaplama: ' . date('d.m.Y H:i', strtotime($bp->hesaplama_tarihi)) . '</div>';
                }

                echo json_encode([
                    'status' => 'success',
                    'html' => $html
                ]);
                break;

            // Dönemi Kapat
            case 'donem-kapat':
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem.');
                }

                $sql = $BordroDonem->getDb()->prepare("UPDATE bordro_donemi SET kapali_mi = 1 WHERE id = ?");
                $sql->execute([$donem_id]);

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Dönem kapatıldı. Artık bu dönemde değişiklik yapılamaz.'
                ]);
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

                // Net maaşı al
                $sql = $BordroPersonel->getDb()->prepare("SELECT net_maas FROM bordro_personel WHERE id = ?");
                $sql->execute([$id]);
                $data = $sql->fetch(PDO::FETCH_OBJ);

                if (!$data) {
                    throw new Exception('Bordro kaydı bulunamadı.');
                }

                $net = floatval($data->net_maas ?? 0);
                $elden = $net - $banka - $sodexo - $diger;

                if ($elden < 0) {
                    throw new Exception('Ödeme toplamı net maaşı aşamaz!');
                }

                // Güncelle
                $sql = $BordroPersonel->getDb()->prepare("
                    UPDATE bordro_personel 
                    SET banka_odemesi = ?, sodexo_odemesi = ?, diger_odeme = ?, elden_odeme = ?
                    WHERE id = ?
                ");
                $sql->execute([$banka, $sodexo, $diger, $elden, $id]);

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Ödeme dağılımı kaydedildi.'
                ]);
                break;

            // Personel Gelir Ekle / Güncelle
            case 'personel-gelir-ekle':
                $id = intval($_POST['id'] ?? 0); // ID varsa güncelleme
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');
                $tutar = floatval($_POST['tutar'] ?? 0);
                $tur = trim($_POST['ek_odeme_tur'] ?? 'diger');

                if ($personel_id <= 0 || $donem_id <= 0) {
                    throw new Exception('Geçersiz personel veya dönem.');
                }

                if ($tutar <= 0) {
                    throw new Exception('Tutar 0\'dan büyük olmalıdır.');
                }

                if ($id > 0) {
                    // Güncelleme
                    $sql = $BordroPersonel->getDb()->prepare("
                        UPDATE personel_ek_odemeler 
                        SET aciklama = ?, tutar = ?, tur = ? 
                        WHERE id = ?
                    ");
                    if ($sql->execute([$aciklama, $tutar, $tur, $id])) {
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
                    if ($BordroPersonel->addEkOdeme($personel_id, $donem_id, $aciklama, $tutar, $tur)) {
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
                $tutar = floatval($_POST['tutar'] ?? 0);
                $tur = trim($_POST['kesinti_tur'] ?? 'diger');

                if ($personel_id <= 0 || $donem_id <= 0) {
                    throw new Exception('Geçersiz personel veya dönem.');
                }

                if ($tutar <= 0) {
                    throw new Exception('Tutar 0\'dan büyük olmalıdır.');
                }

                if ($id > 0) {
                    // Güncelleme
                    $sql = $BordroPersonel->getDb()->prepare("
                        UPDATE personel_kesintileri 
                        SET aciklama = ?, tutar = ?, tur = ? 
                        WHERE id = ?
                    ");
                    if ($sql->execute([$aciklama, $tutar, $tur, $id])) {
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
                    if ($BordroPersonel->addKesinti($personel_id, $donem_id, $aciklama, $tutar, $tur)) {
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
                    'gunluk_muaf_limit' => floatval($_POST['gunluk_muaf_limit'] ?? 0),
                    'aylik_muaf_limit' => floatval($_POST['aylik_muaf_limit'] ?? 0),
                    'muaf_limit_tipi' => $_POST['muaf_limit_tipi'] ?? 'yok',
                    'sgk_matrahi_dahil' => intval($_POST['sgk_matrahi_dahil'] ?? 0),
                    'gelir_vergisi_dahil' => intval($_POST['gelir_vergisi_dahil'] ?? 1),
                    'damga_vergisi_dahil' => intval($_POST['damga_vergisi_dahil'] ?? 0),
                    'gecerlilik_baslangic' => !empty($_POST['gecerlilik_baslangic']) ? $_POST['gecerlilik_baslangic'] : null,
                    'gecerlilik_bitis' => !empty($_POST['gecerlilik_bitis']) ? $_POST['gecerlilik_bitis'] : null,
                    'varsayilan_tutar' => floatval($_POST['varsayilan_tutar'] ?? 0),
                    'gunluk_tutar' => floatval($_POST['gunluk_tutar'] ?? 0),
                    'gun_sayisi_otomatik' => intval($_POST['gun_sayisi_otomatik'] ?? 0),
                    'varsayilan_gun_sayisi' => intval($_POST['varsayilan_gun_sayisi'] ?? 26),
                    'oran' => floatval($_POST['oran'] ?? 0),
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
                    'gunluk_muaf_limit' => floatval($_POST['gunluk_muaf_limit'] ?? 0),
                    'aylik_muaf_limit' => floatval($_POST['aylik_muaf_limit'] ?? 0),
                    'muaf_limit_tipi' => $_POST['muaf_limit_tipi'] ?? 'yok',
                    'sgk_matrahi_dahil' => intval($_POST['sgk_matrahi_dahil'] ?? 0),
                    'gelir_vergisi_dahil' => intval($_POST['gelir_vergisi_dahil'] ?? 1),
                    'damga_vergisi_dahil' => intval($_POST['damga_vergisi_dahil'] ?? 0),
                    'gecerlilik_baslangic' => !empty($_POST['gecerlilik_baslangic']) ? $_POST['gecerlilik_baslangic'] : null,
                    'gecerlilik_bitis' => !empty($_POST['gecerlilik_bitis']) ? $_POST['gecerlilik_bitis'] : null,
                    'varsayilan_tutar' => floatval($_POST['varsayilan_tutar'] ?? 0),
                    'gunluk_tutar' => floatval($_POST['gunluk_tutar'] ?? 0),
                    'gun_sayisi_otomatik' => intval($_POST['gun_sayisi_otomatik'] ?? 0),
                    'varsayilan_gun_sayisi' => intval($_POST['varsayilan_gun_sayisi'] ?? 26),
                    'oran' => floatval($_POST['oran'] ?? 0),
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
                $deger = floatval($_POST['deger'] ?? 0);
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
                $deger = floatval($_POST['deger'] ?? 0);
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
                $alt_limit = floatval(str_replace(['.', ','], ['', '.'], $_POST['alt_limit'] ?? '0'));
                $ust_limit_raw = trim($_POST['ust_limit'] ?? '');
                $ust_limit = $ust_limit_raw !== '' ? floatval(str_replace(['.', ','], ['', '.'], $ust_limit_raw)) : null;
                $vergi_orani = floatval($_POST['vergi_orani'] ?? 0);
                $aciklama = trim($_POST['dilim_aciklama'] ?? '');

                if ($dilim_no <= 0 || $dilim_no > 10) {
                    throw new Exception('Dilim numarası 1-10 arasında olmalıdır.');
                }

                if ($vergi_orani < 0 || $vergi_orani > 100) {
                    throw new Exception('Vergi oranı 0-100 arasında olmalıdır.');
                }

                if ($BordroParametre->addVergiDilimi($yil, $dilim_no, $alt_limit, $ust_limit, $vergi_orani, $aciklama)) {
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
                $alt_limit = floatval(str_replace(['.', ','], ['', '.'], $_POST['alt_limit'] ?? '0'));
                $ust_limit_raw = trim($_POST['ust_limit'] ?? '');
                $ust_limit = $ust_limit_raw !== '' ? floatval(str_replace(['.', ','], ['', '.'], $ust_limit_raw)) : null;
                $vergi_orani = floatval($_POST['vergi_orani'] ?? 0);
                $aciklama = trim($_POST['dilim_aciklama'] ?? '');

                $sql = $BordroParametre->getDb()->prepare("
                    UPDATE bordro_vergi_dilimleri 
                    SET yil = ?, dilim_no = ?, alt_limit = ?, ust_limit = ?, vergi_orani = ?, aciklama = ?
                    WHERE id = ?
                ");

                if ($sql->execute([$yil, $dilim_no, $alt_limit, $ust_limit, $vergi_orani, $aciklama, $id])) {
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

                $sql = $BordroParametre->getDb()->prepare("DELETE FROM bordro_vergi_dilimleri WHERE id = ?");

                if ($sql->execute([$id])) {
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
                        $personelData = $Personel->where('tc_kimlik_no', $tcNo);
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
                        $personelData = $Personel->where('tc_kimlik_no', $tcNo);
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
                                if ($BordroPersonel->addKesinti($personel_id, $donem_id, "Excel'den yüklendi", $tutar, $kod)) {
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
                        $personelData = $Personel->where('tc_kimlik_no', $tcNo);
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

                        // Güncelle
                        $updateData = [
                            'banka_odemesi' => $banka,
                            'sodexo_odemesi' => $sodexo,
                            'diger_odeme' => $diger
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
