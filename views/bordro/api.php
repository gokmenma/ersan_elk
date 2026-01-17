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

                $Personel = new PersonelModel();
                $hesaplananSayisi = 0;

                foreach ($personel_ids as $bp_id) {
                    // Bordro personel kaydını al
                    $bp = $BordroPersonel->find(intval($bp_id));
                    if (!$bp)
                        continue;

                    // Personel bilgisini al
                    $personel = $Personel->find($bp->personel_id);
                    if (!$personel)
                        continue;

                    // Brüt maaş (personelden al veya varsayılan)
                    $brutMaas = floatval($personel->maas_tutari ?? 0);

                    if ($brutMaas <= 0) {
                        // Asgari ücret (2026 Ocak varsayımı)
                        $brutMaas = 22104.00;
                    }

                    // Ek Ödemeler ve Kesintiler
                    $toplamEkOdeme = $BordroPersonel->getDonemEkOdemeleri($bp->personel_id, $donem_id);
                    $toplamKesinti = $BordroPersonel->getDonemKesintileri($bp->personel_id, $donem_id);

                    // SGK Primleri
                    $sgkIsci = $brutMaas * 0.14; // %14
                    $issizlikIsci = $brutMaas * 0.01; // %1

                    // SGK Matrah
                    $sgkMatrah = $brutMaas - $sgkIsci - $issizlikIsci;

                    // Gelir Vergisi (İlk dilim %15 varsayımı - basitleştirilmiş hesap)
                    $gelirVergisi = $sgkMatrah * 0.15;

                    // Damga Vergisi
                    $damgaVergisi = $brutMaas * 0.00759;

                    // Net Maaş
                    // Formül: (Brüt - Kesintiler) + Ek Ödemeler - Özel Kesintiler
                    $netMaas = ($brutMaas - $sgkIsci - $issizlikIsci - $gelirVergisi - $damgaVergisi) + $toplamEkOdeme - $toplamKesinti;

                    // İşveren Maliyetleri
                    $sgkIsveren = $brutMaas * 0.205; // %20.5
                    $issizlikIsveren = $brutMaas * 0.02; // %2
                    $toplamMaliyet = $brutMaas + $sgkIsveren + $issizlikIsveren + $toplamEkOdeme; // Ek ödeme maliyete eklenir (basit mantık)

                    // Kaydet
                    $BordroPersonel->saveBordroHesaplama(intval($bp_id), [
                        'brut_maas' => round($brutMaas, 2),
                        'sgk_isci' => round($sgkIsci, 2),
                        'issizlik_isci' => round($issizlikIsci, 2),
                        'gelir_vergisi' => round($gelirVergisi, 2),
                        'damga_vergisi' => round($damgaVergisi, 2),
                        'net_maas' => round($netMaas, 2),
                        'sgk_isveren' => round($sgkIsveren, 2),
                        'issizlik_isveren' => round($issizlikIsveren, 2),
                        'toplam_maliyet' => round($toplamMaliyet, 2),
                        'toplam_kesinti' => round($toplamKesinti, 2),
                        'toplam_ek_odeme' => round($toplamEkOdeme, 2)
                    ]);

                    $hesaplananSayisi++;
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
                
                if ($bp->toplam_ek_odeme > 0) {
                    $html .= '<tr><td class="text-muted">Ek Ödemeler:</td><td class="text-success">+' . number_format($bp->toplam_ek_odeme, 2, ',', '.') . ' ₺</td></tr>';
                }
                if ($bp->toplam_kesinti > 0) {
                    $html .= '<tr><td class="text-muted">Özel Kesintiler:</td><td class="text-danger">-' . number_format($bp->toplam_kesinti, 2, ',', '.') . ' ₺</td></tr>';
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
