<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Model\PersonelModel;
use App\Model\PersonelIzinleriModel;
use App\Model\DemirbasZimmetModel;
use App\Model\DemirbasModel;
use App\Model\PersonelEvrakModel;
use App\Model\PersonelKesintileriModel;
use App\Model\PersonelEkOdemelerModel;
use App\Model\PersonelIcralariModel;
use App\Model\BordroDonemModel;
use App\Model\UserModel;
use App\Model\AvansModel;
use App\Model\BordroPersonelModel;
use App\Model\PersonelGirisLogModel;
use App\Helper\Security;

$id = $_GET['id'] ?? 0;
$tab = $_GET['tab'] ?? '';

if ($id > 0) {
    $PersonelModel = new PersonelModel();
    $personel = $PersonelModel->find($id);
} else {
    $personel = null;
}

switch ($tab) {
    case 'izinler':
        $PersonelIzinleriModel = new PersonelIzinleriModel();
        $izinler = $PersonelIzinleriModel->getPersonelIzinleri($id);

        $entitlement = $PersonelIzinleriModel->calculateLeaveEntitlement($id);
        $toplam_hakedis = $entitlement['toplam_hakedis'];
        $kullanilan_izin = $entitlement['kullanilan_izin'];
        $kalan_izin = $entitlement['kalan_izin'];

        include_once __DIR__ . "/icerik/izinler.php";
        break;
    case 'zimmetler':
        include_once __DIR__ . "/icerik/zimmetler.php";
        break;
    case 'finansal_islemler':
        $AvansModel = new AvansModel();
        $avanslar = $AvansModel->getPersonelAvanslari($id);

        $BordroPersonelModel = new BordroPersonelModel();
        $bordrolar = $BordroPersonelModel->getPersonelBordrolari($id);

        $PersonelEkOdemelerModel = new \App\Model\PersonelEkOdemelerModel();
        $ek_odemeler = $PersonelEkOdemelerModel->getPersonelEkOdemeler($id);

        $PersonelKesintileriModel = new \App\Model\PersonelKesintileriModel();
        $kesintiler = $PersonelKesintileriModel->getPersonelKesintileri($id);

        // Dönemleri de getir (modal için)
        $BordroDonemModel = new \App\Model\BordroDonemModel();
        $donemler_raw = $BordroDonemModel->getAllDonemsForFilter();
        $acik_donemler = [];
        $tum_donemler = [];
        foreach ($donemler_raw as $d) {
            $tum_donemler[$d->id] = $d->donem_adi;
            if (isset($d->kapali_mi) && $d->kapali_mi == 0) {
                $acik_donemler[$d->id] = $d->donem_adi;
            }
        }

        include_once __DIR__ . "/icerik/finansal_islemler.php";
        break;
    case 'evraklar':
        include_once __DIR__ . "/icerik/evraklar.php";
        break;
    case 'ek_odemeler':
        // Filtre değerlerini yakala ve oturuma kaydet
        $filter_params = [
            'filter_ek_mode' => $_GET['filter_mode'] ?? $_GET['filter_ek_mode'] ?? $_SESSION['filter_ek_mode'] ?? 'donem',
            'filter_ek_baslangic' => $_GET['filter_ek_baslangic'] ?? $_SESSION['filter_ek_baslangic'] ?? '',
            'filter_ek_bitis' => $_GET['filter_ek_bitis'] ?? $_SESSION['filter_ek_bitis'] ?? '',
            'filter_ek_donem' => $_GET['filter_ek_donem'] ?? $_SESSION['filter_ek_donem'] ?? '',
            'filter_ek_ay_yil' => $_GET['filter_ek_ay_yil'] ?? $_SESSION['filter_ek_ay_yil'] ?? date('Y-m')
        ];

        // Oturumu güncelle
        foreach ($filter_params as $key => $val) {
            $_SESSION[$key] = $val;
            $_GET[$key] = $val;
        }

        $PersonelEkOdemelerModel = new PersonelEkOdemelerModel();
        // Model fonksiyonuna filtreleri gönder
        $ek_odemeler = $PersonelEkOdemelerModel->getPersonelEkOdemeler($id, $filter_params);

        // Açık dönemleri getir
        $BordroDonemModel = new BordroDonemModel();
        $donemler_raw = $BordroDonemModel->getAllDonemsForFilter();
        $acik_donemler = [];
        $tum_donemler = [];

        foreach ($donemler_raw as $d) {
            $formatter = new IntlDateFormatter('tr_TR', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'MMMM yyyy');
            $ay_adi = $formatter->format(strtotime($d->baslangic_tarihi));
            $donem_isim = $d->donem_adi ?? $ay_adi;
            
            $tum_donemler[$d->id] = $donem_isim;

            if (isset($d->kapali_mi) && $d->kapali_mi == 0) {
                $acik_donemler[$d->id] = $donem_isim;
            }
        }

        if (empty($acik_donemler)) {
            $acik_donemler[date('Y-m')] = date('m/Y') . ' (Otomatik)';
        }

        include_once __DIR__ . "/icerik/ek_odemeler.php";
        break;
    case 'kesintiler':
        // Filtre değerlerini yakala ve oturuma kaydet
        $filter_params = [
            'filter_kesinti_mode' => $_GET['filter_mode'] ?? $_GET['filter_kesinti_mode'] ?? $_SESSION['filter_kesinti_mode'] ?? 'donem',
            'filter_kesinti_baslangic' => $_GET['filter_kesinti_baslangic'] ?? $_SESSION['filter_kesinti_baslangic'] ?? '',
            'filter_kesinti_bitis' => $_GET['filter_kesinti_bitis'] ?? $_SESSION['filter_kesinti_bitis'] ?? '',
            'filter_kesinti_donem' => $_GET['filter_kesinti_donem'] ?? $_SESSION['filter_kesinti_donem'] ?? '',
            'filter_kesinti_ay_yil' => $_GET['filter_kesinti_ay_yil'] ?? $_SESSION['filter_kesinti_ay_yil'] ?? date('Y-m')
        ];

        // Oturumu güncelle
        foreach ($filter_params as $key => $val) {
            $_SESSION[$key] = $val;
            $_GET[$key] = $val;
        }

        $PersonelKesintileriModel = new PersonelKesintileriModel();
        // Model fonksiyonuna filtreleri gönder
        $kesintiler = $PersonelKesintileriModel->getPersonelKesintileri($id, $filter_params);

        // Açık dönemleri getir
        $BordroDonemModel = new BordroDonemModel();
        $donemler_raw = $BordroDonemModel->getAllDonemsForFilter();
        $acik_donemler = [];
        $tum_donemler = [];

        foreach ($donemler_raw as $d) {
            // Türkçe ay ismini al
            $formatter = new IntlDateFormatter('tr_TR', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'MMMM yyyy');
            $ay_adi = $formatter->format(strtotime($d->baslangic_tarihi));
            $donem_isim = $d->donem_adi ?? $ay_adi;
            
            $tum_donemler[$d->id] = $donem_isim;

            if (isset($d->kapali_mi) && $d->kapali_mi == 0) {
                $acik_donemler[$d->id] = $donem_isim;
            }
        }

        // Eğer hiç açık dönem yoksa manuel giriş için fallback (şimdiki ay)
        if (empty($acik_donemler)) {
            $acik_donemler[0] = date('m/Y') . ' (Dönem Yok)';
        }

        include_once __DIR__ . "/icerik/kesintiler.php";
        break;
    case 'icralar':
        $PersonelIcralariModel = new PersonelIcralariModel();
        $icralar = $PersonelIcralariModel->getPersonelIcralariWithKesintiler($id);
        include_once __DIR__ . "/icerik/icralar.php";
        break;

    case 'puantaj':
        include_once __DIR__ . "/icerik/puantaj.php";
        break;
    case 'giris_loglari':
        $GirisLogModel = new PersonelGirisLogModel();
        $db = $GirisLogModel->getDb();
        $stmt = $db->prepare("SELECT * FROM personel_giris_loglari WHERE personel_id = ? ORDER BY giris_tarihi DESC");
        $stmt->execute([$id]);
        $logs = $stmt->fetchAll(PDO::FETCH_OBJ);
        include_once __DIR__ . "/icerik/giris_loglari.php";
        break;
    default:
        echo "Geçersiz tab.";
        break;
}
?>