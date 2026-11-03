<?php

namespace App\Service;

use App\Helper\Security;
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
    private int $firmaId;
    private int $kullaniciId;
    private ?array $erisim = null;

    public function __construct()
    {
        $this->klasorler = new DosyaKlasorModel();
        $this->dosyalar = new DosyaModel();
        $this->paylasimlar = new DosyaPaylasimModel();
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

    private function erisim(): array
    {
        if ($this->erisim === null) {
            $roller = (new UserRoleAssignmentModel())->activeRoleIdsForUser($this->kullaniciId);
            $this->erisim = ['super' => Gate::isSuperAdmin(), 'roles' => array_map('intval', $roller)];
        }
        return $this->erisim;
    }

    private function izinliKlasorler(): array
    {
        return $this->klasorler->erisilebilirKlasorIdleri($this->firmaId, $this->erisim());
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

    private function klasorGerekli($sifreliId): object
    {
        $id = $this->coz($sifreliId);
        if ($id === null) {
            throw new \DomainException('Klasör seçilmedi.');
        }
        $klasor = $this->klasorler->bul($id, $this->firmaId);
        if (!$klasor || !$this->klasorler->klasorErisebilir($id, $this->firmaId, $this->erisim())) {
            throw new \DomainException('Klasör bulunamadı.');
        }
        return $klasor;
    }

    private function hedefKlasorId($sifreliId): ?int
    {
        $id = $this->coz($sifreliId);
        if ($id === null) {
            return null;
        }
        $this->klasorGerekli($sifreliId);
        return $id;
    }

    private function dosyaGerekli($sifreliId): object
    {
        $id = $this->coz($sifreliId);
        $dosya = $id !== null ? $this->dosyalar->bul($id, $this->firmaId) : null;
        if (!$dosya || !$this->klasorler->klasorErisebilir($dosya->klasor_id !== null ? (int) $dosya->klasor_id : null, $this->firmaId, $this->erisim())) {
            throw new \DomainException('Dosya bulunamadı.');
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

    private function klasorSatiri(object $k): array
    {
        return [
            'id' => Security::encrypt($k->id),
            'parent_id' => $k->parent_id !== null ? Security::encrypt($k->parent_id) : null,
            'ad' => $k->ad,
            'dosya_sayisi' => (int) ($k->dosya_sayisi ?? 0),
        ];
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
            'onizlenebilir' => in_array($d->uzanti, ['pdf', 'jpg', 'jpeg', 'png'], true),
        ];
    }

    public function agac(): array
    {
        $liste = array_map(fn($k) => $this->klasorSatiri($k), $this->klasorler->agac($this->firmaId, $this->erisim()));
        $kokDosya = $this->dosyalar->klasordekiler($this->firmaId, null, '', $this->izinliKlasorler(), 1);
        return ['klasorler' => $liste, 'kok_dosya_var' => count($kokDosya) > 0];
    }

    public function ozet(): array
    {
        $o = $this->dosyalar->summary($this->firmaId, $this->izinliKlasorler());
        return [
            'toplam_dosya' => (int) $o->toplam_dosya,
            'toplam_boyut' => (int) $o->toplam_boyut,
            'bu_ay' => (int) $o->bu_ay,
            'toplam_klasor' => (int) $o->toplam_klasor,
        ];
    }

    public function liste(array $params): array
    {
        if (!empty($params['klasor_id']) && $params['klasor_id'] !== 'tum') {
            $klasorId = $params['klasor_id'] === 'kok' ? null : $this->hedefKlasorId($params['klasor_id']);
            $params['klasor_id'] = $klasorId === null ? 0 : $klasorId;
        }
        $sonuc = $this->dosyalar->ajaxList($params, $this->firmaId, $this->izinliKlasorler());
        $sonuc['data'] = array_map(fn($d) => $this->dosyaSatiri($d), $sonuc['rows']);
        unset($sonuc['rows']);
        return $sonuc;
    }

    public function klasorIcerik($sifreliKlasorId, string $arama): array
    {
        $klasorId = $this->hedefKlasorId($sifreliKlasorId);
        $arama = trim($arama);
        $yol = [];
        $altKlasorler = [];
        if ($arama === '') {
            $yol = array_map(fn($k) => ['id' => Security::encrypt($k->id), 'ad' => $k->ad], $this->klasorler->yol((int) $klasorId, $this->firmaId));
            foreach ($this->klasorler->agac($this->firmaId, $this->erisim()) as $k) {
                $ust = $k->parent_id !== null ? (int) $k->parent_id : null;
                if ($ust === $klasorId) {
                    $altKlasorler[] = $this->klasorSatiri($k);
                }
            }
        }
        $dosyalar = array_map(
            fn($d) => $this->dosyaSatiri($d),
            $this->dosyalar->klasordekiler($this->firmaId, $klasorId, $arama, $this->izinliKlasorler())
        );
        return ['yol' => $yol, 'klasorler' => $altKlasorler, 'dosyalar' => $dosyalar, 'aranan' => $arama !== ''];
    }

    public function klasorOlustur($sifreliParentId, string $ad): array
    {
        $parentId = $this->hedefKlasorId($sifreliParentId);
        $ad = $this->temizAd($ad, 190);
        if ($this->klasorler->ayniAdVarMi($this->firmaId, $parentId, $ad)) {
            throw new \DomainException('Bu konumda aynı adlı bir klasör var.');
        }
        $id = $this->klasorler->olustur($this->firmaId, $parentId, $ad, $this->kullaniciId);
        $this->log("Klasör oluşturuldu: {$ad} (#{$id})");
        return ['id' => Security::encrypt($id), 'ad' => $ad];
    }

    public function klasorYenidenAdlandir($sifreliId, string $ad): void
    {
        $klasor = $this->klasorGerekli($sifreliId);
        $ad = $this->temizAd($ad, 190);
        $parentId = $klasor->parent_id !== null ? (int) $klasor->parent_id : null;
        if ($this->klasorler->ayniAdVarMi($this->firmaId, $parentId, $ad, (int) $klasor->id)) {
            throw new \DomainException('Bu konumda aynı adlı bir klasör var.');
        }
        $this->klasorler->yenidenAdlandir((int) $klasor->id, $this->firmaId, $ad);
        $this->log("Klasör yeniden adlandırıldı: {$klasor->ad} → {$ad} (#{$klasor->id})");
    }

    public function klasorTasi($sifreliId, $sifreliHedefId): void
    {
        $klasor = $this->klasorGerekli($sifreliId);
        $hedef = $this->hedefKlasorId($sifreliHedefId);
        if ($this->klasorler->ayniAdVarMi($this->firmaId, $hedef, $klasor->ad, (int) $klasor->id)) {
            throw new \DomainException('Hedefte aynı adlı bir klasör var.');
        }
        if (!$this->klasorler->tasi((int) $klasor->id, $this->firmaId, $hedef)) {
            throw new \DomainException('Klasör bu konuma taşınamaz.');
        }
        $this->log("Klasör taşındı: {$klasor->ad} (#{$klasor->id}) → " . ($hedef ?? 'kök'));
    }

    public function klasorSil($sifreliId): void
    {
        $klasor = $this->klasorGerekli($sifreliId);
        $idler = $this->klasorler->sil((int) $klasor->id, $this->firmaId);
        $this->log("Klasör silindi: {$klasor->ad} (#{$klasor->id}, alt klasör dahil " . count($idler) . ' klasör)', SystemLogModel::LEVEL_IMPORTANT);
    }

    public function klasorRolleri($sifreliId): array
    {
        $klasor = $this->klasorGerekli($sifreliId);
        $secenekler = array_map(fn($r) => ['id' => (int) $r->id, 'ad' => $r->role_name], $this->klasorler->rolSecenekleri());
        return ['secili' => $this->klasorler->roller((int) $klasor->id), 'roller' => $secenekler];
    }

    public function klasorRolleriKaydet($sifreliId, array $roleIdleri): void
    {
        $klasor = $this->klasorGerekli($sifreliId);
        $gecerli = array_map(fn($r) => (int) $r->id, $this->klasorler->rolSecenekleri());
        $roleIdleri = array_values(array_intersect(array_map('intval', $roleIdleri), $gecerli));
        $this->klasorler->rolleriKaydet((int) $klasor->id, $roleIdleri);
        $this->log("Klasör rol erişimi güncellendi: {$klasor->ad} (#{$klasor->id}), rol sayısı " . count($roleIdleri), SystemLogModel::LEVEL_IMPORTANT);
    }

    public function dosyaYukle($sifreliKlasorId, array $dosya, string $aciklama = ''): array
    {
        $klasorId = $this->hedefKlasorId($sifreliKlasorId);

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
        $dosya = $this->dosyaGerekli($sifreliId);
        $ad = $this->temizAd($ad, 200);
        if (strtolower(pathinfo($ad, PATHINFO_EXTENSION)) !== $dosya->uzanti) {
            $ad .= '.' . $dosya->uzanti;
        }
        $this->dosyalar->yenidenAdlandir((int) $dosya->id, $this->firmaId, $ad);
        $this->log("Dosya yeniden adlandırıldı: {$dosya->orijinal_ad} → {$ad} (#{$dosya->id})");
    }

    public function dosyaTasi($sifreliId, $sifreliHedefId): void
    {
        $dosya = $this->dosyaGerekli($sifreliId);
        $hedef = $this->hedefKlasorId($sifreliHedefId);
        $this->dosyalar->tasi((int) $dosya->id, $this->firmaId, $hedef);
        $this->log("Dosya taşındı: {$dosya->orijinal_ad} (#{$dosya->id}) → " . ($hedef ?? 'kök'));
    }

    public function dosyaSil($sifreliId): void
    {
        $dosya = $this->dosyaGerekli($sifreliId);
        $this->dosyalar->sil((int) $dosya->id, $this->firmaId);
        $this->dosyalar->erisimLogla($this->firmaId, (int) $dosya->id, null, 'sil', $this->kullaniciId);
        $this->log("Dosya silindi: {$dosya->orijinal_ad} (#{$dosya->id})", SystemLogModel::LEVEL_IMPORTANT);
    }

    public function paylasimOlustur($sifreliDosyaId, int $gun, ?string $sifre, ?int $limit): array
    {
        $dosya = $this->dosyaGerekli($sifreliDosyaId);
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
        $dosya = $this->dosyaGerekli($sifreliDosyaId);
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
        if (!$paylasim) {
            throw new \DomainException('Paylaşım bulunamadı.');
        }
        $dosya = $this->dosyalar->bul((int) $paylasim->dosya_id, $this->firmaId);
        if (!$dosya || !$this->klasorler->klasorErisebilir($dosya->klasor_id !== null ? (int) $dosya->klasor_id : null, $this->firmaId, $this->erisim())) {
            throw new \DomainException('Paylaşım bulunamadı.');
        }
        $this->log("Paylaşım bağlantısı iptal edildi: paylaşım #{$id}, dosya #{$paylasim->dosya_id}", SystemLogModel::LEVEL_IMPORTANT);
    }

    public function akit($sifreliId, bool $satirIci): never
    {
        $dosya = $this->dosyaGerekli($sifreliId);
        $this->dosyalar->erisimLogla($this->firmaId, (int) $dosya->id, null, $satirIci ? 'goruntule' : 'indir', $this->kullaniciId);
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
