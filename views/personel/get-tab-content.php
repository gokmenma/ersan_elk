<?php
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
        $donemler_raw = $BordroDonemModel->getAllDonems(date('Y'));
        $acik_donemler = [];
        foreach ($donemler_raw as $d) {
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
        $PersonelEkOdemelerModel = new PersonelEkOdemelerModel();
        $ek_odemeler = $PersonelEkOdemelerModel->getPersonelEkOdemeler($id);

        // Açık dönemleri getir
        $BordroDonemModel = new BordroDonemModel();
        $donemler_raw = $BordroDonemModel->getAllDonems(date('Y'));
        $acik_donemler = [];

        foreach ($donemler_raw as $d) {
            if (isset($d->kapali_mi) && $d->kapali_mi == 0) {
                $key = date('Y-m', strtotime($d->baslangic_tarihi));
                $formatter = new IntlDateFormatter('tr_TR', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'MMMM yyyy');
                $ay_adi = $formatter->format(strtotime($d->baslangic_tarihi));
                $acik_donemler[$d->id] = $d->donem_adi ?? $ay_adi;
            }
        }

        //Helper::dd($acik_donemler);

        if (empty($acik_donemler)) {
            $acik_donemler[date('Y-m')] = date('m/Y') . ' (Otomatik)';
        }

        include_once __DIR__ . "/icerik/ek_odemeler.php";
        break;
    case 'kesintiler':
        $PersonelKesintileriModel = new PersonelKesintileriModel();
        $kesintiler = $PersonelKesintileriModel->getPersonelKesintileri($id);

        // Açık dönemleri getir
        $BordroDonemModel = new BordroDonemModel();
        $donemler_raw = $BordroDonemModel->getAllDonems(date('Y'));
        $acik_donemler = [];

        // Önceki yıldan kalan açık dönem olabilir mi? Genelde hayır ama kontrol edilebilir.
        // Şimdilik sadece bu yılın açık dönemlerini alıyoruz.
        foreach ($donemler_raw as $d) {
            if (isset($d->kapali_mi) && $d->kapali_mi == 0) {
                // Türkçe ay ismini al
                $formatter = new IntlDateFormatter('tr_TR', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'MMMM yyyy');
                $ay_adi = $formatter->format(strtotime($d->baslangic_tarihi));
                $acik_donemler[$d->id] = $d->donem_adi ?? $ay_adi;
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
        $icralar = $PersonelIcralariModel->getPersonelIcralari($id);
        include_once __DIR__ . "/icerik/icralar.php";
        break;
    case 'puantaj':
        include_once __DIR__ . "/icerik/puantaj.php";
        break;
    default:
        echo "Geçersiz tab.";
        break;
}
?>