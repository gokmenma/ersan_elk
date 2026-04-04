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
use App\Model\AracModel;
use App\Model\AracHareketleriModel;
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
    
    $db = (new \App\Core\Db())->db;

    switch ($action) {

        // Özet istatistikler
        case 'getOzet':
            $firma_id = $_SESSION['firma_id'] ?? null;
            $departman = $_POST['departman'] ?? null;
            
            if ($is_restricted) {
                $departman = $restricted_dept;
            }
            
            $personeller = $HareketModel->getTumPersonelDurumu($firma_id, null, $departman);

            $gorevde = 0;
            $tamamladi = 0;
            $baslamadi = 0;
            $izinli_count = 0;
            $gec_kalan = 0;
            $limit_saat = '08:30';

            $gun = date('Y-m-d');
            $now = new DateTime();

            try {
                $limit_time = new DateTime($gun . ' ' . $limit_saat);
            } catch (Exception $e) {
                $limit_time = new DateTime($gun . ' 08:30');
            }

            $izinliler = [];
            $stmt_izin = $db->prepare("SELECT pi.personel_id FROM personel_izinleri pi LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id WHERE pi.baslangic_tarihi <= :gun AND pi.bitis_tarihi >= :gun AND pi.onay_durumu = 'Onaylandı' AND pi.silinme_tarihi IS NULL AND (t.kisa_kod IS NULL OR (t.kisa_kod NOT IN ('X', 'x') AND (t.normal_mesai_sayilir IS NULL OR t.normal_mesai_sayilir = 0)))");
            $stmt_izin->execute([':gun' => $gun]);
            while ($row = $stmt_izin->fetch(PDO::FETCH_ASSOC)) {
                $izinliler[] = $row['personel_id'];
            }

            foreach ($personeller as $p) {
                $is_izinli = in_array($p->personel_id, $izinliler);

                if ($p->durum === 'aktif') {
                    $gorevde++;
                    // Geç başlama kontrolü
                    if (!$is_izinli && !empty($p->son_baslama) && $p->son_baslama !== '0000-00-00 00:00:00') {
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
                } elseif ($p->durum === 'izinli') {
                    // İzinli olanları baslamadi'den sayma
                    // İsterseniz yeni bir stat-izinli ekleyebilirsiniz
                } else {
                    $baslamadi++;
                    // Başlamamış ve saat geçmiş (bugün için)
                    if (!$is_izinli && $now > $limit_time) {
                        $gec_kalan++;
                    }
                }
            }

            response(true, [
                'gorevde' => $gorevde,
                'tamamladi' => $tamamladi,
                'baslamadi' => $baslamadi,
                'izinli' => $izinli_count,
                'gec_kalan' => $gec_kalan,
                'toplam' => count($personeller)
            ]);
            break;

        // Tüm personel durumları (tablo için)
        case 'getPersonelDurumlari':
            $firma_id = $_SESSION['firma_id'] ?? null;
            $departman = $_POST['departman'] ?? null;
            
            if ($is_restricted) {
                $departman = $restricted_dept;
            }
            
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
                    case 'izinli':
                        $durum_badge = '<span class="badge bg-info">İzinli</span>';
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
            
            // PersonelHareketleriModel::getRapor departman filtresi almıyor, 
            // ama dolaylı yoldan firma_id üzerinden veya manuel filtreleme ile yapabiliriz.
            // En temizi SQL'i burada kısıtlamak veya Model'i güncellemek.
            // Ancak Model'i PersonelModel::all gibi güncelleyebiliriz veya burada sonuçları filtreleyebiliriz.
            
            $hareketler = $HareketModel->getRapor(null, $baslangic, $bitis, $firma_id);
            
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
            
            if ($is_restricted) {
                $departman = $restricted_dept;
            }

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
            $params = [];
            if ($firma_id) {
                $query .= " AND firma_id = :firma_id";
                $params[':firma_id'] = $firma_id;
            }
            $stmt = $db->prepare($query);
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
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
            
            if ($is_restricted) {
                $departman = $restricted_dept;
            }

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
            
            if ($is_restricted) {
                $departman = $restricted_dept;
            }

            $personeller = $HareketModel->getTumPersonelDurumu($firma_id, $tarih_req, $departman);

            // Gecikme açıklamalarını getir
            $stmt_aciklama = $db->prepare("SELECT a.*, u1.adi_soyadi as ekleyen_ad, u2.adi_soyadi as guncelleyen_ad 
                                         FROM personel_takip_aciklamalar a 
                                         LEFT JOIN users u1 ON a.ekleyen_kullanici_id = u1.id 
                                         LEFT JOIN users u2 ON a.guncelleyen_kullanici_id = u2.id 
                                         WHERE a.tarih = :tarih");
            $stmt_aciklama->execute([':tarih' => $tarih_req]);
            $aciklamalar = [];
            while ($row = $stmt_aciklama->fetch(PDO::FETCH_ASSOC)) {
                $aciklamalar[$row['personel_id']] = $row;
            }

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

            $izinliler = [];
            $stmt_izin = $db->prepare("SELECT pi.personel_id FROM personel_izinleri pi LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id WHERE pi.baslangic_tarihi <= :gun AND pi.bitis_tarihi >= :gun AND pi.onay_durumu = 'Onaylandı' AND pi.silinme_tarihi IS NULL AND (t.kisa_kod IS NULL OR (t.kisa_kod NOT IN ('X', 'x') AND (t.normal_mesai_sayilir IS NULL OR t.normal_mesai_sayilir = 0)))");
            $stmt_izin->execute([':gun' => $gun]);
            while ($row = $stmt_izin->fetch(PDO::FETCH_ASSOC)) {
                $izinliler[] = $row['personel_id'];
            }

            foreach ($personeller as $p) {
                if (in_array($p->personel_id, $izinliler)) {
                    continue;
                }

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
                    $aciklama_bilgi = $aciklamalar[$p->personel_id] ?? null;
                    $gec_kalanlar[] = [
                        'personel_id' => $p->personel_id,
                        'adi_soyadi' => $p->adi_soyadi,
                        'baslama_saati' => $baslama_saati,
                        'gecikme' => $gecikme_text,
                        'gecikme_dk' => $gecikme_dk,
                        'durum' => $durum_text,
                        'aciklama' => $aciklama_bilgi ? $aciklama_bilgi['aciklama'] : '',
                        'ekleyen_ad' => $aciklama_bilgi ? $aciklama_bilgi['ekleyen_ad'] : '',
                        'guncelleyen_ad' => $aciklama_bilgi ? $aciklama_bilgi['guncelleyen_ad'] : '',
                        'guncellenme_tarihi' => $aciklama_bilgi ? date('d.m.Y H:i', strtotime($aciklama_bilgi['guncellenme_tarihi'])) : ''
                    ];
                }
            }

            // Gecikme süresine göre sayısal sırala (azalan)
            usort($gec_kalanlar, function ($a, $b) {
                return $b['gecikme_dk'] <=> $a['gecikme_dk'];
            });

            response(true, $gec_kalanlar);
            break;

        case 'saveGecikmeAciklama':
            $personel_id = $_POST['personel_id'] ?? null;
            $tarih = $_POST['tarih'] ?? null;
            $aciklama = $_POST['aciklama'] ?? '';
            $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

            if (!$personel_id || !$tarih) {
                response(false, null, 'Parametreler eksik');
            }

            // Mevcut kayıt var mı kontrol et
            $stmt = $db->prepare("SELECT id FROM personel_takip_aciklamalar WHERE personel_id = :pid AND tarih = :tarih");
            $stmt->execute([':pid' => $personel_id, ':tarih' => $tarih]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $sql = "UPDATE personel_takip_aciklamalar SET aciklama = :aciklama, guncelleyen_kullanici_id = :uid, guncellenme_tarihi = NOW() WHERE id = :id";
                $stmt = $db->prepare($sql);
                $res = $stmt->execute([':aciklama' => $aciklama, ':uid' => $user_id, ':id' => $existing['id']]);
            } else {
                $sql = "INSERT INTO personel_takip_aciklamalar (personel_id, tarih, aciklama, ekleyen_kullanici_id, guncelleyen_kullanici_id) VALUES (:pid, :tarih, :aciklama, :uid, :uid)";
                $stmt = $db->prepare($sql);
                $res = $stmt->execute([':pid' => $personel_id, ':tarih' => $tarih, ':aciklama' => $aciklama, ':uid' => $user_id]);
            }

            if ($res) {
                response(true, null, 'Açıklama başarıyla kaydedildi');
            } else {
                response(false, null, 'Kayıt sırasında hata oluştu');
            }
            break;

        case 'getGecikmeGecmisi':
            $personel_id = $_POST['personel_id'] ?? null;
            if (!$personel_id) {
                response(false, null, 'Parametre eksik');
            }

            $sql = "SELECT a.*, u1.adi_soyadi as ekleyen_ad 
                    FROM personel_takip_aciklamalar a 
                    LEFT JOIN users u1 ON a.guncelleyen_kullanici_id = u1.id 
                    WHERE a.personel_id = :pid 
                    ORDER BY a.tarih DESC LIMIT 10";
            $stmt = $db->prepare($sql);
            $stmt->execute([':pid' => $personel_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach($history as &$h) {
                $h['tarih_format'] = date('d.m.Y', strtotime($h['tarih']));
                $h['guncellenme_format'] = date('d.m.Y H:i', strtotime($h['guncellenme_tarihi']));
            }

            response(true, $history);
            break;

        case 'getHomeStatsDetail':
            $type = $_POST['type'] ?? 'saha';
            $firma_id = $_SESSION['firma_id'] ?? null;
            $bugun = date('Y-m-d');
            $limit_saat = '08:30';
            $data = [];

            if ($type === 'izinli') {
                $sql = "SELECT p.id, p.adi_soyadi, p.resim_yolu as foto, p.departman, p.gorev, p.cep_telefonu,
                               pi.baslangic_tarihi, pi.bitis_tarihi
                        FROM personel_izinleri pi
                        JOIN personel p ON pi.personel_id = p.id
                        WHERE pi.baslangic_tarihi <= :bugun AND pi.bitis_tarihi >= :bugun 
                        AND pi.onay_durumu = 'Onaylandı' AND p.firma_id = :firma_id AND pi.silinme_tarihi IS NULL";
                
                $homeParams = [':bugun' => $bugun, ':firma_id' => $firma_id];
                $homeParams = [':bugun' => $bugun, ':firma_id' => $firma_id];
                
                $sql .= " ORDER BY p.adi_soyadi ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($homeParams);
                $data = $stmt->fetchAll(PDO::FETCH_OBJ);
            } elseif ($type === 'gec_kalan') {
                // getGecKalanlar logic
                $personeller = $HareketModel->getTumPersonelDurumu($firma_id, $bugun, null);
                try {
                    $limit_time = new DateTime($bugun . ' ' . $limit_saat);
                } catch (Exception $e) {
                    $limit_time = new DateTime($bugun . ' 08:30');
                }
                $now = new DateTime();

                $izinliler = [];
                $stmt_izin = $db->prepare("SELECT pi.personel_id FROM personel_izinleri pi LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id WHERE pi.baslangic_tarihi <= :gun AND pi.bitis_tarihi >= :gun AND pi.onay_durumu = 'Onaylandı' AND pi.silinme_tarihi IS NULL AND (t.kisa_kod IS NULL OR (t.kisa_kod NOT IN ('X', 'x') AND (t.normal_mesai_sayilir IS NULL OR t.normal_mesai_sayilir = 0)))");
                $stmt_izin->execute([':gun' => $bugun]);
                while ($row = $stmt_izin->fetch(PDO::FETCH_ASSOC)) {
                    $izinliler[] = $row['personel_id'];
                }

                foreach ($personeller as $p) {
                    if (in_array($p->personel_id, $izinliler)) {
                        continue;
                    }

                    $gec_kaldi = false;
                    $has_valid_start = !empty($p->son_baslama) && $p->son_baslama !== '0000-00-00 00:00:00';
                    if ($has_valid_start) {
                        try {
                            $baslama_dt = new DateTime($p->son_baslama);
                            if ($baslama_dt->format('Y-m-d') === $bugun && $baslama_dt->format('H:i') > $limit_saat) {
                                $gec_kaldi = true;
                            }
                        } catch (Exception $e) {}
                    } else {
                        if ($now > $limit_time) $gec_kaldi = true;
                    }

                    if ($gec_kaldi) {
                        $p->gorev = $p->departman;
                        $data[] = $p;
                    }
                }
            } else {
                // 'saha' - Aktif ama izinli değil
                $sql = "SELECT p.id, p.adi_soyadi, p.resim_yolu as foto, p.departman, p.gorev, p.cep_telefonu
                        FROM personel p
                        WHERE p.aktif_mi = 1 AND p.silinme_tarihi IS NULL AND p.firma_id = :firma_id";
                
                $homeParams2 = [':bugun' => $bugun, ':firma_id' => $firma_id];
                $homeParams2 = [':bugun' => $bugun, ':firma_id' => $firma_id];
                
                $sql .= " AND p.id NOT IN (
                            SELECT pi.personel_id FROM personel_izinleri pi
                            LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
                            WHERE pi.baslangic_tarihi <= :bugun AND pi.bitis_tarihi >= :bugun 
                            AND pi.onay_durumu = 'Onaylandı' AND pi.silinme_tarihi IS NULL
                            AND (t.kisa_kod IS NULL OR (t.kisa_kod NOT IN ('X', 'x') AND (t.normal_mesai_sayilir IS NULL OR t.normal_mesai_sayilir = 0)))
                        )
                        ORDER BY p.adi_soyadi ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute($homeParams2);
                $data = $stmt->fetchAll(PDO::FETCH_OBJ);
            }

            foreach($data as &$item) {
                if (isset($item->personel_id) && !isset($item->id)) $item->id = $item->personel_id;
                $item->id_enc = Security::encrypt($item->id);
            }

            response(true, $data);
            break;

        case 'getHomeAracStatsDetail':
            $type = $_POST['type'] ?? 'saha';
            $AracModel = new AracModel();
            $data = [];

            if ($type === 'servis') {
                $data = $AracModel->getServistekiAraclar();
                foreach($data as &$v) $v->durum_text = 'Serviste';
            } elseif ($type === 'bosta') {
                $data = $AracModel->getBostaAraclar();
                foreach($data as &$v) $v->durum_text = 'Boşta (Zimmetsiz)';
            } else {
                // saha - Zimmetli olup serviste olmayanlar
                $all_zimmetli = $AracModel->getZimmetliAraclar();
                foreach ($all_zimmetli as $v) {
                    if (!$v->serviste_mi) {
                        $v->durum_text = 'Saha Görevinde';
                        $data[] = $v;
                    }
                }
            }

            foreach($data as &$item) {
                // Marka model birleştirme
                $item->adi_soyadi = $item->plaka; // Başlık olarak plaka
                $item->detay = ($item->marka ? $item->marka . ' ' : '') . ($item->model ?? '');
                $item->sub_detay = $item->zimmetli_personel_adi ?? $item->durum_text;
                $item->id_enc = Security::encrypt($item->id);
            }

            response(true, $data);
            break;

        default:
            response(false, null, 'Geçersiz işlem');
    }

} catch (Exception $e) {
    response(false, null, 'Bir hata oluştu: ' . $e->getMessage());
}
