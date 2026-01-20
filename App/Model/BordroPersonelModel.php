<?php

namespace App\Model;

use App\Model\Model;
use App\Model\BordroParametreModel;
use PDO;

class BordroPersonelModel extends Model
{
    protected $table = 'bordro_personel';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Belirli bir dönemdeki tüm personelleri getirir
     */
    /**
     * Belirli bir dönemdeki tüm personelleri getirir
     */
    public function getPersonellerByDonem($donem_id)
    {
        $sql = $this->db->prepare("
            SELECT bp.*, p.adi_soyadi, p.tc_kimlik_no, p.departman, p.gorev, 
                   p.ise_giris_tarihi, p.isten_cikis_tarihi, p.maas_tutari,
                   p.cep_telefonu, p.resim_yolu,
                   (SELECT COALESCE(SUM(tutar), 0) FROM personel_kesintileri WHERE personel_id = bp.personel_id AND donem_id = bp.donem_id AND silinme_tarihi IS NULL) as guncel_toplam_kesinti,
                   (SELECT COALESCE(SUM(tutar), 0) FROM personel_ek_odemeler WHERE personel_id = bp.personel_id AND donem_id = bp.donem_id AND silinme_tarihi IS NULL) as guncel_toplam_ek_odeme
            FROM {$this->table} bp
            INNER JOIN personel p ON bp.personel_id = p.id
            WHERE bp.donem_id = ? AND bp.silinme_tarihi IS NULL
            ORDER BY p.adi_soyadi ASC
        ");
        $sql->execute([$donem_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Döneme personel ekler
     * Personel işe başlama ve bitiş tarihlerine göre döneme dahil edilir:
     * - İşe giriş tarihi dönem başlangıcından önce veya dönem içinde olmalı
     * - İşten çıkış tarihi null olmalı veya dönem başlangıcından sonra olmalı
     */
    public function addPersonellerToDonem($donem_id, $baslangic_tarihi, $bitis_tarihi)
    {
        // Önce mevcut dönemdeki personelleri al (tekrar eklemesini önlemek için)
        $existingPersoneller = $this->getPersonellerByDonem($donem_id);
        $existingIds = array_column(
            array_map(function ($p) {
                return (array) $p;
            }, $existingPersoneller),
            'personel_id'
        );


        /**Firma id'yi Session'dan al */
        $firma_id = $_SESSION['firma_id'];
        // Uygun personelleri bul
        // İşten çıkış tarihi: NULL, '0000-00-00', boş string veya dönem başlangıcından büyük/eşit olanlar
        $sql = $this->db->prepare("
            SELECT id, adi_soyadi, ise_giris_tarihi, isten_cikis_tarihi 
            FROM personel 
            WHERE aktif_mi = 1
            AND firma_id = :firma_id
            AND (
                ise_giris_tarihi IS NULL 
                OR ise_giris_tarihi = ''
                OR ise_giris_tarihi = '0000-00-00'
                OR ise_giris_tarihi <= :bitis_tarihi
            )
            AND (
                isten_cikis_tarihi IS NULL 
                OR isten_cikis_tarihi = ''
                OR isten_cikis_tarihi = '0000-00-00'
                OR isten_cikis_tarihi >= :baslangic_tarihi
            )
        ");
        $sql->bindParam(':baslangic_tarihi', $baslangic_tarihi);
        $sql->bindParam(':bitis_tarihi', $bitis_tarihi);
        $sql->bindParam(':firma_id', $firma_id);

        $sql->execute();
        $uygunPersoneller = $sql->fetchAll(PDO::FETCH_OBJ);

        $eklenenSayisi = 0;

        foreach ($uygunPersoneller as $personel) {
            // Zaten eklenmişse atla
            if (in_array($personel->id, $existingIds)) {
                continue;
            }

            // Döneme ekle
            $insertSql = $this->db->prepare("
                INSERT INTO {$this->table} (donem_id, personel_id, olusturma_tarihi)
                VALUES (:donem_id, :personel_id, NOW())
            ");
            $insertSql->bindParam(':donem_id', $donem_id, PDO::PARAM_INT);
            $insertSql->bindParam(':personel_id', $personel->id, PDO::PARAM_INT);
            $insertSql->execute();
            $eklenenSayisi++;
        }

        return $eklenenSayisi;
    }

    /**
     * Personeli dönemden çıkarır (soft delete)
     */
    public function removeFromDonem($id)
    {
        return $this->softDelete($id);
    }

    /**
     * Belirli bir personelin bordro kaydını günceller
     */
    public function updateBordro($id, $data)
    {
        $setClause = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            $setClause[] = "$key = :$key";
            $params[":$key"] = $value;
        }

        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET " . implode(', ', $setClause) . "
            WHERE id = :id
        ");

        return $sql->execute($params);
    }

    /**
     * Bordro hesaplama verilerini kaydeder
     */
    /**
     * Bordro hesaplama verilerini kaydeder
     */
    public function saveBordroHesaplama($id, $hesaplamaData)
    {
        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET brut_maas = :brut_maas,
                sgk_isci = :sgk_isci,
                issizlik_isci = :issizlik_isci,
                gelir_vergisi = :gelir_vergisi,
                damga_vergisi = :damga_vergisi,
                net_maas = :net_maas,
                sgk_isveren = :sgk_isveren,
                issizlik_isveren = :issizlik_isveren,
                toplam_maliyet = :toplam_maliyet,
                kumulatif_matrah = :kumulatif_matrah,
                hesaplama_detay = :hesaplama_detay,
                hesaplama_tarihi = NOW()
            WHERE id = :id
        ");

        $sql->bindParam(':id', $id, PDO::PARAM_INT);
        $sql->bindParam(':brut_maas', $hesaplamaData['brut_maas']);
        $sql->bindParam(':sgk_isci', $hesaplamaData['sgk_isci']);
        $sql->bindParam(':issizlik_isci', $hesaplamaData['issizlik_isci']);
        $sql->bindParam(':gelir_vergisi', $hesaplamaData['gelir_vergisi']);
        $sql->bindParam(':damga_vergisi', $hesaplamaData['damga_vergisi']);
        $sql->bindParam(':net_maas', $hesaplamaData['net_maas']);
        $sql->bindParam(':sgk_isveren', $hesaplamaData['sgk_isveren']);
        $sql->bindParam(':issizlik_isveren', $hesaplamaData['issizlik_isveren']);
        $sql->bindParam(':toplam_maliyet', $hesaplamaData['toplam_maliyet']);
        $kumulatif = $hesaplamaData['kumulatif_matrah'] ?? 0;
        $sql->bindParam(':kumulatif_matrah', $kumulatif);
        $hesaplamaDetay = $hesaplamaData['hesaplama_detay'] ?? null;
        $sql->bindParam(':hesaplama_detay', $hesaplamaDetay);

        return $sql->execute();
    }

    /**
     * Personelin dönemdeki toplam kesintilerini getirir
     */
    public function getDonemKesintileri($personel_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT SUM(tutar) as toplam 
            FROM personel_kesintileri 
            WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL
        ");
        $sql->execute([$personel_id, $donem_id]);
        return $sql->fetch(PDO::FETCH_OBJ)->toplam ?? 0;
    }

    /**
     * Personelin dönemdeki toplam ek ödemelerini getirir
     */
    public function getDonemEkOdemeleri($personel_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT SUM(tutar) as toplam 
            FROM personel_ek_odemeler 
            WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL
        ");
        $sql->execute([$personel_id, $donem_id]);
        return $sql->fetch(PDO::FETCH_OBJ)->toplam ?? 0;
    }
    /**
     * Personelin bordrolarını getirir
     */
    public function getPersonelBordrolari($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT bp.*, bd.donem_adi, bd.baslangic_tarihi
            FROM {$this->table} bp
            LEFT JOIN bordro_donemi bd ON bp.donem_id = bd.id
            WHERE bp.personel_id = ? 
            AND bp.silinme_tarihi IS NULL
            ORDER BY bp.id DESC
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personelin toplam kazanç bilgilerini getirir (Dashboard için)
     * Tüm dönemlerdeki net maaşların toplamı
     */
    public function getPersonelFinansalOzet($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT 
                SUM(bp.net_maas) as toplam_hakedis,
                SUM(bp.net_maas) as alinan_odeme
            FROM {$this->table} bp
            INNER JOIN bordro_donemi bd ON bp.donem_id = bd.id
            WHERE bp.personel_id = ? AND bp.silinme_tarihi IS NULL
        ");
        $sql->execute([$personel_id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Personele kesinti ekler
     */
    /**
     * Personele kesinti ekler
     */
    public function addKesinti($personel_id, $donem_id, $aciklama, $tutar, $tur = 'diger')
    {
        $sql = $this->db->prepare("
            INSERT INTO personel_kesintileri (personel_id, donem_id, aciklama, tutar, tur, olusturma_tarihi)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $sql->execute([$personel_id, $donem_id, $aciklama, $tutar, $tur]);
    }

    /**
     * Personele ek ödeme ekler
     */
    public function addEkOdeme($personel_id, $donem_id, $aciklama, $tutar, $tur = 'diger')
    {
        $sql = $this->db->prepare("
            INSERT INTO personel_ek_odemeler (personel_id, donem_id, aciklama, tutar, tur, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $sql->execute([$personel_id, $donem_id, $aciklama, $tutar, $tur]);
    }

    /**
     * Personelin sürekli kesintilerini dönem için otomatik oluşturur
     * Bordro hesaplaması yapılmadan önce çağrılmalıdır
     * 
     * @param int $personel_id Personel ID
     * @param int $donem_id Dönem ID
     * @param string $donem YYYY-MM formatında dönem
     * @param float $brutMaas Personelin brüt maaşı (oran hesabı için)
     * @param float $netMaas Personelin net maaşı (oran hesabı için)
     * @return int Oluşturulan kayıt sayısı
     */
    public function olusturSurekliKesintiler($personel_id, $donem_id, $donem, $brutMaas = 0, $netMaas = 0)
    {
        $PersonelKesintileriModel = new \App\Model\PersonelKesintileriModel();

        // Aktif sürekli kesintileri getir
        $surekliKesintiler = $PersonelKesintileriModel->getAktifSurekliKesintiler($personel_id, $donem);

        $olusturulanSayisi = 0;

        foreach ($surekliKesintiler as $kesinti) {
            // Tutarı hesapla
            $tutar = 0;
            $hesaplamaTipi = $kesinti->hesaplama_tipi ?? 'sabit';

            if ($hesaplamaTipi === 'sabit') {
                $tutar = floatval($kesinti->tutar ?? 0);
            } elseif ($hesaplamaTipi === 'oran_net' && $netMaas > 0) {
                $oran = floatval($kesinti->oran ?? 0);
                $tutar = $netMaas * ($oran / 100);
            } elseif ($hesaplamaTipi === 'oran_brut' && $brutMaas > 0) {
                $oran = floatval($kesinti->oran ?? 0);
                $tutar = $brutMaas * ($oran / 100);
            } else {
                // Oran hesabı için maaş değeri yoksa, parametre varsayılan tutarını kullan
                $tutar = floatval($kesinti->tutar ?? 0);
            }

            // Dönem için kesinti oluştur
            $result = $PersonelKesintileriModel->olusturDonemKesintisi($kesinti, $donem_id, round($tutar, 2));

            if ($result) {
                $olusturulanSayisi++;
            }
        }

        return $olusturulanSayisi;
    }

    /**
     * Personelin sürekli ek ödemelerini dönem için otomatik oluşturur
     * Bordro hesaplaması yapılmadan önce çağrılmalıdır
     * 
     * @param int $personel_id Personel ID
     * @param int $donem_id Dönem ID
     * @param string $donem YYYY-MM formatında dönem
     * @param float $brutMaas Personelin brüt maaşı (oran hesabı için)
     * @param float $netMaas Personelin net maaşı (oran hesabı için)
     * @return int Oluşturulan kayıt sayısı
     */
    public function olusturSurekliEkOdemeler($personel_id, $donem_id, $donem, $brutMaas = 0, $netMaas = 0)
    {
        $PersonelEkOdemelerModel = new \App\Model\PersonelEkOdemelerModel();

        // Aktif sürekli ek ödemeleri getir
        $surekliOdemeler = $PersonelEkOdemelerModel->getAktifSurekliOdemeler($personel_id, $donem);

        $olusturulanSayisi = 0;

        foreach ($surekliOdemeler as $odeme) {
            // Tutarı hesapla
            $tutar = 0;
            $hesaplamaTipi = $odeme->hesaplama_tipi ?? 'sabit';

            if ($hesaplamaTipi === 'sabit') {
                $tutar = floatval($odeme->tutar ?? 0);
            } elseif ($hesaplamaTipi === 'oran_net' && $netMaas > 0) {
                $oran = floatval($odeme->oran ?? 0);
                $tutar = $netMaas * ($oran / 100);
            } elseif ($hesaplamaTipi === 'oran_brut' && $brutMaas > 0) {
                $oran = floatval($odeme->oran ?? 0);
                $tutar = $brutMaas * ($oran / 100);
            } else {
                // Oran hesabı için maaş değeri yoksa, varsayılan tutarı kullan
                $tutar = floatval($odeme->tutar ?? 0);
            }

            // Dönem için ek ödeme oluştur
            $result = $PersonelEkOdemelerModel->olusturDonemOdemesi($odeme, $donem_id, round($tutar, 2));

            if ($result) {
                $olusturulanSayisi++;
            }
        }

        return $olusturulanSayisi;
    }

    /**
     * Dönemdeki tüm personeller için sürekli kesinti/ek ödemeleri otomatik oluşturur
     * @param int $donem_id Dönem ID
     * @return array ['kesinti' => int, 'ek_odeme' => int] Oluşturulan kayıt sayıları
     */
    public function olusturDonemSurekliKayitlar($donem_id)
    {
        // Dönem bilgisini çek
        $sql = $this->db->prepare("SELECT baslangic_tarihi FROM bordro_donemi WHERE id = ?");
        $sql->execute([$donem_id]);
        $donemBilgi = $sql->fetch(PDO::FETCH_OBJ);

        if (!$donemBilgi) {
            return ['kesinti' => 0, 'ek_odeme' => 0];
        }

        $donem = date('Y-m', strtotime($donemBilgi->baslangic_tarihi));

        // Dönemdeki personelleri getir
        $personeller = $this->getPersonellerByDonem($donem_id);

        $toplamKesinti = 0;
        $toplamEkOdeme = 0;

        foreach ($personeller as $personel) {
            // Brüt ve net maaşı personel kaydından al
            $brutMaas = floatval($personel->maas_tutari ?? 0);
            $netMaas = floatval($personel->net_maas ?? 0);

            // Net maaş yoksa brütten tahmin et (yaklaşık %70)
            if ($netMaas <= 0 && $brutMaas > 0) {
                $netMaas = $brutMaas * 0.70;
            }

            $toplamKesinti += $this->olusturSurekliKesintiler(
                $personel->personel_id,
                $donem_id,
                $donem,
                $brutMaas,
                $netMaas
            );

            $toplamEkOdeme += $this->olusturSurekliEkOdemeler(
                $personel->personel_id,
                $donem_id,
                $donem,
                $brutMaas,
                $netMaas
            );
        }

        return ['kesinti' => $toplamKesinti, 'ek_odeme' => $toplamEkOdeme];
    }

    /**
     * Personelin puantaj (yapılan işler) verilerine göre ek ödemelerini oluşturur
     */
    public function olusturPuantajOdemeleri($personel_id, $donem_id, $baslangic_tarihi, $bitis_tarihi)
    {
        // 1. Personelin ekip kodunu bul
        $PersonelModel = new \App\Model\PersonelModel();
        $personel = $PersonelModel->find($personel_id);

        if (!$personel || empty($personel->ekip_no)) {
            return;
        }

        $ekipKodu = $personel->ekip_no;

        // 2. Önceki puantaj kaynaklı ek ödemeleri temizle (duplicate önlemek için)
        // Açıklamada "[Puantaj]" etiketi olanları siliyoruz
        $deleteSql = $this->db->prepare("
            DELETE FROM personel_ek_odemeler 
            WHERE personel_id = ? AND donem_id = ? AND aciklama LIKE '[Puantaj]%'
        ");
        $deleteSql->execute([$personel_id, $donem_id]);

        // 3. Yapılan işleri getir ve grupla
        // is_emri_tipi'ne göre gruplayıp sayılarını alıyoruz
        // Not: yapilan_isler tablosunda firma_id yok, firma adı text olarak 'firma' kolonunda tutuluyor
        $sql = $this->db->prepare("
            SELECT is_emri_tipi, COUNT(*) as adet
            FROM yapilan_isler 
            WHERE ekip_kodu = ? 
            AND tarih BETWEEN ? AND ?
            AND is_emri_tipi IS NOT NULL 
            AND is_emri_tipi != ''
            GROUP BY is_emri_tipi
        ");
        $sql->execute([$ekipKodu, $baslangic_tarihi, $bitis_tarihi]);
        $yapilanIsler = $sql->fetchAll(PDO::FETCH_OBJ);

        if (empty($yapilanIsler)) {
            return;
        }

        // 4. Tanımlamalar tablosundan ücretleri al
        // Tüm iş türlerini çekip bir map oluşturuyoruz
        $TanimlamalarModel = new \App\Model\TanimlamalarModel();
        $isTurleri = $TanimlamalarModel->getIsTurleri(); // grup = 'is_turu'

        $ucretMap = [];
        foreach ($isTurleri as $tur) {
            $ucretMap[$tur->tur_adi] = floatval($tur->is_turu_ucret ?? 0);
        }

        // 5. Ek ödemeleri oluştur
        $PersonelEkOdemelerModel = new \App\Model\PersonelEkOdemelerModel();

        foreach ($yapilanIsler as $is) {
            $isTipi = $is->is_emri_tipi;
            $adet = $is->adet;

            // Ücreti bul
            $birimUcret = $ucretMap[$isTipi] ?? 0;

            if ($birimUcret > 0) {
                $toplamTutar = $adet * $birimUcret;
                $aciklama = "[Puantaj] $isTipi ($adet Adet)";

                // Ek ödeme ekle
                $PersonelEkOdemelerModel->saveWithAttr([
                    'personel_id' => $personel_id,
                    'donem_id' => $donem_id,
                    'tur' => 'prim', // Prim olarak sınıflandırıyoruz
                    'aciklama' => $aciklama,
                    'tutar' => $toplamTutar,
                    'tekrar_tipi' => 'tek_sefer',
                    'aktif' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    /**
     * Tek bir personelin maaşını hesaplar ve günceller
     * Parametrelere dayalı gelişmiş hesaplama
     */
    public function hesaplaMaas($bordro_personel_id)
    {
        // BordroParametreModel'i kullan
        $parametreModel = new BordroParametreModel();

        // Bordro kaydını ve personel detaylarını çek
        $sql = $this->db->prepare("
            SELECT bp.*, p.maas_tutari, bd.baslangic_tarihi, bd.bitis_tarihi
            FROM {$this->table} bp
            INNER JOIN personel p ON bp.personel_id = p.id
            INNER JOIN bordro_donemi bd ON bp.donem_id = bd.id
            WHERE bp.id = ?
        ");
        $sql->execute([$bordro_personel_id]);
        $kayit = $sql->fetch(PDO::FETCH_OBJ);

        if (!$kayit)
            return false;

        // Dönem tarihi - parametreleri bu tarihe göre çek
        $donemTarihi = $kayit->baslangic_tarihi ?? date('Y-m-d');
        $donemAy = date('n', strtotime($donemTarihi));
        $donemYil = date('Y', strtotime($donemTarihi));
        $donem = date('Y-m', strtotime($donemTarihi));

        // Brüt maaş (sürekli kayıtların oran hesabı için önce al)
        $brutMaas = floatval($kayit->maas_tutari ?? 0);
        if ($brutMaas <= 0) {
            $brutMaas = $parametreModel->getGenelAyar('asgari_ucret_brut', $donemTarihi) ?? 33030.00;
        }

        // Net maaş tahmini (sürekli kayıtların oran hesabı için - brütün %70'i)
        $netMaasTahmini = $brutMaas * 0.70;

        // ========== SÜREKLİ KESİNTİ VE EK ÖDEMELERİ DÖNEME AKTAR ==========
        // Bu işlem, aktif sürekli kayıtları bordro dönemine tek seferlik olarak ekler
        $this->olusturSurekliKesintiler($kayit->personel_id, $kayit->donem_id, $donem, $brutMaas, $netMaasTahmini);
        $this->olusturSurekliEkOdemeler($kayit->personel_id, $kayit->donem_id, $donem, $brutMaas, $netMaasTahmini);

        // Puantaj (Yapılan İşler) Hesaplaması
        $this->olusturPuantajOdemeleri($kayit->personel_id, $kayit->donem_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi);

        // Çalışma günü sayısı (aylık varsayılan 26 gün)
        $calismaGunuSayisi = $parametreModel->getGenelAyar('calisma_gunu_sayisi', $donemTarihi) ?? 26;

        // Genel ayarları çek
        $sgkIsciOrani = ($parametreModel->getGenelAyar('sgk_isci_orani', $donemTarihi) ?? 14) / 100;
        $issizlikIsciOrani = ($parametreModel->getGenelAyar('issizlik_isci_orani', $donemTarihi) ?? 1) / 100;
        $sgkIsverenOrani = ($parametreModel->getGenelAyar('sgk_isveren_orani', $donemTarihi) ?? 20.5) / 100;
        $issizlikIsverenOrani = ($parametreModel->getGenelAyar('issizlik_isveren_orani', $donemTarihi) ?? 2) / 100;
        $damgaVergisiOrani = ($parametreModel->getGenelAyar('damga_vergisi_orani', $donemTarihi) ?? 0.759) / 100;

        // Ek Ödemeler ve Kesintileri detaylı çek (sürekli kayıtlar da artık dahil)
        $ekOdemeler = $this->getDonemEkOdemeleriListe($kayit->personel_id, $kayit->donem_id);
        $kesintiler = $this->getDonemKesintileriListe($kayit->personel_id, $kayit->donem_id);

        // Hesaplama için değişkenler
        $brutEkOdemeler = 0;       // Brüt maaşa eklenecek (SGK + Vergi hesaplanacak)
        $netEkOdemeler = 0;        // Direct net'e eklenecek
        $vergiliMatrahEkleri = 0;  // Sadece gelir vergisi matrahına eklenecek
        $sgkMatrahEkleri = 0;      // SGK matrahına eklenecek
        $toplamKesinti = 0;        // Net'ten düşülecek kesintiler

        // JSON detay için diziler
        $ekOdemeDetaylari = [];
        $kesintiDetaylari = [];

        // Her ek ödemeyi parametresine göre işle
        foreach ($ekOdemeler as $odeme) {
            $tutar = floatval($odeme->tutar);
            $parametre = $parametreModel->getByKod($odeme->tur, $donemTarihi);

            // Detay kaydı
            $detay = [
                'kod' => $odeme->tur,
                'tutar' => $tutar,
                'aciklama' => $odeme->aciklama ?? null
            ];

            if (!$parametre) {
                // Parametre bulunamadıysa varsayılan olarak net ekle
                $netEkOdemeler += $tutar;
                $detay['etiket'] = $odeme->tur;
                $detay['hesaplama_tipi'] = 'net';
                $detay['net_etki'] = $tutar;
                $ekOdemeDetaylari[] = $detay;
                continue;
            }

            $detay['etiket'] = $parametre->etiket;
            $detay['hesaplama_tipi'] = $parametre->hesaplama_tipi;
            $detay['sgk_dahil'] = (bool) $parametre->sgk_matrahi_dahil;
            $detay['gv_dahil'] = (bool) $parametre->gelir_vergisi_dahil;

            switch ($parametre->hesaplama_tipi) {
                case 'brut':
                    // Brüt: Tüm vergi/SGK hesaplamalarına dahil
                    $brutEkOdemeler += $tutar;
                    if ($parametre->sgk_matrahi_dahil) {
                        $sgkMatrahEkleri += $tutar;
                    }
                    if ($parametre->gelir_vergisi_dahil) {
                        $vergiliMatrahEkleri += $tutar;
                    }
                    $detay['net_etki'] = $tutar; // Brütten kesinti yapılacak
                    break;

                case 'kismi_muaf':
                    // Kısmi Muaf: Belirli limite kadar vergisiz
                    $muafLimit = 0;
                    if ($parametre->muaf_limit_tipi === 'gunluk') {
                        $muafLimit = floatval($parametre->gunluk_muaf_limit) * $calismaGunuSayisi;
                    } elseif ($parametre->muaf_limit_tipi === 'aylik') {
                        $muafLimit = floatval($parametre->aylik_muaf_limit);
                    }

                    $muafKisim = min($tutar, $muafLimit);
                    $vergiliKisim = max(0, $tutar - $muafLimit);

                    // Muaf kısım net'e direkt eklenir
                    $netEkOdemeler += $muafKisim;

                    // Vergili kısım hesaplamalara dahil
                    if ($vergiliKisim > 0) {
                        if ($parametre->sgk_matrahi_dahil) {
                            $sgkMatrahEkleri += $vergiliKisim;
                        }
                        if ($parametre->gelir_vergisi_dahil) {
                            $vergiliMatrahEkleri += $vergiliKisim;
                        }
                    }

                    $detay['muaf_limit_tipi'] = $parametre->muaf_limit_tipi;
                    $detay['gunluk_limit'] = $parametre->gunluk_muaf_limit;
                    $detay['aylik_limit'] = $muafLimit;
                    $detay['muaf_kisim'] = round($muafKisim, 2);
                    $detay['vergili_kisim'] = round($vergiliKisim, 2);
                    $detay['net_etki'] = round($muafKisim, 2);
                    break;

                case 'net':
                default:
                    // Net: Direkt net maaşa eklenir
                    $netEkOdemeler += $tutar;
                    $detay['net_etki'] = $tutar;
                    break;
            }

            $ekOdemeDetaylari[] = $detay;
        }

        // Her kesintiyi işle
        foreach ($kesintiler as $kesinti) {
            $tutar = floatval($kesinti->tutar);
            $toplamKesinti += $tutar;

            $parametre = $parametreModel->getByKod($kesinti->tur, $donemTarihi);

            $kesintiDetaylari[] = [
                'kod' => $kesinti->tur,
                'etiket' => $parametre ? $parametre->etiket : $kesinti->tur,
                'tutar' => $tutar,
                'aciklama' => $kesinti->aciklama ?? null
            ];
        }

        // ========== HESAPLAMALAR ==========

        // SGK Matrahı = Brüt Maaş + SGK'ya dahil ek ödemeler
        $sgkMatrahi = $brutMaas + $sgkMatrahEkleri;

        // SGK İşçi Payı
        $sgkIsci = $sgkMatrahi * $sgkIsciOrani;
        $issizlikIsci = $sgkMatrahi * $issizlikIsciOrani;

        // Gelir Vergisi Matrahı = SGK Matrahı - SGK Kesintileri + Vergiye tabi ek ödemeler
        $gelirVergisiMatrahi = ($brutMaas - $sgkIsci - $issizlikIsci) + $vergiliMatrahEkleri;

        // Kümülatif Gelir Vergisi Hesaplaması
        // Önceki ayların matrahını getir
        $kumulatifMatrah = $this->getKumulatifMatrah($kayit->personel_id, $donemYil, $donemAy);
        $yeniKumulatifMatrah = $kumulatifMatrah + $gelirVergisiMatrahi;

        $gelirVergisi = $parametreModel->hesaplaGelirVergisi($yeniKumulatifMatrah, $gelirVergisiMatrahi, $donemYil);

        // Damga Vergisi = Brüt toplam üzerinden
        $damgaVergisiMatrahi = $brutMaas + $brutEkOdemeler;
        $damgaVergisi = $damgaVergisiMatrahi * $damgaVergisiOrani;

        // Net Maaş Hesabı
        // Net = (Brüt - Yasal Kesintiler) + Net Ek Ödemeler - Diğer Kesintiler
        $netMaas = ($brutMaas - $sgkIsci - $issizlikIsci - $gelirVergisi - $damgaVergisi)
            + $netEkOdemeler
            - $toplamKesinti;

        // Toplam ek ödemeler (gösterim için)
        $toplamEkOdeme = $brutEkOdemeler + $netEkOdemeler;

        // İşveren Maliyetleri
        $sgkIsveren = $sgkMatrahi * $sgkIsverenOrani;
        $issizlikIsveren = $sgkMatrahi * $issizlikIsverenOrani;
        $toplamMaliyet = $brutMaas + $sgkIsveren + $issizlikIsveren + $brutEkOdemeler;

        // Hesaplama Snapshot (JSON)
        $hesaplamaDetay = [
            'hesaplama_tarihi' => date('Y-m-d H:i:s'),
            'donem' => [
                'id' => $kayit->donem_id,
                'baslangic' => $donemTarihi,
                'ay' => $donemAy,
                'yil' => $donemYil
            ],
            'parametreler' => [
                'sgk_isci_orani' => $sgkIsciOrani * 100,
                'issizlik_isci_orani' => $issizlikIsciOrani * 100,
                'sgk_isveren_orani' => $sgkIsverenOrani * 100,
                'issizlik_isveren_orani' => $issizlikIsverenOrani * 100,
                'damga_vergisi_orani' => $damgaVergisiOrani * 100,
                'calisma_gunu_sayisi' => $calismaGunuSayisi
            ],
            'matrahlar' => [
                'sgk_matrahi' => round($sgkMatrahi, 2),
                'gelir_vergisi_matrahi' => round($gelirVergisiMatrahi, 2),
                'damga_vergisi_matrahi' => round($damgaVergisiMatrahi, 2),
                'onceki_kumulatif' => round($kumulatifMatrah, 2),
                'yeni_kumulatif' => round($yeniKumulatifMatrah, 2)
            ],
            'ek_odemeler' => $ekOdemeDetaylari,
            'kesintiler' => $kesintiDetaylari,
            'ozet' => [
                'brut_ek_odemeler' => round($brutEkOdemeler, 2),
                'net_ek_odemeler' => round($netEkOdemeler, 2),
                'sgk_matrah_ekleri' => round($sgkMatrahEkleri, 2),
                'vergili_matrah_ekleri' => round($vergiliMatrahEkleri, 2)
            ]
        ];

        // Kaydet
        return $this->saveBordroHesaplama($bordro_personel_id, [
            'brut_maas' => round($brutMaas, 2),
            'sgk_isci' => round($sgkIsci, 2),
            'issizlik_isci' => round($issizlikIsci, 2),
            'gelir_vergisi' => round($gelirVergisi, 2),
            'damga_vergisi' => round($damgaVergisi, 2),
            'net_maas' => round($netMaas, 2),
            'sgk_isveren' => round($sgkIsveren, 2),
            'issizlik_isveren' => round($issizlikIsveren, 2),
            'toplam_maliyet' => round($toplamMaliyet, 2),
            'toplam_kesinti' => round($toplamKesinti, 2),
            'toplam_ek_odeme' => round($toplamEkOdeme, 2),
            'kumulatif_matrah' => round($yeniKumulatifMatrah, 2),
            'hesaplama_detay' => json_encode($hesaplamaDetay, JSON_UNESCAPED_UNICODE)
        ]);
    }

    /**
     * Personelin yılbaşından bu aya kadar kümülatif gelir vergisi matrahını getirir
     */
    private function getKumulatifMatrah($personel_id, $yil, $ay)
    {
        // Bu yılın Ocak'tan önceki aya kadar toplam gelir vergisi matrahı
        $sql = $this->db->prepare("
            SELECT COALESCE(SUM(bp.brut_maas - bp.sgk_isci - bp.issizlik_isci), 0) as toplam_matrah
            FROM {$this->table} bp
            INNER JOIN bordro_donemi bd ON bp.donem_id = bd.id
            WHERE bp.personel_id = ?
            AND YEAR(bd.baslangic_tarihi) = ?
            AND MONTH(bd.baslangic_tarihi) < ?
            AND bp.hesaplama_tarihi IS NOT NULL
        ");
        $sql->execute([$personel_id, $yil, $ay]);
        $result = $sql->fetch(PDO::FETCH_OBJ);

        return floatval($result->toplam_matrah ?? 0);
    }

    /**
     * Personel ID ve Dönem ID'ye göre maaş hesaplar
     */
    public function hesaplaMaasByPersonelDonem($personel_id, $donem_id)
    {
        $sql = $this->db->prepare("SELECT id FROM {$this->table} WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL");
        $sql->execute([$personel_id, $donem_id]);
        $bp = $sql->fetch(PDO::FETCH_OBJ);

        if ($bp) {
            return $this->hesaplaMaas($bp->id);
        }
        return false;
    }

    /**
     * Personelin dönemdeki kesintilerini türe göre detaylı getirir
     */
    public function getDonemKesintileriDetay($personel_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT tur, SUM(tutar) as toplam_tutar, COUNT(*) as adet
            FROM personel_kesintileri 
            WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL
            GROUP BY tur
            ORDER BY toplam_tutar DESC
        ");
        $sql->execute([$personel_id, $donem_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personelin dönemdeki ek ödemelerini türe göre detaylı getirir
     */
    public function getDonemEkOdemeleriDetay($personel_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT tur, SUM(tutar) as toplam_tutar, COUNT(*) as adet
            FROM personel_ek_odemeler 
            WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL
            GROUP BY tur
            ORDER BY toplam_tutar DESC
        ");
        $sql->execute([$personel_id, $donem_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personelin dönemdeki tüm kesinti kayıtlarını getirir (detaylı liste için)
     */
    public function getDonemKesintileriListe($personel_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT pk.*, pi.dosya_no, pi.icra_dairesi
            FROM personel_kesintileri pk
            LEFT JOIN personel_icralari pi ON pk.icra_id = pi.id
            WHERE pk.personel_id = ? AND pk.donem_id = ? AND pk.silinme_tarihi IS NULL
            ORDER BY pk.olusturma_tarihi DESC
        ");
        $sql->execute([$personel_id, $donem_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personelin dönemdeki tüm ek ödeme kayıtlarını getirir (detaylı liste için)
     */
    public function getDonemEkOdemeleriListe($personel_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM personel_ek_odemeler 
            WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL
            ORDER BY created_at DESC
        ");
        $sql->execute([$personel_id, $donem_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Banka export için dönemdeki personellerin detaylı bilgilerini getirir
     * Personel tablosundan tüm gerekli alanları çeker
     */
    public function getPersonellerByDonemDetayli($donem_id)
    {
        $sql = $this->db->prepare("
            SELECT bp.*, 
                   bp.banka_odemesi,
                   bp.sodexo_odemesi,
                   bp.net_maas,
                   p.adi_soyadi, 
                   p.tc_kimlik_no, 
                   p.anne_adi,
                   p.baba_adi,
                   p.dogum_tarihi,
                   p.dogum_yeri_il,
                   p.dogum_yeri_ilce,
                   p.adres,
                   p.cinsiyet,
                   p.cep_telefonu,
                   p.email_adresi,
                   p.iban_numarasi
            FROM {$this->table} bp
            INNER JOIN personel p ON bp.personel_id = p.id
            WHERE bp.donem_id = ? AND bp.silinme_tarihi IS NULL
            ORDER BY p.adi_soyadi ASC
        ");
        $sql->execute([$donem_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
