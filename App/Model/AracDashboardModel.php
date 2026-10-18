<?php

namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use PDO;

class AracDashboardModel extends Model
{
    protected $table = 'araclar';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Firma ID'sini oturumdan veya parametreden çözer
     */
    private function resolveFirmaId($firmaId = null): int
    {
        return (int) ($firmaId ?? $_SESSION['firma_id'] ?? 0);
    }

    /**
     * Filtreye uygun departman listesi
     */
    public function getDepartmanlar($firmaId = null): array
    {
        $fId = $this->resolveFirmaId($firmaId);
        $stmt = $this->db->prepare("
            SELECT DISTINCT departmani 
            FROM {$this->table}
            WHERE firma_id = :firma_id 
              AND silinme_tarihi IS NULL 
              AND departmani IS NOT NULL 
              AND TRIM(departmani) != ''
            ORDER BY departmani ASC
        ");
        $stmt->execute(['firma_id' => $fId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Ana KPI istatistikleri (4 Ana Kart için)
     */
    public function getKpiStats($baslangic = null, $bitis = null, $departman = null, $firmaId = null): array
    {
        $fId = $this->resolveFirmaId($firmaId);

        // 1. Filo Durumu (Anlık)
        $filoSql = "
            SELECT 
                COUNT(*) as toplam_arac,
                SUM(CASE WHEN a.aktif_mi = 1 THEN 1 ELSE 0 END) as aktif_arac,
                SUM(CASE WHEN a.aktif_mi = 0 THEN 1 ELSE 0 END) as pasif_arac,
                SUM(CASE WHEN a.ikame_mi = 1 THEN 1 ELSE 0 END) as ikame_arac,
                SUM(CASE WHEN az.id IS NOT NULL THEN 1 ELSE 0 END) as zimmetli_arac,
                SUM(CASE WHEN az.id IS NULL AND a.aktif_mi = 1 AND a.ikame_mi = 0 AND s.id IS NULL THEN 1 ELSE 0 END) as bosta_arac,
                SUM(CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END) as servisteki_arac
            FROM {$this->table} a
            LEFT JOIN (
                SELECT az1.id, az1.arac_id 
                FROM arac_zimmetleri az1
                INNER JOIN (
                    SELECT MAX(id) as max_id 
                    FROM arac_zimmetleri 
                    WHERE durum = 'aktif' AND silinme_tarihi IS NULL 
                    GROUP BY arac_id
                ) az2 ON az1.id = az2.max_id
                WHERE az1.silinme_tarihi IS NULL
            ) az ON a.id = az.arac_id
            LEFT JOIN (
                SELECT DISTINCT arac_id, id 
                FROM arac_servis_kayitlari 
                WHERE iade_tarihi IS NULL AND silinme_tarihi IS NULL
            ) s ON a.id = s.arac_id
            WHERE a.firma_id = :firma_id
              AND a.silinme_tarihi IS NULL
        ";

        $filoParams = ['firma_id' => $fId];
        if (!empty($departman)) {
            $filoSql .= " AND a.departmani = :departman";
            $filoParams['departman'] = $departman;
        }

        $stmt = $this->db->prepare($filoSql);
        $stmt->execute($filoParams);
        $filo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // 2. Kilometre / Yol Göstergeleri (Dönem İçi)
        $kmSql = "
            SELECT 
                COALESCE(SUM(CASE WHEN k.yapilan_km > 0 THEN k.yapilan_km ELSE 0 END), 0) as toplam_km,
                COUNT(DISTINCT k.arac_id) as aktif_kullanilan_arac,
                COUNT(k.id) as km_kayit_adedi
            FROM arac_km_kayitlari k
            INNER JOIN {$this->table} a ON k.arac_id = a.id
            WHERE k.firma_id = :firma_id
              AND k.silinme_tarihi IS NULL
              AND a.silinme_tarihi IS NULL
        ";
        $kmParams = ['firma_id' => $fId];

        if (!empty($baslangic)) {
            $kmSql .= " AND k.tarih >= :baslangic";
            $kmParams['baslangic'] = $baslangic;
        }
        if (!empty($bitis)) {
            $kmSql .= " AND k.tarih <= :bitis";
            $kmParams['bitis'] = $bitis;
        }
        if (!empty($departman)) {
            $kmSql .= " AND a.departmani = :departman";
            $kmParams['departman'] = $departman;
        }

        $stmt = $this->db->prepare($kmSql);
        $stmt->execute($kmParams);
        $kmData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // En çok KM yapan araç (Dönem İçi)
        $topKmSql = "
            SELECT 
                a.id as arac_id,
                a.plaka,
                a.marka,
                a.model,
                COALESCE(SUM(k.yapilan_km), 0) as yapilan_km
            FROM arac_km_kayitlari k
            INNER JOIN {$this->table} a ON k.arac_id = a.id
            WHERE k.firma_id = :firma_id
              AND k.silinme_tarihi IS NULL
              AND a.silinme_tarihi IS NULL
        ";
        $topKmParams = ['firma_id' => $fId];
        if (!empty($baslangic)) {
            $topKmSql .= " AND k.tarih >= :baslangic";
            $topKmParams['baslangic'] = $baslangic;
        }
        if (!empty($bitis)) {
            $topKmSql .= " AND k.tarih <= :bitis";
            $topKmParams['bitis'] = $bitis;
        }
        if (!empty($departman)) {
            $topKmSql .= " AND a.departmani = :departman";
            $topKmParams['departman'] = $departman;
        }
        $topKmSql .= " GROUP BY a.id, a.plaka, a.marka, a.model ORDER BY yapilan_km DESC LIMIT 1";
        $stmt = $this->db->prepare($topKmSql);
        $stmt->execute($topKmParams);
        $enCokKmYapan = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // 3. Yakıt Göstergeleri (Dönem İçi)
        $yakitSql = "
            SELECT 
                COALESCE(SUM(y.yakit_miktari), 0) as toplam_litre,
                COALESCE(SUM(y.toplam_tutar), 0) as toplam_yakit_tutar,
                COUNT(y.id) as yakit_islem_adedi,
                COUNT(DISTINCT y.arac_id) as yakit_alan_arac_sayisi
            FROM arac_yakit_kayitlari y
            INNER JOIN {$this->table} a ON y.arac_id = a.id
            WHERE y.firma_id = :firma_id
              AND y.silinme_tarihi IS NULL
              AND a.silinme_tarihi IS NULL
        ";
        $yakitParams = ['firma_id' => $fId];

        if (!empty($baslangic)) {
            $yakitSql .= " AND y.tarih >= :baslangic";
            $yakitParams['baslangic'] = $baslangic;
        }
        if (!empty($bitis)) {
            $yakitSql .= " AND y.tarih <= :bitis";
            $yakitParams['bitis'] = $bitis;
        }
        if (!empty($departman)) {
            $yakitSql .= " AND a.departmani = :departman";
            $yakitParams['departman'] = $departman;
        }

        $stmt = $this->db->prepare($yakitSql);
        $stmt->execute($yakitParams);
        $yakitData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // En çok yakıt alan araç
        $topYakitSql = "
            SELECT 
                a.id as arac_id,
                a.plaka,
                a.marka,
                a.model,
                COALESCE(SUM(y.toplam_tutar), 0) as toplam_tutar,
                COALESCE(SUM(y.yakit_miktari), 0) as toplam_litre
            FROM arac_yakit_kayitlari y
            INNER JOIN {$this->table} a ON y.arac_id = a.id
            WHERE y.firma_id = :firma_id
              AND y.silinme_tarihi IS NULL
              AND a.silinme_tarihi IS NULL
        ";
        $topYakitParams = ['firma_id' => $fId];
        if (!empty($baslangic)) {
            $topYakitSql .= " AND y.tarih >= :baslangic";
            $topYakitParams['baslangic'] = $baslangic;
        }
        if (!empty($bitis)) {
            $topYakitSql .= " AND y.tarih <= :bitis";
            $topYakitParams['bitis'] = $bitis;
        }
        if (!empty($departman)) {
            $topYakitSql .= " AND a.departmani = :departman";
            $topYakitParams['departman'] = $departman;
        }
        $topYakitSql .= " GROUP BY a.id, a.plaka, a.marka, a.model ORDER BY toplam_tutar DESC LIMIT 1";
        $stmt = $this->db->prepare($topYakitSql);
        $stmt->execute($topYakitParams);
        $enCokYakitAlan = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // 4. Servis & Bakım Göstergeleri (Dönem İçi)
        $servisSql = "
            SELECT 
                COALESCE(SUM(s.tutar), 0) as toplam_servis_maliyet,
                COUNT(s.id) as servis_islem_adedi,
                COUNT(DISTINCT s.arac_id) as servise_giden_arac_sayisi
            FROM arac_servis_kayitlari s
            INNER JOIN {$this->table} a ON s.arac_id = a.id
            WHERE s.firma_id = :firma_id
              AND s.silinme_tarihi IS NULL
              AND a.silinme_tarihi IS NULL
        ";
        $servisParams = ['firma_id' => $fId];

        if (!empty($baslangic)) {
            $servisSql .= " AND s.servis_tarihi >= :baslangic";
            $servisParams['baslangic'] = $baslangic;
        }
        if (!empty($bitis)) {
            $servisSql .= " AND s.servis_tarihi <= :bitis";
            $servisParams['bitis'] = $bitis;
        }
        if (!empty($departman)) {
            $servisSql .= " AND a.departmani = :departman";
            $servisParams['departman'] = $departman;
        }

        $stmt = $this->db->prepare($servisSql);
        $stmt->execute($servisParams);
        $servisData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // 5. Evrak Uyarıları (Muayene, Sigorta, Kasko - Anlık Durum)
        $evrakSql = "
            SELECT 
                SUM(CASE WHEN muayene_bitis_tarihi IS NOT NULL AND muayene_bitis_tarihi < CURDATE() THEN 1 ELSE 0 END) as muayene_gecen,
                SUM(CASE WHEN muayene_bitis_tarihi IS NOT NULL AND muayene_bitis_tarihi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as muayene_yaklasan,
                SUM(CASE WHEN sigorta_bitis_tarihi IS NOT NULL AND sigorta_bitis_tarihi < CURDATE() THEN 1 ELSE 0 END) as sigorta_gecen,
                SUM(CASE WHEN sigorta_bitis_tarihi IS NOT NULL AND sigorta_bitis_tarihi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as sigorta_yaklasan,
                SUM(CASE WHEN kasko_bitis_tarihi IS NOT NULL AND kasko_bitis_tarihi < CURDATE() THEN 1 ELSE 0 END) as kasko_gecen,
                SUM(CASE WHEN kasko_bitis_tarihi IS NOT NULL AND kasko_bitis_tarihi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as kasko_yaklasan
            FROM {$this->table}
            WHERE firma_id = :firma_id
              AND silinme_tarihi IS NULL
              AND aktif_mi = 1
        ";
        $evrakParams = ['firma_id' => $fId];
        if (!empty($departman)) {
            $evrakSql .= " AND departmani = :departman";
            $evrakParams['departman'] = $departman;
        }
        $stmt = $this->db->prepare($evrakSql);
        $stmt->execute($evrakParams);
        $evrakData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Ortalama ve Oran Hesaplamaları
        $toplamKm = (float) ($kmData['toplam_km'] ?? 0);
        $toplamYakitTutar = (float) ($yakitData['toplam_yakit_tutar'] ?? 0);
        $toplamYakitLitre = (float) ($yakitData['toplam_litre'] ?? 0);
        $aktifAracSayisi = (int) ($filo['aktif_arac'] ?? 0);
        $kullanilanAracSayisi = (int) ($kmData['aktif_kullanilan_arac'] ?? 0);

        // Gün sayısı hesabı
        $gunSayisi = 30;
        if (!empty($baslangic) && !empty($bitis)) {
            $diff = (strtotime($bitis) - strtotime($baslangic)) / 86400;
            $gunSayisi = max(1, round($diff) + 1);
        }

        $gunlukOrtalamaKm = $gunSayisi > 0 ? round($toplamKm / $gunSayisi, 1) : 0;
        $aracBasiOrtalamaKm = $kullanilanAracSayisi > 0 ? round($toplamKm / $kullanilanAracSayisi, 0) : 0;
        $ortalamaLitreFiyat = $toplamYakitLitre > 0 ? round($toplamYakitTutar / $toplamYakitLitre, 2) : 0;
        $ortalamaTuketim100Km = $toplamKm > 0 ? round(($toplamYakitLitre / $toplamKm) * 100, 2) : 0;
        $kmBasiMaliyet = $toplamKm > 0 ? round($toplamYakitTutar / $toplamKm, 2) : 0;

        $toplamGecenEvrak = (int) ($evrakData['muayene_gecen'] ?? 0) + (int) ($evrakData['sigorta_gecen'] ?? 0) + (int) ($evrakData['kasko_gecen'] ?? 0);
        $toplamYaklasanEvrak = (int) ($evrakData['muayene_yaklasan'] ?? 0) + (int) ($evrakData['sigorta_yaklasan'] ?? 0) + (int) ($evrakData['kasko_yaklasan'] ?? 0);

        return [
            'filo' => [
                'toplam_arac' => (int) ($filo['toplam_arac'] ?? 0),
                'aktif_arac' => (int) ($filo['aktif_arac'] ?? 0),
                'pasif_arac' => (int) ($filo['pasif_arac'] ?? 0),
                'ikame_arac' => (int) ($filo['ikame_arac'] ?? 0),
                'zimmetli_arac' => (int) ($filo['zimmetli_arac'] ?? 0),
                'bosta_arac' => (int) ($filo['bosta_arac'] ?? 0),
                'servisteki_arac' => (int) ($filo['servisteki_arac'] ?? 0),
            ],
            'km' => [
                'toplam_km' => $toplamKm,
                'aktif_kullanilan_arac' => $kullanilanAracSayisi,
                'km_kayit_adedi' => (int) ($kmData['km_kayit_adedi'] ?? 0),
                'gunluk_ortalama_km' => $gunlukOrtalamaKm,
                'arac_basi_ortalama_km' => $aracBasiOrtalamaKm,
                'en_cok_km_yapan' => $enCokKmYapan,
            ],
            'yakit' => [
                'toplam_tutar' => $toplamYakitTutar,
                'toplam_litre' => $toplamYakitLitre,
                'islem_adedi' => (int) ($yakitData['yakit_islem_adedi'] ?? 0),
                'ortalama_litre_fiyat' => $ortalamaLitreFiyat,
                'ortalama_tuketim_100km' => $ortalamaTuketim100Km,
                'km_basi_maliyet' => $kmBasiMaliyet,
                'en_cok_yakit_alan' => $enCokYakitAlan,
            ],
            'servis' => [
                'toplam_maliyet' => (float) ($servisData['toplam_servis_maliyet'] ?? 0),
                'islem_adedi' => (int) ($servisData['servis_islem_adedi'] ?? 0),
                'servisteki_arac' => (int) ($filo['servisteki_arac'] ?? 0),
            ],
            'evrak' => [
                'muayene_gecen' => (int) ($evrakData['muayene_gecen'] ?? 0),
                'muayene_yaklasan' => (int) ($evrakData['muayene_yaklasan'] ?? 0),
                'sigorta_gecen' => (int) ($evrakData['sigorta_gecen'] ?? 0),
                'sigorta_yaklasan' => (int) ($evrakData['sigorta_yaklasan'] ?? 0),
                'kasko_gecen' => (int) ($evrakData['kasko_gecen'] ?? 0),
                'kasko_yaklasan' => (int) ($evrakData['kasko_yaklasan'] ?? 0),
                'toplam_gecen' => $toplamGecenEvrak,
                'toplam_yaklasan' => $toplamYaklasanEvrak,
                'toplam_uyari' => $toplamGecenEvrak + $toplamYaklasanEvrak,
            ]
        ];
    }

    /**
     * Yıllık / Aylık Trendler (Yakıt Tutarı, Yakıt Litresi, Yapılan KM, Servis Maliyeti)
     */
    public function getMonthlyTrends($yil = null, $departman = null, $firmaId = null): array
    {
        $fId = $this->resolveFirmaId($firmaId);
        $yil = (int) ($yil ?: date('Y'));

        $ayIsimleri = [
            1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
            5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
            9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
        ];

        // 1. Aylık KM
        $kmSql = "
            SELECT 
                MONTH(k.tarih) as ay,
                COALESCE(SUM(k.yapilan_km), 0) as km
            FROM arac_km_kayitlari k
            INNER JOIN {$this->table} a ON k.arac_id = a.id
            WHERE k.firma_id = :firma_id
              AND YEAR(k.tarih) = :yil
              AND k.silinme_tarihi IS NULL
              AND a.silinme_tarihi IS NULL
        ";
        $kmParams = ['firma_id' => $fId, 'yil' => $yil];
        if (!empty($departman)) {
            $kmSql .= " AND a.departmani = :departman";
            $kmParams['departman'] = $departman;
        }
        $kmSql .= " GROUP BY MONTH(k.tarih)";
        $stmt = $this->db->prepare($kmSql);
        $stmt->execute($kmParams);
        $kmMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // 2. Aylık Yakıt (Tutar & Litre)
        $yakitSql = "
            SELECT 
                MONTH(y.tarih) as ay,
                COALESCE(SUM(y.toplam_tutar), 0) as tutar,
                COALESCE(SUM(y.yakit_miktari), 0) as litre
            FROM arac_yakit_kayitlari y
            INNER JOIN {$this->table} a ON y.arac_id = a.id
            WHERE y.firma_id = :firma_id
              AND YEAR(y.tarih) = :yil
              AND y.silinme_tarihi IS NULL
              AND a.silinme_tarihi IS NULL
        ";
        $yakitParams = ['firma_id' => $fId, 'yil' => $yil];
        if (!empty($departman)) {
            $yakitSql .= " AND a.departmani = :departman";
            $yakitParams['departman'] = $departman;
        }
        $yakitSql .= " GROUP BY MONTH(y.tarih)";
        $stmt = $this->db->prepare($yakitSql);
        $stmt->execute($yakitParams);
        $yakitRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $yakitMap = [];
        foreach ($yakitRows as $r) {
            $yakitMap[(int) $r['ay']] = $r;
        }

        // 3. Aylık Servis Maliyeti
        $servisSql = "
            SELECT 
                MONTH(s.servis_tarihi) as ay,
                COALESCE(SUM(s.tutar), 0) as tutar
            FROM arac_servis_kayitlari s
            INNER JOIN {$this->table} a ON s.arac_id = a.id
            WHERE s.firma_id = :firma_id
              AND YEAR(s.servis_tarihi) = :yil
              AND s.silinme_tarihi IS NULL
              AND a.silinme_tarihi IS NULL
        ";
        $servisParams = ['firma_id' => $fId, 'yil' => $yil];
        if (!empty($departman)) {
            $servisSql .= " AND a.departmani = :departman";
            $servisParams['departman'] = $departman;
        }
        $servisSql .= " GROUP BY MONTH(s.servis_tarihi)";
        $stmt = $this->db->prepare($servisSql);
        $stmt->execute($servisParams);
        $servisMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $labels = [];
        $kmSeries = [];
        $fuelCostSeries = [];
        $fuelLitreSeries = [];
        $serviceCostSeries = [];

        for ($m = 1; $m <= 12; $m++) {
            $labels[] = $ayIsimleri[$m];
            $kmSeries[] = round((float) ($kmMap[$m] ?? 0), 1);
            $fuelCostSeries[] = round((float) ($yakitMap[$m]['tutar'] ?? 0), 2);
            $fuelLitreSeries[] = round((float) ($yakitMap[$m]['litre'] ?? 0), 1);
            $serviceCostSeries[] = round((float) ($servisMap[$m] ?? 0), 2);
        }

        return [
            'yil' => $yil,
            'categories' => $labels,
            'km' => $kmSeries,
            'yakit_tutar' => $fuelCostSeries,
            'yakit_litre' => $fuelLitreSeries,
            'servis_tutar' => $serviceCostSeries
        ];
    }

    /**
     * Departman Dağılımı (Araç Sayısı, Yapılan KM, Yakıt Tutarı)
     */
    public function getDepartmentDistribution($baslangic = null, $bitis = null, $firmaId = null): array
    {
        $fId = $this->resolveFirmaId($firmaId);

        $sql = "
            SELECT 
                COALESCE(NULLIF(TRIM(a.departmani), ''), 'Belirtilmemiş') as departman,
                COUNT(DISTINCT a.id) as arac_sayisi,
                COALESCE(SUM(k.yapilan_km), 0) as toplam_km,
                COALESCE(SUM(y.toplam_tutar), 0) as toplam_yakit_tutar,
                COALESCE(SUM(y.yakit_miktari), 0) as toplam_litre
            FROM {$this->table} a
            LEFT JOIN (
                SELECT arac_id, SUM(yapilan_km) as yapilan_km 
                FROM arac_km_kayitlari 
                WHERE firma_id = :f_km AND silinme_tarihi IS NULL
        ";
        $params = ['f_km' => $fId, 'f_yk' => $fId, 'firma_id' => $fId];

        if (!empty($baslangic)) {
            $sql .= " AND tarih >= :baslangic_km";
            $params['baslangic_km'] = $baslangic;
        }
        if (!empty($bitis)) {
            $sql .= " AND tarih <= :bitis_km";
            $params['bitis_km'] = $bitis;
        }
        $sql .= " GROUP BY arac_id) k ON a.id = k.arac_id";

        $sql .= "
            LEFT JOIN (
                SELECT arac_id, SUM(toplam_tutar) as toplam_tutar, SUM(yakit_miktari) as yakit_miktari
                FROM arac_yakit_kayitlari 
                WHERE firma_id = :f_yk AND silinme_tarihi IS NULL
        ";
        if (!empty($baslangic)) {
            $sql .= " AND tarih >= :baslangic_yk";
            $params['baslangic_yk'] = $baslangic;
        }
        if (!empty($bitis)) {
            $sql .= " AND tarih <= :bitis_yk";
            $params['bitis_yk'] = $bitis;
        }
        $sql .= " GROUP BY arac_id) y ON a.id = y.arac_id";

        $sql .= "
            WHERE a.firma_id = :firma_id 
              AND a.silinme_tarihi IS NULL 
              AND a.aktif_mi = 1
            GROUP BY departman
            ORDER BY toplam_km DESC, arac_sayisi DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Araç Tipi & Mülkiyet Dağılımı
     */
    public function getVehicleTypesDistribution($firmaId = null): array
    {
        $fId = $this->resolveFirmaId($firmaId);

        // Mülkiyet (Özmal, Kiralık, vb.)
        $mulkiyetStmt = $this->db->prepare("
            SELECT 
                COALESCE(NULLIF(TRIM(mulkiyet), ''), 'Belirtilmemiş') as mulkiyet,
                COUNT(*) as adet
            FROM {$this->table}
            WHERE firma_id = :firma_id 
              AND silinme_tarihi IS NULL 
              AND aktif_mi = 1
            GROUP BY mulkiyet
            ORDER BY adet DESC
        ");
        $mulkiyetStmt->execute(['firma_id' => $fId]);
        $mulkiyetler = $mulkiyetStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Yakıt Tipi (Dizel, Benzin, Hibrit, Elektrik, vb.)
        $yakitTipiStmt = $this->db->prepare("
            SELECT 
                COALESCE(NULLIF(TRIM(yakit_tipi), ''), 'Belirtilmemiş') as yakit_tipi,
                COUNT(*) as adet
            FROM {$this->table}
            WHERE firma_id = :firma_id 
              AND silinme_tarihi IS NULL 
              AND aktif_mi = 1
            GROUP BY yakit_tipi
            ORDER BY adet DESC
        ");
        $yakitTipiStmt->execute(['firma_id' => $fId]);
        $yakitTipleri = $yakitTipiStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'mulkiyet' => $mulkiyetler,
            'yakit_tipi' => $yakitTipleri
        ];
    }

    /**
     * En Çok KM Yapan İlk N Araç
     */
    public function getTopVehiclesByKm($baslangic = null, $bitis = null, $limit = 5, $departman = null, $firmaId = null): array
    {
        $fId = $this->resolveFirmaId($firmaId);
        $limit = max(1, min(50, (int) $limit));

        $sql = "
            SELECT 
                a.id as arac_id,
                a.plaka,
                a.marka,
                a.model,
                a.departmani,
                a.guncel_km,
                COALESCE(SUM(k.yapilan_km), 0) as toplam_yapilan_km,
                COUNT(k.id) as kayit_adedi,
                p.id as surucu_id,
                p.adi_soyadi as surucu_adi,
                p.departman as surucu_departman,
                p.resim_yolu as surucu_resim,
                p.personel_resim_yolu as surucu_resim_yolu
            FROM {$this->table} a
            INNER JOIN arac_km_kayitlari k ON a.id = k.arac_id AND k.silinme_tarihi IS NULL
            LEFT JOIN (
                SELECT az1.arac_id, az1.personel_id
                FROM arac_zimmetleri az1
                INNER JOIN (
                    SELECT MAX(id) as max_id 
                    FROM arac_zimmetleri 
                    WHERE durum = 'aktif' AND silinme_tarihi IS NULL 
                    GROUP BY arac_id
                ) az2 ON az1.id = az2.max_id
                WHERE az1.silinme_tarihi IS NULL
            ) az ON a.id = az.arac_id
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id
              AND a.silinme_tarihi IS NULL
        ";
        $params = ['firma_id' => $fId];

        if (!empty($baslangic)) {
            $sql .= " AND k.tarih >= :baslangic";
            $params['baslangic'] = $baslangic;
        }
        if (!empty($bitis)) {
            $sql .= " AND k.tarih <= :bitis";
            $params['bitis'] = $bitis;
        }
        if (!empty($departman)) {
            $sql .= " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }

        $sql .= "
            GROUP BY a.id, a.plaka, a.marka, a.model, a.departmani, a.guncel_km, p.id, p.adi_soyadi, p.departman, p.resim_yolu, p.personel_resim_yolu
            ORDER BY toplam_yapilan_km DESC
            LIMIT {$limit}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($results as &$r) {
            $r['enc_id'] = Security::encrypt((string) $r['arac_id']);
            $r['toplam_yapilan_km'] = (float) $r['toplam_yapilan_km'];
            if (!empty($r['surucu_id'])) {
                $r['enc_surucu_id'] = Security::encrypt((string) $r['surucu_id']);
            }
        }
        unset($r);

        return $results;
    }

    /**
     * En Çok Yakıt Tüketen İlk N Araç
     */
    public function getTopVehiclesByFuel($baslangic = null, $bitis = null, $limit = 5, $departman = null, $firmaId = null): array
    {
        $fId = $this->resolveFirmaId($firmaId);
        $limit = max(1, min(50, (int) $limit));

        $sql = "
            SELECT 
                a.id as arac_id,
                a.plaka,
                a.marka,
                a.model,
                a.departmani,
                COALESCE(SUM(y.toplam_tutar), 0) as toplam_tutar,
                COALESCE(SUM(y.yakit_miktari), 0) as toplam_litre,
                COUNT(y.id) as islem_adedi,
                p.id as surucu_id,
                p.adi_soyadi as surucu_adi,
                p.departman as surucu_departman
            FROM {$this->table} a
            INNER JOIN arac_yakit_kayitlari y ON a.id = y.arac_id AND y.silinme_tarihi IS NULL
            LEFT JOIN (
                SELECT az1.arac_id, az1.personel_id
                FROM arac_zimmetleri az1
                INNER JOIN (
                    SELECT MAX(id) as max_id 
                    FROM arac_zimmetleri 
                    WHERE durum = 'aktif' AND silinme_tarihi IS NULL 
                    GROUP BY arac_id
                ) az2 ON az1.id = az2.max_id
                WHERE az1.silinme_tarihi IS NULL
            ) az ON a.id = az.arac_id
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE a.firma_id = :firma_id
              AND a.silinme_tarihi IS NULL
        ";
        $params = ['firma_id' => $fId];

        if (!empty($baslangic)) {
            $sql .= " AND y.tarih >= :baslangic";
            $params['baslangic'] = $baslangic;
        }
        if (!empty($bitis)) {
            $sql .= " AND y.tarih <= :bitis";
            $params['bitis'] = $bitis;
        }
        if (!empty($departman)) {
            $sql .= " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }

        $sql .= "
            GROUP BY a.id, a.plaka, a.marka, a.model, a.departmani, p.id, p.adi_soyadi, p.departman
            ORDER BY toplam_tutar DESC
            LIMIT {$limit}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($results as &$r) {
            $r['enc_id'] = Security::encrypt((string) $r['arac_id']);
            $r['toplam_tutar'] = (float) $r['toplam_tutar'];
            $r['toplam_litre'] = (float) $r['toplam_litre'];
            $r['ort_fiyat'] = $r['toplam_litre'] > 0 ? round($r['toplam_tutar'] / $r['toplam_litre'], 2) : 0;
            if (!empty($r['surucu_id'])) {
                $r['enc_surucu_id'] = Security::encrypt((string) $r['surucu_id']);
            }
        }
        unset($r);

        return $results;
    }

    /**
     * Yaklaşan ve Geciken Evraklar (Muayene, Sigorta, Kasko)
     */
    public function getExpiringDocuments($departman = null, $firmaId = null): array
    {
        $fId = $this->resolveFirmaId($firmaId);

        $deptClause = '';
        $params = [
            'f1' => $fId,
            'f2' => $fId,
            'f3' => $fId
        ];

        if (!empty($departman)) {
            $deptClause = " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }

        $sql = "
            SELECT 
                sub.arac_id,
                sub.plaka,
                sub.marka,
                sub.model,
                sub.departmani,
                sub.evrak_tipi,
                sub.bitis_tarihi,
                DATEDIFF(sub.bitis_tarihi, CURDATE()) as kalan_gun,
                p.adi_soyadi as surucu_adi
            FROM (
                SELECT 
                    a.id as arac_id, a.plaka, a.marka, a.model, a.departmani,
                    'Muayene' as evrak_tipi, a.muayene_bitis_tarihi as bitis_tarihi
                FROM {$this->table} a
                WHERE a.firma_id = :f1 AND a.silinme_tarihi IS NULL AND a.aktif_mi = 1 
                  AND a.muayene_bitis_tarihi IS NOT NULL
                  AND (a.muayene_bitis_tarihi < CURDATE() OR a.muayene_bitis_tarihi <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                  {$deptClause}

                UNION ALL

                SELECT 
                    a.id as arac_id, a.plaka, a.marka, a.model, a.departmani,
                    'Trafik Sigortası' as evrak_tipi, a.sigorta_bitis_tarihi as bitis_tarihi
                FROM {$this->table} a
                WHERE a.firma_id = :f2 AND a.silinme_tarihi IS NULL AND a.aktif_mi = 1 
                  AND a.sigorta_bitis_tarihi IS NOT NULL
                  AND (a.sigorta_bitis_tarihi < CURDATE() OR a.sigorta_bitis_tarihi <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                  {$deptClause}

                UNION ALL

                SELECT 
                    a.id as arac_id, a.plaka, a.marka, a.model, a.departmani,
                    'Kasko' as evrak_tipi, a.kasko_bitis_tarihi as bitis_tarihi
                FROM {$this->table} a
                WHERE a.firma_id = :f3 AND a.silinme_tarihi IS NULL AND a.aktif_mi = 1 
                  AND a.kasko_bitis_tarihi IS NOT NULL
                  AND (a.kasko_bitis_tarihi < CURDATE() OR a.kasko_bitis_tarihi <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                  {$deptClause}
            ) sub
            LEFT JOIN (
                SELECT az1.arac_id, az1.personel_id
                FROM arac_zimmetleri az1
                INNER JOIN (
                    SELECT MAX(id) as max_id 
                    FROM arac_zimmetleri 
                    WHERE durum = 'aktif' AND silinme_tarihi IS NULL 
                    GROUP BY arac_id
                ) az2 ON az1.id = az2.max_id
                WHERE az1.silinme_tarihi IS NULL
            ) az ON sub.arac_id = az.arac_id
            LEFT JOIN personel p ON az.personel_id = p.id
            ORDER BY kalan_gun ASC
            LIMIT 25
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($results as &$r) {
            $r['enc_id'] = Security::encrypt((string) $r['arac_id']);
            $r['kalan_gun'] = (int) $r['kalan_gun'];
        }
        unset($r);

        return $results;
    }

    /**
     * Son Yapılan Servis & Bakım İşlemleri
     */
    public function getRecentServices($limit = 5, $departman = null, $firmaId = null): array
    {
        $fId = $this->resolveFirmaId($firmaId);
        $limit = max(1, min(50, (int) $limit));

        $sql = "
            SELECT 
                s.id as servis_id,
                s.servis_tarihi,
                s.iade_tarihi,
                s.servis_adi,
                s.servis_nedeni,
                s.yapilan_islemler,
                s.tutar,
                s.fatura_no,
                a.id as arac_id,
                a.plaka,
                a.marka,
                a.model,
                a.departmani
            FROM arac_servis_kayitlari s
            INNER JOIN {$this->table} a ON s.arac_id = a.id
            WHERE s.firma_id = :firma_id
              AND s.silinme_tarihi IS NULL
              AND a.silinme_tarihi IS NULL
        ";
        $params = ['firma_id' => $fId];

        if (!empty($departman)) {
            $sql .= " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }

        $sql .= " ORDER BY s.servis_tarihi DESC, s.id DESC LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($results as &$r) {
            $r['enc_arac_id'] = Security::encrypt((string) $r['arac_id']);
            $r['tutar'] = (float) $r['tutar'];
        }
        unset($r);

        return $results;
    }

    /**
     * Son Yakıt Alımları
     */
    public function getRecentFuelLogs($limit = 5, $departman = null, $firmaId = null): array
    {
        $fId = $this->resolveFirmaId($firmaId);
        $limit = max(1, min(50, (int) $limit));

        $sql = "
            SELECT 
                y.id as yakit_id,
                y.tarih,
                y.km,
                y.yakit_miktari,
                y.birim_fiyat,
                y.toplam_tutar,
                y.istasyon,
                y.fatura_no,
                a.id as arac_id,
                a.plaka,
                a.marka,
                a.model,
                a.departmani,
                p.adi_soyadi as surucu_adi
            FROM arac_yakit_kayitlari y
            INNER JOIN {$this->table} a ON y.arac_id = a.id
            LEFT JOIN (
                SELECT az1.arac_id, az1.personel_id
                FROM arac_zimmetleri az1
                INNER JOIN (
                    SELECT MAX(id) as max_id 
                    FROM arac_zimmetleri 
                    WHERE durum = 'aktif' AND silinme_tarihi IS NULL 
                    GROUP BY arac_id
                ) az2 ON az1.id = az2.max_id
                WHERE az1.silinme_tarihi IS NULL
            ) az ON a.id = az.arac_id
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE y.firma_id = :firma_id
              AND y.silinme_tarihi IS NULL
              AND a.silinme_tarihi IS NULL
        ";
        $params = ['firma_id' => $fId];

        if (!empty($departman)) {
            $sql .= " AND a.departmani = :departman";
            $params['departman'] = $departman;
        }

        $sql .= " ORDER BY y.tarih DESC, y.id DESC LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($results as &$r) {
            $r['enc_arac_id'] = Security::encrypt((string) $r['arac_id']);
            $r['toplam_tutar'] = (float) $r['toplam_tutar'];
            $r['yakit_miktari'] = (float) $r['yakit_miktari'];
            $r['birim_fiyat'] = (float) $r['birim_fiyat'];
        }
        unset($r);

        return $results;
    }
}
