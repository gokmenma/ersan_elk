<?php

namespace App\Model;

use PDO;

class PersonelIsRaporuModel extends Model
{
    public function __construct()
    {
        parent::__construct('personel');
    }

    /**
     * Personelin verilen tarih aralığındaki KPI özet sayılarını döndürür
     */
    public function getPersonelKpiSummary(int $firmaId, int $personelId, string $startDate, string $endDate): array
    {
        $kpi = [
            'toplam_is' => 0,
            'kesme_acma' => 0,
            'kesme_adet' => 0,
            'acma_adet' => 0,
            'muhurleme' => 0,
            'endeks_okuma' => 0,
            'sayac_degisim' => 0,
            'kacak_kontrol' => 0,
            'aktif_gun_sayisi' => 0,
            'gunluk_ortalama' => 0
        ];

        // 1. Kesme / Açma ve Mühürleme (yapilan_isler tablosundan)
        $sqlKesme = "SELECT 
                        COUNT(*) as total_kayit,
                        SUM(COALESCE(sonuclanmis, 1)) as total_is,
                        SUM(CASE WHEN UPPER(is_emri_tipi) LIKE '%KESME%' THEN COALESCE(sonuclanmis, 1) ELSE 0 END) as kesme_adet,
                        SUM(CASE WHEN UPPER(is_emri_tipi) LIKE '%AÇMA%' OR UPPER(is_emri_tipi) LIKE '%ACMA%' THEN COALESCE(sonuclanmis, 1) ELSE 0 END) as acma_adet,
                        SUM(CASE WHEN UPPER(is_emri_tipi) LIKE '%MÜHÜR%' OR UPPER(is_emri_tipi) LIKE '%MUHUR%' THEN COALESCE(sonuclanmis, 1) ELSE 0 END) as muhurleme_adet
                     FROM yapilan_isler 
                     WHERE firma_id = :firma_id 
                       AND personel_id = :personel_id 
                       AND tarih BETWEEN :start_date AND :end_date
                       AND silinme_tarihi IS NULL";
        $stmtKesme = $this->db->prepare($sqlKesme);
        $stmtKesme->execute([
            ':firma_id' => $firmaId,
            ':personel_id' => $personelId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $rowKesme = $stmtKesme->fetch(PDO::FETCH_ASSOC);
        if ($rowKesme) {
            $kpi['muhurleme'] = (int) ($rowKesme['muhurleme_adet'] ?? 0);
            $kpi['kesme_adet'] = (int) ($rowKesme['kesme_adet'] ?? 0);
            $kpi['acma_adet'] = (int) ($rowKesme['acma_adet'] ?? 0);
            $kpi['kesme_acma'] = (int) ($rowKesme['total_is'] ?? 0) - $kpi['muhurleme'];
            if ($kpi['kesme_acma'] < 0) $kpi['kesme_acma'] = 0;
        }

        // 2. Endeks Okuma
        $sqlOkuma = "SELECT 
                        COUNT(*) as total_kayit,
                        SUM(CASE 
                            WHEN okunan_abone_sayisi > 0 THEN okunan_abone_sayisi 
                            WHEN abone_sayisi > 0 THEN abone_sayisi 
                            ELSE 1 
                        END) as total_okuma
                     FROM endeks_okuma 
                     WHERE firma_id = :firma_id 
                       AND personel_id = :personel_id 
                       AND tarih BETWEEN :start_date AND :end_date
                       AND silinme_tarihi IS NULL";
        $stmtOkuma = $this->db->prepare($sqlOkuma);
        $stmtOkuma->execute([
            ':firma_id' => $firmaId,
            ':personel_id' => $personelId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $rowOkuma = $stmtOkuma->fetch(PDO::FETCH_ASSOC);
        if ($rowOkuma) {
            $kpi['endeks_okuma'] = (int) ($rowOkuma['total_okuma'] ?? 0);
        }

        // 3. Sayaç Sökme Takma
        $sqlSayac = "SELECT 
                        COUNT(*) as total_kayit,
                        SUM(COALESCE(is_sayisi, 1)) as total_sayac
                     FROM sayac_degisim 
                     WHERE firma_id = :firma_id 
                       AND personel_id = :personel_id 
                       AND tarih BETWEEN :start_date AND :end_date
                       AND silinme_tarihi IS NULL";
        $stmtSayac = $this->db->prepare($sqlSayac);
        $stmtSayac->execute([
            ':firma_id' => $firmaId,
            ':personel_id' => $personelId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $rowSayac = $stmtSayac->fetch(PDO::FETCH_ASSOC);
        if ($rowSayac) {
            $kpi['sayac_degisim'] = (int) ($rowSayac['total_sayac'] ?? 0);
        }

        // 4. Kaçak Kontrol
        $sqlKacak = "SELECT 
                        COUNT(*) as total_kayit,
                        SUM(COALESCE(sayi, 1)) as total_kacak
                     FROM kacak_kontrol 
                     WHERE firma_id = :firma_id 
                       AND (FIND_IN_SET(:personel_id, personel_ids) OR bildiren_personel_id = :personel_id)
                       AND tarih BETWEEN :start_date AND :end_date
                       AND silinme_tarihi IS NULL
                       AND durum = 'aktif'";
        $stmtKacak = $this->db->prepare($sqlKacak);
        $stmtKacak->execute([
            ':firma_id' => $firmaId,
            ':personel_id' => $personelId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $rowKacak = $stmtKacak->fetch(PDO::FETCH_ASSOC);
        if ($rowKacak) {
            $kpi['kacak_kontrol'] = (int) ($rowKacak['total_kacak'] ?? 0);
        }

        $kpi['toplam_is'] = $kpi['kesme_acma'] + $kpi['muhurleme'] + $kpi['endeks_okuma'] + $kpi['sayac_degisim'] + $kpi['kacak_kontrol'];

        // Aktif gün sayısı tespiti (en az 1 işlem yapılan farklı günlerin sayısı)
        $sqlGun = "SELECT COUNT(DISTINCT tarih) as aktif_gun FROM (
                        SELECT tarih FROM yapilan_isler WHERE firma_id = :f1 AND personel_id = :p1 AND tarih BETWEEN :s1 AND :e1 AND silinme_tarihi IS NULL
                        UNION
                        SELECT tarih FROM endeks_okuma WHERE firma_id = :f2 AND personel_id = :p2 AND tarih BETWEEN :s2 AND :e2 AND silinme_tarihi IS NULL
                        UNION
                        SELECT tarih FROM sayac_degisim WHERE firma_id = :f3 AND personel_id = :p3 AND tarih BETWEEN :s3 AND :e3 AND silinme_tarihi IS NULL
                        UNION
                        SELECT tarih FROM kacak_kontrol WHERE firma_id = :f4 AND (FIND_IN_SET(:p4, personel_ids) OR bildiren_personel_id = :p4) AND tarih BETWEEN :s4 AND :e4 AND silinme_tarihi IS NULL AND durum = 'aktif'
                   ) as t_gunler";
        $stmtGun = $this->db->prepare($sqlGun);
        $stmtGun->execute([
            ':f1' => $firmaId, ':p1' => $personelId, ':s1' => $startDate, ':e1' => $endDate,
            ':f2' => $firmaId, ':p2' => $personelId, ':s2' => $startDate, ':e2' => $endDate,
            ':f3' => $firmaId, ':p3' => $personelId, ':s3' => $startDate, ':e3' => $endDate,
            ':f4' => $firmaId, ':p4' => $personelId, ':s4' => $startDate, ':e4' => $endDate
        ]);
        $gunRow = $stmtGun->fetch(PDO::FETCH_ASSOC);
        $kpi['aktif_gun_sayisi'] = (int) ($gunRow['aktif_gun'] ?? 0);

        if ($kpi['aktif_gun_sayisi'] > 0) {
            $kpi['gunluk_ortalama'] = round($kpi['toplam_is'] / $kpi['aktif_gun_sayisi'], 1);
        }

        return $kpi;
    }

    /**
     * ApexCharts ve Günlük Trend için tarih bazında kategorilere göre dağılım
     */
    public function getDailyTrendData(int $firmaId, int $personelId, string $startDate, string $endDate): array
    {
        $dates = [];
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $end->modify('+1 day');
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($start, $interval, $end);

        $trDays = [
            'Mon' => 'Pzt',
            'Tue' => 'Sal',
            'Wed' => 'Çar',
            'Thu' => 'Per',
            'Fri' => 'Cum',
            'Sat' => 'Cmt',
            'Sun' => 'Paz'
        ];

        $dailyData = [];
        foreach ($period as $dt) {
            $dStr = $dt->format('Y-m-d');
            $dates[] = $dStr;
            $enDay = $dt->format('D');
            $dailyData[$dStr] = [
                'tarih' => $dStr,
                'tarih_tr' => $dt->format('d.m.Y'),
                'gun_adi' => $trDays[$enDay] ?? $enDay,
                'is_weekend' => ($enDay === 'Sun' || $enDay === 'Sat'),
                'kesme_acma' => 0,
                'muhurleme' => 0,
                'endeks_okuma' => 0,
                'sayac_degisim' => 0,
                'kacak_kontrol' => 0,
                'toplam' => 0
            ];
        }

        // 1. Kesme Açma ve Mühürleme Günlük
        $sqlKesme = "SELECT 
                        tarih, 
                        SUM(CASE WHEN UPPER(is_emri_tipi) LIKE '%MÜHÜR%' OR UPPER(is_emri_tipi) LIKE '%MUHUR%' THEN COALESCE(sonuclanmis, 1) ELSE 0 END) as muhur_toplam,
                        SUM(CASE WHEN UPPER(is_emri_tipi) NOT LIKE '%MÜHÜR%' AND UPPER(is_emri_tipi) NOT LIKE '%MUHUR%' THEN COALESCE(sonuclanmis, 1) ELSE 0 END) as kesme_toplam
                     FROM yapilan_isler 
                     WHERE firma_id = :firma_id AND personel_id = :personel_id AND tarih BETWEEN :start_date AND :end_date AND silinme_tarihi IS NULL
                     GROUP BY tarih";
        $stmtKesme = $this->db->prepare($sqlKesme);
        $stmtKesme->execute([':firma_id' => $firmaId, ':personel_id' => $personelId, ':start_date' => $startDate, ':end_date' => $endDate]);
        while ($r = $stmtKesme->fetch(PDO::FETCH_ASSOC)) {
            $d = $r['tarih'];
            if (isset($dailyData[$d])) {
                $dailyData[$d]['kesme_acma'] = (int) $r['kesme_toplam'];
                $dailyData[$d]['muhurleme'] = (int) $r['muhur_toplam'];
            }
        }

        // 2. Endeks Okuma Günlük
        $sqlOkuma = "SELECT tarih, SUM(CASE WHEN okunan_abone_sayisi > 0 THEN okunan_abone_sayisi WHEN abone_sayisi > 0 THEN abone_sayisi ELSE 1 END) as toplam 
                     FROM endeks_okuma 
                     WHERE firma_id = :firma_id AND personel_id = :personel_id AND tarih BETWEEN :start_date AND :end_date AND silinme_tarihi IS NULL
                     GROUP BY tarih";
        $stmtOkuma = $this->db->prepare($sqlOkuma);
        $stmtOkuma->execute([':firma_id' => $firmaId, ':personel_id' => $personelId, ':start_date' => $startDate, ':end_date' => $endDate]);
        while ($r = $stmtOkuma->fetch(PDO::FETCH_ASSOC)) {
            $d = $r['tarih'];
            if (isset($dailyData[$d])) {
                $dailyData[$d]['endeks_okuma'] = (int) $r['toplam'];
            }
        }

        // 3. Sayaç Sökme Takma Günlük
        $sqlSayac = "SELECT tarih, SUM(COALESCE(is_sayisi, 1)) as toplam 
                     FROM sayac_degisim 
                     WHERE firma_id = :firma_id AND personel_id = :personel_id AND tarih BETWEEN :start_date AND :end_date AND silinme_tarihi IS NULL
                     GROUP BY tarih";
        $stmtSayac = $this->db->prepare($sqlSayac);
        $stmtSayac->execute([':firma_id' => $firmaId, ':personel_id' => $personelId, ':start_date' => $startDate, ':end_date' => $endDate]);
        while ($r = $stmtSayac->fetch(PDO::FETCH_ASSOC)) {
            $d = $r['tarih'];
            if (isset($dailyData[$d])) {
                $dailyData[$d]['sayac_degisim'] = (int) $r['toplam'];
            }
        }

        // 4. Kaçak Kontrol Günlük
        $sqlKacak = "SELECT tarih, SUM(COALESCE(sayi, 1)) as toplam 
                     FROM kacak_kontrol 
                     WHERE firma_id = :firma_id AND (FIND_IN_SET(:personel_id, personel_ids) OR bildiren_personel_id = :personel_id) AND tarih BETWEEN :start_date AND :end_date AND silinme_tarihi IS NULL AND durum = 'aktif'
                     GROUP BY tarih";
        $stmtKacak = $this->db->prepare($sqlKacak);
        $stmtKacak->execute([':firma_id' => $firmaId, ':personel_id' => $personelId, ':start_date' => $startDate, ':end_date' => $endDate]);
        while ($r = $stmtKacak->fetch(PDO::FETCH_ASSOC)) {
            $d = $r['tarih'];
            if (isset($dailyData[$d])) {
                $dailyData[$d]['kacak_kontrol'] = (int) $r['toplam'];
            }
        }

        // Toplamları hesapla
        $seriesKesme = [];
        $seriesOkuma = [];
        $seriesSayac = [];
        $seriesMuhur = [];
        $seriesKacak = [];
        $categories = [];

        foreach ($dailyData as $d => &$row) {
            $row['toplam'] = $row['kesme_acma'] + $row['muhurleme'] + $row['endeks_okuma'] + $row['sayac_degisim'] + $row['kacak_kontrol'];
            $categories[] = date('d.m', strtotime($d));
            $seriesKesme[] = $row['kesme_acma'];
            $seriesOkuma[] = $row['endeks_okuma'];
            $seriesSayac[] = $row['sayac_degisim'];
            $seriesMuhur[] = $row['muhurleme'];
            $seriesKacak[] = $row['kacak_kontrol'];
        }
        unset($row);

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'Kesme / Açma', 'data' => $seriesKesme, 'color' => '#f06548'],
                ['name' => 'Endeks Okuma', 'data' => $seriesOkuma, 'color' => '#0ab39c'],
                ['name' => 'Sayaç Sökme Takma', 'data' => $seriesSayac, 'color' => '#ffbe0b'],
                ['name' => 'Mühürleme', 'data' => $seriesMuhur, 'color' => '#06b6d4'],
                ['name' => 'Kaçak İşlemleri', 'data' => $seriesKacak, 'color' => '#405189']
            ],
            'daily_list' => array_values($dailyData)
        ];
    }

    /**
     * İş türü dağılımı (Donut grafik için)
     */
    public function getCategoryDistribution(array $kpi): array
    {
        $labels = [];
        $series = [];
        $colors = [];

        if ($kpi['kesme_acma'] > 0) {
            $labels[] = 'Kesme / Açma';
            $series[] = $kpi['kesme_acma'];
            $colors[] = '#f06548';
        }
        if ($kpi['endeks_okuma'] > 0) {
            $labels[] = 'Endeks Okuma';
            $series[] = $kpi['endeks_okuma'];
            $colors[] = '#0ab39c';
        }
        if ($kpi['sayac_degisim'] > 0) {
            $labels[] = 'Sayaç Sökme Takma';
            $series[] = $kpi['sayac_degisim'];
            $colors[] = '#ffbe0b';
        }
        if ($kpi['muhurleme'] > 0) {
            $labels[] = 'Mühürleme';
            $series[] = $kpi['muhurleme'];
            $colors[] = '#06b6d4';
        }
        if ($kpi['kacak_kontrol'] > 0) {
            $labels[] = 'Kaçak İşlemleri';
            $series[] = $kpi['kacak_kontrol'];
            $colors[] = '#405189';
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'colors' => $colors
        ];
    }

    /**
     * Detaylı işlem listesi (Tüm kategorileri birleşik liste olarak getirir)
     */
    public function getDetailedWorkLogs(int $firmaId, int $personelId, string $startDate, string $endDate, ?string $category = null, int $limit = 1000): array
    {
        $logs = [];

        // 1. Kesme / Açma & Mühürleme
        if (empty($category) || $category === 'kesme_acma' || $category === 'muhurleme') {
            $muhurWhere = '';
            if ($category === 'kesme_acma') {
                $muhurWhere = " AND UPPER(is_emri_tipi) NOT LIKE '%MÜHÜR%' AND UPPER(is_emri_tipi) NOT LIKE '%MUHUR%' ";
            } elseif ($category === 'muhurleme') {
                $muhurWhere = " AND (UPPER(is_emri_tipi) LIKE '%MÜHÜR%' OR UPPER(is_emri_tipi) LIKE '%MUHUR%') ";
            }

            $sql = "SELECT 
                        id,
                        tarih,
                        CASE 
                            WHEN UPPER(is_emri_tipi) LIKE '%MÜHÜR%' OR UPPER(is_emri_tipi) LIKE '%MUHUR%' THEN 'muhurleme'
                            ELSE 'kesme_acma'
                        END as kategori,
                        CASE 
                            WHEN UPPER(is_emri_tipi) LIKE '%MÜHÜR%' OR UPPER(is_emri_tipi) LIKE '%MUHUR%' THEN 'Mühürleme'
                            ELSE 'Kesme / Açma'
                        END as kategori_adi,
                        is_emri_tipi,
                        is_emri_sonucu,
                        abone_no,
                        is_emri_no,
                        ekip_kodu as ekip,
                        '' as bolge,
                        COALESCE(sonuclanmis, 1) as adet,
                        aciklama,
                        created_at
                    FROM yapilan_isler
                    WHERE firma_id = :firma_id 
                      AND personel_id = :personel_id 
                      AND tarih BETWEEN :start_date AND :end_date
                      AND silinme_tarihi IS NULL
                      {$muhurWhere}
                    ORDER BY tarih DESC, id DESC
                    LIMIT " . (int) $limit;
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':firma_id' => $firmaId, ':personel_id' => $personelId, ':start_date' => $startDate, ':end_date' => $endDate]);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $logs[] = $r;
            }
        }

        // 2. Endeks Okuma
        if (empty($category) || $category === 'endeks_okuma') {
            $sql = "SELECT 
                        id,
                        tarih,
                        'endeks_okuma' as kategori,
                        'Endeks Okuma' as kategori_adi,
                        'Endeks Okuma' as is_emri_tipi,
                        COALESCE(sayac_durum, 'Okundu') as is_emri_sonucu,
                        abone_no,
                        is_emri_no,
                        defter as ekip,
                        CONCAT_WS(' / ', bolge, mahalle) as bolge,
                        CASE WHEN okunan_abone_sayisi > 0 THEN okunan_abone_sayisi WHEN abone_sayisi > 0 THEN abone_sayisi ELSE 1 END as adet,
                        CONCAT_WS(' - ', aciklama, CONCAT('Sarfiyat: ', sarfiyat)) as aciklama,
                        created_at
                    FROM endeks_okuma
                    WHERE firma_id = :firma_id 
                      AND personel_id = :personel_id 
                      AND tarih BETWEEN :start_date AND :end_date
                      AND silinme_tarihi IS NULL
                    ORDER BY tarih DESC, id DESC
                    LIMIT " . (int) $limit;
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':firma_id' => $firmaId, ':personel_id' => $personelId, ':start_date' => $startDate, ':end_date' => $endDate]);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $logs[] = $r;
            }
        }

        // 3. Sayaç Sökme Takma
        if (empty($category) || $category === 'sayac_degisim') {
            $sql = "SELECT 
                        id,
                        tarih,
                        'sayac_degisim' as kategori,
                        'Sayaç Sökme Takma' as kategori_adi,
                        COALESCE(isemri_sebep, 'Sayaç Değişimi') as is_emri_tipi,
                        COALESCE(isemri_sonucu, sonuc_aciklama, 'Tamamlandı') as is_emri_sonucu,
                        abone_no,
                        isemri_no as is_emri_no,
                        ekip,
                        bolge,
                        COALESCE(is_sayisi, 1) as adet,
                        CONCAT_WS(' - ', sonuc_aciklama, CONCAT('Takılan: ', takilan_sayacno)) as aciklama,
                        created_at
                    FROM sayac_degisim
                    WHERE firma_id = :firma_id 
                      AND personel_id = :personel_id 
                      AND tarih BETWEEN :start_date AND :end_date
                      AND silinme_tarihi IS NULL
                    ORDER BY tarih DESC, id DESC
                    LIMIT " . (int) $limit;
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':firma_id' => $firmaId, ':personel_id' => $personelId, ':start_date' => $startDate, ':end_date' => $endDate]);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $logs[] = $r;
            }
        }

        // 4. Kaçak Kontrol
        if (empty($category) || $category === 'kacak_kontrol') {
            $sql = "SELECT 
                        id,
                        tarih,
                        'kacak_kontrol' as kategori,
                        'Kaçak İşlemleri' as kategori_adi,
                        tur as is_emri_tipi,
                        CONCAT('Tutanak No: ', COALESCE(tutanak_no, '-')) as is_emri_sonucu,
                        COALESCE(sayac_no, '-') as abone_no,
                        COALESCE(tutanak_no, '-') as is_emri_no,
                        ekip_adi as ekip,
                        ilce as bolge,
                        COALESCE(sayi, 1) as adet,
                        CONCAT_WS(' - ', abone_adi, aciklama) as aciklama,
                        olusturma_tarihi as created_at
                    FROM kacak_kontrol
                    WHERE firma_id = :firma_id 
                      AND (FIND_IN_SET(:personel_id, personel_ids) OR bildiren_personel_id = :personel_id)
                      AND tarih BETWEEN :start_date AND :end_date
                      AND silinme_tarihi IS NULL
                      AND durum = 'aktif'
                    ORDER BY tarih DESC, id DESC
                    LIMIT " . (int) $limit;
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':firma_id' => $firmaId, ':personel_id' => $personelId, ':start_date' => $startDate, ':end_date' => $endDate]);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $logs[] = $r;
            }
        }

        // Tarihe göre sırala (en yeniden eskiye)
        usort($logs, function ($a, $b) {
            $cmp = strcmp($b['tarih'], $a['tarih']);
            if ($cmp === 0) {
                return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
            }
            return $cmp;
        });

        return array_slice($logs, 0, $limit);
    }
}
