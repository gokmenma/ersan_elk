<?php

namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use PDO;

class GorevModel extends Model
{
    protected $table = 'gorevler';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    // =====================================================
    // LİSTE İŞLEMLERİ
    // =====================================================

    public function getListeler($firma_id)
    {
        $sql = "SELECT gl.*, 
                (SELECT COUNT(*) FROM gorevler g WHERE g.liste_id = gl.id AND g.tamamlandi = 0) as aktif_gorev_sayisi,
                (SELECT COUNT(*) FROM gorevler g WHERE g.liste_id = gl.id AND g.tamamlandi = 1) as tamamlanan_gorev_sayisi
                FROM gorev_listeleri gl 
                WHERE gl.firma_id = :firma_id 
                ORDER BY gl.sira ASC, gl.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':firma_id' => $firma_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function addListe($data)
    {
        // Mevcut en yüksek sıra numarasını bul
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(sira), 0) + 1 as next_sira FROM gorev_listeleri WHERE firma_id = :firma_id");
        $stmt->execute([':firma_id' => $data['firma_id']]);
        $nextSira = $stmt->fetch(PDO::FETCH_OBJ)->next_sira;

        $sql = "INSERT INTO gorev_listeleri (firma_id, baslik, sira, renk, olusturan_id) 
                VALUES (:firma_id, :baslik, :sira, :renk, :olusturan_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':firma_id' => $data['firma_id'],
            ':baslik' => $data['baslik'],
            ':sira' => $nextSira,
            ':renk' => $data['renk'] ?? null,
            ':olusturan_id' => $data['olusturan_id']
        ]);
        return $this->db->lastInsertId();
    }

    public function updateListe($id, $data)
    {
        $sets = [];
        $params = [':id' => $id];

        if (isset($data['baslik'])) {
            $sets[] = 'baslik = :baslik';
            $params[':baslik'] = $data['baslik'];
        }
        if (isset($data['renk'])) {
            $sets[] = 'renk = :renk';
            $params[':renk'] = $data['renk'];
        }

        if (empty($sets))
            return false;

        $sql = "UPDATE gorev_listeleri SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteListe($id)
    {
        // CASCADE ile gorevler de silinir
        $sql = "DELETE FROM gorev_listeleri WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function updateListeSira($siralar)
    {
        $stmt = $this->db->prepare("UPDATE gorev_listeleri SET sira = :sira WHERE id = :id");
        foreach ($siralar as $sira) {
            $stmt->execute([':id' => $sira['id'], ':sira' => $sira['sira']]);
        }
        return true;
    }

    public function findListe($id)
    {
        $sql = "SELECT * FROM gorev_listeleri WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    // =====================================================
    // GÖREV İŞLEMLERİ
    // =====================================================

    public function getGorevler($liste_id, $tamamlandi = 0)
    {
        $sql = "SELECT * FROM gorevler 
                WHERE liste_id = :liste_id AND tamamlandi = :tamamlandi 
                ORDER BY sira ASC, id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':liste_id' => $liste_id, ':tamamlandi' => $tamamlandi]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getTumGorevler($firma_id)
    {
        $sql = "SELECT g.*, gl.baslik as liste_adi 
                FROM gorevler g 
                JOIN gorev_listeleri gl ON g.liste_id = gl.id 
                WHERE g.firma_id = :firma_id 
                ORDER BY gl.sira ASC, g.tamamlandi ASC, g.sira ASC, g.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':firma_id' => $firma_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function addGorev($data)
    {
        // Yeni görevlerin en üstte olması için MIN(sira) - 1 kullanıyoruz
        $stmt = $this->db->prepare("SELECT COALESCE(MIN(sira), 0) - 1 as next_sira FROM gorevler WHERE liste_id = :liste_id AND tamamlandi = 0");
        $stmt->execute([':liste_id' => $data['liste_id']]);
        $nextSira = $stmt->fetch(PDO::FETCH_OBJ)->next_sira;

        $sql = "INSERT INTO gorevler (liste_id, firma_id, baslik, aciklama, tarih, saat, sira, 
                yineleme_sikligi, yineleme_birimi, yineleme_gunleri, yineleme_baslangic, 
                yineleme_bitis_tipi, yineleme_bitis_tarihi, yineleme_bitis_adet, olusturan_id, gorev_kullanicilari, ana_sayfa_goster) 
                VALUES (:liste_id, :firma_id, :baslik, :aciklama, :tarih, :saat, :sira,
                :yineleme_sikligi, :yineleme_birimi, :yineleme_gunleri, :yineleme_baslangic,
                :yineleme_bitis_tipi, :yineleme_bitis_tarihi, :yineleme_bitis_adet, :olusturan_id, :gorev_kullanicilari, :ana_sayfa_goster)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':liste_id' => $data['liste_id'],
            ':firma_id' => $data['firma_id'],
            ':baslik' => $data['baslik'],
            ':aciklama' => $data['aciklama'] ?? null,
            ':tarih' => $data['tarih'] ?? null,
            ':saat' => $data['saat'] ?? null,
            ':sira' => $nextSira,
            ':yineleme_sikligi' => $data['yineleme_sikligi'] ?? null,
            ':yineleme_birimi' => $data['yineleme_birimi'] ?? null,
            ':yineleme_gunleri' => $data['yineleme_gunleri'] ?? null,
            ':yineleme_baslangic' => $data['yineleme_baslangic'] ?? null,
            ':yineleme_bitis_tipi' => $data['yineleme_bitis_tipi'] ?? null,
            ':yineleme_bitis_tarihi' => $data['yineleme_bitis_tarihi'] ?? null,
            ':yineleme_bitis_adet' => $data['yineleme_bitis_adet'] ?? null,
            ':olusturan_id' => $data['olusturan_id'],
            ':gorev_kullanicilari' => $data['gorev_kullanicilari'] ?? null,
            ':ana_sayfa_goster' => $data['ana_sayfa_goster'] ?? 1
        ]);
        return $this->db->lastInsertId();
    }

    public function updateGorev($id, $data)
    {
        $sets = [];
        $params = [':id' => $id];

        $allowedFields = [
            'baslik',
            'aciklama',
            'tarih',
            'saat',
            'yildizli',
            'yineleme_sikligi',
            'yineleme_birimi',
            'yineleme_gunleri',
            'yineleme_baslangic',
            'yineleme_bitis_tipi',
            'yineleme_bitis_tarihi',
            'yineleme_bitis_adet',
            'gorev_kullanicilari',
            'ana_sayfa_goster'
        ];

        $tarihSaatDegisti = false;

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = :$field";
                $params[":$field"] = $data[$field];

                if ($field === 'tarih' || $field === 'saat') {
                    $tarihSaatDegisti = true;
                }
            }
        }

        if (empty($sets))
            return false;

        // Tarih veya saat değiştiyse, bildirimleri tekrar gönderebilmek için bayrakları sıfırla
        if ($tarihSaatDegisti) {
            $sets[] = "bildirim_gonderildi = 0";
            $sets[] = "on_bildirim_gonderildi = 0";
            $sets[] = "tam_vakit_bildirim_gonderildi = 0";
        }

        $sql = "UPDATE gorevler SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteGorev($id)
    {
        $sql = "DELETE FROM gorevler WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function tamamla($id)
    {
        $sql = "UPDATE gorevler SET tamamlandi = 1, tamamlanma_tarihi = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function tamamlamayiGeriAl($id)
    {
        $sql = "UPDATE gorevler SET tamamlandi = 0, tamamlanma_tarihi = NULL WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function moveGorev($id, $liste_id, $sira)
    {
        $sql = "UPDATE gorevler SET liste_id = :liste_id, sira = :sira WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id, ':liste_id' => $liste_id, ':sira' => $sira]);
    }

    public function updateGorevSira($gorevler)
    {
        $stmt = $this->db->prepare("UPDATE gorevler SET sira = :sira, liste_id = :liste_id WHERE id = :id");
        foreach ($gorevler as $gorev) {
            $stmt->execute([
                ':id' => $gorev['id'],
                ':sira' => $gorev['sira'],
                ':liste_id' => $gorev['liste_id']
            ]);
        }
        return true;
    }

    public function getTamamlananlar($liste_id)
    {
        $sql = "SELECT * FROM gorevler 
                WHERE liste_id = :liste_id AND tamamlandi = 1 
                ORDER BY tamamlanma_tarihi DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':liste_id' => $liste_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getYaklasanGorevler($firma_id, $limit = 5)
    {
        $sql = "SELECT g.*, gl.baslik as liste_adi, gl.renk as liste_renk 
                FROM gorevler g 
                JOIN gorev_listeleri gl ON g.liste_id = gl.id 
                WHERE g.firma_id = :firma_id 
                AND g.tamamlandi = 0 
                AND g.ana_sayfa_goster = 1 
                ORDER BY g.tarih ASC, g.saat ASC, g.id ASC 
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':firma_id', $firma_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function findGorev($id)
    {
        $sql = "SELECT * FROM gorevler WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    // =====================================================
    // BİLDİRİM İŞLEMLERİ
    // =====================================================

    /**
     * Bugün tarihli, saati gelmiş veya ön bildirim saati gelmiş görevleri döner
     */
    public function getBildirimBekleyenGorevler()
    {
        $Settings = new \App\Model\SettingsModel();
        $offset = (int) ($Settings->getSettings('gorev_bildirim_dakika') ?? 0);

        // 1. ÖN BİLDİRİM BEKLEYENLER (Offset süresi gelenler)
        $sqlOn = "SELECT g.*, gl.baslik as liste_adi, gl.olusturan_id as liste_olusturan_id, 'on' as bildirim_tipi
                  FROM gorevler g
                  JOIN gorev_listeleri gl ON g.liste_id = gl.id
                  WHERE g.tarih = CURDATE()
                    AND g.tamamlandi = 0
                    AND g.on_bildirim_gonderildi = 0
                    AND COALESCE(g.saat, '09:00:00') <= ADDTIME(CURTIME(), SEC_TO_TIME(:offset * 60))
                    AND COALESCE(g.saat, '09:00:00') > CURTIME()";

        // 2. TAM VAKİT BİLDİRİM BEKLEYENLER (Saati gelmiş/geçmiş olanlar)
        $sqlTam = "SELECT g.*, gl.baslik as liste_adi, gl.olusturan_id as liste_olusturan_id, 'tam' as bildirim_tipi
                   FROM gorevler g
                   JOIN gorev_listeleri gl ON g.liste_id = gl.id
                   WHERE g.tarih = CURDATE()
                     AND g.tamamlandi = 0
                     AND g.tam_vakit_bildirim_gonderildi = 0
                     AND COALESCE(g.saat, '09:00:00') <= CURTIME()";

        $results = [];

        // Ön bildirimleri çek (Sadece offset 0'dan büyükse)
        if ($offset > 0) {
            $stmtOn = $this->db->prepare($sqlOn);
            $stmtOn->execute([':offset' => $offset]);
            $results = array_merge($results, $stmtOn->fetchAll(PDO::FETCH_OBJ));
        }

        // Tam vakit bildirimlerini çek
        $stmtTam = $this->db->prepare($sqlTam);
        $stmtTam->execute();
        $results = array_merge($results, $stmtTam->fetchAll(PDO::FETCH_OBJ));

        return $results;
    }

    /**
     * Görevin bildirim gönderildi flag'ini günceller
     * @param int $gorevId
     * @param string $tip 'on' veya 'tam' (varsayılan: 'tam')
     */
    public function markBildirimGonderildi($gorevId, $tip = 'tam')
    {
        if ($tip === 'on') {
            $sql = "UPDATE gorevler SET on_bildirim_gonderildi = 1 WHERE id = :id";
        } else {
            // Hem tam_vakit'i hem de ana bayrağı kapat
            $sql = "UPDATE gorevler SET tam_vakit_bildirim_gonderildi = 1, bildirim_gonderildi = 1 WHERE id = :id";
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $gorevId]);
    }
}
