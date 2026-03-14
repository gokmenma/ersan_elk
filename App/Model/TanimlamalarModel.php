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


    public function getGelirGiderTurleriSelect($type = null)
    {
        $where = "silinme_tarihi IS NULL";
        $params = [];
        
        if (!empty($type)) {
            $where .= " AND type = ?";
            $params[] = $type;
        }

        // Kategori isimleri gelir_gider tablosunda direkt varchar olarak tutuluyormuş 
        // o yüzden tanimlamalar yerine gelir_gider tablosundan distinct olarak çekmeliyiz.
        $sql = $this->db->prepare("SELECT DISTINCT kategori as tur_adi, kategori as id FROM gelir_gider WHERE $where ORDER BY kategori ASC");
        $sql->execute($params);
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
        if (isset($data->birden_fazla_personel_kullanabilir) && $data->birden_fazla_personel_kullanabilir == 1) {
            $durum .= ' <span class="badge bg-info">Çoklu</span>';
        }
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

    // Defter Kodu için tablo satırı
    public function getDefterKoduTableRow($id)
    {
        $data = $this->find($id);
        $enc_id = Security::encrypt($data->id);

        $baslangic_tarihi = $data->baslangic_tarihi ? date('d.m.Y', strtotime($data->baslangic_tarihi)) : '';
        $bitis_tarihi = $data->bitis_tarihi ? date('d.m.Y', strtotime($data->bitis_tarihi)) : '';

        return '<tr id="row_' . $data->id . '">
            <td class="text-center">' . $data->id . '</td>
            <td class="text-center">' . $data->tur_adi . '</td>
            <td class="text-center">' . $data->defter_bolge . '</td>
            <td class="text-center">' . $data->defter_mahalle . '</td>
            <td class="text-center">' . $data->defter_abone_sayisi . '</td>
            <td class="text-center">' . $baslangic_tarihi . '</td>
            <td class="text-center">' . $bitis_tarihi . '</td>
            <td class="text-center">' . $data->aciklama . '</td>
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
                                         OR (p.ekip_no = t.id AND NOT EXISTS (SELECT 1 FROM personel_ekip_gecmisi peg2 WHERE peg2.personel_id = p.id)))) as personel_isimleri,
                                   TRIM(t.ekip_bolge) as ekip_bolge
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
            return (isset($item->birden_fazla_personel_kullanabilir) && $item->birden_fazla_personel_kullanabilir == 1) || !in_array($item->id, $aktifEkipKodlariResult);
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
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE grup = ? AND firma_id = ? and silinme_tarihi IS NULL ORDER BY id DESC");
        $sql->execute(['is_turu', $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function getIzinTurleri()
    {
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE grup = ? AND firma_id = ? and silinme_tarihi IS NULL ORDER BY id DESC");
        $sql->execute(['izin_turu', $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function getDemirbasKategorileri()
    {
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE grup = ? AND firma_id = ? AND silinme_tarihi IS NULL ORDER BY id DESC");
        $sql->execute(['demirbas_kategorisi', $_SESSION['firma_id']]);
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
        $sql = "SELECT DISTINCT TRIM(ekip_bolge) FROM $this->table WHERE grup = 'ekip_kodu' AND ekip_bolge IS NOT NULL AND ekip_bolge != '' AND ekip_bolge != '0' AND firma_id = ? AND silinme_tarihi IS NULL";
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
            return (isset($item->birden_fazla_personel_kullanabilir) && $item->birden_fazla_personel_kullanabilir == 1) || !in_array($item->id, $aktifEkipKodlariResult);
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
        // Aynı isimli iş türlerini tekilleştir (Trimleyerek)
        $sql = "SELECT id, tur_adi, TRIM(is_emri_sonucu) as is_emri_sonucu, is_turu_ucret, rapor_sekmesi 
            FROM $this->table 
            WHERE id IN (
                SELECT MAX(id) 
                FROM $this->table 
                WHERE grup = 'is_turu' AND is_turu_ucret > 0 AND firma_id = ? AND silinme_tarihi IS NULL 
                GROUP BY TRIM(is_emri_sonucu)
            )
            ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$_SESSION['firma_id']]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getIsTurleriByRaporTuru($raporTuru)
    {
        // Aynı isimli iş türlerini tekilleştir (Trimleyerek)
        $sql = "SELECT id, tur_adi, TRIM(is_emri_sonucu) as is_emri_sonucu, is_turu_ucret, rapor_sekmesi 
            FROM $this->table 
            WHERE id IN (
                SELECT MAX(id) 
                FROM $this->table 
                WHERE grup = 'is_turu' AND rapor_sekmesi = ? AND firma_id = ? AND is_turu_ucret > 0 AND silinme_tarihi IS NULL 
                GROUP BY TRIM(is_emri_sonucu)
            )
            ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$raporTuru, $_SESSION['firma_id']]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
    public function getIsTurleriAdlari()
    {
        $sql = "SELECT DISTINCT tur_adi FROM $this->table WHERE grup = 'is_turu' AND tur_adi IS NOT NULL AND tur_adi != '' AND firma_id = ? AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$_SESSION['firma_id']]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }



    public function isEmriSonucu($isEmriTipi, $isEmriSonucu)
    {
        // Gelen verileri ve veritabanı değerlerini trim ile karşılaştır
        $sql = "SELECT * FROM $this->table 
                        WHERE grup = 'is_turu' 
                        AND TRIM(tur_adi) = :tur_adi 
                        AND TRIM(is_emri_sonucu) = :is_emri_sonucu 
                        AND firma_id = :firma_id 
                        AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tur_adi' => trim($isEmriTipi),
            'is_emri_sonucu' => trim($isEmriSonucu),
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }


    /**Ekip Kodu silerken personel, personel_ekip_gecmisi, yapilan_isler ve endeks_okuma tablolarında kontrol eder */
    public function ekipKoduKullaniliyormu($id)
    {
        $sql = "SELECT 
                (SELECT COUNT(*) FROM personel_ekip_gecmisi WHERE ekip_kodu_id = ?) as gecmis_sayisi,
                (SELECT COUNT(*) FROM endeks_okuma WHERE ekip_kodu_id = ? AND silinme_tarihi IS NULL) as okuma_sayisi,
                (SELECT COUNT(*) FROM yapilan_isler WHERE ekip_kodu_id = ? AND silinme_tarihi IS NULL) as is_sayisi,
                (SELECT COUNT(*) FROM personel WHERE ekip_no = ? AND silinme_tarihi IS NULL) as personel_sayisi";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id, $id, $id, $id]);
        $res = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$res) {
            return false;
        }

        return ($res->gecmis_sayisi > 0 || $res->okuma_sayisi > 0 || $res->is_sayisi > 0 || $res->personel_sayisi > 0);
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
     * Demirbaş kategorisi kullanılıyor mu kontrol et
     */
    public function isDemirbasKategorisiKullaniliyor($id)
    {
        $sql = "SELECT id FROM demirbas WHERE kategori_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_OBJ) ? true : false;
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
                AND firma_id = ? 
                AND silinme_tarihi IS NULL 
                ORDER BY is_emri_sonucu ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$_SESSION['firma_id']]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * İş emri sonuçlarını id ve adıyla getirir (Demirbaş otomatik zimmet için)
     * @return array İş emri sonuçları listesi (id, tur_adi, is_emri_sonucu)
     */
    public function getIsEmriSonuclariWithId()
    {
        $sql = "SELECT id, is_emri_sonucu, tur_adi 
                FROM {$this->table} 
                WHERE grup = 'is_turu' 
                AND is_emri_sonucu IS NOT NULL 
                AND is_emri_sonucu != '' 
                AND firma_id = ? 
                AND silinme_tarihi IS NULL 
                ORDER BY tur_adi ASC, is_emri_sonucu ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$_SESSION['firma_id']]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }


    /**
     * Departmana göre unvan/ücret tanımlamalarını getirir
     * @param string $departman Departman adı
     * @return array Unvan/ücret listesi
     */
    public function getUnvanUcretlerByDepartman($departman)
    {
        $sql = "SELECT * FROM {$this->table} WHERE grup = 'unvan_ucret' AND unvan_departman = ? AND firma_id = ? AND silinme_tarihi IS NULL ORDER BY tur_adi ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$departman, $_SESSION['firma_id']]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Ekip kodunun geçmişini getirir
     * @param int $ekip_kodu_id Ekip kodu ID'si
     * @return array Geçmiş listesi
     */
    public function getEkipGecmisi($ekip_kodu_id)
    {
        $sql = "SELECT peg.*, p.adi_soyadi 
                FROM personel_ekip_gecmisi peg
                JOIN personel p ON peg.personel_id = p.id
                WHERE peg.ekip_kodu_id = ? AND peg.firma_id = ?
                ORDER BY peg.baslangic_tarihi DESC, peg.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ekip_kodu_id, $_SESSION['firma_id']]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Server-side DataTables verilerini getirir
     */
    public function getServerSideData($grup, $params)
    {
        $draw = $params['draw'];
        $start = $params['start'];
        $length = $params['length'];
        $searchValue = $params['search']['value'] ?? '';
        $orderColumn = $params['order'][0]['column'] ?? 0;
        $orderDir = $params['order'][0]['dir'] ?? 'asc';
        $columns = $params['columns'];

        $where = "WHERE grup = :grup AND firma_id = :firma_id AND silinme_tarihi IS NULL";
        $sqlParams = [
            'grup' => $grup,
            'firma_id' => $_SESSION['firma_id']
        ];

        if (!empty($searchValue)) {
            $where .= " AND (tur_adi LIKE :search 
                        OR defter_bolge LIKE :search 
                        OR defter_mahalle LIKE :search 
                        OR aciklama LIKE :search)";
            $sqlParams['search'] = "%$searchValue%";
        }

        // Sütun bazlı aramalar
        foreach ($columns as $index => $column) {
            $searchVal = $column['search']['value'] ?? '';
            if (!empty($searchVal)) {
                $colName = $column['data'];
                // Güvenlik için sütun adını doğrula
                $allowedSearchColumns = ['id', 'tur_adi', 'defter_bolge', 'defter_mahalle', 'defter_abone_sayisi', 'baslangic_tarihi', 'bitis_tarihi', 'aciklama'];
                if (in_array($colName, $allowedSearchColumns)) {
                    $paramName = "col_search_" . $index;
                    $where .= " AND $colName LIKE :$paramName";
                    $sqlParams[$paramName] = "%$searchVal%";
                }
            }
        }

        // Toplam kayıt sayısı (filtresiz)
        $totalSql = "SELECT COUNT(*) FROM {$this->table} WHERE grup = ? AND firma_id = ? AND silinme_tarihi IS NULL";
        $totalStmt = $this->db->prepare($totalSql);
        $totalStmt->execute([$grup, $_SESSION['firma_id']]);
        $totalData = $totalStmt->fetchColumn();

        // Filtrelenmiş kayıt sayısı
        $filterSql = "SELECT COUNT(*) FROM {$this->table} $where";
        $filterStmt = $this->db->prepare($filterSql);
        $filterStmt->execute($sqlParams);
        $totalFiltered = $filterStmt->fetchColumn();

        // Sıralama
        $orderColName = $columns[$orderColumn]['data'] ?? 'id';
        // Güvenlik için sütun adını kontrol et
        $allowedColumns = ['id', 'tur_adi', 'defter_bolge', 'defter_mahalle', 'defter_abone_sayisi', 'baslangic_tarihi', 'bitis_tarihi', 'aciklama'];
        if (!in_array($orderColName, $allowedColumns)) {
            $orderColName = 'id';
        }

        // Verileri getir
        $dataSql = "SELECT * FROM {$this->table} $where ORDER BY $orderColName $orderDir LIMIT $start, $length";
        $dataStmt = $this->db->prepare($dataSql);
        $dataStmt->execute($sqlParams);
        $dataRows = $dataStmt->fetchAll(PDO::FETCH_OBJ);

        return [
            "draw" => intval($draw),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $dataRows
        ];
    }
}
