<?php
namespace App\Service;

use App\Model\BildirimModel;
use App\Model\IhbarModel;
use App\Model\PersonelHareketleriModel;
use App\Model\UserNotificationPreferenceModel;
use DomainException;
use RuntimeException;
use Throwable;

class IhbarBildirimService
{
    private IhbarModel $model;

    public function __construct(?IhbarModel $model = null)
    {
        $this->model = $model ?? new IhbarModel();
    }

    public static function yetkiliMi(object $personel): bool
    {
        return ($personel->personel_tipi ?? 'standart') !== 'kaski_kacak';
    }

    public function dogrula(array $girdi): array
    {
        $tur = trim((string) ($girdi['ihbar_turu'] ?? ''));
        if (!in_array($tur, IhbarModel::IHBAR_TURLERI, true)) {
            throw new DomainException('Geçerli bir ihbar türü seçiniz.');
        }
        $ilce = trim((string) ($girdi['ilce'] ?? ''));
        if (!in_array($ilce, IhbarModel::ILCELER, true)) {
            throw new DomainException('Lütfen geçerli bir ilçe seçiniz.');
        }
        $mahalle = trim(strip_tags((string) ($girdi['mahalle'] ?? '')));
        if ($mahalle === '') {
            throw new DomainException('Mahalle alanı zorunludur.');
        }
        $aciklama = trim(strip_tags((string) ($girdi['aciklama'] ?? '')));
        if ($aciklama === '') {
            throw new DomainException('Açıklama zorunludur.');
        }
        $lat = $girdi['konum_lat'] ?? null;
        $lng = $girdi['konum_lng'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            throw new DomainException('Konum bilgisi zorunludur.');
        }
        $dogruluk = $girdi['konum_dogruluk'] ?? null;

        return [
            'ihbar_turu' => $tur,
            'ilce' => $ilce,
            'mahalle' => mb_substr($mahalle, 0, 150),
            'telefon' => mb_substr(trim((string) ($girdi['telefon'] ?? '')), 0, 30) ?: null,
            'komsu_abone_no' => mb_substr(trim((string) ($girdi['komsu_abone_no'] ?? '')), 0, 50) ?: null,
            'aciklama' => $aciklama,
            'konum_lat' => (float) $lat,
            'konum_lng' => (float) $lng,
            'konum_dogruluk' => is_numeric($dogruluk) ? (float) $dogruluk : null,
            'konum_link' => 'https://maps.google.com/?q=' . (float) $lat . ',' . (float) $lng,
        ];
    }

    public function olustur(array $veri, int $personelId, array $ilkFoto): array
    {
        if (($ilkFoto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new DomainException('En az 1 adet ihbar fotoğrafı yüklenmelidir.');
        }
        $ihbarId = $this->model->create($veri + ['bildiren_personel_id' => $personelId]);
        $this->fotografSakla($ihbarId, $ilkFoto);
        $atanan = $this->model->autoAssignNearest($ihbarId, $veri['konum_lat'], $veri['konum_lng'], $personelId);
        return ['id' => $ihbarId, 'atanan_personel_id' => $atanan];
    }

    public function duzenlenebilirKayit(int $ihbarId, int $personelId, int $firmaId): void
    {
        $ihbar = $ihbarId > 0 ? $this->model->getById($ihbarId) : null;
        if (!$ihbar || (int) $ihbar->firma_id !== $firmaId || (int) $ihbar->bildiren_personel_id !== $personelId) {
            throw new DomainException('İhbar bulunamadı veya size ait değil.');
        }
        if (in_array($ihbar->durum, ['olumlu', 'olumsuz'], true)) {
            throw new DomainException('Sonuçlandırılmış ihbarlar güncellenemez.');
        }
    }

    public function kayitSahibiMi(int $ihbarId, int $personelId, int $firmaId): bool
    {
        $ihbar = $ihbarId > 0 ? $this->model->getById($ihbarId) : null;
        return $ihbar && (int) $ihbar->firma_id === $firmaId && (int) $ihbar->bildiren_personel_id === $personelId;
    }

    public function guncelle(int $ihbarId, int $personelId, array $veri): void
    {
        $this->model->updateByBildiren($ihbarId, $personelId, $veri);
    }

    public function fotografEkle(int $ihbarId, array $dosya): void
    {
        if (($dosya['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new DomainException('Fotoğraf sunucuya ulaşmadı.');
        }
        if ($this->model->countFotograflar($ihbarId) >= IhbarModel::MAX_FOTO) {
            throw new DomainException('Bir ihbara en fazla ' . IhbarModel::MAX_FOTO . ' fotoğraf eklenebilir.');
        }
        $this->fotografSakla($ihbarId, $dosya);
    }

    public function bilgilendir(int $ihbarId, ?int $atananPersonelId, int $firmaId, string $bildirenAdi): void
    {
        try {
            (new PersonelHareketleriModel())->requestKacakPersonelKonumlari($firmaId);
        } catch (Throwable $e) {
            error_log('Mobil ihbar konum talebi gönderilemedi: ' . $e->getMessage());
        }
        if ($atananPersonelId) {
            try {
                (new PushNotificationService())->sendToPersonel($atananPersonelId, [
                    'title' => 'Yakınınızdaki ihbar',
                    'body' => 'Konumunuza en yakın yeni ihbar size otomatik yönlendirildi.',
                    'url' => '?page=ihbar&ihbar_id=' . $ihbarId . '&tip=gelen',
                ]);
            } catch (Throwable $e) {
                error_log('Mobil ihbar yönlendirme bildirimi gönderilemedi: ' . $e->getMessage());
            }
        }
        try {
            (new BildirimModel())->broadcastByPermissionForFirm(
                $firmaId,
                'ihbar/list',
                'Yeni İhbar',
                ($bildirenAdi !== '' ? $bildirenAdi : 'Personel') . ' tarafından yeni bir ihbar bildirildi.',
                'index.php?p=ihbar/list&ihbar_id=' . $ihbarId,
                'alert-triangle',
                'danger',
                UserNotificationPreferenceModel::TYPE_IHBAR_CREATED
            );
        } catch (Throwable $e) {
            error_log('Mobil ihbar bildirimi yöneticilere iletilemedi: ' . $e->getMessage());
        }
    }

    private function fotografSakla(int $ihbarId, array $dosya): void
    {
        try {
            $sonuc = IhbarModel::storeUploadedFoto($dosya, $ihbarId);
        } catch (Throwable $e) {
            if (preg_match('/Sadece JPG|formatında|en fazla|WebP/u', $e->getMessage())) {
                throw new DomainException($e->getMessage());
            }
            throw new RuntimeException('Fotoğraf kaydedilemedi.', 0, $e);
        }
        $this->model->addFotograf($ihbarId, $sonuc['yol'], $sonuc['kucuk']);
    }
}
