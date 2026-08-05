<?php

namespace App\Model;

use Exception;
use PDO;

/**
 * Aparat ana defteri. `aparat_hareket` gerçeğin kaynağıdır; `aparat_stok`
 * yalnızca hızlı okuma içindir ve her hareketle aynı transaction'da güncellenir.
 */
class AparatHareketModel extends Model
{
    protected $table = 'aparat_hareket';

    const HAVUZ_EKIP = 'ekip';
    const HAVUZ_DEPO = 'depo';
    const HAVUZ_SAHA = 'saha';
    const HAVUZ_HURDA = 'hurda';
    const HAVUZ_KAYIP = 'kayip';

    const HAVUZLAR = [
        self::HAVUZ_DEPO => 'Depo',
        self::HAVUZ_EKIP => 'Ekip',
        self::HAVUZ_SAHA => 'Sahada Takılı',
        self::HAVUZ_HURDA => 'Hurda',
        self::HAVUZ_KAYIP => 'Kayıp',
    ];

    const HAREKET_TIPLERI = [
        'kesme' => 'Kesme',
        'acma' => 'Açma',
        'transfer' => 'Transfer',
        'depo_giris' => 'Depo Girişi',
        'depo_cikis' => 'Depodan Ekibe Çıkış',
        'depo_iade' => 'Depoya İade',
        'hurda' => 'Hurda',
        'kayip' => 'Kayıp',
        'sayim_duzeltme' => 'Sayım Düzeltmesi',
        'acilis' => 'Açılış Stoğu',
    ];

    /**
     * Stoğun eksiye düşmesi engellenmez (saha kilitlenmesin), fakat düşen her
     * havuz bu listede raporlanır ve kayıt işaretlenir.
     */
    const NEGATIF_IZLENEN_HAVUZLAR = [self::HAVUZ_EKIP, self::HAVUZ_DEPO, self::HAVUZ_SAHA];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    /**
     * Bir olayın tüm havuz etkilerini tek transaction'da defterle ve bakiyeye işler.
     *
     * @param array $satirlar [['sahip_tipi','sahip_id','aparat_tip_id','adet'(işaretli)], ...]
     * @param array $ortak    hareket_tipi, ekip_id, personel_id, referans_tipi,
     *                        referans_id, aciklama, kaydeden_id, tarih
     * @return array ['hareket_ids' => int[], 'negatif' => bool, 'negatif_detay' => array]
     */
    public function uygula(array $satirlar, array $ortak): array
    {
        if (empty($satirlar)) {
            throw new Exception('İşlenecek hareket satırı bulunmuyor.');
        }

        $firmaId = (int) ($ortak['firma_id'] ?? $this->firmaId());
        if ($firmaId <= 0) {
            throw new Exception('Firma bilgisi bulunamadı.');
        }

        $kendiTransaction = !$this->db->inTransaction();
        if ($kendiTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $hareketStmt = $this->db->prepare("INSERT INTO {$this->table}
                (firma_id, aparat_tip_id, hareket_tipi, sahip_tipi, sahip_id, adet, ekip_id,
                 personel_id, referans_tipi, referans_id, aciklama, tarih, kaydeden_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stokStmt = $this->db->prepare("INSERT INTO aparat_stok
                (firma_id, sahip_tipi, sahip_id, aparat_tip_id, adet)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE adet = adet + VALUES(adet)");

            $bakiyeStmt = $this->db->prepare("SELECT adet FROM aparat_stok
                WHERE firma_id = ? AND sahip_tipi = ? AND sahip_id = ? AND aparat_tip_id = ?");

            $hareketIds = [];
            $negatifDetay = [];

            foreach ($satirlar as $satir) {
                $sahipTipi = (string) $satir['sahip_tipi'];
                $sahipId = (int) ($satir['sahip_id'] ?? 0);
                $tipId = (int) $satir['aparat_tip_id'];
                $adet = (int) $satir['adet'];

                if (!isset(self::HAVUZLAR[$sahipTipi])) {
                    throw new Exception('Geçersiz stok havuzu: ' . $sahipTipi);
                }
                if ($tipId <= 0) {
                    throw new Exception('Geçersiz aparat tipi.');
                }
                if ($adet === 0) {
                    continue;
                }

                $hareketStmt->execute([
                    $firmaId,
                    $tipId,
                    $ortak['hareket_tipi'],
                    $sahipTipi,
                    $sahipId,
                    $adet,
                    $ortak['ekip_id'] ?? null,
                    $ortak['personel_id'] ?? null,
                    $ortak['referans_tipi'] ?? null,
                    $ortak['referans_id'] ?? null,
                    $ortak['aciklama'] ?? null,
                    $ortak['tarih'] ?? date('Y-m-d H:i:s'),
                    $ortak['kaydeden_id'] ?? null,
                ]);
                $hareketIds[] = (int) $this->db->lastInsertId();

                $stokStmt->execute([$firmaId, $sahipTipi, $sahipId, $tipId, $adet]);

                $bakiyeStmt->execute([$firmaId, $sahipTipi, $sahipId, $tipId]);
                $yeniBakiye = (int) $bakiyeStmt->fetchColumn();

                if ($yeniBakiye < 0 && in_array($sahipTipi, self::NEGATIF_IZLENEN_HAVUZLAR, true)) {
                    $negatifDetay[] = [
                        'sahip_tipi' => $sahipTipi,
                        'sahip_id' => $sahipId,
                        'aparat_tip_id' => $tipId,
                        'bakiye' => $yeniBakiye,
                    ];
                }
            }

            if ($kendiTransaction) {
                $this->db->commit();
            }

            return [
                'hareket_ids' => $hareketIds,
                'negatif' => !empty($negatifDetay),
                'negatif_detay' => $negatifDetay,
            ];
        } catch (Exception $e) {
            if ($kendiTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Aynı olaya ait hareket satırlarını tek grup kimliği altında toplar.
     * Grup kimliği, gruptaki ilk hareketin id'sidir.
     */
    public function grupKimligiAta(array $hareketIds): int
    {
        $hareketIds = array_values(array_filter(array_map('intval', $hareketIds)));
        if (empty($hareketIds)) {
            return 0;
        }

        $grupId = min($hareketIds);
        $placeholders = implode(',', array_fill(0, count($hareketIds), '?'));

        $stmt = $this->db->prepare("UPDATE {$this->table} SET referans_id = ?
            WHERE id IN ($placeholders) AND firma_id = ?");
        $stmt->execute(array_merge([$grupId], $hareketIds, [$this->firmaId()]));

        return $grupId;
    }

    /**
     * Bir hareket satırının ait olduğu grubun tüm satırlarını getirir.
     */
    public function grubuGetir(int $hareketId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ? AND firma_id = ?");
        $stmt->execute([$hareketId, $this->firmaId()]);
        $hareket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hareket) {
            return [];
        }

        // Grup kimliği atanmamış eski kayıtlar tek satır olarak ele alınır
        if (empty($hareket['referans_id'])) {
            return [$hareket];
        }

        $stmt = $this->db->prepare("SELECT * FROM {$this->table}
            WHERE firma_id = ? AND referans_tipi = ? AND referans_id = ?
            ORDER BY id ASC");
        $stmt->execute([$this->firmaId(), $hareket['referans_tipi'], (int) $hareket['referans_id']]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verilen hareket satırlarını ters kayıtla geri alır; orijinal satırlar silinmez,
     * iptal işareti alır ve ters hareketin id'siyle ilişkilendirilir.
     */
    public function hareketleriTersle(array $hareketler, array $ortak): array
    {
        if (empty($hareketler)) {
            throw new Exception('Geri alınacak hareket bulunamadı.');
        }

        $satirlar = [];
        foreach ($hareketler as $h) {
            $satirlar[] = [
                'sahip_tipi' => $h['sahip_tipi'],
                'sahip_id' => (int) $h['sahip_id'],
                'aparat_tip_id' => (int) $h['aparat_tip_id'],
                'adet' => -1 * (int) $h['adet'],
            ];
        }

        $sonuc = $this->uygula($satirlar, array_merge([
            'hareket_tipi' => $hareketler[0]['hareket_tipi'],
            'ekip_id' => $hareketler[0]['ekip_id'],
            'referans_tipi' => $hareketler[0]['referans_tipi'],
            'referans_id' => null,
        ], $ortak));

        $tersGrupId = $this->grupKimligiAta($sonuc['hareket_ids']);

        $ids = array_map(static fn($h) => (int) $h['id'], $hareketler);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $isaretle = $this->db->prepare("UPDATE {$this->table}
            SET iptal_mi = 1, ters_hareket_id = ?
            WHERE id IN ($placeholders) AND firma_id = ? AND iptal_mi = 0");
        $isaretle->execute(array_merge([$tersGrupId], $ids, [$this->firmaId()]));

        return $sonuc;
    }

    /**
     * İptal edilen kaydın hareketlerini ters işaretle yeniden yazar; iz silinmez.
     */
    public function tersle(string $referansTipi, int $referansId, array $ortak): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table}
            WHERE firma_id = ? AND referans_tipi = ? AND referans_id = ? AND iptal_mi = 0");
        $stmt->execute([$this->firmaId(), $referansTipi, $referansId]);
        $hareketler = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($hareketler)) {
            return ['hareket_ids' => [], 'negatif' => false, 'negatif_detay' => []];
        }

        $kendiTransaction = !$this->db->inTransaction();
        if ($kendiTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $satirlar = [];
            foreach ($hareketler as $h) {
                $satirlar[] = [
                    'sahip_tipi' => $h['sahip_tipi'],
                    'sahip_id' => (int) $h['sahip_id'],
                    'aparat_tip_id' => (int) $h['aparat_tip_id'],
                    'adet' => -1 * (int) $h['adet'],
                ];
            }

            $sonuc = $this->uygula($satirlar, array_merge([
                'hareket_tipi' => $hareketler[0]['hareket_tipi'],
                'ekip_id' => $hareketler[0]['ekip_id'],
                'referans_tipi' => $referansTipi,
                'referans_id' => $referansId,
            ], $ortak));

            $isaretle = $this->db->prepare("UPDATE {$this->table} SET iptal_mi = 1
                WHERE firma_id = ? AND referans_tipi = ? AND referans_id = ? AND iptal_mi = 0");
            $isaretle->execute([$this->firmaId(), $referansTipi, $referansId]);

            if ($kendiTransaction) {
                $this->db->commit();
            }

            return $sonuc;
        } catch (Exception $e) {
            if ($kendiTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function listele(array $filtre = [], int $limit = 500, int $offset = 0): array
    {
        [$kosul, $parametreler] = $this->filtreKosulu($filtre);

        $sql = "SELECT h.*, t.ad AS aparat_adi, t.kod AS aparat_kod,
                       ek.tur_adi AS ekip_adi, p.adi_soyadi AS personel_adi,
                       u.adi_soyadi AS kullanici_adi
                FROM {$this->table} h
                LEFT JOIN aparat_tipleri t ON t.id = h.aparat_tip_id
                LEFT JOIN tanimlamalar ek ON ek.id = h.ekip_id
                LEFT JOIN personel p ON p.id = h.personel_id
                LEFT JOIN users u ON u.id = h.kaydeden_id
                WHERE {$kosul}
                ORDER BY h.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametreler);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sayisi(array $filtre = []): int
    {
        [$kosul, $parametreler] = $this->filtreKosulu($filtre);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} h WHERE {$kosul}");
        $stmt->execute($parametreler);
        return (int) $stmt->fetchColumn();
    }

    private function filtreKosulu(array $filtre): array
    {
        $kosul = 'h.firma_id = ?';
        $parametreler = [$this->firmaId()];

        if (!empty($filtre['baslangic'])) {
            $kosul .= ' AND h.tarih >= ?';
            $parametreler[] = $filtre['baslangic'] . ' 00:00:00';
        }
        if (!empty($filtre['bitis'])) {
            $kosul .= ' AND h.tarih <= ?';
            $parametreler[] = $filtre['bitis'] . ' 23:59:59';
        }
        if (!empty($filtre['ekip_id'])) {
            $kosul .= ' AND h.ekip_id = ?';
            $parametreler[] = (int) $filtre['ekip_id'];
        }
        if (!empty($filtre['aparat_tip_id'])) {
            $kosul .= ' AND h.aparat_tip_id = ?';
            $parametreler[] = (int) $filtre['aparat_tip_id'];
        }
        if (!empty($filtre['hareket_tipi'])) {
            $kosul .= ' AND h.hareket_tipi = ?';
            $parametreler[] = $filtre['hareket_tipi'];
        }
        if (!empty($filtre['sahip_tipi'])) {
            $kosul .= ' AND h.sahip_tipi = ?';
            $parametreler[] = $filtre['sahip_tipi'];
        }
        if (!empty($filtre['personel_id'])) {
            $kosul .= ' AND h.personel_id = ?';
            $parametreler[] = (int) $filtre['personel_id'];
        }
        if (!empty($filtre['referans_tipi'])) {
            $kosul .= ' AND h.referans_tipi = ?';
            $parametreler[] = $filtre['referans_tipi'];
        }
        if (!empty($filtre['referans_id'])) {
            $kosul .= ' AND h.referans_id = ?';
            $parametreler[] = (int) $filtre['referans_id'];
        }
        if (isset($filtre['iptal_haric']) && $filtre['iptal_haric']) {
            $kosul .= ' AND h.iptal_mi = 0';
        }

        return [$kosul, $parametreler];
    }

    /**
     * Defterden yeniden hesaplanan bakiye ile `aparat_stok` tablosunu karşılaştırır.
     */
    public function tutarlilikKontrolu(): array
    {
        $sql = "SELECT k.sahip_tipi, k.sahip_id, k.aparat_tip_id,
                       k.defter_adet, COALESCE(s.adet, 0) AS stok_adet
                FROM (
                    SELECT sahip_tipi, sahip_id, aparat_tip_id, SUM(adet) AS defter_adet
                    FROM {$this->table}
                    WHERE firma_id = ?
                    GROUP BY sahip_tipi, sahip_id, aparat_tip_id
                ) k
                LEFT JOIN aparat_stok s
                       ON s.firma_id = ? AND s.sahip_tipi = k.sahip_tipi
                      AND s.sahip_id = k.sahip_id AND s.aparat_tip_id = k.aparat_tip_id
                WHERE k.defter_adet <> COALESCE(s.adet, 0)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->firmaId(), $this->firmaId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bakiyeyi defterden yeniden kurar (tutarsızlık tespit edilirse kullanılır).
     */
    public function bakiyeleriYenidenKur(): int
    {
        $firmaId = $this->firmaId();

        $kendiTransaction = !$this->db->inTransaction();
        if ($kendiTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $sil = $this->db->prepare("DELETE FROM aparat_stok WHERE firma_id = ?");
            $sil->execute([$firmaId]);

            $kur = $this->db->prepare("INSERT INTO aparat_stok
                (firma_id, sahip_tipi, sahip_id, aparat_tip_id, adet)
                SELECT firma_id, sahip_tipi, sahip_id, aparat_tip_id, SUM(adet)
                FROM {$this->table}
                WHERE firma_id = ?
                GROUP BY firma_id, sahip_tipi, sahip_id, aparat_tip_id");
            $kur->execute([$firmaId]);
            $sayi = $kur->rowCount();

            if ($kendiTransaction) {
                $this->db->commit();
            }
            return $sayi;
        } catch (Exception $e) {
            if ($kendiTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
