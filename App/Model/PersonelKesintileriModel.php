<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class PersonelKesintileriModel extends Model
{
    protected $table = 'personel_kesintileri';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Personelin tüm kesintilerini getirir (listeleme için)
     * Sürekli kesintiler için ana_kesinti_id NULL olanları gösterir
     */
    public function getPersonelKesintileri($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT pk.*, pi.dosya_no, pi.icra_dairesi, bp.etiket as parametre_adi, bp.kod as parametre_kodu,
                   COALESCE(pk.durum, 'beklemede') as durum, bd.donem_adi, bd.kapali_mi
            FROM {$this->table} pk
            LEFT JOIN personel_icralari pi ON pk.icra_id = pi.id
            LEFT JOIN bordro_parametreleri bp ON pk.parametre_id = bp.id
            LEFT JOIN bordro_donemi bd ON pk.donem_id = bd.id
            WHERE pk.personel_id = ? 
              AND pk.silinme_tarihi IS NULL 
              AND pk.ana_kesinti_id IS NULL
            ORDER BY pk.tekrar_tipi DESC, pk.baslangic_donemi DESC, pk.donem_id DESC, pk.olusturma_tarihi DESC
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personelin aktif sürekli kesintilerini getirir
     * @param int $personel_id
     * @param string $donem YYYY-MM formatında dönem (dönem başlangıç tarihi olarak kullanılır)
     * @return array
     */
    public function getAktifSurekliKesintiler($personel_id, $donem)
    {
        // Dönemden tarih oluştur
        $donemBaslangic = $donem . '-01';
        $donemBitis = date('Y-m-t', strtotime($donemBaslangic));

        $sql = $this->db->prepare("
            SELECT pk.*, bp.etiket as parametre_adi, bp.kod as parametre_kodu, bp.hesaplama_tipi as param_hesaplama_tipi
            FROM {$this->table} pk
            LEFT JOIN bordro_parametreleri bp ON pk.parametre_id = bp.id
            WHERE pk.personel_id = ? 
              AND pk.tekrar_tipi = 'surekli'
              AND pk.aktif = 1
              AND pk.silinme_tarihi IS NULL
              AND pk.ana_kesinti_id IS NULL
              AND pk.baslangic_donemi <= ? -- Ayın son gününden önce başlamış olmalı
              AND (pk.bitis_donemi IS NULL OR pk.bitis_donemi >= ?) -- Ayın ilk gününden sonra bitiyor (veya açık uçlu) olmalı
            ORDER BY pk.olusturma_tarihi ASC
        ");
        $sql->execute([$personel_id, $donemBitis, $donemBaslangic]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Dönem için sürekli kesintiden otomatik oluşturulan kayıt var mı kontrol eder
     * Soft-delete yapılan kayıtları dikkate almaz (yeniden oluşturulabilir)
     */
    public function donemdeKaynakKayitVarMi($ana_kesinti_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT COUNT(*) as adet 
            FROM {$this->table} 
            WHERE ana_kesinti_id = ? AND donem_id = ? AND silinme_tarihi IS NULL
        ");
        $sql->execute([$ana_kesinti_id, $donem_id]);
        return $sql->fetch(PDO::FETCH_OBJ)->adet > 0;
    }

    /**
     * Sürekli kesintiden dönem için otomatik kesinti oluşturur
     * @param object $surekliKesinti Sürekli kesinti kaydı
     * @param int $donem_id Dönem ID
     * @param float $tutar Hesaplanan tutar (oran bazlı ise hesaplanmış tutar)
     * @return int|string|bool Eklenen kayıt ID'si veya false
     */
    public function olusturDonemKesintisi($surekliKesinti, $donem_id, $tutar)
    {
        // Bu dönem için zaten kayıt var mı kontrol et (Silinmişler dahil)
        $sql = $this->db->prepare("
            SELECT id, durum, silinme_tarihi FROM {$this->table} 
            WHERE ana_kesinti_id = ? AND donem_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $sql->execute([$surekliKesinti->id, $donem_id]);
        $mevcut = $sql->fetch(PDO::FETCH_OBJ);

        if ($mevcut) {
            // Kayıt varsa güncelle ve silinmişse geri getir
            $updateSql = "UPDATE {$this->table} SET 
                silinme_tarihi = NULL, 
                durum = 'onaylandi', 
                tutar = ?, 
                updated_at = NOW() 
                WHERE id = ?";
            
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([$tutar, $mevcut->id]);
            
            return true;
        }

        $data = [
            'personel_id' => $surekliKesinti->personel_id,
            'donem_id' => $donem_id,
            'tarih' => date('Y-m-d'), // Kayıt tarihi
            'tur' => $surekliKesinti->tur,
            'tekrar_tipi' => 'tek_sefer', // Oluşturulan kayıt tek seferlik olarak işaretlenir
            'hesaplama_tipi' => $surekliKesinti->hesaplama_tipi,
            'tutar' => $tutar,
            'oran' => $surekliKesinti->oran,
            'aciklama' => $surekliKesinti->aciklama . ' (Otomatik)',
            'parametre_id' => $surekliKesinti->parametre_id,
            'icra_id' => $surekliKesinti->icra_id,
            'ana_kesinti_id' => $surekliKesinti->id, // Ana kayıt referansı
            'aktif' => 1,
            'durum' => 'onaylandi'
        ];

        return $this->saveWithAttr($data);
    }

    /**
     * Sürekli kesintiyi pasife alır (sonlandırır)
     */
    public function sonlandirSurekliKesinti($id, $bitis_donemi = null)
    {
        $bitis = $bitis_donemi ?? date('Y-m');
        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET bitis_donemi = ?, aktif = 0, updated_at = NOW()
            WHERE id = ? AND tekrar_tipi = 'surekli'
        ");
        return $sql->execute([$bitis, $id]);
    }

    /**
     * Kesinti detayını getirir
     */
    public function getKesinti($id)
    {
        $sql = $this->db->prepare("
            SELECT pk.*, pi.dosya_no, pi.icra_dairesi, bp.etiket as parametre_adi, bp.kod as parametre_kodu
            FROM {$this->table} pk
            LEFT JOIN personel_icralari pi ON pk.icra_id = pi.id
            LEFT JOIN bordro_parametreleri bp ON pk.parametre_id = bp.id
            WHERE pk.id = ?
        ");
        $sql->execute([$id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Kesinti günceller
     */
    public function updateKesinti($id, $data)
    {
        // Mevcut durumu kontrol et
        $mevcut = $this->getKesinti($id);
        if (!$mevcut) {
            throw new \Exception('Kesinti bulunamadı.');
        }

        // Eğer mevcut durum 'onaylandi' ve yeni durum 'beklemede' ise engelle
        if (
            isset($mevcut->durum) && $mevcut->durum === 'onaylandi' &&
            isset($data['durum']) && $data['durum'] === 'beklemede'
        ) {
            throw new \Exception('Onaylanmış bir kesinti tekrar beklemede durumuna alınamaz.');
        }

        $sets = [];
        $params = [];

        foreach ($data as $key => $value) {
            $sets[] = "{$key} = ?";
            $params[] = $value;
        }

        $sets[] = "updated_at = NOW()";
        $params[] = $id;

        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET " . implode(', ', $sets) . "
            WHERE id = ?
        ");
        return $sql->execute($params);
    }

    /**
     * Ana kesintiden oluşturulan tüm dönem kayıtlarını getirir
     */
    public function getDonemKayitlari($ana_kesinti_id)
    {
        $sql = $this->db->prepare("
            SELECT pk.*, bd.donem_adi
            FROM {$this->table} pk
            LEFT JOIN bordro_donemi bd ON pk.donem_id = bd.id
            WHERE pk.ana_kesinti_id = ? AND pk.silinme_tarihi IS NULL
            ORDER BY pk.donem_id DESC
        ");
        $sql->execute([$ana_kesinti_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
