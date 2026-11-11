<?php
namespace App\Service;

use App\Helper\Security;
use App\Model\BildirimModel;
use App\Model\KacakKontrolModel;
use App\Model\UserNotificationPreferenceModel;
use DomainException;
use RuntimeException;
use Throwable;

class KacakBildirimService
{
    private const UUID_DESENI = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private KacakKontrolModel $model;

    public function __construct(?KacakKontrolModel $model = null)
    {
        $this->model = $model ?? new KacakKontrolModel();
    }

    public static function yetkiliMi(object $personel): bool
    {
        return stripos((string) ($personel->departman ?? ''), 'Kaçak') !== false;
    }

    public function oncekiKayitId(string $clientUuid): ?int
    {
        if ($clientUuid === '') {
            return null;
        }
        if (!preg_match(self::UUID_DESENI, $clientUuid)) {
            throw new DomainException('Geçersiz kayıt anahtarı.');
        }

        $kayit = $this->model->findByClientUuid($clientUuid, true);
        if (!$kayit) {
            return null;
        }
        if (!empty($kayit['silinme_tarihi'])) {
            $this->model->restoreRecord((int) $kayit['id']);
        }
        return (int) $kayit['id'];
    }

    public function dogrula(array $girdi, int $personelId, ?int $haricKayitId = null): array
    {
        $ekipArkadasiId = (int) ($girdi['ekip_arkadasi_id'] ?? 0);
        if ($ekipArkadasiId <= 0) {
            throw new DomainException('Lütfen ekip arkadaşınızı seçin.');
        }
        if ($ekipArkadasiId === $personelId) {
            throw new DomainException('Ekip arkadaşı olarak kendinizi seçemezsiniz.');
        }
        $gecerliAday = false;
        foreach ($this->model->getEkipAdaylari($personelId) as $aday) {
            if ((int) $aday['id'] === $ekipArkadasiId) {
                $gecerliAday = true;
                break;
            }
        }
        if (!$gecerliAday) {
            throw new DomainException('Seçilen personel geçerli değil.');
        }

        $ilce = trim((string) ($girdi['ilce'] ?? ''));
        if (!in_array($ilce, KacakKontrolModel::ILCELER, true)) {
            throw new DomainException('Lütfen geçerli bir ilçe seçin.');
        }

        $tarih = !empty($girdi['tarih']) && strtotime((string) $girdi['tarih'])
            ? date('Y-m-d', strtotime((string) $girdi['tarih']))
            : date('Y-m-d');
        if ($tarih > date('Y-m-d')) {
            throw new DomainException('İleri tarihli bildirim yapılamaz.');
        }
        if ($tarih < date('Y-m-d', strtotime('-7 days'))) {
            throw new DomainException('Geriye dönük en fazla 1 hafta (7 gün) önceki bir tarih için bildirim yapılabilir.');
        }

        $tutanakNo = trim((string) ($girdi['tutanak_no'] ?? ''));
        $sayacNo = trim((string) ($girdi['sayac_no'] ?? ''));
        $mukerrer = $this->model->findDuplicateRecord(['tutanak_no' => $tutanakNo, 'sayac_no' => $sayacNo, 'tarih' => $tarih], $haricKayitId);
        if ($mukerrer) {
            $kayit = $mukerrer['record'];
            $tarihMetni = !empty($kayit['tarih']) ? date('d.m.Y', strtotime($kayit['tarih'])) : '';
            $abone = !empty($kayit['abone_adi']) ? ' (' . $kayit['abone_adi'] . ')' : '';
            throw new DomainException($mukerrer['type'] === 'tutanak_no'
                ? "Mükerrer kayıt: '{$tutanakNo}' numaralı tutanak daha önce{$abone} {$tarihMetni} tarihinde bildirilmiş."
                : "Mükerrer kayıt: '{$sayacNo}' numaralı sayaç için {$tarihMetni} tarihinde zaten tutanak var.{$abone}");
        }

        $aboneTc = trim((string) ($girdi['abone_tc'] ?? ''));
        if ($aboneTc === '') {
            throw new DomainException('TC Kimlik veya Vergi No alanı zorunludur.');
        }
        if (!ctype_digit($aboneTc) || (strlen($aboneTc) !== 10 && strlen($aboneTc) !== 11)) {
            throw new DomainException('TC Kimlik No 11 haneli veya Vergi No 10 haneli rakamlardan oluşmalıdır.');
        }
        $aboneDogum = trim((string) ($girdi['abone_dogum_tarihi'] ?? ''));
        if (strlen($aboneTc) === 11 && $aboneDogum === '') {
            throw new DomainException('TC Kimlik No girildiğinde Doğum Tarihi alanı zorunludur.');
        }
        if ($aboneDogum !== '' && !strtotime($aboneDogum)) {
            throw new DomainException('Geçerli bir doğum tarihi giriniz.');
        }

        $tur = trim((string) ($girdi['tur'] ?? 'Kaçak'));

        return [
            'tarih' => $tarih,
            'personel_ids' => [$personelId, $ekipArkadasiId],
            'bildiren_personel_id' => $personelId,
            'ilce' => $ilce,
            'tur' => in_array($tur, KacakKontrolModel::TURLER, true) ? $tur : 'Kaçak',
            'tutanak_no' => $tutanakNo !== '' ? $tutanakNo : null,
            'abone_adi' => trim((string) ($girdi['abone_adi'] ?? '')) ?: null,
            'abone_tc' => $aboneTc,
            'abone_dogum_tarihi' => $aboneDogum !== '' ? date('Y-m-d', strtotime($aboneDogum)) : null,
            'abone_tel' => trim((string) ($girdi['abone_tel'] ?? '')) ?: null,
            'sayac_no' => $sayacNo !== '' ? $sayacNo : null,
            'sayac_markasi' => trim((string) ($girdi['sayac_markasi'] ?? '')) ?: null,
            'endeks' => trim((string) ($girdi['endeks'] ?? '')) ?: null,
            'sayi' => max(1, (int) ($girdi['sayi'] ?? 1)),
            'aciklama' => trim(strip_tags((string) ($girdi['aciklama'] ?? ''))) ?: null,
        ];
    }

    public function olustur(array $veri, array $tutanakDosyasi, ?string $tutanakCekim, string $clientUuid, ?string $offlineOlusturma, int $beklenenFoto): int
    {
        if (($tutanakDosyasi['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($tutanakDosyasi['name'])) {
            throw new DomainException('Kaçak bildirimi yapabilmek için tutanak fotoğrafı zorunludur.');
        }

        $kacakId = $this->model->createRecord($veri + [
            'kaynak' => 'pwa',
            'client_uuid' => $clientUuid,
            'offline_olusturma' => $offlineOlusturma,
            'beklenen_foto_sayisi' => max(1, $beklenenFoto),
            'onay_durumu' => 'beklemede',
        ]);

        $sunucuCekim = null;
        $yol = $this->dosyaSakla($tutanakDosyasi, $kacakId, 'tutanak', $sunucuCekim);
        $this->model->addPhoto($kacakId, 'tutanak', $yol, (string) $tutanakDosyasi['name'], (int) $veri['bildiren_personel_id'], null, null,
            KacakKontrolModel::cekimBilgisiCoz($sunucuCekim, $tutanakCekim));

        return $kacakId;
    }

    public function duzenlenebilirKayit(int $kacakId, int $personelId, int $firmaId): array
    {
        $kayit = $this->model->getRecord($kacakId);
        if (!$kayit || (int) $kayit['firma_id'] !== $firmaId || (int) $kayit['bildiren_personel_id'] !== $personelId || !empty($kayit['silinme_tarihi'])) {
            throw new DomainException('Bu kaydı düzenleme yetkiniz bulunmuyor.');
        }
        if (($kayit['onay_durumu'] ?? '') !== 'beklemede' || ($kayit['durum'] ?? '') === 'iptal') {
            throw new DomainException('Kayıt artık düzenlenemiyor; onaylanmış, reddedilmiş veya iptal edilmiş.');
        }
        return $kayit;
    }

    public function guncelle(int $kacakId, int $personelId, array $veri, array $tutanakDosyasi, ?string $tutanakCekim): void
    {
        $yeniTutanak = ($tutanakDosyasi['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($tutanakDosyasi['name']);
        if (!$yeniTutanak && $this->model->countPhotos($kacakId, 'tutanak') === 0) {
            throw new DomainException('Kaçak bildirimi için tutanak fotoğrafı zorunludur.');
        }

        $alanlar = $veri;
        unset($alanlar['bildiren_personel_id']);
        if (!$this->model->updatePendingByReporter($kacakId, $personelId, $alanlar)) {
            throw new DomainException('Kayıt güncellenemedi; durumu değişmiş olabilir.');
        }

        if ($yeniTutanak) {
            $sunucuCekim = null;
            $yol = $this->dosyaSakla($tutanakDosyasi, $kacakId, 'tutanak', $sunucuCekim);
            $this->model->addPhoto($kacakId, 'tutanak', $yol, (string) $tutanakDosyasi['name'], $personelId, null, null,
                KacakKontrolModel::cekimBilgisiCoz($sunucuCekim, $tutanakCekim));
        }
    }

    public function sahaFotografiEkle(int $kacakId, array $dosya, ?string $cekim, int $personelId, int $sira): void
    {
        if ($sira < 0 || $sira >= KacakKontrolModel::MAX_SAHA_FOTO) {
            throw new DomainException('Geçersiz fotoğraf sırası.');
        }
        if ($this->model->countPhotos($kacakId, 'saha') >= KacakKontrolModel::MAX_SAHA_FOTO) {
            throw new DomainException('Saha fotoğraf sınırına ulaşıldı.');
        }
        $sunucuCekim = null;
        $yol = $this->dosyaSakla($dosya, $kacakId, 'saha', $sunucuCekim);
        $this->model->addPhoto($kacakId, 'saha', $yol, (string) ($dosya['name'] ?? ''), $personelId, null, $sira,
            KacakKontrolModel::cekimBilgisiCoz($sunucuCekim, $cekim));
    }

    public function kayitSahibiMi(int $kacakId, int $personelId, int $firmaId): bool
    {
        $kayit = $this->model->getRecord($kacakId);
        if (!$kayit || (int) $kayit['firma_id'] !== $firmaId || ($kayit['durum'] ?? '') === 'iptal' || !empty($kayit['silinme_tarihi'])) {
            return false;
        }
        if ((int) $kayit['bildiren_personel_id'] === $personelId) {
            return true;
        }
        return in_array($personelId, array_map('intval', explode(',', (string) ($kayit['personel_ids'] ?? ''))), true);
    }

    public function yoneticileriBilgilendir(int $kacakId, array $veri, string $bildirenAdi): void
    {
        try {
            (new BildirimModel())->broadcastByPermission(
                'kacak/list',
                'Yeni Kaçak Bildirimi',
                $bildirenAdi . ', ' . date('d.m.Y', strtotime($veri['tarih'])) . ' tarihli ' . $veri['tur'] . ' tutanağı bildirdi (' . $veri['ilce'] . ').',
                'index.php?p=kacak/list&tab=onay&kacak_token=' . Security::encrypt($kacakId),
                'shield',
                'info',
                UserNotificationPreferenceModel::TYPE_KACAK_CREATED
            );
        } catch (Throwable $e) {
            error_log('Mobil kaçak bildirimi yöneticilere iletilemedi: ' . $e->getMessage());
        }
    }

    private function dosyaSakla(array $dosya, int $kacakId, string $tur, ?array &$cekim): string
    {
        try {
            return $this->model->storeUploadedFile($dosya, $kacakId, $tur, $cekim);
        } catch (Throwable $e) {
            if (preg_match('/Sadece JPG|Dosya boyutu/u', $e->getMessage())) {
                throw new DomainException($e->getMessage());
            }
            throw new RuntimeException('Fotoğraf kaydedilemedi.', 0, $e);
        }
    }
}
