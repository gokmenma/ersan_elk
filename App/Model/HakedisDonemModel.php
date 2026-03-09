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

        $relevantDonemIds = array_filter([$hakedisId, $prevHakedisId]);
        $miktarlarMap = [];
        if (!empty($relevantDonemIds)) {
            $placeholders = implode(',', array_fill(0, count($relevantDonemIds), '?'));
            $stmtMiktar = $db->prepare("SELECT * FROM hakedis_miktarlari WHERE hakedis_donem_id IN ($placeholders)");
            $stmtMiktar->execute(array_values($relevantDonemIds));
            while ($m = $stmtMiktar->fetch(\PDO::FETCH_ASSOC)) {
                $miktarlarMap[$m['hakedis_donem_id']][$m['kalem_id']] = $m;
            }
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
                $oncekiMiktar = floatval($curM['onceki_miktar']);
            } else if ($prevHakedisId) {
                $prevM = $miktarlarMap[$prevHakedisId][$kalemId] ?? null;
                if ($prevM) {
                    $oncekiMiktar = floatval($prevM['onceki_miktar'] ?? 0) + floatval($prevM['miktar'] ?? 0);
                }
            }
            
            $totalCumulativeImalat += ($oncekiMiktar + $buAyMiktar) * $birimFiyat;
            $totalPeriodImalat += $buAyMiktar * $birimFiyat;
        }

        // 4. Fiyat Farkı Hesapla
        $fiyatFarki = 0;
        $pn = 0;
        if (floatval($hakedis['asgari_ucret_temel']) > 0) {
            $pn += floatval($hakedis['a1_katsayisi'] ?: 0.28) * (floatval($hakedis['asgari_ucret_guncel']) / floatval($hakedis['asgari_ucret_temel']));
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
            $fiyatFarki = $totalPeriodImalat * 0.90 * ($pn - 1);
        }

        $araToplam = $totalCumulativeImalat + $fiyatFarki;
        $kdvTutar = ($araToplam * $kdvRate) / 100;
        $genelToplam = $araToplam + $kdvTutar;

        return [
            'imalat_kumulatif' => $totalCumulativeImalat,
            'imalat_donem' => $totalPeriodImalat,
            'fiyat_farki' => $fiyatFarki,
            'kdv_dahil_toplam' => $genelToplam,
            'kdv_orani' => $kdvRate
        ];
    }
}
