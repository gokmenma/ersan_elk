<?php

namespace App\Model;


use App\Model\Model;
use App\Helper\Helper;
class HakedisDonemModel extends Model
{
    protected $table = 'hakedis_donemleri';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function calculateTotals($hakedisId)
    {
        $db = $this->getDb();

        // 1. Hakediş ve Sözleşme verilerini çek
        $stmt = $db->prepare("
            SELECT d.*, s.kdv_orani as s_kdv
            FROM hakedis_donemleri d
            JOIN hakedis_sozlesmeler s ON d.sozlesme_id = s.id
            WHERE d.id = ?
        ");
        $stmt->execute([$hakedisId]);
        $hakedis = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$hakedis) return null;

        $sozlesmeId = $hakedis['sozlesme_id'];
        $hNo = $hakedis['hakedis_no'];
        $kdvRate = floatval($hakedis['kdv_orani'] ?: $hakedis['s_kdv'] ?: 20);

        // 2. Bir önceki hakedişi bul
        $stmtPrev = $db->prepare("SELECT id FROM hakedis_donemleri WHERE sozlesme_id = ? AND hakedis_no < ? AND silinme_tarihi IS NULL ORDER BY hakedis_no DESC LIMIT 1");
        $stmtPrev->execute([$sozlesmeId, $hNo]);
        $prevHakedisId = $stmtPrev->fetchColumn();

        // 3. Kalemleri ve Miktarları çek
        $stmtKalem = $db->prepare("SELECT id, teklif_edilen_birim_fiyat FROM hakedis_kalemleri WHERE sozlesme_id = ?");
        $stmtKalem->execute([$sozlesmeId]);
        $kalemler = $stmtKalem->fetchAll(\PDO::FETCH_ASSOC);

        $relevantDonemIds = array_filter([$hakedisId]);
        $miktarlarMap = [];
        if (!empty($relevantDonemIds)) {
            $placeholders = implode(',', array_fill(0, count($relevantDonemIds), '?'));
            $stmtMiktar = $db->prepare("SELECT * FROM hakedis_miktarlari WHERE hakedis_donem_id IN ($placeholders)");
            $stmtMiktar->execute(array_values($relevantDonemIds));
            while ($m = $stmtMiktar->fetch(\PDO::FETCH_ASSOC)) {
                $miktarlarMap[$m['hakedis_donem_id']][$m['kalem_id']] = $m;
            }
        }

        // 4. Tüm önceki miktarları kalem bazlı topla (Kümülatif doğruluk için)
        $prevMiktarlarSum = [];
        $stmtPrevSum = $db->prepare("
            SELECT m.kalem_id, SUM(m.miktar) as toplam_prev
            FROM hakedis_miktarlari m
            JOIN hakedis_donemleri d ON m.hakedis_donem_id = d.id
            WHERE d.sozlesme_id = ? AND d.hakedis_no < ? AND d.silinme_tarihi IS NULL
            GROUP BY m.kalem_id
        ");
        $stmtPrevSum->execute([$sozlesmeId, $hNo]);
        while ($row = $stmtPrevSum->fetch(\PDO::FETCH_ASSOC)) {
            $prevMiktarlarSum[$row['kalem_id']] = floatval($row['toplam_prev']);
        }

        // 5. İlk hakedişteki (hno=1) başlangıç 'onceki_miktar' değerlerini al
        $baslangicMiktarlari = [];
        $stmtBaslangic = $db->prepare("
            SELECT m.kalem_id, m.onceki_miktar
            FROM hakedis_miktarlari m
            JOIN hakedis_donemleri d ON m.hakedis_donem_id = d.id
            WHERE d.sozlesme_id = ? AND d.hakedis_no = 1 AND d.silinme_tarihi IS NULL
        ");
        $stmtBaslangic->execute([$sozlesmeId]);
        while ($row = $stmtBaslangic->fetch(\PDO::FETCH_ASSOC)) {
            $baslangicMiktarlari[$row['kalem_id']] = floatval($row['onceki_miktar']);
        }

        $totalCumulativeImalat = 0;
        $totalPeriodImalat = 0;
        foreach ($kalemler as $k) {
            $kalemId = $k['id'];
            $birimFiyat = floatval($k['teklif_edilen_birim_fiyat']);
            
            $curM = $miktarlarMap[$hakedisId][$kalemId] ?? null;
            $buAyMiktar = floatval($curM['miktar'] ?? 0);
            
            $oncekiMiktar = 0;
            if ($curM && isset($curM['onceki_miktar']) && $curM['onceki_miktar'] != 0) {
                // Eğer bu dönem için manuel bir önceki miktar girildiyse onu kullan
                $oncekiMiktar = floatval($curM['onceki_miktar']);
            } else {
                // Değilse; (tüm önceki dönemlerin toplamı) + (en baştaki başlangıç miktarı)
                $prevSum = $prevMiktarlarSum[$kalemId] ?? 0;
                $baslangic = $baslangicMiktarlari[$kalemId] ?? 0;
                $oncekiMiktar = $prevSum + $baslangic;
            }
            
            $totalCumulativeImalat += round(($oncekiMiktar + $buAyMiktar) * $birimFiyat, 2);
            $totalPeriodImalat += round($buAyMiktar * $birimFiyat, 2);
        }

        // 4. Fiyat Farkı Hesapla
        $fiyatFarki = 0;
        $pn = 0;
        // İşçilik katsayısı (a1) hesaplaması: Eğer 'asgari_farki_dahil_edilsin' 1 ise güncel/temel oranla çarpılır, değilse sabit kalır.
        if (floatval($hakedis['asgari_ucret_temel']) > 0 || floatval($hakedis['a1_katsayisi']) > 0) {
            $a1 = floatval($hakedis['a1_katsayisi'] ?: 0.28);
            if (isset($hakedis['asgari_farki_dahil_edilsin']) && $hakedis['asgari_farki_dahil_edilsin'] == 1 && floatval($hakedis['asgari_ucret_temel']) > 0) {
                $pn += $a1 * (floatval($hakedis['asgari_ucret_guncel']) / floatval($hakedis['asgari_ucret_temel']));
            } else {
                $pn += $a1;
            }
        }
        if (floatval($hakedis['motorin_temel']) > 0) {
            $pn += floatval($hakedis['b1_katsayisi'] ?: 0.22) * (floatval($hakedis['motorin_guncel']) / floatval($hakedis['motorin_temel']));
        }
        if (floatval($hakedis['ufe_genel_temel']) > 0) {
            $pn += floatval($hakedis['b2_katsayisi'] ?: 0.25) * (floatval($hakedis['ufe_genel_guncel']) / floatval($hakedis['ufe_genel_temel']));
        }
        if (floatval($hakedis['makine_ekipman_temel']) > 0) {
            $pn += floatval($hakedis['c_katsayisi'] ?: 0.25) * (floatval($hakedis['makine_ekipman_guncel']) / floatval($hakedis['makine_ekipman_temel']));
        }

        if ($pn > 1) {
            // Fiyat Farkı = Tutar * 0.90 * (Pn - 1)
            // User clarified that calculation should be on current period manufacture
            $fiyatFarki = round($totalPeriodImalat * 0.90 * ($pn - 1), 2);
        }

        $araToplam = round($totalCumulativeImalat + $fiyatFarki, 2);
        $kdvTutar = round(($araToplam * $kdvRate) / 100, 2);
        $genelToplam = round($araToplam + $kdvTutar, 2);

        return [
            'imalat_kumulatif' => round($totalCumulativeImalat, 2),
            'imalat_donem' => round($totalPeriodImalat, 2),
            'fiyat_farki' => round($fiyatFarki, 2),
            'kdv_dahil_toplam' => round($genelToplam, 2),
            'kdv_orani' => $kdvRate
        ];
    }

    public function ozet(int $sozlesmeId): array
    {
        $db = $this->getDb();
        $stmt = $db->prepare("SELECT firma_id FROM hakedis_sozlesmeler WHERE id = :id");
        $stmt->execute([':id' => $sozlesmeId]);
        $firmaId = (int) $stmt->fetchColumn();

        return $this->ozetToplu($firmaId, [$sozlesmeId])[$sozlesmeId] ?? $this->bosOzet();
    }

    public function bosOzet(): array
    {
        return [
            'tamamlanan_imalat' => 0.0, 'tamamlanan_ff' => 0.0, 'tamamlanan_adet' => 0,
            'taslak_imalat' => 0.0, 'taslak_ff' => 0.0, 'taslak_adet' => 0,
            'is_artis_tutari' => 0.0, 'is_artis_adet' => 0,
        ];
    }

    public function ozetToplu(int $firmaId, ?array $sozlesmeIds = null): array
    {
        $db = $this->getDb();
        $sonuc = [];

        $filtre = '';
        $params = [':firma_id' => $firmaId];
        if ($sozlesmeIds !== null) {
            if (!$sozlesmeIds) {
                return [];
            }
            $ph = [];
            foreach (array_values($sozlesmeIds) as $i => $id) {
                $ph[] = ":sid{$i}";
                $params[":sid{$i}"] = (int) $id;
            }
            $filtre = ' AND s.id IN (' . implode(',', $ph) . ')';
        }

        $stmt = $db->prepare("SELECT hd.*,
            (SELECT SUM(m.miktar * k.teklif_edilen_birim_fiyat)
             FROM hakedis_miktarlari m
             JOIN hakedis_kalemleri k ON m.kalem_id = k.id
             WHERE m.hakedis_donem_id = hd.id) as imalat_donem
            FROM hakedis_donemleri hd
            JOIN hakedis_sozlesmeler s ON s.id = hd.sozlesme_id
            WHERE s.firma_id = :firma_id AND s.silinme_tarihi IS NULL AND hd.silinme_tarihi IS NULL{$filtre}");
        $stmt->execute($params);

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $sid = (int) $row['sozlesme_id'];
            $sonuc[$sid] = $sonuc[$sid] ?? $this->bosOzet();
            $imalat = floatval($row['imalat_donem'] ?? 0);
            $pn = 0;
            if (floatval($row['asgari_ucret_temel']) > 0 || floatval($row['a1_katsayisi']) > 0) {
                $a1 = floatval($row['a1_katsayisi'] ?: 0.28);
                if (!empty($row['asgari_farki_dahil_edilsin']) && floatval($row['asgari_ucret_temel']) > 0) {
                    $pn += $a1 * (floatval($row['asgari_ucret_guncel']) / floatval($row['asgari_ucret_temel']));
                } else {
                    $pn += $a1;
                }
            }
            if (floatval($row['motorin_temel']) > 0) {
                $pn += floatval($row['b1_katsayisi'] ?: 0.22) * (floatval($row['motorin_guncel']) / floatval($row['motorin_temel']));
            }
            if (floatval($row['ufe_genel_temel']) > 0) {
                $pn += floatval($row['b2_katsayisi'] ?: 0.25) * (floatval($row['ufe_genel_guncel']) / floatval($row['ufe_genel_temel']));
            }
            if (floatval($row['makine_ekipman_temel']) > 0) {
                $pn += floatval($row['c_katsayisi'] ?: 0.25) * (floatval($row['makine_ekipman_guncel']) / floatval($row['makine_ekipman_temel']));
            }
            $ff = $pn > 1 ? $imalat * 0.90 * ($pn - 1) : 0.0;

            if ($row['durum'] === 'tamamlandi') {
                $sonuc[$sid]['tamamlanan_imalat'] += $imalat;
                $sonuc[$sid]['tamamlanan_ff'] += $ff;
                $sonuc[$sid]['tamamlanan_adet']++;
            } elseif ($row['durum'] === 'taslak') {
                $sonuc[$sid]['taslak_imalat'] += $imalat;
                $sonuc[$sid]['taslak_ff'] += $ff;
                $sonuc[$sid]['taslak_adet']++;
            }
        }

        try {
            $stmt = $db->prepare("SELECT r.sozlesme_id, COALESCE(SUM(d.degisim_miktari * d.birim_fiyat), 0) AS tutar, COUNT(DISTINCT r.id) AS adet
                FROM hakedis_is_revizyonlari r
                JOIN hakedis_sozlesmeler s ON s.id = r.sozlesme_id
                LEFT JOIN hakedis_is_revizyon_kalemleri d ON d.revizyon_id = r.id
                WHERE s.firma_id = :firma_id AND s.silinme_tarihi IS NULL{$filtre}
                GROUP BY r.sozlesme_id");
            $stmt->execute($params);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $artis) {
                $sid = (int) $artis['sozlesme_id'];
                $sonuc[$sid] = $sonuc[$sid] ?? $this->bosOzet();
                $sonuc[$sid]['is_artis_tutari'] = floatval($artis['tutar']);
                $sonuc[$sid]['is_artis_adet'] = intval($artis['adet']);
            }
        } catch (\Throwable $e) {
            error_log('HakedisDonemModel::ozetToplu is artis: ' . $e->getMessage());
        }

        return $sonuc;
    }

    public function firmaToplami(int $firmaId): array
    {
        $toplam = $this->bosOzet();
        foreach ($this->ozetToplu($firmaId) as $ozet) {
            foreach ($toplam as $k => $v) {
                $toplam[$k] += $ozet[$k];
            }
        }

        return $toplam;
    }
}
