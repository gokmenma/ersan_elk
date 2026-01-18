<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class BordroParametreModel extends Model
{
    protected $table = 'bordro_parametreleri';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Aktif parametreleri kategoriye göre getirir
     */
    public function getParametrelerByKategori($kategori, $tarih = null)
    {
        $tarih = $tarih ?? date('Y-m-d');

        $sql = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE kategori = ?
            AND aktif = 1
            AND (gecerlilik_baslangic IS NULL OR gecerlilik_baslangic <= ?)
            AND (gecerlilik_bitis IS NULL OR gecerlilik_bitis >= ?)
            ORDER BY sira ASC, etiket ASC
        ");
        $sql->execute([$kategori, $tarih, $tarih]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Gelir türlerini getirir
     */
    public function getGelirTurleri($tarih = null)
    {
        return $this->getParametrelerByKategori('gelir', $tarih);
    }

    /**
     * Kesinti türlerini getirir
     */
    public function getKesintiTurleri($tarih = null)
    {
        return $this->getParametrelerByKategori('kesinti', $tarih);
    }

    /**
     * Tüm aktif parametreleri getirir
     */
    public function getAllAktifParametreler($tarih = null)
    {
        $tarih = $tarih ?? date('Y-m-d');

        $sql = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE aktif = 1
            AND (gecerlilik_baslangic IS NULL OR gecerlilik_baslangic <= ?)
            AND (gecerlilik_bitis IS NULL OR gecerlilik_bitis >= ?)
            ORDER BY kategori ASC, sira ASC, etiket ASC
        ");
        $sql->execute([$tarih, $tarih]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Kod'a göre parametre getirir (belirli tarihe göre)
     */
    public function getByKod($kod, $tarih = null)
    {
        $tarih = $tarih ?? date('Y-m-d');

        $sql = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE kod = ? 
            AND aktif = 1
            AND (gecerlilik_baslangic IS NULL OR gecerlilik_baslangic <= ?)
            AND (gecerlilik_bitis IS NULL OR gecerlilik_bitis >= ?)
            ORDER BY gecerlilik_baslangic DESC
            LIMIT 1
        ");
        $sql->execute([$kod, $tarih, $tarih]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Tüm parametreleri getirir (admin için, tarih filtresi yok)
     */
    public function getAllParametreler()
    {
        $sql = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE aktif = 1
            ORDER BY kategori ASC, kod ASC, gecerlilik_baslangic DESC
        ");
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Belirli kod için tüm dönem kayıtlarını getirir
     */
    public function getParametreGecmisi($kod)
    {
        $sql = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE kod = ?
            ORDER BY gecerlilik_baslangic DESC
        ");
        $sql->execute([$kod]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Parametre ekler
     */
    public function addParametre($data)
    {
        $sql = $this->db->prepare("
            INSERT INTO {$this->table} 
            (kod, etiket, kategori, hesaplama_tipi, gunluk_muaf_limit, aylik_muaf_limit, muaf_limit_tipi, 
             sgk_matrahi_dahil, gelir_vergisi_dahil, damga_vergisi_dahil, 
             gecerlilik_baslangic, gecerlilik_bitis, varsayilan_tutar, aciklama, sira, aktif)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        return $sql->execute([
            $data['kod'],
            $data['etiket'],
            $data['kategori'],
            $data['hesaplama_tipi'] ?? 'net',
            $data['gunluk_muaf_limit'] ?? 0,
            $data['aylik_muaf_limit'] ?? 0,
            $data['muaf_limit_tipi'] ?? 'yok',
            $data['sgk_matrahi_dahil'] ?? 0,
            $data['gelir_vergisi_dahil'] ?? 1,
            $data['damga_vergisi_dahil'] ?? 0,
            $data['gecerlilik_baslangic'] ?? null,
            $data['gecerlilik_bitis'] ?? null,
            $data['varsayilan_tutar'] ?? 0,
            $data['aciklama'] ?? null,
            $data['sira'] ?? 0,
            $data['aktif'] ?? 1
        ]);
    }

    /**
     * Parametre günceller
     */
    public function updateParametre($id, $data)
    {
        $setClause = [];
        $params = [];

        $allowedFields = [
            'kod',
            'etiket',
            'kategori',
            'hesaplama_tipi',
            'gunluk_muaf_limit',
            'aylik_muaf_limit',
            'muaf_limit_tipi',
            'sgk_matrahi_dahil',
            'gelir_vergisi_dahil',
            'damga_vergisi_dahil',
            'gecerlilik_baslangic',
            'gecerlilik_bitis',
            'varsayilan_tutar',
            'aciklama',
            'sira',
            'aktif'
        ];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $setClause[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($setClause)) {
            return false;
        }

        $params[] = $id;

        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET " . implode(', ', $setClause) . "
            WHERE id = ?
        ");

        return $sql->execute($params);
    }

    /**
     * Gelir vergisi dilimlerini getirir
     */
    public function getVergiDilimleri($yil = null)
    {
        $yil = $yil ?? date('Y');

        $sql = $this->db->prepare("
            SELECT * FROM bordro_vergi_dilimleri
            WHERE yil = ?
            ORDER BY dilim_no ASC
        ");
        $sql->execute([$yil]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Genel bordro ayarını getirir
     */
    public function getGenelAyar($parametre_kodu, $tarih = null)
    {
        $tarih = $tarih ?? date('Y-m-d');

        $sql = $this->db->prepare("
            SELECT deger FROM bordro_genel_ayarlar
            WHERE parametre_kodu = ?
            AND aktif = 1
            AND gecerlilik_baslangic <= ?
            AND (gecerlilik_bitis IS NULL OR gecerlilik_bitis >= ?)
            ORDER BY gecerlilik_baslangic DESC
            LIMIT 1
        ");
        $sql->execute([$parametre_kodu, $tarih, $tarih]);
        $result = $sql->fetch(PDO::FETCH_OBJ);

        return $result ? floatval($result->deger) : null;
    }

    /**
     * Tüm genel ayarları getirir (Yönetim ekranı için - Aktif/Pasif hepsi)
     */
    public function getAllGenelAyarlarListesi()
    {
        $sql = $this->db->prepare("
            SELECT * FROM bordro_genel_ayarlar
            ORDER BY gecerlilik_baslangic DESC, parametre_kodu ASC
        ");
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Geçerli genel ayarları getirir (Hesaplama için)
     */
    public function getAllGenelAyarlar($tarih = null)
    {
        $tarih = $tarih ?? date('Y-m-d');

        $sql = $this->db->prepare("
            SELECT DISTINCT parametre_kodu, parametre_adi, deger, aciklama
            FROM bordro_genel_ayarlar
            WHERE aktif = 1
            AND gecerlilik_baslangic <= ?
            AND (gecerlilik_bitis IS NULL OR gecerlilik_bitis >= ?)
            ORDER BY parametre_kodu
        ");
        $sql->execute([$tarih, $tarih]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Genel ayar ekler/günceller
     */
    public function setGenelAyar($parametre_kodu, $parametre_adi, $deger, $gecerlilik_baslangic, $gecerlilik_bitis = null, $aciklama = null)
    {
        // Önce mevcut ayarı kapat
        $this->db->prepare("
            UPDATE bordro_genel_ayarlar 
            SET gecerlilik_bitis = DATE_SUB(?, INTERVAL 1 DAY)
            WHERE parametre_kodu = ? 
            AND gecerlilik_bitis IS NULL
        ")->execute([$gecerlilik_baslangic, $parametre_kodu]);

        // Yeni ayarı ekle
        $sql = $this->db->prepare("
            INSERT INTO bordro_genel_ayarlar 
            (parametre_kodu, parametre_adi, deger, gecerlilik_baslangic, gecerlilik_bitis, aciklama)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        return $sql->execute([
            $parametre_kodu,
            $parametre_adi,
            $deger,
            $gecerlilik_baslangic,
            $gecerlilik_bitis,
            $aciklama
        ]);
    }

    /**
     * Vergi dilimi ekler
     */
    public function addVergiDilimi($yil, $dilim_no, $alt_limit, $ust_limit, $vergi_orani, $aciklama = null)
    {
        $sql = $this->db->prepare("
            INSERT INTO bordro_vergi_dilimleri 
            (yil, dilim_no, alt_limit, ust_limit, vergi_orani, aciklama)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                alt_limit = VALUES(alt_limit),
                ust_limit = VALUES(ust_limit),
                vergi_orani = VALUES(vergi_orani),
                aciklama = VALUES(aciklama)
        ");

        return $sql->execute([$yil, $dilim_no, $alt_limit, $ust_limit, $vergi_orani, $aciklama]);
    }

    /**
     * Kümülatif gelir vergisi hesaplar
     * @param float $kumulatifMatrah Yılbaşından bu aya kadar toplam matrah
     * @param float $aylikMatrah Bu ayki matrah
     * @param int $yil Vergi yılı
     * @return float Hesaplanan vergi tutarı
     */
    public function hesaplaGelirVergisi($kumulatifMatrah, $aylikMatrah, $yil = null)
    {
        $yil = $yil ?? date('Y');
        $dilimler = $this->getVergiDilimleri($yil);

        if (empty($dilimler)) {
            // Dilim bulunamazsa sabit %15 uygula
            return $aylikMatrah * 0.15;
        }

        $oncekiToplam = $kumulatifMatrah - $aylikMatrah; // Önceki ayların toplamı
        $yeniToplam = $kumulatifMatrah;

        $toplamVergi = 0;

        foreach ($dilimler as $dilim) {
            $altLimit = floatval($dilim->alt_limit);
            $ustLimit = $dilim->ust_limit !== null ? floatval($dilim->ust_limit) : PHP_FLOAT_MAX;
            $oran = floatval($dilim->vergi_orani) / 100;

            // Bu dilimde vergilendirilecek tutar
            $dilimdeOnceki = max(0, min($oncekiToplam, $ustLimit) - $altLimit);
            $dilimdeYeni = max(0, min($yeniToplam, $ustLimit) - $altLimit);

            $buDilimdekiAylikMatrah = $dilimdeYeni - $dilimdeOnceki;

            if ($buDilimdekiAylikMatrah > 0) {
                $toplamVergi += $buDilimdekiAylikMatrah * $oran;
            }
        }

        return round($toplamVergi, 2);
    }
}
