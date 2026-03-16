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
    if (ob_get_length()) ob_clean();
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

    $db = (new \App\Core\Db())->db;

    switch ($action) {

        // Özet istatistikler
        case 'getOzet':
            $firma_id = $_SESSION['firma_id'] ?? null;
            $departman = $_POST['departman'] ?? null;
            $personeller = $HareketModel->getTumPersonelDurumu($firma_id, null, $departman);

            $gorevde = 0;
            $tamamladi = 0;
            $baslamadi = 0;
            $gec_kalan = 0;
            $limit_saat = '08:30';

            $gun = date('Y-m-d');
            $now = new DateTime();

            try {
                $limit_time = new DateTime($gun . ' ' . $limit_saat);
            } catch (Exception $e) {
                $limit_time = new DateTime($gun . ' 08:30');
            }

            foreach ($personeller as $p) {
                if ($p->durum === 'aktif') {
                    $gorevde++;
                    // Geç başlama kontrolü
                    if (!empty($p->son_baslama) && $p->son_baslama !== '0000-00-00 00:00:00') {
                        try {
                            $baslama_dt = new DateTime($p->son_baslama);
                            if ($baslama_dt->format('Y-m-d') === $gun && $baslama_dt->format('H:i') > $limit_saat) {
                                $gec_kalan++;
                            }
                        } catch (Exception $e) {
                            // Hatalı tarih, pas geç
                        }
                    }
                } elseif ($p->durum === 'bitti') {
                    $tamamladi++;
                } else {
                    $baslamadi++;
                    // Başlamamış ve saat geçmiş (bugün için)
                    if ($now > $limit_time) {
                        $gec_kalan++;
                    }
                }
            }

            response(true, [
                'gorevde' => $gorevde,
                'tamamladi' => $tamamladi,
                'baslamadi' => $baslamadi,
                'gec_kalan' => $gec_kalan,
                'toplam' => count($personeller)
            ]);
            break;

        // Tüm personel durumları (tablo için)
        case 'getPersonelDurumlari':
            $firma_id = $_SESSION['firma_id'] ?? null;
            $departman = $_POST['departman'] ?? null;
            $personeller = $HareketModel->getTumPersonelDurumu($firma_id, null, $departman);

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
                    ? '<img src="' . htmlspecialchars($p->foto) . '" class="rounded-circle" width="32" height="32" style="object-fit:cover;">'
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
                    'departman' => htmlspecialchars($p->departman ?? '-'),
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
            $tumPersoneller = $_POST['tumPersoneller'] ?? '0';
            $viewType = $_POST['viewType'] ?? 'gorev'; // gorev veya anlik
            $departman = $_POST['departman'] ?? null;

            $personeller = $HareketModel->getTumPersonelDurumu($firma_id, null, $departman);

            $markers = [];
            foreach ($personeller as $p) {
                $lat = null;
                $lng = null;
                $zaman = null;

                if ($viewType === 'anlik') {
                    // Anlık konum tablosundan son başarılı yanıtı al
                    $stmt = $db->prepare("SELECT enlem, boylam, yanit_zamani FROM personel_konum_istekleri WHERE personel_id = :pid AND durum = 'TAMAMLANDI' ORDER BY yanit_zamani DESC LIMIT 1");
                    $stmt->execute([':pid' => $p->personel_id]);
                    $anlik = $stmt->fetch(PDO::FETCH_OBJ);
                    if ($anlik) {
                        $lat = $anlik->enlem;
                        $lng = $anlik->boylam;
                        $zaman = $anlik->yanit_zamani;
                    }
                } else {
                    // Görev konumları (giriş-çıkış)
                    $lat = $p->son_enlem;
                    $lng = $p->son_boylam;
                    $zaman = $p->son_baslama;
                }

                if ($tumPersoneller === '1' || ($lat && $lng)) {
                    $markers[] = [
                        'id' => $p->personel_id,
                        'adi_soyadi' => $p->adi_soyadi,
                        'lat' => $lat ? (float) $lat : null,
                        'lng' => $lng ? (float) $lng : null,
                        'durum' => $p->durum,
                        'durum_text' => $p->durum_text,
                        'foto' => $p->foto,
                        'son_zaman' => $zaman
                    ];
                }
            }

            response(true, $markers);
            break;

        case 'istekTumKonum':
            $firma_id = $_SESSION['firma_id'] ?? null;

            // Saha takibi olan ve silinmemiş tüm personelleri al
            $query = "SELECT id FROM personel WHERE silinme_tarihi IS NULL AND aktif_mi = 1 AND saha_takibi = 1 AND (disardan_sigortali = 0 OR FIND_IN_SET('takip', gorunum_modulleri))";
            if ($firma_id) {
                $query .= " AND firma_id = :firma_id";
            }
            $stmt = $db->prepare($query);
            if ($firma_id) {
                $stmt->execute([':firma_id' => $firma_id]);
            } else {
                $stmt->execute();
            }
            $personeller = $stmt->fetchAll(PDO::FETCH_OBJ);

            $eklenen = 0;
            $zaten_var = 0;

            foreach ($personeller as $p) {
                // Bekleyen işlem var mı kontrol et (son 2 dk)
                $stmtCheck = $db->prepare("SELECT id FROM personel_konum_istekleri WHERE personel_id = :pid AND durum = 'BEKLIYOR' AND istek_zamani > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
                $stmtCheck->execute([':pid' => $p->id]);
                if ($stmtCheck->fetch()) {
                    $zaten_var++;
                    continue;
                }

                $stmtInsert = $db->prepare("INSERT INTO personel_konum_istekleri (personel_id, durum) VALUES (:pid, 'BEKLIYOR')");
                if ($stmtInsert->execute([':pid' => $p->id])) {
                    $eklenen++;
                }
            }

            response(true, ['eklenen' => $eklenen, 'zaten_var' => $zaten_var], "$eklenen personelden konum talep edildi. $zaten_var personel için zaten bekleyen talep vardı.");
            break;

        case 'istekKonum':
            $personel_id = $_POST['personel_id'] ?? null;
            if (!$personel_id) {
                response(false, null, 'Personel seçilmedi');
            }

            // Bekleyen işlem var mı kontrol et (tekrar tekrar istememek için son 2 dk)
            $stmt = $db->prepare("SELECT id FROM personel_konum_istekleri WHERE personel_id = :pid AND durum = 'BEKLIYOR' AND istek_zamani > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
            $stmt->execute([':pid' => $personel_id]);
            if ($stmt->fetch()) {
                response(false, null, 'Bu personel için zaten bekleyen bir konum isteği var.');
            }

            $stmt = $db->prepare("INSERT INTO personel_konum_istekleri (personel_id, durum) VALUES (:pid, 'BEKLIYOR')");
            if ($stmt->execute([':pid' => $personel_id])) {
                response(true, null, 'Konum talebi personele iletildi. Cihaz konumu aldığında harita güncellenecektir.');
            } else {
                response(false, null, 'Talep oluşturulamadı.');
            }
            break;

        // Çalışma süreleri raporu
        case 'getCalismaRaporu':
            $baslangic = $_POST['baslangic'] ?? date('Y-m-d', strtotime('-7 days'));
            $bitis = $_POST['bitis'] ?? date('Y-m-d');
            $firma_id = $_SESSION['firma_id'] ?? null;
            $departman = $_POST['departman'] ?? null;

            // Tüm sahada takip edilen aktif personelleri al
            $query = "SELECT id, adi_soyadi FROM personel WHERE silinme_tarihi IS NULL AND aktif_mi = 1 AND saha_takibi = 1 AND (disardan_sigortali = 0 OR FIND_IN_SET('takip', gorunum_modulleri))";
            $params = [];
            if ($firma_id) {
                $query .= " AND firma_id = :firma_id";
                $params[':firma_id'] = $firma_id;
            }
            if (!empty($departman)) {
                $query .= " AND departman = :departman";
                $params[':departman'] = $departman;
            }
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $personeller = $stmt->fetchAll(PDO::FETCH_OBJ);

            $data = [];
            foreach ($personeller as $personel) {
                // Bu personelin tarih aralığındaki hareketlerini al
                $hareketler = $HareketModel->getRapor($personel->id, $baslangic, $bitis);

                if (empty($hareketler))
                    continue;

                // Günlük gruplandırma
                $gunler = [];
                $toplam_dakika = 0;
                $baslama_saatleri = [];
                $bitis_saatleri = [];
                $gec_kalma_sayisi = 0;
                $limit_saat = '08:30'; // Geç kalma limiti

                foreach ($hareketler as $h) {
                    $timestamp = strtotime($h->zaman);
                    $tarih = date('Y-m-d', $timestamp);
                    $saat = date('H:i', $timestamp);
                    $saat_sn = date('H:i:s', $timestamp); // Ortalama için

                    if (!isset($gunler[$tarih])) {
                        $gunler[$tarih] = ['basla' => null, 'bitir' => null];
                    }

                    if ($h->islem_tipi === 'BASLA') {
                        if (!$gunler[$tarih]['basla'] || $saat < $gunler[$tarih]['basla']) {
                            $gunler[$tarih]['basla'] = $saat;
                        }
                        $baslama_saatleri[] = strtotime('1970-01-01 ' . ($saat_sn ?? '00:00:00'));

                        // Geç kalma kontrolü
                        if ($saat > $limit_saat) {
                            $gec_kalma_sayisi++;
                        }
                    } else {
                        if (!$gunler[$tarih]['bitir'] || $saat > $gunler[$tarih]['bitir']) {
                            $gunler[$tarih]['bitir'] = $saat;
                        }
                        $bitis_saatleri[] = strtotime('1970-01-01 ' . ($saat_sn ?? '00:00:00'));
                    }
                }

                // Toplam çalışma süresini hesapla
                foreach ($gunler as $gun) {
                    if ($gun['basla'] && $gun['bitir']) {
                        $start = strtotime($gun['basla'] ?? '');
                        $end = strtotime($gun['bitir'] ?? '');
                        if ($end > $start) {
                            $toplam_dakika += ($end - $start) / 60;
                        }
                    }
                }

                // Ortalama saatleri hesapla
                $ort_baslama = count($baslama_saatleri) > 0
                    ? date('H:i', array_sum($baslama_saatleri) / count($baslama_saatleri))
                    : '-';
                $ort_bitis = count($bitis_saatleri) > 0
                    ? date('H:i', array_sum($bitis_saatleri) / count($bitis_saatleri))
                    : '-';

                $data[] = [
                    'personel_id' => $personel->id,
                    'adi_soyadi' => $personel->adi_soyadi,
                    'toplam_gun' => count($gunler),
                    'toplam_saat' => round($toplam_dakika / 60, 1),
                    'ort_baslama' => $ort_baslama,
                    'ort_bitis' => $ort_bitis,
                    'gec_kalma' => $gec_kalma_sayisi
                ];
            }

            // Toplam saate göre sırala (azalan)
            usort($data, function ($a, $b) {
                if ($a['toplam_saat'] == $b['toplam_saat'])
                    return 0;
                return ($b['toplam_saat'] > $a['toplam_saat']) ? 1 : -1;
            });

            response(true, $data);
            break;

        // Geç kalanları getir
        case 'getGecKalanlar':
            $limit_saat = $_POST['limit_saat'] ?? '08:30';
            $tarih_req = $_POST['tarih'] ?? date('Y-m-d');
            $firma_id = $_SESSION['firma_id'] ?? null;
            $departman = $_POST['departman'] ?? null;

            $personeller = $HareketModel->getTumPersonelDurumu($firma_id, $tarih_req, $departman);

            $gec_kalanlar = [];
            $gun = $tarih_req;

            try {
                // Limit zamanını güvenli oluştur
                $limit_time = new DateTime($gun . ' ' . $limit_saat);
            } catch (Exception $e) {
                // Hatalı format gelirse varsayılanı kullan
                $limit_time = new DateTime($gun . ' 08:30');
                $limit_saat = '08:30';
            }

            foreach ($personeller as $p) {
                $gec_kaldi = false;
                $baslama_saati = '-';
                $gecikme_dk = 0;
                $gecikme_text = '';
                $durum_text = '';

                // Geçerli bir başlama zamanı var mı?
                $has_valid_start = !empty($p->son_baslama) && $p->son_baslama !== '0000-00-00 00:00:00';

                if ($has_valid_start) {
                    try {
                        $baslama_dt = new DateTime($p->son_baslama);
                        $baslama_saat_str = $baslama_dt->format('H:i');
                        $baslama_saati = $baslama_saat_str;

                        // Sadece bugün başlayanları kontrol et
                        if ($baslama_dt->format('Y-m-d') === $gun) {
                            if ($baslama_saat_str > $limit_saat) {
                                $gec_kaldi = true;

                                // Gecikme süresini hesapla
                                $fark = $baslama_dt->getTimestamp() - $limit_time->getTimestamp();
                                $gecikme_dk = max(0, floor($fark / 60));

                                if ($gecikme_dk >= 60) {
                                    $gecikme_text = floor($gecikme_dk / 60) . ' sa ' . ($gecikme_dk % 60) . ' dk';
                                } else {
                                    $gecikme_text = $gecikme_dk . ' dk';
                                }

                                $durum_text = '<span class="badge bg-warning">Geç Başladı</span>';
                            }
                        }
                    } catch (Exception $e) {
                        // Hatalı tarih formatı, yok say
                    }
                } else {
                    // Hiç başlamamış veya geçersiz veri
                    $now = new DateTime();
                    if ($now > $limit_time) {
                        $gec_kaldi = true;
                        $baslama_saati = 'Başlamadı';

                        $fark = $now->getTimestamp() - $limit_time->getTimestamp();
                        $gecikme_dk = max(0, floor($fark / 60));

                        if ($gecikme_dk >= 60) {
                            $gecikme_text = floor($gecikme_dk / 60) . ' sa ' . (round($gecikme_dk) % 60) . ' dk';
                        } else {
                            $gecikme_text = $gecikme_dk . ' dk';
                        }

                        $durum_text = '<span class="badge bg-danger">Başlamadı</span>';
                    }
                }

                if ($gec_kaldi) {
                    $gec_kalanlar[] = [
                        'personel_id' => $p->personel_id,
                        'adi_soyadi' => $p->adi_soyadi,
                        'baslama_saati' => $baslama_saati,
                        'gecikme' => $gecikme_text,
                        'gecikme_dk' => $gecikme_dk,
                        'durum' => $durum_text
                    ];
                }
            }

            // Gecikme süresine göre sayısal sırala (azalan)
            usort($gec_kalanlar, function ($a, $b) {
                return $b['gecikme_dk'] <=> $a['gecikme_dk'];
            });

            response(true, $gec_kalanlar);
            break;

        default:
            response(false, null, 'Geçersiz işlem');
    }

} catch (Exception $e) {
    response(false, null, 'Bir hata oluştu: ' . $e->getMessage());
}
