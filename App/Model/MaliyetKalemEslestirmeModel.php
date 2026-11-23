<?php

namespace App\Model;

use App\Core\Db;
use PDO;

class MaliyetKalemEslestirmeModel extends Db
{
    public const KAYNAKLAR = [
        'fatura'      => 'Gelen Fatura Firması (VKN)',
        'gelir_gider' => 'Gelir-Gider Kategorisi',
    ];

    private int $firmaId;

    public function __construct()
    {
        parent::__construct();
        $this->firmaId = (int) ($_SESSION['firma_id'] ?? 0);
    }

    public function liste(): array
    {
        $stmt = $this->db->prepare("SELECT id, kaynak, eslesme_degeri, eslesme_etiketi, kalem, olusturma_tarihi
                                    FROM maliyet_kalem_eslestirme
                                    WHERE firma_id = :firma_id AND deleted_at IS NULL AND is_active = 1
                                    ORDER BY kaynak ASC, kalem ASC, eslesme_etiketi ASC");
        $stmt->execute(['firma_id' => $this->firmaId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function bul(int $id): ?object
    {
        $stmt = $this->db->prepare("SELECT * FROM maliyet_kalem_eslestirme
                                    WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['id' => $id, 'firma_id' => $this->firmaId]);
        $r = $stmt->fetch(PDO::FETCH_OBJ);
        return $r ?: null;
    }

    public function kaydet(string $kaynak, string $deger, ?string $etiket, string $kalem, int $kullaniciId): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT id FROM maliyet_kalem_eslestirme
                                        WHERE firma_id = :firma_id AND kaynak = :kaynak AND eslesme_degeri = :deger
                                          AND deleted_at IS NULL
                                        ORDER BY id ASC FOR UPDATE");
            $stmt->execute(['firma_id' => $this->firmaId, 'kaynak' => $kaynak, 'deger' => $deger]);
            $mevcut = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if ($mevcut) {
                $id = (int) array_shift($mevcut);
                $this->db->prepare("UPDATE maliyet_kalem_eslestirme
                                    SET kalem = :kalem, eslesme_etiketi = COALESCE(:etiket, eslesme_etiketi), is_active = 1
                                    WHERE id = :id AND firma_id = :firma_id")
                    ->execute(['kalem' => $kalem, 'etiket' => $etiket, 'id' => $id, 'firma_id' => $this->firmaId]);
                foreach ($mevcut as $fazla) {
                    $this->softDelete((int) $fazla);
                }
            } else {
                $this->db->prepare("INSERT INTO maliyet_kalem_eslestirme
                                        (firma_id, kaynak, eslesme_degeri, eslesme_etiketi, kalem, olusturan_kullanici_id)
                                    VALUES (:firma_id, :kaynak, :deger, :etiket, :kalem, :kullanici)")
                    ->execute([
                        'firma_id'  => $this->firmaId,
                        'kaynak'    => $kaynak,
                        'deger'     => $deger,
                        'etiket'    => $etiket,
                        'kalem'     => $kalem,
                        'kullanici' => $kullaniciId ?: null,
                    ]);
                $id = (int) $this->db->lastInsertId();
            }
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE maliyet_kalem_eslestirme SET deleted_at = NOW(), is_active = 0
                                    WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id, 'firma_id' => $this->firmaId]);
        return $stmt->rowCount() > 0;
    }

    public function gelirGiderKategorileri(): array
    {
        $stmt = $this->db->query("SELECT DISTINCT TRIM(kategori) AS kategori FROM gelir_gider
                                  WHERE silinme_tarihi IS NULL AND type = 2 AND TRIM(COALESCE(kategori, '')) <> ''
                                  ORDER BY kategori ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function faturaBul(int $faturaId): ?object
    {
        $stmt = $this->db->prepare("SELECT f.id, f.fatura_no, COALESCE(NULLIF(f.alici_unvan, ''), c.unvan) AS unvan
                                    FROM faturalar f
                                    LEFT JOIN efatura_cariler c ON c.id = f.cari_id
                                    WHERE f.id = :id AND f.firm_id = :firma_id AND f.yon = 'GELEN' AND f.deleted_at IS NULL
                                    LIMIT 1");
        $stmt->execute(['id' => $faturaId, 'firma_id' => $this->firmaId]);
        $r = $stmt->fetch(PDO::FETCH_OBJ);
        return $r ?: null;
    }

    public function faturaKalemAta(int $faturaId, ?string $kalem, int $kullaniciId): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE maliyet_fatura_kalem SET deleted_at = NOW(), is_active = 0
                                WHERE firma_id = :firma_id AND fatura_id = :fatura_id AND deleted_at IS NULL")
                ->execute(['firma_id' => $this->firmaId, 'fatura_id' => $faturaId]);

            if ($kalem !== null && $kalem !== '') {
                $this->db->prepare("INSERT INTO maliyet_fatura_kalem (firma_id, fatura_id, kalem, olusturan_kullanici_id)
                                    VALUES (:firma_id, :fatura_id, :kalem, :kullanici)")
                    ->execute([
                        'firma_id'  => $this->firmaId,
                        'fatura_id' => $faturaId,
                        'kalem'     => $kalem,
                        'kullanici' => $kullaniciId ?: null,
                    ]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
