<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\BordroDonemModel;
use App\Model\BordroPersonelModel;
use App\Model\PersonelModel;

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
                $baslangic_tarihi = $_POST['baslangic_tarihi'] ?? '';
                $bitis_tarihi = $_POST['bitis_tarihi'] ?? '';

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

                // Dönemi oluştur
                $donemId = $BordroDonem->createDonem([
                    'donem_adi' => $donem_adi,
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

                // Bordro Bilgileri
                $html .= '<div class="col-md-6">';
                $html .= '<h6 class="border-bottom pb-2 mb-3"><i class="bx bx-money me-1"></i>Bordro Hesaplaması</h6>';
                $html .= '<table class="table table-sm">';
                $html .= '<tr><td class="text-muted">Brüt Maaş:</td><td class="fw-bold text-primary">' . ($bp->brut_maas ? number_format($bp->brut_maas, 2, ',', '.') . ' ₺' : '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">SGK İşçi (%14):</td><td class="text-danger">-' . ($bp->sgk_isci ? number_format($bp->sgk_isci, 2, ',', '.') . ' ₺' : '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">İşsizlik İşçi (%1):</td><td class="text-danger">-' . ($bp->issizlik_isci ? number_format($bp->issizlik_isci, 2, ',', '.') . ' ₺' : '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">Gelir Vergisi:</td><td class="text-danger">-' . ($bp->gelir_vergisi ? number_format($bp->gelir_vergisi, 2, ',', '.') . ' ₺' : '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">Damga Vergisi:</td><td class="text-danger">-' . ($bp->damga_vergisi ? number_format($bp->damga_vergisi, 2, ',', '.') . ' ₺' : '-') . '</td></tr>';

                // Anlık Ek Ödeme ve Kesintileri Çek
                $guncelEkOdeme = $BordroPersonel->getDonemEkOdemeleri($bp->personel_id, $bp->donem_id);
                $guncelKesinti = $BordroPersonel->getDonemKesintileri($bp->personel_id, $bp->donem_id);

                if ($guncelEkOdeme > 0) {
                    $html .= '<tr><td class="text-muted">Ek Ödemeler:</td><td class="text-success">+' . number_format($guncelEkOdeme, 2, ',', '.') . ' ₺</td></tr>';
                }
                if ($guncelKesinti > 0) {
                    $html .= '<tr><td class="text-muted">Özel Kesintiler:</td><td class="text-danger">-' . number_format($guncelKesinti, 2, ',', '.') . ' ₺</td></tr>';
                }

                $html .= '<tr class="table-success"><td class="fw-bold">Net Maaş:</td><td class="fw-bold text-success fs-5">' . ($bp->net_maas ? number_format($bp->net_maas, 2, ',', '.') . ' ₺' : '-') . '</td></tr>';
                $html .= '</table>';
                $html .= '</div>';

                // İşveren Maliyetleri
                $html .= '<div class="col-12 mt-3">';
                $html .= '<h6 class="border-bottom pb-2 mb-3"><i class="bx bx-building me-1"></i>İşveren Maliyetleri</h6>';
                $html .= '<div class="row text-center">';
                $html .= '<div class="col-md-4"><div class="border rounded p-3"><small class="text-muted d-block">SGK İşveren (%20.5)</small><span class="fs-5 fw-bold text-warning">' . ($bp->sgk_isveren ? number_format($bp->sgk_isveren, 2, ',', '.') . ' ₺' : '-') . '</span></div></div>';
                $html .= '<div class="col-md-4"><div class="border rounded p-3"><small class="text-muted d-block">İşsizlik İşveren (%2)</small><span class="fs-5 fw-bold text-warning">' . ($bp->issizlik_isveren ? number_format($bp->issizlik_isveren, 2, ',', '.') . ' ₺' : '-') . '</span></div></div>';
                $html .= '<div class="col-md-4"><div class="border rounded p-3 bg-primary bg-opacity-10"><small class="text-muted d-block">Toplam Maliyet</small><span class="fs-5 fw-bold text-primary">' . ($bp->toplam_maliyet ? number_format($bp->toplam_maliyet, 2, ',', '.') . ' ₺' : '-') . '</span></div></div>';
                $html .= '</div>';
                $html .= '</div>';

                $html .= '</div>';

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

            // Personel Gelir Ekle
            case 'personel-gelir-ekle':
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');
                $tutar = floatval($_POST['tutar'] ?? 0);
                $tur = trim($_POST['ek_odeme_tur'] ?? 'diger'); // Tür parametresi eklendi

                if ($personel_id <= 0 || $donem_id <= 0) {
                    throw new Exception('Geçersiz personel veya dönem.');
                }
                // Açıklama zorunlu değil artık, opsiyonel olabilir veya tür seçildiyse açıklama boş olabilir.
                // Ancak mevcut yapıda açıklama inputu var.

                if ($tutar <= 0) {
                    throw new Exception('Tutar 0\'dan büyük olmalıdır.');
                }

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
                break;

            // Personel Kesinti Ekle
            case 'personel-kesinti-ekle':
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');
                $tutar = floatval($_POST['tutar'] ?? 0);
                $tur = trim($_POST['kesinti_tur'] ?? 'diger'); // Tür parametresi eklendi

                if ($personel_id <= 0 || $donem_id <= 0) {
                    throw new Exception('Geçersiz personel veya dönem.');
                }

                if ($tutar <= 0) {
                    throw new Exception('Tutar 0\'dan büyük olmalıdır.');
                }

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
