<?php
namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use App\Helper\Helper;
use PDO;

class TanimlamalarModel extends Model
{
    protected $table = 'tanimlamalar';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Belirli bir gruba ait tanımlamaları getirir
     * @param string $grup Grup adı (departman, is_turu, izin_turu, ekip_kodu, vb.)
     * @return array Tanımlamalar listesi
     */
    public function getByGrup($grup)
    {
        $sql = "SELECT * FROM {$this->table} WHERE grup = ? AND firma_id = ? AND silinme_tarihi IS NULL ORDER BY tur_adi ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$grup, $_SESSION['firma_id']]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }


    public function getGelirGiderTurleriSelect($type)
    {
        $sql = $this->db->prepare("SELECT id,tur_adi FROM $this->table WHERE type = ? AND silinme_tarihi IS NULL");
        $sql->execute([$type]);
        $result = $sql->fetchAll(PDO::FETCH_OBJ);

        $select = "<option value='0' disabled >Seçiniz</option>";

        foreach ($result as $item) {
            $select .= '<option value="' . $item->id . '">' . $item->tur_adi . '</option>';
        }

        return $select;
    }


    //ekleme yapıldıktan sonra eklenen kaydın bilgileri tabloya eklemek için
    public function getTableRow($id)
    {
        $data = $this->find($id);
        $enc_id = Security::encrypt($data->id);


        return '<tr id="row_' . $data->id . '"> 
            <td class="text-center">' . $data->id . '</td>
            <td class="text-center" style="width:8%">' . Helper::getBadge($data->type) . '</td>
            <td>' . $data->tur_adi . '</td>
            <td>' . $data->aciklama . '</td>
            <td>' . $data->kayit_tarihi . '</td>
            <td class="text-center" style="width:5%">
                <div class="flex-shrink-0">
                    <div class="dropdown align-self-start">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                             <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                        </a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item duzenle" href="#" data-id="' . $enc_id . '">
                                <span class="mdi mdi-account-edit" style="font-size: 18px;"></span> Düzenle
                            </a>
                            <a class="dropdown-item sil" href="#" data-id="' . $enc_id . '">
                                <span class="mdi mdi-delete" style="font-size: 18px;"></span> Sil
                            </a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>';
    }

    // Ekip Kodu için tablo satırı
    public function getEkipKoduTableRow($id)
    {
        $sql = "SELECT t.*, 
                (SELECT COUNT(DISTINCT p.id) FROM personel p 
                 WHERE p.firma_id = t.firma_id AND p.aktif_mi IN (1, 2) 
                 AND (EXISTS (SELECT 1 FROM personel_ekip_gecmisi peg 
                              WHERE peg.personel_id = p.id AND peg.ekip_kodu_id = t.id 
                              AND (peg.baslangic_tarihi IS NULL OR peg.baslangic_tarihi = '' OR DATE(peg.baslangic_tarihi) <= CURDATE())
                              AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi = '' OR peg.bitis_tarihi = '0000-00-00' OR DATE(peg.bitis_tarihi) >= CURDATE()))
                      OR (p.ekip_no = t.id AND NOT EXISTS (SELECT 1 FROM personel_ekip_gecmisi peg2 WHERE peg2.personel_id = p.id)))) as kullanim_sayisi,
                (SELECT GROUP_CONCAT(DISTINCT p.adi_soyadi SEPARATOR ', ') FROM personel p 
                 WHERE p.firma_id = t.firma_id AND p.aktif_mi IN (1, 2) 
                 AND (EXISTS (SELECT 1 FROM personel_ekip_gecmisi peg 
                              WHERE peg.personel_id = p.id AND peg.ekip_kodu_id = t.id 
                              AND (peg.baslangic_tarihi IS NULL OR peg.baslangic_tarihi = '' OR DATE(peg.baslangic_tarihi) <= CURDATE())
                              AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi = '' OR peg.bitis_tarihi = '0000-00-00' OR DATE(peg.bitis_tarihi) >= CURDATE()))
                      OR (p.ekip_no = t.id AND NOT EXISTS (SELECT 1 FROM personel_ekip_gecmisi peg2 WHERE peg2.personel_id = p.id)))) as personel_isimleri
                FROM $this->table t 
                WHERE t.id = ? AND t.firma_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id, $_SESSION['firma_id']]);
        $data = $stmt->fetch(PDO::FETCH_OBJ);

        $enc_id = Security::encrypt($data->id);
        $durum = $data->kullanim_sayisi > 0 ? '<span class="badge bg-danger">Dolu</span>' : '<span class="badge bg-success">Boşta</span>';
        $personel = !empty($data->personel_isimleri) ? '<br><small class="text-muted">' . $data->personel_isimleri . '</small>' : '';

        return '<tr id="row_' . $data->id . '">
            <td class="text-center">' . $data->id . '</td>
            <td class="text-center">' . $data->ekip_bolge . '</td>
            <td class="text-center">' . $data->tur_adi . '</td>
            <td class="text-center">' . $data->aciklama . '</td>
            <td class="text-center">' . $durum . $personel . '</td>
            <td class="text-center" style="width:5%">
                <div class="flex-shrink-0">
                    <div class="dropdown align-self-start">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                             <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                        </a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item duzenle" href="#" data-id="' . $enc_id . '">
                                <span class="mdi mdi-account-edit" style="font-size: 18px;"></span> Düzenle
                            </a>
                            <a class="dropdown-item sil" href="#" data-id="' . $enc_id . '">
                                <span class="mdi mdi-delete" style="font-size: 18px;"></span> Sil
                            </a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>';
    }

    public function getEkipKodlari()
    {
        $sql = $this->db->prepare("SELECT t.*, 
                                   (SELECT COUNT(DISTINCT p.id) FROM personel p 
                                    WHERE p.firma_id = t.firma_id AND p.aktif_mi IN (1, 2) 
                                    AND (EXISTS (SELECT 1 FROM personel_ekip_gecmisi peg 
                                                 WHERE peg.personel_id = p.id AND peg.ekip_kodu_id = t.id 
                                                 AND (peg.baslangic_tarihi IS NULL OR peg.baslangic_tarihi = '' OR DATE(peg.baslangic_tarihi) <= CURDATE())
                                                 AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi = '' OR peg.bitis_tarihi = '0000-00-00' OR DATE(peg.bitis_tarihi) >= CURDATE()))
                                         OR (p.ekip_no = t.id AND NOT EXISTS (SELECT 1 FROM personel_ekip_gecmisi peg2 WHERE peg2.personel_id = p.id)))) as kullanim_sayisi,
                                   (SELECT GROUP_CONCAT(DISTINCT p.adi_soyadi SEPARATOR ', ') FROM personel p 
                                    WHERE p.firma_id = t.firma_id AND p.aktif_mi IN (1, 2) 
                                    AND (EXISTS (SELECT 1 FROM personel_ekip_gecmisi peg 
                                                 WHERE peg.personel_id = p.id AND peg.ekip_kodu_id = t.id 
                                                 AND (peg.baslangic_tarihi IS NULL OR peg.baslangic_tarihi = '' OR DATE(peg.baslangic_tarihi) <= CURDATE())
                                                 AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi = '' OR peg.bitis_tarihi = '0000-00-00' OR DATE(peg.bitis_tarihi) >= CURDATE()))
                                         OR (p.ekip_no = t.id AND NOT EXISTS (SELECT 1 FROM personel_ekip_gecmisi peg2 WHERE peg2.personel_id = p.id)))) as personel_isimleri
                                   FROM $this->table t 
                                   WHERE t.grup = ? AND t.firma_id = ? AND t.silinme_tarihi IS NULL
                                   ORDER BY t.id DESC");
        $sql->execute(['ekip_kodu', $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Ekip kodunun personel tablosunda kullanılıp kullanılmadığını kontrol eder
     * @param int $id Ekip kodu ID'si
     * @return bool Boşta ise true, dolu ise false döner
     */
    public function getEkipKoduBosmu($id)
    {
        $sql = $this->db->prepare("SELECT COUNT(DISTINCT p.id) 
                                   FROM personel p 
                                   WHERE p.firma_id = ? AND p.aktif_mi IN (1, 2) 
                                     AND (EXISTS (SELECT 1 FROM personel_ekip_gecmisi peg 
                                                  WHERE peg.personel_id = p.id AND peg.ekip_kodu_id = ? 
                                                  AND (peg.baslangic_tarihi IS NULL OR peg.baslangic_tarihi = '' OR DATE(peg.baslangic_tarihi) <= CURDATE())
                                                  AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi = '' OR peg.bitis_tarihi = '0000-00-00' OR DATE(peg.bitis_tarihi) >= CURDATE()))
                                          OR (p.ekip_no = ? AND NOT EXISTS (SELECT 1 FROM personel_ekip_gecmisi peg2 WHERE peg2.personel_id = p.id)))");
        $sql->execute([$_SESSION['firma_id'], $id, $id]);
        $count = $sql->fetchColumn();
        return $count > 0 ? false : true;
    }

    /**
     * Aktif personeli olmayan (müsait) ekip kodlarını getirir
     * @param string|null $includeEkipNo Dahil edilecek ekip kodu (güncelleme işlemlerinde mevcut personelin ekip kodunu göstermek için)
     * @return array Müsait ekip kodları listesi
     */
    public function getMusaitEkipKodlari($includeEkipNo = null)
    {
        $firma_id = $_SESSION['firma_id'];
        // Aktif veya Maaş Hesaplanmayan personelin direkt ekip_no'larını al (Sadece hiç geçmişi olmayanlar için)
        $personelSql = $this->db->prepare("SELECT DISTINCT ekip_no FROM personel 
                                           WHERE ekip_no IS NOT NULL AND ekip_no != 0 
                                           AND aktif_mi IN (1, 2) AND firma_id = ?
                                           AND NOT EXISTS (SELECT 1 FROM personel_ekip_gecmisi WHERE personel_id = personel.id)");
        $personelSql->execute([$firma_id]);
        $personelAktif = $personelSql->fetchAll(PDO::FETCH_COLUMN);

        // Aktif veya Maaş Hesaplanmayan personelin geçmişindeki aktif kayıtları al
        $gecmisSql = $this->db->prepare("SELECT DISTINCT peg.ekip_kodu_id 
                                         FROM personel_ekip_gecmisi peg
                                         JOIN personel p ON peg.personel_id = p.id
                                         WHERE peg.firma_id = ? AND p.aktif_mi IN (1, 2)
                                         AND (peg.baslangic_tarihi IS NULL OR peg.baslangic_tarihi = '' OR DATE(peg.baslangic_tarihi) <= CURDATE())
                                         AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi = '' OR peg.bitis_tarihi = '0000-00-00' OR DATE(peg.bitis_tarihi) >= CURDATE())");
        $gecmisSql->execute([$firma_id]);
        $gecmisAktif = $gecmisSql->fetchAll(PDO::FETCH_COLUMN);

        $aktifEkipKodlariResult = array_unique(array_merge($personelAktif, $gecmisAktif));

        if ($includeEkipNo) {
            $aktifEkipKodlariResult = array_filter($aktifEkipKodlariResult, function ($kod) use ($includeEkipNo) {
                return (int) $kod != (int) $includeEkipNo;
            });
        }

        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE grup = 'ekip_kodu' AND firma_id = ? AND silinme_tarihi IS NULL ORDER BY tur_adi ASC");
        $sql->execute([$firma_id]);
        $tumEkipKodlari = $sql->fetchAll(PDO::FETCH_OBJ);

        $musaitEkipKodlari = array_filter($tumEkipKodlari, function ($item) use ($aktifEkipKodlariResult) {
            return !in_array($item->id, $aktifEkipKodlariResult);
        });

        return array_values($musaitEkipKodlari);
    }


    /**
     * Ekip kodu varsa true döner
     */
    public function getEkipKoduVarmi($tur_adi, $id = 0)
    {
        $query = "SELECT * FROM $this->table WHERE tur_adi = ? and firma_id = ? and silinme_tarihi IS NULL";
        $params = [$tur_adi, $_SESSION['firma_id']];

        if ($id > 0) {
            $query .= " AND id != ?";
            $params[] = $id;
        }

        $query .= " ORDER BY id DESC";
        $sql = $this->db->prepare($query);
        $sql->execute($params);
        return $sql->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function getIsTurleri()
    {
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE grup = ? and silinme_tarihi IS NULL ORDER BY id DESC");
        $sql->execute(['is_turu']);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function getIzinTurleri()
    {
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE grup = ? and silinme_tarihi IS NULL ORDER BY id DESC");
        $sql->execute(['izin_turu']);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    //Tur adını getir
    public function getTurAdi($id)
    {
        $data = $this->find($id);
        return $data->tur_adi;
    }

    /**
     * Belirli bir sütun değerine göre kayıt bul
     * @param string $column Aranacak sütun adı
     * @param mixed $value Aranacak değer
     * @param string|null $additionalWhere Ek WHERE koşulu
     * @return object|null Bulunan kayıt veya null
     */
    public function findByColumn($column, $value, $additionalWhere = null)
    {
        $sql = "SELECT * FROM $this->table WHERE $column = ?";
        if ($additionalWhere) {
            $sql .= " AND " . $additionalWhere;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Birden fazla sütun değerine göre kayıt bul
     * @param array $criteria [sütun => değer] şeklinde dizi
     * @param string|null $additionalWhere Ek WHERE koşulu
     * @return object|null Bulunan kayıt veya null
     */
    public function findByColumns($criteria, $additionalWhere = null)
    {
        $where = [];
        $params = [];
        foreach ($criteria as $column => $value) {
            $where[] = "$column = ?";
            $params[] = $value;
        }

        $sql = "SELECT * FROM $this->table WHERE " . implode(" AND ", $where);
        if ($additionalWhere) {
            $sql .= " AND " . $additionalWhere;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**Ekip Kodundan id bulur */
    public function getEkipKodId($ekip_no)
    {

        $firma_id = $_SESSION['firma_id'];
        $sql = "SELECT id FROM $this->table WHERE tur_adi = ? AND firma_id = ? AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ekip_no, $firma_id]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }
    /**
     * Ekip bölgelerini getirir
     * @return array Bölgeler listesi
     */
    public function getEkipBolgeleri()
    {
        $sql = "SELECT DISTINCT ekip_bolge FROM $this->table WHERE grup = 'ekip_kodu' AND ekip_bolge IS NOT NULL AND ekip_bolge != '' AND ekip_bolge != '0' AND firma_id = ? AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$_SESSION['firma_id']]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Belirli bir bölgedeki müsait ekip kodlarını getirir
     * @param string $bolge Bölge adı
     * @param int|null $includeEkipNo Dahil edilecek ekip kodu ID'si
     * @return array Müsait ekip kodları listesi
     */
    public function getMusaitEkipKodlariByBolge($bolge, $includeEkipNo = null)
    {
        $firma_id = $_SESSION['firma_id'];
        // Aktif veya Maaş Hesaplanmayan personelin direkt ekip_no'larını al (Sadece hiç geçmişi olmayanlar için)
        $personelSql = $this->db->prepare("SELECT DISTINCT ekip_no FROM personel 
                                           WHERE ekip_no IS NOT NULL AND ekip_no != 0 
                                           AND aktif_mi IN (1, 2) AND firma_id = ?
                                           AND NOT EXISTS (SELECT 1 FROM personel_ekip_gecmisi WHERE personel_id = personel.id)");
        $personelSql->execute([$firma_id]);
        $personelAktif = $personelSql->fetchAll(PDO::FETCH_COLUMN);

        // Aktif veya Maaş Hesaplanmayan personelin geçmişindeki aktif kayıtları al
        $gecmisSql = $this->db->prepare("SELECT DISTINCT peg.ekip_kodu_id 
                                         FROM personel_ekip_gecmisi peg
                                         JOIN personel p ON peg.personel_id = p.id
                                         WHERE peg.firma_id = ? AND p.aktif_mi IN (1, 2)
                                         AND (peg.baslangic_tarihi IS NULL OR peg.baslangic_tarihi = '' OR DATE(peg.baslangic_tarihi) <= CURDATE())
                                         AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi = '' OR peg.bitis_tarihi = '0000-00-00' OR DATE(peg.bitis_tarihi) >= CURDATE())");
        $gecmisSql->execute([$firma_id]);
        $gecmisAktif = $gecmisSql->fetchAll(PDO::FETCH_COLUMN);

        $aktifEkipKodlariResult = array_unique(array_merge($personelAktif, $gecmisAktif));

        if ($includeEkipNo) {
            $aktifEkipKodlariResult = array_filter($aktifEkipKodlariResult, function ($kod) use ($includeEkipNo) {
                return (int) $kod != (int) $includeEkipNo;
            });
        }

        $sql = "SELECT * FROM $this->table WHERE grup = 'ekip_kodu' AND ekip_bolge = ? AND firma_id = ? AND silinme_tarihi IS NULL ORDER BY tur_adi ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bolge, $firma_id]);
        $bolgeEkipKodlari = $stmt->fetchAll(PDO::FETCH_OBJ);

        $musaitEkipKodlari = array_filter($bolgeEkipKodlari, function ($item) use ($aktifEkipKodlariResult) {
            return !in_array($item->id, $aktifEkipKodlariResult);
        });

        return array_values($musaitEkipKodlari);
    }

    public function getEkipKodlariByBolgeAll($bolge)
    {
        $sql = "SELECT * FROM $this->table WHERE grup = 'ekip_kodu' AND ekip_bolge = ? AND firma_id = ? AND silinme_tarihi IS NULL ORDER BY tur_adi ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bolge, $_SESSION['firma_id']]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getUcretliIsTurleri()
    {
        $sql = "SELECT * FROM $this->table WHERE grup = 'is_turu' AND is_turu_ucret > 0 AND silinme_tarihi IS NULL ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getIsTurleriByRaporTuru($raporTuru)
    {
        $sql = "SELECT * FROM $this->table WHERE grup = 'is_turu' AND rapor_sekmesi = ? AND silinme_tarihi IS NULL ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$raporTuru]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
    public function getIsTurleriAdlari()
    {
        $sql = "SELECT DISTINCT tur_adi FROM $this->table WHERE grup = 'is_turu' AND tur_adi IS NOT NULL AND tur_adi != '' AND firma_id = ? AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$_SESSION['firma_id']]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }



    /** İş Emri Sonucu Tanımlı mı */
    public function isEmriSonucu($isEmriTipi, $isEmriSonucu)
    {
        $sql = "SELECT * FROM $this->table 
                        WHERE grup = 'is_turu' 
                        AND tur_adi = :tur_adi 
                        AND is_emri_sonucu = :is_emri_sonucu 
                        AND firma_id = :firma_id 
                        AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tur_adi' => $isEmriTipi,
            'is_emri_sonucu' => $isEmriSonucu,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }


    /**Ekip Kodu silerken personel,puantaj ve endeks_okuma tablolarında kontrol eder */
    public function ekipKoduKullaniliyormu($id)
    {
        $sql = "SELECT 
                t.id,
                COUNT(DISTINCT p.id) AS personel_sayisi,
                COUNT(DISTINCT yi.id) AS is_sayisi,
                COUNT(DISTINCT eo.id) AS okuma_sayisi
            FROM tanimlamalar t
            LEFT JOIN personel p ON p.ekip_no = t.id
            LEFT JOIN yapilan_isler yi ON yi.personel_id = p.id
            LEFT JOIN endeks_okuma eo ON eo.personel_id = p.id
            WHERE t.id = ?
            GROUP BY t.id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**İş türü kullanılıyor mu kontrol et */
    public function isTuruKullaniliyor($id)
    {
        $sql = "SELECT * FROM yapilan_isler WHERE is_emri_sonucu_id = ? AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Benzersiz iş emri sonuçlarını getirir (Demirbaş otomatik zimmet için)
     * @return array İş emri sonuçları listesi
     */
    public function getIsEmriSonuclari()
    {
        $sql = "SELECT DISTINCT is_emri_sonucu 
                FROM {$this->table} 
                WHERE grup = 'is_turu' 
                AND is_emri_sonucu IS NOT NULL 
                AND is_emri_sonucu != '' 
                AND silinme_tarihi IS NULL 
                ORDER BY is_emri_sonucu ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }


}
