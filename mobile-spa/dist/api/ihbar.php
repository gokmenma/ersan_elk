<?php

declare(strict_types=1);

use App\Helper\Security;
use App\Helper\MedyaServis;
use App\Model\IhbarModel;
use App\Model\BildirimModel;
use App\Model\UserNotificationPreferenceModel;
use App\Service\Gate;
use App\Service\PushNotificationService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function ihbarApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function checkIhbarAuth(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
        ihbarApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.'], 401);
    }

    $canAccess = Gate::allows('ihbar/list') || Gate::isSuperAdmin();
    if (!$canAccess) {
        ihbarApiResponse(['success' => false, 'message' => 'İhbar yönetimi modülüne erişim yetkiniz bulunmuyor.'], 403);
    }
}

function requireCsrfToken(): void
{
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        ihbarApiResponse(['success' => false, 'message' => 'Güvenlik doğrulaması (CSRF) geçersiz. Lütfen sayfayı yenileyin.'], 419);
    }
}

function checkIhbarEditAuth(): void
{
    $canEdit = Gate::allows('ihbar_duzenle') || Gate::isSuperAdmin();
    if (!$canEdit) {
        ihbarApiResponse(['success' => false, 'message' => 'Bu işlem için düzenleme yetkiniz bulunmuyor.'], 403);
    }
}

function resolveIhbarId(string $tokenOrId): int
{
    $tokenOrId = trim($tokenOrId);
    if ($tokenOrId === '') {
        return 0;
    }
    if (ctype_digit($tokenOrId)) {
        return (int) $tokenOrId;
    }
    $decrypted = Security::decrypt($tokenOrId);
    return is_numeric($decrypted) ? (int) $decrypted : 0;
}

checkIhbarAuth();

$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
if ($firmaId <= 0) {
    ihbarApiResponse(['success' => false, 'message' => 'Geçerli bir firma seçilmemiş.'], 400);
}

$currentUserId = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
$canEdit = Gate::allows('ihbar_duzenle') || Gate::isSuperAdmin();
$IhbarModel = new IhbarModel();

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'list');

try {
    switch ($action) {
        case 'list':
            $tarihDogrula = static function ($deger, string $varsayilan): string {
                $deger = (string) $deger;
                $tarih = DateTime::createFromFormat('Y-m-d', $deger);
                return $tarih && $tarih->format('Y-m-d') === $deger ? $deger : $varsayilan;
            };

            $bitisTarihi = $tarihDogrula($_GET['bitis'] ?? '', date('Y-m-d'));
            $baslangicTarihi = $tarihDogrula($_GET['baslangic'] ?? '', date('Y-m-d', strtotime('-1 month')));
            if ($baslangicTarihi > $bitisTarihi) {
                [$baslangicTarihi, $bitisTarihi] = [$bitisTarihi, $baslangicTarihi];
            }

            $rawIhbarlar = $IhbarModel->getAllForDashboard($baslangicTarihi, $bitisTarihi);

            // Fotoğraf sayılarını tek sorguda toplayalım
            $db = $IhbarModel->getDb();
            $stmtFoto = $db->prepare(
                "SELECT f.ihbar_id, COUNT(*) AS foto_sayisi
                 FROM ihbar_fotograflari f
                 INNER JOIN ihbarlar i ON i.id = f.ihbar_id
                 WHERE i.firma_id = ? AND i.silinme_tarihi IS NULL
                 GROUP BY f.ihbar_id"
            );
            $stmtFoto->execute([$firmaId]);
            $fotoSayilari = [];
            while ($row = $stmtFoto->fetch(PDO::FETCH_ASSOC)) {
                $fotoSayilari[(int) $row['ihbar_id']] = (int) $row['foto_sayisi'];
            }

            $ozet = [
                'toplam' => count($rawIhbarlar),
                'yeni' => 0,
                'yonlendirildi' => 0,
                'islemde' => 0,
                'olumlu' => 0,
                'olumsuz' => 0,
                'devam' => 0,
                'sonuclanan' => 0,
                'basari_orani' => 0,
            ];

            $durumEtiketleri = [
                'yeni' => 'Yeni',
                'yonlendirildi' => 'Yönlendirildi',
                'islemde' => 'İşlemde',
                'olumlu' => 'Olumlu',
                'olumsuz' => 'Olumsuz',
            ];

            $ihbarList = [];
            foreach ($rawIhbarlar as $ihbar) {
                $durum = (string) ($ihbar->durum ?? 'yeni');
                if (isset($ozet[$durum])) {
                    $ozet[$durum]++;
                }
                if (in_array($durum, ['yonlendirildi', 'islemde'], true)) {
                    $ozet['devam']++;
                }
                if (in_array($durum, ['olumlu', 'olumsuz'], true)) {
                    $ozet['sonuclanan']++;
                }

                $token = Security::encrypt((string) $ihbar->id);
                $bildirenAdi = (string) ($ihbar->bildiren_personel_adi ?: ($ihbar->olusturan_user_adi ?: 'Belirtilmedi'));

                $ihbarList[] = [
                    'token' => $token,
                    'durum' => $durum,
                    'durum_etiketi' => $durumEtiketleri[$durum] ?? $durum,
                    'ilce' => (string) ($ihbar->ilce ?? ''),
                    'mahalle' => (string) ($ihbar->mahalle ?? ''),
                    'konum' => trim(((string) ($ihbar->ilce ?? '')) . ' / ' . ((string) ($ihbar->mahalle ?? '')), ' /'),
                    'aciklama' => (string) ($ihbar->aciklama ?? ''),
                    'bildiren' => $bildirenAdi,
                    'telefon' => (string) ($ihbar->telefon ?? ''),
                    'komsu_abone_no' => (string) ($ihbar->komsu_abone_no ?? ''),
                    'ekip' => (string) ($ihbar->atanan_ekip_adi ?: 'Atama bekliyor'),
                    'tarih' => !empty($ihbar->created_at) ? date('d.m.Y H:i', strtotime((string) $ihbar->created_at)) : '-',
                    'created_at' => (string) ($ihbar->created_at ?? ''),
                    'tutanak_no' => (string) ($ihbar->tutanak_no ?? ''),
                    'sebep' => (string) ($ihbar->olumsuz_sebep ?? ''),
                    'foto_sayisi' => $fotoSayilari[(int) $ihbar->id] ?? 0,
                    'lat' => is_numeric($ihbar->konum_lat) ? (float) $ihbar->konum_lat : null,
                    'lng' => is_numeric($ihbar->konum_lng) ? (float) $ihbar->konum_lng : null,
                    'konum_link' => (string) ($ihbar->konum_link ?? ''),
                ];
            }

            if ($ozet['sonuclanan'] > 0) {
                $ozet['basari_orani'] = (int) round(($ozet['olumlu'] / $ozet['sonuclanan']) * 100);
            }

            // İlçe listesi
            $ilceler = [
                'Afşin', 'Andırın', 'Çağlayancerit', 'Dulkadiroğlu',
                'Ekinözü', 'Elbistan', 'Göksun', 'Nurhak',
                'Onikişubat', 'Pazarcık', 'Türkoğlu',
            ];

            // Atanabilecek personeller
            $yonlendirilecek = $IhbarModel->getYonlendirilecekPersonelListesi();
            $personelAdaylari = array_map(static function ($p): array {
                return [
                    'token' => Security::encrypt((string) $p->id),
                    'ad' => (string) $p->adi_soyadi,
                    'departman' => (string) ($p->departman ?? 'Kaçak Kontrol'),
                ];
            }, $yonlendirilecek);

            ihbarApiResponse([
                'success' => true,
                'data' => [
                    'ihbarlar' => $ihbarList,
                    'ozet' => $ozet,
                    'baslangic' => $baslangicTarihi,
                    'bitis' => $bitisTarihi,
                    'ilceler' => $ilceler,
                    'personel_adaylari' => $personelAdaylari,
                    'can_edit' => $canEdit,
                ],
            ]);
            break;

        case 'detail':
            $token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
            $id = resolveIhbarId($token);
            if ($id <= 0) {
                ihbarApiResponse(['success' => false, 'message' => 'Geçersiz ihbar parametresi.'], 400);
            }

            $ihbar = $IhbarModel->getById($id);
            if (!$ihbar) {
                ihbarApiResponse(['success' => false, 'message' => 'İhbar kaydı bulunamadı.'], 404);
            }

            $fotograflar = $IhbarModel->getFotograflar($id);
            $fotoList = array_map(static function ($f): array {
                return [
                    'token' => Security::encrypt((string) $f->id),
                    'medya_tipi' => (string) ($f->medya_tipi ?? 'foto'),
                    'sure_saniye' => $f->sure_saniye ? (int) $f->sure_saniye : null,
                    'kucuk_var' => !empty($f->kucuk_var),
                    'created_at' => (string) ($f->created_at ?? ''),
                ];
            }, $fotograflar);

            $tarihce = $IhbarModel->getTarihce($id);
            $tarihceList = array_map(static function ($t): array {
                return [
                    'id' => (int) $t->id,
                    'tip' => (string) ($t->tip ?? ''),
                    'aciklama' => (string) ($t->aciklama ?? ''),
                    'ekleyen_tip' => (string) ($t->ekleyen_tip ?? ''),
                    'ekleyen_adi' => (string) ($t->ekleyen_adi ?? 'Sistem'),
                    'tarih' => !empty($t->created_at) ? date('d.m.Y H:i', strtotime((string) $t->created_at)) : '-',
                ];
            }, $tarihce);

            $atananPersonelIds = $IhbarModel->getAtananPersonelIds($id);
            $atananTokenlar = array_map(static fn($pid) => Security::encrypt((string) $pid), $atananPersonelIds);

            $durumEtiketleri = [
                'yeni' => 'Yeni',
                'yonlendirildi' => 'Yönlendirildi',
                'islemde' => 'İşlemde',
                'olumlu' => 'Olumlu',
                'olumsuz' => 'Olumsuz',
            ];

            ihbarApiResponse([
                'success' => true,
                'data' => [
                    'token' => Security::encrypt((string) $ihbar->id),
                    'durum' => (string) ($ihbar->durum ?? 'yeni'),
                    'durum_etiketi' => $durumEtiketleri[$ihbar->durum ?? 'yeni'] ?? $ihbar->durum,
                    'ilce' => (string) ($ihbar->ilce ?? ''),
                    'mahalle' => (string) ($ihbar->mahalle ?? ''),
                    'telefon' => (string) ($ihbar->telefon ?? ''),
                    'komsu_abone_no' => (string) ($ihbar->komsu_abone_no ?? ''),
                    'aciklama' => (string) ($ihbar->aciklama ?? ''),
                    'ekip' => (string) ($ihbar->atanan_ekip_adi ?: 'Henüz yönlendirilmedi'),
                    'atanan_personel_tokenlari' => $atananTokenlar,
                    'bildiren' => (string) ($ihbar->bildiren_personel_adi ?: ($ihbar->olusturan_user_adi ?: 'Belirtilmedi')),
                    'tarih' => !empty($ihbar->created_at) ? date('d.m.Y H:i', strtotime((string) $ihbar->created_at)) : '-',
                    'created_at' => (string) ($ihbar->created_at ?? ''),
                    'tutanak_no' => (string) ($ihbar->tutanak_no ?? ''),
                    'sebep' => (string) ($ihbar->olumsuz_sebep ?? ''),
                    'lat' => is_numeric($ihbar->konum_lat) ? (float) $ihbar->konum_lat : null,
                    'lng' => is_numeric($ihbar->konum_lng) ? (float) $ihbar->konum_lng : null,
                    'konum_link' => (string) ($ihbar->konum_link ?? ''),
                    'fotograflar' => $fotoList,
                    'tarihce' => $tarihceList,
                ],
            ]);
            break;

        case 'foto':
            $fotoToken = (string) ($_GET['token'] ?? '');
            $fotoId = resolveIhbarId($fotoToken);
            if ($fotoId <= 0) {
                http_response_code(404);
                exit;
            }

            $stmt = $IhbarModel->getDb()->prepare(
                "SELECT f.dosya_yolu, f.kucuk_yol
                 FROM ihbar_fotograflari f
                 INNER JOIN ihbarlar i ON i.id = f.ihbar_id
                 WHERE f.id = ? AND i.firma_id = ? AND i.silinme_tarihi IS NULL"
            );
            $stmt->execute([$fotoId, $firmaId]);
            $satir = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $dosyaYolu = (string) ($satir['dosya_yolu'] ?? '');

            if ($dosyaYolu !== '' && ($_GET['boyut'] ?? '') === 'kucuk' && !empty($satir['kucuk_yol'])) {
                $kucukTam = realpath(dirname(__DIR__, 3) . '/' . ltrim((string) $satir['kucuk_yol'], '/'));
                if ($kucukTam && is_file($kucukTam)) {
                    $dosyaYolu = (string) $satir['kucuk_yol'];
                }
            }

            $uploadRoot = realpath(dirname(__DIR__, 3) . '/uploads/ihbar');
            $dosya = $dosyaYolu !== '' ? realpath(dirname(__DIR__, 3) . '/' . ltrim($dosyaYolu, '/')) : false;

            if (!$uploadRoot || !$dosya || strpos($dosya, $uploadRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($dosya)) {
                http_response_code(404);
                exit;
            }

            header_remove('Content-Type');
            MedyaServis::gonder($dosya, 86400);
            exit;

        case 'stats':
            $tarihDogrula = static function ($deger, string $varsayilan): string {
                $deger = (string) $deger;
                $tarih = DateTime::createFromFormat('Y-m-d', $deger);
                return $tarih && $tarih->format('Y-m-d') === $deger ? $deger : $varsayilan;
            };

            $bitisTarihi = $tarihDogrula($_GET['bitis'] ?? '', date('Y-m-d'));
            $baslangicTarihi = $tarihDogrula($_GET['baslangic'] ?? '', date('Y-m-d', strtotime('-1 month')));
            if ($baslangicTarihi > $bitisTarihi) {
                [$baslangicTarihi, $bitisTarihi] = [$bitisTarihi, $baslangicTarihi];
            }

            $personelIhbarStats = $IhbarModel->getPersonelIhbarIstatistikleri($baslangicTarihi, $bitisTarihi);

            $enBasariliPersonel = null;
            $enYuksekBasariOrani = -1;
            $sahaIhbarToplam = 0;
            $statsList = [];

            foreach ($personelIhbarStats as $pStat) {
                $toplam = (int) $pStat->toplam_ihbar;
                $olumlu = (int) $pStat->olumlu_sayisi;
                $olumsuz = (int) $pStat->olumsuz_sayisi;
                $bekleyen = (int) $pStat->bekleyen_sayisi;
                $sonuclanan = $olumlu + $olumsuz;
                $oran = $sonuclanan > 0 ? (int) round(($olumlu / $sonuclanan) * 100) : 0;
                $sahaIhbarToplam += $toplam;

                if ($sonuclanan > 0 && $oran > $enYuksekBasariOrani) {
                    $enYuksekBasariOrani = $oran;
                    $enBasariliPersonel = [
                        'ad' => (string) $pStat->adi_soyadi,
                        'oran' => $oran,
                        'olumlu' => $olumlu,
                        'sonuclanan' => $sonuclanan,
                    ];
                }

                $statsList[] = [
                    'ad' => (string) $pStat->adi_soyadi,
                    'toplam' => $toplam,
                    'olumlu' => $olumlu,
                    'olumsuz' => $olumsuz,
                    'bekleyen' => $bekleyen,
                    'sonuclanan' => $sonuclanan,
                    'oran' => $oran,
                    'son_ihbar_tarihi' => !empty($pStat->son_ihbar_tarihi) ? date('d.m.Y H:i', strtotime((string) $pStat->son_ihbar_tarihi)) : '-',
                ];
            }

            $enCokGonderen = !empty($personelIhbarStats[0]) ? [
                'ad' => (string) $personelIhbarStats[0]->adi_soyadi,
                'toplam' => (int) $personelIhbarStats[0]->toplam_ihbar,
            ] : null;

            ihbarApiResponse([
                'success' => true,
                'data' => [
                    'toplam_personel' => count($statsList),
                    'saha_ihbar_toplam' => $sahaIhbarToplam,
                    'en_cok_gonderen' => $enCokGonderen,
                    'en_basarili' => $enBasariliPersonel,
                    'personel_stats' => $statsList,
                    'baslangic' => $baslangicTarihi,
                    'bitis' => $bitisTarihi,
                ],
            ]);
            break;

        case 'assign':
            requireCsrfToken();
            checkIhbarEditAuth();

            $token = (string) ($_POST['token'] ?? '');
            $id = resolveIhbarId($token);
            if ($id <= 0) {
                ihbarApiResponse(['success' => false, 'message' => 'Geçersiz ihbar parametresi.'], 400);
            }

            $personelTokens = $_POST['personel_tokens'] ?? [];
            if (!is_array($personelTokens)) {
                $personelTokens = [$personelTokens];
            }

            $personelIds = [];
            foreach ($personelTokens as $pToken) {
                $pid = resolveIhbarId((string) $pToken);
                if ($pid > 0) {
                    $personelIds[] = $pid;
                }
            }

            if (empty($personelIds)) {
                ihbarApiResponse(['success' => false, 'message' => 'Lütfen en az bir personel seçin.'], 400);
            }
            if (count($personelIds) > 2) {
                ihbarApiResponse(['success' => false, 'message' => 'En fazla iki personel seçebilirsiniz.'], 400);
            }

            $IhbarModel->assignTeam($id, $personelIds, $currentUserId);

            // Push bildirimi
            try {
                $pushService = new PushNotificationService();
                foreach ($personelIds as $pId) {
                    $pushService->sendToPersonel($pId, [
                        'title' => '📣 Yeni İhbar Yönlendirildi',
                        'body' => 'Size yeni bir ihbar yönlendirildi. Detayları görmek için uygulamayı açın.',
                        'url' => '?page=ihbar&ihbar_id=' . $id . '&tip=gelen',
                    ]);
                }
            } catch (Throwable $e) {
                error_log('SPA İhbar yönlendirme push hatası: ' . $e->getMessage());
            }

            ihbarApiResponse(['success' => true, 'message' => 'İhbar başarıyla ekibe yönlendirildi.']);
            break;

        case 'addNote':
            requireCsrfToken();
            checkIhbarEditAuth();

            $token = (string) ($_POST['token'] ?? '');
            $id = resolveIhbarId($token);
            if ($id <= 0) {
                ihbarApiResponse(['success' => false, 'message' => 'Geçersiz ihbar parametresi.'], 400);
            }

            $aciklama = trim((string) ($_POST['aciklama'] ?? ''));
            if ($aciklama === '') {
                ihbarApiResponse(['success' => false, 'message' => 'İşlem notu boş bırakılamaz.'], 400);
            }

            $IhbarModel->addNote($id, $aciklama, 'user', $currentUserId);
            ihbarApiResponse(['success' => true, 'message' => 'İşlem notu eklendi.']);
            break;

        case 'close':
            requireCsrfToken();
            checkIhbarEditAuth();

            $token = (string) ($_POST['token'] ?? '');
            $id = resolveIhbarId($token);
            if ($id <= 0) {
                ihbarApiResponse(['success' => false, 'message' => 'Geçersiz ihbar parametresi.'], 400);
            }

            $durum = (string) ($_POST['durum'] ?? '');
            if (!in_array($durum, ['olumlu', 'olumsuz'], true)) {
                ihbarApiResponse(['success' => false, 'message' => 'Geçerli bir sonuç durumu (Olumlu / Olumsuz) seçin.'], 400);
            }

            $tutanakNo = trim((string) ($_POST['tutanak_no'] ?? '')) ?: null;
            $sebep = trim((string) ($_POST['sebep'] ?? '')) ?: null;

            if ($durum === 'olumsuz' && empty($sebep)) {
                ihbarApiResponse(['success' => false, 'message' => 'Olumsuz sonuç için lütfen gerekçe belirtin.'], 400);
            }

            $IhbarModel->closeSonuc($id, $durum, $tutanakNo, $sebep, 'user', $currentUserId);
            ihbarApiResponse(['success' => true, 'message' => 'İhbar başarıyla sonuçlandırıldı.']);
            break;

        case 'cancelResult':
            requireCsrfToken();
            checkIhbarEditAuth();

            $token = (string) ($_POST['token'] ?? '');
            $id = resolveIhbarId($token);
            if ($id <= 0) {
                ihbarApiResponse(['success' => false, 'message' => 'Geçersiz ihbar parametresi.'], 400);
            }

            $IhbarModel->cancelResult($id, $currentUserId);
            ihbarApiResponse(['success' => true, 'message' => 'İhbar sonucu iptal edildi ve kayıt yeniden işleme alındı.']);
            break;

        case 'create':
            requireCsrfToken();
            checkIhbarEditAuth();

            $ilce = trim((string) ($_POST['ilce'] ?? ''));
            if ($ilce === '') {
                ihbarApiResponse(['success' => false, 'message' => 'İlçe seçimi zorunludur.'], 400);
            }

            $mahalle = trim((string) ($_POST['mahalle'] ?? ''));
            if ($mahalle === '') {
                ihbarApiResponse(['success' => false, 'message' => 'Mahalle alanı zorunludur.'], 400);
            }

            $aciklama = trim((string) ($_POST['aciklama'] ?? ''));
            if ($aciklama === '') {
                ihbarApiResponse(['success' => false, 'message' => 'Açıklama zorunludur.'], 400);
            }

            $konumLink = trim((string) ($_POST['konum_link'] ?? ''));
            if ($konumLink !== '' && !filter_var($konumLink, FILTER_VALIDATE_URL)) {
                $konumLink = '';
            }

            $lat = is_numeric($_POST['konum_lat'] ?? null) ? (float) $_POST['konum_lat'] : null;
            $lng = is_numeric($_POST['konum_lng'] ?? null) ? (float) $_POST['konum_lng'] : null;
            $dogruluk = is_numeric($_POST['konum_dogruluk'] ?? null) ? (float) $_POST['konum_dogruluk'] : null;

            if ($konumLink === '' && ($lat === null || $lng === null)) {
                ihbarApiResponse(['success' => false, 'message' => 'Konum bilgisi (harita linki veya GPS konumu) zorunludur.'], 400);
            }

            $ihbarId = $IhbarModel->create([
                'ilce' => $ilce,
                'mahalle' => $mahalle,
                'telefon' => trim((string) ($_POST['telefon'] ?? '')) ?: null,
                'komsu_abone_no' => trim((string) ($_POST['komsu_abone_no'] ?? '')) ?: null,
                'aciklama' => $aciklama,
                'konum_link' => $konumLink ?: null,
                'konum_lat' => $lat,
                'konum_lng' => $lng,
                'konum_dogruluk' => $dogruluk,
                'olusturan_user_id' => $currentUserId,
            ]);

            // Fotoğraf yükleme
            if (!empty($_FILES['fotograflar']) && !empty($_FILES['fotograflar']['name'][0])) {
                $maxFiles = min(count($_FILES['fotograflar']['name']), IhbarModel::MAX_FOTO);
                for ($i = 0; $i < $maxFiles; $i++) {
                    if (($_FILES['fotograflar']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    try {
                        $sonuc = IhbarModel::storeUploadedFoto([
                            'name' => $_FILES['fotograflar']['name'][$i],
                            'tmp_name' => $_FILES['fotograflar']['tmp_name'][$i],
                            'error' => $_FILES['fotograflar']['error'][$i],
                            'size' => $_FILES['fotograflar']['size'][$i],
                        ], $ihbarId);

                        $IhbarModel->addFotograf($ihbarId, $sonuc['yol'], $sonuc['kucuk']);
                    } catch (Throwable $e) {
                        error_log('SPA İhbar fotoğrafı yüklenemedi (' . $ihbarId . '): ' . $e->getMessage());
                    }
                }
            }

            // Sorumlulara bildirim
            try {
                $bildirimModel = new BildirimModel();
                $sorumlular = (new \App\Model\UserRoleAssignmentModel())->activeUsersWithPermission('ihbar/list');
                $ozetMetin = "Yeni bir ihbar kaydı oluşturuldu: {$ilce} / {$mahalle}";
                $hedefAdres = 'index.php?p=ihbar/list&ihbar_id=' . $ihbarId;
                foreach ($sorumlular as $kullanici) {
                    $bildirimModel->createNotification(
                        (int) $kullanici->id,
                        '📣 Yeni İhbar',
                        $ozetMetin,
                        $hedefAdres,
                        'alert-triangle',
                        'danger',
                        UserNotificationPreferenceModel::TYPE_IHBAR_CREATED
                    );
                }
            } catch (Throwable $e) {
                error_log('SPA İhbar bildirim hatası: ' . $e->getMessage());
            }

            ihbarApiResponse([
                'success' => true,
                'message' => 'İhbar başarıyla kaydedildi.',
                'data' => [
                    'id' => $ihbarId,
                    'token' => Security::encrypt((string) $ihbarId),
                ],
            ]);
            break;

        default:
            ihbarApiResponse(['success' => false, 'message' => 'Bilinmeyen işlem türü.'], 400);
    }
} catch (Throwable $e) {
    error_log('mobile-spa ihbar.php exception: ' . $e->getMessage());
    ihbarApiResponse(['success' => false, 'message' => 'İşlem sırasında bir hata oluştu: ' . $e->getMessage()], 500);
}
