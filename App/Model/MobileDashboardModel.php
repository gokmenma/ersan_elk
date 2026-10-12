<?php

namespace App\Model;

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
            'title' => trim((string) ($row->baslik ?? 'Duyuru')),
            'content' => trim(strip_tags((string) ($row->icerik ?? ''))),
            'date' => (string) ($row->tarih ?? ''),
            'event_date' => (string) ($row->etkinlik_tarihi ?? ''),
        ], array_slice($rows, 0, 8));
    }

    public function personnel(): array
    {
        $model = new PersonelModel();
        $counts = $this->safely(fn() => $model->personelSayilari('personel'), (object) []);
        $advanced = $this->safely(fn() => $model->getAdvancedDashboardStats(), (object) []);
        $active = (int) ($counts->aktif_personel ?? 0);
        $onLeave = (int) ($advanced->izinli_personel ?? 0);
        return ['total' => $active, 'working' => max(0, $active - $onLeave), 'on_leave' => $onLeave];
    }

    public function vehicles(): array
    {
        $model = new AracModel();
        $stats = $this->safely(fn() => $model->getStats(), (object) []);
        $active = (int) ($stats->aktif_arac ?? 0);
        $idle = (int) ($stats->bosta_arac ?? 0);
        $service = (int) $this->safely(fn() => $model->getServistekiAracSayisi(), 0);
        return ['total' => $active, 'field' => max(0, $active - $idle - $service), 'service' => $service, 'idle' => $idle];
    }

    public function workOperations(): array
    {
        $model = new PuantajModel();
        $daily = $this->safely(fn() => $model->getDailyStats(), (object) []);
        $monthly = $this->safely(fn() => $model->getMonthlyStats(), (object) []);
        return [
            ['key' => 'sealing', 'label' => 'Mühürleme', 'daily' => (int) ($daily->muhurleme ?? 0), 'monthly' => (int) ($monthly->muhurleme ?? 0)],
            ['key' => 'cut_open', 'label' => 'Kesme / Açma', 'daily' => (int) ($daily->kesme_acma ?? 0), 'monthly' => (int) ($monthly->kesme_acma ?? 0)],
            ['key' => 'meter_change', 'label' => 'Sayaç değişimi', 'daily' => (int) ($daily->sayac_degisimi ?? 0), 'monthly' => (int) ($monthly->sayac_degisimi ?? 0)],
        ];
    }

    public function readings(): array
    {
        $model = new EndeksOkumaModel();
        return ['key' => 'reading', 'label' => 'Endeks okuma', 'daily' => (int) $this->safely(fn() => $model->getDailyStats(), 0), 'monthly' => (int) $this->safely(fn() => $model->getMonthlyStats(), 0)];
    }

    public function illegalUse(): array
    {
        $model = new PuantajModel();
        $daily = $this->safely(fn() => $model->getKacakDailyStats(), (object) []);
        $monthly = $this->safely(fn() => $model->getKacakMonthlyStats(), (object) []);
        return ['key' => 'illegal', 'label' => 'Kaçak', 'daily' => (int) ($daily->toplam ?? 0), 'monthly' => (int) ($monthly->toplam ?? 0)];
    }

    public function notices(): array
    {
        $model = new IhbarModel();
        $daily = $this->safely(fn() => $model->getDailyStats(), (object) []);
        $monthly = $this->safely(fn() => $model->getMonthlyStats(), (object) []);
        return ['key' => 'notice', 'label' => 'İhbar', 'daily' => (int) ($daily->toplam ?? 0), 'monthly' => (int) ($monthly->toplam ?? 0)];
    }
}
