<?php

namespace App\Model;

use PDO;

class AparatSayimModel extends Model
{
    protected $table = 'aparat_sayim';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    public function acikSayim(): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table}
            WHERE firma_id = ? AND durum = 'acik' AND silinme_tarihi IS NULL
            ORDER BY id DESC LIMIT 1");
        $stmt->execute([$this->firmaId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getir(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table}
            WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$id, $this->firmaId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listele(int $limit = 50): array
    {
        $stmt = $this->db->prepare("SELECT s.*, u.adi_soyadi AS baslatan_adi,
                (SELECT COUNT(*) FROM aparat_sayim_detay d WHERE d.sayim_id = s.id) AS satir_sayisi,
                (SELECT COUNT(*) FROM aparat_sayim_detay d WHERE d.sayim_id = s.id AND d.sayilan_adet IS NOT NULL) AS girilen_sayisi,
                (SELECT COUNT(DISTINCT d.ekip_id) FROM aparat_sayim_detay d WHERE d.sayim_id = s.id) AS ekip_sayisi
            FROM {$this->table} s
            LEFT JOIN users u ON u.id = s.baslatan_id
            WHERE s.firma_id = ? AND s.silinme_tarihi IS NULL
            ORDER BY s.id DESC LIMIT {$limit}");
        $stmt->execute([$this->firmaId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sayım başlatır ve o anki sistem bakiyelerini satır satır dondurur.
     */
    public function baslat(string $baslik, array $ekipler, array $tipler, int $kullaniciId, string $aciklama = ''): int
    {
        $firmaId = $this->firmaId();

        $kendiTransaction = !$this->db->inTransaction();
        if ($kendiTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO {$this->table}
                (firma_id, baslik, durum, baslatan_id, aciklama) VALUES (?, ?, 'acik', ?, ?)");
            $stmt->execute([$firmaId, $baslik, $kullaniciId, $aciklama ?: null]);
            $sayimId = (int) $this->db->lastInsertId();

            $detay = $this->db->prepare("INSERT INTO aparat_sayim_detay
                (firma_id, sayim_id, ekip_id, aparat_tip_id, sistem_adet)
                SELECT ?, ?, ?, ?, COALESCE((SELECT adet FROM aparat_stok
                    WHERE firma_id = ? AND sahip_tipi = ? AND sahip_id = ? AND aparat_tip_id = ?), 0)");

            foreach ($ekipler as $ekipId) {
                $ekipId = (int) $ekipId;
                foreach ($tipler as $tipId) {
                    $detay->execute([
                        $firmaId, $sayimId, $ekipId, (int) $tipId,
                        $firmaId, $ekipId < 0 ? 'personel' : 'ekip', abs($ekipId), (int) $tipId,
                    ]);
                }
            }

            if ($kendiTransaction) {
                $this->db->commit();
            }
            return $sayimId;
        } catch (\Exception $e) {
            if ($kendiTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function detaylar(int $sayimId, ?int $ekipId = null): array
    {
        $sql = "SELECT d.*, t.ad AS aparat_adi, t.kod AS aparat_kod,
                       CASE WHEN d.ekip_id < 0 THEN pk.adi_soyadi ELSE ek.tur_adi END AS ekip_adi,
                       CASE WHEN d.ekip_id < 0 THEN 1 ELSE 0 END AS personel_mi,
                       p.adi_soyadi AS giren_adi
                FROM aparat_sayim_detay d
                LEFT JOIN aparat_tipleri t ON t.id = d.aparat_tip_id
                LEFT JOIN tanimlamalar ek ON d.ekip_id > 0 AND ek.id = d.ekip_id
                LEFT JOIN personel pk ON d.ekip_id < 0 AND pk.id = -d.ekip_id
                LEFT JOIN personel p ON p.id = d.giren_personel_id
                WHERE d.sayim_id = ? AND d.firma_id = ?";
        $parametreler = [$sayimId, $this->firmaId()];

        if ($ekipId) {
            $sql .= " AND d.ekip_id = ?";
            $parametreler[] = $ekipId;
        }

        $sql .= " ORDER BY (d.ekip_id < 0) ASC, ek.tur_adi ASC, pk.adi_soyadi ASC, t.sira ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametreler);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Açık sayıma ekip (pozitif) veya personel (negatif = -personel_id) ekler.
     * Sayımda zaten bulunanlara dokunmaz. Eklenen satır sayısını döndürür.
     */
    public function satirlariEkle(int $sayimId, array $sahipler, array $tipler): int
    {
        $eklenen = 0;
        $firmaId = $this->firmaId();
        $var = $this->db->prepare("SELECT COUNT(*) FROM aparat_sayim_detay WHERE sayim_id = ? AND ekip_id = ?");
        $ekle = $this->db->prepare("INSERT INTO aparat_sayim_detay
            (firma_id, sayim_id, ekip_id, aparat_tip_id, sistem_adet)
            SELECT ?, ?, ?, ?, COALESCE((SELECT adet FROM aparat_stok
                WHERE firma_id = ? AND sahip_tipi = ? AND sahip_id = ? AND aparat_tip_id = ?), 0)");

        foreach ($sahipler as $sahip) {
            $sahip = (int) $sahip;
            if ($sahip === 0) {
                continue;
            }
            $var->execute([$sayimId, $sahip]);
            if ((int) $var->fetchColumn() > 0) {
                continue;
            }
            foreach ($tipler as $tipId) {
                $ekle->execute([$firmaId, $sayimId, $sahip, (int) $tipId, $firmaId, $sahip < 0 ? 'personel' : 'ekip', abs($sahip), (int) $tipId]);
                $eklenen++;
            }
        }

        return $eklenen;
    }

    public function sahipIslenmisSatirVarMi(int $sayimId, int $sahipId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM aparat_sayim_detay
            WHERE sayim_id = ? AND ekip_id = ? AND firma_id = ? AND islendi = 1");
        $stmt->execute([$sayimId, $sahipId, $this->firmaId()]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function sahibiCikar(int $sayimId, int $sahipId): int
    {
        $stmt = $this->db->prepare("DELETE FROM aparat_sayim_detay
            WHERE sayim_id = ? AND ekip_id = ? AND firma_id = ? AND islendi = 0");
        $stmt->execute([$sayimId, $sahipId, $this->firmaId()]);
        return $stmt->rowCount();
    }

    public function satirGetir(int $sayimId, int $ekipId, int $tipId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM aparat_sayim_detay
            WHERE sayim_id = ? AND ekip_id = ? AND aparat_tip_id = ? AND firma_id = ?");
        $stmt->execute([$sayimId, $ekipId, $tipId, $this->firmaId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function detayGetir(int $detayId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM aparat_sayim_detay
            WHERE id = ? AND firma_id = ?");
        $stmt->execute([$detayId, $this->firmaId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Sayım anındaki sistem bakiyesi dondurulmaz; giriş anında yeniden okunur ki
     * sayım açıkken oluşan hareketler farkı yanlış göstermesin.
     */
    public function sayimGir(int $sayimId, int $ekipId, int $tipId, int $sayilanAdet, string $aciklama, ?int $personelId): bool
    {
        $stmt = $this->db->prepare("UPDATE aparat_sayim_detay d
            SET d.sistem_adet = COALESCE((SELECT s.adet FROM aparat_stok s
                    WHERE s.firma_id = d.firma_id AND s.sahip_tipi = IF(d.ekip_id < 0, 'personel', 'ekip')
                      AND s.sahip_id = ABS(d.ekip_id) AND s.aparat_tip_id = d.aparat_tip_id), 0),
                d.sayilan_adet = ?,
                d.fark = ? - COALESCE((SELECT s.adet FROM aparat_stok s
                    WHERE s.firma_id = d.firma_id AND s.sahip_tipi = IF(d.ekip_id < 0, 'personel', 'ekip')
                      AND s.sahip_id = ABS(d.ekip_id) AND s.aparat_tip_id = d.aparat_tip_id), 0),
                d.aciklama = ?,
                d.giren_personel_id = ?,
                d.giris_tarihi = NOW()
            WHERE d.sayim_id = ? AND d.ekip_id = ? AND d.aparat_tip_id = ?
              AND d.firma_id = ? AND d.islendi = 0");

        $stmt->execute([
            $sayilanAdet, $sayilanAdet, $aciklama ?: null, $personelId,
            $sayimId, $ekipId, $tipId, $this->firmaId(),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function islendiIsaretle(int $detayId): void
    {
        $stmt = $this->db->prepare("UPDATE aparat_sayim_detay SET islendi = 1
            WHERE id = ? AND firma_id = ?");
        $stmt->execute([$detayId, $this->firmaId()]);
    }

    const OTOMATIK_ACIKLAMALAR = ['Kullanıldı', 'Elinde mevcut'];

    /**
     * Fark varsa ve gerekçe boş ya da otomatik metinse gerekçeyi farkın yönüne göre yazar:
     * eksik → "Kullanıldı", fazla → "Elinde mevcut". Elle yazılmış gerekçeye dokunmaz.
     */
    public function otomatikAciklamaUygula(int $sayimId, int $ekipId, int $tipId): string
    {
        $satir = $this->satirGetir($sayimId, $ekipId, $tipId);
        if (!$satir || $satir['sayilan_adet'] === null || (int) $satir['islendi'] === 1) {
            return (string) ($satir['aciklama'] ?? '');
        }

        $fark = (int) $satir['fark'];
        $mevcut = trim((string) ($satir['aciklama'] ?? ''));
        $otomatikMi = $mevcut === '' || in_array($mevcut, self::OTOMATIK_ACIKLAMALAR, true);

        if (!$otomatikMi) {
            return $mevcut;
        }

        $yeni = $fark < 0 ? 'Kullanıldı' : ($fark > 0 ? 'Elinde mevcut' : '');
        if ($yeni !== $mevcut) {
            $stmt = $this->db->prepare("UPDATE aparat_sayim_detay SET aciklama = ?
                WHERE id = ? AND firma_id = ? AND islendi = 0");
            $stmt->execute([$yeni !== '' ? $yeni : null, (int) $satir['id'], $this->firmaId()]);
        }

        return $yeni;
    }

    public function islendiSifirla(int $detayId): void
    {
        $stmt = $this->db->prepare("UPDATE aparat_sayim_detay SET islendi = 0
            WHERE id = ? AND firma_id = ?");
        $stmt->execute([$detayId, $this->firmaId()]);
    }

    public function islenmemisFarklar(int $sayimId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM aparat_sayim_detay
            WHERE sayim_id = ? AND firma_id = ? AND sayilan_adet IS NOT NULL
              AND islendi = 0 AND fark <> 0");
        $stmt->execute([$sayimId, $this->firmaId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function kapat(int $sayimId): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table}
            SET durum = 'tamamlandi', bitis_tarihi = NOW()
            WHERE id = ? AND firma_id = ? AND durum = 'acik'");
        $stmt->execute([$sayimId, $this->firmaId()]);
        return $stmt->rowCount() > 0;
    }

    public function iptalEt(int $sayimId): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table}
            SET durum = 'iptal', bitis_tarihi = NOW()
            WHERE id = ? AND firma_id = ? AND durum = 'acik'");
        $stmt->execute([$sayimId, $this->firmaId()]);
        return $stmt->rowCount() > 0;
    }
}
