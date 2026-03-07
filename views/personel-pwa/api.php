<?php
/**
 * Personel PWA - API Endpoint
 * Tüm AJAX isteklerini yönetir
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

ini_set('error_log', dirname(dirname(__DIR__)) . '/pwa_api_error.log');
ini_set('log_errors', 1);

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Model\PersonelModel;
use App\Model\BordroPersonelModel;
use App\Model\PersonelIzinleriModel;
use App\Model\AvansModel;
use App\Service\MailGonderService;
use App\Model\PushSubscriptionModel;
use App\Model\BildirimModel;
use App\Model\UserModel;
use App\Model\PersonelHareketleriModel;
use App\Model\PersonelIcralariModel;

// Oturum kontrolü (logout hariç)
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$MailGonderService = new MailGonderService();

if ($action !== 'login' && $action !== 'logout' && !isset($_SESSION['personel_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum süresi doldu']);
    exit;
}

$personel_id = $_SESSION['personel_id'] ?? 0;

if ($personel_id > 0) {
    $PersonelModel = new PersonelModel();
    $personel = $PersonelModel->find($personel_id);
    if (!$personel) {
        session_destroy();
        echo json_encode(['success' => false, 'error' => 'Session expired', 'redirect' => 'login.php']);
        exit;
    }
    // Firma ID'yi oturuma ekleyelim (Model'ler için gerekli)
    if (!isset($_SESSION['firma_id']) && isset($personel->firma_id)) {
        $_SESSION['firma_id'] = $personel->firma_id;
    }
}

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

/**
 * Resim yollarını PWA için düzeltir
 */
function getPwaImageUrl($path)
{
    if (empty($path))
        return '';

    $host = $_SERVER['HTTP_HOST'];
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https:' : 'http:';

    // Eğer 'personel.' ile başlayan bir subdomain kullanılıyorsa (örn: personel.softran.online) 
    // Ana dizin (parent domain) üzerinden dosyayı çekelim.
    if (strpos($host, 'personel.') === 0) {
        $mainHost = substr($host, 9);
        return $protocol . '//' . $mainHost . '/' . $path;
    }

    // Subdomain yoksa veya local klasör yapısı ise klasik ../../uploads mantığı
    return '../../' . $path;
}

try {
    switch ($action) {
        case 'debugSession':
            response(true, $_SESSION);
            break;

        case 'whoami':
            $PersonelModel = new PersonelModel();
            $p = $PersonelModel->find($personel_id);
            response(true, $p);
            break;

        case 'logout':
            session_destroy();
            setcookie('remember_token', '', time() - 3600, "/");
            response(true, null, 'Oturum kapatıldı');
            break;

        // ===== İcra İşlemleri =====
        case 'getIcralar':
            $PersonelIcralariModel = new PersonelIcralariModel();
            $icralar = $PersonelIcralariModel->getPersonelIcralariWithKesintiler($personel_id);

            $data = array_map(function ($item) {
                return [
                    'id' => $item->id,
                    'dosya_no' => $item->dosya_no ?? null,
                    'alacakli' => $item->alacakli ?? null,
                    'icra_dairesi' => $item->icra_dairesi ?? null,
                    'toplam_borc' => $item->toplam_borc ?? 0,
                    'kalan_tutar' => $item->kalan_tutar ?? ($item->toplam_borc ?? 0),
                    'toplam_kesilen' => $item->toplam_kesilen ?? 0,
                    'kesinti_orani' => $item->kesinti_orani ?? 0,
                    'kesinti_turu' => $item->kesinti_turu ?? null, // oran veya tutar
                    'aylik_kesinti_tutari' => $item->aylik_kesinti_tutari ?? 0,
                    'durum' => $item->durum ?? null,
                    'sira' => $item->sira ?? 0,
                    'created_at' => isset($item->created_at) ? date('d.m.Y', strtotime($item->created_at)) : '-'
                ];
            }, $icralar);

            response(true, $data);
            break;

        case 'getIcraKesintileri':
            $icra_id = $_POST['icra_id'] ?? 0;
            if (!$icra_id) {
                response(false, null, 'İcra ID eksik.');
            }

            $PersonelIcralariModel = new PersonelIcralariModel();

            // Güvenlik: Bu icra dosyası bu personele mi ait kontrol et
            $personelZimmet = $PersonelIcralariModel->find($icra_id);
            if (!$personelZimmet || $personelZimmet->personel_id != $personel_id) {
                response(false, null, 'İcra dosyası bulunamadı veya yetkiniz yok.');
            }

            $kesintiler = $PersonelIcralariModel->getIcraKesintileri($icra_id);

            $data = array_map(function ($item) {
                return [
                    'id' => $item->id,
                    'donem_adi' => $item->donem_adi ?? null,
                    'tutar' => $item->tutar ?? 0,
                    'aciklama' => $item->aciklama ?? '',
                    'tarih' => isset($item->olusturma_tarihi) ? date('d.m.Y', strtotime($item->olusturma_tarihi)) : '-'
                ];
            }, $kesintiler);

            response(true, $data);
            break;

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
                'remaining_balance' => $kalan_bakiye,
                'son_donem_adi' => $ozet->son_donem_adi ?? null
            ]);
            break;

        case 'getWorkStats':
            $today = date('Y-m-d');
            $startOfMonth = date('Y-m-01');
            $endOfMonth = date('Y-m-t');
            $firmaId = $_SESSION['firma_id'] ?? 0;

            $PersonelModel = new \App\Model\PersonelModel();
            $personelDetails = $PersonelModel->find($personel_id);
            $isSayacSokmeTakma = (stripos($personelDetails->departman ?? '', 'Sayaç Sökme Takma') !== false);

            $db = (new \App\Model\Model('tanimlamalar'))->getDb();

            $adminFilter = "AND tn.is_turu_ucret > 0 AND tn.rapor_sekmesi IS NOT NULL AND tn.rapor_sekmesi != ''";

            // Günlük Toplam
            $sqlIslerDaily = "SELECT SUM(t.sonuclanmis) as toplam 
                            FROM yapilan_isler t
                            LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                            WHERE t.personel_id = ? AND t.firma_id = ? AND t.silinme_tarihi IS NULL AND t.tarih = ? $adminFilter";
            $stmt = $db->prepare($sqlIslerDaily);
            $stmt->execute([$personel_id, $firmaId, $today]);
            $dailyIsler = (int) ($stmt->fetch(PDO::FETCH_OBJ)->toplam ?? 0);

            // Aylık Toplam
            $sqlIslerMonthly = "SELECT SUM(t.sonuclanmis) as toplam 
                            FROM yapilan_isler t
                            LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                            WHERE t.personel_id = ? AND t.firma_id = ? AND t.silinme_tarihi IS NULL AND t.tarih BETWEEN ? AND ? $adminFilter";
            $stmt = $db->prepare($sqlIslerMonthly);
            $stmt->execute([$personel_id, $firmaId, $startOfMonth, $endOfMonth]);
            $monthlyIsler = (int) ($stmt->fetch(PDO::FETCH_OBJ)->toplam ?? 0);

            // Rapor Sekmesi Bazlı Dağılım (Günlük)
            $sqlSekmeDaily = "SELECT tn.rapor_sekmesi, SUM(t.sonuclanmis) as toplam 
                             FROM yapilan_isler t
                             LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                             WHERE t.personel_id = ? AND t.firma_id = ? AND t.silinme_tarihi IS NULL AND t.tarih = ? $adminFilter
                             GROUP BY tn.rapor_sekmesi";
            $stmt = $db->prepare($sqlSekmeDaily);
            $stmt->execute([$personel_id, $firmaId, $today]);
            $dailySekme = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Rapor Sekmesi Bazlı Dağılım (Aylık)
            $sqlSekmeMonthly = "SELECT tn.rapor_sekmesi, SUM(t.sonuclanmis) as toplam 
                               FROM yapilan_isler t
                               LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                               WHERE t.personel_id = ? AND t.firma_id = ? AND t.silinme_tarihi IS NULL AND t.tarih BETWEEN ? AND ? $adminFilter
                               GROUP BY tn.rapor_sekmesi";
            $stmt = $db->prepare($sqlSekmeMonthly);
            $stmt->execute([$personel_id, $firmaId, $startOfMonth, $endOfMonth]);
            $monthlySekme = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Endeks okuma (Sadece Sayaç Sökme Takma olmayanlar için)
            $dailyEndeks = 0;
            $monthlyEndeks = 0;

            if (!$isSayacSokmeTakma) {
                $sqlEndeksDaily = "SELECT SUM(t.okunan_abone_sayisi) as toplam 
                                   FROM endeks_okuma t 
                                   LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id
                                   WHERE t.personel_id = ? AND t.firma_id = ? AND t.silinme_tarihi IS NULL AND t.tarih = ?
                                   AND def.tur_adi REGEXP 'EK[İI]P-?[[:space:]]?[0-9]+'";
                $stmt = $db->prepare($sqlEndeksDaily);
                $stmt->execute([$personel_id, $firmaId, $today]);
                $dailyEndeks = (int) ($stmt->fetch(PDO::FETCH_OBJ)->toplam ?? 0);

                $sqlEndeksMonthly = "SELECT SUM(t.okunan_abone_sayisi) as toplam 
                                     FROM endeks_okuma t 
                                     LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id
                                     WHERE t.personel_id = ? AND t.firma_id = ? AND t.silinme_tarihi IS NULL AND t.tarih BETWEEN ? AND ?
                                     AND def.tur_adi REGEXP 'EK[İI]P-?[[:space:]]?[0-9]+'";
                $stmt = $db->prepare($sqlEndeksMonthly);
                $stmt->execute([$personel_id, $firmaId, $startOfMonth, $endOfMonth]);
                $monthlyEndeks = (int) ($stmt->fetch(PDO::FETCH_OBJ)->toplam ?? 0);
            }

            $dailyTotal = $dailyIsler + $dailyEndeks;
            $monthlyTotal = $monthlyIsler + $monthlyEndeks;

            // ---- YENİ: SIRALAMA EKLENMESİ ----
            $departman = $personelDetails->departman ?? '';
            $aktifEkipGecmisi = $PersonelModel->getEkipGecmisi($personel_id);
            $aktifEkipId = null;
            $aktifEkipBolge = '';
            foreach ($aktifEkipGecmisi as $gecmis) {
                if (empty($gecmis->bitis_tarihi) || $gecmis->bitis_tarihi >= date('Y-m-d')) {
                    $aktifEkipId = $gecmis->ekip_kodu_id;
                    break;
                }
            }
            if ($aktifEkipId) {
                $TanimlamalarModel = new \App\Model\TanimlamalarModel();
                $ekip = $TanimlamalarModel->find($aktifEkipId);
                if ($ekip) {
                    $aktifEkipBolge = $ekip->ekip_bolge ?? '';
                }
            }

            // Tüm aktif personelleri departman ve bölgesine göre çekelim
            $allActiveQuery = "SELECT p.id, p.departman, 
                (SELECT t.ekip_bolge FROM personel_ekip_gecmisi peg 
                 LEFT JOIN tanimlamalar t ON peg.ekip_kodu_id = t.id
                 WHERE peg.personel_id = p.id AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi >= CURDATE())
                 ORDER BY peg.baslangic_tarihi DESC LIMIT 1) as bolge 
                FROM personel p 
                WHERE p.firma_id = ? AND p.silinme_tarihi IS NULL 
                AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '0000-00-00')";
            $stmt = $PersonelModel->getDb()->prepare($allActiveQuery);
            $stmt->execute([$firmaId]);
            $tumPersoneller = $stmt->fetchAll(PDO::FETCH_OBJ);

            $departmanPersonelIds = [];
            $ekipPersonelIds = [];

            foreach ($tumPersoneller as $p) {
                if ($p->departman == $departman) {
                    $departmanPersonelIds[] = $p->id;
                }
                if ($aktifEkipBolge && $p->bolge == $aktifEkipBolge) {
                    $ekipPersonelIds[] = $p->id;
                }
            }
            if (!in_array($personel_id, $departmanPersonelIds))
                $departmanPersonelIds[] = $personel_id;
            if ($aktifEkipBolge && !in_array($personel_id, $ekipPersonelIds))
                $ekipPersonelIds[] = $personel_id;

            // Aylık skor tablosu hesabı
            $skorSorgusu = "SELECT personel_id, SUM(toplam) as skor FROM (
                SELECT t.personel_id, t.sonuclanmis as toplam
                FROM yapilan_isler t
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                WHERE t.firma_id = ? AND t.silinme_tarihi IS NULL AND t.tarih BETWEEN ? AND ?
                AND tn.is_turu_ucret > 0 AND tn.rapor_sekmesi IS NOT NULL AND tn.rapor_sekmesi != ''
                UNION ALL
                SELECT t.personel_id, t.okunan_abone_sayisi as toplam
                FROM endeks_okuma t
                LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id
                WHERE t.firma_id = ? AND t.silinme_tarihi IS NULL AND t.tarih BETWEEN ? AND ?
                AND def.tur_adi REGEXP 'EK[İI]P-?[[:space:]]?[0-9]+'
            ) AS birlesik GROUP BY personel_id";

            $stmt = $db->prepare($skorSorgusu);
            $stmt->execute([$firmaId, $startOfMonth, $endOfMonth, $firmaId, $startOfMonth, $endOfMonth]);
            $skorlarArray = $stmt->fetchAll(PDO::FETCH_OBJ);

            $skorMap = [];
            foreach ($skorlarArray as $sk) {
                $skorMap[$sk->personel_id] = (int) $sk->skor;
            }

            $departmanSiralama = [];
            foreach ($departmanPersonelIds as $id) {
                $departmanSiralama[$id] = $skorMap[$id] ?? 0;
            }
            arsort($departmanSiralama);

            $ekipSiralama = [];
            foreach ($ekipPersonelIds as $id) {
                $ekipSiralama[$id] = $skorMap[$id] ?? 0;
            }
            arsort($ekipSiralama);

            $myDeptRank = array_search($personel_id, array_keys($departmanSiralama)) !== false ? array_search($personel_id, array_keys($departmanSiralama)) + 1 : 0;
            $myEkipRank = array_search($personel_id, array_keys($ekipSiralama)) !== false ? array_search($personel_id, array_keys($ekipSiralama)) + 1 : 0;
            // ------------------------------------

            response(true, [
                'today' => $dailyTotal,
                'month' => $monthlyTotal,
                'is_sayac_ekibi' => $isSayacSokmeTakma,
                'departman' => $departman,
                'ekip_bolge' => $aktifEkipBolge,
                'siralama' => [
                    'departman_sira' => $myDeptRank,
                    'departman_kisi' => count($departmanPersonelIds),
                    'ekip_sira' => $myEkipRank,
                    'ekip_kisi' => count($ekipPersonelIds)
                ],
                'details' => [
                    'daily_isler' => $dailyIsler,
                    'daily_endeks' => $dailyEndeks,
                    'monthly_isler' => $monthlyIsler,
                    'monthly_endeks' => $monthlyEndeks,
                    'daily_sekme' => $dailySekme,
                    'monthly_sekme' => $monthlySekme
                ]
            ]);
            break;

        // ===== Ekip Takibi (Endeks Okuma Şef) =====
        case 'getEkipTakibiData':
            $PersonelModel = new PersonelModel();
            $TanimlamalarModel = new \App\Model\TanimlamalarModel();

            // 1. Personelin ekip şefi olduğu aktif ekip kodunu bul
            $ekipGecmisi = $PersonelModel->getEkipGecmisi($personel_id);
            $sefEkipKoduId = null;
            foreach ($ekipGecmisi as $g) {
                if (($g->ekip_sefi_mi ?? 0) == 1 && (empty($g->bitis_tarihi) || $g->bitis_tarihi >= date('Y-m-d'))) {
                    $sefEkipKoduId = $g->ekip_kodu_id;
                    break;
                }
            }

            if (!$sefEkipKoduId) {
                response(false, null, 'Ekip şefi kaydı bulunamadı');
            }

            // 2. O ekip kodunun bölgesini bul
            $ekipKodu = $TanimlamalarModel->find($sefEkipKoduId);
            if (!$ekipKodu) {
                response(false, null, 'Ekip kodu bulunamadı');
            }
            $bolge = trim($ekipKodu->ekip_bolge ?? '');

            if (empty($bolge)) {
                response(false, null, 'Ekip kodunun bölge bilgisi tanımlı değil');
            }

            // 3. Aynı bölgedeki tüm ekip kodlarını bul
            $bolgeEkipleri = $TanimlamalarModel->getEkipKodlariByBolgeAll($bolge);

            // 4. Tarih parametreleri
            $startDate = $_POST['start_date'] ?? date('Y-m-01');
            $endDate = $_POST['end_date'] ?? date('Y-m-d');
            $bugun = date('Y-m-d');

            $db = (new \App\Model\Model('endeks_okuma'))->getDb();
            $firmaId = $_SESSION['firma_id'] ?? 0;

            $ekiplerData = [];

            foreach ($bolgeEkipleri as $ekip) {
                $ekipId = $ekip->id;

                // Günlük toplam (bugün)
                $stmtDaily = $db->prepare("SELECT COALESCE(SUM(okunan_abone_sayisi), 0) as toplam 
                    FROM endeks_okuma 
                    WHERE ekip_kodu_id = ? AND tarih = ? AND firma_id = ? AND silinme_tarihi IS NULL");
                $stmtDaily->execute([$ekipId, $bugun, $firmaId]);
                $gunlukToplam = (int) ($stmtDaily->fetch(PDO::FETCH_OBJ)->toplam ?? 0);

                // Aylık toplam (seçilen tarih aralığı)
                $stmtMonthly = $db->prepare("SELECT COALESCE(SUM(okunan_abone_sayisi), 0) as toplam, COUNT(DISTINCT tarih) as gun_sayisi
                    FROM endeks_okuma 
                    WHERE ekip_kodu_id = ? AND tarih BETWEEN ? AND ? AND firma_id = ? AND silinme_tarihi IS NULL");
                $stmtMonthly->execute([$ekipId, $startDate, $endDate, $firmaId]);
                $monthlyResult = $stmtMonthly->fetch(PDO::FETCH_OBJ);
                $aylikToplam = (int) ($monthlyResult->toplam ?? 0);
                $calisilanGun = (int) ($monthlyResult->gun_sayisi ?? 0);

                // Günlük detay (seçilen tarih aralığı)
                $stmtDetail = $db->prepare("SELECT tarih, SUM(okunan_abone_sayisi) as toplam
                    FROM endeks_okuma 
                    WHERE ekip_kodu_id = ? AND tarih BETWEEN ? AND ? AND firma_id = ? AND silinme_tarihi IS NULL
                    GROUP BY tarih
                    ORDER BY tarih DESC");
                $stmtDetail->execute([$ekipId, $startDate, $endDate, $firmaId]);
                $gunlukDetay = $stmtDetail->fetchAll(PDO::FETCH_OBJ);

                // Bu ekip koduna aktif olarak atanmış personeli bul
                $stmtPersonel = $db->prepare("SELECT p.adi_soyadi, p.departman 
                    FROM personel_ekip_gecmisi pg
                    JOIN personel p ON pg.personel_id = p.id
                    WHERE pg.ekip_kodu_id = ? AND pg.firma_id = ?
                    AND pg.baslangic_tarihi <= CURDATE()
                    AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= CURDATE())
                    ORDER BY pg.id DESC LIMIT 1");
                $stmtPersonel->execute([$ekipId, $firmaId]);
                $personelResult = $stmtPersonel->fetch(PDO::FETCH_OBJ);

                // Eğer ekibe atanmış personel varsa:
                // Sadece departmanında "Endeks Okuma" geçip geçmediğine bak
                // Diğer departmanlarının (örn: Kaçak Kontrol) ne olduğunun bir önemi yok
                if ($personelResult) {
                    $dep = $personelResult->departman ?? '';
                    if (stripos($dep, 'Endeks Okuma') === false) {
                        continue;
                    }
                }

                $personelAdi = $personelResult ? $personelResult->adi_soyadi : '—';

                $ekiplerData[] = [
                    'ekip_kodu_id' => $ekipId,
                    'ekip_adi' => $ekip->tur_adi,
                    'personel_adi' => $personelAdi,
                    'gunluk_toplam' => $gunlukToplam,
                    'aylik_toplam' => $aylikToplam,
                    'calisilan_gun' => $calisilanGun,
                    'gunluk_detay' => array_map(function ($d) {
                        return ['tarih' => $d->tarih, 'toplam' => (int) $d->toplam];
                    }, $gunlukDetay)
                ];
            }

            response(true, [
                'bolge' => $bolge,
                'ekipler' => $ekiplerData
            ]);
            break;

        case 'getDelayedReadings':
            $PersonelModel = new PersonelModel();
            $TanimlamalarModel = new \App\Model\TanimlamalarModel();

            // 1. Personelin ekip şefi olduğu aktif ekip kodunu bul
            $ekipGecmisi = $PersonelModel->getEkipGecmisi($personel_id);
            $sefEkipKoduId = null;
            foreach ($ekipGecmisi as $g) {
                if (($g->ekip_sefi_mi ?? 0) == 1 && (empty($g->bitis_tarihi) || $g->bitis_tarihi >= date('Y-m-d'))) {
                    $sefEkipKoduId = $g->ekip_kodu_id;
                    break;
                }
            }

            if (!$sefEkipKoduId) {
                response(false, null, 'Ekip şefi kaydı bulunamadı');
            }

            // 2. O ekip kodunun bölgesini bul
            $ekipKodu = $TanimlamalarModel->find($sefEkipKoduId);
            if (!$ekipKodu) {
                response(false, null, 'Ekip kodu bulunamadı');
            }
            $bolge = trim($ekipKodu->ekip_bolge ?? '');

            if (empty($bolge)) {
                response(false, null, 'Bölge bilgisi tanımlı değil');
            }

            $db = (new \App\Model\Model('tanimlamalar'))->getDb();
            $firmaId = $_SESSION['firma_id'] ?? 0;

            // 3. Bölgedeki defterleri ve son okuma tarihlerini bul
            $sql = "SELECT 
                        t.id, 
                        t.tur_adi as defter_kodu, 
                        t.defter_mahalle as mahalle,
                        t.baslangic_tarihi,
                        (SELECT MAX(eo.tarih) 
                         FROM endeks_okuma eo 
                         WHERE eo.defter = t.tur_adi 
                         AND eo.firma_id = :firma_id 
                         AND eo.silinme_tarihi IS NULL) as son_okuma_tarihi
                    FROM tanimlamalar t
                    WHERE t.grup = 'defter_kodu' 
                    AND t.defter_bolge = :bolge 
                    AND t.firma_id = :firma_id 
                    AND t.silinme_tarihi IS NULL";

            $stmt = $db->prepare($sql);
            $stmt->execute(['bolge' => $bolge, 'firma_id' => $firmaId]);
            $defterler = $stmt->fetchAll(PDO::FETCH_OBJ);

            $delayed = [];
            $bugun = new DateTime();

            foreach ($defterler as $d) {
                $sonOkuma = $d->son_okuma_tarihi ?: $d->baslangic_tarihi;

                if ($sonOkuma) {
                    $sonTarih = new DateTime($sonOkuma);
                    $interval = $bugun->diff($sonTarih);
                    $gunFarki = $interval->days;

                    if ($gunFarki > 35) {
                        $delayed[] = [
                            'defter_kodu' => $d->defter_kodu,
                            'mahalle' => $d->mahalle,
                            'gun' => $gunFarki,
                            'son_okuma' => date('d.m.Y', strtotime($sonOkuma))
                        ];
                    }
                }
            }

            response(true, $delayed);
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

            // Sadece personel görsün olan dönemleri filtrele
            $bordrolar = array_filter($bordrolar, function ($item) {
                return isset($item->personel_gorsun) && $item->personel_gorsun == 1;
            });

            $data = array_map(function ($item) {
                // Dönem adını belirle
                $donem = $item->donem_adi ?? null;

                // Eğer donem_adi yoksa baslangic_tarihi'nden oluştur
                if (empty($donem) && !empty($item->baslangic_tarihi)) {
                    $tarih = strtotime($item->baslangic_tarihi);
                    $donem = date('Y', $tarih) . '/' . str_pad(date('m', $tarih), 2, '0', STR_PAD_LEFT);
                } else if (empty($donem)) {
                    $donem = 'Dönem ' . ($item->donem_id ?? '?');
                }

                return [
                    'id' => $item->id,
                    'donem' => $donem,
                    'odeme_tarihi' => $item->odeme_tarihi ?? '-',
                    'net_tutar' => $item->net_maas ?? 0,
                    'durum' => 'odendi'
                ];
            }, array_values($bordrolar)); // array_values to reset keys after filter

            response(true, $data);
            break;

        case 'getBordroDetay':
            $id = $_POST['id'] ?? 0;
            $BordroModel = new BordroPersonelModel();

            // Bordro ve dönem bilgisini birlikte çek
            $sql = $BordroModel->getDb()->prepare("
                SELECT bp.*, bd.personel_gorsun 
                FROM bordro_personel bp
                INNER JOIN bordro_donemi bd ON bp.donem_id = bd.id
                WHERE bp.id = ? AND bp.silinme_tarihi IS NULL
            ");
            $sql->execute([$id]);
            $bordro = $sql->fetch(PDO::FETCH_OBJ);

            if ($bordro && $bordro->personel_id == $personel_id && ($bordro->personel_gorsun ?? 0) == 1) {
                response(true, [
                    'id' => $bordro->id,
                    'donem' => 'Dönem ' . $bordro->donem_id,
                    'brut' => $bordro->brut_maas,
                    'sgk' => $bordro->sgk_isci,
                    'vergi' => $bordro->gelir_vergisi,
                    'net' => $bordro->net_maas
                ]);
            } else {
                response(false, null, 'Bordro bulunamadı veya henüz onaylanmadı');
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
                'kayit_yapan' => $personel_id,
                'talep_tarihi' => date('Y-m-d H:i:s')
            ]);
            $newId = $AvansModel->getDb()->lastInsertId();

            // Bildirim ve Mail Gönderimi
            try {
                $logFile = dirname(dirname(__DIR__)) . '/debug_bildirim.log';
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Avans talebi süreci başladı. Personel ID: $personel_id\n", FILE_APPEND);

                $UserModel = new UserModel();
                $PersonelModel = new PersonelModel();
                $talep_eden = $PersonelModel->find($personel_id);
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Talep eden: " . ($talep_eden->adi_soyadi ?? 'Bulunamadı') . "\n", FILE_APPEND);

                // 1. Uygulama İçi Bildirimler (Email bağımsız)
                $bildirimKullanicilari = $UserModel->getInAppBildirimKullanicilari('avans');
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Bildirim gidecek kullanıcı sayısı: " . count($bildirimKullanicilari) . "\n", FILE_APPEND);

                foreach ($bildirimKullanicilari as $kullanici) {
                    try {
                        $BildirimModel = new BildirimModel();
                        $res = $BildirimModel->createNotification(
                            $kullanici->id,
                            'Yeni Avans Talebi',
                            ($talep_eden->adi_soyadi ?? 'Personel') . ' ' . number_format($tutar, 2, ',', '.') . ' TL avans talep etti.',
                            'index.php?p=talepler/list&tab=avans&id=' . $newId,
                            'bx-money',
                            'success'
                        );
                        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Bildirim oluşturuldu. Kullanıcı: {$kullanici->id}, Sonuç: $res\n", FILE_APPEND);
                    } catch (Exception $e) {
                        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Bildirim oluşturma hatası: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }

                // 2. Mail Gönderimi (Sadece email adresi olanlara)
                $mailKullanicilari = $UserModel->getMailBildirimKullanicilari('avans');
                foreach ($mailKullanicilari as $kullanici) {
                    try {
                        $mail_content = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                                <h2 style='color: #5b73e8;'>Yeni Avans Talebi</h2>
                                <p><strong>" . ($talep_eden->adi_soyadi ?? 'Personel') . "</strong> tarafından <strong>" . number_format($tutar, 2, ',', '.') . " TL</strong> tutarında avans talebi oluşturuldu.</p>
                                <p>Talebi incelemek için sisteme giriş yapabilirsiniz.</p>
                            </div>
                        ";

                        $MailGonderService->gonder(
                            [$kullanici->email_adresi],
                            'Yeni Avans Talebi - ' . ($talep_eden->adi_soyadi ?? 'Personel'),
                            $mail_content
                        );
                    } catch (Exception $e) {
                        error_log('Avans mail gönderme hatası: ' . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                error_log('Avans bildirim süreci hatası: ' . $e->getMessage());
            }

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
        case 'getIzinTurleri':
            $TanimlamalarModel = new App\Model\TanimlamalarModel();
            // Personelin görebileceği izin türlerini getir
            $izinTurleri = $TanimlamalarModel->getDb()->query("SELECT * FROM tanimlamalar WHERE grup = 'izin_turu' AND personel_gorebilir = 1 AND silinme_tarihi IS NULL ORDER BY tur_adi ASC")->fetchAll(PDO::FETCH_OBJ);

            $data = array_map(function ($item) {
                return [
                    'id' => $item->id, // ID'yi value olarak kullanacağız
                    'tur_adi' => $item->tur_adi,
                    'renk' => $item->renk ?? 'bg-primary/10 text-primary',
                    'ikon' => $item->ikon ?? 'event',
                    'aciklama' => $item->aciklama
                ];
            }, $izinTurleri);

            response(true, $data);
            break;

        case 'getIzinStats':
            $IzinModel = new PersonelIzinleriModel();
            file_put_contents(dirname(dirname(__DIR__)) . '/debug_pwa.log', "getIzinStats for personel_id: " . $personel_id . "\n", FILE_APPEND);
            $entitlement = $IzinModel->calculateLeaveEntitlement($personel_id);
            file_put_contents(dirname(dirname(__DIR__)) . '/debug_pwa.log', "Entitlement: " . json_encode($entitlement) . "\n", FILE_APPEND);
            $izinler = $IzinModel->getPersonelIzinleri($personel_id);

            $bekleyen = 0;
            foreach ($izinler as $izin) {
                $durum = mb_strtolower($izin->onay_durumu ?? '', 'UTF-8');
                if ($durum == 'beklemede') {
                    $bekleyen++;
                }
            }

            response(true, [
                'kalan_izin' => $entitlement['kalan_izin'],
                'toplam_hakedis' => $entitlement['toplam_hakedis'],
                'kullanilan_izin' => $entitlement['kullanilan_izin'],
                'detay' => $entitlement['detay'],
                'hastalik_izni' => 0,
                'bekleyen' => $bekleyen
            ]);
            break;

        case 'getIzinler':
            $IzinModel = new PersonelIzinleriModel();
            $izinler = $IzinModel->getPersonelIzinleri($personel_id);

            $data = array_map(function ($item) {
                // Onay durumu kontrolü
                $main_durum = mb_strtolower($item->onay_durumu ?? '', 'UTF-8');
                $cancel_target = mb_strtolower('İptal Edildi', 'UTF-8');

                if ($main_durum === 'iptal edildi' || $main_durum === $cancel_target) {
                    $durum_raw = 'İptal Edildi';
                } else {
                    $durum_raw = $item->onay_durumu_text ?? $item->onay_durumu ?? 'beklemede';
                }

                $durum = mb_strtolower($durum_raw, 'UTF-8');
                $durum_text = mb_convert_case($durum, MB_CASE_TITLE, "UTF-8");

                if ($durum == 'beklemede')
                    $durum_text = 'Beklemede';
                if ($durum == 'onaylandi' || $durum == 'onaylandı') {
                    $durum = 'onaylandi'; // Normalize status for frontend
                    $durum_text = 'Onaylandı';
                }
                if ($durum == 'reddedildi')
                    $durum_text = 'Reddedildi';
                if ($durum == 'iptal edildi' || $durum == $cancel_target) {
                    $durum = 'iptal_edildi'; // Normalize status
                    $durum_text = 'İptal Edildi';
                }

                $izin_tipi_text = $item->izin_tipi_adi ?? 'İzin Türü Belirtilmemiş';
                $renk = 'bg-primary/10 text-primary';
                $ikon = 'event';

                // Red nedenini bul
                $red_nedeni = null;
                if ($durum == 'reddedildi' && !empty($item->onaylar)) {
                    $son_onay = end($item->onaylar);
                    if ($son_onay && $son_onay->durum == 'Reddedildi') {
                        $red_nedeni = $son_onay->aciklama;
                    }
                }

                return [
                    'id' => $item->id,
                    'izin_tipi' => $item->izin_tipi_id,
                    'izin_tipi_text' => $izin_tipi_text,
                    'baslangic' => date('d.m.Y', strtotime($item->baslangic_tarihi)),
                    'bitis' => date('d.m.Y', strtotime($item->bitis_tarihi)),
                    'toplam_gun' => $item->toplam_gun,
                    'talep_tarihi' => date('d.m.Y', strtotime($item->olusturma_tarihi)),
                    'durum' => $durum,
                    'durum_text' => $durum_text,
                    'aciklama' => $item->aciklama,
                    'red_nedeni' => $red_nedeni,
                    'renk' => $renk,
                    'ikon' => $ikon
                ];
            }, $izinler);

            response(true, $data);
            break;

        case 'getIzinlerByYear':
            $IzinModel = new PersonelIzinleriModel();
            $PersonelModel = new PersonelModel();

            // Get personnel info to calculate year ranges
            $personel = $PersonelModel->find($personel_id);
            if (!$personel || empty($personel->ise_giris_tarihi)) {
                response(true, []);
                break;
            }

            // Get approved leave records
            $izinler = $IzinModel->getPersonelIzinleri($personel_id);

            // Debug log
            $logFile = dirname(dirname(__DIR__)) . '/debug_izin_year.log';
            file_put_contents($logFile, "Total izinler: " . count($izinler) . "\n", FILE_APPEND);

            // Filter only approved and leaves that affect annual leave balance
            $approvedIzinler = array_filter($izinler, function ($izin) use ($logFile) {
                $durum = mb_strtolower($izin->onay_durumu ?? '', 'UTF-8');
                $affectsBalance = isset($izin->yillik_izne_etki) && ($izin->yillik_izne_etki == 'Dus' || $izin->yillik_izne_etki == 1);

                // Only include annual leave types (Yıllık İzin) - exclude other types like maternity/paternity leave
                $isAnnualLeave = stripos($izin->izin_tipi_adi ?? '', 'Yıllık') !== false ||
                    stripos($izin->izin_tipi_adi ?? '', 'Yillik') !== false;

                file_put_contents($logFile, "Izin ID: {$izin->id}, Tip: {$izin->izin_tipi_adi}, Durum: {$durum}, yillik_izne_etki: " . ($izin->yillik_izne_etki ?? 'NULL') . ", isAnnualLeave: " . ($isAnnualLeave ? 'YES' : 'NO') . "\n", FILE_APPEND);

                return ($durum == 'onaylandi' || $durum == 'onaylandı' || $durum == 'kabuledildi') && $affectsBalance && $isAnnualLeave;
            });

            file_put_contents($logFile, "Approved izinler: " . count($approvedIzinler) . "\n", FILE_APPEND);

            // Group by work anniversary year
            $giris = new DateTime($personel->ise_giris_tarihi);
            $bugun = new DateTime();
            $yearGroups = [];

            foreach ($approvedIzinler as $izin) {
                if (empty($izin->baslangic_tarihi))
                    continue;

                $izinDate = new DateTime($izin->baslangic_tarihi);

                // Calculate which work year this leave belongs to
                $yilFarki = $giris->diff($izinDate)->y;
                $workYear = $yilFarki + 1; // Year 1-based

                if ($workYear < 1)
                    continue; // Skip leaves before work start

                if (!isset($yearGroups[$workYear])) {
                    $yearGroups[$workYear] = [];
                }

                $yearGroups[$workYear][] = [
                    'id' => $izin->id,
                    'izin_tipi_adi' => $izin->izin_tipi_adi ?? 'Yıllık İzin',
                    'baslangic' => date('d.m.Y', strtotime($izin->baslangic_tarihi)),
                    'bitis' => date('d.m.Y', strtotime($izin->bitis_tarihi)),
                    'toplam_gun' => $izin->toplam_gun
                ];
            }

            file_put_contents($logFile, "Year groups: " . json_encode($yearGroups) . "\n", FILE_APPEND);

            // Also add all approved leaves under 'all' key for frontend to use
            $allLeaves = [];
            foreach ($approvedIzinler as $izin) {
                if (!empty($izin->baslangic_tarihi)) {
                    $allLeaves[] = [
                        'id' => $izin->id,
                        'izin_tipi_adi' => $izin->izin_tipi_adi ?? 'Yıllık İzin',
                        'baslangic' => date('d.m.Y', strtotime($izin->baslangic_tarihi)),
                        'bitis' => date('d.m.Y', strtotime($izin->bitis_tarihi)),
                        'toplam_gun' => $izin->toplam_gun
                    ];
                }
            }
            $yearGroups['all'] = $allLeaves;

            response(true, $yearGroups);
            break;

        case 'createIzinTalebi':
            $izin_tipi = $_POST['izin_tipi'] ?? '';
            $baslangic = $_POST['baslangic_tarihi'] ?? '';
            $bitis = $_POST['bitis_tarihi'] ?? '';

            // Güvenlik: Açıklama alanını temizle (XSS/Script koruması)
            $aciklama = $_POST['aciklama'] ?? '';
            $aciklama = strip_tags($aciklama);
            $aciklama = htmlspecialchars($aciklama, ENT_QUOTES, 'UTF-8');

            if (empty($izin_tipi)) {
                response(false, null, 'Lütfen izin türünü seçiniz.');
            }

            if (empty($baslangic) || empty($bitis)) {
                response(false, null, 'Lütfen tarihleri seçiniz.');
            }

            $IzinModel = new PersonelIzinleriModel();

            // Çakışma Kontrolü
            // Seçilen tarih aralığında (başlangıç ve bitiş dahil) başka bir izin var mı?
            // Durumu 'beklemede', 'onaylandi' veya 'onaylandı' olanları kontrol et.
            $sql = "SELECT COUNT(*) as sayi FROM personel_izinleri 
                    WHERE personel_id = :personel_id 
                    AND silinme_tarihi IS NULL 
                    AND (onay_durumu = 'beklemede' OR onay_durumu = 'onaylandi' OR onay_durumu = 'onaylandı')
                    AND (
                        (baslangic_tarihi <= :bitis AND bitis_tarihi >= :baslangic)
                    )";

            $stmt = $IzinModel->getDb()->prepare($sql);
            $stmt->execute([
                ':personel_id' => $personel_id,
                ':baslangic' => $baslangic,
                ':bitis' => $bitis
            ]);
            $cakisma = $stmt->fetch(PDO::FETCH_OBJ);

            if ($cakisma && $cakisma->sayi > 0) {
                response(false, null, 'Seçilen tarih aralığında bekleyen veya onaylanmış başka bir izin talebiniz bulunmaktadır. Lütfen tarihleri kontrol ediniz.');
            }

            // Gün sayısını hesapla
            $diff = strtotime($bitis) - strtotime($baslangic);
            $toplam_gun = round($diff / (60 * 60 * 24)) + 1;

            // $IzinModel zaten yukarıda tanımlandı

            $IzinModel->saveWithAttr([
                'personel_id' => $personel_id,
                'izin_tipi_id' => $izin_tipi,
                'baslangic_tarihi' => $baslangic,
                'bitis_tarihi' => $bitis,
                'toplam_gun' => $toplam_gun,
                'aciklama' => $aciklama,
                'olusturma_tarihi' => date('Y-m-d H:i:s')
            ]);
            $newId = $IzinModel->getDb()->lastInsertId();

            // İzin onayı yapacak personeli getir ve mail gönder
            // İzin onayı yapacak personeli getir ve bildirim/mail gönder
            try {
                $logFile = dirname(dirname(__DIR__)) . '/debug_bildirim.log';
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "İzin talebi süreci başladı. Personel ID: $personel_id\n", FILE_APPEND);

                $UserModel = new UserModel();
                $PersonelModel = new PersonelModel();
                $talep_eden = $PersonelModel->find($personel_id);

                // İzin türü adını belirle
                $izin_tipi_text = $izin_tipi;
                $izin_tipleri = [
                    'yillik' => 'Yıllık İzin',
                    'mazeret' => 'Mazeret İzni',
                    'hastalik' => 'Hastalık İzni',
                    'dogum' => 'Doğum / Babalık İzni',
                    'ucretsiz' => 'Ücretsiz İzin'
                ];

                if (isset($izin_tipleri[$izin_tipi])) {
                    $izin_tipi_text = $izin_tipleri[$izin_tipi];
                } elseif (is_numeric($izin_tipi)) {
                    $TanimlamalarModel = new App\Model\TanimlamalarModel();
                    $tur = $TanimlamalarModel->find($izin_tipi);
                    if ($tur) {
                        $izin_tipi_text = $tur->tur_adi;
                    }
                }

                // 1. Uygulama İçi Bildirimler
                // Hem onaylayacak kişiye hem de bildirimleri açık olan yöneticilere gönder
                $bildirimKullanicilari = $UserModel->getInAppBildirimKullanicilari('izin');
                $izin_onayi_yapacak_personel = $UserModel->getIzinOnayPersonel();

                $recipients = [];
                if ($izin_onayi_yapacak_personel) {
                    $recipients[$izin_onayi_yapacak_personel->id] = $izin_onayi_yapacak_personel;
                }
                foreach ($bildirimKullanicilari as $k) {
                    $recipients[$k->id] = $k;
                }

                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Bildirim gidecek kullanıcı sayısı: " . count($recipients) . "\n", FILE_APPEND);
                foreach ($recipients as $kullanici) {
                    try {
                        $BildirimModel = new BildirimModel();
                        $res = $BildirimModel->createNotification(
                            $kullanici->id,
                            'Yeni İzin Talebi',
                            ($talep_eden->adi_soyadi ?? 'Personel') . ' ' . $izin_tipi_text . ' talep etti.',
                            'index.php?p=talepler/list&tab=izin&id=' . $newId,
                            'calendar',
                            'warning'
                        );
                        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "İzin bildirimi oluşturuldu. Kullanıcı: {$kullanici->id}, Sonuç: $res\n", FILE_APPEND);
                    } catch (Exception $e) {
                        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "İzin bildirim hatası: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }

                // 2. Mail Gönderimi (Sadece email adresi olanlara)
                $mailKullanicilari = $UserModel->getMailBildirimKullanicilari('izin');
                if ($izin_onayi_yapacak_personel && !empty($izin_onayi_yapacak_personel->email_adresi)) {
                    // Onaylayacak kişiyi de ekle (eğer listede yoksa)
                    $found = false;
                    foreach ($mailKullanicilari as $mk) {
                        if ($mk->id == $izin_onayi_yapacak_personel->id) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found)
                        $mailKullanicilari[] = $izin_onayi_yapacak_personel;
                }

                foreach ($mailKullanicilari as $kullanici) {
                    try {
                        $mail_template_path = dirname(__DIR__) . '/mail-template/izin_onay.php';
                        if (file_exists($mail_template_path)) {
                            $mail_content = file_get_contents($mail_template_path);
                            $replacements = [
                                '{{ONAYLAYAN_AD_SOYAD}}' => $kullanici->adi_soyadi ?? 'Yetkili',
                                '{{TALEP_EDEN_AD_SOYAD}}' => $talep_eden->adi_soyadi ?? 'Personel',
                                '{{IZIN_TURU}}' => $izin_tipi_text,
                                '{{BASLANGIC_TARIHI}}' => date('d.m.Y', strtotime($baslangic)),
                                '{{BITIS_TARIHI}}' => date('d.m.Y', strtotime($bitis)),
                                '{{ACIKLAMA}}' => !empty($aciklama) ? nl2br(htmlspecialchars($aciklama)) : 'Açıklama belirtilmemiş',
                                '{{ONAY_LINKI}}' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/views/personel/',
                                '{{YIL}}' => date('Y')
                            ];
                            $mail_content = str_replace(array_keys($replacements), array_values($replacements), $mail_content);
                            $MailGonderService->gonder(
                                [$kullanici->email_adresi],
                                'Yeni İzin Talebi - ' . ($talep_eden->adi_soyadi ?? 'Personel'),
                                $mail_content
                            );
                        }
                    } catch (Exception $e) {
                        error_log('İzin talebi mail gönderme hatası: ' . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                error_log('İzin bildirim süreci hatası: ' . $e->getMessage());
            }

            response(true, null, 'İzin talebiniz başarıyla oluşturuldu');

            break;

        case 'cancelIzinTalebi':
            $id = $_POST['id'] ?? 0;
            $IzinModel = new PersonelIzinleriModel();
            $izin = $IzinModel->find($id);

            // Onay durumu kontrolünü esnet (büyük/küçük harf duyarsız)
            $durum = mb_strtolower($izin->onay_durumu ?? '', 'UTF-8');

            if ($izin && $izin->personel_id == $personel_id && $durum == 'beklemede') {
                $IzinModel->softDelete($id);
                /**onay_durumunu iptal edildi yap */
                $IzinModel->saveWithAttr(
                    [
                        'id' => $id,
                        'onay_durumu' => 'iptal edildi'
                    ]
                );
                response(true, null, 'İzin talebi iptal edildi');
            } else {
                // Debug için detaylı hata mesajı (geliştirme aşamasında)
                // $msg = "İzin: " . ($izin ? 'Var' : 'Yok') . ", PID: " . ($izin->personel_id ?? 'Yok') . " vs $personel_id, Durum: " . ($izin->onay_durumu ?? 'Yok');
                response(false, null, 'İşlem yapılamaz. İzin durumu uygun değil veya yetkiniz yok.');
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
                    'cozum_aciklama' => $item->cozum_aciklama,
                    'foto' => $item->foto,
                    'latitude' => $item->latitude,
                    'longitude' => $item->longitude,
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
                        $absolute_foto_path = realpath($upload_dir . $new_name);
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

            $newId = (int) $TalepModel->getDb()->lastInsertId();

            // Bildirim ve Mail Gönderimi
            try {
                $logFile = dirname(dirname(__DIR__)) . '/debug_bildirim.log';
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Talep bildirimi süreci başladı. Personel ID: $personel_id\n", FILE_APPEND);

                $UserModel = new UserModel();
                $PersonelModel = new PersonelModel();
                $talep_eden = $PersonelModel->find($personel_id);

                // Kategori bazında bildirim türünü belirle
                $bildirim_turu = ($kategori == 'ariza') ? 'ariza' : 'genel';

                // 1. Uygulama İçi Bildirimler
                $bildirimKullanicilari = $UserModel->getInAppBildirimKullanicilari($bildirim_turu);
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Talep bildirimi gidecek kullanıcı sayısı: " . count($bildirimKullanicilari) . "\n", FILE_APPEND);

                foreach ($bildirimKullanicilari as $kullanici) {
                    try {
                        $BildirimModel = new BildirimModel();
                        $res = $BildirimModel->createNotification(
                            $kullanici->id,
                            "Yeni {$baslik}",
                            ($talep_eden->adi_soyadi ?? 'Personel') . " yeni bir talep oluşturdu: {$baslik}",
                            'index.php?p=talepler/list&tab=talep&id=' . $newId,
                            'message-square',
                            'info'
                        );
                        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Talep bildirimi oluşturuldu. Kullanıcı: {$kullanici->id}, Sonuç: $res\n", FILE_APPEND);
                    } catch (Exception $e) {
                        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Talep bildirim hatası: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }

                // 2. Mail Gönderimi
                $mailKullanicilari = $UserModel->getMailBildirimKullanicilari($bildirim_turu);
                foreach ($mailKullanicilari as $kullanici) {
                    try {
                        // Öncelik renk haritası
                        $oncelik_renk = [
                            'dusuk' => '#28a745',
                            'orta' => '#ffc107',
                            'yuksek' => '#dc3545'
                        ];

                        $oncelik_text = [
                            'dusuk' => 'Düşük',
                            'orta' => 'Orta',
                            'yuksek' => 'Yüksek'
                        ];

                        $mail_content = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                                <h2 style='color: #5b73e8;'>Yeni {$baslik}</h2>
                                <p>Sayın <strong>{$kullanici->adi_soyadi}</strong>,</p>
                                <p><strong>" . ($talep_eden->adi_soyadi ?? 'Personel') . "</strong> tarafından yeni bir talep bildirimi oluşturuldu.</p>
                                <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                                    <h3 style='margin-top: 0;'>Talep Detayları</h3>
                                    <table style='width: 100%;'>
                                        <tr>
                                            <td style='padding: 5px;'><strong>Referans No:</strong></td>
                                            <td style='padding: 5px;'><span style='background-color: #e3f2fd; padding: 3px 8px; border-radius: 3px; font-weight: bold;'>{$ref_no}</span></td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 5px;'><strong>Personel:</strong></td>
                                            <td style='padding: 5px;'>" . ($talep_eden->adi_soyadi ?? 'Personel') . "</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 5px;'><strong>Kategori:</strong></td>
                                            <td style='padding: 5px;'>{$baslik}</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 5px;'><strong>Konum:</strong></td>
                                            <td style='padding: 5px;'>{$konum}</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 5px;'><strong>Öncelik:</strong></td>
                                            <td style='padding: 5px;'>
                                                <span style='background-color: {$oncelik_renk[$oncelik]}; color: white; padding: 3px 8px; border-radius: 3px; font-weight: bold;'>
                                                    {$oncelik_text[$oncelik]}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 5px;'><strong>Açıklama:</strong></td>
                                            <td style='padding: 5px;'>" . nl2br(htmlspecialchars($aciklama)) . "</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 5px;'><strong>Talep Tarihi:</strong></td>
                                            <td style='padding: 5px;'>" . date('d.m.Y H:i') . "</td>
                                        </tr>
                                    </table>
                                </div>
                                <p>Talebi incelemek için sisteme giriş yapabilirsiniz.</p>
                                <hr style='margin: 20px 0;'>
                                <p style='font-size: 12px; color: #666;'>Bu bir otomatik bildirimdir. Lütfen yanıtlamayınız.</p>
                            </div>
                        ";

                        $attachments = [];
                        if (isset($absolute_foto_path) && file_exists($absolute_foto_path)) {
                            $attachments[] = $absolute_foto_path;
                        }

                        $MailGonderService->gonder(
                            [$kullanici->email_adresi],
                            "Yeni {$baslik} - Ref: {$ref_no}",
                            $mail_content,
                            $attachments
                        );
                    } catch (Exception $e) {
                        error_log('Talep bildirimi mail gönderme hatası: ' . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                error_log('Talep bildirim süreci hatası: ' . $e->getMessage());
            }

            response(true, ['id' => $newId, 'ref_no' => $ref_no], 'Talebiniz başarıyla oluşturuldu. Referans No: ' . $ref_no);
            break;

        case 'updateTalepBildirimi':
            $TalepModel = new App\Model\TalepModel();

            $id = (int) ($_POST['id'] ?? 0);
            $konum = $_POST['konum'] ?? '';
            $kategori = $_POST['kategori'] ?? '';
            $oncelik = $_POST['oncelik'] ?? 'orta';
            $aciklama = $_POST['aciklama'] ?? '';
            $latitude = $_POST['latitude'] ?? null;
            $longitude = $_POST['longitude'] ?? null;

            if ($id <= 0) {
                throw new Exception('Geçersiz talep.');
            }

            if (empty($konum) || empty($kategori) || empty($aciklama)) {
                throw new Exception('Lütfen tüm zorunlu alanları doldurun.');
            }

            $stmt = $TalepModel->getDb()->prepare("SELECT id, ref_no, foto, durum FROM personel_talepleri WHERE id = ? AND personel_id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$id, $personel_id]);
            $existing = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$existing) {
                response(false, null, 'Talep bulunamadı.');
            }

            if ($existing->durum !== 'beklemede') {
                response(false, null, 'Sadece beklemede olan talepler güncellenebilir.');
            }

            $foto_path = $existing->foto;
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
                        // Yeni dosya başarıyla yüklendi, eskisi varsa silelim
                        if (!empty($existing->foto)) {
                            $old_file = dirname(dirname(__DIR__)) . '/' . $existing->foto;
                            if (file_exists($old_file)) {
                                @unlink($old_file);
                            }
                        }
                        $foto_path = 'uploads/talepler/' . $new_name;
                        $absolute_foto_path = realpath($upload_dir . $new_name);
                    }
                }
            }

            $kategori_titles = [
                'ariza' => 'Arıza Bildirimi',
                'oneri' => 'Öneri',
                'sikayet' => 'Şikayet',
                'istek' => 'İstek',
                'diger' => 'Diğer Talep'
            ];
            $baslik = $kategori_titles[$kategori] ?? 'Yeni Talep';

            $update = $TalepModel->getDb()->prepare("
                UPDATE personel_talepleri 
                SET konum = ?, latitude = ?, longitude = ?, kategori = ?, oncelik = ?, baslik = ?, aciklama = ?, foto = ?
                WHERE id = ? AND personel_id = ? AND durum = 'beklemede' AND deleted_at IS NULL
            ");
            $update->execute([$konum, $latitude, $longitude, $kategori, $oncelik, $baslik, $aciklama, $foto_path, $id, $personel_id]);

            response(true, ['id' => $id, 'ref_no' => $existing->ref_no], 'Talebiniz güncellendi. Referans No: ' . $existing->ref_no);
            break;

        case 'deleteTalepBildirimi':
            $TalepModel = new App\Model\TalepModel();
            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Geçersiz talep.');
            }

            // Önce talebi bulup fotoğraf yolunu alalım
            $stmt = $TalepModel->getDb()->prepare("SELECT foto FROM personel_talepleri WHERE id = ? AND personel_id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$id, $personel_id]);
            $talep = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$talep) {
                response(false, null, 'Talep bulunamadı.');
            }

            // Eğer fotoğraf varsa dosyayı sil
            if (!empty($talep->foto)) {
                $file_path = dirname(dirname(__DIR__)) . '/' . $talep->foto;
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }

            $delete = $TalepModel->getDb()->prepare("
                UPDATE personel_talepleri 
                SET deleted_at = NOW()
                WHERE id = ? AND personel_id = ? AND durum = 'beklemede' AND deleted_at IS NULL
            ");
            $delete->execute([$id, $personel_id]);

            if ($delete->rowCount() === 0) {
                response(false, null, 'Talep silinemedi (sadece beklemede olan talepler silinebilir).');
            }

            response(true, ['id' => $id], 'Talep ve ilgili dosyalar silindi.');
            break;

        // ===== Profil İşlemleri =====
        case 'changePassword':
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';

            // Validasyon
            if (empty($current) || empty($new)) {
                response(false, null, 'Lütfen tüm alanları doldurun.');
            }

            if (strlen($new) < 6) {
                response(false, null, 'Yeni şifre en az 6 karakter olmalıdır.');
            }

            // Personeli bul
            $PersonelModel = new PersonelModel();
            $db = $PersonelModel->getDb();
            $stmt = $db->prepare("SELECT id, sifre FROM personel WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $personel_id]);
            $personel = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$personel) {
                response(false, null, 'Personel bulunamadı.');
            }

            // Mevcut şifre kontrolü
            if (empty($personel->sifre)) {
                response(false, null, 'Hesabınızda henüz şifre belirlenmemiş.');
            }

            if (!password_verify($current, $personel->sifre)) {
                response(false, null, 'Mevcut şifreniz hatalı.');
            }

            // Yeni şifreyi hash'le ve kaydet
            $newHashedPassword = password_hash($new, PASSWORD_DEFAULT);
            $updateStmt = $db->prepare("UPDATE personel SET sifre = :sifre WHERE id = :id");
            $result = $updateStmt->execute([
                'sifre' => $newHashedPassword,
                'id' => $personel_id
            ]);

            if ($result) {
                response(true, null, 'Şifreniz başarıyla değiştirildi.');
            } else {
                response(false, null, 'Şifre değiştirilirken bir hata oluştu.');
            }
            break;

        case 'updateProfileImage':
            if (!isset($_FILES['image']) || $_FILES['image']['error'] != 0) {
                response(false, null, 'Lütfen bir resim seçiniz.');
            }

            $file = $_FILES['image'];
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                response(false, null, 'Sadece JPG, PNG ve WEBP formatları desteklenir.');
            }

            // Dosya boyutu kontrolü (örn: 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                response(false, null, 'Dosya boyutu 5MB\'dan büyük olamaz.');
            }

            // Hedef klasörler
            $mainAppDir = dirname(dirname(__DIR__));
            $pwaDir = __DIR__;

            $uploadDirMain = $mainAppDir . '/assets/images/users/';
            $uploadDirPwa = $pwaDir . '/assets/images/users/';

            if (!file_exists($uploadDirMain)) {
                mkdir($uploadDirMain, 0777, true);
            }
            if (!file_exists($uploadDirPwa)) {
                mkdir($uploadDirPwa, 0777, true);
            }

            $fileName = uniqid('personel_') . '.' . $ext;
            $targetPathMain = $uploadDirMain . $fileName;
            $targetPathPwa = $uploadDirPwa . $fileName;

            // DB'ye kaydedilecek yol
            $dbPath = 'assets/images/users/' . $fileName;

            // Debug Log
            $logFile = $mainAppDir . '/debug_image_upload.txt';
            $logContent = date('Y-m-d H:i:s') . " - Uploading for ID: $personel_id\n";
            $logContent .= "Target Main: $targetPathMain\n";
            $logContent .= "Target PWA: $targetPathPwa\n";
            $logContent .= "DB Path: $dbPath\n";

            if (move_uploaded_file($file['tmp_name'], $targetPathMain)) {
                // PWA dizinine kopyala
                if (!copy($targetPathMain, $targetPathPwa)) {
                    $logContent .= "ERROR: Failed to copy to PWA directory: $targetPathPwa\n";
                } else {
                    $logContent .= "SUCCESS: Copied to PWA directory.\n";
                }

                $PersonelModel = new PersonelModel();
                $db = $PersonelModel->getDb();

                // Eski resmi silmek istersek burada yapabiliriz ama şimdilik kalsın

                $stmt = $db->prepare("UPDATE personel SET resim_yolu = ? WHERE id = ?");
                $result = $stmt->execute([$dbPath, $personel_id]);
                $rowCount = $stmt->rowCount();

                $logContent .= "Update Result: " . ($result ? 'True' : 'False') . "\n";
                $logContent .= "Row Count: $rowCount\n";
                file_put_contents($logFile, $logContent, FILE_APPEND);

                if ($result) {
                    response(true, ['image_url' => $dbPath], 'Profil resmi güncellendi.');
                } else {
                    @unlink($targetPathMain); // DB güncellenemezse dosyaları sil
                    @unlink($targetPathPwa);
                    response(false, null, 'Veritabanı güncellenemedi.');
                }
            } else {
                $logContent .= "Move Uploaded File Failed\n";
                file_put_contents($logFile, $logContent, FILE_APPEND);
                response(false, null, 'Dosya yüklenirken bir hata oluştu.');
            }
            break;

        // ===== Push Notification =====
        case 'get-vapid-key':
            $configPath = dirname(__DIR__) . '/../App/Config/vapid.php';
            $publicKey = '';

            if (file_exists($configPath)) {
                $config = require $configPath;
                $publicKey = $config['publicKey'];
            } else {
                $publicKey = $_ENV['VAPID_PUBLIC_KEY'] ?? '';
            }

            response(true, ['publicKey' => $publicKey]);
            break;

        case 'save-subscription':
            $subscription = json_decode($_POST['subscription'] ?? '{}', true);

            if (empty($subscription) || empty($subscription['endpoint'])) {
                response(false, null, 'Geçersiz abonelik verisi');
            }

            $PushSubscriptionModel = new PushSubscriptionModel();
            $result = $PushSubscriptionModel->saveSubscription(
                $personel_id,
                $subscription['endpoint'],
                $subscription['keys']['p256dh'] ?? null,
                $subscription['keys']['auth'] ?? null
            );

            if ($result) {
                response(true, null, 'Bildirim aboneliği başarıyla kaydedildi');
            } else {
                response(false, null, 'Abonelik kaydedilirken bir hata oluştu');
            }
            break;

        case 'check-subscription-status':
            $PushSubscriptionModel = new PushSubscriptionModel();
            $db = $PushSubscriptionModel->getDb();

            $stmt = $db->prepare("SELECT COUNT(*) FROM push_subscriptions WHERE personel_id = ?");
            $stmt->execute([$personel_id]);
            $count = (int) $stmt->fetchColumn();

            response(true, ['subscribed' => $count > 0, 'count' => $count]);
            break;

        case 'remove-subscription':
            $PushSubscriptionModel = new PushSubscriptionModel();
            $db = $PushSubscriptionModel->getDb();

            // Tüm abonelikleri sil
            $stmt = $db->prepare("DELETE FROM push_subscriptions WHERE personel_id = ?");
            $result = $stmt->execute([$personel_id]);

            if ($result) {
                response(true, null, 'Bildirim aboneliği kaldırıldı');
            } else {
                response(false, null, 'Abonelik kaldırılırken bir hata oluştu');
            }
            break;

        case 'getMyNotifications':
            // Personele gönderilen push bildirimlerini getir
            $PersonelModel = new PersonelModel();
            $personelData = $PersonelModel->find($personel_id);
            $personelAdi = $personelData->adi_soyadi ?? '';

            $db = $PersonelModel->getDb();

            // personel_bildirim_durumu tablosu yoksa oluştur
            $db->exec("CREATE TABLE IF NOT EXISTS personel_bildirim_durumu (
                id INT AUTO_INCREMENT PRIMARY KEY,
                personel_id INT NOT NULL,
                mesaj_log_id INT NOT NULL,
                okundu TINYINT(1) DEFAULT 0,
                okunma_tarihi DATETIME NULL,
                silindi TINYINT(1) DEFAULT 0,
                silme_tarihi DATETIME NULL,
                UNIQUE KEY unique_personel_mesaj (personel_id, mesaj_log_id)
            )");

            // mesaj_log tablosundan push bildirimlerini çek
            // recipients alanında personel adı veya "Tüm Aboneler" ibaresi geçenleri al
            // silindi olarak işaretlenmemiş olanları getir
            $sql = "SELECT m.*, 
                    COALESCE(pbd.okundu, 0) as okundu,
                    pbd.okunma_tarihi
                    FROM mesaj_log m
                    LEFT JOIN personel_bildirim_durumu pbd ON m.id = pbd.mesaj_log_id AND pbd.personel_id = :personel_id
                    WHERE m.type = 'push' 
                    AND (
                        m.recipients LIKE :personel_adi 
                        OR m.recipients LIKE '%Tüm Aboneler%'
                        OR m.recipients LIKE '%Test Kullanıcısı%'
                    )
                    AND (pbd.silindi IS NULL OR pbd.silindi = 0)
                    ORDER BY m.created_at DESC 
                    LIMIT 50";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':personel_id' => $personel_id,
                ':personel_adi' => '%' . $personelAdi . '%'
            ]);
            $notifications = $stmt->fetchAll(PDO::FETCH_OBJ);

            $data = array_map(function ($item) {
                // Zaman farkını hesapla
                $created = new DateTime($item->created_at);
                $now = new DateTime();
                $diff = $now->diff($created);

                if ($diff->days == 0) {
                    if ($diff->h == 0) {
                        if ($diff->i == 0) {
                            $timeAgo = 'Az önce';
                        } else {
                            $timeAgo = $diff->i . ' dk önce';
                        }
                    } else {
                        $timeAgo = $diff->h . ' saat önce';
                    }
                } elseif ($diff->days == 1) {
                    $timeAgo = 'Dün';
                } elseif ($diff->days < 7) {
                    $timeAgo = $diff->days . ' gün önce';
                } else {
                    $timeAgo = date('d M', strtotime($item->created_at));
                }

                // Payload'dan image URL'ini al
                $imageUrl = null;
                if (!empty($item->attachments)) {
                    $payload = json_decode($item->attachments, true);
                    if ($payload && isset($payload['image'])) {
                        $imageUrl = $payload['image'];
                    }
                }

                return [
                    'id' => $item->id,
                    'title' => $item->subject,
                    'body' => $item->message,
                    'image' => $imageUrl,
                    'time_ago' => $timeAgo,
                    'created_at' => $item->created_at,
                    'status' => $item->status,
                    'okundu' => (bool) $item->okundu
                ];
            }, $notifications);

            response(true, $data);
            break;

        case 'markNotificationRead':
            $mesaj_log_id = $_POST['notification_id'] ?? null;

            if (!$mesaj_log_id) {
                response(false, null, 'Bildirim ID gerekli');
            }

            $PersonelModel = new PersonelModel();
            $db = $PersonelModel->getDb();

            // Upsert - varsa güncelle, yoksa ekle
            $sql = "INSERT INTO personel_bildirim_durumu (personel_id, mesaj_log_id, okundu, okunma_tarihi)
                    VALUES (:personel_id, :mesaj_log_id, 1, NOW())
                    ON DUPLICATE KEY UPDATE okundu = 1, okunma_tarihi = NOW()";

            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                ':personel_id' => $personel_id,
                ':mesaj_log_id' => $mesaj_log_id
            ]);

            if ($result) {
                response(true, null, 'Bildirim okundu olarak işaretlendi');
            } else {
                response(false, null, 'İşlem başarısız');
            }
            break;

        case 'markAllNotificationsRead':
            $PersonelModel = new PersonelModel();
            $personelData = $PersonelModel->find($personel_id);
            $personelAdi = $personelData->adi_soyadi ?? '';
            $db = $PersonelModel->getDb();

            // Personele ait tüm bildirimleri bul
            $sql = "SELECT id FROM mesaj_log 
                    WHERE type = 'push' 
                    AND (
                        recipients LIKE :personel_adi 
                        OR recipients LIKE '%Tüm Aboneler%'
                        OR recipients LIKE '%Test Kullanıcısı%'
                    )";

            $stmt = $db->prepare($sql);
            $stmt->execute([':personel_adi' => '%' . $personelAdi . '%']);
            $notifications = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Her birini okundu olarak işaretle
            $insertSql = "INSERT INTO personel_bildirim_durumu (personel_id, mesaj_log_id, okundu, okunma_tarihi)
                          VALUES (:personel_id, :mesaj_log_id, 1, NOW())
                          ON DUPLICATE KEY UPDATE okundu = 1, okunma_tarihi = NOW()";

            $insertStmt = $db->prepare($insertSql);

            foreach ($notifications as $notifId) {
                $insertStmt->execute([
                    ':personel_id' => $personel_id,
                    ':mesaj_log_id' => $notifId
                ]);
            }

            response(true, null, 'Tüm bildirimler okundu olarak işaretlendi');
            break;

        case 'deleteNotification':
            $mesaj_log_id = $_POST['notification_id'] ?? null;

            if (!$mesaj_log_id) {
                response(false, null, 'Bildirim ID gerekli');
            }

            $PersonelModel = new PersonelModel();
            $db = $PersonelModel->getDb();

            // Upsert - varsa güncelle, yoksa ekle (silindi olarak işaretle)
            $sql = "INSERT INTO personel_bildirim_durumu (personel_id, mesaj_log_id, silindi, silme_tarihi)
                    VALUES (:personel_id, :mesaj_log_id, 1, NOW())
                    ON DUPLICATE KEY UPDATE silindi = 1, silme_tarihi = NOW()";

            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                ':personel_id' => $personel_id,
                ':mesaj_log_id' => $mesaj_log_id
            ]);

            if ($result) {
                response(true, null, 'Bildirim silindi');
            } else {
                response(false, null, 'İşlem başarısız');
            }
            break;

        case 'deleteAllNotifications':
            $PersonelModel = new PersonelModel();
            $personelData = $PersonelModel->find($personel_id);
            $personelAdi = $personelData->adi_soyadi ?? '';
            $db = $PersonelModel->getDb();

            // Personele ait tüm bildirimleri bul
            $sql = "SELECT id FROM mesaj_log 
                    WHERE type = 'push' 
                    AND (
                        recipients LIKE :personel_adi 
                        OR recipients LIKE '%Tüm Aboneler%'
                        OR recipients LIKE '%Test Kullanıcısı%'
                    )";

            $stmt = $db->prepare($sql);
            $stmt->execute([':personel_adi' => '%' . $personelAdi . '%']);
            $notifications = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Her birini silindi olarak işaretle
            $insertSql = "INSERT INTO personel_bildirim_durumu (personel_id, mesaj_log_id, silindi, silme_tarihi)
                          VALUES (:personel_id, :mesaj_log_id, 1, NOW())
                          ON DUPLICATE KEY UPDATE silindi = 1, silme_tarihi = NOW()";

            $insertStmt = $db->prepare($insertSql);

            foreach ($notifications as $notifId) {
                $insertStmt->execute([
                    ':personel_id' => $personel_id,
                    ':mesaj_log_id' => $notifId
                ]);
            }

            response(true, null, 'Tüm bildirimler silindi');
            break;


        // ===== Puantaj / İş Takip =====
        case 'getPuantajData':
            $PersonelModel = new PersonelModel();
            $personelData = $PersonelModel->find($personel_id);

            if (!$personelData) {
                response(true, [
                    'items' => [],
                    'stats' => ['toplam' => 0, 'sonuclanan' => 0, 'acik' => 0]
                ]);
            }

            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            $workType = $_POST['work_type'] ?? '';
            $workResult = $_POST['work_result'] ?? '';
            $raporSekmesi = $_POST['rapor_sekmesi'] ?? '';

            $PuantajModel = new \App\Model\PuantajModel();
            $items = $PuantajModel->getFiltered($startDate, $endDate, $personel_id, $workType, $workResult, $raporSekmesi);

            // İstatistikler
            $totalSonuclanan = 0;
            $filteredSonuclanan = 0; // Admin raporuyla eşleşen (ücretli ve tanımlı)
            $totalAcik = 0;

            foreach ($items as $item) {
                $sonuclanan = (int) ($item->sonuclanmis ?? 0);
                $totalSonuclanan += $sonuclanan;
                $totalAcik += (int) ($item->acik_olanlar ?? 0);

                // Admin raporu mantığı: is_turu_ucret > 0 ve rapor_sekmesi tanımlı olanlar
                if (!empty($item->rapor_sekmesi) && ($item->is_turu_ucret > 0)) {
                    $filteredSonuclanan += $sonuclanan;
                }
            }

            response(true, [
                'items' => $items,
                'stats' => [
                    'toplam' => $filteredSonuclanan, // Admin raporu ile tutması için filtrelenmiş toplam
                    'sonuclanan' => $totalSonuclanan, // Gerçek toplam (tüm işler)
                    'acik' => $totalAcik
                ]
            ]);
            break;

        case 'getEndeksData':
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';

            $EndeksOkumaModel = new \App\Model\EndeksOkumaModel();
            $items = $EndeksOkumaModel->getFiltered($startDate, $endDate, $personel_id);

            // İstatistikler
            $totalOkunan = 0;
            $totalAbone = 0;

            foreach ($items as $item) {
                $totalOkunan += (int) ($item->okunan_abone_sayisi ?? 0);
                // Diğer istatistikler gerekirse eklenebilir
            }

            response(true, [
                'items' => $items,
                'stats' => [
                    'toplam_okunan' => $totalOkunan
                ]
            ]);
            break;

        case 'getPuantajWorkTypes':
            $PuantajModel = new \App\Model\PuantajModel();
            $types = $PuantajModel->getWorkTypes($personel_id);
            response(true, $types);
            break;

        case 'getPuantajWorkResults':
            $workType = $_POST['work_type'] ?? null;
            $PuantajModel = new \App\Model\PuantajModel();
            $results = $PuantajModel->getWorkResults($personel_id, $workType);
            response(true, $results);
            break;

        case 'getRecentActivities':
            $PersonelModel = new PersonelModel();
            $db = $PersonelModel->getDb();
            $limit = 10;

            // İzin talepleri
            $izinSql = "SELECT 
                            'izin' as type,
                            id,
                            CASE 
                                WHEN onay_durumu = 'Onaylandı' THEN CONCAT(izin_tipi, ' İzni Onaylandı')
                                WHEN onay_durumu = 'Reddedildi' THEN CONCAT(izin_tipi, ' İzni Reddedildi')
                                WHEN onay_durumu = 'iptal edildi' THEN CONCAT(izin_tipi, ' İzni İptal Edildi')
                                ELSE CONCAT(izin_tipi, ' İzin Talebi')
                            END as title,
                            CONCAT(DATE_FORMAT(baslangic_tarihi, '%d.%m'), ' - ', DATE_FORMAT(bitis_tarihi, '%d.%m'), ' tarihli talebiniz') as description,
                            onay_durumu as status,
                            COALESCE(guncelleme_tarihi, olusturma_tarihi) as activity_date
                        FROM personel_izinleri
                        WHERE personel_id = ? AND silinme_tarihi IS NULL
                        ORDER BY activity_date DESC
                        LIMIT $limit";

            // Avans talepleri
            $avansSql = "SELECT 
                            'avans' as type,
                            id,
                            CASE 
                                WHEN durum = 'onaylandi' THEN 'Avans Talebi Onaylandı'
                                WHEN durum = 'reddedildi' THEN 'Avans Talebi Reddedildi'
                                ELSE 'Avans Talebi Oluşturuldu'
                            END as title,
                            CONCAT(FORMAT(tutar, 0, 'tr_TR'), ' ₺ tutarında avans talebiniz') as description,
                            durum as status,
                            COALESCE(onay_tarihi, talep_tarihi) as activity_date
                        FROM personel_avanslari
                        WHERE personel_id = ? AND silinme_tarihi IS NULL
                        ORDER BY activity_date DESC
                        LIMIT $limit";

            // Genel talepler
            $talepSql = "SELECT 
                            'talep' as type,
                            id,
                            CASE 
                                WHEN durum = 'cozuldu' THEN CONCAT('Talep #', ref_no, ' Çözüldü')
                                WHEN durum = 'devam' THEN CONCAT('Talep #', ref_no, ' İnceleniyor')
                                ELSE CONCAT('Talep #', ref_no, ' Oluşturuldu')
                            END as title,
                            CONCAT(baslik, ' - ', konum) as description,
                            durum as status,
                            COALESCE(cozum_tarihi, olusturma_tarihi) as activity_date
                        FROM personel_talepleri
                        WHERE personel_id = ? AND deleted_at IS NULL
                        ORDER BY activity_date DESC
                        LIMIT $limit";

            // Bordrolar
            $bordroSql = "SELECT 
                            'bordro' as type,
                            bp.id,
                            CONCAT('Bordro Hazırlandı - ', COALESCE(bd.donem_adi, CONCAT('Dönem ', bd.id))) as title,
                            CONCAT(FORMAT(bp.net_maas, 2, 'tr_TR'), ' ₺ net ödeme') as description,
                            'tamamlandi' as status,
                            COALESCE(bp.hesaplama_tarihi, bp.olusturma_tarihi) as activity_date
                        FROM bordro_personel bp
                        JOIN bordro_donemi bd ON bp.donem_id = bd.id
                        WHERE bp.personel_id = ? AND bp.silinme_tarihi IS NULL AND bd.kapali_mi = 1
                        ORDER BY activity_date DESC
                        LIMIT $limit";

            // Duyurular ve Etkinlikler
            $duyuruSql = "SELECT
                            'duyuru' as type,
                            id,
                            baslik as title,
                            icerik as description,
                            'yeni' as status,
                            tarih as activity_date,
                            etkinlik_tarihi
                        FROM duyurular
                        WHERE silinme_tarihi IS NULL
                        AND pwa_goster = 1
                        AND (alici_tipi = 'toplu' OR FIND_IN_SET(?, alici_ids))
                        AND (etkinlik_tarihi IS NULL OR etkinlik_tarihi >= CURDATE())
                        ORDER BY activity_date DESC
                        LIMIT $limit";

            // Verileri çek
            $activities = [];

            // İzinler
            $stmt = $db->prepare($izinSql);
            $stmt->execute([$personel_id]);
            $izinler = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $activities = array_merge($activities, $izinler);

            // Avanslar
            $stmt = $db->prepare($avansSql);
            $stmt->execute([$personel_id]);
            $avanslar = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $activities = array_merge($activities, $avanslar);

            // Talepler
            $stmt = $db->prepare($talepSql);
            $stmt->execute([$personel_id]);
            $talepler = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $activities = array_merge($activities, $talepler);

            // Bordrolar
            $stmt = $db->prepare($bordroSql);
            $stmt->execute([$personel_id]);
            $bordrolar = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $activities = array_merge($activities, $bordrolar);

            // Duyurular
            $stmt = $db->prepare($duyuruSql);
            $stmt->execute([$personel_id]);
            $duyurular = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $activities = array_merge($activities, $duyurular);

            // Tarihe göre sırala (en yeni önce)
            usort($activities, function ($a, $b) {
                return strtotime($b['activity_date']) - strtotime($a['activity_date']);
            });

            // İlk N kaydı al
            $activities = array_slice($activities, 0, $limit);

            // Her etkinlik için formatla
            $formattedActivities = array_map(function ($item) {
                // İkon ve renk belirle
                $iconMap = [
                    'izin' => ['icon' => 'event_available', 'color' => 'blue'],
                    'avans' => ['icon' => 'payments', 'color' => 'green'],
                    'talep' => ['icon' => 'assignment', 'color' => 'orange'],
                    'bordro' => ['icon' => 'receipt_long', 'color' => 'primary'],
                    'duyuru' => ['icon' => 'campaign', 'color' => 'primary']
                ];

                $statusMap = [
                    // İzin durumları
                    'Onaylandı' => ['text' => 'Onaylandı', 'badge' => 'success'],
                    'onaylandi' => ['text' => 'Onaylandı', 'badge' => 'success'],
                    'Reddedildi' => ['text' => 'Reddedildi', 'badge' => 'danger'],
                    'reddedildi' => ['text' => 'Reddedildi', 'badge' => 'danger'],
                    'beklemede' => ['text' => 'Beklemede', 'badge' => 'warning'],
                    'iptal edildi' => ['text' => 'İptal Edildi', 'badge' => 'gray'],
                    // Talep durumları
                    'devam' => ['text' => 'İnceleniyor', 'badge' => 'warning'],
                    'cozuldu' => ['text' => 'Çözüldü', 'badge' => 'success'],
                    // Bordro
                    'tamamlandi' => ['text' => 'Tamamlandı', 'badge' => 'gray'],
                    // Duyuru
                    'yeni' => ['text' => 'Duyuru', 'badge' => 'success']
                ];

                $type = $item['type'];
                $status = strtolower($item['status'] ?? 'beklemede');

                // Göreceli zaman hesapla
                $activityTime = strtotime($item['activity_date']);
                $now = time();
                $diff = $now - $activityTime;

                if ($diff < 60) {
                    $timeAgo = 'Az önce';
                } elseif ($diff < 3600) {
                    $timeAgo = floor($diff / 60) . 'd önce';
                } elseif ($diff < 86400) {
                    $timeAgo = floor($diff / 3600) . 's önce';
                } elseif ($diff < 172800) {
                    $timeAgo = 'Dün';
                } elseif ($diff < 604800) {
                    $timeAgo = floor($diff / 86400) . ' gün önce';
                } else {
                    $timeAgo = date('d M', $activityTime);
                }

                return [
                    'id' => $item['id'],
                    'type' => $type,
                    'icon' => $iconMap[$type]['icon'] ?? 'info',
                    'icon_color' => $iconMap[$type]['color'] ?? 'gray',
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'status' => $status,
                    'status_text' => $statusMap[$status]['text'] ?? ucfirst($status),
                    'status_badge' => $statusMap[$status]['badge'] ?? 'gray',
                    'time_ago' => $timeAgo,
                    'activity_date' => $item['activity_date']
                ];
            }, $activities);

            response(true, $formattedActivities);
            break;

        case 'getEtkinlikSlider':
            $PersonelModel = new PersonelModel();
            $db = $PersonelModel->getDb();
            // Duyuruları son eklenenden geriye doğru al
            $duyuruSql = "SELECT id, baslik, icerik, resim, hedef_sayfa, tarih, etkinlik_tarihi
                        FROM duyurular
                        WHERE silinme_tarihi IS NULL 
                        AND pwa_goster = 1
                        AND durum = 'Yayında'
                        AND (alici_tipi = 'toplu' OR FIND_IN_SET(?, alici_ids))
                        AND (etkinlik_tarihi IS NULL OR etkinlik_tarihi >= CURDATE())
                        ORDER BY id DESC
                        LIMIT 5";
            $stmt = $db->prepare($duyuruSql);
            $stmt->execute([$personel_id]);
            $duyurular = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Resim URL formatı (varsa) ve içerik kısaltması vb. işlemleri yapabiliriz
            $formattedDuyurular = array_map(function ($d) {
                $tarih_metni = date('d.m.Y H:i', strtotime($d['tarih']));
                $kalan_gun = null;
                $kalan_gun_text = null;

                if ($d['etkinlik_tarihi']) {
                    $tarih_metni = "Son: " . date('d.m.Y', strtotime($d['etkinlik_tarihi']));

                    // Kalan günü hesapla
                    $etkinlikDateTime = new DateTime($d['etkinlik_tarihi']);
                    $etkinlikDateTime->setTime(0, 0, 0);
                    $bugun = new DateTime();
                    $bugun->setTime(0, 0, 0);

                    if ($etkinlikDateTime >= $bugun) {
                        $diff = $bugun->diff($etkinlikDateTime);
                        $kalan_gun = $diff->days;
                        $kalan_gun_text = sprintf("%02d", $kalan_gun);
                    }
                }

                return [
                    'id' => $d['id'],
                    'baslik' => $d['baslik'],
                    'icerik' => $d['icerik'],
                    'resim' => getPwaImageUrl($d['resim']),
                    'tarih' => $tarih_metni,
                    'kalan_gun' => $kalan_gun_text,
                    'hedef_sayfa' => $d['hedef_sayfa']
                ];
            }, $duyurular);

            response(true, $formattedDuyurular);
            break;

        case 'getAllEtkinlikler':
            $PersonelModel = new PersonelModel();
            $db = $PersonelModel->getDb();

            $duyuruSql = "SELECT id, baslik, icerik, resim, hedef_sayfa, tarih, etkinlik_tarihi
                        FROM duyurular
                        WHERE silinme_tarihi IS NULL 
                        AND pwa_goster = 1
                        AND durum = 'Yayında'
                        AND (alici_tipi = 'toplu' OR FIND_IN_SET(?, alici_ids))
                        ORDER BY id DESC";
            $stmt = $db->prepare($duyuruSql);
            $stmt->execute([$personel_id]);
            $duyurular = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $formattedDuyurular = array_map(function ($d) {
                $tarih_metni = date('d.m.Y H:i', strtotime($d['tarih']));
                $kalan_gun = null;
                $kalan_gun_text = null;
                $gecmis = false;

                if ($d['etkinlik_tarihi']) {
                    $tarih_metni = "Son: " . date('d.m.Y', strtotime($d['etkinlik_tarihi']));

                    $etkinlikDateTime = new DateTime($d['etkinlik_tarihi']);
                    $etkinlikDateTime->setTime(0, 0, 0);
                    $bugun = new DateTime();
                    $bugun->setTime(0, 0, 0);

                    if ($etkinlikDateTime >= $bugun) {
                        $diff = $bugun->diff($etkinlikDateTime);
                        $kalan_gun = $diff->days;
                        $kalan_gun_text = sprintf("%02d", $kalan_gun);
                    } else {
                        $gecmis = true;
                    }
                }

                return [
                    'id' => $d['id'],
                    'baslik' => $d['baslik'],
                    'icerik' => $d['icerik'],
                    'resim' => getPwaImageUrl($d['resim']),
                    'tarih' => $tarih_metni,
                    'kalan_gun' => $kalan_gun_text,
                    'gecmis' => $gecmis,
                    'hedef_sayfa' => $d['hedef_sayfa']
                ];
            }, $duyurular);

            response(true, $formattedDuyurular);
            break;

        // ===== GÖREV TAKİP İŞLEMLERİ =====
        case 'getGorevDurumu':
            $HareketModel = new PersonelHareketleriModel();
            $acikGorev = $HareketModel->getAcikGorev($personel_id);

            if ($acikGorev) {
                response(true, [
                    'gorev_var' => true,
                    'baslangic_zamani' => $acikGorev->zaman,
                    'baslangic_saat' => date('H:i', strtotime($acikGorev->zaman)),
                    'baslangic_tarih' => date('d.m.Y', strtotime($acikGorev->zaman)),
                    'konum_enlem' => $acikGorev->konum_enlem,
                    'konum_boylam' => $acikGorev->konum_boylam
                ]);
            } else {
                response(true, [
                    'gorev_var' => false
                ]);
            }
            break;

        case 'checkKonumIstegi':
            $db = (new \App\Core\Db())->db;
            $stmt = $db->prepare("SELECT id FROM personel_konum_istekleri WHERE personel_id = :pid AND durum = 'BEKLIYOR' ORDER BY istek_zamani DESC LIMIT 1");
            $stmt->execute([':pid' => $personel_id]);
            $istek = $stmt->fetch(PDO::FETCH_OBJ);

            if ($istek) {
                response(true, ['istek_id' => $istek->id]);
            } else {
                response(true, null);
            }
            break;

        case 'yanitlaKonumIstegi':
            $db = (new \App\Core\Db())->db;
            $istek_id = $_POST['istek_id'] ?? null;
            $lat = $_POST['lat'] ?? null;
            $lng = $_POST['lng'] ?? null;

            if (!$istek_id || !$lat || !$lng) {
                response(false, null, 'Eksik veri');
            }

            $stmt = $db->prepare("UPDATE personel_konum_istekleri SET enlem = :lat, boylam = :lng, durum = 'TAMAMLANDI', yanit_zamani = NOW() WHERE id = :id");
            $result = $stmt->execute([':lat' => $lat, ':lng' => $lng, ':id' => $istek_id]);

            if ($result) {
                response(true, null, 'Konum iletildi.');
            } else {
                response(false, null, 'Konum iletilemedi.');
            }
            break;

        case 'baslaGorev':
            $konum_enlem = $_POST['konum_enlem'] ?? null;
            $konum_boylam = $_POST['konum_boylam'] ?? null;
            $konum_hassasiyeti = $_POST['konum_hassasiyeti'] ?? null;
            $cihaz_bilgisi = $_POST['cihaz_bilgisi'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null;
            $ip_adresi = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

            // Konum zorunlu
            if (empty($konum_enlem) || empty($konum_boylam)) {
                response(false, null, 'Konum bilgisi alınamadı. Lütfen konum iznini kontrol edin.');
            }

            $HareketModel = new PersonelHareketleriModel();

            // Zaten açık görev var mı kontrol
            $acikGorev = $HareketModel->getAcikGorev($personel_id);
            if ($acikGorev) {
                response(false, null, 'Zaten başlamış bir göreviniz var. Önce mevcut görevi bitirin.');
            }

            // Firma ID'yi session'dan al (varsa)
            $firma_id = $_SESSION['firma_id'] ?? null;

            $result = $HareketModel->baslaGorev([
                'personel_id' => $personel_id,
                'konum_enlem' => $konum_enlem,
                'konum_boylam' => $konum_boylam,
                'konum_hassasiyeti' => $konum_hassasiyeti,
                'cihaz_bilgisi' => $cihaz_bilgisi,
                'ip_adresi' => $ip_adresi,
                'firma_id' => $firma_id
            ]);

            if ($result) {
                response(true, [
                    'id' => $result,
                    'baslangic_saat' => date('H:i'),
                    'baslangic_tarih' => date('d.m.Y')
                ], 'Göreve başarıyla başladınız!');
            } else {
                response(false, null, 'Görev başlatılamadı. Lütfen tekrar deneyin.');
            }
            break;

        case 'bitirGorev':
            $konum_enlem = $_POST['konum_enlem'] ?? null;
            $konum_boylam = $_POST['konum_boylam'] ?? null;
            $konum_hassasiyeti = $_POST['konum_hassasiyeti'] ?? null;
            $cihaz_bilgisi = $_POST['cihaz_bilgisi'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null;
            $ip_adresi = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

            // Konum zorunlu
            if (empty($konum_enlem) || empty($konum_boylam)) {
                response(false, null, 'Konum bilgisi alınamadı. Lütfen konum iznini kontrol edin.');
            }

            $HareketModel = new PersonelHareketleriModel();

            // Açık görev var mı kontrol
            $acikGorev = $HareketModel->getAcikGorev($personel_id);
            if (!$acikGorev) {
                response(false, null, 'Bitirilecek aktif bir görev bulunamadı.');
            }

            // Firma ID'yi session'dan al (varsa)
            $firma_id = $_SESSION['firma_id'] ?? null;

            $result = $HareketModel->bitirGorev([
                'personel_id' => $personel_id,
                'konum_enlem' => $konum_enlem,
                'konum_boylam' => $konum_boylam,
                'konum_hassasiyeti' => $konum_hassasiyeti,
                'cihaz_bilgisi' => $cihaz_bilgisi,
                'ip_adresi' => $ip_adresi,
                'firma_id' => $firma_id
            ]);

            if ($result) {
                // Görev süresini hesapla
                $baslangic = new DateTime($acikGorev->zaman);
                $bitis = new DateTime();
                $diff = $baslangic->diff($bitis);
                $sure = '';
                if ($diff->h > 0)
                    $sure .= $diff->h . ' saat ';
                $sure .= $diff->i . ' dakika';

                response(true, [
                    'id' => $result,
                    'bitis_saat' => date('H:i'),
                    'bitis_tarih' => date('d.m.Y'),
                    'calisma_suresi' => $sure
                ], 'Görev başarıyla tamamlandı! Toplam süre: ' . $sure);
            } else {
                response(false, null, 'Görev bitirilemedi. Lütfen tekrar deneyin.');
            }
            break;

        // =====================================================
        // NÖBET İŞLEMLERİ
        // =====================================================
        case 'getNobetler':
            $NobetModel = new \App\Model\NobetModel();
            $yil = $_POST['yil'] ?? date('Y');
            $ay = $_POST['ay'] ?? date('m');
            $firma_id = $personel->firma_id ?? $_SESSION['firma_id'] ?? null;

            $SettingsModel = new \App\Model\SettingsModel();
            $settings = $SettingsModel->getAllSettingsAsKeyValue($firma_id);
            $include_deleted = ($settings['nobet_silinmis_goster'] ?? '0') === '1';

            $baslangic = "$yil-" . str_pad($ay, 2, '0', STR_PAD_LEFT) . "-01";
            $bitis = date('Y-m-t', strtotime($baslangic));

            $nobetler = $NobetModel->getPersonelNobetleri($personel_id, $baslangic, $bitis, $include_deleted);

            $data = [];
            foreach ($nobetler as $nobet) {
                $data[] = [
                    'id' => $nobet->id,
                    'nobet_tarihi' => $nobet->nobet_tarihi,
                    'baslangic_saati' => $nobet->baslangic_saati,
                    'bitis_saati' => $nobet->bitis_saati,
                    'nobet_tipi' => $nobet->nobet_tipi,
                    'aciklama' => $nobet->aciklama,
                    'silinmis_mi' => !empty($nobet->silinme_tarihi),
                    'durum' => $nobet->durum ?? 'aktif'
                ];
            }

            response(true, $data);
            break;

        case 'getNobetTalepleri':
            $NobetModel = new \App\Model\NobetModel();
            $talepler = $NobetModel->getPersonelDegisimTalepleri($personel_id, 'hepsi');

            $data = [];
            foreach ($talepler as $talep) {
                $talepTipi = ($talep->talep_edilen_id == $personel_id) ? 'gelen' : 'giden';

                $data[] = [
                    'id' => $talep->id,
                    'nobet_id' => $talep->nobet_id,
                    'nobet_tarihi' => $talep->nobet_tarihi,
                    'talep_eden_adi' => $talep->talep_eden_adi,
                    'talep_edilen_adi' => $talep->talep_edilen_adi,
                    'durum' => $talep->durum,
                    'aciklama' => $talep->aciklama,
                    'talep_tarihi' => $talep->talep_tarihi,
                    'talep_tipi' => $talepTipi
                ];
            }

            response(true, $data);
            break;

        case 'getNobetPersonelleri':
            $firma_id = $personel->firma_id ?? $_SESSION['firma_id'] ?? null;

            // Aynı firmadaki diğer personelleri getir (doğrudan SQL sorgusu)
            $db = new \App\Core\Db();
            $sql = "SELECT id, adi_soyadi, departman FROM personel 
                    WHERE firma_id = :firma_id 
                    AND aktif_mi = 1 
                    AND silinme_tarihi IS NULL 
                    AND id != :personel_id 
                    AND (disardan_sigortali = 0 OR FIND_IN_SET('nobet', gorunum_modulleri))
                    AND (departman LIKE '%Kesme%' OR departman LIKE '%Açma%')
                    ORDER BY adi_soyadi ASC";
            $stmt = $db->db->prepare($sql);
            $stmt->execute([':firma_id' => $firma_id, ':personel_id' => $personel_id]);
            $personeller = $stmt->fetchAll(PDO::FETCH_OBJ);

            $data = [];
            foreach ($personeller as $p) {
                $data[] = [
                    'id' => $p->id,
                    'adi_soyadi' => $p->adi_soyadi,
                    'departman' => $p->departman
                ];
            }

            response(true, $data);
            break;

        case 'createNobetDegisimTalebi':
            $NobetModel = new \App\Model\NobetModel();
            $nobet_id = $_POST['nobet_id'] ?? null;
            $talep_edilen_id = $_POST['talep_edilen_id'] ?? null;
            $aciklama = $_POST['aciklama'] ?? null;

            if (!$nobet_id || !$talep_edilen_id) {
                response(false, null, 'Gerekli alanlar eksik.');
            }

            // Nöbetin bu personele ait olduğunu kontrol et
            $nobet = $NobetModel->find($nobet_id);
            if (!$nobet || $nobet->personel_id != $personel_id) {
                response(false, null, 'Bu nöbet size ait değil.');
            }

            // Nöbet tarihi geçmiş mi kontrol et
            if (strtotime($nobet->nobet_tarihi) < strtotime('today')) {
                response(false, null, 'Geçmiş tarihli nöbetler için talep oluşturamazsınız.');
            }

            // Zaten bekleyen bir talep var mı?
            if ($NobetModel->hasPendingDegisimTalebi($nobet_id, $personel_id)) {
                response(false, null, 'Bu nöbet için zaten bekleyen bir değişim talebiniz bulunuyor. Mevcut talebi iptal edip yenisini oluşturabilirsiniz.');
            }

            $result = $NobetModel->createDegisimTalebi([
                'nobet_id' => $nobet_id,
                'talep_eden_id' => $personel_id,
                'talep_edilen_id' => $talep_edilen_id,
                'aciklama' => $aciklama
            ]);

            if ($result) {
                // Karşı tarafa bildirim gönder
                try {
                    $NobetModel->sendDegisimTalepBildirimi($talep_edilen_id, $personel->adi_soyadi, $nobet->nobet_tarihi);
                } catch (Exception $e) {
                    // Bildirim hatası ana işlemi etkilemesin
                }

                response(true, ['id' => $result], 'Değişim talebiniz oluşturuldu. Karşı tarafın onayı bekleniyor.');
            } else {
                response(false, null, 'Talep oluşturulamadı.');
            }
            break;

        case 'onaylaNobetDegisimTalebi':
            $NobetModel = new \App\Model\NobetModel();
            $talep_id = $_POST['talep_id'] ?? null;

            if (!$talep_id) {
                response(false, null, 'Talep ID gerekli.');
            }

            // Talebin bu personele geldiğini kontrol et
            $sql = "SELECT dt.*, n.nobet_tarihi FROM nobet_degisim_talepleri dt 
                    LEFT JOIN nobetler n ON dt.nobet_id = n.id 
                    WHERE dt.id = :id AND dt.talep_edilen_id = :pid AND dt.durum = 'beklemede'";
            $db = new \App\Core\Db();
            $stmt = $db->db->prepare($sql);
            $stmt->execute([':id' => $talep_id, ':pid' => $personel_id]);
            $talep = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$talep) {
                response(false, null, 'Bu talep size ait değil veya zaten işlem görmüş.');
            }

            $result = $NobetModel->onaylaPersonelTalebi($talep_id);

            if ($result) {
                // Yöneticiye mail ve bildirim
                try {
                    $BildirimModel = new BildirimModel();
                    $BildirimModel->createNotification(
                        1, // Admin user_id
                        'Nöbet Değişim Talebi Onayı Bekliyor',
                        $personel->adi_soyadi . " nöbet değişim talebini onayladı. Yönetici onayı bekleniyor.",
                        'index?p=nobet/list'
                    );
                } catch (Exception $e) {
                }

                response(true, null, 'Talebi onayladınız. Yönetici onayı bekleniyor.');
            } else {
                response(false, null, 'Onaylama işlemi başarısız.');
            }
            break;

        case 'reddetNobetDegisimTalebi':
            $NobetModel = new \App\Model\NobetModel();
            $talep_id = $_POST['talep_id'] ?? null;

            if (!$talep_id) {
                response(false, null, 'Talep ID gerekli.');
            }

            // Talebin bu personele geldiğini kontrol et
            $db = new \App\Core\Db();
            $sql = "SELECT * FROM nobet_degisim_talepleri WHERE id = :id AND talep_edilen_id = :pid AND durum = 'beklemede'";
            $stmt = $db->db->prepare($sql);
            $stmt->execute([':id' => $talep_id, ':pid' => $personel_id]);
            $talep = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$talep) {
                response(false, null, 'Bu talep size ait değil veya zaten işlem görmüş.');
            }

            $result = $NobetModel->reddetTalebi($talep_id, $personel_id, 'Personel tarafından reddedildi');

            if ($result) {
                // Talep edene bildirim
                try {
                    $NobetModel->sendDegisimSonucBildirimi($talep->talep_eden_id, 'reddedildi', '');
                } catch (Exception $e) {
                }

                response(true, null, 'Talebi reddettiniz.');
            } else {
                response(false, null, 'Reddetme işlemi başarısız.');
            }
            break;

        case 'iptalNobetDegisimTalebi':
            $NobetModel = new \App\Model\NobetModel();
            $talep_id = $_POST['talep_id'] ?? null;

            if (!$talep_id) {
                response(false, null, 'Talep ID gerekli.');
            }

            $result = $NobetModel->iptalDegisimTalebi($talep_id, $personel_id);

            if ($result) {
                response(true, null, 'Değişim talebiniz iptal edildi.');
            } else {
                response(false, null, 'Talep iptal edilemedi veya zaten onaylanmış/iptal edilmiş.');
            }
            break;

        case 'updateNobetDegisimTalebi':
            $NobetModel = new \App\Model\NobetModel();
            $talep_id = $_POST['talep_id'] ?? null;
            $talep_edilen_id = $_POST['talep_edilen_id'] ?? null;
            $aciklama = $_POST['aciklama'] ?? null;

            if (!$talep_id || !$talep_edilen_id) {
                response(false, null, 'Gerekli alanlar eksik.');
            }

            // Talebin bu personele ait ve beklemede olduğunu kontrol et
            $db = new \App\Core\Db();
            $sql = "SELECT id FROM nobet_degisim_talepleri WHERE id = :id AND talep_eden_id = :pid AND durum = 'beklemede'";
            $stmt = $db->db->prepare($sql);
            $stmt->execute([':id' => $talep_id, ':pid' => $personel_id]);
            if (!$stmt->fetch()) {
                response(false, null, 'Bu talep güncellenemez.');
            }

            $result = $NobetModel->updateDegisimTalebi($talep_id, [
                'talep_edilen_id' => $talep_edilen_id,
                'aciklama' => $aciklama
            ]);

            if ($result) {
                response(true, null, 'Değişim talebiniz güncellendi.');
            } else {
                response(false, null, 'Talep güncellenemedi.');
            }
            break;

        case 'createNobetMazeretBildirimi':
            $NobetModel = new \App\Model\NobetModel();
            $nobet_id = $_POST['nobet_id'] ?? null;
            $aciklama = $_POST['aciklama'] ?? null;

            if (!$nobet_id || !$aciklama) {
                response(false, null, 'Gerekli alanlar eksik.');
            }

            // Nöbetin bu personele ait olduğunu kontrol et
            $nobet = $NobetModel->find($nobet_id);
            if (!$nobet || $nobet->personel_id != $personel_id) {
                response(false, null, 'Bu nöbet size ait değil.');
            }

            // Nöbet durumunu mazeret_bildirildi olarak güncelle
            $result = $NobetModel->updateNobet($nobet_id, [
                'durum' => 'mazeret_bildirildi',
                'mazeret_aciklama' => $aciklama,
                'mazeret_tarihi' => date('Y-m-d H:i:s')
            ]);

            if ($result) {
                // Yöneticiye mail ve bildirim
                try {
                    $BildirimModel = new BildirimModel();
                    $tarihFormatli = date('d.m.Y', strtotime($nobet->nobet_tarihi));
                    $BildirimModel->createNotification(
                        1, // Admin user_id
                        'Nöbet Mazeret Bildirimi',
                        $personel->adi_soyadi . ", $tarihFormatli tarihli nöbeti için mazeret bildirdi: " . mb_substr($aciklama, 0, 100),
                        'index?p=nobet/list'
                    );

                    // Mail gönder - Admin kullanıcıları doğrudan sorgula
                    $dbMail = new \App\Core\Db();
                    $sqlMail = "SELECT email FROM users WHERE role = 'admin' AND email IS NOT NULL AND email != ''";
                    $stmtMail = $dbMail->db->prepare($sqlMail);
                    $stmtMail->execute();
                    $adminler = $stmtMail->fetchAll(PDO::FETCH_OBJ);
                    foreach ($adminler as $admin) {
                        if ($admin->email) {
                            MailGonderService::gonder(
                                [$admin->email],
                                'Nöbet Mazeret Bildirimi - ' . $personel->adi_soyadi,
                                "<p><strong>{$personel->adi_soyadi}</strong>, <strong>$tarihFormatli</strong> tarihli nöbeti için mazeret bildirdi.</p><p><strong>Mazeret:</strong> $aciklama</p>"
                            );
                        }
                    }
                } catch (Exception $e) {
                    error_log("Nöbet mazeret bildirim hatası: " . $e->getMessage());
                }

                response(true, null, 'Mazeret bildiriminiz yöneticiye iletildi.');
            } else {
                response(false, null, 'Mazeret bildirimi kaydedilemedi.');
            }
            break;

        // ============ Nöbet Talep İşlemleri ============
        case 'getMusaitNobetGunleri':
            $yil = $_POST['yil'] ?? date('Y');
            $ay = $_POST['ay'] ?? date('m');
            $firma_id = $personel->firma_id ?? $_SESSION['firma_id'] ?? null;

            $baslangic = "$yil-" . str_pad($ay, 2, '0', STR_PAD_LEFT) . "-01";
            $bitis = date('Y-m-t', strtotime($baslangic));

            $NobetModel = new \App\Model\NobetModel();
            $db = new \App\Core\Db();

            // Bu personelin atanmış nöbetlerini getir (aktif olanlar - durum NULL veya standart)
            $sql = "SELECT DISTINCT nobet_tarihi FROM nobetler 
                    WHERE firma_id = :firma_id 
                    AND personel_id = :personel_id
                    AND nobet_tarihi BETWEEN :bas AND :bit 
                    AND silinme_tarihi IS NULL
                    AND (durum IS NULL OR durum NOT IN ('talep_edildi', 'reddedildi'))";
            $stmt = $db->db->prepare($sql);
            $stmt->execute([':firma_id' => $firma_id, ':personel_id' => $personel_id, ':bas' => $baslangic, ':bit' => $bitis]);
            $assignedDays = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Bu personelin bekleyen nöbet taleplerini getir (nobetler tablosundan)
            $requestedDays = $NobetModel->getPersonelBekleyenTalepTarihleri($personel_id);

            $allDays = [];
            $gunler_tr = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];

            $current = strtotime($baslangic);
            $end = strtotime($bitis);
            $today = strtotime(date('Y-m-d'));

            while ($current <= $end) {
                $date = date('Y-m-d', $current);
                // Sadece bugün ve sonrası, atanmamış ve talep edilmemiş günleri getir
                if ($current >= $today && !in_array($date, $assignedDays) && !in_array($date, $requestedDays)) {
                    $allDays[] = [
                        'tarih' => $date,
                        'formatli' => date('d.m.Y', $current),
                        'gun' => date('d', $current),
                        'gun_adi' => $gunler_tr[date('w', $current)]
                    ];
                }
                $current = strtotime("+1 day", $current);
            }
            response(true, $allDays);
            break;

        case 'getBireyselNobetTalepleri':
            $yil = $_POST['yil'] ?? date('Y');
            $ay = $_POST['ay'] ?? date('m');
            $baslangic = "$yil-" . str_pad($ay, 2, '0', STR_PAD_LEFT) . "-01";
            $bitis = date('Y-m-t', strtotime($baslangic));

            $NobetModel = new \App\Model\NobetModel();
            $talepler = $NobetModel->getPersonelBekleyenTalepler($personel_id, $baslangic, $bitis);

            response(true, $talepler);
            break;

        case 'iptalEtNobetTalebi':
            $talep_id = $_POST['talep_id'] ?? null;
            if (!$talep_id)
                response(false, null, 'Talep ID gerekli.');

            $NobetModel = new \App\Model\NobetModel();
            $result = $NobetModel->iptalNobetTalebi($talep_id, $personel_id);

            if ($result) {
                response(true, null, 'Nöbet talebi iptal edildi.');
            } else {
                response(false, null, 'İşlem başarısız.');
            }
            break;

        case 'createYeniNobetTalebi':
            $tarih_string = $_POST['tarih'] ?? '';
            $aciklama = $_POST['aciklama'] ?? '';

            if (empty($tarih_string)) {
                response(false, null, 'Lütfen en az bir tarih seçiniz.');
            }

            $tarihler = explode(',', $tarih_string);
            $NobetModel = new \App\Model\NobetModel();
            $firma_id = $personel->firma_id ?? $_SESSION['firma_id'] ?? null;
            $success_count = 0;

            foreach ($tarihler as $tarih) {
                $tarih = trim($tarih);
                if (empty($tarih))
                    continue;

                $result = $NobetModel->addNobetTalebi([
                    'firma_id' => $firma_id,
                    'personel_id' => $personel_id,
                    'nobet_tarihi' => $tarih,
                    'aciklama' => $aciklama
                ]);

                if ($result) {
                    $success_count++;
                }
            }

            if ($success_count > 0) {
                // Yöneticiye bildirim
                try {
                    $BildirimModel = new BildirimModel();
                    $tarih_sayisi = count($tarihler);
                    $mesaj = ($personel->adi_soyadi ?? 'Bir personel') . ", $tarih_sayisi farklı gün için nöbet talep etti.";

                    if ($tarih_sayisi == 1) {
                        $tarihFormatli = date('d.m.Y', strtotime($tarihler[0]));
                        $mesaj = ($personel->adi_soyadi ?? 'Bir personel') . ", $tarihFormatli tarihi için nöbet talep etti.";
                    }

                    $BildirimModel->createNotification(
                        1,
                        'Yeni Nöbet Talebi',
                        $mesaj,
                        'index?p=nobet/talepler#talepler',
                        'calendar',
                        'info'
                    );
                } catch (Exception $e) {
                }

                response(true, null, "$success_count adet nöbet talebiniz iletildi. Yönetici onayı bekleniyor.");
            } else {
                response(false, null, 'Talepler oluşturulamadı.');
            }
            break;

        // =====================================================
        // CANLI DESTEK İŞLEMLERİ
        // =====================================================
        case 'check-chat':
            $DestekModel = new \App\Model\DestekModel();
            $isWorkingHours = $DestekModel->isWorkingHours();
            $outOfHoursMsg = $DestekModel->getOutOfHoursMessage();
            $Settings = new \App\Model\SettingsModel();
            $adminDurum = $Settings->getSettings('canli_destek_admin_durum') ?: 'cevrimici';

            // Aktif konuşma var mı? (yeni oluşturma!)
            $existing = $DestekModel->getActiveConversation($personel_id);
            if ($existing) {
                $messages = $DestekModel->getMessages($existing->id);
                $DestekModel->markMessagesAsRead($existing->id, 'personel');
                response(true, [
                    'has_conversation' => true,
                    'konusma_id' => $existing->id,
                    'messages' => $messages,
                    'is_working_hours' => $isWorkingHours,
                    'out_of_hours_message' => $outOfHoursMsg,
                    'admin_durum' => $adminDurum
                ]);
            } else {
                response(true, [
                    'has_conversation' => false,
                    'is_working_hours' => $isWorkingHours,
                    'out_of_hours_message' => $outOfHoursMsg,
                    'admin_durum' => $adminDurum
                ]);
            }
            break;

        case 'start-chat':
            $DestekModel = new \App\Model\DestekModel();
            $konu = $_POST['konu'] ?? 'Destek Talebi';
            $isWorkingHours = $DestekModel->isWorkingHours();
            $outOfHoursMsg = $DestekModel->getOutOfHoursMessage();

            if (!$isWorkingHours) {
                response(false, ['is_working_hours' => false, 'out_of_hours_message' => $outOfHoursMsg], 'Mesai saatleri dışında yeni destek talebi başlatılamaz.');
                break;
            }

            $Settings = new \App\Model\SettingsModel();
            $adminDurum = $Settings->getSettings('canli_destek_admin_durum') ?: 'cevrimici';

            // Aktif konuşma var mı?
            $existing = $DestekModel->getActiveConversation($personel_id);
            if ($existing) {
                $messages = $DestekModel->getMessages($existing->id);
                $DestekModel->markMessagesAsRead($existing->id, 'personel');
                response(true, [
                    'konusma_id' => $existing->id,
                    'messages' => $messages,
                    'is_new' => false,
                    'is_working_hours' => $isWorkingHours,
                    'out_of_hours_message' => $outOfHoursMsg,
                    'admin_durum' => $adminDurum
                ]);
                break;
            }

            // Yeni konuşma başlat
            $konusmaId = $DestekModel->startConversation($personel_id, $konu);

            // Hoşgeldin mesajı
            $DestekModel->sendSystemMessage($konusmaId, 'Merhaba ' . ($personel->adi_soyadi ?? '') . '! 👋 Size nasıl yardımcı olabiliriz?');

            // Mesai dışı kontrolü
            if (!$DestekModel->isWorkingHours()) {
                $DestekModel->sendSystemMessage($konusmaId, $DestekModel->getOutOfHoursMessage());
            }

            $messages = $DestekModel->getMessages($konusmaId);

            // Yöneticiye bildirim
            try {
                $BildirimModel = new \App\Model\BildirimModel();
                $BildirimModel->createNotification(
                    1,
                    'Yeni Destek Talebi',
                    ($personel->adi_soyadi ?? 'Bir personel') . ' canlı destek başlattı.',
                    'index.php?p=home',
                    'chat',
                    'info'
                );
            } catch (Exception $e) {
            }

            response(true, [
                'konusma_id' => $konusmaId,
                'messages' => $messages,
                'is_new' => true,
                'admin_durum' => $adminDurum
            ]);
            break;

        case 'send-chat-message':
            $DestekModel = new \App\Model\DestekModel();
            $konusmaId = (int) ($_POST['konusma_id'] ?? 0);
            $mesaj = trim($_POST['mesaj'] ?? '');

            if (!$DestekModel->isWorkingHours()) {
                response(false, ['is_working_hours' => false, 'out_of_hours_message' => $DestekModel->getOutOfHoursMessage()], 'Mesai saatleri dışında mesaj gönderilemez.');
                break;
            }

            if (!$konusmaId || empty($mesaj)) {
                response(false, null, 'Mesaj boş olamaz');
                break;
            }

            // Kapalı/çözülmüş konuşmaya mesaj göndermeyi engelle
            $conversation = $DestekModel->getConversation($konusmaId);
            if ($conversation && in_array($conversation->durum, ['kapali', 'cozuldu'])) {
                response(false, null, 'Bu konuşma kapatılmış. Yeni bir destek talebi oluşturabilirsiniz.');
                break;
            }

            $mesajId = $DestekModel->sendMessage($konusmaId, [
                'tip' => 'personel',
                'id' => $personel_id
            ], $mesaj);

            // Mesai dışıysa ve ilk mesajsa otomatik yanıt gönder (sadece ilk mesajda)
            $conversation = $DestekModel->getConversation($konusmaId);
            if (!$DestekModel->isWorkingHours()) {
                // Son 5 dakika içinde sistem mesajı gönderilmiş mi?
                $recentMessages = $DestekModel->getMessages($konusmaId);
                $hasSysMsg = false;
                foreach ($recentMessages as $m) {
                    if (
                        $m->gonderen_tip === 'sistem' &&
                        strtotime($m->created_at) > strtotime('-5 minutes')
                    ) {
                        $hasSysMsg = true;
                        break;
                    }
                }
                if (!$hasSysMsg) {
                    $DestekModel->sendSystemMessage($konusmaId, $DestekModel->getOutOfHoursMessage());
                }
            } else {
                // Mesai içindeyiz, yönetici durumunu kontrol et
                $Settings = new \App\Model\SettingsModel();
                $adminDurum = $Settings->getSettings('canli_destek_admin_durum') ?: 'cevrimici';

                if ($adminDurum !== 'cevrimici') {
                    // Son 5 dakika içinde sistem mesajı gönderilmiş mi?
                    $recentMessages = $DestekModel->getMessages($konusmaId);
                    $hasSysMsg = false;
                    foreach ($recentMessages as $m) {
                        if (
                            $m->gonderen_tip === 'sistem' &&
                            strtotime($m->created_at) > strtotime('-5 minutes')
                        ) {
                            $hasSysMsg = true;
                            break;
                        }
                    }
                    if (!$hasSysMsg) {
                        $durumLabel = $adminDurum === 'mesgul' ? 'meşgul' : 'çevrimdışı';
                        $sysMsg = "🕐 Şu anda destek ekibimiz {$durumLabel}. Mesajınız bize ulaştı, müsait olunduğunda yanıt verilecektir.";
                        $DestekModel->sendSystemMessage($konusmaId, $sysMsg);
                    }
                }
            }

            $opponentLastReadId = $DestekModel->getOpponentLastReadId($konusmaId, 'personel');

            response(true, [
                'message_id' => $mesajId,
                'opponent_last_read_id' => $opponentLastReadId
            ]);
            break;

        case 'get-chat-messages':
            $DestekModel = new \App\Model\DestekModel();
            $konusmaId = (int) ($_POST['konusma_id'] ?? 0);
            $afterId = (int) ($_POST['after_id'] ?? 0);

            if (!$konusmaId) {
                response(false, null, 'Konuşma ID gerekli');
                break;
            }

            $messages = $DestekModel->getMessages($konusmaId, $afterId);
            $DestekModel->markMessagesAsRead($konusmaId, 'personel');

            $isWorkingHours = $DestekModel->isWorkingHours();
            $outOfHoursMsg = $DestekModel->getOutOfHoursMessage();

            $opponentLastReadId = $DestekModel->getOpponentLastReadId($konusmaId, 'personel');

            $Settings = new \App\Model\SettingsModel();
            $adminDurum = $Settings->getSettings('canli_destek_admin_durum') ?: 'cevrimici';

            response(true, [
                'messages' => $messages,
                'is_working_hours' => $isWorkingHours,
                'out_of_hours_message' => $outOfHoursMsg,
                'opponent_last_read_id' => $opponentLastReadId,
                'admin_durum' => $adminDurum
            ]);
            break;

        case 'poll-chat':
            $DestekModel = new \App\Model\DestekModel();
            $konusmaId = (int) ($_POST['konusma_id'] ?? 0);
            $afterId = (int) ($_POST['after_id'] ?? 0);

            if (!$konusmaId) {
                response(false, null, 'Konuşma ID gerekli');
                break;
            }

            $newMessages = $DestekModel->getMessages($konusmaId, $afterId);
            if (!empty($newMessages)) {
                $DestekModel->markMessagesAsRead($konusmaId, 'personel');
            }

            $opponentLastReadId = $DestekModel->getOpponentLastReadId($konusmaId, 'personel');
            $Settings = new \App\Model\SettingsModel();
            $adminDurum = $Settings->getSettings('canli_destek_admin_durum') ?: 'cevrimici';

            response(true, [
                'messages' => $newMessages,
                'has_new' => !empty($newMessages),
                'opponent_last_read_id' => $opponentLastReadId,
                'admin_durum' => $adminDurum
            ]);
            break;

        case 'get-chat-unread':
            $DestekModel = new \App\Model\DestekModel();
            $unread = $DestekModel->getUnreadForPersonel($personel_id);
            response(true, ['count' => $unread]);
            break;

        case 'get-chat-history':
            $DestekModel = new \App\Model\DestekModel();
            $conversations = $DestekModel->getConversationHistory($personel_id, 20);
            response(true, ['conversations' => $conversations]);
            break;

        case 'send-chat-image':
            $DestekModel = new \App\Model\DestekModel();
            $konusmaId = (int) ($_POST['konusma_id'] ?? 0);

            if (!$konusmaId) {
                response(false, null, 'Konuşma ID gerekli');
                break;
            }

            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                response(false, null, 'Resim dosyası gerekli');
                break;
            }

            // Dosya yükleme
            $uploadDir = dirname(dirname(__DIR__)) . '/uploads/destek/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                response(false, null, 'Sadece resim dosyaları yüklenebilir');
                break;
            }

            if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                response(false, null, 'Dosya boyutu 5MB\'dan büyük olamaz');
                break;
            }

            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fileName = 'chat_' . uniqid() . '_' . time() . '.' . $ext;
            $filePath = $uploadDir . $fileName;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
                response(false, null, 'Dosya yüklenirken hata oluştu');
                break;
            }

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
            $relPath = str_replace($docRoot, '', $uploadDir);
            $relPath = '/' . ltrim(str_replace('\\', '/', $relPath), '/');
            $fileUrl = "{$protocol}://{$host}{$relPath}{$fileName}";

            $mesajId = $DestekModel->sendMessage($konusmaId, [
                'tip' => 'personel',
                'id' => $personel_id
            ], '📷 Resim', $fileUrl, $mimeType);

            $opponentLastReadId = $DestekModel->getOpponentLastReadId($konusmaId, 'personel');

            response(true, [
                'message_id' => $mesajId,
                'file_url' => $fileUrl,
                'opponent_last_read_id' => $opponentLastReadId
            ]);
            break;

        case 'close-chat':
            $DestekModel = new \App\Model\DestekModel();
            $konusmaId = (int) ($_POST['konusma_id'] ?? 0);

            if (!$konusmaId) {
                response(false, null, 'Konuşma ID gerekli');
                break;
            }

            $DestekModel->updateStatus($konusmaId, 'kapali');
            $DestekModel->sendSystemMessage($konusmaId, '✅ Konuşma kapatıldı. İyi günler dileriz!');

            response(true, null, 'Konuşma kapatıldı');
            break;



        case 'update-chat-status':
            $DestekModel = new \App\Model\DestekModel();
            $konusmaId = (int) ($_POST['konusma_id'] ?? 0);
            $durum = $_POST['durum'] ?? '';

            if (!$konusmaId) {
                response(false, null, 'Konuşma ID gerekli');
                break;
            }

            if (!in_array($durum, ['cozuldu', 'kapali'])) {
                response(false, null, 'Geçersiz durum');
                break;
            }

            $DestekModel->updateStatus($konusmaId, $durum);

            if ($durum === 'cozuldu') {
                $DestekModel->sendSystemMessage($konusmaId, '✅ Konuşma personel tarafından çözüldü olarak işaretlendi.');
            } else {
                $DestekModel->sendSystemMessage($konusmaId, '🔒 Konuşma personel tarafından kapatıldı.');
            }

            response(true, null, $durum === 'cozuldu' ? 'Konuşma çözüldü' : 'Konuşma kapatıldı');
            break;

        case 'get-admin-status':
            $settingsModel = new \App\Model\SettingsModel();
            $adminStatus = $settingsModel->getSettings('canli_destek_admin_durum') ?: 'cevrimici';
            response(true, ['status' => $adminStatus]);
            break;

        // ===== Zimmet İşlemleri =====
        case 'getZimmetler':
            $ZimmetModel = new \App\Model\DemirbasZimmetModel();
            $zimmetler = $ZimmetModel->getByPersonel($personel_id);

            $AracZimmetModel = new \App\Model\AracZimmetModel();
            $aracZimmetler = $AracZimmetModel->getByPersonel($personel_id);

            $data = [];

            // Demirbaş Zimmetleri
            foreach ($zimmetler as $item) {
                $durum_map = [
                    'teslim' => ['text' => 'Zimmetli', 'color' => 'amber'],
                    'iade' => ['text' => 'İade Edildi', 'color' => 'emerald'],
                    'kayip' => ['text' => 'Kayıp', 'color' => 'rose'],
                    'arizali' => ['text' => 'Arızalı', 'color' => 'slate']
                ];

                $durum = $durum_map[$item->durum] ?? ['text' => $item->durum, 'color' => 'primary'];

                $data[] = [
                    'id' => $item->id,
                    'type' => 'demirbas',
                    'is_encrypted' => \App\Helper\Security::encrypt($item->id),
                    'demirbas_adi' => $item->demirbas_adi,
                    'demirbas_no' => $item->demirbas_no,
                    'kategori' => $item->kategori_adi,
                    'marka_model' => trim(($item->marka ?? '') . ' ' . ($item->model ?? '')),
                    'seri_no' => $item->seri_no,
                    'miktar' => $item->teslim_miktar,
                    'teslim_tarihi' => date('d.m.Y', strtotime($item->teslim_tarihi)),
                    'durum' => $item->durum,
                    'durum_text' => $durum['text'],
                    'durum_color' => $durum['color']
                ];
            }

            // Araç Zimmetleri
            foreach ($aracZimmetler as $item) {
                $data[] = [
                    'id' => $item->id,
                    'type' => 'arac',
                    'is_encrypted' => \App\Helper\Security::encrypt($item->id),
                    'demirbas_adi' => $item->plaka,
                    'demirbas_no' => 'ARAÇ',
                    'kategori' => 'Taşıt',
                    'marka_model' => trim(($item->marka ?? '') . ' ' . ($item->model ?? '')),
                    'seri_no' => $item->plaka,
                    'miktar' => 1,
                    'teslim_tarihi' => date('d.m.Y', strtotime($item->zimmet_tarihi)),
                    'durum' => $item->durum === 'aktif' ? 'teslim' : 'iade',
                    'durum_text' => $item->durum === 'aktif' ? 'Zimmetli' : 'İade Edildi',
                    'durum_color' => $item->durum === 'aktif' ? 'amber' : 'emerald'
                ];
            }

            // Tarihe göre sırala
            usort($data, function ($a, $b) {
                return strtotime($b['teslim_tarihi']) - strtotime($a['teslim_tarihi']);
            });

            response(true, $data);
            break;

        case 'getZimmetHareketleri':
            $zimmet_id = $_POST['zimmet_id'] ?? 0;
            $type = $_POST['type'] ?? 'demirbas';

            if ($type === 'arac') {
                $ZimmetModel = new \App\Model\AracZimmetModel();
                $zimmet = $ZimmetModel->find($zimmet_id);

                if (!$zimmet || $zimmet->personel_id != $personel_id) {
                    response(false, null, 'Yetkisiz erişim veya kayıt bulunamadı.');
                }

                $data = [];
                $data[] = [
                    'id' => 'z' . $zimmet->id,
                    'tip' => 'zimmet',
                    'tip_text' => '<span class="badge bg-warning">Zimmet</span>',
                    'miktar' => 1,
                    'tarih' => date('d.m.Y', strtotime($zimmet->zimmet_tarihi)),
                    'aciklama' => 'Araç zimmet teslimi. Başlangıç KM: ' . ($zimmet->baslangic_km ?? '-'),
                    'islem_yapan' => '-'
                ];

                if ($zimmet->durum === 'iade_edildi' && $zimmet->iade_tarihi) {
                    $data[] = [
                        'id' => 'i' . $zimmet->id,
                        'tip' => 'iade',
                        'tip_text' => '<span class="badge bg-success">İade</span>',
                        'miktar' => 1,
                        'tarih' => date('d.m.Y', strtotime($zimmet->iade_tarihi)),
                        'aciklama' => 'Araç iade edildi. İade KM: ' . ($zimmet->iade_km ?? '-'),
                        'islem_yapan' => '-'
                    ];
                }

                response(true, $data);
            } else {
                // Güvenlik: Zimmetin bu personele ait olduğunu doğrula
                $ZimmetModel = new \App\Model\DemirbasZimmetModel();
                $zimmet = $ZimmetModel->find($zimmet_id);

                if (!$zimmet || $zimmet->personel_id != $personel_id) {
                    response(false, null, 'Yetkisiz erişim veya kayıt bulunamadı.');
                }

                $HareketModel = new \App\Model\DemirbasHareketModel();
                $hareketler = $HareketModel->getZimmetHareketleri($zimmet_id);

                $data = array_map(function ($h) {
                    return [
                        'id' => $h->id,
                        'tip' => $h->hareket_tipi,
                        'tip_text' => \App\Model\DemirbasHareketModel::getHareketTipiBadge($h->hareket_tipi),
                        'miktar' => $h->miktar,
                        'tarih' => date('d.m.Y', strtotime($h->tarih)),
                        'aciklama' => $h->aciklama,
                        'islem_yapan' => $h->islem_yapan_adi
                    ];
                }, $hareketler);

                response(true, $data);
            }
            break;

        default:
            response(false, null, 'Geçersiz işlem');
    }
} catch (Exception $e) {
    response(false, null, 'Bir hata oluştu: ' . $e->getMessage());
}
