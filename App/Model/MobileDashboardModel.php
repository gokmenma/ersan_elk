<?php

namespace App\Model;

use App\Helper\Security;
use PDO;
use Throwable;

class MobileDashboardModel
{
    private function safely(callable $callback, mixed $fallback = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable $error) {
            error_log('Mobile dashboard data: ' . $error->getMessage());
            return $fallback;
        }
    }

    public function announcements(): array
    {
        $rows = $this->safely(fn() => (new DuyuruModel())->getAll(true), []);
        return array_map(static fn(object $row): array => [
            'id' => (int) ($row->id ?? 0),
            'title' => trim((string) ($row->baslik ?? 'Duyuru')),
            'content' => trim(strip_tags((string) ($row->icerik ?? ''))),
            'raw_content' => trim((string) ($row->icerik ?? '')),
            'date' => (string) ($row->tarih ?? ''),
            'event_date' => (string) ($row->etkinlik_tarihi ?? ''),
            'image' => !empty($row->resim) ? (string) $row->resim : null,
        ], array_slice($rows, 0, 10));
    }

    public function personnel(): array
    {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $model = new PersonelModel();
        $counts = $this->safely(fn() => $model->personelSayilari('personel'), (object) []);
        $advanced = $this->safely(fn() => $model->getAdvancedDashboardStats(), (object) []);
        $hareketModel = new PersonelHareketleriModel();
        $lateCount = (int) $this->safely(fn() => $hareketModel->getGecKalanlarCount($firmaId), 0);

        $active = (int) ($counts->aktif_personel ?? 0);
        $onLeave = (int) ($advanced->izinli_personel ?? 0);
        return [
            'total' => $active,
            'working' => max(0, $active - $onLeave),
            'on_leave' => $onLeave,
            'late' => $lateCount,
        ];
    }

    public function vehicles(): array
    {
        $model = new AracModel();
        $stats = $this->safely(fn() => $model->getStats(), (object) []);
        $active = (int) ($stats->aktif_arac ?? 0);
        $idle = (int) ($stats->bosta_arac ?? 0);
        $service = (int) $this->safely(fn() => $model->getServistekiAracSayisi(), 0);
        return [
            'total' => $active,
            'field' => max(0, $active - $idle - $service),
            'service' => $service,
            'idle' => $idle,
        ];
    }

    public function shifts(): array
    {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        if ($firmaId <= 0) return [];

        return $this->safely(function () use ($firmaId): array {
            $model = new PersonelModel();
            $db = $model->getDb();
            $stmt = $db->prepare("SELECT n.id, n.personel_id, n.baslangic_saati, n.bitis_saati, n.nobet_tipi,
                                         p.adi_soyadi, p.cep_telefonu, p.resim_yolu, p.personel_resim_yolu
                                  FROM nobetler n
                                  JOIN personel p ON n.personel_id = p.id
                                  WHERE n.nobet_tarihi = CURDATE()
                                    AND n.firma_id = :firma_id
                                    AND n.silinme_tarihi IS NULL
                                    AND (n.durum IS NULL OR n.durum NOT IN ('talep_edildi', 'reddedildi', 'iptal'))
                                    AND n.yonetici_onayi = 1
                                  ORDER BY n.baslangic_saati ASC");
            $stmt->execute([':firma_id' => $firmaId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(static function (array $row): array {
                $photo = !empty($row['personel_resim_yolu']) ? $row['personel_resim_yolu'] : ($row['resim_yolu'] ?? '');
                $name = trim((string) ($row['adi_soyadi'] ?? ''));
                $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $initials = count($words) >= 2
                    ? mb_strtoupper(mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr(end($words), 0, 1, 'UTF-8'), 'UTF-8')
                    : mb_strtoupper(mb_substr($name, 0, 2, 'UTF-8'), 'UTF-8');

                return [
                    'id' => (int) $row['id'],
                    'personel_id' => (int) $row['personel_id'],
                    'encrypted_id' => Security::encrypt((string) $row['personel_id']),
                    'name' => $name,
                    'initials' => $initials ?: 'NB',
                    'phone' => trim((string) ($row['cep_telefonu'] ?? '')),
                    'start_time' => !empty($row['baslangic_saati']) ? date('H:i', strtotime($row['baslangic_saati'])) : '',
                    'end_time' => !empty($row['bitis_saati']) ? date('H:i', strtotime($row['bitis_saati'])) : '',
                    'shift_type' => (string) ($row['nobet_tipi'] ?? ''),
                    'photo' => (!empty($photo) && file_exists(dirname(__DIR__, 2) . '/' . ltrim($photo, '/'))) ? '/' . ltrim($photo, '/') : null,
                ];
            }, $rows);
        }, []);
    }

    public function pendingApprovals(): array
    {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        if ($firmaId <= 0) {
            return ['requests' => 0, 'advances' => 0, 'leaves' => 0, 'total' => 0];
        }

        return $this->safely(function () use ($firmaId): array {
            $model = new PersonelModel();
            $db = $model->getDb();

            $st1 = $db->prepare("SELECT COUNT(*) FROM personel_talepleri pt 
                                  JOIN personel p ON pt.personel_id = p.id 
                                  WHERE (LOWER(pt.durum) NOT IN ('cozuldu', 'onaylandi', 'reddedildi', 'iptal', 'iptal_edildi', 'iptal edildi', 'i̇ptal edildi') 
                                    AND LOWER(pt.durum) NOT LIKE '%iptal%') 
                                    AND pt.silinme_tarihi IS NULL 
                                    AND p.silinme_tarihi IS NULL 
                                    AND p.firma_id = ?");
            $st1->execute([$firmaId]);
            $requests = (int) $st1->fetchColumn();

            $st2 = $db->prepare("SELECT COUNT(*) FROM personel_avanslari pa 
                                  JOIN personel p ON pa.personel_id = p.id 
                                  WHERE (LOWER(pa.durum) = 'beklemede') 
                                    AND pa.silinme_tarihi IS NULL 
                                    AND p.silinme_tarihi IS NULL 
                                    AND p.firma_id = ?");
            $st2->execute([$firmaId]);
            $advances = (int) $st2->fetchColumn();

            $st3 = $db->prepare("SELECT COUNT(*) FROM personel_izinleri pi 
                                  JOIN personel p ON pi.personel_id = p.id 
                                  WHERE (LOWER(pi.onay_durumu) = 'beklemede') 
                                    AND pi.silinme_tarihi IS NULL 
                                    AND p.silinme_tarihi IS NULL 
                                    AND p.firma_id = ?");
            $st3->execute([$firmaId]);
            $leaves = (int) $st3->fetchColumn();

            return [
                'requests' => $requests,
                'advances' => $advances,
                'leaves' => $leaves,
                'total' => $requests + $advances + $leaves,
            ];
        }, ['requests' => 0, 'advances' => 0, 'leaves' => 0, 'total' => 0]);
    }

    public function operationalTimestamps(): array
    {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        if ($firmaId <= 0) return ['reading' => null, 'works' => null, 'meter' => null];

        return $this->safely(function () use ($firmaId): array {
            $model = new PersonelModel();
            $db = $model->getDb();
            $stmt = $db->prepare("SELECT
                (SELECT MAX(created_at) FROM endeks_okuma WHERE firma_id = :fid1 AND created_at >= CURDATE()) AS last_update_endeks,
                (SELECT MAX(created_at) FROM yapilan_isler WHERE firma_id = :fid2 AND created_at >= CURDATE()) AS last_update_isler,
                (SELECT MAX(GREATEST(created_at, IFNULL(guncelleme_tarihi, created_at))) FROM sayac_degisim WHERE firma_id = :fid3 AND created_at >= CURDATE()) AS last_update_sayac");
            $stmt->execute([':fid1' => $firmaId, ':fid2' => $firmaId, ':fid3' => $firmaId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $fmt = static function (?string $dt): ?string {
                if (!$dt) return null;
                $ts = strtotime($dt);
                return $ts ? date('H:i', $ts) : null;
            };

            return [
                'reading' => $fmt($row['last_update_endeks'] ?? null),
                'works' => $fmt($row['last_update_isler'] ?? null),
                'meter' => $fmt($row['last_update_sayac'] ?? null),
            ];
        }, ['reading' => null, 'works' => null, 'meter' => null]);
    }

    public function workOperations(): array
    {
        $model = new PuantajModel();
        $daily = $this->safely(fn() => $model->getDailyStats(), (object) []);
        $monthly = $this->safely(fn() => $model->getMonthlyStats(), (object) []);
        $times = $this->operationalTimestamps();

        return [
            [
                'key' => 'cut_open',
                'label' => 'Kesme / Açma',
                'daily' => (int) ($daily->kesme_acma ?? 0),
                'monthly' => (int) ($monthly->kesme_acma ?? 0),
                'last_update' => $times['works'] ?? null,
                'api_action' => 'online-puantaj-sorgula',
                'api_tab' => 'kesme',
                'link' => '/raporlar?tab=kesme',
            ],
            [
                'key' => 'meter_change',
                'label' => 'Sayaç Değişimi',
                'daily' => (int) ($daily->sayac_degisimi ?? 0),
                'monthly' => (int) ($monthly->sayac_degisimi ?? 0),
                'last_update' => $times['meter'] ?? null,
                'api_action' => 'online-sayac-degisim-sorgula',
                'api_tab' => 'sokme_takma',
                'link' => '/raporlar?tab=sokme_takma',
            ],
            [
                'key' => 'sealing',
                'label' => 'Mühürleme',
                'daily' => (int) ($daily->muhurleme ?? 0),
                'monthly' => (int) ($monthly->muhurleme ?? 0),
                'last_update' => $times['works'] ?? null,
                'api_action' => 'online-puantaj-sorgula',
                'api_tab' => 'muhurleme',
                'link' => '/raporlar?tab=muhurleme',
            ],
        ];
    }

    public function readings(): array
    {
        $model = new EndeksOkumaModel();
        $times = $this->operationalTimestamps();
        return [
            'key' => 'reading',
            'label' => 'Endeks Okuma',
            'daily' => (int) $this->safely(fn() => $model->getDailyStats(), 0),
            'monthly' => (int) $this->safely(fn() => $model->getMonthlyStats(), 0),
            'last_update' => $times['reading'] ?? null,
            'api_action' => 'online-icmal-sorgula',
            'api_tab' => '',
            'link' => '/raporlar?tab=okuma',
        ];
    }

    public function illegalUse(): array
    {
        $model = new PuantajModel();
        $daily = $this->safely(fn() => $model->getKacakDailyStats(), (object) []);
        $monthly = $this->safely(fn() => $model->getKacakMonthlyStats(), (object) []);
        return [
            'key' => 'illegal',
            'label' => 'Kaçak',
            'daily' => (int) ($daily->toplam ?? 0),
            'monthly' => (int) ($monthly->toplam ?? 0),
            'last_update' => null,
            'api_action' => null,
            'api_tab' => null,
            'link' => '/raporlar?tab=kacakkontrol',
        ];
    }

    public function notices(): array
    {
        $model = new IhbarModel();
        $daily = $this->safely(fn() => $model->getDailyStats(), (object) []);
        $monthly = $this->safely(fn() => $model->getMonthlyStats(), (object) []);
        return [
            'key' => 'notice',
            'label' => 'İhbar',
            'daily' => (int) ($daily->toplam ?? 0),
            'monthly' => (int) ($monthly->toplam ?? 0),
            'last_update' => null,
            'api_action' => null,
            'api_tab' => null,
            'link' => '/ihbar',
        ];
    }

    public function getCardSettings(int $userId, int $firmaId): array
    {
        $settingsModel = new SettingsModel();
        $orderRaw = $settingsModel->getSettingByUser('mobile_dashboard_order', $userId, $firmaId);
        $settingsRaw = $settingsModel->getSettingByUser('mobile_dashboard_settings', $userId, $firmaId);

        $defaultOrder = [
            'announcements',
            'pending_approvals',
            'shifts',
            'personnel',
            'vehicles',
            'operations',
            'quick_actions',
        ];

        $order = !empty($orderRaw) ? json_decode($orderRaw, true) : null;
        if (!is_array($order) || empty($order)) {
            $order = $defaultOrder;
        } else {
            // Eksik olabilecek yeni kartları sona ekle
            foreach ($defaultOrder as $k) {
                if (!in_array($k, $order, true)) {
                    $order[] = $k;
                }
            }
        }

        $settings = !empty($settingsRaw) ? json_decode($settingsRaw, true) : [];
        if (!is_array($settings)) $settings = [];

        return [
            'order' => $order,
            'settings' => $settings,
        ];
    }

    public function saveCardSettings(int $userId, int $firmaId, array $order, array $settings): bool
    {
        $settingsModel = new SettingsModel();
        return $settingsModel->upsertMultipleSettings([
            'mobile_dashboard_order' => json_encode(array_values($order), JSON_UNESCAPED_UNICODE),
            'mobile_dashboard_settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
        ], $firmaId, $userId);
    }
}

