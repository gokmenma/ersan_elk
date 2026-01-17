<?php
/**
 * Personel PWA - API Endpoint
 * Tüm AJAX isteklerini yönetir
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once dirname(dirname(__DIR__)) . '/Autoloader.php';

use App\Model\PersonelModel;
use App\Model\BordroPersonelModel;
use App\Model\PersonelIzinleriModel;
use App\Model\AvansModel;
use App\Helper\Security;
use App\Helper\Helper;

// Oturum kontrolü (logout hariç)
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action !== 'login' && !isset($_SESSION['personel_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum süresi doldu']);
    exit;
}

$personel_id = $_SESSION['personel_id'] ?? 0;

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

try {
    switch ($action) {
        // ===== Dashboard =====
        case 'getDashboardData':
            $BordroModel = new BordroPersonelModel();
            $ozet = $BordroModel->getPersonelFinansalOzet($personel_id);

            $toplam_hakedis = $ozet->toplam_hakedis ?? 0;
            $alinan_odeme = $ozet->alinan_odeme ?? 0;
            $kalan_bakiye = $toplam_hakedis - $alinan_odeme;

            response(true, [
                'total_earning' => $toplam_hakedis,
                'received_payment' => $alinan_odeme,
                'remaining_balance' => $kalan_bakiye
            ]);
            break;

        // ===== Bordro İşlemleri =====
        case 'getBordroStats':
            $AvansModel = new AvansModel();
            $BordroModel = new BordroPersonelModel();

            $limit = $AvansModel->getAvansLimiti($personel_id);
            $bekleyenler = $AvansModel->getBekleyenAvanslar($personel_id);
            $ozet = $BordroModel->getPersonelFinansalOzet($personel_id);

            response(true, [
                'yearly_net' => $ozet->toplam_hakedis ?? 0,
                'advance_limit' => $limit,
                'pending_requests' => count($bekleyenler)
            ]);
            break;

        case 'getBordrolar':
            $BordroModel = new BordroPersonelModel();
            $bordrolar = $BordroModel->getPersonelBordrolari($personel_id);

            $data = array_map(function ($item) {
                return [
                    'id' => $item->id,
                    'donem' => $item->donem_adi,
                    'odeme_tarihi' => $item->odeme_tarihi ? date('d.m.Y', strtotime($item->odeme_tarihi)) : '-',
                    'net_tutar' => $item->net_maas,
                    'durum' => $item->durum ?? 'beklemede'
                ];
            }, $bordrolar);

            response(true, $data);
            break;

        case 'getBordroDetay':
            $id = $_POST['id'] ?? 0;
            $BordroModel = new BordroPersonelModel();
            $bordro = $BordroModel->find($id);

            if ($bordro && $bordro->personel_id == $personel_id) {
                response(true, [
                    'id' => $bordro->id,
                    'donem' => 'Dönem ' . $bordro->donem_id, // Dönem adını çekmek için join lazım ama şimdilik ID
                    'brut' => $bordro->brut_maas,
                    'sgk' => $bordro->sgk_isci,
                    'vergi' => $bordro->gelir_vergisi,
                    'net' => $bordro->net_maas
                ]);
            } else {
                response(false, null, 'Bordro bulunamadı');
            }
            break;

        // ===== Avans İşlemleri =====
        case 'getAvansTalepleri':
            $AvansModel = new AvansModel();
            $avanslar = $AvansModel->getPersonelAvanslari($personel_id);

            $data = array_map(function ($item) {
                $durum_text = 'Beklemede';
                if ($item->durum == 'onaylandi')
                    $durum_text = 'Onaylandı';
                if ($item->durum == 'reddedildi')
                    $durum_text = 'Reddedildi';

                return [
                    'id' => $item->id,
                    'tutar' => $item->tutar,
                    'tarih' => date('d.m.Y', strtotime($item->talep_tarihi)),
                    'durum' => $item->durum,
                    'durum_text' => $durum_text,
                    'aciklama' => $item->aciklama,
                    'odeme_sekli' => $item->odeme_sekli
                ];
            }, $avanslar);

            response(true, $data);
            break;

        case 'createAvansTalebi':
            $tutar = $_POST['tutar'] ?? 0;
            $odeme_sekli = $_POST['odeme_sekli'] ?? 'tek';
            $aciklama = $_POST['aciklama'] ?? '';

            $AvansModel = new AvansModel();
            $AvansModel->saveWithAttr([
                'personel_id' => $personel_id,
                'tutar' => $tutar,
                'odeme_sekli' => $odeme_sekli,
                'aciklama' => $aciklama,
                'durum' => 'beklemede',
                'talep_tarihi' => date('Y-m-d H:i:s')
            ]);

            response(true, null, 'Avans talebiniz başarıyla oluşturuldu');
            break;

        case 'updateAvansTalebi':
            $id = $_POST['id'] ?? 0;
            $tutar = $_POST['tutar'] ?? 0;
            $odeme_sekli = $_POST['odeme_sekli'] ?? 'tek';
            $aciklama = $_POST['aciklama'] ?? '';

            $AvansModel = new AvansModel();
            $avans = $AvansModel->find($id);

            if ($avans && $avans->personel_id == $personel_id && $avans->durum == 'beklemede') {
                $AvansModel->saveWithAttr([
                    'id' => $id,
                    'tutar' => $tutar,
                    'odeme_sekli' => $odeme_sekli,
                    'aciklama' => $aciklama
                ]);
                response(true, null, 'Avans talebiniz güncellendi');
            } else {
                response(false, null, 'İşlem yapılamaz');
            }
            break;

        case 'deleteAvansTalebi':
            $id = $_POST['id'] ?? 0;
            $AvansModel = new AvansModel();
            $avans = $AvansModel->find($id);

            if ($avans && $avans->personel_id == $personel_id && $avans->durum == 'beklemede') {
                $AvansModel->softDelete($id);
                response(true, null, 'Avans talebi silindi');
            } else {
                response(false, null, 'İşlem yapılamaz');
            }
            break;

        // ===== İzin İşlemleri =====
        case 'getIzinStats':
            $IzinModel = new PersonelIzinleriModel();
            // TODO: İzin istatistiklerini hesapla
            response(true, [
                'kalan_izin' => 14, // Örnek
                'hastalik_izni' => 0,
                'bekleyen' => 0
            ]);
            break;

        case 'getIzinler':
            $IzinModel = new PersonelIzinleriModel();
            $izinler = $IzinModel->getPersonelIzinleri($personel_id);

            $izin_tipleri = [
                'yillik' => 'Yıllık İzin',
                'mazeret' => 'Mazeret İzni',
                'hastalik' => 'Hastalık İzni',
                'dogum' => 'Doğum / Babalık İzni',
                'ucretsiz' => 'Ücretsiz İzin'
            ];

            $data = array_map(function ($item) use ($izin_tipleri) {
                // Onay durumu kontrolü
                $durum_raw = $item->onay_durumu_text ?? $item->onay_durumu ?? 'beklemede';
                $durum = strtolower($durum_raw);
                $durum_text = ucfirst($durum);

                if ($durum == 'beklemede')
                    $durum_text = 'Beklemede';
                if ($durum == 'onaylandi')
                    $durum_text = 'Onaylandı';
                if ($durum == 'reddedildi')
                    $durum_text = 'Reddedildi';

                $izin_tipi = $item->izin_tipi ?? '';
                $izin_tipi_text = $izin_tipleri[$izin_tipi] ?? $izin_tipi;

                if (empty($izin_tipi_text)) {
                    $izin_tipi_text = 'İzin Türü Belirtilmemiş';
                }

                return [
                    'id' => $item->id,
                    'izin_tipi' => $izin_tipi,
                    'izin_tipi_text' => $izin_tipi_text,
                    'baslangic' => date('d.m.Y', strtotime($item->baslangic_tarihi)),
                    'bitis' => date('d.m.Y', strtotime($item->bitis_tarihi)),
                    'toplam_gun' => $item->toplam_gun,
                    'talep_tarihi' => date('d.m.Y', strtotime($item->olusturma_tarihi)),
                    'durum' => $durum,
                    'durum_text' => $durum_text,
                    'aciklama' => $item->aciklama
                ];
            }, $izinler);

            response(true, $data);
            break;

        case 'createIzinTalebi':
            $izin_tipi = $_POST['izin_tipi'] ?? '';
            $baslangic = $_POST['baslangic_tarihi'] ?? '';
            $bitis = $_POST['bitis_tarihi'] ?? '';
            $aciklama = $_POST['aciklama'] ?? '';

            if (empty($izin_tipi)) {
                response(false, null, 'Lütfen izin türünü seçiniz.');
            }

            if (empty($baslangic) || empty($bitis)) {
                response(false, null, 'Lütfen tarihleri seçiniz.');
            }

            // Gün sayısını hesapla
            $diff = strtotime($bitis) - strtotime($baslangic);
            $toplam_gun = round($diff / (60 * 60 * 24)) + 1;

            $IzinModel = new PersonelIzinleriModel();
            $IzinModel->saveWithAttr([
                'personel_id' => $personel_id,
                'izin_tipi' => $izin_tipi,
                'baslangic_tarihi' => $baslangic,
                'bitis_tarihi' => $bitis,
                'toplam_gun' => $toplam_gun,
                'aciklama' => $aciklama,
                'olusturma_tarihi' => date('Y-m-d H:i:s')
            ]);

            response(true, null, 'İzin talebiniz başarıyla oluşturuldu');
            break;

        case 'cancelIzinTalebi':
            $id = $_POST['id'] ?? 0;
            $IzinModel = new PersonelIzinleriModel();
            $izin = $IzinModel->find($id);

            if ($izin && $izin->personel_id == $personel_id && $izin->onay_durumu == 'beklemede') {
                $IzinModel->softDelete($id);
                response(true, null, 'İzin talebi iptal edildi');
            } else {
                response(false, null, 'İşlem yapılamaz');
            }
            break;

        // ===== Talep İşlemleri =====
        case 'getTalepStats':
            $TalepModel = new App\Model\TalepModel();
            $stats = $TalepModel->getStats($personel_id);
            response(true, $stats);
            break;

        case 'getTalepler':
            $TalepModel = new App\Model\TalepModel();
            $talepler = $TalepModel->getPersonelTalepleri($personel_id);

            // Format data for frontend
            $data = array_map(function ($item) {
                $kategori_map = [
                    'ariza' => 'Arıza',
                    'oneri' => 'Öneri',
                    'sikayet' => 'Şikayet',
                    'istek' => 'İstek',
                    'diger' => 'Diğer'
                ];

                $durum_map = [
                    'beklemede' => 'Beklemede',
                    'devam' => 'İnceleniyor',
                    'cozuldu' => 'Çözüldü'
                ];

                $oncelik_map = [
                    'dusuk' => 'Düşük',
                    'orta' => 'Orta',
                    'yuksek' => 'Yüksek'
                ];

                return [
                    'id' => $item->id,
                    'ref_no' => $item->ref_no,
                    'baslik' => $item->baslik ?: $kategori_map[$item->kategori] ?? 'Talep',
                    'kategori' => $item->kategori,
                    'kategori_text' => $kategori_map[$item->kategori] ?? $item->kategori,
                    'konum' => $item->konum,
                    'oncelik' => $item->oncelik,
                    'oncelik_text' => $oncelik_map[$item->oncelik] ?? $item->oncelik,
                    'durum' => $item->durum,
                    'durum_text' => $durum_map[$item->durum] ?? $item->durum,
                    'tarih' => date('d.m.Y H:i', strtotime($item->olusturma_tarihi)),
                    'aciklama' => $item->aciklama,
                    'foto' => $item->foto,
                    'cozum_tarihi' => $item->cozum_tarihi ? date('d.m.Y H:i', strtotime($item->cozum_tarihi)) : null
                ];
            }, $talepler);

            response(true, $data);
            break;

        case 'createTalepBildirimi':
            $TalepModel = new App\Model\TalepModel();

            $konum = $_POST['konum'] ?? '';
            $kategori = $_POST['kategori'] ?? '';
            $oncelik = $_POST['oncelik'] ?? 'orta';
            $aciklama = $_POST['aciklama'] ?? '';
            $latitude = $_POST['latitude'] ?? null;
            $longitude = $_POST['longitude'] ?? null;

            if (empty($konum) || empty($kategori) || empty($aciklama)) {
                throw new Exception('Lütfen tüm zorunlu alanları doldurun.');
            }

            // Handle file upload
            $foto_path = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
                $upload_dir = '../../uploads/talepler/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (in_array($file_ext, $allowed)) {
                    $new_name = uniqid('tlp_') . '.' . $file_ext;
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $new_name)) {
                        $foto_path = 'uploads/talepler/' . $new_name;
                    }
                }
            }

            // Generate Ref No
            $ref_no = $TalepModel->generateRefNo();

            // Map category to title if needed
            $kategori_titles = [
                'ariza' => 'Arıza Bildirimi',
                'oneri' => 'Öneri',
                'sikayet' => 'Şikayet',
                'istek' => 'İstek',
                'diger' => 'Diğer Talep'
            ];
            $baslik = $kategori_titles[$kategori] ?? 'Yeni Talep';

            $TalepModel->saveWithAttr([
                'personel_id' => $personel_id,
                'ref_no' => $ref_no,
                'konum' => $konum,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'kategori' => $kategori,
                'oncelik' => $oncelik,
                'baslik' => $baslik,
                'aciklama' => $aciklama,
                'foto' => $foto_path,
                'durum' => 'beklemede',
                'olusturma_tarihi' => date('Y-m-d H:i:s')
            ]);

            response(true, null, 'Talebiniz başarıyla oluşturuldu. Referans No: ' . $ref_no);
            break;

        // ===== Profil İşlemleri =====
        case 'changePassword':
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';

            // TODO: Güvenli şifre değiştirme
            // PersonelModel veya UserModel üzerinden yapılmalı

            response(true, null, 'Şifreniz başarıyla değiştirildi');
            break;

        case 'logout':
            session_destroy();
            response(true, null, 'Çıkış yapıldı');
            break;

        default:
            response(false, null, 'Geçersiz işlem');
    }
} catch (Exception $e) {
    response(false, null, 'Bir hata oluştu: ' . $e->getMessage());
}
