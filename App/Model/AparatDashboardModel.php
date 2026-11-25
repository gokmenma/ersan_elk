<?php

namespace App\Model;

use DateTimeImmutable;
use PDO;

/**
 * Aparat Takip Dashboard veri katmanı.
 * Anlık stok havuzlarını, aparat tipi / ekip kırılımlarını, kritik stokları
 * ve seçili dönemin saha/depo hareket trendlerini PDO parametrik sorgularla üretir.
 */
class AparatDashboardModel extends Model
{
    protected $table = 'aparat_stok';

    private const AY_ADLARI = [
        1 => 'Oca', 2 => 'Şub', 3 => 'Mar', 4 => 'Nis',
        5 => 'May', 6 => 'Haz', 7 => 'Tem', 8 => 'Ağu',
        9 => 'Eyl', 10 => 'Eki', 11 => 'Kas', 12 => 'Ara',
    ];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    /**
     * Dönem anahtarını başlangıç/bitiş tarihine ve grafik kırılımına çevirir.
     */
    public function donemCoz(string $period, ?string $startDate = null, ?string $endDate = null, int $aparatTipId = 0): array
    {
        $bugun = new DateTimeImmutable('today');
        $bitis = $bugun->format('Y-m-d');
        $etiket = 'Bu Ay';

        switch ($period) {
            case 'today':
                $baslangic = $bitis;
                $etiket = 'Bugün';
                break;

            case 'last_7_days':
                $baslangic = $bugun->modify('-6 days')->format('Y-m-d');
                $etiket = 'Son 7 Gün';
                break;

            case 'last_30_days':
                $baslangic = $bugun->modify('-29 days')->format('Y-m-d');
                $etiket = 'Son 30 Gün';
                break;

            case 'last_3_months':
                $baslangic = $bugun->modify('first day of -2 months')->format('Y-m-d');
                $etiket = 'Son 3 Ay';
                break;

            case 'this_year':
                $baslangic = $bugun->format('Y-01-01');
                $etiket = 'Bu Yıl (' . $bugun->format('Y') . ')';
                break;

            case 'all':
                $ilkTarih = $this->ilkHareketTarihi($aparatTipId);
                $baslangic = $ilkTarih ?: $bugun->format('Y-01-01');
                $etiket = 'Tüm Zamanlar';
                break;

            case 'custom':
                $basTs = $startDate ? strtotime($startDate) : false;
                $bitTs = $endDate ? strtotime($endDate) : false;
                $baslangic = $basTs !== false ? date('Y-m-d', $basTs) : $bugun->format('Y-m-01');
                $bitis = $bitTs !== false ? date('Y-m-d', $bitTs) : $bugun->format('Y-m-d');
                if ($baslangic > $bitis) {
                    [$baslangic, $bitis] = [$bitis, $baslangic];
                }
                $etiket = date('d.m.Y', strtotime($baslangic)) . ' - ' . date('d.m.Y', strtotime($bitis));
                break;

            case 'this_month':
            default:
                $period = 'this_month';
                $baslangic = $bugun->format('Y-m-01');
                $etiket = 'Bu Ay';
                break;
        }

        $gunFarki = max(1, (int) ((strtotime($bitis) - strtotime($baslangic)) / 86400) + 1);
        $kirilim = $gunFarki <= 92 ? 'daily' : 'monthly';

        return [
            'key' => $period,
            'start_date' => $baslangic,
            'end_date' => $bitis,
            'days' => $gunFarki,
            'granularity' => $kirilim,
            'label' => $etiket,
        ];
    }

    private function ilkHareketTarihi(int $aparatTipId = 0): ?string
    {
        $sql = "SELECT MIN(DATE(tarih)) FROM aparat_hareket WHERE firma_id = ? AND iptal_mi = 0";
        $params = [$this->firmaId()];
        if ($aparatTipId > 0) {
            $sql .= " AND aparat_tip_id = ?";
            $params[] = $aparatTipId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val ? (string) $val : null;
    }

    /**
     * Dashboard için tüm özet, grafik ve liste verilerini tek çağrıda döner.
     */
    public function getDashboardData(array $filtre = [], bool $gelismisYetki = false): array
    {
        $aparatTipId = max(0, (int) ($filtre['aparat_tip_id'] ?? 0));
        $donem = $this->donemCoz(
            (string) ($filtre['period'] ?? 'this_month'),
            $filtre['start_date'] ?? null,
            $filtre['end_date'] ?? null,
            $aparatTipId
        );

        $stokModel = new AparatStokModel();
        $hareketModel = new AparatHareketModel();
        $sayimModel = new AparatSayimModel();

        $matris = $stokModel->matris();
        $kritik = $stokModel->kritikStoklar();

        // Aparat tipi filtresi seçiliyse kritik stokları da ilgili tipe daralt
        if ($aparatTipId > 0) {
            $kritik['depo'] = array_values(array_filter(
                $kritik['depo'],
                static fn(array $k): bool => (int) $k['aparat_tip_id'] === $aparatTipId
            ));
            $yeniEkipKritik = [];
            foreach ($kritik['ekipler'] as $ek) {
                $eksikler = array_values(array_filter(
                    $ek['eksikler'],
                    static fn(array $k): bool => (int) $k['aparat_tip_id'] === $aparatTipId
                ));
                if (!empty($eksikler)) {
                    $ek['eksikler'] = $eksikler;
                    $yeniEkipKritik[] = $ek;
                }
            }
            $kritik['ekipler'] = $yeniEkipKritik;
            $kritik['toplam'] = count($kritik['depo']) + array_sum(array_map(
                static fn(array $e): int => count($e['eksikler']),
                $kritik['ekipler']
            ));
        }

        $tipler = $matris['tipler'];
        $aktifTipler = array_values(array_filter($tipler, static fn(array $t): bool => (int) $t['is_active'] === 1));

        // Havuz toplamları ve ekip envanter analizi
        $havuzToplam = [
            'depo' => 0,
            'ekip' => 0,
            'saha' => 0,
            'hurda' => 0,
            'kayip' => 0,
        ];

        $tipHavuzHaritasi = [];
        foreach ($tipler as $tip) {
            $tid = (int) $tip['id'];
            $tipHavuzHaritasi[$tid] = [
                'depo' => 0,
                'ekip' => 0,
                'saha' => 0,
                'hurda' => 0,
                'kayip' => 0,
            ];
        }

        $toplamEkipSayisi = 0;
        $zimmetliEkipSayisi = 0;
        $negatifEkipSayisi = 0;
        $ekipKritikHarita = [];
        foreach ($kritik['ekipler'] as $ek) {
            $ekipKritikHarita[(int) $ek['ekip_id']] = $ek['eksikler'];
        }

        $ekipSatirlari = [];
        foreach ($matris['satirlar'] as $satir) {
            $havuz = (string) $satir['sahip_tipi'];
            $satirAdet = 0;
            $satirNegatif = false;
            $tipDetay = [];

            foreach ($satir['adetler'] as $tid => $adet) {
                $tid = (int) $tid;
                $adet = (int) $adet;
                if ($aparatTipId > 0 && $tid !== $aparatTipId) {
                    continue;
                }
                $satirAdet += $adet;
                if (isset($tipHavuzHaritasi[$tid][$havuz])) {
                    $tipHavuzHaritasi[$tid][$havuz] += $adet;
                }
                if ($havuz === 'ekip') {
                    if ($adet < 0) {
                        $satirNegatif = true;
                    }
                    if ($adet !== 0) {
                        $tipDetay[$tid] = $adet;
                    }
                }
            }

            if (isset($havuzToplam[$havuz])) {
                $havuzToplam[$havuz] += $satirAdet;
            }

            if ($havuz === 'ekip') {
                $toplamEkipSayisi++;
                if ($satirAdet > 0) {
                    $zimmetliEkipSayisi++;
                }
                if ($satirNegatif) {
                    $negatifEkipSayisi++;
                }
                $ekipId = (int) $satir['sahip_id'];
                $ekipSatirlari[$ekipId] = [
                    'ekip_id' => $ekipId,
                    'ekip_kodu' => (string) $satir['ekip_kodu'],
                    'uyeler' => (string) $satir['uyeler'],
                    'bolge' => (string) ($satir['bolge'] ?? ''),
                    'toplam_stok' => $satirAdet,
                    'negatif' => $satirNegatif,
                    'kritik_sayisi' => isset($ekipKritikHarita[$ekipId]) ? count($ekipKritikHarita[$ekipId]) : 0,
                    'kritik_detay' => $ekipKritikHarita[$ekipId] ?? [],
                    'donem_kesme' => 0,
                    'donem_acma' => 0,
                    'donem_depo_alinan' => 0,
                    'tip_detay' => $tipDetay,
                ];
            }
        }

        $toplamEnvanter = $havuzToplam['depo'] + $havuzToplam['ekip'] + $havuzToplam['saha']
            + $havuzToplam['hurda'] + $havuzToplam['kayip'];

        // Dönemsel hareket metrikleri, tip ve ekip operasyon adetleri
        $donemVeri = $this->donemHareketAnalizi($donem['start_date'], $donem['end_date'], $aparatTipId);

        foreach ($donemVeri['ekip_operasyon'] as $ekipId => $op) {
            if (isset($ekipSatirlari[$ekipId])) {
                $ekipSatirlari[$ekipId]['donem_kesme'] = $op['kesme'];
                $ekipSatirlari[$ekipId]['donem_acma'] = $op['acma'];
                $ekipSatirlari[$ekipId]['donem_depo_alinan'] = $op['depo_cikis'];
            }
        }

        // Aparat tipi bazında kırılım listesi
        $depoKritikMap = [];
        foreach ($kritik['depo'] as $dk) {
            $depoKritikMap[(int) $dk['aparat_tip_id']] = (string) $dk['seviye'];
        }

        $tipKirilimi = [];
        foreach ($tipler as $tip) {
            $tid = (int) $tip['id'];
            if ($aparatTipId > 0 && $tid !== $aparatTipId) {
                continue;
            }
            $h = $tipHavuzHaritasi[$tid] ?? ['depo' => 0, 'ekip' => 0, 'saha' => 0, 'hurda' => 0, 'kayip' => 0];
            $tipToplam = $h['depo'] + $h['ekip'] + $h['saha'] + $h['hurda'] + $h['kayip'];
            if ((int) $tip['is_active'] === 0 && $tipToplam === 0) {
                continue;
            }
            $tipOp = $donemVeri['tip_operasyon'][$tid] ?? ['kesme' => 0, 'acma' => 0, 'depo_cikis' => 0];
            $tipKirilimi[] = [
                'id' => $tid,
                'ad' => (string) $tip['ad'],
                'kod' => (string) $tip['kod'],
                'renk' => (string) ($tip['renk'] ?? 'primary'),
                'is_active' => (int) $tip['is_active'],
                'depo' => $h['depo'],
                'ekip' => $h['ekip'],
                'saha' => $h['saha'],
                'hurda' => $h['hurda'],
                'kayip' => $h['kayip'],
                'hurda_kayip' => $h['hurda'] + $h['kayip'],
                'toplam' => $tipToplam,
                'depo_kritik_stok' => (int) ($tip['depo_kritik_stok'] ?? 0),
                'ekip_kritik_stok' => (int) ($tip['ekip_kritik_stok'] ?? 0),
                'depo_seviye' => $depoKritikMap[$tid] ?? 'normal',
                'donem_kesme' => $tipOp['kesme'],
                'donem_acma' => $tipOp['acma'],
                'donem_depo_cikis' => $tipOp['depo_cikis'],
            ];
        }

        // Ekip sıralaması: önce pozitif zimmet veya dönem hareketi olanlar, stok ve işlem hacmine göre
        $ekipSiralamasi = array_values(array_filter($ekipSatirlari, static function (array $e): bool {
            return $e['toplam_stok'] !== 0
                || $e['uyeler'] !== ''
                || $e['donem_kesme'] > 0
                || $e['donem_acma'] > 0
                || $e['kritik_sayisi'] > 0;
        }));

        usort($ekipSiralamasi, static function (array $a, array $b): int {
            $skorA = max(0, $a['toplam_stok']) + ($a['donem_kesme'] + $a['donem_acma']) * 2;
            $skorB = max(0, $b['toplam_stok']) + ($b['donem_kesme'] + $b['donem_acma']) * 2;
            if ($skorA !== $skorB) {
                return $skorB <=> $skorA;
            }
            return strcmp($a['ekip_kodu'], $b['ekip_kodu']);
        });

        // Zaman serisi (günlük / aylık)
        $trend = $this->trendSerisiOlustur(
            $donem['start_date'],
            $donem['end_date'],
            $donem['granularity'],
            $aparatTipId
        );

        // Uzun süredir abonede takılı aparatlar ve bekleyen transfer / açık sayım kontrolleri
        $sahadaTakililar = $stokModel->sahadaTakililar($aparatTipId > 0 ? $aparatTipId : null, 0);
        $uzunSureliSayisi = 0;
        foreach ($sahadaTakililar as $st) {
            if ((int) ($st['gun_sayisi'] ?? 0) >= 30) {
                $uzunSureliSayisi++;
            }
        }

        $bekleyenTransfer = $this->bekleyenTransferSayisi($aparatTipId);
        $acikSayim = $this->acikSayimOzeti($sayimModel);
        $tutarsizSayisi = $gelismisYetki ? count($hareketModel->tutarlilikKontrolu()) : 0;

        return [
            'period' => $donem,
            'inventory' => [
                'toplam_envanter' => $toplamEnvanter,
                'depo_adet' => $havuzToplam['depo'],
                'ekip_adet' => $havuzToplam['ekip'],
                'saha_adet' => $havuzToplam['saha'],
                'hurda_adet' => $havuzToplam['hurda'],
                'kayip_adet' => $havuzToplam['kayip'],
                'hurda_kayip_adet' => $havuzToplam['hurda'] + $havuzToplam['kayip'],
                'aktif_tip_sayisi' => count($aktifTipler),
                'zimmetli_ekip_sayisi' => $zimmetliEkipSayisi,
                'toplam_ekip_sayisi' => $toplamEkipSayisi,
                'negatif_ekip_sayisi' => $negatifEkipSayisi,
                'kritik_toplam' => (int) $kritik['toplam'],
                'kritik_depo_sayisi' => count($kritik['depo']),
                'kritik_ekip_sayisi' => count($kritik['ekipler']),
                'uzun_sureli_saha_sayisi' => $uzunSureliSayisi,
                'sahada_kayit_sayisi' => count($sahadaTakililar),
                'bekleyen_transfer_sayisi' => $bekleyenTransfer,
                'acik_sayim' => $acikSayim,
                'tutarsiz_satir_sayisi' => $tutarsizSayisi,
            ],
            'period_summary' => $donemVeri['ozet'],
            'pool_distribution' => [
                'labels' => ['Depoda', 'Ekiplerde', 'Abonede Takılı', 'Hurda', 'Kayıp'],
                'series' => [
                    max(0, $havuzToplam['depo']),
                    max(0, $havuzToplam['ekip']),
                    max(0, $havuzToplam['saha']),
                    max(0, $havuzToplam['hurda']),
                    max(0, $havuzToplam['kayip']),
                ],
                'raw' => $havuzToplam,
            ],
            'trend' => $trend,
            'type_breakdown' => $tipKirilimi,
            'team_ranking' => array_slice($ekipSiralamasi, 0, 15),
            'critical_alerts' => $kritik,
            'long_standing_field' => array_slice($sahadaTakililar, 0, 10),
            'recent_movements' => $this->sonHareketler($aparatTipId, 12),
        ];
    }

    /**
     * İptal edilmiş ve ters kayıt olarak yazılmış hareketleri dışlayan SQL koşulu.
     */
    private function aktifHareketKosulu(string $alias = 'h'): string
    {
        return "{$alias}.iptal_mi = 0
            AND NOT EXISTS (
                SELECT 1 FROM aparat_hareket rev
                WHERE rev.firma_id = {$alias}.firma_id
                  AND rev.ters_hareket_id IS NOT NULL
                  AND {$alias}.referans_id IS NOT NULL
                  AND rev.ters_hareket_id = {$alias}.referans_id
            )";
    }

    /**
     * Çift taraflı defter yapısında her olayı tek bacağından sayarak dönemsel özet çıkarır.
     */
    private function donemHareketAnalizi(string $baslangic, string $bitis, int $aparatTipId = 0): array
    {
        $kosul = "h.firma_id = ? AND h.tarih >= ? AND h.tarih <= ? AND " . $this->aktifHareketKosulu('h');
        $params = [$this->firmaId(), $baslangic . ' 00:00:00', $bitis . ' 23:59:59'];

        if ($aparatTipId > 0) {
            $kosul .= " AND h.aparat_tip_id = ?";
            $params[] = $aparatTipId;
        }

        $sql = "SELECT h.hareket_tipi, h.sahip_tipi, h.sahip_id, h.ekip_id, h.aparat_tip_id,
                       COUNT(*) AS satir_sayisi,
                       COALESCE(SUM(h.adet), 0) AS toplam_adet,
                       COALESCE(SUM(ABS(h.adet)), 0) AS mutlak_adet
                FROM aparat_hareket h
                WHERE {$kosul}
                GROUP BY h.hareket_tipi, h.sahip_tipi, h.sahip_id, h.ekip_id, h.aparat_tip_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $ozet = [
            'kesme_adet' => 0,
            'acma_adet' => 0,
            'net_saha_degisim' => 0,
            'geri_donus_orani' => 0.0,
            'depo_giris_adet' => 0,
            'depo_cikis_adet' => 0,
            'depo_iade_adet' => 0,
            'transfer_adet' => 0,
            'hurda_adet' => 0,
            'kayip_adet' => 0,
            'acilis_adet' => 0,
            'olay_sayisi' => 0,
        ];

        $ekipOperasyon = [];
        $tipOperasyon = [];

        $ekipEkle = static function (array &$harita, int $ekipId, string $alan, int $deger): void {
            if ($ekipId <= 0 || $deger <= 0) {
                return;
            }
            if (!isset($harita[$ekipId])) {
                $harita[$ekipId] = ['kesme' => 0, 'acma' => 0, 'depo_cikis' => 0];
            }
            $harita[$ekipId][$alan] += $deger;
        };

        $tipEkle = static function (array &$harita, int $tipId, string $alan, int $deger): void {
            if ($tipId <= 0 || $deger <= 0) {
                return;
            }
            if (!isset($harita[$tipId])) {
                $harita[$tipId] = ['kesme' => 0, 'acma' => 0, 'depo_cikis' => 0];
            }
            $harita[$tipId][$alan] += $deger;
        };

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ht = (string) $r['hareket_tipi'];
            $st = (string) $r['sahip_tipi'];
            $toplam = (int) $r['toplam_adet'];
            $mutlak = (int) $r['mutlak_adet'];
            $satirSayi = (int) $r['satir_sayisi'];
            $ekipId = (int) ($r['ekip_id'] ?: $r['sahip_id']);
            $tipId = (int) $r['aparat_tip_id'];

            if ($ht === 'kesme' && $st === 'saha' && $toplam > 0) {
                $ozet['kesme_adet'] += $toplam;
                $ozet['olay_sayisi'] += $satirSayi;
                $ekipEkle($ekipOperasyon, $ekipId, 'kesme', $toplam);
                $tipEkle($tipOperasyon, $tipId, 'kesme', $toplam);
            } elseif ($ht === 'acma' && $st === 'saha' && $toplam < 0) {
                $ozet['acma_adet'] += $mutlak;
                $ozet['olay_sayisi'] += $satirSayi;
                $ekipEkle($ekipOperasyon, $ekipId, 'acma', $mutlak);
                $tipEkle($tipOperasyon, $tipId, 'acma', $mutlak);
            } elseif ($ht === 'depo_giris' && $st === 'depo' && $toplam > 0) {
                $ozet['depo_giris_adet'] += $toplam;
                $ozet['olay_sayisi'] += $satirSayi;
            } elseif ($ht === 'depo_cikis' && $st === 'ekip' && $toplam > 0) {
                $ozet['depo_cikis_adet'] += $toplam;
                $ozet['olay_sayisi'] += $satirSayi;
                $ekipEkle($ekipOperasyon, (int) $r['sahip_id'], 'depo_cikis', $toplam);
                $tipEkle($tipOperasyon, $tipId, 'depo_cikis', $toplam);
            } elseif ($ht === 'depo_iade' && $st === 'depo' && $toplam > 0) {
                $ozet['depo_iade_adet'] += $toplam;
                $ozet['olay_sayisi'] += $satirSayi;
            } elseif ($ht === 'transfer' && $st === 'ekip' && $toplam > 0) {
                // Tek satırda int_max test kaydı varsa makul sınır kontrolü yapmadan gerçek adedi koru
                $ozet['transfer_adet'] += $toplam;
                $ozet['olay_sayisi'] += $satirSayi;
            } elseif ($st === 'hurda' && $toplam > 0) {
                $ozet['hurda_adet'] += $toplam;
                if ($ht === 'hurda') {
                    $ozet['olay_sayisi'] += $satirSayi;
                }
            } elseif ($st === 'kayip' && $toplam > 0) {
                $ozet['kayip_adet'] += $toplam;
                if ($ht === 'kayip') {
                    $ozet['olay_sayisi'] += $satirSayi;
                }
            } elseif ($ht === 'acilis' && $st === 'ekip' && $toplam > 0) {
                $ozet['acilis_adet'] += $toplam;
                $ozet['olay_sayisi'] += $satirSayi;
            }
        }

        $ozet['net_saha_degisim'] = $ozet['kesme_adet'] - $ozet['acma_adet'];
        $ozet['geri_donus_orani'] = $ozet['kesme_adet'] > 0
            ? round(($ozet['acma_adet'] / $ozet['kesme_adet']) * 100, 1)
            : 0.0;

        return [
            'ozet' => $ozet,
            'ekip_operasyon' => $ekipOperasyon,
            'tip_operasyon' => $tipOperasyon,
        ];
    }

    /**
     * Günlük veya aylık zaman serisi üretir.
     */
    private function trendSerisiOlustur(string $baslangic, string $bitis, string $kirilim, int $aparatTipId = 0): array
    {
        $zamanIfadesi = $kirilim === 'monthly'
            ? "DATE_FORMAT(h.tarih, '%Y-%m')"
            : "DATE(h.tarih)";

        $kosul = "h.firma_id = ? AND h.tarih >= ? AND h.tarih <= ? AND " . $this->aktifHareketKosulu('h');
        $params = [$this->firmaId(), $baslangic . ' 00:00:00', $bitis . ' 23:59:59'];

        if ($aparatTipId > 0) {
            $kosul .= " AND h.aparat_tip_id = ?";
            $params[] = $aparatTipId;
        }

        $sql = "SELECT {$zamanIfadesi} AS donem_key,
                       COALESCE(SUM(CASE WHEN h.hareket_tipi = 'kesme' AND h.sahip_tipi = 'saha' AND h.adet > 0 THEN h.adet ELSE 0 END), 0) AS kesme,
                       COALESCE(SUM(CASE WHEN h.hareket_tipi = 'acma' AND h.sahip_tipi = 'saha' AND h.adet < 0 THEN ABS(h.adet) ELSE 0 END), 0) AS acma,
                       COALESCE(SUM(CASE WHEN h.hareket_tipi = 'depo_cikis' AND h.sahip_tipi = 'ekip' AND h.adet > 0 THEN h.adet ELSE 0 END), 0) AS depo_cikis,
                       COALESCE(SUM(CASE WHEN h.hareket_tipi = 'depo_iade' AND h.sahip_tipi = 'depo' AND h.adet > 0 THEN h.adet ELSE 0 END), 0) AS depo_iade
                FROM aparat_hareket h
                WHERE {$kosul}
                GROUP BY donem_key
                ORDER BY donem_key ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $veriHarita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $veriHarita[(string) $r['donem_key']] = [
                'kesme' => (int) $r['kesme'],
                'acma' => (int) $r['acma'],
                'depo_cikis' => (int) $r['depo_cikis'],
                'depo_iade' => (int) $r['depo_iade'],
            ];
        }

        $etiketler = [];
        $kesmeSeri = [];
        $acmaSeri = [];
        $depoCikisSeri = [];
        $depoIadeSeri = [];

        if ($kirilim === 'monthly') {
            $cur = new DateTimeImmutable(substr($baslangic, 0, 7) . '-01');
            $son = new DateTimeImmutable(substr($bitis, 0, 7) . '-01');
            while ($cur <= $son) {
                $anahtar = $cur->format('Y-m');
                $ay = (int) $cur->format('n');
                $etiketler[] = (self::AY_ADLARI[$ay] ?? $cur->format('m')) . ' ' . $cur->format('y');
                $v = $veriHarita[$anahtar] ?? ['kesme' => 0, 'acma' => 0, 'depo_cikis' => 0, 'depo_iade' => 0];
                $kesmeSeri[] = $v['kesme'];
                $acmaSeri[] = $v['acma'];
                $depoCikisSeri[] = $v['depo_cikis'];
                $depoIadeSeri[] = $v['depo_iade'];
                $cur = $cur->modify('+1 month');
            }
        } else {
            $cur = new DateTimeImmutable($baslangic);
            $son = new DateTimeImmutable($bitis);
            while ($cur <= $son) {
                $anahtar = $cur->format('Y-m-d');
                $etiketler[] = $cur->format('d.m');
                $v = $veriHarita[$anahtar] ?? ['kesme' => 0, 'acma' => 0, 'depo_cikis' => 0, 'depo_iade' => 0];
                $kesmeSeri[] = $v['kesme'];
                $acmaSeri[] = $v['acma'];
                $depoCikisSeri[] = $v['depo_cikis'];
                $depoIadeSeri[] = $v['depo_iade'];
                $cur = $cur->modify('+1 day');
            }
        }

        return [
            'granularity' => $kirilim,
            'categories' => $etiketler,
            'kesme' => $kesmeSeri,
            'acma' => $acmaSeri,
            'depo_cikis' => $depoCikisSeri,
            'depo_iade' => $depoIadeSeri,
        ];
    }

    private function bekleyenTransferSayisi(int $aparatTipId = 0): int
    {
        $sql = "SELECT COUNT(*) FROM aparat_transfer
                WHERE firma_id = ? AND durum = 'beklemede' AND silinme_tarihi IS NULL";
        $params = [$this->firmaId()];
        if ($aparatTipId > 0) {
            $sql .= " AND aparat_tip_id = ?";
            $params[] = $aparatTipId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function acikSayimOzeti(AparatSayimModel $sayimModel): ?array
    {
        $acik = $sayimModel->acikSayim();
        if (!$acik) {
            return null;
        }
        $sayimId = (int) $acik['id'];
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS toplam,
                    COALESCE(SUM(CASE WHEN sayilan_adet IS NOT NULL THEN 1 ELSE 0 END), 0) AS girilen
             FROM aparat_sayim_detay
             WHERE sayim_id = ? AND firma_id = ?"
        );
        $stmt->execute([$sayimId, $this->firmaId()]);
        $ozet = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['toplam' => 0, 'girilen' => 0];
        $toplam = (int) $ozet['toplam'];
        $girilen = (int) $ozet['girilen'];

        return [
            'id' => $sayimId,
            'baslik' => (string) $acik['baslik'],
            'baslangic_tarihi' => (string) $acik['baslangic_tarihi'],
            'toplam_satir' => $toplam,
            'girilen_satir' => $girilen,
            'oran' => $toplam > 0 ? round(($girilen / $toplam) * 100, 1) : 0.0,
        ];
    }

    /**
     * Çift taraflı defter satırlarını tek olay olarak birleştirip son hareketleri döner.
     */
    private function sonHareketler(int $aparatTipId = 0, int $limit = 12): array
    {
        $stokModel = new AparatStokModel();
        $uyeHarita = $stokModel->ekipUyeHaritasi();

        $kosul = "h.firma_id = ? AND " . $this->aktifHareketKosulu('h') . "
            AND (
                (h.hareket_tipi = 'depo_giris' AND h.sahip_tipi = 'depo')
                OR (h.hareket_tipi = 'depo_cikis' AND h.sahip_tipi = 'ekip')
                OR (h.hareket_tipi = 'depo_iade' AND h.sahip_tipi = 'depo')
                OR (h.hareket_tipi = 'kesme' AND h.sahip_tipi = 'saha')
                OR (h.hareket_tipi = 'acma' AND h.sahip_tipi = 'saha')
                OR (h.hareket_tipi = 'transfer' AND h.sahip_tipi = 'ekip' AND h.adet > 0)
                OR (h.hareket_tipi = 'hurda' AND h.sahip_tipi = 'hurda')
                OR (h.hareket_tipi = 'kayip' AND h.sahip_tipi = 'kayip')
                OR (h.hareket_tipi = 'acilis' AND h.sahip_tipi = 'ekip')
                OR (h.hareket_tipi = 'sayim_duzeltme' AND h.sahip_tipi = 'ekip')
            )";

        $params = [$this->firmaId()];
        if ($aparatTipId > 0) {
            $kosul .= " AND h.aparat_tip_id = ?";
            $params[] = $aparatTipId;
        }

        $limit = max(1, min(50, $limit));
        $sql = "SELECT h.id, h.tarih, h.hareket_tipi, h.sahip_tipi, h.sahip_id, h.ekip_id,
                       h.adet, h.aciklama, t.ad AS aparat_adi, t.kod AS aparat_kod, t.renk AS aparat_renk,
                       ek.tur_adi AS ekip_kodu, p.adi_soyadi AS personel_adi, u.adi_soyadi AS kullanici_adi
                FROM aparat_hareket h
                LEFT JOIN aparat_tipleri t ON t.id = h.aparat_tip_id
                LEFT JOIN tanimlamalar ek ON ek.id = COALESCE(NULLIF(h.ekip_id, 0), NULLIF(h.sahip_id, 0))
                LEFT JOIN personel p ON p.id = h.personel_id
                LEFT JOIN users u ON u.id = h.kaydeden_id
                WHERE {$kosul}
                ORDER BY h.id DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $etiketHarita = [
            'depo_giris' => ['ad' => 'Depoya Giriş', 'renk' => 'success', 'ikon' => 'bx-log-in-circle'],
            'depo_cikis' => ['ad' => 'Ekibe Verildi', 'renk' => 'primary', 'ikon' => 'bx-export'],
            'depo_iade' => ['ad' => 'İade Alındı', 'renk' => 'info', 'ikon' => 'bx-import'],
            'kesme' => ['ad' => 'Saha Kesme', 'renk' => 'warning', 'ikon' => 'bx-cut'],
            'acma' => ['ad' => 'Saha Açma', 'renk' => 'success', 'ikon' => 'bx-check-circle'],
            'transfer' => ['ad' => 'Ekip Transferi', 'renk' => 'secondary', 'ikon' => 'bx-git-compare'],
            'hurda' => ['ad' => 'Hurdaya Ayrıldı', 'renk' => 'danger', 'ikon' => 'bx-trash'],
            'kayip' => ['ad' => 'Kayıp Kaydı', 'renk' => 'danger', 'ikon' => 'bx-error'],
            'acilis' => ['ad' => 'Açılış Stoğu', 'renk' => 'primary', 'ikon' => 'bx-box'],
            'sayim_duzeltme' => ['ad' => 'Sayım Farkı', 'renk' => 'dark', 'ikon' => 'bx-check-square'],
        ];

        $sonuc = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ht = (string) $r['hareket_tipi'];
            $meta = $etiketHarita[$ht] ?? ['ad' => $ht, 'renk' => 'secondary', 'ikon' => 'bx-transfer'];
            $ekipId = (int) ($r['ekip_id'] ?: ($r['sahip_tipi'] === 'ekip' ? $r['sahip_id'] : 0));
            $uyeler = $ekipId > 0 ? ($uyeHarita[$ekipId] ?? '') : '';
            $ekipKodu = (string) ($r['ekip_kodu'] ?? '');
            $ekipGosterim = $uyeler !== ''
                ? ($ekipKodu !== '' ? "{$uyeler} ({$ekipKodu})" : $uyeler)
                : $ekipKodu;

            $sonuc[] = [
                'id' => (int) $r['id'],
                'tarih' => (string) $r['tarih'],
                'hareket_tipi' => $ht,
                'hareket_adi' => $meta['ad'],
                'hareket_renk' => $meta['renk'],
                'hareket_ikon' => $meta['ikon'],
                'aparat_adi' => (string) ($r['aparat_adi'] ?? '-'),
                'aparat_kod' => (string) ($r['aparat_kod'] ?? ''),
                'aparat_renk' => (string) ($r['aparat_renk'] ?? 'primary'),
                'adet' => $ht === 'sayim_duzeltme' ? (int) $r['adet'] : abs((int) $r['adet']),
                'isaretli_mi' => $ht === 'sayim_duzeltme',
                'ekip' => $ekipGosterim,
                'islem_yapan' => (string) ($r['personel_adi'] ?: ($r['kullanici_adi'] ?? '')),
                'aciklama' => (string) ($r['aciklama'] ?? ''),
            ];
        }

        return $sonuc;
    }
}
