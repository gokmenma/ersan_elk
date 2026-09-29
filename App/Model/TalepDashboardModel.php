<?php

namespace App\Model;

use PDO;

class TalepDashboardModel extends Model
{
    public function __construct()
    {
        parent::__construct('personel_talepleri');
    }

    public function getYoneticiOzeti(bool $canAvans, bool $canIzin, bool $canTalep): array
    {
        $parts = [];
        $params = [];
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $periodStart = date('Y-m-01', strtotime('-5 months'));

        if ($canAvans) {
            $partParams = [$firmaId, $periodStart];
            $deptSql = $this->getRestrictedDeptSql('p.departman', $partParams);
            $parts[] = "SELECT 'Avans' AS tip, pa.talep_tarihi AS talep_tarihi, pa.onay_tarihi AS sonuc_tarihi,
                              pa.durum AS durum, p.departman, pa.tutar AS tutar, p.adi_soyadi AS personel,
                              'Avans Talebi' AS baslik, pa.aciklama, 'Finansal' AS kategori, NULL AS oncelik
                       FROM personel_avanslari pa
                       INNER JOIN personel p ON p.id = pa.personel_id
                       WHERE p.firma_id = ? AND pa.talep_tarihi >= ?
                         AND pa.silinme_tarihi IS NULL AND p.silinme_tarihi IS NULL {$deptSql}";
            $params = array_merge($params, $partParams);
        }

        if ($canIzin) {
            $partParams = [$firmaId, $periodStart];
            $deptSql = $this->getRestrictedDeptSql('p.departman', $partParams);
            $parts[] = "SELECT 'İzin' AS tip, pi.talep_tarihi AS talep_tarihi,
                              (SELECT MAX(io.onay_tarihi) FROM izin_onaylari io WHERE io.izin_id = pi.id) AS sonuc_tarihi,
                              pi.onay_durumu AS durum, p.departman, 0 AS tutar, p.adi_soyadi AS personel,
                              COALESCE(t.tur_adi, 'İzin Talebi') AS baslik, pi.aciklama,
                              COALESCE(t.tur_adi, 'İzin') AS kategori, NULL AS oncelik
                       FROM personel_izinleri pi
                       INNER JOIN personel p ON p.id = pi.personel_id
                       LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
                       WHERE p.firma_id = ? AND pi.talep_tarihi >= ?
                         AND pi.silinme_tarihi IS NULL AND p.silinme_tarihi IS NULL {$deptSql}
                         AND (t.kisa_kod IS NULL OR (t.kisa_kod NOT IN ('X', 'x')
                         AND (t.normal_mesai_sayilir IS NULL OR t.normal_mesai_sayilir = 0)))";
            $params = array_merge($params, $partParams);
        }

        if ($canTalep) {
            $partParams = [$firmaId, $periodStart];
            $deptSql = $this->getRestrictedDeptSql('p.departman', $partParams);
            $parts[] = "SELECT 'Talep' AS tip, pt.olusturma_tarihi AS talep_tarihi, pt.cozum_tarihi AS sonuc_tarihi,
                              pt.durum, p.departman, 0 AS tutar, p.adi_soyadi AS personel,
                              pt.baslik, pt.aciklama, COALESCE(pt.kategori, 'Genel') AS kategori, pt.oncelik
                       FROM personel_talepleri pt
                       INNER JOIN personel p ON p.id = pt.personel_id
                       WHERE p.firma_id = ? AND pt.olusturma_tarihi >= ?
                         AND pt.silinme_tarihi IS NULL AND p.silinme_tarihi IS NULL {$deptSql}
                         AND (pt.kategori IS NULL OR pt.kategori != 'nobet_talebi')";
            $params = array_merge($params, $partParams);
        }

        $rows = [];
        if ($parts) {
            $query = $this->db->prepare(implode(' UNION ALL ', $parts));
            $query->execute($params);
            $rows = $query->fetchAll(PDO::FETCH_OBJ);
        }

        return $this->buildSummary($rows);
    }

    private function buildSummary(array $rows): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = date('Y-m', strtotime("-{$i} months"));
            $months[$key] = ['label' => date('m/Y', strtotime($key . '-01')), 'Avans' => 0, 'İzin' => 0, 'Talep' => 0];
        }

        $types = ['Avans' => 0, 'İzin' => 0, 'Talep' => 0];
        $departments = [];
        $pending = 0;
        $completed = 0;
        $approved = 0;
        $resolutionHours = [];
        $oldestPendingDays = 0;
        $pendingAdvanceAmount = 0.0;
        $requesters = [];
        $themes = [];
        $complaintSignals = [];
        $categories = [];
        $highPriority = 0;

        $themeDictionary = [
            'Araç / Ulaşım' => ['araç', 'araba', 'servis', 'lastik', 'balata', 'motor', 'yakıt', 'ulaşım'],
            'Arıza / Bakım' => ['arıza', 'bozuk', 'çalışmıyor', 'bakım', 'tamir', 'hata', 'kırık', 'ses', 'koku'],
            'Ekipman / Zimmet' => ['ekipman', 'zimmet', 'telefon', 'bilgisayar', 'cihaz', 'malzeme', 'alet'],
            'İzin / Sağlık' => ['izin', 'sağlık', 'hastane', 'doktor', 'rapor', 'hastalık', 'muayene'],
            'Ücret / Avans' => ['avans', 'maaş', 'ücret', 'ödeme', 'para', 'bordro', 'kesinti'],
            'Mesai / Vardiya' => ['mesai', 'vardiya', 'nöbet', 'çalışma', 'saat', 'fazla mesai'],
            'İş Ortamı' => ['yemek', 'temizlik', 'güvenlik', 'ortam', 'ofis', 'servis', 'şikayet'],
        ];
        $complaintDictionary = ['arıza', 'bozuk', 'çalışmıyor', 'sorun', 'şikayet', 'koku', 'ses', 'gecikme', 'gecikti', 'eksik', 'hata', 'kırık', 'yetersiz', 'tehlike'];

        foreach ($rows as $row) {
            $type = (string) $row->tip;
            $monthKey = date('Y-m', strtotime($row->talep_tarihi));
            if (isset($months[$monthKey][$type])) $months[$monthKey][$type]++;
            if (isset($types[$type])) $types[$type]++;

            $person = trim((string) ($row->personel ?? '')) ?: 'Belirtilmemiş';
            $requesters[$person] = ($requesters[$person] ?? 0) + 1;
            $category = trim((string) ($row->kategori ?? '')) ?: $type;
            $categories[$category] = ($categories[$category] ?? 0) + 1;
            if (mb_strtolower((string) ($row->oncelik ?? ''), 'UTF-8') === 'yuksek') $highPriority++;

            $content = mb_strtolower(trim((string) ($row->baslik ?? '') . ' ' . (string) ($row->aciklama ?? '')), 'UTF-8');
            $matchedTheme = false;
            foreach ($themeDictionary as $theme => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($content, $keyword)) {
                        $themes[$theme] = ($themes[$theme] ?? 0) + 1;
                        $matchedTheme = true;
                        break;
                    }
                }
            }
            if (!$matchedTheme && $content !== '') $themes['Diğer'] = ($themes['Diğer'] ?? 0) + 1;

            foreach ($complaintDictionary as $keyword) {
                if (str_contains($content, $keyword)) {
                    $complaintSignals[$keyword] = ($complaintSignals[$keyword] ?? 0) + 1;
                }
            }

            $status = mb_strtolower(trim((string) $row->durum), 'UTF-8');
            $isClosed = in_array($status, ['cozuldu', 'onaylandi', 'onaylandı', 'reddedildi', 'iptal', 'iptal_edildi', 'iptal edildi', 'i̇ptal edildi'], true)
                || str_contains($status, 'iptal');

            if (!$isClosed) {
                $pending++;
                $department = trim((string) ($row->departman ?? '')) ?: 'Belirtilmemiş';
                $departments[$department] = ($departments[$department] ?? 0) + 1;
                $waitDays = max(0, (int) floor((time() - strtotime($row->talep_tarihi)) / 86400));
                $oldestPendingDays = max($oldestPendingDays, $waitDays);
                if ($type === 'Avans') $pendingAdvanceAmount += (float) $row->tutar;
            } else {
                $completed++;
                if (in_array($status, ['cozuldu', 'onaylandi', 'onaylandı'], true)) $approved++;
                if (!empty($row->sonuc_tarihi)) {
                    $hours = (strtotime($row->sonuc_tarihi) - strtotime($row->talep_tarihi)) / 3600;
                    if ($hours >= 0) $resolutionHours[] = $hours;
                }
            }
        }

        arsort($departments);
        $departments = array_slice($departments, 0, 5, true);
        arsort($requesters);
        arsort($themes);
        arsort($complaintSignals);
        arsort($categories);

        return [
            'total' => count($rows),
            'pending' => $pending,
            'completed' => $completed,
            'approval_rate' => $completed > 0 ? round(($approved / $completed) * 100, 1) : 0,
            'avg_resolution_hours' => $resolutionHours ? round(array_sum($resolutionHours) / count($resolutionHours), 1) : 0,
            'oldest_pending_days' => $oldestPendingDays,
            'pending_advance_amount' => round($pendingAdvanceAmount, 2),
            'months' => array_values($months),
            'types' => $types,
            'departments' => $departments,
            'top_requesters' => array_slice($requesters, 0, 8, true),
            'themes' => array_slice($themes, 0, 8, true),
            'complaint_signals' => array_slice($complaintSignals, 0, 10, true),
            'categories' => array_slice($categories, 0, 8, true),
            'high_priority' => $highPriority,
        ];
    }
}
