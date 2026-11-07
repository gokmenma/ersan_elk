<?php

declare(strict_types=1);

use App\Helper\Security;
use App\Model\IkAdayBildirimModel;
use App\Model\IkBasvuruModel;
use App\Model\IkIlanModel;
use App\Model\IkMulakatModel;
use App\Model\PermissionPolicyModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use App\Service\IkAdayBildirimService;
use App\Service\IkBasvuruAiService;
use App\Service\IkBasvuruDonusturService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function ikResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ikDecryptId(string $encrypted): int
{
    try {
        return (int) Security::decrypt($encrypted);
    } catch (Throwable) {
        return 0;
    }
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user']) || empty($_SESSION['firma_id'])) {
    ikResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.'], 401);
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'applications');
$resource = 'mobile-spa/ik-ise-alim';

if ((new PermissionPolicyModel())->isReady()) {
    Gate::authorizeApiPolicy($resource, $action);
} else {
    $legacyMap = [
        'stats' => 'ik_ise_alim_dashboard',
        'applications' => 'ik_basvuru_listesi',
        'application-detail' => 'ik_basvuru_listesi',
        'update-status' => 'ik_basvuru_listesi',
        'save-note' => 'ik_basvuru_listesi',
        'rerun-ai' => 'ik_basvuru_listesi',
        'cv-view' => 'ik_basvuru_listesi',
        'photo-view' => 'ik_basvuru_listesi',
        'jobs' => 'ik_ilan_yonetimi',
        'job-status' => 'ik_ilan_yonetimi',
        'job-link' => 'ik_ilan_yonetimi',
        'save-interview' => 'ik_basvuru_mulakat',
        'delete-interview' => 'ik_basvuru_mulakat',
        'prepare-notification' => 'ik_basvuru_aday_bildirim',
        'send-notification' => 'ik_basvuru_aday_bildirim',
        'convert-to-personnel' => 'ik_basvuru_personele_donustur',
    ];
    $required = $legacyMap[$action] ?? null;
    if ($required && !Gate::allows($required) && !Gate::isSuperAdmin()) {
        ikResponse(['success' => false, 'message' => 'Bu işlem için yetkiniz bulunmuyor.'], 403);
    }
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'POST') {
    $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
    $known = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sent === '' || $known === '' || !hash_equals($known, $sent)) {
        ikResponse(['success' => false, 'message' => 'Güvenlik doğrulamasının süresi doldu. Lütfen sayfayı yenileyin.'], 419);
    }
}

$firmaId = (int) $_SESSION['firma_id'];
$userId = (int) $_SESSION['user_id'];

$Basvuru = new IkBasvuruModel();
$Ilan = new IkIlanModel();
$Mulakat = new IkMulakatModel();
$AdayBildirim = new IkAdayBildirimModel();
$Log = new SystemLogModel();

$jsonAlan = static function ($deger): array {
    $dizi = json_decode((string) $deger, true);
    return is_array($dizi) ? $dizi : [];
};

try {
    // 1. Dashboard / İstatistik Verileri
    if ($action === 'stats') {
        $gun = max(7, min(365, (int) ($_GET['gun'] ?? 30)));
        $stats = $Basvuru->dashboardStats($firmaId, $gun);
        $summary = $Basvuru->summary($firmaId);

        $db = $Basvuru->getDb();
        $st = $db->prepare("SELECT durum, COUNT(*) as cnt FROM ik_basvurular WHERE firma_id = :fid AND is_active = 1 AND deleted_at IS NULL GROUP BY durum");
        $st->execute(['fid' => $firmaId]);
        $durumlar = $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        ikResponse([
            'success' => true,
            'stats' => array_merge($stats, [
                'toplam_basvuru' => (int) ($summary->toplam ?? 0),
                'ai_ortalama_puan' => (float) ($summary->ortalama_puan ?? 0),
                'ai_olumlu_sayi' => (int) ($summary->ai_olumlu ?? 0),
                'durum_dagilim' => $durumlar,
            ]),
            'summary' => [
                'toplam' => (int) ($summary->toplam ?? 0),
                'yeni' => (int) ($durumlar['yeni'] ?? 0),
                'degerlendirildi' => (int) ($durumlar['degerlendirildi'] ?? 0),
                'gorusme' => (int) ($durumlar['gorusme'] ?? 0),
                'olumlu' => (int) ($durumlar['olumlu'] ?? 0),
                'olumsuz' => (int) ($durumlar['olumsuz'] ?? 0),
                'personele_donustu' => (int) ($durumlar['personele_donustu'] ?? 0),
            ],
        ]);
    }

    // 2. Başvuru Listesi
    if ($action === 'applications') {
        $durum = (string) ($_GET['status'] ?? 'all');
        $ilanId = ($_GET['job_id'] ?? '') !== '' ? ikDecryptId((string) $_GET['job_id']) : 0;
        $arama = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 80);
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
        $sayfa = max(1, (int) ($_GET['page'] ?? 1));

        $postParams = [
            'draw' => 0,
            'start' => ($sayfa - 1) * $limit,
            'length' => $limit,
            'search' => ['value' => $arama],
            'durum_filtre' => $durum,
            'ilan_filtre' => $ilanId,
            'order' => [
                ['column' => 0, 'dir' => 'desc']
            ]
        ];

        $sonuc = $Basvuru->ajaxList($postParams, $firmaId);
        $ozet = $Basvuru->summary($firmaId);

        $db = $Basvuru->getDb();
        $st = $db->prepare("SELECT durum, COUNT(*) as cnt FROM ik_basvurular WHERE firma_id = :fid AND is_active = 1 AND deleted_at IS NULL GROUP BY durum");
        $st->execute(['fid' => $firmaId]);
        $durumlar = $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $items = array_map(static function (object $b): array {
            return [
                'id' => (string) $b->enc_id,
                'name' => (string) $b->ad_soyad,
                'phone' => (string) ($b->telefon ?? ''),
                'email' => (string) ($b->email ?? ''),
                'city' => (string) ($b->sehir ?? ''),
                'education' => (string) ($b->egitim ?? ''),
                'experience_years' => (float) ($b->deneyim_yili ?? 0),
                'job_title' => (string) ($b->ilan_baslik ?? ''),
                'status' => (string) $b->durum,
                'status_label' => IkBasvuruModel::DURUMLAR[$b->durum] ?? $b->durum,
                'ai_status' => (string) ($b->ai_durum ?? 'beklemede'),
                'ai_score' => $b->ai_puan !== null ? (int) $b->ai_puan : null,
                'ai_label' => (string) ($b->ai_etiket ?? ''),
                'ai_summary' => (string) ($b->ai_ozet ?? ''),
                'has_cv' => !empty($b->cv_dosya),
                'has_photo' => !empty($b->foto_dosya),
                'personnel_id' => !empty($b->personel_id) ? Security::encrypt((int) $b->personel_id) : null,
                'created_at' => (string) $b->created_at,
            ];
        }, $sonuc['data']);

        ikResponse([
            'success' => true,
            'items' => $items,
            'total' => (int) $sonuc['recordsFiltered'],
            'has_more' => ($sayfa * $limit) < (int) $sonuc['recordsFiltered'],
            'summary' => [
                'toplam' => (int) ($ozet->toplam ?? 0),
                'yeni' => (int) ($durumlar['yeni'] ?? 0),
                'degerlendirildi' => (int) ($durumlar['degerlendirildi'] ?? 0),
                'gorusme' => (int) ($durumlar['gorusme'] ?? 0),
                'olumlu' => (int) ($durumlar['olumlu'] ?? 0),
                'olumsuz' => (int) ($durumlar['olumsuz'] ?? 0),
                'personele_donustu' => (int) ($durumlar['personele_donustu'] ?? 0),
            ],
        ]);
    }

    // 3. Başvuru Detayı
    if ($action === 'application-detail') {
        $id = ikDecryptId((string) ($_GET['id'] ?? ''));
        $b = $Basvuru->bul($id, $firmaId);
        if (!$b) {
            ikResponse(['success' => false, 'message' => 'Başvuru bulunamadı.'], 404);
        }

        $olaylar = $Basvuru->olaylar((int) $b->id, $firmaId);
        $guncel = $Mulakat->guncelDegerler((int) $b->id, $firmaId);

        $mulakatYetki = Gate::allows('ik_basvuru_mulakat') || Gate::isSuperAdmin();
        $mulakatlar = [];
        if ($mulakatYetki) {
            foreach ($Mulakat->listele((int) $b->id, $firmaId) as $m) {
                $mulakatlar[] = [
                    'id' => Security::encrypt((int) $m->id),
                    'mulakat_tarihi' => (string) $m->mulakat_tarihi,
                    'tur' => (string) $m->tur,
                    'tur_label' => IkMulakatModel::TURLER[$m->tur] ?? $m->tur,
                    'sonuc' => (string) $m->sonuc,
                    'sonuc_label' => IkMulakatModel::SONUCLAR[$m->sonuc] ?? $m->sonuc,
                    'genel_puan' => $m->genel_puan !== null ? (int) $m->genel_puan : null,
                    'genel_not' => (string) ($m->genel_not ?? ''),
                    'sorular' => $jsonAlan($m->sorular_cevaplar),
                    'yapan' => (string) ($m->yapan ?? ''),
                ];
            }
        }

        $bildirimYetki = Gate::allows('ik_basvuru_aday_bildirim') || Gate::isSuperAdmin();
        $bildirimler = [];
        if ($bildirimYetki) {
            foreach ($AdayBildirim->listele((int) $b->id, $firmaId) as $bl) {
                $bildirimler[] = [
                    'kanal' => (string) $bl->kanal,
                    'sablon' => (string) ($bl->sablon ?? ''),
                    'sablon_label' => IkAdayBildirimService::SABLONLAR[$bl->sablon] ?? $bl->sablon,
                    'durum' => (string) $bl->durum,
                    'created_at' => (string) $bl->created_at,
                    'kullanici' => (string) ($bl->kullanici ?? ''),
                ];
            }
        }

        ikResponse([
            'success' => true,
            'application' => [
                'id' => Security::encrypt((int) $b->id),
                'name' => (string) $b->ad_soyad,
                'phone' => (string) ($b->telefon ?? ''),
                'email' => (string) ($b->email ?? ''),
                'birth_date' => (string) ($b->dogum_tarihi ?? ''),
                'city' => (string) ($b->sehir ?? ''),
                'education' => (string) ($b->egitim ?? ''),
                'experience_years' => (float) ($b->deneyim_yili ?? 0),
                'experience_summary' => (string) ($b->deneyim_ozet ?? ''),
                'driving_license' => (string) ($b->ehliyet ?? ''),
                'salary_expectation' => $b->maas_beklentisi !== null ? (float) $b->maas_beklentisi : null,
                'start_date_type' => (string) ($b->ise_baslama ?? ''),
                'answers' => $jsonAlan($b->cevaplar),
                'has_cv' => !empty($b->cv_dosya),
                'has_photo' => !empty($b->foto_dosya),
                'job_title' => (string) ($b->ilan_baslik ?? ''),
                'job_department' => (string) ($b->ilan_departman ?? ''),
                'status' => (string) $b->durum,
                'status_label' => IkBasvuruModel::DURUMLAR[$b->durum] ?? $b->durum,
                'ai_status' => (string) ($b->ai_durum ?? 'beklemede'),
                'ai_score' => $b->ai_puan !== null ? (int) $b->ai_puan : null,
                'ai_label' => (string) ($b->ai_etiket ?? ''),
                'ai_summary' => (string) ($b->ai_ozet ?? ''),
                'ai_strengths' => $jsonAlan($b->ai_guclu),
                'ai_weaknesses' => $jsonAlan($b->ai_zayif),
                'ai_risks' => $jsonAlan($b->ai_riskler),
                'ai_interview_questions' => $jsonAlan($b->ai_mulakat_sorulari),
                'ai_date' => (string) ($b->ai_tarihi ?? ''),
                'ik_note' => (string) ($b->ik_notu ?? ''),
                'personnel_id' => !empty($b->personel_id) ? Security::encrypt((int) $b->personel_id) : null,
                'created_at' => (string) $b->created_at,
                'current_values' => $guncel,
                'timeline' => array_map(static fn($o) => [
                    'title' => (string) $o->olay,
                    'description' => (string) ($o->aciklama ?? ''),
                    'user' => (string) ($o->kullanici ?? 'Sistem'),
                    'date' => (string) $o->created_at,
                ], $olaylar),
                'interviews' => $mulakatlar,
                'notifications' => $bildirimler,
                'permissions' => [
                    'can_interview' => $mulakatYetki,
                    'can_notify' => $bildirimYetki,
                    'can_convert' => Gate::allows('ik_basvuru_personele_donustur') || Gate::isSuperAdmin(),
                ],
            ],
        ]);
    }

    // 4. Durum Güncelleme
    if ($action === 'update-status') {
        $id = ikDecryptId((string) ($_POST['id'] ?? ''));
        $durum = (string) ($_POST['status'] ?? '');
        $mevcut = $Basvuru->bul($id, $firmaId);
        if (!$mevcut) {
            ikResponse(['success' => false, 'message' => 'Başvuru bulunamadı.'], 404);
        }
        if ($mevcut->durum === 'personele_donustu') {
            ikResponse(['success' => false, 'message' => 'Personele dönüşmüş başvurunun durumu değiştirilemez.']);
        }
        if (!isset(IkBasvuruModel::DURUMLAR[$durum])) {
            ikResponse(['success' => false, 'message' => 'Geçersiz başvuru durumu.']);
        }
        if ($mevcut->durum === $durum) {
            ikResponse(['success' => true, 'message' => 'Başvuru zaten bu durumda.']);
        }
        if (!$Basvuru->durumGuncelle($id, $firmaId, $durum)) {
            ikResponse(['success' => false, 'message' => 'Durum güncellenemedi.']);
        }
        $Basvuru->olayEkle($firmaId, $id, $userId, 'Durum değişti', IkBasvuruModel::DURUMLAR[$durum] ?? $durum);
        $Log->logAction($userId, 'İşe Alım Başvurusu', 'Mobil: Başvuru durumu değişti (ID: ' . $id . ') → ' . $durum, SystemLogModel::LEVEL_INFO);
        ikResponse(['success' => true, 'message' => 'Durum güncellendi.']);
    }

    // 5. İK Notu Kaydetme
    if ($action === 'save-note') {
        $id = ikDecryptId((string) ($_POST['id'] ?? ''));
        if (!$Basvuru->bul($id, $firmaId)) {
            ikResponse(['success' => false, 'message' => 'Başvuru bulunamadı.'], 404);
        }
        $not = trim(mb_substr(str_replace("\r", '', (string) ($_POST['note'] ?? '')), 0, 3000, 'UTF-8'));
        $Basvuru->notKaydet($id, $firmaId, $not);
        $Basvuru->olayEkle($firmaId, $id, $userId, 'İK notu güncellendi');
        ikResponse(['success' => true, 'message' => 'Not kaydedildi.']);
    }

    // 6. AI Yeniden Değerlendirme
    if ($action === 'rerun-ai') {
        $id = ikDecryptId((string) ($_POST['id'] ?? ''));
        $b = $Basvuru->bul($id, $firmaId);
        if (!$b) {
            ikResponse(['success' => false, 'message' => 'Başvuru bulunamadı.'], 404);
        }
        $ilan = $Ilan->bul((int) $b->ilan_id, $firmaId);
        if (!$ilan) {
            ikResponse(['success' => false, 'message' => 'İlan bulunamadı.'], 404);
        }
        set_time_limit(180);
        $b = $Mulakat->guncelUygula($b, $Mulakat->guncelDegerler((int) $b->id, $firmaId));
        try {
            $sonuc = (new IkBasvuruAiService())->degerlendir($ilan, [
                'sehir' => $b->sehir,
                'egitim' => $b->egitim,
                'deneyim_yili' => $b->deneyim_yili,
                'deneyim_ozet' => $b->deneyim_ozet,
                'ehliyet' => $b->ehliyet,
                'maas_beklentisi' => $b->maas_beklentisi,
                'ise_baslama' => $b->ise_baslama,
                'cevaplar' => $jsonAlan($b->cevaplar),
                'cv_metin' => (string) $b->cv_metin,
            ], $firmaId);
        } catch (Throwable $e) {
            error_log('Mobil İK AI yeniden değerlendirme hatası: ' . $e->getMessage());
            $Basvuru->aiHataKaydet($id);
            ikResponse(['success' => false, 'message' => 'Yapay zekâ değerlendirmesi şu anda tamamlanamadı.']);
        }
        $Basvuru->aiSonucKaydet($id, $sonuc);
        $Basvuru->olayEkle($firmaId, $id, $userId, 'Yapay zekâ yeniden değerlendirdi', 'Puan: ' . $sonuc['puan']);
        $Log->logAction($userId, 'İşe Alım Başvurusu', 'Mobil: Yapay zekâ değerlendirmesi yenilendi (ID: ' . $id . ')', SystemLogModel::LEVEL_INFO);
        ikResponse(['success' => true, 'message' => 'Değerlendirme yenilendi.']);
    }

    // 7. İlanlar Listesi
    if ($action === 'jobs') {
        $db = $Ilan->getDb();
        $stmt = $db->prepare("SELECT i.*, COUNT(b.id) AS basvuru_sayisi
            FROM ik_ilanlar i
            LEFT JOIN ik_basvurular b ON b.ilan_id = i.id AND b.is_active = 1 AND b.deleted_at IS NULL
            WHERE i.firma_id = :firma_id AND i.is_active = 1 AND i.deleted_at IS NULL
            GROUP BY i.id
            ORDER BY i.created_at DESC");
        $stmt->execute(['firma_id' => $firmaId]);
        $ilanlar = $stmt->fetchAll(PDO::FETCH_OBJ);

        $ozet = $Ilan->summary($firmaId);
        $items = array_map(static function ($i) use ($Ilan): array {
            $link = IkIlanModel::linkOlustur($Ilan->tokenGetir($i));
            return [
                'id' => Security::encrypt((int) $i->id),
                'title' => (string) $i->baslik,
                'department' => (string) ($i->departman ?? ''),
                'duty' => (string) ($i->gorev ?? ''),
                'location' => (string) ($i->calisma_yeri ?? ''),
                'description' => (string) ($i->aciklama ?? ''),
                'requirements' => (string) ($i->aranan_nitelikler ?? ''),
                'ai_criteria' => (string) ($i->ai_kriterleri ?? ''),
                'status' => (string) $i->durum,
                'status_label' => IkIlanModel::DURUMLAR[$i->durum] ?? $i->durum,
                'application_count' => (int) ($i->basvuru_sayisi ?? 0),
                'share_link' => $link,
                'expires_at' => !empty($i->gecerlilik_tarihi) ? substr((string) $i->gecerlilik_tarihi, 0, 10) : '',
                'created_at' => (string) $i->created_at,
            ];
        }, $ilanlar);

        ikResponse([
            'success' => true,
            'items' => $items,
            'summary' => [
                'toplam' => (int) ($ozet->toplam ?? 0),
                'yayinda' => (int) ($ozet->yayinda ?? 0),
                'taslak' => (int) ($ozet->taslak ?? 0),
                'kapali' => (int) ($ozet->kapali ?? 0),
            ],
        ]);
    }

    // 7.1. İlan Detay (Düzenleme İçin)
    if ($action === 'job-detail') {
        $id = ikDecryptId((string) ($_GET['id'] ?? ''));
        $ilan = $Ilan->bul($id, $firmaId);
        if (!$ilan) {
            ikResponse(['success' => false, 'message' => 'İlan bulunamadı.'], 404);
        }
        $sorular = $jsonAlan($ilan->sorular);
        ikResponse([
            'success' => true,
            'job' => [
                'id' => Security::encrypt((int) $ilan->id),
                'title' => (string) $ilan->baslik,
                'department' => (string) ($ilan->departman ?? ''),
                'duty' => (string) ($ilan->gorev ?? ''),
                'location' => (string) ($ilan->calisma_yeri ?? ''),
                'description' => (string) ($ilan->aciklama ?? ''),
                'requirements' => (string) ($ilan->aranan_nitelikler ?? ''),
                'ai_criteria' => (string) ($ilan->ai_kriterleri ?? ''),
                'questions' => implode("\n", $sorular),
                'status' => (string) $ilan->durum,
                'status_label' => IkIlanModel::DURUMLAR[$ilan->durum] ?? $ilan->durum,
                'expires_at' => !empty($ilan->gecerlilik_tarihi) ? substr((string) $ilan->gecerlilik_tarihi, 0, 10) : '',
            ],
        ]);
    }

    // 7.2. İlan Kaydet / Güncelle
    if ($action === 'save-job') {
        $id = ($_POST['id'] ?? '') !== '' ? ikDecryptId((string) $_POST['id']) : 0;
        if ($id > 0 && !$Ilan->bul($id, $firmaId)) {
            ikResponse(['success' => false, 'message' => 'İlan bulunamadı.'], 404);
        }
        $baslik = trim(mb_substr((string) ($_POST['title'] ?? $_POST['baslik'] ?? ''), 0, 255, 'UTF-8'));
        if ($baslik === '') {
            ikResponse(['success' => false, 'message' => 'İlan başlığı zorunludur.']);
        }
        $durum = (string) ($_POST['status'] ?? $_POST['durum'] ?? 'taslak');
        if (!isset(IkIlanModel::DURUMLAR[$durum])) {
            $durum = 'taslak';
        }
        $gecerlilik = trim((string) ($_POST['expires_at'] ?? $_POST['gecerlilik_tarihi'] ?? ''));
        if ($gecerlilik !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $gecerlilik);
            if (!$d || $d->format('Y-m-d') !== $gecerlilik) {
                ikResponse(['success' => false, 'message' => 'Geçerlilik tarihi hatalı (YYYY-AA-GG).']);
            }
            $gecerlilik .= ' 23:59:59';
        } else {
            $gecerlilik = null;
        }

        $sorularRaw = (string) ($_POST['questions'] ?? $_POST['sorular'] ?? '');
        $sorular = [];
        foreach (explode("\n", str_replace("\r", '', $sorularRaw)) as $satir) {
            $satir = trim($satir);
            if ($satir !== '') {
                $sorular[] = mb_substr($satir, 0, 300, 'UTF-8');
            }
            if (count($sorular) >= 8) {
                break;
            }
        }

        $kayitId = $Ilan->kaydet([
            'id' => $id,
            'baslik' => $baslik,
            'departman' => trim(mb_substr((string) ($_POST['department'] ?? $_POST['departman'] ?? ''), 0, 150, 'UTF-8')),
            'gorev' => trim(mb_substr((string) ($_POST['duty'] ?? $_POST['gorev'] ?? ''), 0, 150, 'UTF-8')),
            'calisma_yeri' => trim(mb_substr((string) ($_POST['location'] ?? $_POST['calisma_yeri'] ?? ''), 0, 150, 'UTF-8')),
            'aciklama' => trim(mb_substr((string) ($_POST['description'] ?? $_POST['aciklama'] ?? ''), 0, 5000, 'UTF-8')),
            'aranan_nitelikler' => trim(mb_substr((string) ($_POST['requirements'] ?? $_POST['aranan_nitelikler'] ?? ''), 0, 3000, 'UTF-8')),
            'ai_kriterleri' => trim(mb_substr((string) ($_POST['ai_criteria'] ?? $_POST['ai_kriterleri'] ?? ''), 0, 3000, 'UTF-8')),
            'sorular' => $sorular ? json_encode($sorular, JSON_UNESCAPED_UNICODE) : null,
            'durum' => $durum,
            'gecerlilik_tarihi' => $gecerlilik,
        ], $firmaId, $userId);

        $Log->logAction($userId, 'İşe Alım İlanı', 'Mobil: İlan ' . ($id > 0 ? 'güncellendi: ' : 'oluşturuldu: ') . $baslik . ' (ID: ' . $kayitId . ')', SystemLogModel::LEVEL_IMPORTANT);
        ikResponse([
            'success' => true,
            'message' => $id > 0 ? 'İlan başarıyla güncellendi.' : 'Yeni ilan oluşturuldu.',
            'id' => Security::encrypt($kayitId),
        ]);
    }

    // 7.3. İlan Sil
    if ($action === 'delete-job') {
        $id = ikDecryptId((string) ($_POST['id'] ?? ''));
        if (!$Ilan->softDeleteForFirma($id, $firmaId)) {
            ikResponse(['success' => false, 'message' => 'İlan silinemedi.']);
        }
        $Log->logAction($userId, 'İşe Alım İlanı', 'Mobil: İlan silindi (ID: ' . $id . ')', SystemLogModel::LEVEL_IMPORTANT);
        ikResponse(['success' => true, 'message' => 'İlan silindi.']);
    }

    // 8. İlan Durumu Güncelleme
    if ($action === 'job-status') {
        $id = ikDecryptId((string) ($_POST['id'] ?? ''));
        $durum = (string) ($_POST['status'] ?? '');
        $mevcut = $Ilan->bul($id, $firmaId);
        if (!$mevcut) {
            ikResponse(['success' => false, 'message' => 'İlan bulunamadı.'], 404);
        }
        if (!isset(IkIlanModel::DURUMLAR[$durum])) {
            ikResponse(['success' => false, 'message' => 'Geçersiz ilan durumu.']);
        }
        if ($mevcut->durum === $durum) {
            ikResponse(['success' => true, 'message' => 'İlan durumu zaten ' . (IkIlanModel::DURUMLAR[$durum] ?? $durum) . '.']);
        }
        if (!$Ilan->durumGuncelle($id, $firmaId, $durum)) {
            ikResponse(['success' => false, 'message' => 'İlan durumu güncellenemedi.']);
        }
        $Log->logAction($userId, 'İşe Alım İlanı', 'Mobil: İlan durumu değişti (ID: ' . $id . ') → ' . $durum, SystemLogModel::LEVEL_INFO);
        ikResponse(['success' => true, 'message' => 'İlan durumu güncellendi.']);
    }

    // 9. İlan Paylaşım Linki ve Metni
    if ($action === 'job-link') {
        $id = ikDecryptId((string) ($_GET['id'] ?? ''));
        $ilan = $Ilan->bul($id, $firmaId);
        if (!$ilan) {
            ikResponse(['success' => false, 'message' => 'İlan bulunamadı.'], 404);
        }
        $link = IkIlanModel::linkOlustur($Ilan->tokenGetir($ilan));
        $firmaAdi = $Ilan->firmaAdi($firmaId);
        $mesaj = "Merhaba,\n\n" . ($firmaAdi !== '' ? $firmaAdi . ' olarak ' : '') . '"' . $ilan->baslik . "\" pozisyonu için başvuru alıyoruz.\n"
            . "Aşağıdaki bağlantıdan birkaç dakikada dijital başvuru yapabilirsiniz:\n\n" . $link;
        ikResponse([
            'success' => true,
            'link' => $link,
            'message' => $mesaj,
            'is_published' => $ilan->durum === 'yayinda',
        ]);
    }

    // 10. Mülakat Kaydetme
    if ($action === 'save-interview') {
        if (!Gate::allows('ik_basvuru_mulakat') && !Gate::isSuperAdmin()) {
            ikResponse(['success' => false, 'message' => 'Mülakat kaydetme yetkiniz bulunmuyor.'], 403);
        }
        $basvuruId = ikDecryptId((string) ($_POST['id'] ?? ''));
        $b = $Basvuru->bul($basvuruId, $firmaId);
        if (!$b) {
            ikResponse(['success' => false, 'message' => 'Başvuru bulunamadı.'], 404);
        }
        if ($b->durum === 'personele_donustu') {
            ikResponse(['success' => false, 'message' => 'Personele dönüşmüş başvuruda mülakat kaydı eklenemez.']);
        }

        $mulakatId = ($_POST['mulakat_id'] ?? '') !== '' ? ikDecryptId((string) $_POST['mulakat_id']) : 0;
        $tarih = DateTime::createFromFormat('Y-m-d\TH:i', (string) ($_POST['mulakat_tarihi'] ?? ''))
            ?: DateTime::createFromFormat('Y-m-d H:i:s', (string) ($_POST['mulakat_tarihi'] ?? ''));
        if (!$tarih) {
            ikResponse(['success' => false, 'message' => 'Mülakat tarihi geçersiz.']);
        }
        $tur = (string) ($_POST['tur'] ?? 'yuz_yuze');
        $sonuc = (string) ($_POST['sonuc'] ?? 'beklemede');
        if (!isset(IkMulakatModel::TURLER[$tur]) || !isset(IkMulakatModel::SONUCLAR[$sonuc])) {
            ikResponse(['success' => false, 'message' => 'Mülakat türü veya sonucu geçersiz.']);
        }

        $puan = isset($_POST['genel_puan']) && (int) $_POST['genel_puan'] >= 1 && (int) $_POST['genel_puan'] <= 5 ? (int) $_POST['genel_puan'] : null;
        $genelNot = trim(mb_substr(str_replace("\r", '', (string) ($_POST['genel_not'] ?? '')), 0, 3000, 'UTF-8'));

        $db = $Mulakat->getDb();
        $db->beginTransaction();
        try {
            $savedId = $Mulakat->kaydet($mulakatId, [
                'firma_id' => $firmaId,
                'basvuru_id' => $basvuruId,
                'mulakat_tarihi' => $tarih->format('Y-m-d H:i:s'),
                'tur' => $tur,
                'mulakat_yapan_id' => $userId,
                'sonuc' => $sonuc,
                'genel_puan' => $puan,
                'genel_not' => $genelNot !== '' ? $genelNot : null,
                'sorular_cevaplar' => json_encode([], JSON_UNESCAPED_UNICODE),
            ]);
            $Basvuru->olayEkle($firmaId, $basvuruId, $userId, 'Mülakat kaydedildi', IkMulakatModel::TURLER[$tur] . ' • ' . IkMulakatModel::SONUCLAR[$sonuc]);
            if (in_array($b->durum, ['yeni', 'degerlendirildi'], true)) {
                $Basvuru->durumGuncelle($basvuruId, $firmaId, 'gorusme');
                $Basvuru->olayEkle($firmaId, $basvuruId, $userId, 'Durum değişti', IkBasvuruModel::DURUMLAR['gorusme']);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $Log->logAction($userId, 'İşe Alım Başvurusu', 'Mobil: Mülakat kaydedildi (Başvuru ID: ' . $basvuruId . ')', SystemLogModel::LEVEL_INFO);
        ikResponse(['success' => true, 'message' => 'Mülakat kaydedildi.']);
    }

    // 11. Mülakat Silme
    if ($action === 'delete-interview') {
        if (!Gate::allows('ik_basvuru_mulakat') && !Gate::isSuperAdmin()) {
            ikResponse(['success' => false, 'message' => 'Yetkiniz bulunmuyor.'], 403);
        }
        $mulakatId = ikDecryptId((string) ($_POST['mulakat_id'] ?? ''));
        $m = $Mulakat->bul($mulakatId, $firmaId);
        if (!$m || !$Mulakat->softDeleteForFirma($mulakatId, $firmaId)) {
            ikResponse(['success' => false, 'message' => 'Mülakat kaydı silinemedi.']);
        }
        $Basvuru->olayEkle($firmaId, (int) $m->basvuru_id, $userId, 'Mülakat kaydı silindi');
        ikResponse(['success' => true, 'message' => 'Mülakat kaydı silindi.']);
    }

    // 12. Aday Bildirim Şablonu Hazırlama
    if ($action === 'prepare-notification') {
        if (!Gate::allows('ik_basvuru_aday_bildirim') && !Gate::isSuperAdmin()) {
            ikResponse(['success' => false, 'message' => 'Yetkiniz bulunmuyor.'], 403);
        }
        $b = $Basvuru->bul(ikDecryptId((string) ($_POST['id'] ?? '')), $firmaId);
        if (!$b) {
            ikResponse(['success' => false, 'message' => 'Başvuru bulunamadı.'], 404);
        }
        $sablon = (string) ($_POST['template'] ?? '');
        if (!isset(IkAdayBildirimService::SABLONLAR[$sablon])) {
            ikResponse(['success' => false, 'message' => 'Şablon geçersiz.']);
        }
        $mulakatTarihi = null;
        if ($sablon === 'gorusme') {
            foreach ($Mulakat->listele((int) $b->id, $firmaId) as $m) {
                if (strtotime((string) $m->mulakat_tarihi) > time()) {
                    $mulakatTarihi = (string) $m->mulakat_tarihi;
                    break;
                }
            }
        }
        [$konu, $mesaj] = IkAdayBildirimService::hazirla($sablon, (string) $b->ad_soyad, (string) $b->ilan_baslik, (string) $Ilan->firmaAdi($firmaId), $mulakatTarihi);
        ikResponse(['success' => true, 'subject' => $konu, 'message' => $mesaj]);
    }

    // 13. Aday Bildirimi Gönderme
    if ($action === 'send-notification') {
        if (!Gate::allows('ik_basvuru_aday_bildirim') && !Gate::isSuperAdmin()) {
            ikResponse(['success' => false, 'message' => 'Yetkiniz bulunmuyor.'], 403);
        }
        $basvuruId = ikDecryptId((string) ($_POST['id'] ?? ''));
        $b = $Basvuru->bul($basvuruId, $firmaId);
        if (!$b) {
            ikResponse(['success' => false, 'message' => 'Başvuru bulunamadı.'], 404);
        }
        $kanal = (string) ($_POST['channel'] ?? '');
        $sablon = (string) ($_POST['template'] ?? '');
        if (!in_array($kanal, ['eposta', 'whatsapp'], true) || !isset(IkAdayBildirimService::SABLONLAR[$sablon])) {
            ikResponse(['success' => false, 'message' => 'Kanal veya şablon geçersiz.']);
        }
        $konu = trim(mb_substr((string) ($_POST['subject'] ?? ''), 0, 190, 'UTF-8'));
        $mesaj = trim(mb_substr(str_replace("\r", '', (string) ($_POST['message'] ?? '')), 0, 3000, 'UTF-8'));
        if ($mesaj === '' || ($kanal === 'eposta' && $konu === '')) {
            ikResponse(['success' => false, 'message' => 'Konu ve mesaj boş olamaz.']);
        }
        $kayit = [
            'firma_id' => $firmaId,
            'basvuru_id' => $basvuruId,
            'kanal' => $kanal,
            'sablon' => $sablon,
            'konu' => $konu !== '' ? $konu : null,
            'mesaj' => $mesaj,
            'kullanici_id' => $userId,
        ];

        if ($kanal === 'eposta') {
            $alici = trim((string) $b->email);
            if ($alici === '' || !filter_var($alici, FILTER_VALIDATE_EMAIL)) {
                ikResponse(['success' => false, 'message' => 'Adayın geçerli bir e-posta adresi yok.']);
            }
            $basarili = false;
            try {
                $basarili = IkAdayBildirimService::epostaGonder($alici, $konu, $mesaj, $firmaId);
            } catch (Throwable $e) {
                error_log('Mobil İK aday e-postası hatası: ' . $e->getMessage());
            }
            $AdayBildirim->ekle($kayit + ['alici' => $alici, 'durum' => $basarili ? 'gonderildi' : 'hata']);
            if (!$basarili) {
                ikResponse(['success' => false, 'message' => 'E-posta gönderilemedi. Lütfen daha sonra tekrar deneyin.']);
            }
            $Basvuru->olayEkle($firmaId, $basvuruId, $userId, 'Adaya e-posta gönderildi', IkAdayBildirimService::SABLONLAR[$sablon]);
            $Log->logAction($userId, 'İşe Alım Başvurusu', 'Mobil: Adaya e-posta gönderildi (ID: ' . $basvuruId . ')', SystemLogModel::LEVEL_IMPORTANT);
            ikResponse(['success' => true, 'message' => 'E-posta gönderildi.']);
        }

        $AdayBildirim->ekle($kayit + ['alici' => (string) $b->telefon, 'durum' => 'whatsapp_acildi']);
        $Basvuru->olayEkle($firmaId, $basvuruId, $userId, 'Adaya WhatsApp mesajı hazırlandı', IkAdayBildirimService::SABLONLAR[$sablon]);
        $Log->logAction($userId, 'İşe Alım Başvurusu', 'Mobil: Adaya WhatsApp bildirimi açıldı (ID: ' . $basvuruId . ')', SystemLogModel::LEVEL_IMPORTANT);
        ikResponse(['success' => true, 'message' => 'WhatsApp bildirimi kaydedildi.']);
    }

    // 14. Adayı Personele Dönüştür
    if ($action === 'convert-to-personnel') {
        if (!Gate::allows('ik_basvuru_personele_donustur') && !Gate::isSuperAdmin()) {
            ikResponse(['success' => false, 'message' => 'Yetkiniz bulunmuyor.'], 403);
        }
        $b = $Basvuru->bul(ikDecryptId((string) ($_POST['id'] ?? '')), $firmaId);
        if (!$b) {
            ikResponse(['success' => false, 'message' => 'Başvuru bulunamadı.'], 404);
        }
        $b = $Mulakat->guncelUygula($b, $Mulakat->guncelDegerler((int) $b->id, $firmaId));
        $tc = preg_replace('/\D/', '', (string) ($_POST['tc_kimlik_no'] ?? ''));
        $giris = trim(mb_substr((string) ($_POST['ise_giris_tarihi'] ?? ''), 0, 10, 'UTF-8'));
        try {
            $sonuc = (new IkBasvuruDonusturService())->donustur($b, $tc, $giris, $firmaId, $userId);
        } catch (DomainException $e) {
            ikResponse(['success' => false, 'message' => $e->getMessage()]);
        }
        ikResponse([
            'success' => true,
            'message' => 'Aday başarıyla personel olarak kaydedildi.',
            'personel_id' => $sonuc['personel_enc_id'],
        ]);
    }

    // 15. CV Görüntüleme (Güvenli AES-256 Deşifre)
    if ($action === 'cv-view') {
        $b = $Basvuru->bul(ikDecryptId((string) ($_GET['id'] ?? '')), $firmaId);
        if (!$b || empty($b->cv_dosya)) {
            http_response_code(404);
            exit;
        }
        $yol = dirname(__DIR__, 3) . '/uploads/ik_basvuru/' . $firmaId . '/' . basename((string) $b->cv_dosya);
        if (!is_file($yol)) {
            http_response_code(404);
            exit;
        }
        $icerik = Security::decryptFile((string) file_get_contents($yol));
        $Log->logAction($userId, 'İşe Alım Başvurusu', 'Mobil: Aday CV görüntülendi (ID: ' . (int) $b->id . ')', SystemLogModel::LEVEL_INFO);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="cv.pdf"');
        header('Content-Length: ' . strlen($icerik));
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: sandbox');
        header('Cache-Control: private, no-store');
        echo $icerik;
        exit;
    }

    // 16. Aday Fotoğrafı (Güvenli AES-256 Deşifre)
    if ($action === 'photo-view') {
        $b = $Basvuru->bul(ikDecryptId((string) ($_GET['id'] ?? '')), $firmaId);
        $yol = $b && !empty($b->foto_dosya) ? dirname(__DIR__, 3) . '/uploads/ik_basvuru/' . $firmaId . '/' . basename((string) $b->foto_dosya) : '';
        if ($yol === '' || !is_file($yol)) {
            http_response_code(404);
            exit;
        }
        $icerik = Security::decryptFile((string) file_get_contents($yol));
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . strlen($icerik));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=300');
        echo $icerik;
        exit;
    }

    ikResponse(['success' => false, 'message' => 'Tanımsız işlem.'], 400);
} catch (Throwable $e) {
    error_log('mobile-spa/ik-ise-alim.php [' . $action . '] hatası: ' . $e->getMessage());
    ikResponse(['success' => false, 'message' => 'İşlem sırasında bir hata oluştu.'], 500);
}
