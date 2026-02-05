<?php
/**
 * Personel Takip API
 * Yönetici tarafı için API endpoint'leri
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once dirname(dirname(__DIR__)) . '/Autoloader.php';

use App\Model\PersonelHareketleriModel;
use App\Model\PersonelModel;
use App\Helper\Security;

// Response helper
function response($success, $data = null, $message = '')
{
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $HareketModel = new PersonelHareketleriModel();
    $PersonelModel = new PersonelModel();

    switch ($action) {

        // Özet istatistikler
        case 'getOzet':
            $firma_id = $_SESSION['firma_id'] ?? null;
            $personeller = $HareketModel->getTumPersonelDurumu($firma_id);

            $gorevde = 0;
            $tamamladi = 0;
            $baslamadi = 0;

            foreach ($personeller as $p) {
                if ($p->durum === 'aktif')
                    $gorevde++;
                elseif ($p->durum === 'bitti')
                    $tamamladi++;
                else
                    $baslamadi++;
            }

            response(true, [
                'gorevde' => $gorevde,
                'tamamladi' => $tamamladi,
                'baslamadi' => $baslamadi,
                'toplam' => count($personeller)
            ]);
            break;

        // Tüm personel durumları (tablo için)
        case 'getPersonelDurumlari':
            $firma_id = $_SESSION['firma_id'] ?? null;
            $personeller = $HareketModel->getTumPersonelDurumu($firma_id);

            $data = [];
            foreach ($personeller as $p) {
                $durum_badge = '';
                switch ($p->durum) {
                    case 'aktif':
                        $durum_badge = '<span class="badge bg-success">Görevde</span>';
                        break;
                    case 'bitti':
                        $durum_badge = '<span class="badge bg-primary">Tamamladı</span>';
                        break;
                    default:
                        $durum_badge = '<span class="badge bg-secondary">Başlamadı</span>';
                }

                $foto = $p->foto
                    ? '<img src="uploads/personel/' . htmlspecialchars($p->foto) . '" class="rounded-circle" width="32" height="32" style="object-fit:cover;">'
                    : '<div class="avatar-xs"><span class="avatar-title rounded-circle bg-soft-primary text-primary">' . mb_substr($p->adi_soyadi, 0, 1) . '</span></div>';

                $baslama_saat = $p->son_baslama ? date('H:i', strtotime($p->son_baslama)) : '-';
                $bitis_saat = $p->son_bitis ? date('H:i', strtotime($p->son_bitis)) : '-';

                // Harita linki
                $konum_link = '';
                if ($p->son_enlem && $p->son_boylam) {
                    $konum_link = '<a href="https://www.google.com/maps?q=' . $p->son_enlem . ',' . $p->son_boylam . '" target="_blank" class="btn btn-sm btn-soft-info" title="Haritada Göster"><i class="bx bx-map"></i></a>';
                }

                $data[] = [
                    'id' => Security::encrypt($p->personel_id),
                    'foto' => $foto,
                    'adi_soyadi' => htmlspecialchars($p->adi_soyadi),
                    'durum' => $durum_badge,
                    'baslama' => $baslama_saat,
                    'bitis' => $bitis_saat,
                    'konum' => $konum_link,
                    'islemler' => '<button class="btn btn-sm btn-soft-primary btn-detay" data-id="' . Security::encrypt($p->personel_id) . '"><i class="bx bx-history"></i> Geçmiş</button>'
                ];
            }

            response(true, $data);
            break;

        // Personel hareket geçmişi
        case 'getHareketGecmisi':
            $personel_id = isset($_POST['personel_id']) ? Security::decrypt($_POST['personel_id']) : 0;
            $baslangic = $_POST['baslangic'] ?? date('Y-m-d', strtotime('-7 days'));
            $bitis = $_POST['bitis'] ?? date('Y-m-d');

            if (!$personel_id) {
                response(false, null, 'Personel ID gerekli');
            }

            $personel = $PersonelModel->find($personel_id);
            if (!$personel) {
                response(false, null, 'Personel bulunamadı');
            }

            $hareketler = $HareketModel->getRapor($personel_id, $baslangic, $bitis);

            $data = [];
            foreach ($hareketler as $h) {
                $tip_badge = $h->islem_tipi === 'BASLA'
                    ? '<span class="badge bg-success">Başladı</span>'
                    : '<span class="badge bg-danger">Bitirdi</span>';

                $konum_link = '<a href="https://www.google.com/maps?q=' . $h->konum_enlem . ',' . $h->konum_boylam . '" target="_blank" class="text-info"><i class="bx bx-map-pin"></i> Haritada Gör</a>';

                $data[] = [
                    'tarih' => date('d.m.Y', strtotime($h->zaman)),
                    'saat' => date('H:i:s', strtotime($h->zaman)),
                    'islem' => $tip_badge,
                    'konum' => $konum_link,
                    'hassasiyet' => $h->konum_hassasiyeti ? round($h->konum_hassasiyeti) . ' m' : '-',
                    'cihaz' => '<span class="text-muted small" title="' . htmlspecialchars($h->cihaz_bilgisi ?? '') . '">' . mb_substr($h->cihaz_bilgisi ?? '-', 0, 30) . '...</span>'
                ];
            }

            response(true, [
                'personel' => [
                    'adi_soyadi' => $personel->adi_soyadi,
                    'foto' => $personel->foto
                ],
                'hareketler' => $data
            ]);
            break;

        // Tarih aralığı raporu
        case 'getRapor':
            $baslangic = $_POST['baslangic'] ?? date('Y-m-d', strtotime('-30 days'));
            $bitis = $_POST['bitis'] ?? date('Y-m-d');
            $firma_id = $_SESSION['firma_id'] ?? null;

            $hareketler = $HareketModel->getRapor(null, $baslangic, $bitis, $firma_id);

            $data = [];
            foreach ($hareketler as $h) {
                $tip_badge = $h->islem_tipi === 'BASLA'
                    ? '<span class="badge bg-success">Başladı</span>'
                    : '<span class="badge bg-danger">Bitirdi</span>';

                $data[] = [
                    'personel' => htmlspecialchars($h->adi_soyadi),
                    'tarih' => date('d.m.Y', strtotime($h->zaman)),
                    'saat' => date('H:i:s', strtotime($h->zaman)),
                    'islem' => $tip_badge,
                    'enlem' => $h->konum_enlem,
                    'boylam' => $h->konum_boylam
                ];
            }

            response(true, $data);
            break;

        // Harita verileri
        case 'getHaritaVerileri':
            $firma_id = $_SESSION['firma_id'] ?? null;
            $personeller = $HareketModel->getTumPersonelDurumu($firma_id);

            $markers = [];
            foreach ($personeller as $p) {
                if ($p->son_enlem && $p->son_boylam) {
                    $markers[] = [
                        'id' => $p->personel_id,
                        'adi_soyadi' => $p->adi_soyadi,
                        'lat' => (float) $p->son_enlem,
                        'lng' => (float) $p->son_boylam,
                        'durum' => $p->durum,
                        'durum_text' => $p->durum_text,
                        'foto' => $p->foto
                    ];
                }
            }

            response(true, $markers);
            break;

        default:
            response(false, null, 'Geçersiz işlem');
    }

} catch (Exception $e) {
    response(false, null, 'Bir hata oluştu: ' . $e->getMessage());
}
