<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\NobetModel;
use App\Model\PersonelModel;
use App\Helper\Security;
use App\Helper\Date;
use App\Model\SystemLogModel;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $Nobet = new NobetModel();
    $Personel = new PersonelModel();
    $SystemLog = new SystemLogModel();
    $userId = $_SESSION['user_id'] ?? 0;

    header('Content-Type: application/json');

    try {
        switch ($action) {
            // =====================================================
            // TAKVİM İŞLEMLERİ
            // =====================================================
            case 'get-calendar-events':
                $baslangic = $_POST['start'] ?? date('Y-m-01');
                $bitis = $_POST['end'] ?? date('Y-m-t');

                // Departmanları Dinamik Olarak Personel Verilerinden Çek
                $PersonelModel = new \App\Model\PersonelModel();
                $allPersonel = $PersonelModel->all();
                $uniqueDepts = array_filter(array_unique(array_column($allPersonel, 'departman')));

                $colors = [
                    '#3b82f6',
                    '#10b981',
                    '#f59e0b',
                    '#8b5cf6',
                    '#ef4444',
                    '#06b6d4',
                    '#ec4899',
                    '#f97316',
                    '#6366f1',
                    '#14b8a6',
                    '#f43f5e',
                    '#84cc16',
                    '#eab308',
                    '#d946ef',
                    '#0ea5e9',
                    '#4ade80',
                    '#fbbf24',
                    '#a78bfa',
                    '#f87171',
                    '#2dd4bf'
                ];

                // Normalleştirme fonksiyonu (agresif)
                $normalizeDeptName = function ($name) {
                    if (!$name)
                        return '';
                    $name = trim($name);
                    $search = array('ç', 'ğ', 'ı', 'i', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'I', 'İ', 'Ö', 'Ş', 'Ü', '-', '_', ' ', '/', '.', ',');
                    $replace = array('C', 'G', 'I', 'I', 'O', 'S', 'U', 'C', 'G', 'I', 'I', 'O', 'S', 'U', '', '', '', '', '', '');
                    $name = str_replace($search, $replace, $name);
                    return strtoupper($name);
                };

                $deptColorMap = [];
                $ci = 0;
                foreach ($uniqueDepts as $ud) {
                    $normalizedName = $normalizeDeptName($ud);
                    if (!isset($deptColorMap[$normalizedName])) {
                        $deptColorMap[$normalizedName] = $colors[$ci % count($colors)];
                        $ci++;
                    }
                }

                $nobetler = $Nobet->getCalendarEvents($baslangic, $bitis);

                $events = [];
                foreach ($nobetler as $nobet) {
                    // Departmana göre renk belirleme
                    $normalizedDept = $normalizeDeptName($nobet->departman ?? '');
                    $color = $deptColorMap[$normalizedDept] ?? '#3788d8';
                    $textColor = '#ffffff';

                    // Özel Durum Görselleştirmesi
                    $borderColor = $color;
                    if ($nobet->durum == 'devir_alindi') {
                        $borderColor = '#155724'; // Koyu yeşil çerçeve
                    }

                    $events[] = [
                        'id' => Security::encrypt($nobet->id),
                        'title' => $nobet->adi_soyadi,
                        'start' => $nobet->nobet_tarihi . 'T' . $nobet->baslangic_saati,
                        'end' => $nobet->nobet_tarihi . 'T' . $nobet->bitis_saati,
                        'allDay' => true,
                        'backgroundColor' => $color,
                        'borderColor' => $borderColor,
                        'textColor' => $textColor,
                        'extendedProps' => [
                            'personel_id' => Security::encrypt($nobet->personel_id),
                            'departman' => $nobet->departman,
                            'ekip_adi' => $nobet->ekip_adi,
                            'ekip_bolge' => $nobet->ekip_bolge,
                            'telefon' => $nobet->cep_telefonu,
                            'durum' => $nobet->durum,
                            'nobet_tipi' => $nobet->nobet_tipi,
                            'aciklama' => $nobet->aciklama,
                            'resim' => $nobet->resim_yolu ?? 'assets/images/users/user-dummy-img.jpg'
                        ]
                    ];
                }

                echo json_encode($events);
                break;

            // =====================================================
            // NÖBET EKLEME / GÜNCELLEME
            // =====================================================
            case 'add-nobet':
                $personel_id = Security::decrypt($_POST['personel_id']);
                $nobet_tarihi = Date::Ymd($_POST['nobet_tarihi']);

                $data = [
                    'personel_id' => $personel_id,
                    'nobet_tarihi' => $nobet_tarihi,
                    'baslangic_saati' => $_POST['baslangic_saati'] ?? '18:00:00',
                    'bitis_saati' => $_POST['bitis_saati'] ?? '08:00:00',
                    'nobet_tipi' => $_POST['nobet_tipi'] ?? 'standart',
                    'aciklama' => $_POST['aciklama'] ?? null
                ];

                // Çakışma kontrolü
                if ($Nobet->hasNobetOnDate($personel_id, $nobet_tarihi)) {
                    $personel = $Personel->find($personel_id);
                    throw new Exception("{$personel->adi_soyadi} isimli personelin {$nobet_tarihi} tarihinde zaten bir nöbeti bulunuyor.");
                }

                $nobet_id = $Nobet->addNobet($data);

                if ($nobet_id) {
                    $personel = $Personel->find($personel_id);
                    $SystemLog->logAction($userId, 'Nöbet Ekleme', "{$personel->adi_soyadi} için {$nobet_tarihi} tarihinde nöbet eklendi.");

                    // PWA Push bildirimi gönder
                    $Nobet->sendNobetAtamaBildirimi($personel_id, $nobet_tarihi);

                    echo json_encode(['status' => 'success', 'message' => 'Nöbet başarıyla eklendi.', 'id' => Security::encrypt($nobet_id)]);
                } else {
                    throw new Exception("Nöbet eklenirken bir hata oluştu.");
                }
                break;

            case 'update-nobet':
                $id = Security::decrypt($_POST['nobet_id']);

                $data = [];
                if (isset($_POST['personel_id'])) {
                    $data['personel_id'] = Security::decrypt($_POST['personel_id']);
                }
                if (isset($_POST['nobet_tarihi'])) {
                    $data['nobet_tarihi'] = Date::Ymd($_POST['nobet_tarihi']);
                }
                if (isset($_POST['baslangic_saati'])) {
                    $data['baslangic_saati'] = $_POST['baslangic_saati'];
                }
                if (isset($_POST['bitis_saati'])) {
                    $data['bitis_saati'] = $_POST['bitis_saati'];
                }
                if (isset($_POST['nobet_tipi'])) {
                    $data['nobet_tipi'] = $_POST['nobet_tipi'];
                }
                if (isset($_POST['aciklama'])) {
                    $data['aciklama'] = $_POST['aciklama'];
                }

                // Çakışma kontrolü (Eğer personel veya tarih değişmişse)
                $currentNobet = $Nobet->find($id);
                $checkPersonelId = $data['personel_id'] ?? $currentNobet->personel_id;
                $checkTarih = $data['nobet_tarihi'] ?? $currentNobet->nobet_tarihi;

                if ($Nobet->hasNobetOnDate($checkPersonelId, $checkTarih, $id)) {
                    $personel = $Personel->find($checkPersonelId);
                    throw new Exception("{$personel->adi_soyadi} isimli personelin {$checkTarih} tarihinde zaten bir nöbeti bulunuyor.");
                }

                $result = $Nobet->updateNobet($id, $data);

                if ($result) {
                    echo json_encode(['status' => 'success', 'message' => 'Nöbet güncellendi.']);
                } else {
                    throw new Exception("Güncelleme sırasında hata oluştu.");
                }
                break;

            case 'delete-nobet':
                $id = Security::decrypt($_POST['nobet_id']);
                $nobet = $Nobet->find($id);

                $result = $Nobet->deleteNobet($id);

                if ($result) {
                    $SystemLog->logAction($userId, 'Nöbet Silme', "{$nobet->adi_soyadi}'nin {$nobet->nobet_tarihi} tarihli nöbeti silindi.");
                    echo json_encode(['status' => 'success', 'message' => 'Nöbet silindi.']);
                } else {
                    throw new Exception("Silme işlemi başarısız.");
                }
                break;

            // =====================================================
            // SÜRÜKLE-BIRAK İŞLEMLERİ
            // =====================================================
            case 'move-nobet':
                $id = Security::decrypt($_POST['nobet_id']);
                $yeni_tarih = $_POST['yeni_tarih']; // Y-m-d formatında
                $personel_id = isset($_POST['personel_id']) ? Security::decrypt($_POST['personel_id']) : null;

                $result = false;
                $currentNobet = $Nobet->find($id);
                $finalPersonelId = $personel_id ?? $currentNobet->personel_id;

                // Çakışma kontrolü
                if ($Nobet->hasNobetOnDate($finalPersonelId, $yeni_tarih, $id)) {
                    $personel = $Personel->find($finalPersonelId);
                    throw new Exception("{$personel->adi_soyadi} isimli personelin {$yeni_tarih} tarihinde zaten bir nöbeti bulunuyor.");
                }

                $result = $Nobet->moveNobet($id, $yeni_tarih, $personel_id);

                if ($result) {
                    echo json_encode(['status' => 'success', 'message' => 'Nöbet taşındı.']);
                } else {
                    throw new Exception("Taşıma işlemi başarısız.");
                }
                break;

            case 'drop-personel':
                // Personel havuzundan takvime sürükle-bırak
                $personel_id = Security::decrypt($_POST['personel_id']);
                $nobet_tarihi = $_POST['nobet_tarihi']; // Y-m-d formatında

                $data = [
                    'personel_id' => $personel_id,
                    'nobet_tarihi' => $nobet_tarihi,
                    'baslangic_saati' => $_POST['baslangic_saati'] ?? '18:00:00',
                    'bitis_saati' => $_POST['bitis_saati'] ?? '08:00:00',
                    'nobet_tipi' => $_POST['nobet_tipi'] ?? 'standart'
                ];

                // Çakışma kontrolü
                if ($Nobet->hasNobetOnDate($personel_id, $nobet_tarihi)) {
                    $personel = $Personel->find($personel_id);
                    throw new Exception("{$personel->adi_soyadi} isimli personelin {$nobet_tarihi} tarihinde zaten bir nöbeti bulunuyor.");
                }

                $nobet_id = $Nobet->addNobet($data);

                if ($nobet_id) {
                    $personel = $Personel->find($personel_id);

                    // PWA Push bildirimi gönder
                    $Nobet->sendNobetAtamaBildirimi($personel_id, $nobet_tarihi);

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Nöbet atandı.',
                        'id' => Security::encrypt($nobet_id),
                        'personel' => [
                            'adi_soyadi' => $personel->adi_soyadi,
                            'departman' => $personel->departman
                        ]
                    ]);
                } else {
                    throw new Exception("Atama işlemi başarısız.");
                }
                break;

            // =====================================================
            // DEĞİŞİM TALEPLERİ
            // =====================================================
            case 'create-degisim-talebi':
                $data = [
                    'nobet_id' => Security::decrypt($_POST['nobet_id']),
                    'talep_eden_id' => Security::decrypt($_POST['talep_eden_id']),
                    'talep_edilen_id' => Security::decrypt($_POST['talep_edilen_id']),
                    'aciklama' => $_POST['aciklama'] ?? null
                ];

                $talep_id = $Nobet->createDegisimTalebi($data);

                if ($talep_id) {
                    echo json_encode(['status' => 'success', 'message' => 'Değişim talebi oluşturuldu.']);
                } else {
                    throw new Exception("Talep oluşturulamadı.");
                }
                break;

            case 'onayla-personel-talebi':
                $talep_id = Security::decrypt($_POST['talep_id']);
                $result = $Nobet->onaylaPersonelTalebi($talep_id);

                if ($result) {
                    echo json_encode(['status' => 'success', 'message' => 'Talep onaylandı. Amir onayı bekleniyor.']);
                } else {
                    throw new Exception("Onaylama başarısız.");
                }
                break;

            case 'onayla-amir-talebi':
                $talep_id = Security::decrypt($_POST['talep_id']);
                $result = $Nobet->onaylaAmirTalebi($talep_id, $userId);

                if ($result) {
                    $SystemLog->logAction($userId, 'Nöbet Değişim Onayı', "Nöbet değişim talebi #{$talep_id} onaylandı.");
                    echo json_encode(['status' => 'success', 'message' => 'Değişim onaylandı. Takvim güncellendi.']);
                } else {
                    throw new Exception("Onaylama başarısız.");
                }
                break;

            case 'reddet-talebi':
                $talep_id = Security::decrypt($_POST['talep_id']);
                $red_nedeni = $_POST['red_nedeni'] ?? null;

                $result = $Nobet->reddetTalebi($talep_id, $userId, $red_nedeni);

                if ($result) {
                    echo json_encode(['status' => 'success', 'message' => 'Talep reddedildi.']);
                } else {
                    throw new Exception("Reddetme başarısız.");
                }
                break;

            case 'get-bekleyen-talepler':
                $talepler = $Nobet->getBekleyenDegisimTalepleri();
                echo json_encode(['status' => 'success', 'data' => $talepler]);
                break;

            // =====================================================
            // NÖBET DEVİR İŞLEMLERİ
            // =====================================================
            case 'devir-yap':
                $nobet_id = Security::decrypt($_POST['nobet_id']);
                $personel_id = Security::decrypt($_POST['personel_id']);

                $result = $Nobet->devirYap($nobet_id, $personel_id);

                if ($result) {
                    $personel = $Personel->find($personel_id);
                    $SystemLog->logAction($userId, 'Nöbet Devri', "{$personel->adi_soyadi} nöbeti devraldı.");
                    echo json_encode(['status' => 'success', 'message' => 'Nöbet devralındı. Zaman damgası kaydedildi.']);
                } else {
                    throw new Exception("Devir işlemi başarısız.");
                }
                break;

            // =====================================================
            // PERSONEL LİSTESİ
            // =====================================================
            case 'get-personel-list':
                $departman = $_POST['departman'] ?? null;

                $personeller = $Personel->all();

                // Departmana göre filtrele
                // Departman Renk Haritası Oluştur
                $Tanimlamalar = new \App\Model\TanimlamalarModel();
                $allDepts = $Tanimlamalar->getByGrup('departman');
                $colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#ec4899', '#f97316', '#6366f1', '#14b8a6'];

                // Normalleştirme fonksiyonu (agresif)
                $normalizeDeptName = function ($name) {
                    if (!$name)
                        return '';
                    $name = trim($name);
                    $search = array('ç', 'ğ', 'ı', 'i', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'I', 'İ', 'Ö', 'Ş', 'Ü', '-', '_', ' ', '/', '.', ',');
                    $replace = array('C', 'G', 'I', 'I', 'O', 'S', 'U', 'C', 'G', 'I', 'I', 'O', 'S', 'U', '', '', '', '', '', '');
                    $name = str_replace($search, $replace, $name);
                    return strtoupper($name);
                };

                $deptColorMap = [];
                $ci = 0;
                foreach ($allDepts as $ad) {
                    $normalizedName = $normalizeDeptName($ad->tur_adi);
                    if (!isset($deptColorMap[$normalizedName])) {
                        $deptColorMap[$normalizedName] = $colors[$ci % count($colors)];
                        $ci++;
                    }
                }

                // Departmana göre filtrele
                if ($departman && $departman !== 'all') {
                    $personeller = array_filter($personeller, function ($p) use ($departman) {
                        return trim($p->departman ?? '') === trim($departman);
                    });
                }

                $data = [];
                foreach ($personeller as $p) {
                    // Renk belirleme
                    $normalizedDept = $normalizeDeptName($p->departman ?? '');
                    $color = $deptColorMap[$normalizedDept] ?? '#3788d8';

                    $data[] = [
                        'id' => Security::encrypt($p->id),
                        'adi_soyadi' => $p->adi_soyadi,
                        'departman' => $p->departman,
                        'ekip_adi' => $p->ekip_adi ?? '',
                        'resim_yolu' => $p->resim_yolu ?? 'assets/images/users/user-dummy-img.jpg',
                        'color' => $color
                    ];
                }

                echo json_encode(['status' => 'success', 'data' => $data]);
                break;

            // =====================================================
            // İSTATİSTİKLER
            // =====================================================
            case 'get-nobet-dagilimi':
                $yil = $_POST['yil'] ?? date('Y');
                $ay = $_POST['ay'] ?? date('m');

                $dagilim = $Nobet->getAylikNobetDagilimi($yil, $ay);
                echo json_encode(['status' => 'success', 'data' => $dagilim]);
                break;

            case 'get-nobet-detay':
                $id = Security::decrypt($_POST['nobet_id']);
                $nobet = $Nobet->find($id);

                if ($nobet) {
                    $nobet->id = Security::encrypt($nobet->id);
                    $nobet->personel_id = Security::encrypt($nobet->personel_id);
                    $nobet->nobet_tarihi_formatted = Date::dmY($nobet->nobet_tarihi);
                    echo json_encode(['status' => 'success', 'data' => $nobet]);
                } else {
                    throw new Exception("Nöbet bulunamadı.");
                }
                break;

            default:
                throw new Exception("Geçersiz işlem.");
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// GET istekleri için (AJAX olmayan)
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action == 'export-calendar') {
        // Takvim dışa aktarma (iCal formatı)
        // TODO: Implement iCal export
    }
}
