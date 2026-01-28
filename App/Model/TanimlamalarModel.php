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



    public function getGelirGiderTurleriSelect($type)
    {
        $sql = $this->db->prepare("SELECT id,tur_adi FROM $this->table WHERE type = ?");
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
        $data = $this->find($id);
        $enc_id = Security::encrypt($data->id);

        return '<tr id="row_' . $data->id . '">
            <td class="text-center">' . $data->id . '</td>
            <td class="text-center">' . $data->tur_adi . '</td>
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
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE grup = ? ORDER BY id DESC");
        $sql->execute(['ekip_kodu']);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Aktif personeli olmayan (müsait) ekip kodlarını getirir
     * @param string|null $includeEkipNo Dahil edilecek ekip kodu (güncelleme işlemlerinde mevcut personelin ekip kodunu göstermek için)
     * @return array Müsait ekip kodları listesi
     */
    public function getMusaitEkipKodlari($includeEkipNo = null)
    {
        // Aktif personeli olan ekip kodlarını bul
        $aktifEkipKodlari = [];
        $personelSql = $this->db->prepare("SELECT DISTINCT ekip_no FROM personel WHERE ekip_no IS NOT NULL AND ekip_no != '' AND ekip_no != 0 AND aktif_mi = 1 AND firma_id = ?");
        $personelSql->execute([$_SESSION['firma_id']]);
        $aktifEkipKodlariResult = $personelSql->fetchAll(PDO::FETCH_COLUMN);

        // Dahil edilecek ekip kodu varsa, onu aktif listesinden çıkar
        if ($includeEkipNo) {
            $aktifEkipKodlariResult = array_filter($aktifEkipKodlariResult, function ($kod) use ($includeEkipNo) {
                return $kod != $includeEkipNo;
            });
        }

        // Tüm ekip kodlarını al
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE grup = ? ORDER BY id DESC");
        $sql->execute(['ekip_kodu']);
        $tumEkipKodlari = $sql->fetchAll(PDO::FETCH_OBJ);

        // Aktif personeli olmayan ekip kodlarını filtrele
        $musaitEkipKodlari = array_filter($tumEkipKodlari, function ($item) use ($aktifEkipKodlariResult) {
            return !in_array($item->id, $aktifEkipKodlariResult);
        });

        return array_values($musaitEkipKodlari); // Reindex array
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

    /**Ekip Kodundan id bulur */
    public function getEkipKodId($ekip_no)
    {

        $firma_id = $_SESSION['firma_id'];
        $sql = "SELECT id FROM $this->table WHERE tur_adi = ? AND firma_id = ?";
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
        $sql = "SELECT DISTINCT ekip_bolge FROM $this->table WHERE grup = 'ekip_kodu' AND ekip_bolge IS NOT NULL AND ekip_bolge != '' AND ekip_bolge != '0' AND firma_id = ?";
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
        // Aktif personeli olan ekip kodlarını bul
        $personelSql = $this->db->prepare("SELECT DISTINCT ekip_no FROM personel WHERE ekip_no IS NOT NULL AND ekip_no != 0 AND aktif_mi = 1 AND firma_id = ?");
        $personelSql->execute([$_SESSION['firma_id']]);
        $aktifEkipKodlariResult = $personelSql->fetchAll(PDO::FETCH_COLUMN);

        // Dahil edilecek ekip kodu varsa, onu aktif listesinden çıkar
        if ($includeEkipNo) {
            $aktifEkipKodlariResult = array_filter($aktifEkipKodlariResult, function ($kod) use ($includeEkipNo) {
                return (int) $kod != (int) $includeEkipNo;
            });
        }

        // Belirli bölgedeki ekip kodlarını al
        $sql = "SELECT * FROM $this->table WHERE grup = 'ekip_kodu' AND ekip_bolge = ? AND firma_id = ? ORDER BY tur_adi ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bolge, $_SESSION['firma_id']]);
        $bolgeEkipKodlari = $stmt->fetchAll(PDO::FETCH_OBJ);

        // Aktif personeli olmayanları filtrele
        $musaitEkipKodlari = array_filter($bolgeEkipKodlari, function ($item) use ($aktifEkipKodlariResult) {
            return !in_array($item->id, $aktifEkipKodlariResult);
        });

        return array_values($musaitEkipKodlari);
    }
}