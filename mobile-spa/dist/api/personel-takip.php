<?php

declare(strict_types=1);

use App\Helper\Security;
use App\Model\PersonelTakipModel;
use App\Model\SystemLogModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
ini_set('serialize_precision', '-1');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function takipResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function takipCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if ($token === '' || $sessionToken === '' || !hash_equals((string) $sessionToken, (string) $token)) {
        takipResponse(['success' => false, 'message' => 'Güvenlik doğrulaması (CSRF) geçersiz. Lütfen sayfayı yenileyin.'], 419);
    }
}

function takipTarih(?string $value, string $default): string
{
    $value = trim((string) $value);
    $dt = DateTime::createFromFormat('!Y-m-d', $value);
    return ($dt && $dt->format('Y-m-d') === $value) ? $value : $default;
}

function takipAralik(string $default = '-6 days'): array
{
    $bugun = date('Y-m-d');
    $bitis = takipTarih($_GET['bitis'] ?? null, $bugun);
    $baslangic = takipTarih($_GET['baslangic'] ?? null, date('Y-m-d', strtotime($bitis . ' ' . $default)));
    if ($baslangic > $bitis) {
        [$baslangic, $bitis] = [$bitis, $baslangic];
    }
    if ((strtotime($bitis) - strtotime($baslangic)) / 86400 > 92) {
        $baslangic = date('Y-m-d', strtotime($bitis . ' -92 days'));
    }
    return [$baslangic, $bitis];
}

function takipPersonelId(string $token): int
{
    $token = trim($token);
    if ($token === '') {
        return 0;
    }
    $decrypted = Security::decrypt($token);
    return is_numeric($decrypted) ? (int) $decrypted : 0;
}

function takipFoto(array $p): ?string
{
    return trim((string) ($p['resim_yolu'] ?? '')) !== ''
        ? '/api/personnel-photo.php?id=' . urlencode(Security::encrypt((string) $p['id']))
        : null;
}

function takipSaat(?string $datetime): ?string
{
    return $datetime ? date('H:i', strtotime($datetime)) : null;
}

const TAKIP_GUNLER = ['1' => 'Pzt', '2' => 'Sal', '3' => 'Çar', '4' => 'Per', '5' => 'Cum', '6' => 'Cmt', '7' => 'Paz'];

if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
    takipResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.'], 401);
}
if (!Gate::allows('personel-takip/list') && !Gate::isSuperAdmin()) {
    takipResponse(['success' => false, 'message' => 'Personel takip modülüne erişim yetkiniz bulunmuyor.'], 403);
}

$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
if ($firmaId <= 0) {
    takipResponse(['success' => false, 'message' => 'Geçerli bir firma seçilmemiş.'], 400);
}

$userId = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
$canMesai = Gate::allows('personel_takip_deparmana_gore_ise_baslama_belirleme') || Gate::isSuperAdmin();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? 'gun');

if ($method === 'GET') {
    session_write_close();
}

$Takip = new PersonelTakipModel();
$SystemLog = new SystemLogModel();

try {
    if ($method === 'GET' && $action === 'gun') {
        $bugun = date('Y-m-d');
        $tarih = takipTarih($_GET['tarih'] ?? null, $bugun);
        if ($tarih > $bugun) {
            $tarih = $bugun;
        }
        $bugunMu = $tarih === $bugun;
        $nowTs = time();

        $personeller = $Takip->takipPersonelleri($firmaId);
        $ozetler = $Takip->gunlukOzetler($Takip->hareketler($firmaId, $tarih, $tarih), $bugun, $nowTs);
        $izinler = $Takip->izinGunleri($firmaId, $tarih, $tarih);
        $aciklamalar = $Takip->aciklamalar($firmaId, $tarih, $tarih);
        $canli = $bugunMu ? $Takip->canliKonumlar($firmaId) : [];
        $bekleyen = $bugunMu ? $Takip->bekleyenKonumIstekleri($firmaId) : [];

        $sayac = ['toplam' => 0, 'gorevde' => 0, 'kapanmadi' => 0, 'tamamladi' => 0, 'baslamadi' => 0, 'izinli' => 0, 'gec' => 0, 'toplam_dakika' => 0];
        $departmanlar = [];
        $items = [];

        foreach ($personeller as $p) {
            $pid = (int) $p['id'];
            $departman = (string) ($p['departman'] ?? '');
            $mesai = $Takip->mesaiSaati($departman);
            $ozet = $ozetler[$pid][$tarih] ?? null;
            $izin = $izinler[$pid][$tarih] ?? null;

            if ($ozet && $ozet['devam_ediyor']) {
                $durum = $bugunMu ? 'gorevde' : 'kapanmadi';
            } elseif ($ozet) {
                $durum = 'tamamladi';
            } elseif ($izin) {
                $durum = 'izinli';
            } else {
                $durum = 'baslamadi';
            }

            $gecikme = $izin ? null : $Takip->gecikmeDakika($tarih, $ozet['ilk_basla'] ?? null, $mesai, $nowTs, $bugunMu);
            $sayac['toplam']++;
            $sayac[$durum]++;
            if ($gecikme !== null) {
                $sayac['gec']++;
            }
            $sayac['toplam_dakika'] += $ozet['dakika'] ?? 0;
            if ($departman !== '') {
                $departmanlar[$departman] = true;
            }

            $canliKonum = null;
            if (isset($canli[$pid])) {
                $guncelleme = strtotime((string) $canli[$pid]['son_guncelleme']);
                if (date('Y-m-d', $guncelleme) === $bugun) {
                    $canliKonum = [
                        'lat' => (float) $canli[$pid]['enlem'],
                        'lng' => (float) $canli[$pid]['boylam'],
                        'hassasiyet' => $canli[$pid]['hassasiyet'] !== null ? (float) $canli[$pid]['hassasiyet'] : null,
                        'zaman' => $canli[$pid]['son_guncelleme'],
                        'yas_dk' => (int) max(0, floor(($nowTs - $guncelleme) / 60)),
                    ];
                }
            }
            $aciklama = $aciklamalar[$pid][$tarih] ?? null;

            $items[] = [
                'id' => Security::encrypt((string) $pid),
                'adi_soyadi' => (string) $p['adi_soyadi'],
                'departman' => $departman,
                'gorev' => (string) ($p['gorev'] ?? ''),
                'telefon' => (string) ($p['cep_telefonu'] ?? ''),
                'foto' => takipFoto($p),
                'durum' => $durum,
                'izin_turu' => $izin,
                'mesai' => $mesai,
                'baslama' => takipSaat($ozet['ilk_basla'] ?? null),
                'bitis' => takipSaat($ozet['son_bitir'] ?? null),
                'dakika' => $ozet['dakika'] ?? 0,
                'gecikme_dk' => $gecikme,
                'otomatik_kapandi' => (bool) ($ozet['otomatik_kapandi'] ?? false),
                'hareket_sayisi' => $ozet['hareket_sayisi'] ?? 0,
                'gorev_konum' => $ozet ? $ozet['son_hareket'] : null,
                'canli_konum' => $canliKonum,
                'konum_bekleniyor' => isset($bekleyen[$pid]),
                'aciklama' => $aciklama['aciklama'] ?? '',
                'aciklama_yazan' => $aciklama['guncelleyen_ad'] ?? '',
            ];
        }

        $departmanListesi = array_keys($departmanlar);
        sort($departmanListesi, SORT_LOCALE_STRING);

        takipResponse([
            'success' => true,
            'tarih' => $tarih,
            'bugun' => $bugunMu,
            'sunucu_saati' => date('H:i'),
            'ozet' => $sayac,
            'departmanlar' => $departmanListesi,
            'items' => $items,
            'can_mesai' => $canMesai,
        ]);
    }

    if ($method === 'GET' && $action === 'personel') {
        $pid = takipPersonelId((string) ($_GET['id'] ?? ''));
        $personel = $pid > 0 ? $Takip->takipPersoneli($pid, $firmaId) : null;
        if (!$personel) {
            takipResponse(['success' => false, 'message' => 'Personel bulunamadı veya takip kapsamında değil.'], 404);
        }

        [$baslangic, $bitis] = takipAralik();
        $bugun = date('Y-m-d');
        $nowTs = time();
        $mesai = $Takip->mesaiSaati((string) ($personel['departman'] ?? ''));
        $hareketler = $Takip->hareketler($firmaId, $baslangic, $bitis, $pid);
        $ozetler = $Takip->gunlukOzetler($hareketler, $bugun, $nowTs)[$pid] ?? [];
        $izinler = $Takip->izinGunleri($firmaId, $baslangic, $bitis, $pid)[$pid] ?? [];
        $aciklamalar = $Takip->aciklamalar($firmaId, $baslangic, $bitis, $pid)[$pid] ?? [];

        $gunler = [];
        $toplam = ['calisilan_gun' => 0, 'dakika' => 0, 'zamaninda' => 0, 'gec' => 0, 'gecikme_dk' => 0, 'izinli' => 0, 'kayitsiz' => 0];
        $baslamalar = [];
        $bitisler = [];
        for ($cur = strtotime($bitis); $cur >= strtotime($baslangic); $cur = strtotime('-1 day', $cur)) {
            $tarih = date('Y-m-d', $cur);
            $ozet = $ozetler[$tarih] ?? null;
            $izin = $izinler[$tarih] ?? null;
            $gecikme = ($ozet && !$izin) ? $Takip->gecikmeDakika($tarih, $ozet['ilk_basla'], $mesai, $nowTs, false) : null;

            if ($ozet) {
                $toplam['calisilan_gun']++;
                $toplam['dakika'] += $ozet['dakika'];
                if (!$izin) {
                    $gecikme !== null ? $toplam['gec']++ : $toplam['zamaninda']++;
                    $toplam['gecikme_dk'] += $gecikme ?? 0;
                }
                if ($ozet['ilk_basla']) {
                    $baslamalar[] = strtotime('1970-01-01 ' . date('H:i:s', strtotime($ozet['ilk_basla'])) . ' UTC');
                }
                if ($ozet['son_bitir']) {
                    $bitisler[] = strtotime('1970-01-01 ' . date('H:i:s', strtotime($ozet['son_bitir'])) . ' UTC');
                }
            } elseif ($izin) {
                $toplam['izinli']++;
            } else {
                $toplam['kayitsiz']++;
            }

            $aciklama = $aciklamalar[$tarih] ?? null;
            $gunler[] = [
                'tarih' => $tarih,
                'gun_adi' => TAKIP_GUNLER[date('N', $cur)] ?? '',
                'hafta_sonu' => (int) date('N', $cur) >= 6,
                'durum' => $ozet ? ($ozet['devam_ediyor'] ? ($tarih === $bugun ? 'gorevde' : 'acik_kaldi') : ($gecikme !== null ? 'gec' : 'zamaninda')) : ($izin ? 'izinli' : 'kayit_yok'),
                'izin_turu' => $izin,
                'baslama' => takipSaat($ozet['ilk_basla'] ?? null),
                'bitis' => takipSaat($ozet['son_bitir'] ?? null),
                'dakika' => $ozet['dakika'] ?? 0,
                'gecikme_dk' => $gecikme,
                'otomatik_kapandi' => (bool) ($ozet['otomatik_kapandi'] ?? false),
                'ilk_konum' => $ozet['ilk_konum'] ?? null,
                'son_konum' => $ozet['son_konum'] ?? null,
                'aciklama' => $aciklama['aciklama'] ?? '',
                'aciklama_yazan' => $aciklama['guncelleyen_ad'] ?? '',
            ];
        }

        $girisler = $toplam['zamaninda'] + $toplam['gec'];
        $hareketListesi = [];
        foreach (array_reverse($hareketler) as $h) {
            $hareketListesi[] = [
                'tip' => $h['islem_tipi'],
                'tarih' => substr((string) $h['zaman'], 0, 10),
                'saat' => date('H:i', strtotime((string) $h['zaman'])),
                'lat' => (float) $h['konum_enlem'],
                'lng' => (float) $h['konum_boylam'],
                'hassasiyet' => $h['konum_hassasiyeti'] !== null ? (int) round((float) $h['konum_hassasiyeti']) : null,
                'cihaz' => mb_substr((string) ($h['cihaz_bilgisi'] ?? ''), 0, 120),
                'aciklama' => (string) ($h['aciklama'] ?? ''),
            ];
        }

        $gecmis = array_map(static fn(array $row): array => [
            'tarih' => $row['tarih'],
            'aciklama' => (string) $row['aciklama'],
            'yazan' => (string) ($row['guncelleyen_ad'] ?? ''),
            'guncelleme' => $row['guncellenme_tarihi'],
        ], $Takip->aciklamaGecmisi($pid));

        takipResponse([
            'success' => true,
            'personel' => [
                'id' => Security::encrypt((string) $pid),
                'adi_soyadi' => (string) $personel['adi_soyadi'],
                'departman' => (string) ($personel['departman'] ?? ''),
                'gorev' => (string) ($personel['gorev'] ?? ''),
                'telefon' => (string) ($personel['cep_telefonu'] ?? ''),
                'foto' => takipFoto($personel),
                'mesai' => $mesai,
            ],
            'aralik' => ['baslangic' => $baslangic, 'bitis' => $bitis],
            'ozet' => [
                'calisilan_gun' => $toplam['calisilan_gun'],
                'toplam_saat' => round($toplam['dakika'] / 60, 1),
                'ort_saat' => $toplam['calisilan_gun'] > 0 ? round($toplam['dakika'] / 60 / $toplam['calisilan_gun'], 1) : 0,
                'zamaninda' => $toplam['zamaninda'],
                'gec' => $toplam['gec'],
                'gecikme_dk' => $toplam['gecikme_dk'],
                'izinli' => $toplam['izinli'],
                'kayitsiz' => $toplam['kayitsiz'],
                'dakiklik' => $girisler > 0 ? (int) round($toplam['zamaninda'] / $girisler * 100) : null,
                'ort_baslama' => $baslamalar ? gmdate('H:i', (int) (array_sum($baslamalar) / count($baslamalar))) : null,
                'ort_bitis' => $bitisler ? gmdate('H:i', (int) (array_sum($bitisler) / count($bitisler))) : null,
            ],
            'gunler' => $gunler,
            'hareketler' => $hareketListesi,
            'aciklama_gecmisi' => $gecmis,
        ]);
    }

    if ($method === 'GET' && $action === 'analiz') {
        [$baslangic, $bitis] = takipAralik();
        $departman = trim((string) ($_GET['departman'] ?? ''));
        $bugun = date('Y-m-d');
        $nowTs = time();

        $personeller = $Takip->takipPersonelleri($firmaId, $departman !== '' ? $departman : null);
        $ozetler = $Takip->gunlukOzetler($Takip->hareketler($firmaId, $baslangic, $bitis), $bugun, $nowTs);
        $izinler = $Takip->izinGunleri($firmaId, $baslangic, $bitis);

        $tarihler = [];
        for ($cur = strtotime($baslangic); $cur <= strtotime($bitis); $cur = strtotime('+1 day', $cur)) {
            $tarih = date('Y-m-d', $cur);
            $tarihler[$tarih] = ['tarih' => $tarih, 'gun_adi' => TAKIP_GUNLER[date('N', $cur)] ?? '', 'dakika' => 0, 'kisi' => 0, 'gec' => 0, 'zamaninda' => 0];
        }
        $kovalar = ['07:30 öncesi' => 0, '07:30–08:00' => 0, '08:00–08:30' => 0, '08:30–09:00' => 0, '09:00 sonrası' => 0];
        $genel = ['dakika' => 0, 'zamaninda' => 0, 'gec' => 0, 'gecikme_dk' => 0, 'izin_gun' => 0];
        $aktifPersonel = 0;
        $performans = [];
        $departmanStat = [];

        foreach ($personeller as $p) {
            $pid = (int) $p['id'];
            $dep = (string) ($p['departman'] ?: 'Genel');
            $mesai = $Takip->mesaiSaati((string) ($p['departman'] ?? ''));
            $kisi = ['gun' => 0, 'dakika' => 0, 'zamaninda' => 0, 'gec' => 0, 'gecikme_dk' => 0, 'baslamalar' => []];

            foreach ($tarihler as $tarih => $_) {
                $izinli = isset($izinler[$pid][$tarih]);
                if ($izinli) {
                    $genel['izin_gun']++;
                }
                $ozet = $ozetler[$pid][$tarih] ?? null;
                if (!$ozet || !$ozet['ilk_basla']) {
                    continue;
                }
                $kisi['gun']++;
                $kisi['dakika'] += $ozet['dakika'];
                $tarihler[$tarih]['dakika'] += $ozet['dakika'];
                $tarihler[$tarih]['kisi']++;
                $saat = date('H:i', strtotime($ozet['ilk_basla']));
                $kisi['baslamalar'][] = strtotime('1970-01-01 ' . date('H:i:s', strtotime($ozet['ilk_basla'])) . ' UTC');
                $kova = $saat < '07:30' ? '07:30 öncesi' : ($saat <= '08:00' ? '07:30–08:00' : ($saat <= '08:30' ? '08:00–08:30' : ($saat <= '09:00' ? '08:30–09:00' : '09:00 sonrası')));
                $kovalar[$kova]++;
                if ($izinli) {
                    continue;
                }
                $gecikme = $Takip->gecikmeDakika($tarih, $ozet['ilk_basla'], $mesai, $nowTs, false);
                if ($gecikme !== null) {
                    $kisi['gec']++;
                    $kisi['gecikme_dk'] += $gecikme;
                    $tarihler[$tarih]['gec']++;
                } else {
                    $kisi['zamaninda']++;
                    $tarihler[$tarih]['zamaninda']++;
                }
            }

            $genel['dakika'] += $kisi['dakika'];
            $genel['zamaninda'] += $kisi['zamaninda'];
            $genel['gec'] += $kisi['gec'];
            $genel['gecikme_dk'] += $kisi['gecikme_dk'];
            if ($kisi['gun'] > 0) {
                $aktifPersonel++;
            }
            $giris = $kisi['zamaninda'] + $kisi['gec'];
            $performans[] = [
                'id' => Security::encrypt((string) $pid),
                'adi_soyadi' => (string) $p['adi_soyadi'],
                'departman' => $dep,
                'foto' => takipFoto($p),
                'gun' => $kisi['gun'],
                'toplam_saat' => round($kisi['dakika'] / 60, 1),
                'ort_saat' => $kisi['gun'] > 0 ? round($kisi['dakika'] / 60 / $kisi['gun'], 1) : 0,
                'zamaninda' => $kisi['zamaninda'],
                'gec' => $kisi['gec'],
                'gecikme_dk' => $kisi['gecikme_dk'],
                'dakiklik' => $giris > 0 ? (int) round($kisi['zamaninda'] / $giris * 100) : null,
                'ort_baslama' => $kisi['baslamalar'] ? gmdate('H:i', (int) (array_sum($kisi['baslamalar']) / count($kisi['baslamalar']))) : null,
            ];

            $departmanStat[$dep] ??= ['departman' => $dep, 'personel' => 0, 'aktif' => 0, 'dakika' => 0, 'zamaninda' => 0, 'gec' => 0, 'mesai' => $mesai];
            $departmanStat[$dep]['personel']++;
            $departmanStat[$dep]['aktif'] += $kisi['gun'] > 0 ? 1 : 0;
            $departmanStat[$dep]['dakika'] += $kisi['dakika'];
            $departmanStat[$dep]['zamaninda'] += $kisi['zamaninda'];
            $departmanStat[$dep]['gec'] += $kisi['gec'];
        }

        $departmanListesi = array_values(array_map(static function (array $d): array {
            $giris = $d['zamaninda'] + $d['gec'];
            return [
                'departman' => $d['departman'],
                'mesai' => $d['mesai'],
                'personel' => $d['personel'],
                'aktif' => $d['aktif'],
                'toplam_saat' => round($d['dakika'] / 60, 1),
                'ort_saat' => $d['aktif'] > 0 ? round($d['dakika'] / 60 / $d['aktif'], 1) : 0,
                'dakiklik' => $giris > 0 ? (int) round($d['zamaninda'] / $giris * 100) : null,
            ];
        }, $departmanStat));
        usort($departmanListesi, static fn($a, $b) => $b['toplam_saat'] <=> $a['toplam_saat']);
        usort($performans, static fn($a, $b) => $b['toplam_saat'] <=> $a['toplam_saat']);

        $girisGenel = $genel['zamaninda'] + $genel['gec'];
        $gunSayisi = max(1, count($tarihler));
        takipResponse([
            'success' => true,
            'aralik' => ['baslangic' => $baslangic, 'bitis' => $bitis],
            'kpi' => [
                'toplam_saat' => round($genel['dakika'] / 60, 1),
                'gunluk_ort_saat' => round($genel['dakika'] / 60 / $gunSayisi, 1),
                'kisi_basi_saat' => $aktifPersonel > 0 ? round($genel['dakika'] / 60 / $aktifPersonel, 1) : 0,
                'dakiklik' => $girisGenel > 0 ? (int) round($genel['zamaninda'] / $girisGenel * 100) : null,
                'zamaninda' => $genel['zamaninda'],
                'gec' => $genel['gec'],
                'gecikme_dk' => $genel['gecikme_dk'],
                'takip_personel' => count($personeller),
                'aktif_personel' => $aktifPersonel,
                'katilim' => count($personeller) > 0 ? (int) round($aktifPersonel / count($personeller) * 100) : 0,
                'izin_gun' => $genel['izin_gun'],
            ],
            'trend' => array_values(array_map(static fn(array $t): array => [
                'tarih' => $t['tarih'],
                'gun_adi' => $t['gun_adi'],
                'saat' => round($t['dakika'] / 60, 1),
                'kisi' => $t['kisi'],
                'gec' => $t['gec'],
                'zamaninda' => $t['zamaninda'],
            ], $tarihler)),
            'baslama_dagilimi' => array_map(static fn($k, $v) => ['etiket' => $k, 'adet' => $v], array_keys($kovalar), array_values($kovalar)),
            'departmanlar' => $departmanListesi,
            'personeller' => $performans,
        ]);
    }

    if ($method === 'GET' && $action === 'mesai') {
        if (!$canMesai) {
            takipResponse(['success' => false, 'message' => 'Departman mesai ayarları için yetkiniz bulunmuyor.'], 403);
        }
        takipResponse(['success' => true, 'items' => $Takip->departmanMesaiListesi($firmaId), 'varsayilan' => PersonelTakipModel::VARSAYILAN_MESAI]);
    }

    if ($method !== 'POST') {
        takipResponse(['success' => false, 'message' => 'Desteklenmeyen işlem.'], 405);
    }

    takipCsrf();
    $data = json_decode((string) file_get_contents('php://input'), true);
    $data = is_array($data) ? $data : [];

    if ($action === 'konum_iste') {
        $pid = takipPersonelId((string) ($data['id'] ?? ''));
        $personel = $pid > 0 ? $Takip->takipPersoneli($pid, $firmaId) : null;
        if (!$personel) {
            takipResponse(['success' => false, 'message' => 'Personel bulunamadı veya takip kapsamında değil.'], 404);
        }
        if (!$Takip->konumIste($pid)) {
            takipResponse(['success' => false, 'message' => 'Bu personel için zaten bekleyen bir konum isteği var.'], 409);
        }
        $SystemLog->logAction($userId, 'Personel Konum İsteği (Mobil)', "{$personel['adi_soyadi']} personelinden anlık konum istendi.", SystemLogModel::LEVEL_INFO);
        takipResponse(['success' => true, 'message' => 'Konum isteği personelin cihazına iletildi.']);
    }

    if ($action === 'konum_iste_toplu') {
        $bugun = date('Y-m-d');
        $ozetler = $Takip->gunlukOzetler($Takip->hareketler($firmaId, $bugun, $bugun), $bugun, time());
        $eklenen = 0;
        $bekleyen = 0;
        foreach ($Takip->takipPersonelleri($firmaId) as $p) {
            $pid = (int) $p['id'];
            if (empty($ozetler[$pid][$bugun]['devam_ediyor'])) {
                continue;
            }
            $Takip->konumIste($pid) ? $eklenen++ : $bekleyen++;
        }
        $SystemLog->logAction($userId, 'Toplu Personel Konum İsteği (Mobil)', "Görevdeki {$eklenen} personelden anlık konum istendi.", SystemLogModel::LEVEL_INFO);
        takipResponse([
            'success' => true,
            'eklenen' => $eklenen,
            'bekleyen' => $bekleyen,
            'message' => $eklenen > 0
                ? "{$eklenen} görevdeki personelden konum istendi." . ($bekleyen > 0 ? " {$bekleyen} personelin isteği zaten bekliyordu." : '')
                : ($bekleyen > 0 ? 'Görevdeki tüm personeller için istek zaten bekliyor.' : 'Şu an görevde personel bulunmuyor.'),
        ]);
    }

    if ($action === 'aciklama_kaydet') {
        $pid = takipPersonelId((string) ($data['id'] ?? ''));
        $personel = $pid > 0 ? $Takip->takipPersoneli($pid, $firmaId) : null;
        if (!$personel) {
            takipResponse(['success' => false, 'message' => 'Personel bulunamadı veya takip kapsamında değil.'], 404);
        }
        $tarih = takipTarih((string) ($data['tarih'] ?? ''), '');
        if ($tarih === '' || $tarih > date('Y-m-d')) {
            takipResponse(['success' => false, 'message' => 'Geçerli bir tarih seçin.'], 422);
        }
        $aciklama = trim((string) ($data['aciklama'] ?? ''));
        if (mb_strlen($aciklama) > 1000) {
            takipResponse(['success' => false, 'message' => 'Açıklama en fazla 1000 karakter olabilir.'], 422);
        }
        if (!$Takip->aciklamaKaydet($pid, $tarih, $aciklama, $userId)) {
            takipResponse(['success' => false, 'message' => 'Açıklama kaydedilemedi.'], 500);
        }
        $tarihTr = date('d.m.Y', strtotime($tarih));
        $SystemLog->logAction($userId, 'Personel Takip Açıklaması (Mobil)', "{$personel['adi_soyadi']} için {$tarihTr} tarihli gecikme/devam açıklaması kaydedildi.", SystemLogModel::LEVEL_INFO);
        takipResponse(['success' => true, 'message' => 'Açıklama kaydedildi.']);
    }

    if ($action === 'mesai_kaydet') {
        if (!$canMesai) {
            takipResponse(['success' => false, 'message' => 'Departman mesai ayarları için yetkiniz bulunmuyor.'], 403);
        }
        $ayarlar = [];
        foreach ((array) ($data['items'] ?? []) as $row) {
            if (is_array($row) && isset($row['departman'], $row['mesai_saati'])) {
                $ayarlar[(string) $row['departman']] = (string) $row['mesai_saati'];
            }
        }
        if (!$ayarlar || !$Takip->departmanMesaileriniKaydet($ayarlar)) {
            takipResponse(['success' => false, 'message' => 'Mesai ayarları kaydedilemedi.'], 422);
        }
        $SystemLog->logAction($userId, 'Personel Takip Mesai Ayarı (Mobil)', 'Departmanlara göre işe başlama saatleri güncellendi.', SystemLogModel::LEVEL_IMPORTANT);
        takipResponse(['success' => true, 'message' => 'Mesai saatleri kaydedildi.']);
    }

    takipResponse(['success' => false, 'message' => 'Geçersiz işlem.'], 400);
} catch (Throwable $e) {
    error_log('[mobile personel-takip] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    takipResponse(['success' => false, 'message' => 'İşlem sırasında bir hata oluştu.'], 500);
}
