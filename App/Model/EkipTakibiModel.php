<?php

namespace App\Model;

use PDO;

class EkipTakibiModel extends Model
{
    protected $table = 'endeks_okuma';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getSonOkumaTarihi(int $firmaId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT MAX(tarih) FROM endeks_okuma WHERE firma_id = :firma_id AND silinme_tarihi IS NULL"
        );
        $stmt->execute(['firma_id' => $firmaId]);
        $value = $stmt->fetchColumn();

        return $value !== false && $value !== null ? (string) $value : null;
    }

    /**
     * Bölgedeki ekiplerin dönem performansını ekip sayısından bağımsız, toplu sorgularla getirir.
     */
    public function getEkipPerformansi(array $ekipler, int $firmaId, string $startDate, string $endDate): array
    {
        if ($ekipler === []) {
            return [];
        }

        $ekipIds = array_values(array_unique(array_map(static fn($ekip): int => (int) $ekip->id, $ekipler)));
        $placeholders = implode(',', array_fill(0, count($ekipIds), '?'));
        $baseParams = array_merge([$firmaId], $ekipIds, [$startDate, $endDate]);

        $stmt = $this->db->prepare(
            "SELECT ekip_kodu_id,
                    COALESCE(SUM(okunan_abone_sayisi), 0) AS donem_toplam,
                    COUNT(DISTINCT tarih) AS calisilan_gun
             FROM endeks_okuma
             WHERE firma_id = ?
               AND ekip_kodu_id IN ($placeholders)
               AND tarih BETWEEN ? AND ?
               AND silinme_tarihi IS NULL
             GROUP BY ekip_kodu_id"
        );
        $stmt->execute($baseParams);
        $istatistikler = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
            $istatistikler[(int) $row->ekip_kodu_id] = $row;
        }

        $stmt = $this->db->prepare(
            "SELECT ekip_kodu_id, tarih, COALESCE(SUM(okunan_abone_sayisi), 0) AS toplam
             FROM endeks_okuma
             WHERE firma_id = ?
               AND ekip_kodu_id IN ($placeholders)
               AND tarih BETWEEN ? AND ?
               AND silinme_tarihi IS NULL
             GROUP BY ekip_kodu_id, tarih
             ORDER BY ekip_kodu_id, tarih DESC"
        );
        $stmt->execute($baseParams);
        $detaylar = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
            $detaylar[(int) $row->ekip_kodu_id][] = [
                'tarih' => $row->tarih,
                'toplam' => (int) $row->toplam,
            ];
        }

        $stmt = $this->db->prepare(
            "SELECT son.ekip_kodu_id, p.adi_soyadi, p.departman
             FROM (
                 SELECT ekip_kodu_id, MAX(id) AS son_id
                 FROM endeks_okuma
                 WHERE firma_id = ?
                   AND ekip_kodu_id IN ($placeholders)
                   AND tarih BETWEEN ? AND ?
                   AND silinme_tarihi IS NULL
                 GROUP BY ekip_kodu_id
             ) son
             JOIN endeks_okuma eo ON eo.id = son.son_id
             JOIN personel p ON p.id = eo.personel_id"
        );
        $stmt->execute($baseParams);
        $personeller = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
            $personeller[(int) $row->ekip_kodu_id] = $row;
        }

        $atamaParams = array_merge([$endDate, $startDate, $firmaId], $ekipIds);
        $stmt = $this->db->prepare(
            "SELECT pg.ekip_kodu_id, p.adi_soyadi, p.departman,
                    CASE WHEN pg.baslangic_tarihi <= ?
                              AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= ?)
                         THEN 0 ELSE 1 END AS donem_disinda
             FROM personel_ekip_gecmisi pg
             JOIN personel p ON p.id = pg.personel_id
             WHERE pg.firma_id = ? AND pg.ekip_kodu_id IN ($placeholders)
             ORDER BY pg.ekip_kodu_id, donem_disinda, pg.id DESC"
        );
        $stmt->execute($atamaParams);
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
            $ekipId = (int) $row->ekip_kodu_id;
            if (!isset($personeller[$ekipId])) {
                $personeller[$ekipId] = $row;
            }
        }

        $sonuc = [];
        $bugun = date('Y-m-d');
        $hedefGun = $startDate === $endDate
            ? $startDate
            : (($bugun >= $startDate && $bugun <= $endDate) ? $bugun : $endDate);
        foreach ($ekipler as $ekip) {
            $ekipId = (int) $ekip->id;
            $istatistik = $istatistikler[$ekipId] ?? null;
            $donemToplam = (int) ($istatistik->donem_toplam ?? 0);
            $calisilanGun = (int) ($istatistik->calisilan_gun ?? 0);
            $personel = $personeller[$ekipId] ?? null;
            $gunlukToplam = 0;
            foreach ($detaylar[$ekipId] ?? [] as $detay) {
                if ($detay['tarih'] === $hedefGun) {
                    $gunlukToplam = (int) $detay['toplam'];
                    break;
                }
            }

            if ($personel && $donemToplam === 0) {
                $departman = (string) ($personel->departman ?? '');
                if ($departman !== '' && stripos($departman, 'Endeks Okuma') === false) {
                    continue;
                }
            }

            $sonuc[] = [
                'ekip_kodu_id' => $ekipId,
                'ekip_adi' => (string) ($ekip->tur_adi ?? ''),
                'personel_adi' => (string) ($personel->adi_soyadi ?? '—'),
                'gunluk_toplam' => $gunlukToplam,
                'aylik_toplam' => $donemToplam,
                'donem_toplam' => $donemToplam,
                'calisilan_gun' => $calisilanGun,
                'gunluk_ort' => $calisilanGun > 0 ? (int) round($donemToplam / $calisilanGun) : 0,
                'gunluk_detay' => $detaylar[$ekipId] ?? [],
            ];
        }

        return $sonuc;
    }

    public function getGecikenOkumalar(string $bolge, int $firmaId, int $gecikmeGun = 35): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.tur_adi AS defter_kodu,
                    t.defter_mahalle AS mahalle,
                    COALESCE(MAX(eo.tarih), t.baslangic_tarihi) AS son_okuma_tarihi
             FROM tanimlamalar t
             LEFT JOIN endeks_okuma eo
                    ON eo.defter = t.tur_adi
                   AND eo.firma_id = t.firma_id
                   AND eo.silinme_tarihi IS NULL
             WHERE t.grup = 'defter_kodu'
               AND t.defter_bolge = :bolge
               AND t.firma_id = :firma_id
               AND t.silinme_tarihi IS NULL
             GROUP BY t.id, t.tur_adi, t.defter_mahalle, t.baslangic_tarihi
             HAVING son_okuma_tarihi IS NOT NULL
                AND DATEDIFF(CURDATE(), son_okuma_tarihi) > :gecikme_gun
             ORDER BY son_okuma_tarihi ASC"
        );
        $stmt->bindValue(':bolge', $bolge, PDO::PARAM_STR);
        $stmt->bindValue(':firma_id', $firmaId, PDO::PARAM_INT);
        $stmt->bindValue(':gecikme_gun', $gecikmeGun, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function ($row): array {
            return [
                'defter_kodu' => (string) $row->defter_kodu,
                'mahalle' => (string) ($row->mahalle ?? ''),
                'gun' => (int) $row->gun,
                'son_okuma' => date('d.m.Y', strtotime($row->son_okuma_tarihi)),
            ];
        }, array_map(static function ($row) {
            $row->gun = (int) ((new \DateTime())->diff(new \DateTime($row->son_okuma_tarihi))->days);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_OBJ)));
    }
}
