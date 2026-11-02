<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class ZimmetDevirModel extends Model
{
    protected $table = 'zimmet_devirleri';

    public const TUR_ARAC = 'arac';

    public const DEVIR_GECICI = 'gecici';
    public const DEVIR_KALICI = 'kalici';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    private function selectSql(): string
    {
        return "
            SELECT zd.*,
                   pv.adi_soyadi AS veren_personel_adi,
                   pa.adi_soyadi AS alan_personel_adi,
                   COALESCE(uo.adi_soyadi, uo.user_name) AS olusturan_kullanici_adi,
                   COALESCE(ui.adi_soyadi, ui.user_name) AS iade_alan_kullanici_adi,
                   CASE zd.zimmet_turu WHEN 'arac' THEN ar.plaka END AS nesne_adi,
                   CASE zd.zimmet_turu WHEN 'arac' THEN TRIM(CONCAT_WS(' ', ar.marka, ar.model)) END AS nesne_detay,
                   CASE zd.zimmet_turu WHEN 'arac' THEN az.personel_id END AS asil_personel_id,
                   CASE zd.zimmet_turu WHEN 'arac' THEN az.durum END AS asil_zimmet_durum,
                   pasil.adi_soyadi AS asil_personel_adi
            FROM {$this->table} zd
            LEFT JOIN personel pv ON pv.id = zd.veren_personel_id
            LEFT JOIN personel pa ON pa.id = zd.alan_personel_id
            LEFT JOIN users uo ON uo.id = zd.olusturan_kullanici_id
            LEFT JOIN users ui ON ui.id = zd.iade_alan_kullanici_id
            LEFT JOIN araclar ar ON zd.zimmet_turu = 'arac' AND ar.id = zd.nesne_id
            LEFT JOIN arac_zimmetleri az ON zd.zimmet_turu = 'arac' AND az.id = zd.kaynak_zimmet_id
            LEFT JOIN personel pasil ON pasil.id = az.personel_id
        ";
    }

    private function userId(): ?int
    {
        return (int) ($_SESSION['user_id'] ?? 0) ?: null;
    }

    private function rollbackVeFirlat(\Throwable $e, string $mesaj): never
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        if ($e instanceof \DomainException) {
            throw $e;
        }
        error_log('ZimmetDevirModel - ' . $e->getMessage());
        throw new \RuntimeException($mesaj);
    }

    private function bekleyenleriIptalEt(string $zimmetTuru, int $kaynakZimmetId, string $neden): int
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET durum = 'iptal',
                yanit_tarihi = NOW(),
                red_nedeni = :neden
            WHERE firma_id = :firma_id
              AND zimmet_turu = :zimmet_turu
              AND kaynak_zimmet_id = :kaynak_zimmet_id
              AND durum = 'beklemede'
        ");
        $stmt->execute([
            'neden' => mb_substr($neden, 0, 500),
            'firma_id' => $this->firmaId(),
            'zimmet_turu' => $zimmetTuru,
            'kaynak_zimmet_id' => $kaynakZimmetId,
        ]);
        return $stmt->rowCount();
    }

    private function aktifleriKilitle(string $zimmetTuru, int $kaynakZimmetId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, veren_personel_id, alan_personel_id, teslim_tarihi, durum
            FROM {$this->table}
            WHERE firma_id = :firma_id
              AND zimmet_turu = :zimmet_turu
              AND kaynak_zimmet_id = :kaynak_zimmet_id
              AND durum IN ('aktif', 'beklemede')
              AND deleted_at IS NULL
            FOR UPDATE
        ");
        $stmt->execute([
            'firma_id' => $this->firmaId(),
            'zimmet_turu' => $zimmetTuru,
            'kaynak_zimmet_id' => $kaynakZimmetId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    private function aktifiKapat(int $id, string $tarih, ?int $sayac, ?string $not): void
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET durum = 'iade_edildi',
                iade_tarihi = GREATEST(teslim_tarihi, CAST(:iade_tarihi AS DATETIME)),
                iade_sayac = :iade_sayac,
                iade_notu = :iade_notu,
                iade_alan_kullanici_id = :user_id
            WHERE id = :id AND firma_id = :firma_id AND durum = 'aktif'
        ");
        $stmt->execute([
            'iade_tarihi' => $tarih,
            'iade_sayac' => $sayac,
            'iade_notu' => $not,
            'user_id' => $this->userId(),
            'id' => $id,
            'firma_id' => $this->firmaId(),
        ]);
    }

    public function getById(int $id): ?object
    {
        $stmt = $this->db->prepare($this->selectSql() . "
            WHERE zd.id = :id AND zd.firma_id = :firma_id AND zd.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'firma_id' => $this->firmaId()]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function getAktifDevir(string $zimmetTuru, int $kaynakZimmetId): ?object
    {
        $stmt = $this->db->prepare($this->selectSql() . "
            WHERE zd.firma_id = :firma_id
              AND zd.zimmet_turu = :zimmet_turu
              AND zd.kaynak_zimmet_id = :kaynak_zimmet_id
              AND zd.durum = 'aktif'
              AND zd.deleted_at IS NULL
            ORDER BY zd.teslim_tarihi DESC, zd.id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'firma_id' => $this->firmaId(),
            'zimmet_turu' => $zimmetTuru,
            'kaynak_zimmet_id' => $kaynakZimmetId,
        ]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function getAktifDevirHaritasi(string $zimmetTuru): array
    {
        $stmt = $this->db->prepare($this->selectSql() . "
            WHERE zd.firma_id = :firma_id
              AND zd.zimmet_turu = :zimmet_turu
              AND zd.durum = 'aktif'
              AND zd.deleted_at IS NULL
            ORDER BY zd.teslim_tarihi ASC, zd.id ASC
        ");
        $stmt->execute(['firma_id' => $this->firmaId(), 'zimmet_turu' => $zimmetTuru]);

        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $devir) {
            $harita[(int) $devir->kaynak_zimmet_id] = $devir;
        }
        return $harita;
    }

    public function getByZimmet(string $zimmetTuru, int $kaynakZimmetId): array
    {
        $stmt = $this->db->prepare($this->selectSql() . "
            WHERE zd.firma_id = :firma_id
              AND zd.zimmet_turu = :zimmet_turu
              AND zd.kaynak_zimmet_id = :kaynak_zimmet_id
            ORDER BY zd.teslim_tarihi DESC, zd.id DESC
        ");
        $stmt->execute([
            'firma_id' => $this->firmaId(),
            'zimmet_turu' => $zimmetTuru,
            'kaynak_zimmet_id' => $kaynakZimmetId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getByNesne(string $zimmetTuru, int $nesneId): array
    {
        $stmt = $this->db->prepare($this->selectSql() . "
            WHERE zd.firma_id = :firma_id
              AND zd.zimmet_turu = :zimmet_turu
              AND zd.nesne_id = :nesne_id
            ORDER BY zd.teslim_tarihi DESC, zd.id DESC
        ");
        $stmt->execute([
            'firma_id' => $this->firmaId(),
            'zimmet_turu' => $zimmetTuru,
            'nesne_id' => $nesneId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function geciciDevirOlustur(array $data, ?string $zincirNotu = null): int
    {
        $firmaId = $this->firmaId();
        $userId = $this->userId();

        try {
            $this->db->beginTransaction();

            $aktifler = array_filter(
                $this->aktifleriKilitle($data['zimmet_turu'], (int) $data['kaynak_zimmet_id']),
                static fn($r) => $r->durum === 'aktif'
            );

            foreach ($aktifler as $aktif) {
                if ((int) $aktif->alan_personel_id !== (int) $data['veren_personel_id']) {
                    throw new \DomainException('Zimmetin geçici teslim durumu değişmiş. Listeyi yenileyip tekrar deneyin.');
                }
                if (strtotime($aktif->teslim_tarihi) > strtotime($data['teslim_tarihi'])) {
                    throw new \DomainException('Teslim tarihi, mevcut geçici teslimin tarihinden önce olamaz.');
                }
            }

            foreach ($aktifler as $aktif) {
                $this->aktifiKapat((int) $aktif->id, $data['teslim_tarihi'], $data['teslim_sayac'] ?? null, $zincirNotu);
            }
            $this->bekleyenleriIptalEt($data['zimmet_turu'], (int) $data['kaynak_zimmet_id'], 'Yönetici tarafından geçici teslim yapıldı.');

            $ekle = $this->db->prepare("
                INSERT INTO {$this->table}
                    (firma_id, zimmet_turu, kaynak_zimmet_id, nesne_id, devir_turu,
                     veren_personel_id, alan_personel_id, teslim_tarihi, planlanan_iade_tarihi,
                     teslim_sayac, durum, aciklama, olusturan_kullanici_id)
                VALUES
                    (:firma_id, :zimmet_turu, :kaynak_zimmet_id, :nesne_id, :devir_turu,
                     :veren_personel_id, :alan_personel_id, :teslim_tarihi, :planlanan_iade_tarihi,
                     :teslim_sayac, 'aktif', :aciklama, :olusturan_kullanici_id)
            ");
            $ekle->execute([
                'firma_id' => $firmaId,
                'zimmet_turu' => $data['zimmet_turu'],
                'kaynak_zimmet_id' => $data['kaynak_zimmet_id'],
                'nesne_id' => $data['nesne_id'],
                'devir_turu' => self::DEVIR_GECICI,
                'veren_personel_id' => $data['veren_personel_id'],
                'alan_personel_id' => $data['alan_personel_id'],
                'teslim_tarihi' => $data['teslim_tarihi'],
                'planlanan_iade_tarihi' => $data['planlanan_iade_tarihi'] ?? null,
                'teslim_sayac' => $data['teslim_sayac'] ?? null,
                'aciklama' => $data['aciklama'] ?? null,
                'olusturan_kullanici_id' => $userId,
            ]);
            $yeniId = (int) $this->db->lastInsertId();

            $this->db->commit();
            return $yeniId;
        } catch (\Throwable $e) {
            $this->rollbackVeFirlat($e, 'Geçici teslim kaydı oluşturulamadı.');
        }
    }

    public function iadeAl(int $id, string $iadeTarihi, ?int $iadeSayac, ?string $iadeNotu): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET durum = 'iade_edildi',
                iade_tarihi = :iade_tarihi,
                iade_sayac = :iade_sayac,
                iade_notu = :iade_notu,
                iade_alan_kullanici_id = :user_id
            WHERE id = :id
              AND firma_id = :firma_id
              AND durum = 'aktif'
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            'iade_tarihi' => $iadeTarihi,
            'iade_sayac' => $iadeSayac,
            'iade_notu' => $iadeNotu,
            'user_id' => (int) ($_SESSION['user_id'] ?? 0) ?: null,
            'id' => $id,
            'firma_id' => $this->firmaId(),
        ]);
        if ($stmt->rowCount() === 0) {
            return false;
        }
        $kayit = $this->getById($id);
        if ($kayit) {
            $this->bekleyenleriIptalEt($kayit->zimmet_turu, (int) $kayit->kaynak_zimmet_id, 'Araç yönetici tarafından asıl zimmet sahibine geri alındı.');
        }
        return true;
    }

    public function iptalEt(int $id, ?string $not): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET durum = 'iptal',
                iade_notu = CONCAT_WS(' | ', NULLIF(iade_notu, ''), :not_metni),
                iade_alan_kullanici_id = :user_id,
                deleted_at = NOW()
            WHERE id = :id
              AND firma_id = :firma_id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            'not_metni' => $not,
            'user_id' => (int) ($_SESSION['user_id'] ?? 0) ?: null,
            'id' => $id,
            'firma_id' => $this->firmaId(),
        ]);
        return $stmt->rowCount() > 0;
    }

    public function aktifDevirleriKapat(string $zimmetTuru, int $kaynakZimmetId, string $iadeTarihi, ?int $iadeSayac, string $iadeNotu): int
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET durum = 'iade_edildi',
                iade_tarihi = GREATEST(teslim_tarihi, CAST(:iade_tarihi AS DATETIME)),
                iade_sayac = :iade_sayac,
                iade_notu = :iade_notu,
                iade_alan_kullanici_id = :user_id
            WHERE firma_id = :firma_id
              AND zimmet_turu = :zimmet_turu
              AND kaynak_zimmet_id = :kaynak_zimmet_id
              AND durum = 'aktif'
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            'iade_tarihi' => $iadeTarihi,
            'iade_sayac' => $iadeSayac,
            'iade_notu' => $iadeNotu,
            'user_id' => (int) ($_SESSION['user_id'] ?? 0) ?: null,
            'firma_id' => $this->firmaId(),
            'zimmet_turu' => $zimmetTuru,
            'kaynak_zimmet_id' => $kaynakZimmetId,
        ]);
        $kapanan = $stmt->rowCount();
        $this->bekleyenleriIptalEt($zimmetTuru, $kaynakZimmetId, 'Asıl zimmet iade edildi.');
        return $kapanan;
    }

    public function getBekleyenTalep(string $zimmetTuru, int $kaynakZimmetId): ?object
    {
        $stmt = $this->db->prepare($this->selectSql() . "
            WHERE zd.firma_id = :firma_id
              AND zd.zimmet_turu = :zimmet_turu
              AND zd.kaynak_zimmet_id = :kaynak_zimmet_id
              AND zd.durum = 'beklemede'
            ORDER BY zd.id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'firma_id' => $this->firmaId(),
            'zimmet_turu' => $zimmetTuru,
            'kaynak_zimmet_id' => $kaynakZimmetId,
        ]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function getBekleyenTalepHaritasi(string $zimmetTuru): array
    {
        $stmt = $this->db->prepare($this->selectSql() . "
            WHERE zd.firma_id = :firma_id
              AND zd.zimmet_turu = :zimmet_turu
              AND zd.durum = 'beklemede'
            ORDER BY zd.id ASC
        ");
        $stmt->execute(['firma_id' => $this->firmaId(), 'zimmet_turu' => $zimmetTuru]);

        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $talep) {
            $harita[(int) $talep->kaynak_zimmet_id] = $talep;
        }
        return $harita;
    }

    public function listele(string $zimmetTuru, ?string $durum = null): array
    {
        $sql = $this->selectSql() . "
            WHERE zd.firma_id = :firma_id
              AND zd.zimmet_turu = :zimmet_turu
        ";
        $params = ['firma_id' => $this->firmaId(), 'zimmet_turu' => $zimmetTuru];
        if ($durum !== null && $durum !== '') {
            $sql .= " AND zd.durum = :durum";
            $params['durum'] = $durum;
        }
        $sql .= " ORDER BY FIELD(zd.durum, 'beklemede', 'aktif') DESC, COALESCE(zd.talep_tarihi, zd.teslim_tarihi) DESC, zd.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function ozet(string $zimmetTuru): object
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS toplam,
                   COALESCE(SUM(durum = 'beklemede'), 0) AS beklemede,
                   COALESCE(SUM(durum = 'aktif'), 0) AS aktif,
                   COALESCE(SUM(durum IN ('iade_edildi', 'tamamlandi')), 0) AS kapanan,
                   COALESCE(SUM(durum IN ('reddedildi', 'iptal')), 0) AS iptal_red
            FROM {$this->table}
            WHERE firma_id = :firma_id AND zimmet_turu = :zimmet_turu
        ");
        $stmt->execute(['firma_id' => $this->firmaId(), 'zimmet_turu' => $zimmetTuru]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function getPersonelHareketleri(string $zimmetTuru, int $personelId, int $limit = 50): array
    {
        $stmt = $this->db->prepare($this->selectSql() . "
            WHERE zd.firma_id = :firma_id
              AND zd.zimmet_turu = :zimmet_turu
              AND (zd.veren_personel_id = :pid1 OR zd.alan_personel_id = :pid2)
              AND zd.deleted_at IS NULL
            ORDER BY FIELD(zd.durum, 'beklemede', 'aktif') DESC, COALESCE(zd.talep_tarihi, zd.teslim_tarihi) DESC, zd.id DESC
            LIMIT " . max(1, min(200, $limit)) . "
        ");
        $stmt->execute([
            'firma_id' => $this->firmaId(),
            'zimmet_turu' => $zimmetTuru,
            'pid1' => $personelId,
            'pid2' => $personelId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function talepOlustur(array $data, int $asilPersonelId): int
    {
        try {
            $this->db->beginTransaction();

            $kayitlar = $this->aktifleriKilitle($data['zimmet_turu'], (int) $data['kaynak_zimmet_id']);
            $kullanan = $asilPersonelId;
            foreach ($kayitlar as $kayit) {
                if ($kayit->durum === 'beklemede') {
                    throw new \DomainException('Bu araç için yanıt bekleyen bir devir talebi zaten var.');
                }
                $kullanan = (int) $kayit->alan_personel_id;
            }
            if ($kullanan !== (int) $data['veren_personel_id']) {
                throw new \DomainException('Araç şu anda sizde görünmüyor. Sayfayı yenileyip tekrar deneyin.');
            }

            $ekle = $this->db->prepare("
                INSERT INTO {$this->table}
                    (firma_id, zimmet_turu, kaynak_zimmet_id, nesne_id, devir_turu, kaynak,
                     veren_personel_id, alan_personel_id, talep_tarihi, teslim_tarihi,
                     planlanan_iade_tarihi, teslim_sayac, durum, aciklama)
                VALUES
                    (:firma_id, :zimmet_turu, :kaynak_zimmet_id, :nesne_id, :devir_turu, 'pwa',
                     :veren_personel_id, :alan_personel_id, NOW(), NOW(),
                     :planlanan_iade_tarihi, :teslim_sayac, 'beklemede', :aciklama)
            ");
            $ekle->execute([
                'firma_id' => $this->firmaId(),
                'zimmet_turu' => $data['zimmet_turu'],
                'kaynak_zimmet_id' => $data['kaynak_zimmet_id'],
                'nesne_id' => $data['nesne_id'],
                'devir_turu' => self::DEVIR_GECICI,
                'veren_personel_id' => $data['veren_personel_id'],
                'alan_personel_id' => $data['alan_personel_id'],
                'planlanan_iade_tarihi' => $data['planlanan_iade_tarihi'] ?? null,
                'teslim_sayac' => $data['teslim_sayac'] ?? null,
                'aciklama' => $data['aciklama'] ?? null,
            ]);
            $yeniId = (int) $this->db->lastInsertId();

            $this->db->commit();
            return $yeniId;
        } catch (\Throwable $e) {
            $this->rollbackVeFirlat($e, 'Devir talebi oluşturulamadı.');
        }
    }

    public function talepOnayla(int $id, int $alanPersonelId, int $asilPersonelId, ?int $sayac): string
    {
        try {
            $this->db->beginTransaction();

            $talep = $this->getById($id);
            if (!$talep || $talep->durum !== 'beklemede' || (int) $talep->alan_personel_id !== $alanPersonelId) {
                throw new \DomainException('Talep bulunamadı veya daha önce yanıtlanmış.');
            }

            $kayitlar = $this->aktifleriKilitle($talep->zimmet_turu, (int) $talep->kaynak_zimmet_id);
            $halaBekliyor = array_filter($kayitlar, static fn($r) => (int) $r->id === $id && $r->durum === 'beklemede');
            if (!$halaBekliyor) {
                throw new \DomainException('Talep daha önce yanıtlanmış.');
            }
            $aktifler = array_filter($kayitlar, static fn($r) => $r->durum === 'aktif');
            $kullanan = $asilPersonelId;
            foreach ($aktifler as $aktif) {
                $kullanan = (int) $aktif->alan_personel_id;
            }
            if ($kullanan !== (int) $talep->veren_personel_id) {
                throw new \DomainException('Araç artık talebi gönderen personelde değil; talep geçersiz.');
            }

            $simdi = date('Y-m-d H:i:s');
            $sayac = $sayac ?? ($talep->teslim_sayac !== null ? (int) $talep->teslim_sayac : null);
            $asilaDonus = $alanPersonelId === $asilPersonelId;
            $not = $asilaDonus
                ? 'Asıl zimmet sahibine onaylı teslim edildi.'
                : "{$talep->alan_personel_adi} adlı personele onaylı devredildi.";

            foreach ($aktifler as $aktif) {
                $this->aktifiKapat((int) $aktif->id, $simdi, $sayac, $not);
            }

            $yeniDurum = $asilaDonus ? 'tamamlandi' : 'aktif';
            $guncelle = $this->db->prepare("
                UPDATE {$this->table}
                SET durum = :durum,
                    yanit_tarihi = :simdi1,
                    teslim_tarihi = :simdi2,
                    iade_tarihi = :iade_tarihi,
                    teslim_sayac = :sayac
                WHERE id = :id AND firma_id = :firma_id AND durum = 'beklemede'
            ");
            $guncelle->execute([
                'durum' => $yeniDurum,
                'simdi1' => $simdi,
                'simdi2' => $simdi,
                'iade_tarihi' => $asilaDonus ? $simdi : null,
                'sayac' => $sayac,
                'id' => $id,
                'firma_id' => $this->firmaId(),
            ]);

            $this->db->commit();
            return $yeniDurum;
        } catch (\Throwable $e) {
            $this->rollbackVeFirlat($e, 'Devir talebi onaylanamadı.');
        }
    }

    public function talepReddet(int $id, int $alanPersonelId, string $neden): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET durum = 'reddedildi', yanit_tarihi = NOW(), red_nedeni = :neden
            WHERE id = :id AND firma_id = :firma_id AND alan_personel_id = :alan AND durum = 'beklemede'
        ");
        $stmt->execute([
            'neden' => mb_substr($neden, 0, 500),
            'id' => $id,
            'firma_id' => $this->firmaId(),
            'alan' => $alanPersonelId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function talepYoneticiIptal(int $id, string $neden): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET durum = 'iptal', yanit_tarihi = NOW(), red_nedeni = :neden, iade_alan_kullanici_id = :user_id
            WHERE id = :id AND firma_id = :firma_id AND durum = 'beklemede'
        ");
        $stmt->execute([
            'neden' => mb_substr($neden, 0, 500),
            'user_id' => $this->userId(),
            'id' => $id,
            'firma_id' => $this->firmaId(),
        ]);
        return $stmt->rowCount() > 0;
    }

    public function talepGeriCek(int $id, int $verenPersonelId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET durum = 'iptal', yanit_tarihi = NOW(), red_nedeni = 'Talep eden personel tarafından geri çekildi.'
            WHERE id = :id AND firma_id = :firma_id AND veren_personel_id = :veren AND durum = 'beklemede'
        ");
        $stmt->execute([
            'id' => $id,
            'firma_id' => $this->firmaId(),
            'veren' => $verenPersonelId,
        ]);
        return $stmt->rowCount() > 0;
    }
}
