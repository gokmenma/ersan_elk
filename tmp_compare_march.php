<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$startDate = '2026-03-01';
$endDate = '2026-03-31';
$firmaId = 1;

// 1. Data Loading Page Logic (Generic İş Listesi)
$sqlDL = "SELECT SUM(sonuclanmis) as total 
          FROM yapilan_isler 
          WHERE firma_id = ? AND tarih BETWEEN ? AND ? 
          AND is_emri_tipi NOT IN ('Endeks Okuma', 'Sayaç Değişimi') 
          AND silinme_tarihi IS NULL";
$stmtDL = $db->prepare($sqlDL);
$stmtDL->execute([$firmaId, $startDate, $endDate]);
$totalDL = $stmtDL->fetchColumn();
echo "Data Loading Page (Kesme/Acma Tab) Total for March: " . ($totalDL ?: 0) . "\n";

// 2. Reporting Page Logic (Sum of all categorized tabs for same period)
$sqlRpt = "SELECT tn.rapor_sekmesi, SUM(t.sonuclanmis) as total 
           FROM yapilan_isler t
           JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
           WHERE t.firma_id = ? AND t.tarih BETWEEN ? AND ? 
           AND t.silinme_tarihi IS NULL
           GROUP BY tn.rapor_sekmesi";
$stmtRpt = $db->prepare($sqlRpt);
$stmtRpt->execute([$firmaId, $startDate, $endDate]);
$resRpt = $stmtRpt->fetchAll(PDO::FETCH_ASSOC);
echo "Report Tabs Distribution for March:\n";
print_r($resRpt);

// 3. Uncategorized (Missing) for same period
$sqlMiss = "SELECT COUNT(*) FROM yapilan_isler t 
            LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
            WHERE t.firma_id = ? AND t.tarih BETWEEN ? AND ? 
            AND t.is_emri_tipi NOT IN ('Endeks Okuma', 'Sayaç Değişimi')
            AND (tn.rapor_sekmesi IS NULL OR tn.rapor_sekmesi = '0' OR tn.id IS NULL)
            AND t.silinme_tarihi IS NULL";
$stmtMiss = $db->prepare($sqlMiss);
$stmtMiss->execute([$firmaId, $startDate, $endDate]);
$totalMiss = $stmtMiss->fetchColumn();
echo "Total Uncategorized (Not in any Tab) Records for March: " . ($totalMiss ?: 0) . "\n";
