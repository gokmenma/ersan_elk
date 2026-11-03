<?php

namespace App\Model;

use PDO;

class DosyaModel extends Model
{
    protected $table = 'dosyalar';

    public const IZINLI_UZANTILAR = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/octet-stream', 'application/x-ole-storage', 'application/cdfv2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/msword', 'application/octet-stream', 'application/x-ole-storage', 'application/cdfv2'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/octet-stream'],
        'txt' => ['text/plain'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    public const AZAMI_BOYUT = 26214400;

    public function __construct()
    {
        parent::__construct('dosyalar');
    }

    public static function depoDizini(int $firmaId): string
    {
        return dirname(__DIR__, 2) . '/storage/dosya-yonetimi/' . $firmaId;
    }

    public function bul(int $id, int $firmaId): ?object
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM dosyalar WHERE id = ? AND firma_id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$id, $firmaId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function olustur(array $veri): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO dosyalar
                (firma_id, klasor_id, dosya_adi, orijinal_ad, uzanti, mime_tipi, boyut, sha256, aciklama, etiketler, yukleyen_id, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())"
        );
        $stmt->execute([
            $veri['firma_id'], $veri['klasor_id'], $veri['dosya_adi'], $veri['orijinal_ad'], $veri['uzanti'],
            $veri['mime_tipi'], $veri['boyut'], $veri['sha256'], $veri['aciklama'] ?? null, $veri['etiketler'] ?? null,
            $veri['yukleyen_id'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function yenidenAdlandir(int $id, int $firmaId, string $ad): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE dosyalar SET orijinal_ad = ?, updated_at = NOW()
             WHERE id = ? AND firma_id = ? AND is_active = 1 AND deleted_at IS NULL"
        );
        $stmt->execute([$ad, $id, $firmaId]);
        return $stmt->rowCount() > 0;
    }

    public function tasi(int $id, int $firmaId, ?int $klasorId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE dosyalar SET klasor_id = ?, updated_at = NOW()
             WHERE id = ? AND firma_id = ? AND is_active = 1 AND deleted_at IS NULL"
        );
        $stmt->execute([$klasorId, $id, $firmaId]);
        return $stmt->rowCount() > 0;
    }

    public function sil(int $id, int $firmaId): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "UPDATE dosyalar SET deleted_at = NOW(), is_active = 0
                 WHERE id = ? AND firma_id = ? AND deleted_at IS NULL"
            );
            $stmt->execute([$id, $firmaId]);
            $silindi = $stmt->rowCount() > 0;

            $stmt = $this->db->prepare(
                "UPDATE dosya_paylasimlari SET iptal_tarihi = NOW()
                 WHERE dosya_id = ? AND firma_id = ? AND iptal_tarihi IS NULL"
            );
            $stmt->execute([$id, $firmaId]);

            $this->db->commit();
            return $silindi;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function klasorKosulu(array $izinliKlasorler, array &$bind, string $alias = 'd'): string
    {
        if (!$izinliKlasorler) {
            return "{$alias}.klasor_id IS NULL";
        }
        $yerler = [];
        foreach (array_values($izinliKlasorler) as $i => $id) {
            $anahtar = 'izin' . $i;
            $yerler[] = ':' . $anahtar;
            $bind[$anahtar] = (int) $id;
        }
        return "({$alias}.klasor_id IS NULL OR {$alias}.klasor_id IN (" . implode(',', $yerler) . "))";
    }

    public function ajaxList(array $params, int $firmaId, array $izinliKlasorler): array
    {
        $draw = (int) ($params['draw'] ?? 1);
        $start = max(0, (int) ($params['start'] ?? 0));
        $length = (int) ($params['length'] ?? 25);
        $length = $length < 1 ? 25 : min($length, 200);
        $arama = trim((string) ($params['search']['value'] ?? ''));

        $bind = ['firma' => $firmaId];
        $where = "d.firma_id = :firma AND d.is_active = 1 AND d.deleted_at IS NULL AND " . $this->klasorKosulu($izinliKlasorler, $bind);

        if (isset($params['klasor_id']) && $params['klasor_id'] !== '' && $params['klasor_id'] !== 'tum') {
            $klasor = (int) $params['klasor_id'];
            if ($klasor > 0) {
                $where .= " AND d.klasor_id = :klasor_filtre";
                $bind['klasor_filtre'] = $klasor;
            } else {
                $where .= " AND d.klasor_id IS NULL";
            }
        }

        if ($arama !== '') {
            $where .= " AND (d.orijinal_ad LIKE :ara OR d.aciklama LIKE :ara OR d.etiketler LIKE :ara OR k.ad LIKE :ara)";
            $bind['ara'] = '%' . $arama . '%';
        }

        $kolonlar = $params['columns'] ?? [];
        $kolonHaritasi = [0 => 'd.orijinal_ad', 1 => 'k.ad', 2 => 'd.uzanti', 4 => 'u.adi_soyadi'];
        foreach ($kolonHaritasi as $index => $sutun) {
            $deger = trim((string) ($kolonlar[$index]['search']['value'] ?? ''));
            if ($deger !== '') {
                $anahtar = 'kolon' . $index;
                $where .= " AND {$sutun} LIKE :{$anahtar}";
                $bind[$anahtar] = '%' . $deger . '%';
            }
        }

        $siraHaritasi = [0 => 'd.orijinal_ad', 1 => 'k.ad', 2 => 'd.uzanti', 3 => 'd.boyut', 4 => 'u.adi_soyadi', 5 => 'd.created_at'];
        $siraSutun = 'd.created_at';
        $siraYon = 'DESC';
        if (!empty($params['order'][0])) {
            $index = (int) ($params['order'][0]['column'] ?? 5);
            if (isset($siraHaritasi[$index])) {
                $siraSutun = $siraHaritasi[$index];
                $siraYon = strtolower($params['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
            }
        }

        $birlesim = "FROM dosyalar d
                     LEFT JOIN dosya_klasorleri k ON k.id = d.klasor_id
                     LEFT JOIN users u ON u.id = d.yukleyen_id";

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM dosyalar d WHERE d.firma_id = :firma AND d.is_active = 1 AND d.deleted_at IS NULL");
        $stmt->execute(['firma' => $firmaId]);
        $toplam = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare("SELECT COUNT(*) {$birlesim} WHERE {$where}");
        $stmt->execute($bind);
        $filtreli = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT d.id, d.klasor_id, d.orijinal_ad, d.uzanti, d.mime_tipi, d.boyut, d.aciklama, d.etiketler, d.created_at,
                    k.ad AS klasor_adi, u.adi_soyadi AS yukleyen_adi
             {$birlesim} WHERE {$where}
             ORDER BY {$siraSutun} {$siraYon}, d.id DESC
             LIMIT :baslangic, :uzunluk"
        );
        foreach ($bind as $anahtar => $deger) {
            $stmt->bindValue(':' . $anahtar, $deger, is_int($deger) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':baslangic', $start, PDO::PARAM_INT);
        $stmt->bindValue(':uzunluk', $length, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'draw' => $draw,
            'recordsTotal' => $toplam,
            'recordsFiltered' => $filtreli,
            'rows' => $stmt->fetchAll(PDO::FETCH_OBJ),
        ];
    }

    /**
     * Tek klasörün (veya kökün) doğrudan dosyaları; arama verilirse izinli tüm klasörlerde arar.
     */
    public function klasordekiler(int $firmaId, ?int $klasorId, string $arama, array $izinliKlasorler, int $limit = 500): array
    {
        $bind = ['firma' => $firmaId];
        $where = "d.firma_id = :firma AND d.is_active = 1 AND d.deleted_at IS NULL AND " . $this->klasorKosulu($izinliKlasorler, $bind);

        if ($arama !== '') {
            $where .= " AND (d.orijinal_ad LIKE :ara OR d.aciklama LIKE :ara OR d.etiketler LIKE :ara)";
            $bind['ara'] = '%' . $arama . '%';
        } elseif ($klasorId !== null && $klasorId > 0) {
            $where .= " AND d.klasor_id = :klasor";
            $bind['klasor'] = $klasorId;
        } else {
            $where .= " AND d.klasor_id IS NULL";
        }

        $stmt = $this->db->prepare(
            "SELECT d.id, d.klasor_id, d.orijinal_ad, d.uzanti, d.mime_tipi, d.boyut, d.aciklama, d.etiketler, d.created_at,
                    k.ad AS klasor_adi, u.adi_soyadi AS yukleyen_adi
             FROM dosyalar d
             LEFT JOIN dosya_klasorleri k ON k.id = d.klasor_id
             LEFT JOIN users u ON u.id = d.yukleyen_id
             WHERE {$where}
             ORDER BY d.orijinal_ad ASC
             LIMIT :limit"
        );
        foreach ($bind as $anahtar => $deger) {
            $stmt->bindValue(':' . $anahtar, $deger, is_int($deger) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function summary(int $firmaId, array $izinliKlasorler): object
    {
        $bind = ['firma' => $firmaId];
        $kosul = $this->klasorKosulu($izinliKlasorler, $bind);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS toplam_dosya,
                    COALESCE(SUM(d.boyut), 0) AS toplam_boyut,
                    COALESCE(SUM(CASE WHEN d.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN 1 ELSE 0 END), 0) AS bu_ay
             FROM dosyalar d
             WHERE d.firma_id = :firma AND d.is_active = 1 AND d.deleted_at IS NULL AND {$kosul}"
        );
        $stmt->execute($bind);
        $ozet = $stmt->fetch(PDO::FETCH_OBJ);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM dosya_klasorleri WHERE firma_id = ? AND is_active = 1 AND deleted_at IS NULL"
        );
        $stmt->execute([$firmaId]);
        $ozet->toplam_klasor = (int) $stmt->fetchColumn();
        return $ozet;
    }

    public function erisimLogla(int $firmaId, ?int $dosyaId, ?int $paylasimId, string $islem, ?int $kullaniciId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO dosya_erisim_loglari (firma_id, dosya_id, paylasim_id, islem, user_id, ip, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $firmaId, $dosyaId, $paylasimId, $islem, $kullaniciId,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    }
}
