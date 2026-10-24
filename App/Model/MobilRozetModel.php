<?php

namespace App\Model;

use PDO;

class MobilRozetModel extends Model
{
    protected $table = 'mobil_rozet_durumlari';

    private const FATURA_KOSULLARI = [
        'efatura_gelen' => "yon = 'GELEN'",
        'efatura_giden' => "yon = 'GIDEN' AND entegrator_durum_kodu <> 'TASLAK'",
    ];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public static function faturaAnahtarlari(): array
    {
        return array_keys(self::FATURA_KOSULLARI);
    }

    public function faturaOkunmamisSayisi(int $userId, int $firmaId, string $anahtar): int
    {
        if (!isset(self::FATURA_KOSULLARI[$anahtar])) {
            return 0;
        }
        $sonId = $this->sonGorulenId($userId, $firmaId, $anahtar);
        if ($sonId === null) {
            $this->faturaGoruldu($userId, $firmaId, $anahtar);
            return 0;
        }
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM faturalar WHERE firm_id = ? AND deleted_at IS NULL AND id > ? AND " . self::FATURA_KOSULLARI[$anahtar]
        );
        $stmt->execute([$firmaId, $sonId]);
        return (int) $stmt->fetchColumn();
    }

    public function faturaGoruldu(int $userId, int $firmaId, string $anahtar): bool
    {
        if (!isset(self::FATURA_KOSULLARI[$anahtar])) {
            return false;
        }
        $max = $this->db->prepare(
            "SELECT COALESCE(MAX(id), 0) FROM faturalar WHERE firm_id = ? AND deleted_at IS NULL AND " . self::FATURA_KOSULLARI[$anahtar]
        );
        $max->execute([$firmaId]);
        $maxId = (int) $max->fetchColumn();

        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (user_id, firma_id, anahtar, son_gorulen_id) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE son_gorulen_id = GREATEST(son_gorulen_id, VALUES(son_gorulen_id))"
        );
        return $stmt->execute([$userId, $firmaId, $anahtar, $maxId]);
    }

    public function kmIdleri(int $firmaId): array
    {
        return $this->idler("SELECT id FROM arac_km_bildirimleri WHERE firma_id = ? AND durum = 'beklemede' AND silinme_tarihi IS NULL", [$firmaId]);
    }

    public function kacakIdleri(int $firmaId): array
    {
        return $this->idler(
            "SELECT id FROM kacak_kontrol WHERE firma_id = ? AND silinme_tarihi IS NULL AND COALESCE(durum, '') <> 'iptal' AND COALESCE(onay_durumu, 'beklemede') NOT IN ('onaylandi', 'reddedildi')",
            [$firmaId]
        );
    }

    public function ihbarIdleri(int $firmaId): array
    {
        return $this->idler("SELECT id FROM ihbarlar WHERE firma_id = ? AND durum = 'yeni' AND silinme_tarihi IS NULL", [$firmaId]);
    }

    public function evrakIdleri(int $firmaId, int $userId): array
    {
        return $this->idler(
            "SELECT et.id FROM evrak_takip et
             WHERE et.firma_id = ? AND et.silinme_tarihi IS NULL AND et.evrak_tipi = 'giden' AND et.onay_durumu = 'onay_bekliyor'
               AND EXISTS (
                   SELECT 1 FROM evrak_takip_onaylari o
                   WHERE o.evrak_id = et.id AND o.firma_id = et.firma_id AND o.kullanici_id = ? AND o.durum = 'bekliyor'
                     AND o.sira = (
                         SELECT MIN(o2.sira) FROM evrak_takip_onaylari o2
                         WHERE o2.evrak_id = et.id AND o2.firma_id = et.firma_id AND o2.durum = 'bekliyor'
                     )
               )",
            [$firmaId, $userId]
        );
    }

    public function yeniSayisi(int $userId, int $firmaId, string $anahtar, array $idler): int
    {
        $idler = array_map('intval', $idler);
        $sonId = $this->sonGorulenId($userId, $firmaId, $anahtar);
        if ($sonId === null) {
            $this->kaydet($userId, $firmaId, $anahtar, $idler ? max($idler) : 0);
            return 0;
        }
        return count(array_filter($idler, static fn (int $id): bool => $id > $sonId));
    }

    public function goruldu(int $userId, int $firmaId, string $anahtar, array $idler): bool
    {
        $idler = array_map('intval', $idler);
        return $this->kaydet($userId, $firmaId, $anahtar, $idler ? max($idler) : 0);
    }

    private function idler(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function kaydet(int $userId, int $firmaId, string $anahtar, int $sonId): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (user_id, firma_id, anahtar, son_gorulen_id) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE son_gorulen_id = GREATEST(son_gorulen_id, VALUES(son_gorulen_id))"
        );
        return $stmt->execute([$userId, $firmaId, $anahtar, $sonId]);
    }

    private function sonGorulenId(int $userId, int $firmaId, string $anahtar): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT son_gorulen_id FROM {$this->table} WHERE user_id = ? AND firma_id = ? AND anahtar = ? LIMIT 1"
        );
        $stmt->execute([$userId, $firmaId, $anahtar]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int) $value;
    }
}
