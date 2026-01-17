<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

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

        $toplam_hakedis = 0;
        $kullanilan_izin = 0;

        $parseDate = function ($value) {
            $value = trim((string) $value);
            if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                return null;
            }

            $formats = [
                'Y-m-d',
                'Y-m-d H:i:s',
                'd.m.Y',
                'd.m.Y H:i',
                'd/m/Y',
                'd/m/Y H:i',
                'd-m-Y',
                'd-m-Y H:i',
            ];

            foreach ($formats as $format) {
                $dt = DateTime::createFromFormat($format, $value);
                if (!$dt) {
                    continue;
                }
                $errors = DateTime::getLastErrors();
                if (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                    $year = (int) $dt->format('Y');
                    $currentYear = (int) (new DateTime())->format('Y');
                    if ($year >= 1950 && $year <= ($currentYear + 1)) {
                        return $dt;
                    }
                }
            }

            try {
                $dt = new DateTime($value);
                $year = (int) $dt->format('Y');
                $currentYear = (int) (new DateTime())->format('Y');
                if ($year >= 1950 && $year <= ($currentYear + 1)) {
                    return $dt;
                }
            } catch (Exception $e) {
            }

            return null;
        };

        $toNumber = function ($value) {
            $value = trim((string) $value);
            if ($value === '') {
                return 0.0;
            }
            return (float) str_replace(',', '.', $value);
        };

        if ($personel) {
            $giris = $parseDate($personel->ise_giris_tarihi ?? '');
            $dogum = $parseDate($personel->dogum_tarihi ?? '');

            if ($giris) {
                $bugun = new DateTime();
                if ($giris <= $bugun) {
                    $calisma_yili = (int) $giris->diff($bugun)->y;
                    for ($i = 1; $i <= $calisma_yili; $i++) {
                        if ($i >= 1 && $i <= 5) {
                            $hakedis = 14;
                        } elseif ($i > 5 && $i < 15) {
                            $hakedis = 20;
                        } else {
                            $hakedis = 26;
                        }

                        if ($dogum) {
                            $yil_sonu = (clone $giris)->modify("+$i years");
                            $yas = (int) $dogum->diff($yil_sonu)->y;
                            if (($yas < 18 || $yas > 50) && $hakedis < 20) {
                                $hakedis = 20;
                            }
                        }

                        $toplam_hakedis += $hakedis;
                    }
                }
            }
        }

        if (!empty($izinler)) {
            foreach ($izinler as $izin) {
                if (isset($izin->yillik_izne_etki) && $izin->yillik_izne_etki == 'Dus') {
                    $durum = $izin->son_durum ?? '';
                    if (in_array($durum, ['KabulEdildi', 'Onaylandı'])) {
                        $kullanilan_izin += $toNumber($izin->sure ?? 0);
                    }
                }
            }
        }

        $kalan_izin = max(0, $toplam_hakedis - $kullanilan_izin);

        include_once __DIR__ . "/icerik/izinler.php";
        break;
    case 'zimmetler':
        include_once __DIR__ . "/icerik/zimmetler.php";
        break;
    case 'finansal_islemler':
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
                setlocale(LC_TIME, 'tr_TR.UTF-8');
                $ay_adi = strftime('%B %Y', strtotime($d->baslangic_tarihi));
                $acik_donemler[$key] = $d->donem_adi ?? $ay_adi;
            }
        }

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
        $donemler_raw = $BordroDonemModel->getAllDonems(date('Y')); // Şimdilik bu yılın dönemlerini çekiyoruz
        $acik_donemler = [];

        // Önceki yıldan kalan açık dönem olabilir mi? Genelde hayır ama kontrol edilebilir.
        // Şimdilik sadece bu yılın açık dönemlerini alıyoruz.
        foreach ($donemler_raw as $d) {
            if (isset($d->kapali_mi) && $d->kapali_mi == 0) {
                // YYYY-MM formatında key oluştur
                $key = date('Y-m', strtotime($d->baslangic_tarihi));
                // Türkçe ay ismini al
                setlocale(LC_TIME, 'tr_TR.UTF-8');
                $ay_adi = strftime('%B %Y', strtotime($d->baslangic_tarihi));
                $acik_donemler[$key] = $d->donem_adi ?? $ay_adi;
            }
        }

        // Eğer hiç açık dönem yoksa manuel giriş için fallback (şimdiki ay)
        if (empty($acik_donemler)) {
            $acik_donemler[date('Y-m')] = date('m/Y') . ' (Otomatik)';
        }

        include_once __DIR__ . "/icerik/kesintiler.php";
        break;
    case 'icralar':
        $PersonelIcralariModel = new PersonelIcralariModel();
        $icralar = $PersonelIcralariModel->getPersonelIcralari($id);
        include_once __DIR__ . "/icerik/icralar.php";
        break;
    default:
        echo "Geçersiz tab.";
        break;
}
?>