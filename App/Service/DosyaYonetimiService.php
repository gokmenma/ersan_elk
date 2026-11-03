<?php

namespace App\Service;

use App\Helper\Security;
use App\Model\DosyaErisimModel;
use App\Model\DosyaKlasorModel;
use App\Model\DosyaModel;
use App\Model\DosyaPaylasimModel;
use App\Model\SystemLogModel;
use App\Model\UserRoleAssignmentModel;

class DosyaYonetimiService
{
    private DosyaKlasorModel $klasorler;
    private DosyaModel $dosyalar;
    private DosyaPaylasimModel $paylasimlar;
    private DosyaErisimModel $erisimler;
    private int $firmaId;
    private int $kullaniciId;
    private ?array $baglam = null;
    private ?array $harita = null;
    private ?array $kapsam = null;

    public function __construct()
    {
        $this->klasorler = new DosyaKlasorModel();
        $this->dosyalar = new DosyaModel();
        $this->paylasimlar = new DosyaPaylasimModel();
        $this->erisimler = new DosyaErisimModel();
        $this->firmaId = (int) ($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);
        $this->kullaniciId = (int) ($_SESSION['user']->id ?? $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
        if ($this->firmaId <= 0 || $this->kullaniciId <= 0) {
            throw new \DomainException('Oturumunuz sonlanmış. Lütfen tekrar giriş yapın.');
        }
    }

    public function firmaId(): int
    {
        return $this->firmaId;
    }

    public function kullaniciId(): int
    {
        return $this->kullaniciId;
    }

    private function baglam(): array
    {
        if ($this->baglam === null) {
            $roller = (new UserRoleAssignmentModel())->activeRoleIdsForUser($this->kullaniciId);
            $yetkiler = $this->erisimler->kullaniciHaritasi($this->firmaId, $this->kullaniciId);
            $this->baglam = [
                'super' => false,
                'user' => $this->kullaniciId,
                'roles' => array_map('intval', $roller),
                'klasor_yetkileri' => $yetkiler['klasor'],
                'dosya_yetkileri' => $yetkiler['dosya'],
            ];
        }
        return $this->baglam;
    }

    private function harita(): array
    {
        return $this->harita ??= $this->klasorler->seviyeHaritasi($this->firmaId, $this->baglam());
    }

    private function kapsam(): array
    {
        if ($this->kapsam === null) {
            $ids = [];
            foreach ($this->harita() as $id => $satir) {
                if ($satir['seviye'] >= DosyaKlasorModel::SEVIYE_GORUNTULE) {
                    $ids[] = $id;
                }
            }
            $this->kapsam = [
                'super' => $this->baglam()['super'],
                'user' => $this->kullaniciId,
                'klasorler' => $ids,
                'dosyalar' => array_keys($this->baglam()['dosya_yetkileri']),
            ];
        }
        return $this->kapsam;
    }

    private function sifirla(): void
    {
        $this->baglam = $this->harita = $this->kapsam = null;
    }

    private function klasorSeviyesi(?int $id): int
    {
        return $id !== null ? ($this->harita()[$id]['seviye'] ?? 0) : 0;
    }

    private function dosyaSeviyesi(object $dosya): int
    {
        if ((int) $dosya->yukleyen_id === $this->kullaniciId) {
            return DosyaKlasorModel::SEVIYE_SAHIP;
        }
        $seviye = $dosya->klasor_id !== null ? $this->klasorSeviyesi((int) $dosya->klasor_id) : 0;
        return max($seviye, (int) ($this->baglam()['dosya_yetkileri'][(int) $dosya->id] ?? 0));
    }

    private function coz($deger): ?int
    {
        if ($deger === null || $deger === '' || $deger === '0' || $deger === 0 || $deger === 'kok') {
            return null;
        }
        $cozulen = Security::decrypt((string) $deger);
        if ($cozulen === false || $cozulen === null || !ctype_digit((string) $cozulen) || (int) $cozulen <= 0) {
            throw new \DomainException('Geçersiz kayıt.');
        }
        return (int) $cozulen;
    }

    private function klasorGerekli($sifreliId, int $asgari = DosyaKlasorModel::SEVIYE_GORUNTULE): object
    {
        $id = $this->coz($sifreliId);
        if ($id === null) {
            throw new \DomainException('Klasör seçilmedi.');
        }
        $klasor = $this->klasorler->bul($id, $this->firmaId);
        if (!$klasor || $this->klasorSeviyesi($id) < DosyaKlasorModel::SEVIYE_GORUNTULE) {
            throw new \DomainException('Klasör bulunamadı.');
        }
        if ($this->klasorSeviyesi($id) < $asgari) {
            throw new \DomainException('Bu işlem için klasör üzerinde yetkiniz yok.');
        }
        return $klasor;
    }

    private function hedefKlasorId($sifreliId, int $asgari = DosyaKlasorModel::SEVIYE_GORUNTULE): ?int
    {
        $id = $this->coz($sifreliId);
        if ($id === null) {
            return null;
        }
        $this->klasorGerekli($sifreliId, $asgari);
        return $id;
    }

    private function dosyaGerekli($sifreliId, int $asgari = DosyaKlasorModel::SEVIYE_GORUNTULE): object
    {
        $id = $this->coz($sifreliId);
        $dosya = $id !== null ? $this->dosyalar->bul($id, $this->firmaId) : null;
        if (!$dosya || $this->dosyaSeviyesi($dosya) < DosyaKlasorModel::SEVIYE_GORUNTULE) {
            throw new \DomainException('Dosya bulunamadı.');
        }
        if ($this->dosyaSeviyesi($dosya) < $asgari) {
            throw new \DomainException('Bu işlem için dosya üzerinde yetkiniz yok.');
        }
        return $dosya;
    }

    private function log(string $aciklama, int $seviye = SystemLogModel::LEVEL_INFO): void
    {
        (new SystemLogModel())->logAction($this->kullaniciId, 'Dosya Yönetimi', $aciklama, $seviye);
    }

    private function temizAd(string $ad, int $azami = 150): string
    {
        $ad = trim(preg_replace('/[\x00-\x1F\x7F\\\\\/]+/u', ' ', $ad) ?? '');
        $ad = preg_replace('/\s+/u', ' ', $ad) ?? '';
        $ad = trim($ad, " .");
        if ($ad === '') {
            throw new \DomainException('Ad boş olamaz.');
        }
        return mb_substr($ad, 0, $azami);
    }

    private function anahtar(int $id): string
    {
        $anahtar = (string) ($_ENV['ENCRYPTION_KEY'] ?? '');
        if ($anahtar === '') {
            throw new \RuntimeException('ENCRYPTION_KEY .env dosyasında tanımlı değil.');
        }
        return substr(hash_hmac('sha256', 'dosya-klasor|' . $id, $anahtar), 0, 24);
    }

    private function klasorSatiri(int $id, array $satir): array
    {
        return [
            'id' => Security::encrypt($id),
            'anahtar' => $this->anahtar($id),
            'ust_anahtar' => $satir['parent_id'] !== null ? $this->anahtar($satir['parent_id']) : null,
            'parent_id' => $satir['parent_id'] !== null ? Security::encrypt($satir['parent_id']) : null,
            'ad' => $satir['ad'],
            'alan' => $satir['alan'],
            'bolum' => $satir['bolum'],
            'kok' => $satir['kok'],
            'seviye' => $satir['seviye'],
            'dosya_sayisi' => $satir['dosya_sayisi'],
        ];
    }

    public static function onizlemeTuru(string $uzanti, int $boyut): ?string
    {
        $harita = [
            'pdf' => 'pdf', 'jpg' => 'goruntu', 'jpeg' => 'goruntu', 'png' => 'goruntu',
            'docx' => 'docx', 'xlsx' => 'tablo', 'xls' => 'tablo', 'csv' => 'tablo', 'txt' => 'metin',
        ];
        $tur = $harita[$uzanti] ?? null;
        if ($tur !== null && !in_array($tur, ['pdf', 'goruntu'], true) && $boyut > 10485760) {
            return null;
        }
        return $tur;
    }

    public function dosyaSatiri(object $d): array
    {
        return [
            'id' => Security::encrypt($d->id),
            'klasor_id' => $d->klasor_id !== null ? Security::encrypt($d->klasor_id) : null,
            'klasor_adi' => $d->klasor_adi ?? null,
            'ad' => $d->orijinal_ad,
            'uzanti' => $d->uzanti,
            'mime' => $d->mime_tipi,
            'boyut' => (int) $d->boyut,
            'aciklama' => $d->aciklama,
            'etiketler' => $d->etiketler,
            'yukleyen' => $d->yukleyen_adi ?? null,
            'tarih' => $d->created_at,
            'seviye' => $this->dosyaSeviyesi($d),
            'onizlenebilir' => in_array($d->uzanti, ['pdf', 'jpg', 'jpeg', 'png'], true),
            'onizleme' => self::onizlemeTuru((string) $d->uzanti, (int) $d->boyut),
        ];
    }

    private function ortakAlanYetkisi(): bool
    {
        return Gate::isSuperAdmin() || Gate::allows('dosya_yonetimi_ortak_alan');
    }

    public function agac(): array
    {
        $liste = [];
        $bolumler = ['dosyalarim' => true];
        foreach ($this->harita() as $id => $satir) {
            if ($satir['seviye'] < DosyaKlasorModel::SEVIYE_GORUNTULE) {
                continue;
            }
            $liste[] = $this->klasorSatiri($id, $satir);
            if ($satir['kok']) {
                $bolumler[$satir['bolum']] = true;
            }
        }
        if ($this->kapsam()['dosyalar']) {
            $bolumler['paylasilan'] = true;
        }
        if ($this->ortakAlanYetkisi()) {
            $bolumler['ortak'] = true;
        }
        return ['klasorler' => $liste, 'bolumler' => array_keys($bolumler), 'ortak_alan_yetkisi' => $this->ortakAlanYetkisi()];
    }

    public function ozet(): array
    {
        $o = $this->dosyalar->summary($this->firmaId, $this->kapsam());
        $klasorSayisi = count($this->kapsam()['klasorler']);
        return [
            'toplam_dosya' => (int) $o->toplam_dosya,
            'toplam_boyut' => (int) $o->toplam_boyut,
            'bu_ay' => (int) $o->bu_ay,
            'toplam_klasor' => $klasorSayisi,
        ];
    }

    private const BOLUMLER = ['dosyalarim', 'paylasilan', 'ortak'];

    private function bolumGerekli(?string $bolum): string
    {
        $bolum = $bolum ?: 'dosyalarim';
        if (!in_array($bolum, self::BOLUMLER, true)) {
            throw new \DomainException('Geçersiz bölüm.');
        }
        return $bolum;
    }

    public function liste(array $params): array
    {
        $klasor = (string) ($params['klasor_id'] ?? 'tum');
        unset($params['bolum']);
        if (str_starts_with($klasor, 'bolum:')) {
            $params['bolum'] = $this->bolumGerekli(substr($klasor, 6));
            unset($params['klasor_id']);
        } elseif ($klasor !== 'tum' && $klasor !== '') {
            $klasorId = $this->hedefKlasorId($klasor);
            if ($klasorId === null) {
                $params['bolum'] = 'dosyalarim';
                unset($params['klasor_id']);
            } else {
                $params['klasor_id'] = $klasorId;
            }
        }
        $sonuc = $this->dosyalar->ajaxList($params, $this->firmaId, $this->kapsam());
        $sonuc['data'] = array_map(fn($d) => $this->dosyaSatiri($d), $sonuc['rows']);
        unset($sonuc['rows']);
        return $sonuc;
    }

    public function klasorIcerik($sifreliKlasorId, string $arama, ?string $bolum = null): array
    {
        $klasorId = $this->hedefKlasorId($sifreliKlasorId);
        $arama = trim($arama);
        $bolum = $klasorId === null ? $this->bolumGerekli($bolum) : null;
        $yol = [];
        $altKlasorler = [];
        if ($arama === '') {
            $yol = array_map(fn($k) => ['id' => Security::encrypt($k->id), 'anahtar' => $this->anahtar((int) $k->id), 'ad' => $k->ad], $this->klasorler->yol((int) $klasorId, $this->firmaId));
            foreach ($this->harita() as $id => $satir) {
                if ($satir['seviye'] < DosyaKlasorModel::SEVIYE_GORUNTULE) {
                    continue;
                }
                $buKlasorunAlti = $klasorId === null ? ($satir['kok'] && $satir['bolum'] === $bolum) : ($satir['parent_id'] === $klasorId);
                if ($buKlasorunAlti) {
                    $altKlasorler[] = $this->klasorSatiri($id, $satir);
                }
            }
        }
        $dosyalar = array_map(
            fn($d) => $this->dosyaSatiri($d),
            $this->dosyalar->klasordekiler($this->firmaId, $klasorId, $arama, $this->kapsam(), $bolum)
        );

        $seviye = $klasorId !== null ? $this->klasorSeviyesi($klasorId) : ($bolum === 'dosyalarim' ? DosyaKlasorModel::SEVIYE_SAHIP : 0);
        $alan = $klasorId !== null ? ($this->harita()[$klasorId]['alan'] ?? 'kisisel') : ($bolum === 'ortak' ? 'ortak' : 'kisisel');
        return [
            'yol' => $yol, 'klasorler' => $altKlasorler, 'dosyalar' => $dosyalar, 'aranan' => $arama !== '',
            'seviye' => $seviye, 'alan' => $alan, 'bolum' => $bolum,
            'klasor_acabilir' => $klasorId !== null ? $seviye >= DosyaKlasorModel::SEVIYE_DUZENLE : ($bolum === 'dosyalarim' || ($bolum === 'ortak' && $this->ortakAlanYetkisi())),
        ];
    }

    public function klasorOlustur($sifreliParentId, string $ad, string $alan = 'kisisel'): array
    {
        $parentId = $this->hedefKlasorId($sifreliParentId, DosyaKlasorModel::SEVIYE_DUZENLE);
        $ad = $this->temizAd($ad, 190);
        if ($parentId !== null) {
            $alan = $this->harita()[$parentId]['alan'];
        } elseif ($alan === 'ortak') {
            if (!$this->ortakAlanYetkisi()) {
                throw new \DomainException('Ortak alanda klasör oluşturma yetkiniz yok.');
            }
        } else {
            $alan = 'kisisel';
        }
        if ($this->klasorler->ayniAdVarMi($this->firmaId, $parentId, $ad, 0, $parentId === null ? $this->kullaniciId : null, $alan)) {
            throw new \DomainException('Bu konumda aynı adlı bir klasör var.');
        }
        $id = $this->klasorler->olustur($this->firmaId, $parentId, $ad, $this->kullaniciId, $alan);
        $this->sifirla();
        $this->log("Klasör oluşturuldu: {$ad} (#{$id}, {$alan})");
        return ['id' => Security::encrypt($id), 'ad' => $ad];
    }

    public function klasorYenidenAdlandir($sifreliId, string $ad): void
    {
        $klasor = $this->klasorGerekli($sifreliId, DosyaKlasorModel::SEVIYE_DUZENLE);
        $ad = $this->temizAd($ad, 190);
        $parentId = $klasor->parent_id !== null ? (int) $klasor->parent_id : null;
        if ($this->klasorler->ayniAdVarMi($this->firmaId, $parentId, $ad, (int) $klasor->id, $parentId === null ? (int) $klasor->olusturan_id : null, $klasor->alan)) {
            throw new \DomainException('Bu konumda aynı adlı bir klasör var.');
        }
        $this->klasorler->yenidenAdlandir((int) $klasor->id, $this->firmaId, $ad);
        $this->log("Klasör yeniden adlandırıldı: {$klasor->ad} → {$ad} (#{$klasor->id})");
    }

    public function klasorTasi($sifreliId, $sifreliHedefId): void
    {
        $klasor = $this->klasorGerekli($sifreliId, DosyaKlasorModel::SEVIYE_SAHIP);
        $hedef = $this->hedefKlasorId($sifreliHedefId, DosyaKlasorModel::SEVIYE_DUZENLE);
        $hedefAlan = $hedef !== null ? $this->harita()[$hedef]['alan'] : $klasor->alan;
        if ($hedefAlan !== $klasor->alan) {
            throw new \DomainException('Klasör kişisel ve ortak alan arasında taşınamaz.');
        }
        if ($hedef === null && $klasor->alan === 'ortak' && !$this->ortakAlanYetkisi()) {
            throw new \DomainException('Ortak alanın kök dizinine taşıma yetkiniz yok.');
        }
        if ($this->klasorler->ayniAdVarMi($this->firmaId, $hedef, $klasor->ad, (int) $klasor->id, $hedef === null ? (int) $klasor->olusturan_id : null, $klasor->alan)) {
            throw new \DomainException('Hedefte aynı adlı bir klasör var.');
        }
        if (!$this->klasorler->tasi((int) $klasor->id, $this->firmaId, $hedef)) {
            throw new \DomainException('Klasör bu konuma taşınamaz.');
        }
        $this->sifirla();
        $this->log("Klasör taşındı: {$klasor->ad} (#{$klasor->id}) → " . ($hedef ?? 'kök'));
    }

    public function klasorSirala(array $sifreliIdler): void
    {
        $idler = [];
        $parent = false;
        $alan = null;
        foreach ($sifreliIdler as $sifreli) {
            $klasor = $this->klasorGerekli($sifreli, DosyaKlasorModel::SEVIYE_DUZENLE);
            $ust = $klasor->parent_id !== null ? (int) $klasor->parent_id : null;
            if ($parent !== false && ($ust !== $parent || $klasor->alan !== $alan)) {
                throw new \DomainException('Yalnızca aynı konumdaki klasörler sıralanabilir.');
            }
            $parent = $ust;
            $alan = $klasor->alan;
            $idler[] = (int) $klasor->id;
        }
        if (count($idler) < 2 || count($idler) !== count(array_unique($idler))) {
            return;
        }
        $this->klasorler->siraKaydet($this->firmaId, $idler);
        $this->sifirla();
        $this->log('Klasör sırası güncellendi (' . count($idler) . ' klasör)');
    }

    public function klasorSil($sifreliId): void
    {
        $klasor = $this->klasorGerekli($sifreliId, DosyaKlasorModel::SEVIYE_SAHIP);
        $idler = $this->klasorler->sil((int) $klasor->id, $this->firmaId);
        $this->erisimler->nesneErisimleriniTemizle($this->firmaId, 'klasor', $idler);
        $this->sifirla();
        $this->log("Klasör silindi: {$klasor->ad} (#{$klasor->id}, alt klasör dahil " . count($idler) . ' klasör)', SystemLogModel::LEVEL_IMPORTANT);
    }

    public function klasorRolleri($sifreliId): array
    {
        $klasor = $this->klasorGerekli($sifreliId, DosyaKlasorModel::SEVIYE_SAHIP);
        if ($klasor->alan !== 'ortak') {
            throw new \DomainException('Rol erişimi yalnızca ortak alandaki klasörlerde tanımlanır.');
        }
        $secenekler = array_map(fn($r) => ['id' => (int) $r->id, 'ad' => $r->role_name], $this->klasorler->rolSecenekleri());
        return ['secili' => $this->klasorler->roller((int) $klasor->id), 'roller' => $secenekler];
    }

    public function klasorRolleriKaydet($sifreliId, array $roleIdleri): void
    {
        $klasor = $this->klasorGerekli($sifreliId, DosyaKlasorModel::SEVIYE_SAHIP);
        if ($klasor->alan !== 'ortak') {
            throw new \DomainException('Rol erişimi yalnızca ortak alandaki klasörlerde tanımlanır.');
        }
        $gecerli = array_map(fn($r) => (int) $r->id, $this->klasorler->rolSecenekleri());
        $roleIdleri = array_values(array_intersect(array_map('intval', $roleIdleri), $gecerli));
        $this->klasorler->rolleriKaydet((int) $klasor->id, $roleIdleri);
        $this->sifirla();
        $this->log("Klasör rol erişimi güncellendi: {$klasor->ad} (#{$klasor->id}), rol sayısı " . count($roleIdleri), SystemLogModel::LEVEL_IMPORTANT);
    }

    public function dosyaYukle($sifreliKlasorId, array $dosya, string $aciklama = ''): array
    {
        $klasorId = $this->hedefKlasorId($sifreliKlasorId, DosyaKlasorModel::SEVIYE_DUZENLE);

        if (($dosya['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($dosya['tmp_name'] ?? '')) {
            throw new \DomainException('Dosya yüklenemedi.');
        }
        $boyut = (int) $dosya['size'];
        if ($boyut <= 0 || $boyut > DosyaModel::AZAMI_BOYUT) {
            throw new \DomainException('Dosya boyutu en fazla 25 MB olabilir.');
        }

        $orijinal = $this->temizAd(basename(str_replace('\\', '/', (string) $dosya['name'])), 200);
        $uzanti = strtolower(pathinfo($orijinal, PATHINFO_EXTENSION));
        if (!isset(DosyaModel::IZINLI_UZANTILAR[$uzanti])) {
            throw new \DomainException('Bu dosya türüne izin verilmiyor. İzinli: ' . implode(', ', array_keys(DosyaModel::IZINLI_UZANTILAR)));
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($dosya['tmp_name']);
        if (!in_array($mime, DosyaModel::IZINLI_UZANTILAR[$uzanti], true)) {
            throw new \DomainException('Dosya içeriği uzantısıyla uyuşmuyor.');
        }

        DosyaIcerikDogrulayici::dogrula($dosya['tmp_name'], $uzanti);

        $dizin = DosyaModel::depoDizini($this->firmaId);
        if (!is_dir($dizin) && !mkdir($dizin, 0775, true) && !is_dir($dizin)) {
            error_log('Dosya yönetimi dizini oluşturulamadı: ' . $dizin);
            throw new \DomainException('Dosya kaydedilemedi.');
        }
        $koruma = dirname($dizin) . '/.htaccess';
        if (!is_file($koruma)) {
            @file_put_contents($koruma, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }

        $diskAdi = bin2hex(random_bytes(16)) . '.' . $uzanti;
        if (!move_uploaded_file($dosya['tmp_name'], $dizin . '/' . $diskAdi)) {
            error_log('Dosya yönetimi dosyası taşınamadı: ' . $dizin . '/' . $diskAdi);
            throw new \DomainException('Dosya kaydedilemedi.');
        }

        try {
            $id = $this->dosyalar->olustur([
                'firma_id' => $this->firmaId,
                'klasor_id' => $klasorId,
                'dosya_adi' => $diskAdi,
                'orijinal_ad' => $orijinal,
                'uzanti' => $uzanti,
                'mime_tipi' => $mime,
                'boyut' => $boyut,
                'sha256' => hash_file('sha256', $dizin . '/' . $diskAdi),
                'aciklama' => $aciklama !== '' ? mb_substr(trim($aciklama), 0, 500) : null,
                'etiketler' => null,
                'yukleyen_id' => $this->kullaniciId,
            ]);
        } catch (\Throwable $e) {
            @unlink($dizin . '/' . $diskAdi);
            error_log('Dosya yönetimi kayıt hatası: ' . $e->getMessage());
            throw new \DomainException('Dosya kaydedilemedi.');
        }

        $this->log("Dosya yüklendi: {$orijinal} (#{$id}, {$boyut} bayt)");
        return ['id' => Security::encrypt($id), 'ad' => $orijinal];
    }

    public function dosyaYenidenAdlandir($sifreliId, string $ad): void
    {
        $dosya = $this->dosyaGerekli($sifreliId, DosyaKlasorModel::SEVIYE_DUZENLE);
        $ad = $this->temizAd($ad, 200);
        if (strtolower(pathinfo($ad, PATHINFO_EXTENSION)) !== $dosya->uzanti) {
            $ad .= '.' . $dosya->uzanti;
        }
        $this->dosyalar->yenidenAdlandir((int) $dosya->id, $this->firmaId, $ad);
        $this->log("Dosya yeniden adlandırıldı: {$dosya->orijinal_ad} → {$ad} (#{$dosya->id})");
    }

    public function dosyaTasi($sifreliId, $sifreliHedefId): void
    {
        $dosya = $this->dosyaGerekli($sifreliId, DosyaKlasorModel::SEVIYE_DUZENLE);
        $hedef = $this->hedefKlasorId($sifreliHedefId, DosyaKlasorModel::SEVIYE_DUZENLE);
        if ($hedef === null && (int) $dosya->yukleyen_id !== $this->kullaniciId) {
            throw new \DomainException('Dosya yalnızca yükleyen kişinin kök dizinine taşınabilir.');
        }
        $this->dosyalar->tasi((int) $dosya->id, $this->firmaId, $hedef);
        $this->log("Dosya taşındı: {$dosya->orijinal_ad} (#{$dosya->id}) → " . ($hedef ?? 'kök'));
    }

    public function dosyaSil($sifreliId): void
    {
        $dosya = $this->dosyaGerekli($sifreliId, DosyaKlasorModel::SEVIYE_SAHIP);
        $this->dosyalar->sil((int) $dosya->id, $this->firmaId);
        $this->erisimler->nesneErisimleriniTemizle($this->firmaId, 'dosya', [(int) $dosya->id]);
        $this->dosyalar->erisimLogla($this->firmaId, (int) $dosya->id, null, 'sil', $this->kullaniciId);
        $this->log("Dosya silindi: {$dosya->orijinal_ad} (#{$dosya->id})", SystemLogModel::LEVEL_IMPORTANT);
    }

    public function paylasimOlustur($sifreliDosyaId, int $gun, ?string $sifre, ?int $limit): array
    {
        $dosya = $this->dosyaGerekli($sifreliDosyaId, DosyaKlasorModel::SEVIYE_DUZENLE);
        $gun = max(1, min($gun, 90));
        $limit = ($limit !== null && $limit > 0) ? min($limit, 1000) : null;
        $sonuc = $this->paylasimlar->olustur($this->firmaId, (int) $dosya->id, $gun, $sifre, $limit, $this->kullaniciId);
        $this->dosyalar->erisimLogla($this->firmaId, (int) $dosya->id, $sonuc['id'], 'paylas', $this->kullaniciId);
        $this->log("Paylaşım bağlantısı oluşturuldu: {$dosya->orijinal_ad} (#{$dosya->id}, {$gun} gün)", SystemLogModel::LEVEL_IMPORTANT);
        return ['token' => $sonuc['token'], 'gun' => $gun];
    }

    public static function paylasimUrl(string $token): string
    {
        $betik = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $kok = '';
        foreach (['/mobile-spa/', '/views/', '/api/'] as $isaret) {
            $konum = strpos($betik, $isaret);
            if ($konum !== false) {
                $kok = substr($betik, 0, $konum);
                break;
            }
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        return ($https ? 'https' : 'http') . '://' . $host . $kok . '/dosya-paylas.php?t=' . $token;
    }

    public function paylasimListele($sifreliDosyaId): array
    {
        $dosya = $this->dosyaGerekli($sifreliDosyaId, DosyaKlasorModel::SEVIYE_DUZENLE);
        return array_map(static fn($p) => [
            'id' => Security::encrypt($p->id),
            'bitis' => $p->bitis_tarihi,
            'limit' => $p->indirme_limiti !== null ? (int) $p->indirme_limiti : null,
            'indirme' => (int) $p->indirme_sayisi,
            'sifreli' => (bool) $p->sifreli,
            'iptal' => $p->iptal_tarihi,
            'olusturan' => $p->olusturan_adi,
            'olusturma' => $p->created_at,
            'aktif' => $p->iptal_tarihi === null && strtotime((string) $p->bitis_tarihi) >= time(),
        ], $this->paylasimlar->listele((int) $dosya->id, $this->firmaId));
    }

    public function paylasimIptal($sifreliId): void
    {
        $id = $this->coz($sifreliId);
        $paylasim = $id !== null ? $this->paylasimlar->iptalEt($id, $this->firmaId) : null;
        $dosya = $paylasim ? $this->dosyalar->bul((int) $paylasim->dosya_id, $this->firmaId) : null;
        if (!$dosya || $this->dosyaSeviyesi($dosya) < DosyaKlasorModel::SEVIYE_DUZENLE) {
            throw new \DomainException('Paylaşım bulunamadı.');
        }
        $this->log("Paylaşım bağlantısı iptal edildi: paylaşım #{$id}, dosya #{$paylasim->dosya_id}", SystemLogModel::LEVEL_IMPORTANT);
    }

    private function erisimNesnesi(string $tur, $sifreliId): array
    {
        if ($tur === 'klasor') {
            $klasor = $this->klasorGerekli($sifreliId, DosyaKlasorModel::SEVIYE_DUZENLE);
            return [(int) $klasor->id, $this->klasorSeviyesi((int) $klasor->id), $klasor->ad];
        }
        if ($tur === 'dosya') {
            $dosya = $this->dosyaGerekli($sifreliId, DosyaKlasorModel::SEVIYE_DUZENLE);
            return [(int) $dosya->id, $this->dosyaSeviyesi($dosya), $dosya->orijinal_ad];
        }
        throw new \DomainException('Geçersiz nesne türü.');
    }

    public function erisimKullanicilari(): array
    {
        $liste = [];
        foreach ($this->erisimler->firmaKullanicilari($this->firmaId) as $u) {
            if ((int) $u->id !== $this->kullaniciId) {
                $liste[] = ['id' => (int) $u->id, 'ad' => $u->adi_soyadi];
            }
        }
        return $liste;
    }

    public function erisimListele(string $tur, $sifreliId): array
    {
        [$nesneId, $benimSeviyem] = $this->erisimNesnesi($tur, $sifreliId);
        $liste = array_map(static fn($e) => [
            'id' => Security::encrypt($e->id),
            'user_id' => (int) $e->user_id,
            'kullanici' => $e->kullanici_adi,
            'seviye' => $e->seviye,
            'veren' => $e->veren_adi,
            'tarih' => $e->created_at,
        ], $this->erisimler->listele($this->firmaId, $tur, $nesneId));
        return ['erisimler' => $liste, 'tam_yetki' => $benimSeviyem >= DosyaKlasorModel::SEVIYE_SAHIP];
    }

    public function erisimVer(string $tur, $sifreliId, int $hedefKullaniciId, string $seviye): void
    {
        [$nesneId, $benimSeviyem, $ad] = $this->erisimNesnesi($tur, $sifreliId);
        $seviye = $seviye === 'duzenle' ? 'duzenle' : 'goruntule';
        if ($benimSeviyem < DosyaKlasorModel::SEVIYE_SAHIP && $seviye !== 'goruntule') {
            throw new \DomainException('Düzenleme yetkisi yalnızca sahibi tarafından verilebilir.');
        }
        $izinli = array_column($this->erisimKullanicilari(), 'id');
        if (!in_array($hedefKullaniciId, $izinli, true)) {
            throw new \DomainException('Kullanıcı bulunamadı.');
        }
        $this->erisimler->ver($this->firmaId, $tur, $nesneId, $hedefKullaniciId, $seviye, $this->kullaniciId);
        $this->sifirla();
        $this->log("Erişim verildi: {$tur} '{$ad}' (#{$nesneId}) → kullanıcı #{$hedefKullaniciId} ({$seviye})", SystemLogModel::LEVEL_IMPORTANT);
    }

    public function erisimKaldir($sifreliErisimId): void
    {
        $id = $this->coz($sifreliErisimId);
        $erisim = $id !== null ? $this->erisimler->bul($id, $this->firmaId) : null;
        if (!$erisim) {
            throw new \DomainException('Erişim kaydı bulunamadı.');
        }
        $sifreliNesne = Security::encrypt($erisim->nesne_id);
        [, $benimSeviyem] = $this->erisimNesnesi($erisim->nesne_tur, $sifreliNesne);
        if ($benimSeviyem < DosyaKlasorModel::SEVIYE_SAHIP && (int) $erisim->veren_id !== $this->kullaniciId) {
            throw new \DomainException('Bu erişimi kaldırma yetkiniz yok.');
        }
        $this->erisimler->kaldir($id, $this->firmaId);
        $this->sifirla();
        $this->log("Erişim kaldırıldı: {$erisim->nesne_tur} #{$erisim->nesne_id} → kullanıcı #{$erisim->user_id}", SystemLogModel::LEVEL_IMPORTANT);
    }

    public function akit($sifreliId, bool $satirIci, bool $onizleme = false): never
    {
        $dosya = $this->dosyaGerekli($sifreliId);
        $this->dosyalar->erisimLogla($this->firmaId, (int) $dosya->id, null, ($satirIci || $onizleme) ? 'goruntule' : 'indir', $this->kullaniciId);
        self::dosyaGonder($dosya, $satirIci);
    }

    public static function dosyaGonder(object $dosya, bool $satirIci): never
    {
        $yol = DosyaModel::depoDizini((int) $dosya->firma_id) . '/' . basename((string) $dosya->dosya_adi);
        if (!is_file($yol)) {
            http_response_code(404);
            exit('Dosya bulunamadı.');
        }

        $onizlenebilir = in_array($dosya->uzanti, ['pdf', 'jpg', 'jpeg', 'png'], true);
        $satirIci = $satirIci && $onizlenebilir;
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $dosya->orijinal_ad) ?: 'dosya';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . ($onizlenebilir ? $dosya->mime_tipi : 'application/octet-stream'));
        header('Content-Disposition: ' . ($satirIci ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode((string) $dosya->orijinal_ad));
        header('Content-Length: ' . filesize($yol));
        header('X-Content-Type-Options: nosniff');
        if (!$satirIci) {
            header('Content-Security-Policy: sandbox');
        }
        header('Cache-Control: private, no-store');
        readfile($yol);
        exit;
    }
}
