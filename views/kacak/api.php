<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helper\Date;
use App\Helper\Helper;
use App\Helper\Security;
use App\Model\KacakKontrolModel;
use App\Model\KacakSicilEksikModel;
use App\Model\PersonelModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use App\Service\KacakTutanakAnalizService;
use App\Service\VideoUploadService;

header('Content-Type: application/json; charset=utf-8');

// İstek gövdesi post_max_size sınırını aşarsa PHP $_POST ve $_FILES dizilerini
// tamamen boşaltır; bu durumda istek "action yok" gibi görünüp sessizce düşer.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && empty($_POST)
    && empty($_FILES)
    && !empty($_SERVER['CONTENT_LENGTH'])) {
    error_log(sprintf(
        'Kaçak isteği post_max_size sınırında düştü: content_length=%s post_max_size=%s upload_max_filesize=%s',
        $_SERVER['CONTENT_LENGTH'],
        ini_get('post_max_size'),
        ini_get('upload_max_filesize')
    ));
    echo json_encode([
        'status' => 'error',
        'message' => 'Gönderdiğiniz dosyalar sunucunun izin verdiği toplam boyutu (post_max_size: '
            . ini_get('post_max_size') . ') aşıyor. Daha az veya daha küçük dosya ile tekrar deneyin.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$userPersonelId = (int) ($_SESSION['personel_id'] ?? 0);

if ($userId <= 0 || empty($_SESSION['firma_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum süresi doldu.']);
    exit;
}

if (!empty($_POST['mobile_token'])) {
    $csrf = (string) ($_POST['_mobile_csrf'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Güvenlik doğrulaması başarısız. Sayfayı yenileyin.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_POST['id'] = (int) Security::decrypt((string) $_POST['mobile_token']);
}

if (!kacakIzin('kacak_islemleri') && !kacakIzin('kacak/list') && !kacakSuperAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
    exit;
}

// Salt okunur isteklerde oturum kilidini bırak; aksi halde aynı sekmeden gelen
// paralel AJAX çağrıları PHP session dosya kilidi yüzünden sıraya girer.
// Gate, AuthController::user() üzerinden session_start() çağırdığı için tüm
// yetki sonuçları kilit bırakılmadan önce önbelleğe alınır.
$saltOkunurActionlar = [
    'list',
    'get-record',
    'pending-count',
    'dashboard',
    'cancel-candidates',
    'get-photos',
    'archive-preview',
    'gunluk-rapor',
    'haftalik-rapor',
    'teslim-alma-listesi',
    'sicil-list',
    'sicil-counts',
    'sicil-detay',
    'sicil-tutanak-ara',
];

if (in_array($action, $saltOkunurActionlar, true)) {
    kacakSuperAdmin();
    foreach (['kacak_onay', 'kacak_duzenle', 'kacak_iptal', 'kacak_iptal_ekle', 'kacak_arsiv', 'kacak_sicil_bildir', 'kacak_sicil_yanitla'] as $izin) {
        kacakIzin($izin);
    }
    session_write_close();
}

$Kacak = new KacakKontrolModel();
$Sicil = new KacakSicilEksikModel();
$Log = new SystemLogModel();

function kacakYanit(bool $ok, string $mesaj = '', array $ek = []): void
{
    echo json_encode(array_merge(['status' => $ok ? 'success' : 'error', 'message' => $mesaj], $ek), JSON_UNESCAPED_UNICODE);
    exit;
}

function kacakMedyaUyariMetni(?array $uyarilar): string
{
    if (empty($uyarilar)) {
        return '';
    }
    return ' Ancak bazı dosyalar yüklenemedi: ' . implode(' | ', $uyarilar);
}

function kacakSuperAdmin(): bool
{
    static $deger = null;
    if ($deger === null) {
        $deger = Gate::isSuperAdmin();
    }
    return $deger;
}

function kacakIzin(string $izin): bool
{
    static $onbellek = [];
    if (!array_key_exists($izin, $onbellek)) {
        $onbellek[$izin] = Gate::allows($izin);
    }
    return $onbellek[$izin];
}

function kacakYetkiKontrol(string $izin): void
{
    if (!kacakIzin($izin) && !kacakSuperAdmin()) {
        kacakYanit(false, 'Bu işlem için yetkiniz bulunmuyor.');
    }
}

/**
 * Sicil oluşmayanlar sekmesini görüntüleme yetkisi: bildirme veya yanıtlama.
 */
function sicilGorusYetkiKontrol(): void
{
    if (!kacakIzin('kacak_sicil_bildir') && !kacakIzin('kacak_sicil_yanitla') && !kacakSuperAdmin()) {
        kacakYanit(false, 'Bu işlem için yetkiniz bulunmuyor.');
    }
}

/**
 * Alt sekme adını geçerli durum listesine çevirir.
 */
function sicilDurumFiltresi(string $sekme): array
{
    switch ($sekme) {
        case 'beklemede':
            return ['beklemede'];
        case 'yanitlandi':
            return ['yanitlandi'];
        case 'arsiv':
            return ['cozuldu', 'iptal'];
        default:
            return [];
    }
}

function kacakTarih($deger, string $varsayilan = ''): string
{
    $deger = trim((string) $deger);
    if ($deger === '') {
        return $varsayilan !== '' ? $varsayilan : date('Y-m-d');
    }
    $donusen = Date::convertExcelDate($deger, 'Y-m-d');
    if (!empty($donusen)) {
        return $donusen;
    }
    $ts = strtotime($deger);
    return $ts !== false ? date('Y-m-d', $ts) : ($varsayilan !== '' ? $varsayilan : date('Y-m-d'));
}

const KACAK_EXCEL_MAX_BYTE = 10485760;

const KACAK_EXCEL_MAX_SATIR = 5000;

/**
 * Excel başlık satırını sistem alan adlarına eşler. Hem KASKİ takip dosyasının
 * hem de modülün kendi dışa aktarımının başlıkları desteklenir.
 */
function kacakExcelBasliklariCoz(array $satir): array
{
    $esler = [
        'tarih' => ['tarih', 'islem tarihi', 'tutanak tarihi'],
        'tutanak_no' => ['tutanak no', 'tutanakno', 'tutanak numarasi', 'tutanak'],
        'abone_adi' => ['isim soyisim', 'abone adi', 'ad soyad', 'adi soyadi', 'abone', 'abone ismi'],
        'sayac_no' => ['sayac no', 'sayacno', 'sayac numarasi', 'sayac'],
        'tur' => ['tur', 'turu', 'islem turu', 'tutanak turu'],
        'endeks' => ['endeks', 'endeks degeri'],
        'memur' => ['islem yapan memur', 'islem yapan', 'memur', 'ekip', 'personel', 'ekip adi'],
        'ilce' => ['ilce', 'bolge'],
        'tutar' => ['tutar', 'tahakkuk tutari', 'ceza tutari'],
        'kontrol_edildi' => ['kontrol edildi', 'kontrol', 'kontrol durumu'],
        'usulsuz' => ['usulsuz', 'usulsuz mu'],
        'teslim' => ['teslim durumu', 'teslim', 'teslim alindi'],
        'sayi' => ['sayi', 'adet'],
        'aciklama' => ['aciklama', 'not', 'notlar'],
    ];

    $harita = [];
    foreach ($satir as $sutun => $baslik) {
        $anahtar = KacakKontrolModel::adAnahtari((string) $baslik);
        if ($anahtar === '') {
            continue;
        }
        foreach ($esler as $alan => $adaylar) {
            if (isset($harita[$alan])) {
                continue;
            }
            if (in_array($anahtar, $adaylar, true)) {
                $harita[$alan] = $sutun;
                break;
            }
        }
    }

    return $harita;
}

/**
 * Excel seri numarası veya metin tarihini Y-m-d biçimine çevirir.
 */
function kacakExcelTarih($deger): ?string
{
    $deger = trim((string) $deger);
    if ($deger === '') {
        return null;
    }

    if (is_numeric($deger)) {
        try {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $deger)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    $deger = preg_replace('/\s+/u', ' ', $deger);
    foreach (['d.m.Y H:i:s', 'd.m.Y H:i', 'd.m.Y', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s', 'Y-m-d'] as $bicim) {
        $tarih = DateTime::createFromFormat($bicim, $deger);
        if ($tarih instanceof DateTime) {
            return $tarih->format('Y-m-d');
        }
    }

    $ts = strtotime($deger);

    return $ts !== false ? date('Y-m-d', $ts) : null;
}

/**
 * "BÜNYAMİN ATEŞ,SAMED ARSLAN" biçimindeki memur alanını personel id listesine çevirir.
 * Eşleşmeyen isimler ikinci sırada döner; çağıran satırı atlar.
 */
function kacakExcelPersonelCoz(string $ham, array $personelHaritasi): array
{
    $ham = trim($ham);
    if ($ham === '') {
        return [[], []];
    }

    $tamAnahtar = KacakKontrolModel::adAnahtari($ham);
    if ($tamAnahtar !== '' && isset($personelHaritasi[$tamAnahtar])) {
        return [[$personelHaritasi[$tamAnahtar]], []];
    }

    $parcalar = preg_split('/[,;\/&+]|\sve\s/u', $ham);
    $idler = [];
    $bulunamayan = [];

    foreach ((array) $parcalar as $parca) {
        $anahtar = KacakKontrolModel::adAnahtari((string) $parca);
        if ($anahtar === '') {
            continue;
        }
        if (isset($personelHaritasi[$anahtar])) {
            $idler[] = $personelHaritasi[$anahtar];
        } else {
            $bulunamayan[] = trim((string) $parca);
        }
    }

    return [array_values(array_unique($idler)), $bulunamayan];
}

function kacakExcelTur(string $ham): ?string
{
    $anahtar = KacakKontrolModel::adAnahtari($ham);
    if ($anahtar === '') {
        return null;
    }

    foreach (KacakKontrolModel::TURLER as $tur) {
        if (KacakKontrolModel::adAnahtari($tur) === $anahtar) {
            return $tur;
        }
    }

    if (strpos($anahtar, 'abonesiz') !== false) {
        return 'Abonesiz';
    }
    if (strpos($anahtar, 'usulsuz') !== false) {
        return 'Usülsüz';
    }
    if (strpos($anahtar, 'kacak') !== false) {
        return 'Kaçak';
    }

    return null;
}

/**
 * "4.500,00 TL" / "4500.50" gibi tutar metinlerini float'a çevirir.
 */
function kacakExcelTutar(string $ham): ?float
{
    $ham = trim($ham);
    if ($ham === '') {
        return null;
    }

    $temiz = preg_replace('/[^0-9,.\-]/u', '', $ham);
    if ($temiz === '' || $temiz === '-') {
        return null;
    }

    $sonNokta = strrpos($temiz, '.');
    $sonVirgul = strrpos($temiz, ',');

    if ($sonVirgul !== false && ($sonNokta === false || $sonVirgul > $sonNokta)) {
        $temiz = str_replace('.', '', $temiz);
        $temiz = str_replace(',', '.', $temiz);
    } else {
        $temiz = str_replace(',', '', $temiz);
    }

    return is_numeric($temiz) ? (float) $temiz : null;
}

function kacakExcelEvetMi(string $ham): bool
{
    $anahtar = KacakKontrolModel::adAnahtari($ham);
    if ($anahtar === '') {
        return false;
    }

    $olumsuz = ['hayir', 'yok', '0', 'edilmedi', 'alinmadi', 'bekliyor', 'teslim edilmedi', 'kontrol edilmedi'];
    if (in_array($anahtar, $olumsuz, true)) {
        return false;
    }

    $olumlu = ['evet', 'var', 'x', '1', 'ok', 'e', 'edildi', 'alindi', 'teslim edildi', 'teslim alindi', 'kontrol edildi', 'tamam', 'dogru', 'true'];

    return in_array($anahtar, $olumlu, true);
}

try {
    switch ($action) {

        case 'get-unique-values':
            $column = $_POST['column'] ?? $_GET['column'] ?? '';
            $vals = $Kacak->getUniqueValues($column);
            echo json_encode(['status' => 'success', 'data' => $vals]);
            exit;

        // =====================================================
        // LİSTELEME
        // =====================================================
        case 'list':
            $dataTableRequest = isset($_GET['draw']);
            $columnNames = ['tarih', 'tutanak_no', 'abone_adi', 'ilce', 'tur', 'sayac_no', 'sayi', 'ekip_adi', 'kaynak', '', 'durum', ''];
            $filters = [
                'tarih_baslangic' => kacakTarih($_GET['start_date'] ?? '', date('Y-m-01')),
                'tarih_bitis' => kacakTarih($_GET['end_date'] ?? '', date('Y-m-d')),
                'ilce' => $_GET['ilce'] ?? '',
                'tur' => $_GET['tur'] ?? '',
                'durum' => $_GET['durum'] ?? '',
                'onay_durumu' => $_GET['onay_durumu'] ?? '',
                'kaynak' => $_GET['kaynak'] ?? '',
                'personel_id' => $_GET['personel_id'] ?? '',
                'arama' => $_GET['arama'] ?? '',
            ];

            if ($dataTableRequest) {
                $globalSearch = trim((string) ($_GET['search']['value'] ?? ''));
                if ($globalSearch !== '') {
                    $filters['arama'] = trim($filters['arama'] . ' ' . $globalSearch);
                }
                foreach (($_GET['columns'] ?? []) as $index => $column) {
                    $value = trim((string) ($column['search']['value'] ?? ''));
                    if ($value !== '' && !empty($columnNames[$index])) {
                        $filters['kolon_aramalari'][$columnNames[$index]] = $value;
                    }
                }
            }

            // Personel tarafında (süper admin veya onay yetkilisi değilse) sadece kendi ekibinin bildirimlerini görebilir
            if ($userPersonelId > 0 && !kacakSuperAdmin() && !kacakIzin('kacak_onay')) {
                $filters['personel_id'] = $userPersonelId;
            }

            $limit = $dataTableRequest ? max(1, min(100, (int) ($_GET['length'] ?? 25))) : 0;
            $offset = $dataTableRequest ? max(0, (int) ($_GET['start'] ?? 0)) : 0;
            $orderIndex = (int) ($_GET['order'][0]['column'] ?? 0);
            $orderColumn = $columnNames[$orderIndex] ?? 'tarih';
            $orderDirection = (string) ($_GET['order'][0]['dir'] ?? 'desc');
            $kayitlar = $Kacak->getRecords($filters, $limit, $offset, $orderColumn, $orderDirection);
            foreach ($kayitlar as &$k) {
                $k['tarih_formatted'] = Date::dmY($k['tarih']);
                $k['olusturma_tarihi_formatted'] = !empty($k['olusturma_tarihi']) && $k['olusturma_tarihi'] !== '0000-00-00 00:00:00'
                    ? date('d.m.Y H:i', strtotime($k['olusturma_tarihi']))
                    : '-';
                $k['foto_sayisi'] = (int) $k['foto_sayisi'];
                $k['beklenen_foto_sayisi'] = (int) ($k['beklenen_foto_sayisi'] ?? 0);
                $k['gecikmeli_foto_sayisi'] = (int) ($k['gecikmeli_foto_sayisi'] ?? 0);
                if (kacakIzin('kacak_iptal_ekle') || kacakSuperAdmin()) {
                    $k['iptal_token'] = Security::encrypt((string) $k['id']);
                }
            }
            unset($k);

            if ($dataTableRequest) {
                $filteredCount = $Kacak->countRecords($filters);
                $totalFilters = $filters;
                unset($totalFilters['arama'], $totalFilters['kolon_aramalari']);
                kacakYanit(true, '', [
                    'draw' => (int) $_GET['draw'],
                    'recordsTotal' => $Kacak->countRecords($totalFilters),
                    'recordsFiltered' => $filteredCount,
                    'data' => $kayitlar,
                    'ozet' => $Kacak->getOzet($filters['tarih_baslangic'], $filters['tarih_bitis'], (int) ($filters['personel_id'] ?? 0)),
                ]);
            }

            kacakYanit(true, '', [
                'data' => $kayitlar,
                'ozet' => $Kacak->getOzet($filters['tarih_baslangic'], $filters['tarih_bitis'], (int) ($filters['personel_id'] ?? 0)),
            ]);
            break;

        case 'get-record':
            $kayit = $Kacak->getRecord((int) ($_GET['id'] ?? 0));
            if (!$kayit) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            // Sicil bildirimi açacak kurum kullanıcısının tutanak detayını görmesi gerekir.
            if ($userPersonelId > 0 && !kacakSuperAdmin() && !kacakIzin('kacak_onay') && !kacakIzin('kacak_sicil_bildir') && !kacakIzin('kacak_iptal_ekle')) {
                $isEkip = ($kayit['bildiren_personel_id'] == $userPersonelId) || in_array($userPersonelId, $kayit['personel_ids_array'] ?? [], true);
                if (!$isEkip) {
                    kacakYanit(false, 'Bu kaydı görüntüleme yetkiniz bulunmuyor.');
                }
            }
            $kayit['tarih_formatted'] = Date::dmY($kayit['tarih']);
            kacakYanit(true, '', ['data' => $kayit]);
            break;

        case 'pending-count':
            kacakYanit(true, '', ['count' => $Kacak->getPendingCount()]);
            break;

        case 'dashboard':
            $bas = kacakTarih($_GET['start_date'] ?? '', date('Y-m-01'));
            $bit = kacakTarih($_GET['end_date'] ?? '', date('Y-m-d'));
            $dashboardPersonelId = $userPersonelId > 0 && !kacakSuperAdmin() && !kacakIzin('kacak_onay')
                ? $userPersonelId : 0;
            kacakYanit(true, '', ['data' => $Kacak->getDashboard($bas, $bit, $dashboardPersonelId)]);
            break;

        case 'cancel-candidates':
            kacakYetkiKontrol('kacak_iptal_ekle');
            $sonuclar = [];
            foreach ($Kacak->getCancellationCandidates((string) ($_GET['q'] ?? '')) as $kayit) {
                $sonuclar[] = [
                    'id' => Security::encrypt((string) $kayit['id']),
                    'text' => ($kayit['tutanak_no'] ?: 'Tutanak no yok') . ' — ' . ($kayit['abone_adi'] ?: '-') . ' (' . Date::dmY($kayit['tarih']) . ')',
                ];
            }
            kacakYanit(true, '', ['results' => $sonuclar]);
            break;

        // =====================================================
        // KAYIT / GÜNCELLEME / SİLME
        // =====================================================
        case 'save':
            kacakYetkiKontrol('kacak_duzenle');

            $id = (int) ($_POST['id'] ?? 0);
            $tarih = kacakTarih($_POST['tarih'] ?? '');
            $personelIds = $_POST['kacak_personel_ids'] ?? [];

            if (empty($personelIds)) {
                kacakYanit(false, 'En az bir personel seçmelisiniz.');
            }

            $ilceArr = (array) ($_POST['ilce'] ?? []);
            $turArr = (array) ($_POST['tur'] ?? []);
            $tutanakNoArr = (array) ($_POST['tutanak_no'] ?? []);
            $aboneAdiArr = (array) ($_POST['abone_adi'] ?? []);
            $sayacNoArr = (array) ($_POST['sayac_no'] ?? []);
            $endeksArr = (array) ($_POST['endeks'] ?? []);
            $sayiArr = (array) ($_POST['sayi'] ?? []);
            $aciklamaArr = (array) ($_POST['aciklama'] ?? []);

            if ($id > 0) {
                $duplicate = $Kacak->findDuplicateRecord([
                    'tutanak_no' => $tutanakNoArr[0] ?? null,
                    'sayac_no' => $sayacNoArr[0] ?? null,
                    'tarih' => $tarih,
                ], $id);
                if ($duplicate) {
                    $rec = $duplicate['record'];
                    $tarihFmt = !empty($rec['tarih']) ? date('d.m.Y', strtotime($rec['tarih'])) : '';
                    if ($duplicate['type'] === 'tutanak_no') {
                        $msg = "Mükerrer Kayıt: '" . htmlspecialchars($rec['tutanak_no'], ENT_QUOTES, 'UTF-8') . "' numaralı tutanak daha önce sisteme girilmiş. ({$tarihFmt})";
                    } else {
                        $msg = "Mükerrer Kayıt: '" . htmlspecialchars($rec['sayac_no'], ENT_QUOTES, 'UTF-8') . "' numaralı sayaç için {$tarihFmt} tarihinde zaten kayıt mevcuttur.";
                    }
                    kacakYanit(false, $msg);
                }

                $ok = $Kacak->updateRecord($id, [
                    'tarih' => $tarih,
                    'personel_ids' => $personelIds,
                    'ilce' => $ilceArr[0] ?? '',
                    'tur' => $turArr[0] ?? 'Kaçak',
                    'tutanak_no' => $tutanakNoArr[0] ?? null,
                    'abone_adi' => $aboneAdiArr[0] ?? null,
                    'sayac_no' => $sayacNoArr[0] ?? null,
                    'endeks' => $endeksArr[0] ?? null,
                    'sayi' => $sayiArr[0] ?? 1,
                    'aciklama' => $aciklamaArr[0] ?? null,
                ]);

                if (!$ok) {
                    kacakYanit(false, 'Kayıt güncellenemedi.');
                }

                kacakFotoYukle($Kacak, $id, $userId, $medyaUyarilari);

                $Log->logAction($userId, 'Kaçak Kaydı Güncellendi', "ID: $id, Tarih: $tarih", SystemLogModel::LEVEL_IMPORTANT);
                kacakYanit(
                    true,
                    'Kayıt güncellendi.' . kacakMedyaUyariMetni($medyaUyarilari),
                    ['id' => $id, 'medya_uyarilari' => $medyaUyarilari]
                );
            }

            if (empty($ilceArr)) {
                kacakYanit(false, 'En az bir giriş satırı eklemelisiniz.');
            }

            $eklenen = [];
            $db = $Kacak->getDb();
            $db->beginTransaction();
            try {
                foreach ($ilceArr as $i => $ilce) {
                    if (trim((string) $ilce) === '') {
                        continue;
                    }
                    $tNo = $tutanakNoArr[$i] ?? null;
                    $sNo = $sayacNoArr[$i] ?? null;
                    $duplicate = $Kacak->findDuplicateRecord([
                        'tutanak_no' => $tNo,
                        'sayac_no' => $sNo,
                        'tarih' => $tarih,
                    ]);
                    if ($duplicate) {
                        $rec = $duplicate['record'];
                        $tarihFmt = !empty($rec['tarih']) ? date('d.m.Y', strtotime($rec['tarih'])) : '';
                        if ($duplicate['type'] === 'tutanak_no') {
                            $msg = "Mükerrer Kayıt: '" . htmlspecialchars($rec['tutanak_no'], ENT_QUOTES, 'UTF-8') . "' numaralı tutanak daha önce sisteme girilmiş. ({$tarihFmt})";
                        } else {
                            $msg = "Mükerrer Kayıt: '" . htmlspecialchars($rec['sayac_no'], ENT_QUOTES, 'UTF-8') . "' numaralı sayaç için {$tarihFmt} tarihinde zaten kayıt mevcuttur.";
                        }
                        $db->rollBack();
                        kacakYanit(false, $msg);
                    }
                    $yeniId = $Kacak->createRecord([
                        'tarih' => $tarih,
                        'personel_ids' => $personelIds,
                        'ilce' => $ilce,
                        'tur' => $turArr[$i] ?? 'Kaçak',
                        'tutanak_no' => $tutanakNoArr[$i] ?? null,
                        'abone_adi' => $aboneAdiArr[$i] ?? null,
                        'sayac_no' => $sayacNoArr[$i] ?? null,
                        'endeks' => $endeksArr[$i] ?? null,
                        'sayi' => $sayiArr[$i] ?? 1,
                        'aciklama' => $aciklamaArr[$i] ?? null,
                        'kaynak' => 'masaustu',
                        'onay_durumu' => 'onaylandi',
                        'onaylayan_id' => $userId,
                    ]);
                    $eklenen[] = $yeniId;
                }
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            if (empty($eklenen)) {
                kacakYanit(false, 'Geçerli satır bulunamadı, kayıt eklenmedi.');
            }

            kacakFotoYukle($Kacak, $eklenen[0], $userId, $medyaUyarilari);

            $Log->logAction($userId, 'Kaçak Kaydı Eklendi', count($eklenen) . ' adet kaçak kaydı eklendi. Tarih: ' . $tarih, SystemLogModel::LEVEL_IMPORTANT);
            kacakYanit(
                true,
                count($eklenen) . ' kayıt eklendi.' . kacakMedyaUyariMetni($medyaUyarilari),
                ['ids' => $eklenen, 'medya_uyarilari' => $medyaUyarilari]
            );
            break;

        // =====================================================
        // EXCEL'DEN TOPLU KAYIT YÜKLEME
        // =====================================================
        case 'excel-yukle':
            kacakYetkiKontrol('kacak_duzenle');

            if (empty($_FILES['excelFile']) || ($_FILES['excelFile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                kacakYanit(false, 'Geçerli bir Excel dosyası seçmelisiniz.');
            }

            $uzanti = strtolower(pathinfo((string) $_FILES['excelFile']['name'], PATHINFO_EXTENSION));
            if (!in_array($uzanti, ['xlsx', 'xls', 'csv'], true)) {
                kacakYanit(false, 'Yalnızca .xlsx, .xls veya .csv uzantılı dosyalar yüklenebilir.');
            }

            if ((int) $_FILES['excelFile']['size'] > KACAK_EXCEL_MAX_BYTE) {
                kacakYanit(false, 'Dosya boyutu ' . (KACAK_EXCEL_MAX_BYTE / 1048576) . ' MB sınırını aşıyor.');
            }

            @set_time_limit(300);

            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['excelFile']['tmp_name']);
                $satirlar = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            } catch (\Throwable $e) {
                error_log('Kaçak Excel okuma hatası: ' . $e->getMessage());
                kacakYanit(false, 'Excel dosyası okunamadı. Dosyanın bozuk olmadığından emin olun.');
            }

            $baslikSatiri = null;
            $baslikIndeks = -1;
            foreach ($satirlar as $i => $satir) {
                if ($i > 20) {
                    break;
                }
                $harita = kacakExcelBasliklariCoz((array) $satir);
                if (isset($harita['tutanak_no'])) {
                    $baslikSatiri = $harita;
                    $baslikIndeks = $i;
                    break;
                }
            }

            if ($baslikSatiri === null) {
                kacakYanit(false, 'Başlık satırı bulunamadı. Dosyada "TUTANAK NO" sütunu bulunmalıdır. Örnek şablonu indirip kullanabilirsiniz.');
            }

            $eksikBaslik = [];
            foreach (['tarih' => 'TARİH', 'ilce' => 'İLÇE', 'memur' => 'İŞLEM YAPAN MEMUR'] as $anahtar => $etiket) {
                if (!isset($baslikSatiri[$anahtar])) {
                    $eksikBaslik[] = $etiket;
                }
            }
            if (!empty($eksikBaslik)) {
                kacakYanit(false, 'Şu sütunlar dosyada bulunamadı: ' . implode(', ', $eksikBaslik) . '. Örnek şablonu indirip kullanabilirsiniz.');
            }

            $veriSatirlari = array_slice($satirlar, $baslikIndeks + 1, null, true);
            unset($satirlar);

            if (count($veriSatirlari) > KACAK_EXCEL_MAX_SATIR) {
                kacakYanit(false, 'Dosyada ' . count($veriSatirlari) . ' satır var. Tek seferde en fazla ' . KACAK_EXCEL_MAX_SATIR . ' satır yüklenebilir; dosyayı bölerek deneyin.');
            }

            $mevcutTutanaklar = $Kacak->getTutanakNoHaritasi();
            $personelHaritasi = $Kacak->getPersonelAdHaritasi();
            $gecerliIlceler = [];
            foreach (KacakKontrolModel::ILCELER as $gecerliIlce) {
                $gecerliIlceler[KacakKontrolModel::adAnahtari($gecerliIlce)] = $gecerliIlce;
            }

            $hazir = [];
            $atlanan = [];
            $dosyaIciTutanaklar = [];

            foreach ($veriSatirlari as $i => $satir) {
                $satir = (array) $satir;
                $satirNo = $i + 1;

                $doluMu = false;
                foreach ($satir as $hucre) {
                    if (trim((string) $hucre) !== '') {
                        $doluMu = true;
                        break;
                    }
                }
                if (!$doluMu) {
                    continue;
                }

                $al = function (string $anahtar) use ($satir, $baslikSatiri) {
                    if (!isset($baslikSatiri[$anahtar])) {
                        return '';
                    }
                    return trim((string) ($satir[$baslikSatiri[$anahtar]] ?? ''));
                };

                $tutanakNo = $al('tutanak_no');
                $tutanakAnahtar = KacakKontrolModel::tutanakAnahtari($tutanakNo);

                if ($tutanakAnahtar === '') {
                    $atlanan[] = ['satir' => $satirNo, 'tutanak_no' => '-', 'neden' => 'Tutanak numarası boş.'];
                    continue;
                }

                if (isset($mevcutTutanaklar[$tutanakAnahtar])) {
                    $mevcut = $mevcutTutanaklar[$tutanakAnahtar];
                    $mevcutTarih = !empty($mevcut['tarih']) ? date('d.m.Y', strtotime($mevcut['tarih'])) : '-';
                    $mevcutEkip = trim((string) ($mevcut['ekip_adi'] ?? ''));
                    $atlanan[] = [
                        'satir' => $satirNo,
                        'tutanak_no' => $tutanakNo,
                        'neden' => 'Mükerrer: bu tutanak sistemde zaten kayıtlı (' . $mevcutTarih . ($mevcutEkip !== '' ? ' / ' . $mevcutEkip : '') . ').',
                    ];
                    continue;
                }

                if (isset($dosyaIciTutanaklar[$tutanakAnahtar])) {
                    $atlanan[] = [
                        'satir' => $satirNo,
                        'tutanak_no' => $tutanakNo,
                        'neden' => 'Mükerrer: aynı tutanak numarası dosyanın ' . $dosyaIciTutanaklar[$tutanakAnahtar] . '. satırında da var.',
                    ];
                    continue;
                }

                $tarih = kacakExcelTarih($al('tarih'));
                if ($tarih === null) {
                    $atlanan[] = ['satir' => $satirNo, 'tutanak_no' => $tutanakNo, 'neden' => 'Tarih boş ya da okunamadı: "' . $al('tarih') . '"'];
                    continue;
                }

                $ilceHam = $al('ilce');
                $ilceAnahtar = KacakKontrolModel::adAnahtari($ilceHam);
                if ($ilceAnahtar === '') {
                    $atlanan[] = ['satir' => $satirNo, 'tutanak_no' => $tutanakNo, 'neden' => 'İlçe boş.'];
                    continue;
                }
                if (!isset($gecerliIlceler[$ilceAnahtar])) {
                    $atlanan[] = ['satir' => $satirNo, 'tutanak_no' => $tutanakNo, 'neden' => '"' . $ilceHam . '" geçerli bir ilçe değil.'];
                    continue;
                }
                $ilce = $gecerliIlceler[$ilceAnahtar];

                [$personelIds, $bulunamayanlar] = kacakExcelPersonelCoz($al('memur'), $personelHaritasi);
                if (!empty($bulunamayanlar)) {
                    $atlanan[] = [
                        'satir' => $satirNo,
                        'tutanak_no' => $tutanakNo,
                        'neden' => 'Personel bulunamadı: ' . implode(', ', $bulunamayanlar),
                    ];
                    continue;
                }
                if (empty($personelIds)) {
                    $atlanan[] = ['satir' => $satirNo, 'tutanak_no' => $tutanakNo, 'neden' => 'İşlem yapan memur boş.'];
                    continue;
                }

                $usulsuzHam = $al('usulsuz');
                $tur = kacakExcelTur($al('tur'));
                if ($tur === null) {
                    $usulsuzAnahtar = KacakKontrolModel::adAnahtari($usulsuzHam);
                    $usulsuzVar = $usulsuzAnahtar !== '' && !in_array($usulsuzAnahtar, ['hayir', 'yok', '0', 'degil'], true);
                    $tur = $usulsuzVar ? 'Usülsüz' : 'Kaçak';
                }

                $sayiHam = $al('sayi');
                $tutarHam = $al('tutar');

                $dosyaIciTutanaklar[$tutanakAnahtar] = $satirNo;
                $hazir[] = [
                    'satir' => $satirNo,
                    'veri' => [
                        'tarih' => $tarih,
                        'personel_ids' => $personelIds,
                        'ilce' => $ilce,
                        'tur' => $tur,
                        'tutanak_no' => mb_substr($tutanakNo, 0, 100, 'UTF-8'),
                        'abone_adi' => mb_substr($al('abone_adi'), 0, 255, 'UTF-8') ?: null,
                        'sayac_no' => mb_substr($al('sayac_no'), 0, 100, 'UTF-8') ?: null,
                        'endeks' => mb_substr($al('endeks'), 0, 50, 'UTF-8') ?: null,
                        'sayi' => $sayiHam !== '' ? max(1, (int) $sayiHam) : 1,
                        'tutar' => kacakExcelTutar($tutarHam),
                        'kontrol_edildi' => kacakExcelEvetMi($al('kontrol_edildi')) ? 1 : 0,
                        'usulsuz_notu' => $usulsuzHam !== '' ? $usulsuzHam : null,
                        'aciklama' => $al('aciklama') !== '' ? $al('aciklama') : null,
                        'kaynak' => 'excel',
                        'onay_durumu' => 'onaylandi',
                        'onaylayan_id' => $userId,
                        'mukerrer_kontrol' => false,
                    ],
                    'teslim' => kacakExcelEvetMi($al('teslim')),
                ];
            }

            if (empty($hazir)) {
                kacakYanit(false, 'Yüklenebilir satır bulunamadı.', [
                    'basarili' => 0,
                    'atlanan' => $atlanan,
                    'atlananSayisi' => count($atlanan),
                ]);
            }

            $eklenenIdler = [];
            $teslimIdler = [];
            $enEskiTarih = null;
            $enYeniTarih = null;
            $db = $Kacak->getDb();
            $db->beginTransaction();
            try {
                foreach ($hazir as $kayit) {
                    $yeniId = $Kacak->createRecord($kayit['veri']);
                    $eklenenIdler[] = $yeniId;
                    if ($kayit['teslim']) {
                        $teslimIdler[] = $yeniId;
                    }
                    $satirTarihi = $kayit['veri']['tarih'];
                    if ($enEskiTarih === null || $satirTarihi < $enEskiTarih) {
                        $enEskiTarih = $satirTarihi;
                    }
                    if ($enYeniTarih === null || $satirTarihi > $enYeniTarih) {
                        $enYeniTarih = $satirTarihi;
                    }
                }
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                error_log('Kaçak Excel yükleme hatası: ' . $e->getMessage());
                kacakYanit(false, 'Kayıtlar veritabanına yazılırken hata oluştu, hiçbir satır eklenmedi.');
            }

            if (!empty($teslimIdler)) {
                try {
                    $Kacak->teslimAlindiIsaretle($teslimIdler, $userId);
                } catch (\Throwable $e) {
                    error_log('Kaçak Excel teslim işaretleme hatası: ' . $e->getMessage());
                }
            }

            $Log->logAction(
                $userId,
                'Kaçak Excel Yükleme',
                count($eklenenIdler) . ' kayıt Excel ile eklendi, ' . count($atlanan) . ' satır atlandı. Dosya: ' . $_FILES['excelFile']['name'],
                SystemLogModel::LEVEL_IMPORTANT
            );

            $mesaj = count($eklenenIdler) . ' kayıt yüklendi.';
            if (!empty($atlanan)) {
                $mesaj .= ' ' . count($atlanan) . ' satır atlandı.';
            }

            kacakYanit(true, $mesaj, [
                'basarili' => count($eklenenIdler),
                'atlanan' => $atlanan,
                'atlananSayisi' => count($atlanan),
                'teslimIsaretlenen' => count($teslimIdler),
                'ilkTarih' => $enEskiTarih,
                'sonTarih' => $enYeniTarih,
            ]);
            break;

        case 'delete':
            kacakYetkiKontrol('kacak_duzenle');
            $id = (int) ($_POST['id'] ?? 0);
            if (!$Kacak->getRecord($id)) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            if (!$Kacak->softDeleteRecord($id)) {
                kacakYanit(false, 'Kayıt silinemedi.');
            }
            $Log->logAction($userId, 'Kaçak Kaydı Silindi', "ID: $id", SystemLogModel::LEVEL_IMPORTANT);
            kacakYanit(true, 'Kayıt silindi.');
            break;

        // =====================================================
        // ONAY / RED
        // =====================================================
        case 'approve':
            kacakYetkiKontrol('kacak_onay');
            $id = (int) ($_POST['id'] ?? 0);
            $kayit = $Kacak->getRecord($id);
            if (!$kayit) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            if (!$Kacak->approve($id, $userId)) {
                kacakYanit(false, 'Onaylama işlemi başarısız.');
            }
            kacakBildirimGonder($kayit, 'onaylandi');
            $Log->logAction($userId, 'Kaçak Bildirimi Onaylandı', "ID: $id, Tutanak: " . ($kayit['tutanak_no'] ?? '-'), SystemLogModel::LEVEL_IMPORTANT);
            kacakYanit(true, 'Bildirim onaylandı.');
            break;

        case 'reject':
            kacakYetkiKontrol('kacak_onay');
            $id = (int) ($_POST['id'] ?? 0);
            $neden = trim((string) ($_POST['red_nedeni'] ?? ''));
            if ($neden === '') {
                kacakYanit(false, 'Red nedeni zorunludur.');
            }
            $kayit = $Kacak->getRecord($id);
            if (!$kayit) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            if (!$Kacak->reject($id, $userId, $neden)) {
                kacakYanit(false, 'Reddetme işlemi başarısız.');
            }
            kacakBildirimGonder($kayit, 'reddedildi', $neden);
            $Log->logAction($userId, 'Kaçak Bildirimi Reddedildi', "ID: $id, Neden: $neden", SystemLogModel::LEVEL_IMPORTANT);
            kacakYanit(true, 'Bildirim reddedildi.');
            break;

        // =====================================================
        // İPTAL
        // =====================================================
        case 'cancel':
            kacakYetkiKontrol('kacak_iptal_ekle');
            $id = !empty($_POST['cancel_token'])
                ? (int) Security::decrypt((string) $_POST['cancel_token'])
                : (int) ($_POST['id'] ?? 0);
            $aciklama = trim((string) ($_POST['iptal_aciklama'] ?? ''));
            $hakedistenDus = ($_POST['hakedisten_dus'] ?? '0') === '1';

            if ($aciklama === '') {
                kacakYanit(false, 'İptal açıklaması zorunludur.');
            }
            $kayit = $Kacak->getRecord($id);
            if (!$kayit) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            if ($kayit['durum'] === 'iptal') {
                kacakYanit(false, 'Bu tutanak zaten iptal edilmiş.');
            }

            if (!$Kacak->cancel($id, $userId, $aciklama, $hakedistenDus)) {
                kacakYanit(false, 'İptal işlemi başarısız.');
            }

            if (!empty($_FILES['iptal_foto']['name'])) {
                try {
                    $yol = $Kacak->storeUploadedFile($_FILES['iptal_foto'], $id, 'iptal', $cekim);
                    $Kacak->addPhoto($id, 'iptal', $yol, $_FILES['iptal_foto']['name'], null, $userId, null,
                        KacakKontrolModel::cekimBilgisiCoz($cekim, $_POST['iptal_foto_cekim'] ?? null));
                } catch (\Throwable $e) {
                    error_log('Kaçak iptal fotoğrafı yüklenemedi: ' . $e->getMessage());
                }
            }

            $Log->logAction(
                $userId,
                'Kaçak Tutanağı İptal Edildi',
                "ID: $id, Tutanak: " . ($kayit['tutanak_no'] ?? '-') . ', Hakedişten düş: ' . ($hakedistenDus ? 'Evet' : 'Hayır'),
                SystemLogModel::LEVEL_IMPORTANT
            );
            kacakYanit(true, 'Tutanak iptal edildi.');
            break;

        case 'revert-cancel':
            kacakYetkiKontrol('kacak_iptal');
            $id = (int) ($_POST['id'] ?? 0);
            if (!$Kacak->getRecord($id)) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            if (!$Kacak->revertCancel($id)) {
                kacakYanit(false, 'İptal geri alınamadı.');
            }
            $Log->logAction($userId, 'Kaçak İptali Geri Alındı', "ID: $id", SystemLogModel::LEVEL_IMPORTANT);
            kacakYanit(true, 'İptal geri alındı, kayıt yeniden aktif.');
            break;

        // =====================================================
        // FOTOĞRAF
        // =====================================================
        case 'get-photos':
            $id = (int) ($_GET['id'] ?? 0);
            if (!$Kacak->getRecord($id)) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            kacakYanit(true, '', ['data' => $Kacak->getPhotos($id)]);
            break;

        case 'download-zip':
            $id = !empty($_GET['token'])
                ? (int) Security::decrypt((string) $_GET['token'])
                : (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0) {
                kacakYanit(false, 'Geçersiz kayıt ID.');
            }
            kacakRecordZipIndir($Kacak, $Log, $userId, $userPersonelId, $id);
            break;

        case 'upload-photo':
            kacakYetkiKontrol('kacak_duzenle');
            $id = (int) ($_POST['id'] ?? 0);
            if (!$Kacak->getRecord($id)) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            $eklenen = kacakFotoYukle($Kacak, $id, $userId, $medyaUyarilari);
            if ($eklenen === 0) {
                kacakYanit(false, !empty($medyaUyarilari)
                    ? implode(' ', $medyaUyarilari)
                    : 'Yüklenecek dosya bulunamadı veya limit doldu.');
            }
            kacakYanit(
                true,
                "$eklenen dosya yüklendi." . kacakMedyaUyariMetni($medyaUyarilari),
                ['medya_uyarilari' => $medyaUyarilari]
            );
            break;

        case 'delete-photo':
            kacakYetkiKontrol('kacak_arsiv');
            $fotoId = (int) ($_POST['foto_id'] ?? 0);
            if (!$Kacak->deletePhoto($fotoId)) {
                kacakYanit(false, 'Fotoğraf silinemedi.');
            }
            $Log->logAction($userId, 'Kaçak Fotoğrafı Silindi', "Foto ID: $fotoId", SystemLogModel::LEVEL_IMPORTANT);
            kacakYanit(true, 'Fotoğraf silindi.');
            break;

        case 'archive-preview':
            kacakYetkiKontrol('kacak_arsiv');
            $bas = kacakTarih($_GET['start_date'] ?? '', date('Y-m-01'));
            $bit = kacakTarih($_GET['end_date'] ?? '', date('Y-m-d'));
            $fotolar = $Kacak->getPhotosForArchive($bas, $bit);
            kacakYanit(true, '', ['count' => count($fotolar)]);
            break;

        case 'archive-download':
            kacakYetkiKontrol('kacak_arsiv');
            kacakArsivle($Kacak, $Log, $userId);
            break;

        // =====================================================
        // YAPAY ZEKA ANALİZİ
        // =====================================================
        case 'analyze':
            kacakYetkiKontrol('kacak_duzenle');

            if (empty($_FILES['tutanak_file']['name'])) {
                kacakYanit(false, 'Lütfen analiz edilecek tutanak dosyasını seçin.');
            }

            $varsayilanTarih = kacakTarih($_POST['tarih'] ?? '');

            $Personel = new PersonelModel();
            $dropdown = [];
            foreach ($Personel->all(false, 'puantaj', $varsayilanTarih) as $p) {
                $dropdown[] = ['id' => (int) $p->id, 'name' => $p->adi_soyadi];
            }

            $Analiz = new KacakTutanakAnalizService();
            $satirlar = $Analiz->analyze(
                $_FILES['tutanak_file'],
                $varsayilanTarih,
                $Analiz->getPersonelAdaylari($dropdown)
            );

            kacakYanit(true, 'Tutanak analiz edildi. Lütfen bilgileri kontrol edip kaydedin.', ['data' => $satirlar]);
            break;

        // =====================================================
        // RAPORLAR
        // =====================================================
        case 'gunluk-rapor':
            $tarih = kacakTarih($_GET['tarih'] ?? '');
            kacakYanit(true, '', [
                'tarih' => $tarih,
                'metin' => $Kacak->getGunlukRaporMetni($tarih),
                'veri' => $Kacak->getGunlukRapor($tarih),
            ]);
            break;

        case 'haftalik-rapor':
            $bas = kacakTarih($_GET['start_date'] ?? '', date('Y-m-d', strtotime('monday this week')));
            $bit = kacakTarih($_GET['end_date'] ?? '', date('Y-m-d'));
            kacakYanit(true, '', [
                'baslangic' => $bas,
                'bitis' => $bit,
                'data' => $Kacak->getBolgeBazliOzet($bas, $bit),
            ]);
            break;

        case 'teslim-alma-listesi':
            $bas = kacakTarih($_GET['start_date'] ?? '', date('Y-m-d', strtotime('monday this week')));
            $bit = kacakTarih($_GET['end_date'] ?? '', date('Y-m-d'));
            $liste = $Kacak->getTeslimAlmaListesi($bas, $bit);
            foreach ($liste as &$satir) {
                $satir['tarih_formatted'] = Date::dmY($satir['tarih']);
                $satir['token'] = Security::encrypt($satir['id']);
                unset($satir['id']);
            }
            unset($satir);
            kacakYanit(true, '', ['baslangic' => $bas, 'bitis' => $bit, 'data' => $liste]);
            break;

        case 'teslim-alindi-isaretle':
            kacakYetkiKontrol('kacak_duzenle');
            $tokenlar = $_POST['tokens'] ?? [];
            if (!is_array($tokenlar)) $tokenlar = [];
            $ids = [];
            foreach ($tokenlar as $token) {
                $id = (int) Security::decrypt((string) $token);
                if ($id > 0) $ids[] = $id;
            }
            if (empty($ids)) kacakYanit(false, 'En az bir kayıt seçmelisiniz.');
            $etkilenen = $Kacak->teslimAlindiIsaretle($ids, $userId);
            $Log->logAction($userId, 'Kaçak Evrak Teslim Alındı', 'Kayıt sayısı: ' . count(array_unique($ids)), SystemLogModel::LEVEL_IMPORTANT);
            kacakYanit(true, count(array_unique($ids)) . ' kayıt teslim alındı olarak işaretlendi.', ['affected' => $etkilenen]);
            break;

        // =====================================================
        // SİCİL OLUŞMAYANLAR
        // =====================================================
        case 'sicil-list':
            sicilGorusYetkiKontrol();
            $columnNames = ['tutanak_no', 'tutanak_tarihi', 'abone_adi', 'ekip_adi', 'neden', '', '', '', 'tur_sira', 'durum', ''];
            $filters = [
                'durum' => sicilDurumFiltresi($_GET['durum'] ?? ''),
                'neden' => $_GET['neden'] ?? '',
                'arama' => $_GET['arama'] ?? '',
            ];

            if (!empty($_GET['start_date'])) {
                $filters['tarih_baslangic'] = kacakTarih($_GET['start_date']);
            }
            if (!empty($_GET['end_date'])) {
                $filters['tarih_bitis'] = kacakTarih($_GET['end_date']);
            }

            $dataTableRequest = isset($_GET['draw']);
            if ($dataTableRequest) {
                $globalSearch = trim((string) ($_GET['search']['value'] ?? ''));
                if ($globalSearch !== '') {
                    $filters['arama'] = trim($filters['arama'] . ' ' . $globalSearch);
                }
                foreach (($_GET['columns'] ?? []) as $index => $column) {
                    $value = trim((string) ($column['search']['value'] ?? ''));
                    if ($value !== '' && !empty($columnNames[$index])) {
                        $filters['kolon_aramalari'][$columnNames[$index]] = $value;
                    }
                }
            }

            // Yalnızca yanıtlama yetkisi olan personel kendi ekibinin taleplerini görür.
            if ($userPersonelId > 0 && !kacakSuperAdmin() && !kacakIzin('kacak_sicil_bildir') && !kacakIzin('kacak_onay')) {
                $filters['personel_id'] = $userPersonelId;
            }

            $limit = $dataTableRequest ? max(1, min(100, (int) ($_GET['length'] ?? 25))) : 0;
            $offset = $dataTableRequest ? max(0, (int) ($_GET['start'] ?? 0)) : 0;
            $orderIndex = (int) ($_GET['order'][0]['column'] ?? 0);
            $orderColumn = $columnNames[$orderIndex] ?? 'bildirim_tarihi';
            $orderDirection = (string) ($_GET['order'][0]['dir'] ?? 'desc');

            $kayitlar = $Sicil->getRecords($filters, $limit, $offset, $orderColumn ?: 'bildirim_tarihi', $orderDirection);
            foreach ($kayitlar as &$s) {
                $s['bildirim_tarihi_formatted'] = Date::dmYHis($s['bildirim_tarihi'], 'd.m.Y H:i');
                $s['tutanak_tarihi_formatted'] = !empty($s['tutanak_tarihi']) ? Date::dmY($s['tutanak_tarihi']) : '';
                $s['yanit_tarihi_formatted'] = !empty($s['yanit_tarihi']) ? Date::dmYHis($s['yanit_tarihi'], 'd.m.Y H:i') : '';
                $s['kapatma_tarihi_formatted'] = !empty($s['kapatma_tarihi']) ? Date::dmYHis($s['kapatma_tarihi'], 'd.m.Y H:i') : '';
            }
            unset($s);

            if ($dataTableRequest) {
                $filteredCount = $Sicil->countRecords($filters);
                kacakYanit(true, '', [
                    'draw' => (int) $_GET['draw'],
                    'recordsTotal' => $filteredCount,
                    'recordsFiltered' => $filteredCount,
                    'data' => $kayitlar,
                ]);
            }

            kacakYanit(true, '', ['data' => $kayitlar]);
            break;

        case 'sicil-counts':
            sicilGorusYetkiKontrol();
            $sadeceEkip = $userPersonelId > 0 && !kacakSuperAdmin()
                && !kacakIzin('kacak_sicil_bildir') && !kacakIzin('kacak_onay');
            kacakYanit(true, '', ['counts' => $Sicil->getCounts($sadeceEkip ? $userPersonelId : 0)]);
            break;

        case 'sicil-detay':
            sicilGorusYetkiKontrol();
            $kayit = $Sicil->getRecord((int) ($_GET['id'] ?? 0));
            if (!$kayit) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            if ($userPersonelId > 0 && !kacakSuperAdmin() && !kacakIzin('kacak_sicil_bildir') && !kacakIzin('kacak_onay')) {
                if (!$Sicil->personelYetkiliMi((int) $kayit['id'], $userPersonelId)) {
                    kacakYanit(false, 'Bu kaydı görüntüleme yetkiniz bulunmuyor.');
                }
            }
            kacakYanit(true, '', ['data' => $kayit]);
            break;

        case 'sicil-tutanak-ara':
            kacakYetkiKontrol('kacak_sicil_bildir');
            $sonuclar = $Sicil->tutanakAra((string) ($_GET['q'] ?? ''));
            foreach ($sonuclar as &$t) {
                $t['tarih_formatted'] = Date::dmY($t['tarih']);
            }
            unset($t);
            kacakYanit(true, '', ['data' => $sonuclar]);
            break;

        case 'sicil-create':
            kacakYetkiKontrol('kacak_sicil_bildir');
            try {
                $sicilId = $Sicil->create([
                    'tutanak_no' => $_POST['tutanak_no'] ?? '',
                    'kacak_id' => $_POST['kacak_id'] ?? null,
                    'neden' => $_POST['neden'] ?? '',
                    'aciklama' => $_POST['aciklama'] ?? '',
                ], $userId);
            } catch (\Exception $e) {
                kacakYanit(false, $e->getMessage());
            }

            $yeniKayit = $Sicil->getRecord($sicilId);
            $Log->logAction(
                $userId,
                'Sicil Eksik Bildirimi Açıldı',
                'Tutanak: ' . ($yeniKayit['tutanak_no'] ?? '-') . ', Neden: ' . ($yeniKayit['neden_metin'] ?? '-'),
                SystemLogModel::LEVEL_IMPORTANT
            );

            sicilEkibeBildir($yeniKayit ?? []);

            kacakYanit(true, 'Sicil eksik bildirimi oluşturuldu, ekibe iletildi.', ['id' => $sicilId]);
            break;

        case 'sicil-yanitla':
            kacakYetkiKontrol('kacak_sicil_yanitla');
            $sicilId = (int) ($_POST['id'] ?? 0);
            try {
                $guncel = $Sicil->yanitla($sicilId, [
                    'abone_adi' => $_POST['abone_adi'] ?? '',
                    'abone_tc' => $_POST['abone_tc'] ?? '',
                    'abone_dogum_tarihi' => $_POST['abone_dogum_tarihi'] ?? '',
                    'abone_adres' => $_POST['abone_adres'] ?? '',
                    'sayac_no' => $_POST['sayac_no'] ?? '',
                    'yanit_aciklama' => $_POST['yanit_aciklama'] ?? '',
                ], $userPersonelId, $userId);
            } catch (\Exception $e) {
                kacakYanit(false, $e->getMessage());
            }

            $Log->logAction(
                $userId,
                'Sicil Eksik Bildirimi Yanıtlandı',
                'ID: ' . $sicilId . ', Tutanak: ' . ($guncel['tutanak_no'] ?? '-'),
                SystemLogModel::LEVEL_IMPORTANT
            );

            sicilKurumaBildir($guncel ?? []);

            kacakYanit(true, 'Düzeltilmiş bilgi kaydedildi ve kuruma iletildi.');
            break;

        case 'sicil-kapat':
            kacakYetkiKontrol('kacak_sicil_bildir');
            $sicilId = (int) ($_POST['id'] ?? 0);
            $sonuc = (string) ($_POST['sonuc'] ?? '');
            try {
                $guncel = $Sicil->kapat($sicilId, $sonuc, (string) ($_POST['aciklama'] ?? ''), $userId);
            } catch (\Exception $e) {
                kacakYanit(false, $e->getMessage());
            }

            $Log->logAction(
                $userId,
                $sonuc === 'cozuldu' ? 'Sicil Eksik Bildirimi Çözüldü' : 'Sicil Eksik Bildirimi İptal Edildi',
                'ID: ' . $sicilId . ', Tutanak: ' . ($guncel['tutanak_no'] ?? '-'),
                SystemLogModel::LEVEL_IMPORTANT
            );

            if ($sonuc === 'cozuldu') {
                sicilEkibeBildir($guncel ?? [], 'cozuldu');
            }

            kacakYanit(true, $sonuc === 'cozuldu' ? 'Kayıt çözüldü olarak kapatıldı.' : 'Kayıt iptal edildi.');
            break;

        case 'sicil-eslestir':
            kacakYetkiKontrol('kacak_sicil_yanitla');
            $sicilId = (int) ($_POST['id'] ?? 0);
            try {
                $guncel = $Sicil->eslestir($sicilId, (int) ($_POST['kacak_id'] ?? 0));
            } catch (\Exception $e) {
                kacakYanit(false, $e->getMessage());
            }

            $Log->logAction(
                $userId,
                'Sicil Eksik Bildirimi Eşleştirildi',
                'ID: ' . $sicilId . ', Tutanak: ' . ($guncel['tutanak_no'] ?? '-'),
                SystemLogModel::LEVEL_IMPORTANT
            );

            sicilEkibeBildir($guncel ?? []);

            kacakYanit(true, 'Kayıt tutanakla eşleştirildi, ekibe bildirim gönderildi.');
            break;

        default:
            kacakYanit(false, 'Geçersiz istek.');
    }
} catch (\Throwable $e) {
    error_log('Kaçak API hatası (' . $action . '): ' . $e->getMessage());
    kacakYanit(false, $e->getMessage() !== '' ? $e->getMessage() : 'İşlem sırasında bir hata oluştu.');
}

/**
 * Modal üzerinden gelen tutanak ve saha fotoğraflarını kaydeder.
 */
function kacakFotoYukle(KacakKontrolModel $Kacak, int $kacakId, int $userId, ?array &$uyarilar = null): int
{
    $eklenen = 0;
    $uyarilar = [];

    $tutanakFiles = [];
    if (!empty($_FILES['tutanak_foto']['name'])) {
        if (is_array($_FILES['tutanak_foto']['name'])) {
            foreach ($_FILES['tutanak_foto']['name'] as $i => $ad) {
                if (!empty($ad) && ($_FILES['tutanak_foto']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $tutanakFiles[] = [
                        'name' => $ad,
                        'tmp_name' => $_FILES['tutanak_foto']['tmp_name'][$i],
                        'error' => $_FILES['tutanak_foto']['error'][$i],
                        'size' => $_FILES['tutanak_foto']['size'][$i],
                        'cekim' => $_POST['tutanak_foto_cekim'][$i] ?? null,
                    ];
                }
            }
        } else if (($_FILES['tutanak_foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tutanakFiles[] = [
                'name' => $_FILES['tutanak_foto']['name'],
                'tmp_name' => $_FILES['tutanak_foto']['tmp_name'],
                'error' => $_FILES['tutanak_foto']['error'],
                'size' => $_FILES['tutanak_foto']['size'],
                'cekim' => $_POST['tutanak_foto_cekim'] ?? null,
            ];
        }
    }
    if (!empty($_FILES['tutanak_file']['name']) && ($_FILES['tutanak_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $tutanakFiles[] = [
            'name' => $_FILES['tutanak_file']['name'],
            'tmp_name' => $_FILES['tutanak_file']['tmp_name'],
            'error' => $_FILES['tutanak_file']['error'],
            'size' => $_FILES['tutanak_file']['size'],
            'cekim' => null,
        ];
    }

    foreach ($tutanakFiles as $tf) {
        try {
            $yol = $Kacak->storeUploadedFile($tf, $kacakId, 'tutanak', $cekim);
            $Kacak->addPhoto($kacakId, 'tutanak', $yol, $tf['name'], null, $userId, null,
                KacakKontrolModel::cekimBilgisiCoz($cekim, $tf['cekim']));
            $eklenen++;
        } catch (\Throwable $e) {
            error_log('Kaçak tutanak belgesi yüklenemedi: ' . $e->getMessage());
        }
    }

    if (!empty($_FILES['videolar']['name'])) {
        $sureler = $_POST['video_sureleri'] ?? [];
        $kapaklar = $_POST['video_kapaklari'] ?? [];
        $cekimler = $_POST['video_cekimleri'] ?? [];
        $videoFiles = [];

        if (is_array($_FILES['videolar']['name'])) {
            foreach ($_FILES['videolar']['name'] as $i => $ad) {
                if (empty($ad)) {
                    continue;
                }
                $hataKodu = (int) ($_FILES['videolar']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                if ($hataKodu !== UPLOAD_ERR_OK) {
                    $mesaj = VideoUploadService::uploadErrorMessage($hataKodu, KacakKontrolModel::videoYuklemeSiniri());
                    $uyarilar[] = $ad . ': ' . $mesaj;
                    error_log('Kaçak videosu sunucuya ulaşmadı: kacak_id=' . $kacakId
                        . ' dosya=' . $ad . ' hata_kodu=' . $hataKodu
                        . ' upload_max_filesize=' . ini_get('upload_max_filesize')
                        . ' post_max_size=' . ini_get('post_max_size'));
                    continue;
                }
                $videoFiles[] = [
                    'file' => [
                        'name' => $ad,
                        'tmp_name' => $_FILES['videolar']['tmp_name'][$i],
                        'error' => $hataKodu,
                        'size' => $_FILES['videolar']['size'][$i],
                    ],
                    'sure' => isset($sureler[$i]) && is_numeric($sureler[$i]) ? (int) ceil((float) $sureler[$i]) : null,
                    'kapak' => isset($kapaklar[$i]) ? (string) $kapaklar[$i] : null,
                    'cekim' => $cekimler[$i] ?? null,
                    'name' => $ad
                ];
            }
        } else {
            $hataKodu = (int) ($_FILES['videolar']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($hataKodu === UPLOAD_ERR_OK) {
                $sure = is_array($sureler) ? ($sureler[0] ?? null) : $sureler;
                $kapak = is_array($kapaklar) ? ($kapaklar[0] ?? null) : $kapaklar;
                $cekim = is_array($cekimler) ? ($cekimler[0] ?? null) : $cekimler;
                $videoFiles[] = [
                    'file' => $_FILES['videolar'],
                    'sure' => is_numeric($sure) ? (int) ceil((float) $sure) : null,
                    'kapak' => !empty($kapak) ? (string) $kapak : null,
                    'cekim' => $cekim,
                    'name' => $_FILES['videolar']['name']
                ];
            } else {
                $mesaj = VideoUploadService::uploadErrorMessage($hataKodu, KacakKontrolModel::videoYuklemeSiniri());
                $uyarilar[] = $_FILES['videolar']['name'] . ': ' . $mesaj;
                error_log('Kaçak videosu sunucuya ulaşmadı: kacak_id=' . $kacakId
                    . ' dosya=' . $_FILES['videolar']['name'] . ' hata_kodu=' . $hataKodu
                    . ' upload_max_filesize=' . ini_get('upload_max_filesize')
                    . ' post_max_size=' . ini_get('post_max_size'));
            }
        }

        foreach ($videoFiles as $vItem) {
            try {
                $sonuc = $Kacak->storeUploadedVideo(
                    $vItem['file'],
                    $kacakId,
                    $vItem['sure'],
                    $vItem['kapak']
                );
                $Kacak->addVideo($kacakId, $sonuc['yol'], $sonuc['kapak'], $sonuc['sure_saniye'], $vItem['name'], null, $userId,
                    KacakKontrolModel::cekimBilgisiCoz(null, $vItem['cekim'] ?? null));
                $eklenen++;
            } catch (\Throwable $e) {
                $uyarilar[] = $vItem['name'] . ': ' . $e->getMessage();
                error_log('Kaçak videosu yüklenemedi: kacak_id=' . $kacakId
                    . ' dosya=' . $vItem['name'] . ' hata=' . $e->getMessage());
            }
        }
    }

    if (!empty($_FILES['saha_fotolari']) && is_array($_FILES['saha_fotolari']['name'])) {
        $mevcut = $Kacak->countPhotos($kacakId, 'saha');
        foreach ($_FILES['saha_fotolari']['name'] as $i => $ad) {
            if ($mevcut >= KacakKontrolModel::MAX_SAHA_FOTO) {
                break;
            }
            if (empty($ad) || $_FILES['saha_fotolari']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            try {
                $dosya = [
                    'name' => $ad,
                    'tmp_name' => $_FILES['saha_fotolari']['tmp_name'][$i],
                    'error' => $_FILES['saha_fotolari']['error'][$i],
                    'size' => $_FILES['saha_fotolari']['size'][$i],
                ];
                $yol = $Kacak->storeUploadedFile($dosya, $kacakId, 'saha', $cekim);
                $Kacak->addPhoto($kacakId, 'saha', $yol, $ad, null, $userId, null,
                    KacakKontrolModel::cekimBilgisiCoz($cekim, $_POST['saha_fotolari_cekim'][$i] ?? null));
                $mevcut++;
                $eklenen++;
            } catch (\Throwable $e) {
                error_log('Kaçak saha fotoğrafı yüklenemedi: ' . $e->getMessage());
            }
        }
    }

    return $eklenen;
}

/**
 * Personel PWA bildirimi onaylandığında/reddedildiğinde bildiren personele haber verir.
 */
function kacakBildirimGonder(array $kayit, string $durum, string $neden = ''): void
{
    if (empty($kayit['bildiren_personel_id'])) {
        return;
    }

    try {
        $Push = new \App\Service\PushNotificationService();
        $baslik = $durum === 'onaylandi' ? 'Kaçak Bildiriminiz Onaylandı' : 'Kaçak Bildiriminiz Reddedildi';
        $mesaj = date('d.m.Y', strtotime($kayit['tarih'])) . ' tarihli ' . ($kayit['tur'] ?? 'Kaçak') . ' tutanağınız '
            . ($durum === 'onaylandi' ? 'onaylandı.' : 'reddedildi. Neden: ' . $neden);

        $Push->sendToPersonel((int) $kayit['bildiren_personel_id'], [
            'title' => $baslik,
            'body' => $mesaj,
            'url' => '?page=kacak',
        ]);
    } catch (\Throwable $e) {
        error_log('Kaçak bildirimi gönderilemedi: ' . $e->getMessage());
    }
}

/**
 * Sicil eksik bildirimini tutanağı tutan ekibe iletir.
 * $tip: 'acildi' -> düzeltme isteniyor, 'cozuldu' -> bilgilendirme
 */
function sicilEkibeBildir(array $kayit, string $tip = 'acildi'): void
{
    if (empty($kayit['atanan_personel_ids'])) {
        return;
    }

    $personelIds = array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string) $kayit['atanan_personel_ids']))
    )));

    if (!$personelIds) {
        return;
    }

    $tutanakNo = $kayit['tutanak_no'] ?? '-';

    if ($tip === 'cozuldu') {
        $baslik = 'Tutanak Sicili Oluşturuldu';
        $mesaj = $tutanakNo . ' nolu tutanak için sicil oluşturuldu, düzeltme talebi kapandı.';
    } else {
        $baslik = 'Tutanak Bilgi Düzeltmesi Gerekiyor';
        $mesaj = $tutanakNo . ' nolu tutanağın ' . mb_strtolower($kayit['neden_metin'] ?? 'bilgisi hatalı', 'UTF-8')
            . ' olduğu bildirildi. Aboneye ulaşıp doğru bilgiyi uygulamadan giriniz.';
        if (!empty($kayit['aciklama'])) {
            $mesaj .= ' Not: ' . $kayit['aciklama'];
        }
    }

    try {
        $Push = new \App\Service\PushNotificationService();
        foreach ($personelIds as $personelId) {
            $Push->sendToPersonel($personelId, [
                'title' => $baslik,
                'body' => $mesaj,
                'url' => '?page=kacak',
            ]);
        }
    } catch (\Throwable $e) {
        error_log('Sicil eksik bildirimi ekibe gönderilemedi: ' . $e->getMessage());
    }
}

/**
 * Ekip düzeltmeyi girdiğinde bildirimi açan kurum kullanıcısına haber verir.
 */
function sicilKurumaBildir(array $kayit): void
{
    if (empty($kayit['bildiren_user_id'])) {
        return;
    }

    $tutanakNo = $kayit['tutanak_no'] ?? '-';
    $mesaj = $tutanakNo . ' nolu tutanak için ekip düzeltilmiş bilgiyi girdi. Kontrol edip sicil oluşturabilirsiniz.';
    $link = 'index.php?p=kacak/list&tab=sicil&sicil_id=' . (int) ($kayit['id'] ?? 0);

    try {
        $Bildirim = new \App\Model\BildirimModel();
        $Bildirim->createNotification(
            (int) $kayit['bildiren_user_id'],
            'Sicil Düzeltmesi Yanıtlandı',
            $mesaj,
            $link,
            'user-check',
            'success'
        );
    } catch (\Throwable $e) {
        error_log('Sicil düzeltme bildirimi kaydedilemedi: ' . $e->getMessage());
    }

    try {
        $Push = new \App\Service\PushNotificationService();
        $Push->sendToUser((int) $kayit['bildiren_user_id'], [
            'title' => 'Sicil Düzeltmesi Yanıtlandı',
            'body' => $mesaj,
            'url' => $link,
        ], true);
    } catch (\Throwable $e) {
        error_log('Sicil düzeltme push bildirimi gönderilemedi: ' . $e->getMessage());
    }
}

/**
 * Tarih aralığındaki fotoğrafları ilçe bazlı klasörlenmiş ZIP olarak indirir ve sunucudan siler.
 */
function kacakArsivle(KacakKontrolModel $Kacak, SystemLogModel $Log, int $userId): void
{
    $bas = kacakTarih($_POST['start_date'] ?? $_GET['start_date'] ?? '', date('Y-m-01'));
    $bit = kacakTarih($_POST['end_date'] ?? $_GET['end_date'] ?? '', date('Y-m-d'));

    $fotolar = $Kacak->getPhotosForArchive($bas, $bit);
    if (empty($fotolar)) {
        kacakYanit(false, 'Seçilen tarih aralığında arşivlenecek fotoğraf bulunamadı.');
    }

    $root = KacakKontrolModel::rootPath();
    $zipAdi = 'kacak_fotograflari_' . $bas . '_' . $bit . '.zip';
    $zipYolu = sys_get_temp_dir() . '/' . uniqid('kacak_arsiv_', true) . '.zip';

    $zip = new \ZipArchive();
    if ($zip->open($zipYolu, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        kacakYanit(false, 'Arşiv dosyası oluşturulamadı.');
    }

    $eklenenIds = [];
    foreach ($fotolar as $foto) {
        $kaynak = $root . '/' . ltrim($foto['dosya_yolu'], '/');
        if (!is_file($kaynak)) {
            $eklenenIds[] = (int) $foto['id'];
            continue;
        }

        $ilce = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $foto['ilce'] ?: 'Belirtilmemis');
        $turKlasor = ($foto['medya_tipi'] ?? 'foto') === 'video' ? 'Video' : ucfirst($foto['tur']);
        $origExt = strtolower(pathinfo($foto['dosya_yolu'], PATHINFO_EXTENSION));
        $isPdf = ($origExt === 'pdf');
        $isVideo = (($foto['medya_tipi'] ?? 'foto') === 'video' || in_array($origExt, ['mp4', 'mov', 'webm', '3gp'], true));

        if ($isPdf) {
            $ext = 'pdf';
            $dosyaAdi = sprintf(
                '%s_%s_%s.%s',
                date('Y-m-d', strtotime($foto['tarih'])),
                preg_replace('/[^\p{L}\p{N}_-]+/u', '_', (string) ($foto['tutanak_no'] ?: 'tutanaksiz')),
                $foto['id'],
                $ext
            );
            $zip->addFile($kaynak, $ilce . '/' . $turKlasor . '/' . $dosyaAdi, 0, 0, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
        } elseif ($isVideo) {
            $ext = $origExt ?: 'mp4';
            $dosyaAdi = sprintf(
                '%s_%s_%s.%s',
                date('Y-m-d', strtotime($foto['tarih'])),
                preg_replace('/[^\p{L}\p{N}_-]+/u', '_', (string) ($foto['tutanak_no'] ?: 'tutanaksiz')),
                $foto['id'],
                $ext
            );
            $zip->addFile($kaynak, $ilce . '/' . $turKlasor . '/' . $dosyaAdi, 0, 0, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
        } else {
            $ext = 'jpeg';
            $dosyaAdi = sprintf(
                '%s_%s_%s.%s',
                date('Y-m-d', strtotime($foto['tarih'])),
                preg_replace('/[^\p{L}\p{N}_-]+/u', '_', (string) ($foto['tutanak_no'] ?: 'tutanaksiz')),
                $foto['id'],
                $ext
            );
            $jpegData = KacakKontrolModel::getAsJpegBinary($kaynak);
            if ($jpegData !== null) {
                $zip->addFromString($ilce . '/' . $turKlasor . '/' . $dosyaAdi, $jpegData, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
            } else {
                $zip->addFile($kaynak, $ilce . '/' . $turKlasor . '/' . $dosyaAdi, 0, 0, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
            }
        }
        $eklenenIds[] = (int) $foto['id'];
    }

    $zip->close();

    if (empty($eklenenIds) || !is_file($zipYolu)) {
        @unlink($zipYolu);
        kacakYanit(false, 'Arşivlenecek dosya bulunamadı.');
    }

    $Kacak->markPhotosArchived($eklenenIds);

    foreach ($fotolar as $foto) {
        if (!in_array((int) $foto['id'], $eklenenIds, true)) {
            continue;
        }
        foreach ([$foto['dosya_yolu'], $foto['kucuk_yol'] ?? null] as $yol) {
            if (empty($yol)) {
                continue;
            }
            $kaynak = $root . '/' . ltrim($yol, '/');
            if (is_file($kaynak)) {
                @unlink($kaynak);
            }
        }
    }

    $Log->logAction(
        $userId,
        'Kaçak Fotoğrafları Arşivlendi',
        count($eklenenIds) . " adet fotoğraf arşivlenip sunucudan silindi. Aralık: $bas - $bit",
        SystemLogModel::LEVEL_CRITICAL
    );

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipAdi . '"');
    header('Content-Length: ' . filesize($zipYolu));
    readfile($zipYolu);
    @unlink($zipYolu);
    exit;
}

/**
 * Belirli bir kaçak kaydının fotoğraflarını ZIP olarak indirir.
 */
function kacakRecordZipIndir(KacakKontrolModel $Kacak, SystemLogModel $Log, int $userId, int $userPersonelId, int $kacakId): void
{
    $kayit = $Kacak->getRecord($kacakId);
    if (!$kayit) {
        http_response_code(404);
        exit('Kayıt bulunamadı.');
    }

    if ($userPersonelId > 0 && !kacakSuperAdmin() && !kacakIzin('kacak_onay')) {
        $isEkip = ($kayit['bildiren_personel_id'] == $userPersonelId) || in_array($userPersonelId, $kayit['personel_ids_array'] ?? [], true);
        if (!$isEkip) {
            http_response_code(403);
            exit('Bu kaydın fotoğraflarını indirme yetkiniz bulunmuyor.');
        }
    }

    $fotolar = $Kacak->getPhotos($kacakId);
    if (empty($fotolar)) {
        http_response_code(404);
        exit('Bu kayda ait indirilebilir foto/belge bulunamadı.');
    }

    $root = KacakKontrolModel::rootPath();

    $tutanakNoClean = !empty($kayit['tutanak_no']) ? trim((string) $kayit['tutanak_no']) : '';
    $aboneAdiClean = !empty($kayit['abone_adi']) ? Helper::trUpper(trim((string) $kayit['abone_adi'])) : '';
    $turClean = !empty($kayit['tur']) ? Helper::trUpper(trim((string) $kayit['tur'])) : 'KAÇAK';

    $folderParts = [];
    if ($tutanakNoClean !== '') {
        $folderParts[] = $tutanakNoClean;
    }
    if ($aboneAdiClean !== '') {
        $folderParts[] = $aboneAdiClean;
    }

    if (!empty($folderParts)) {
        $folderBase = implode(' - ', $folderParts);
        $rawFolderName = sprintf('%s (%s)', $folderBase, $turClean);
    } else {
        $rawFolderName = sprintf('kayit_%d (%s)', $kacakId, $turClean);
    }

    $folderName = preg_replace('/[\/\\\\:\*\?"<>\|]/u', '_', trim($rawFolderName));
    $zipAdi = $folderName . '.zip';
    $zipYolu = sys_get_temp_dir() . '/' . uniqid('kacak_rec_zip_', true) . '.zip';

    $zip = new \ZipArchive();
    if ($zip->open($zipYolu, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        exit('Arşiv dosyası oluşturulamadı.');
    }

    $eklenenSayisi = 0;
    $tutanakSeq = 1;
    $sahaSeq = 1;
    $iptalSeq = 1;
    $videoSeq = 1;

    foreach ($fotolar as $foto) {
        $kaynak = $root . '/' . ltrim($foto['dosya_yolu'], '/');
        if (!is_file($kaynak)) {
            continue;
        }

        $origExt = strtolower(pathinfo($foto['dosya_yolu'], PATHINFO_EXTENSION));
        $fotoTur = strtolower($foto['tur'] ?? 'saha');
        $medyaTipi = strtolower($foto['medya_tipi'] ?? 'foto');

        if ($fotoTur === 'tutanak') {
            $prefix = 'tutanak';
            $seq = $tutanakSeq++;
        } elseif ($fotoTur === 'iptal') {
            $prefix = 'iptal';
            $seq = $iptalSeq++;
        } else {
            if ($medyaTipi === 'video') {
                $prefix = 'video';
                $seq = $videoSeq++;
            } else {
                $prefix = 'saha';
                $seq = $sahaSeq++;
            }
        }

        $isPdf = ($origExt === 'pdf');
        $isVideo = ($medyaTipi === 'video' || in_array($origExt, ['mp4', 'mov', 'webm', '3gp'], true));

        if ($isPdf) {
            $ext = 'pdf';
            $dosyaAdi = sprintf('%s_%s_%d.%s', $prefix, $tutanakNoClean ?: ('kayit_' . $kacakId), $seq, $ext);
            $zip->addFile($kaynak, $folderName . '/' . $dosyaAdi, 0, 0, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
        } elseif ($isVideo) {
            $ext = $origExt ?: 'mp4';
            $dosyaAdi = sprintf('%s_%s_%d.%s', $prefix, $tutanakNoClean ?: ('kayit_' . $kacakId), $seq, $ext);
            $zip->addFile($kaynak, $folderName . '/' . $dosyaAdi, 0, 0, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
        } else {
            $ext = 'jpeg';
            $dosyaAdi = sprintf('%s_%s_%d.%s', $prefix, $tutanakNoClean ?: ('kayit_' . $kacakId), $seq, $ext);
            $jpegData = KacakKontrolModel::getAsJpegBinary($kaynak);
            if ($jpegData !== null) {
                $zip->addFromString($folderName . '/' . $dosyaAdi, $jpegData, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
            } else {
                $zip->addFile($kaynak, $folderName . '/' . $dosyaAdi, 0, 0, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
            }
        }
        $eklenenSayisi++;
    }

    $zip->close();

    if ($eklenenSayisi === 0 || !is_file($zipYolu)) {
        @unlink($zipYolu);
        http_response_code(404);
        exit('İndirilebilir dosya bulunamadı.');
    }

    $Log->logAction(
        $userId,
        'Kaçak Kaydı Fotoğrafları İndirildi (ZIP)',
        "Kayıt ID: $kacakId, Tutanak No: " . ($kayit['tutanak_no'] ?? '-') . ", Eklenen Dosya: $eklenenSayisi",
        SystemLogModel::LEVEL_INFO
    );

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $encodedZipAdi = rawurlencode($zipAdi);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $zipAdi) . '"; filename*=UTF-8\'\'' . $encodedZipAdi);
    header('Content-Length: ' . filesize($zipYolu));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    readfile($zipYolu);
    @unlink($zipYolu);
    exit;
}
