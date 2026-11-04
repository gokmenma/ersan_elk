<?php
namespace App\Model;

use DomainException;
use PDO;

final class BordroOnayKilidiModel extends Model
{
    protected $table = 'bordro_yayin_dokum';

    public const ACIK_TALEP_DURUMLARI = "('acik','alindi','inceleniyor','ek_bilgi_bekleniyor')";

    public function __construct(?PDO $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
            return;
        }
        parent::__construct($this->table);
    }

    private function kilitSorgusu(string $kosul): string
    {
        return "SELECT bp.id AS bordro_personel_id, bp.personel_id, bp.donem_id, p.adi_soyadi,
                d.id AS dokum_id, d.beyan_tarihi, d.icerik, d.icerik_hash, y.surum, y.durum
            FROM bordro_personel bp
            JOIN bordro_donemi bd ON bd.id = bp.donem_id
            JOIN personel p ON p.id = bp.personel_id
            JOIN bordro_yayin y ON y.donem_id = bp.donem_id AND y.firma_id = bd.firma_id
                AND y.is_active = 1 AND y.durum IN ('yayinda','test')
            JOIN bordro_yayin_dokum d ON d.yayin_id = y.id AND d.personel_id = bp.personel_id AND d.is_active = 1
            WHERE {$kosul}
              AND bp.silinme_tarihi IS NULL
              AND d.beyan_tarihi IS NOT NULL
              AND d.revizyon_tarihi IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM bordro_yayin_talep t
                  WHERE t.dokum_id = d.id AND t.is_active = 1 AND t.durum IN " . self::ACIK_TALEP_DURUMLARI . "
              )
            ORDER BY d.id";
    }

    private function grupla(array $rows): array
    {
        $kilitler = [];
        foreach ($rows as $row) {
            $kilitler[(int) $row['bordro_personel_id']] = $row;
        }
        return $kilitler;
    }

    public function donemKilitleri(int $donem): array
    {
        $stmt = $this->db->prepare($this->kilitSorgusu('bp.donem_id = ?'));
        $stmt->execute([$donem]);
        return $this->grupla($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function kilit(int $bordroPersonelId): ?array
    {
        $stmt = $this->db->prepare($this->kilitSorgusu('bp.id = ?'));
        $stmt->execute([$bordroPersonelId]);
        return $this->grupla($stmt->fetchAll(PDO::FETCH_ASSOC))[$bordroPersonelId] ?? null;
    }

    public function personelKilidi(int $personelId, int $donem): ?array
    {
        $stmt = $this->db->prepare($this->kilitSorgusu('bp.personel_id = ? AND bp.donem_id = ?'));
        $stmt->execute([$personelId, $donem]);
        $kilitler = $this->grupla($stmt->fetchAll(PDO::FETCH_ASSOC));
        return $kilitler ? end($kilitler) : null;
    }

    public static function mesaj(array $kilit): string
    {
        return sprintf(
            '%s bordrosunu onayladı; onaylı bordro değiştirilemez. Düzeltme için önce Bordro Yayını ekranından onayı geri alın veya toplu revizyon kullanın.',
            (string) ($kilit['adi_soyadi'] ?? 'Personel')
        );
    }

    public function dogrula(int $bordroPersonelId): void
    {
        $kilit = $this->kilit($bordroPersonelId);
        if ($kilit !== null) {
            throw new DomainException(self::mesaj($kilit));
        }
    }

    public function dogrulaPersonel(int $personelId, int $donem): void
    {
        if ($personelId <= 0 || $donem <= 0) {
            return;
        }
        $kilit = $this->personelKilidi($personelId, $donem);
        if ($kilit !== null) {
            throw new DomainException(self::mesaj($kilit));
        }
    }
}
