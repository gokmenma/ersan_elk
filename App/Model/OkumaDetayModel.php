<?php

namespace App\Model;

use PDO;

class OkumaDetayModel extends Model
{
    protected $table = 'endeks_okuma_detay';

    public function __construct($table = null)
    {
        if ($table) {
            $this->table = $table;
        }
        parent::__construct($this->table);
    }

    public function dosyaEkle(array $veri)
    {
        $sql = "INSERT INTO endeks_okuma_detay_dosya
                    (firma_id, orijinal_adi, dosya_hash, dosya_boyutu, satir_sayisi,
                     atlanan_tarih, atlanan_tekrar, ilk_tarih, son_tarih, durum, hata_mesaji, yukleyen)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $veri['firma_id'],
            $veri['orijinal_adi'],
            $veri['dosya_hash'],
            $veri['dosya_boyutu'],
            $veri['satir_sayisi'],
            $veri['atlanan_tarih'],
            $veri['atlanan_tekrar'],
            $veri['ilk_tarih'],
            $veri['son_tarih'],
            $veri['durum'],
            $veri['hata_mesaji'],
            $veri['yukleyen'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function dosyaGuncelle($dosyaId, array $veri)
    {
        $sql = "UPDATE endeks_okuma_detay_dosya
                   SET satir_sayisi = ?, atlanan_tarih = ?, atlanan_tekrar = ?,
                       ilk_tarih = ?, son_tarih = ?, durum = ?, hata_mesaji = ?
                 WHERE id = ? AND firma_id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $veri['satir_sayisi'],
            $veri['atlanan_tarih'],
            $veri['atlanan_tekrar'],
            $veri['ilk_tarih'],
            $veri['son_tarih'],
            $veri['durum'],
            $veri['hata_mesaji'],
            $dosyaId,
            $veri['firma_id'],
        ]);
    }

    public function satirlariEkle($dosyaId, $firmaId, array $satirlar)
    {
        if (empty($satirlar)) {
            return 0;
        }

        $eklenen = 0;
        $sutunlar = "(firma_id, dosya_id, satir_hash, ekip_kodu, ekip_adi, ekip_kodu_id, personel_id,
                      bolge, defter, sayfa, sira_no, mahalle, abone_no, abone_adsoyad,
                      sayac_durum, okuma_zamani, tarih)";

        foreach (array_chunk($satirlar, 500) as $parca) {
            $yerTutucu = implode(',', array_fill(0, count($parca), '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'));
            $sql = "INSERT IGNORE INTO {$this->table} $sutunlar VALUES $yerTutucu";

            $parametreler = [];
            foreach ($parca as $satir) {
                $parametreler[] = $firmaId;
                $parametreler[] = $dosyaId;
                $parametreler[] = $satir['satir_hash'];
                $parametreler[] = $satir['ekip_kodu'];
                $parametreler[] = $satir['ekip_adi'];
                $parametreler[] = $satir['ekip_kodu_id'];
                $parametreler[] = $satir['personel_id'];
                $parametreler[] = $satir['bolge'];
                $parametreler[] = $satir['defter'];
                $parametreler[] = $satir['sayfa'];
                $parametreler[] = $satir['sira_no'];
                $parametreler[] = $satir['mahalle'];
                $parametreler[] = $satir['abone_no'];
                $parametreler[] = $satir['abone_adsoyad'];
                $parametreler[] = $satir['sayac_durum'];
                $parametreler[] = $satir['okuma_zamani'];
                $parametreler[] = $satir['tarih'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($parametreler);
            $eklenen += $stmt->rowCount();
        }

        return $eklenen;
    }

    public function getDosyalar($firmaId)
    {
        $sql = "SELECT d.*, u.adi_soyadi as yukleyen_adi,
                       (SELECT COUNT(*) FROM {$this->table} t WHERE t.dosya_id = d.id) as mevcut_satir
                  FROM endeks_okuma_detay_dosya d
                  LEFT JOIN users u ON u.id = d.yukleyen
                 WHERE d.firma_id = ? AND d.silinme_tarihi IS NULL
                 ORDER BY d.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$firmaId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function dosyaBul($dosyaId, $firmaId)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM endeks_okuma_detay_dosya
              WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL"
        );
        $stmt->execute([$dosyaId, $firmaId]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function dosyaSil($dosyaId, $firmaId)
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE dosya_id = ? AND firma_id = ?");
            $stmt->execute([$dosyaId, $firmaId]);
            $silinen = $stmt->rowCount();

            $stmt = $this->db->prepare(
                "UPDATE endeks_okuma_detay_dosya SET silinme_tarihi = NOW()
                  WHERE id = ? AND firma_id = ?"
            );
            $stmt->execute([$dosyaId, $firmaId]);

            $this->db->commit();
            return $silinen;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getOkumalar($firmaId, $baslangic, $bitis, $bolge = '', $ekipKodu = '', $arama = '')
    {
        $where = "t.firma_id = ? AND t.tarih BETWEEN ? AND ?";
        $params = [$firmaId, $baslangic, $bitis];

        if ($bolge !== '') {
            $where .= " AND t.bolge = ?";
            $params[] = $bolge;
        }
        if ($ekipKodu !== '') {
            $where .= " AND COALESCE(NULLIF(TRIM(t.ekip_kodu), ''), TRIM(t.ekip_adi)) = ?";
            $params[] = $ekipKodu;
        }
        if ($arama !== '') {
            $where .= " AND (t.ekip_adi LIKE ? OR t.ekip_kodu LIKE ? OR t.mahalle LIKE ?)";
            $like = '%' . $arama . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT t.ekip_kodu, t.ekip_adi, t.bolge, t.defter, t.sayfa, t.sira_no,
                       t.mahalle, t.abone_no, t.abone_adsoyad, t.sayac_durum,
                       t.okuma_zamani, t.tarih
                  FROM {$this->table} t
                 WHERE $where
                 ORDER BY t.ekip_kodu ASC, t.okuma_zamani ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getDistinctBolgeler($firmaId)
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT bolge FROM {$this->table}
              WHERE firma_id = ? AND bolge IS NOT NULL AND bolge != ''
              ORDER BY bolge ASC"
        );
        $stmt->execute([$firmaId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getDistinctEkipler($firmaId)
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(NULLIF(TRIM(ekip_kodu), ''), TRIM(ekip_adi)) as ekip_kodu,
                    MAX(ekip_adi) as ekip_adi, COUNT(*) as okuma_sayisi
               FROM {$this->table}
              WHERE firma_id = ?
                AND (COALESCE(NULLIF(TRIM(ekip_kodu), ''), TRIM(ekip_adi))) != ''
              GROUP BY COALESCE(NULLIF(TRIM(ekip_kodu), ''), TRIM(ekip_adi))
              ORDER BY ekip_kodu ASC"
        );
        $stmt->execute([$firmaId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function bozukEkipKodlariniTemizle($firmaId)
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table}
                SET ekip_kodu = ''
              WHERE firma_id = ?
                AND ekip_kodu REGEXP '^[0-9]{9,}$'"
        );
        $stmt->execute([$firmaId]);
        return $stmt->rowCount();
    }

    public function getTarihAraligi($firmaId)
    {
        $stmt = $this->db->prepare(
            "SELECT MIN(tarih) as ilk, MAX(tarih) as son, COUNT(*) as toplam
               FROM {$this->table} WHERE firma_id = ?"
        );
        $stmt->execute([$firmaId]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function getEkipEslesmeleri()
    {
        $stmt = $this->db->prepare(
            "SELECT def.id, def.tur_adi, def.ekip_bolge,
                    GROUP_CONCAT(DISTINCT p.adi_soyadi ORDER BY p.adi_soyadi SEPARATOR ', ') as personeller
               FROM tanimlamalar def
               LEFT JOIN personel p ON p.ekip_no = def.id AND p.silinme_tarihi IS NULL
              WHERE def.grup = 'ekip_kodu' AND def.silinme_tarihi IS NULL
              GROUP BY def.id, def.tur_adi, def.ekip_bolge"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
