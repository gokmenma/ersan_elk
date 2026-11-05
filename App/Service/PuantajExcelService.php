<?php
namespace App\Service;

use App\Model\NobetModel;
use App\Model\PersonelFazlaMesaileriModel;
use App\Model\PersonelIzinleriModel;
use App\Model\PersonelModel;
use App\Model\TanimlamalarModel;
use PDO;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class PuantajExcelService
{
    public static function sayfaOlustur(Worksheet $sheet, int $firma_id, string $ay, string $yil, string $departman = '', string $bolge = '', int $iskur_dahil = 1, ?array $personelIds = null): void
    {
        $Personel = new PersonelModel();
        $Tanimlamalar = new TanimlamalarModel();
        $PersonelIzinleri = new PersonelIzinleriModel();
        $FazlaMesai = new PersonelFazlaMesaileriModel();
        $Nobet = new NobetModel();


    $startDate = "$yil-$ay-01";
    $daysCount = date('t', strtotime($startDate));
    $endDate = "$yil-$ay-$daysCount";

    $sql = "
        SELECT p.id, p.adi_soyadi, p.tc_kimlik_no, 
               COALESCE(pcg.isten_cikis_tarihi, p.isten_cikis_tarihi) as isten_cikis_tarihi, 
               COALESCE(pcg.ise_giris_tarihi, p.ise_giris_tarihi) as ise_giris_tarihi,
               CASE WHEN gg.personel_id IS NOT NULL THEN 1 ELSE 0 END as gorev_gecmisi_var,
               COALESCE(gg_days.toplam_gun, 0) as gg_toplam_gun
        FROM personel p
        LEFT JOIN personel_calisma_gecmisi pcg ON p.id = pcg.personel_id 
            AND pcg.ise_giris_tarihi <= :end_date_cal 
            AND (pcg.isten_cikis_tarihi IS NULL OR pcg.isten_cikis_tarihi >= :start_date_cal)
        LEFT JOIN (
            SELECT DISTINCT pgg.personel_id
            FROM personel_gorev_gecmisi pgg
            WHERE pgg.baslangic_tarihi <= :end_date1
              AND (pgg.bitis_tarihi IS NULL OR pgg.bitis_tarihi >= :start_date1)
        ) gg ON p.id = gg.personel_id
        LEFT JOIN (
            SELECT pgg.personel_id, 
                   SUM(DATEDIFF(LEAST(COALESCE(pgg.bitis_tarihi, :end_date2), :end_date3), GREATEST(pgg.baslangic_tarihi, :start_date2)) + 1) as toplam_gun
            FROM personel_gorev_gecmisi pgg
            WHERE pgg.baslangic_tarihi <= :end_date4 AND (pgg.bitis_tarihi IS NULL OR pgg.bitis_tarihi >= :start_date3)
            GROUP BY pgg.personel_id
        ) gg_days ON p.id = gg_days.personel_id
        LEFT JOIN (
            SELECT pgg_dep.personel_id, pgg_dep.departman 
            FROM personel_gorev_gecmisi pgg_dep
            INNER JOIN (
                SELECT personel_id, MAX(baslangic_tarihi) AS latest_start 
                FROM personel_gorev_gecmisi 
                WHERE baslangic_tarihi <= :end_date_dep AND (bitis_tarihi IS NULL OR bitis_tarihi >= :start_date_dep) 
                GROUP BY personel_id
            ) gg_latest ON pgg_dep.personel_id = gg_latest.personel_id AND pgg_dep.baslangic_tarihi = gg_latest.latest_start
        ) gg_dep ON p.id = gg_dep.personel_id
        LEFT JOIN tanimlamalar t_ekip ON p.ekip_no = t_ekip.id AND t_ekip.grup = 'ekip_kodu' AND t_ekip.silinme_tarihi IS NULL
        WHERE p.firma_id = :firma_id 
        AND p.silinme_tarihi IS NULL 
        AND (p.aktif_mi = 1 OR (COALESCE(pcg.isten_cikis_tarihi, p.isten_cikis_tarihi) IS NOT NULL AND COALESCE(pcg.isten_cikis_tarihi, p.isten_cikis_tarihi) >= :start_date5))
        AND (COALESCE(pcg.isten_cikis_tarihi, p.isten_cikis_tarihi) IS NULL OR COALESCE(pcg.isten_cikis_tarihi, p.isten_cikis_tarihi) = '' OR COALESCE(pcg.isten_cikis_tarihi, p.isten_cikis_tarihi) = '0000-00-00' OR COALESCE(pcg.isten_cikis_tarihi, p.isten_cikis_tarihi) >= :start_date6)
        AND (COALESCE(pcg.disardan_sigortali, p.disardan_sigortali) = 0 OR FIND_IN_SET('puantaj', COALESCE(pcg.gorunum_modulleri, p.gorunum_modulleri)))
        AND (COALESCE(pcg.ise_giris_tarihi, p.ise_giris_tarihi) IS NULL OR COALESCE(pcg.ise_giris_tarihi, p.ise_giris_tarihi) = '' OR COALESCE(pcg.ise_giris_tarihi, p.ise_giris_tarihi) <= :end_date5)
        AND NOT EXISTS (
            SELECT 1 FROM personel_gorev_gecmisi pgg_not
            WHERE pgg_not.personel_id = p.id
            AND pgg_not.baslangic_tarihi <= :end_date6
            AND (pgg_not.bitis_tarihi IS NULL OR pgg_not.bitis_tarihi >= :start_date7)
            AND (pgg_not.maas_durumu = 'Maaş Hesaplanmayan')
        )
    ";

    if ($iskur_dahil === 0) {
        $sql .= " AND NOT (
            COALESCE(pcg.sgk_yapilan_firma, p.sgk_yapilan_firma) LIKE '%İŞKUR%'
            OR COALESCE(pcg.sgk_yapilan_firma, p.sgk_yapilan_firma) LIKE '%ISKUR%'
            OR COALESCE(pcg.sgk_yapilan_firma, p.sgk_yapilan_firma) LIKE '%KUR%'
        ) ";
    }

    if ($departman !== '') {
        $sql .= " AND gg_dep.departman = :departman ";
    }

    if ($bolge !== '') {
        $sql .= " AND t_ekip.ekip_bolge = :bolge ";
    }

    $sql .= " ORDER BY p.adi_soyadi ASC ";

    $personeller = $Personel->db->prepare($sql);
    
    $params = [
        ':end_date_cal' => $endDate,
        ':start_date_cal' => $startDate,
        ':end_date1' => $endDate,
        ':start_date1' => $startDate,
        ':end_date2' => $endDate,
        ':end_date3' => $endDate,
        ':start_date2' => $startDate,
        ':end_date4' => $endDate,
        ':start_date3' => $startDate,
        ':end_date_dep' => $endDate,
        ':start_date_dep' => $startDate,
        ':firma_id' => $firma_id,
        ':start_date5' => $startDate,
        ':start_date6' => $startDate,
        ':end_date5' => $endDate,
        ':end_date6' => $endDate,
        ':start_date7' => $startDate
    ];

    if ($departman !== '') {
        $params[':departman'] = $departman;
    }
    if ($bolge !== '') {
        $params[':bolge'] = $bolge;
    }

    $personeller->execute($params);
    $personel_list = array_map(fn($r) => $Personel->decryptFields($r), $personeller->fetchAll(PDO::FETCH_OBJ));
    if ($personelIds !== null) {
        $izinliIds = array_flip(array_map('intval', $personelIds));
        $personel_list = array_values(array_filter($personel_list, static fn($p) => isset($izinliIds[(int) $p->id])));
    }

    $varsayilan_X = $Tanimlamalar->db->prepare("SELECT id, tur_adi, kisa_kod, renk FROM tanimlamalar WHERE grup = 'izin_turu' AND (kisa_kod = 'X' OR kisa_kod = 'x' OR tur_adi LIKE '%Çalışılan Gün%') AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL LIMIT 1");
    $varsayilan_X->execute([$firma_id]);
    $x_tanim = $varsayilan_X->fetch(PDO::FETCH_OBJ);

    $varsayilan_HT = $Tanimlamalar->db->prepare("SELECT id, tur_adi, kisa_kod, renk FROM tanimlamalar WHERE grup = 'izin_turu' AND (kisa_kod = 'HT' OR kisa_kod = 'ht' OR tur_adi LIKE '%Hafta Tatili%') AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL LIMIT 1");
    $varsayilan_HT->execute([$firma_id]);
    $ht_tanim = $varsayilan_HT->fetch(PDO::FETCH_OBJ);

    // Ücretli izin türlerini getir (Web arayüzü ile aynı mantık)
    $ucretliIdsStmt = $Tanimlamalar->db->prepare("SELECT id FROM tanimlamalar WHERE grup = 'izin_turu' AND ucretli_mi = 1 AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL");
    $ucretliIdsStmt->execute([$firma_id]);
    $ucretliIds = $ucretliIdsStmt->fetchAll(PDO::FETCH_COLUMN);
    $ucretliIds[] = $x_tanim ? $x_tanim->id : 0;
    $ucretliIds[] = $ht_tanim ? $ht_tanim->id : 0;
    $ucretliIds = array_unique(array_filter($ucretliIds));

    $exportPersonelIds = array_map(static fn($p) => (int) $p->id, $personel_list);
    $exportMesailer = $FazlaMesai->getPersonellerAyMesaileri($exportPersonelIds, $startDate, $endDate, $firma_id);
    $exportNobetMesaileri = $Nobet->getPuantajHaftaIciMesaileri($exportPersonelIds, $startDate, $endDate, $firma_id);
    $exportNobetHtc = $Nobet->getOnayliHaftaSonuNobetTarihleri($exportPersonelIds, $startDate, $endDate, $firma_id);
    $exportHtcStmt = $Tanimlamalar->db->prepare("SELECT id FROM tanimlamalar WHERE grup = 'izin_turu' AND (kisa_kod IN ('HTÇ','HTC') OR hafta_tatili_calismasi = 1) AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL LIMIT 1");
    $exportHtcStmt->execute([$firma_id]);
    $exportHtcId = (int) $exportHtcStmt->fetchColumn();

    $sheet->setTitle('Puantaj');

    // Header Style
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4B39B9']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
    ];

    // Column style
    $pNameStyle = [
        'font' => ['bold' => true],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DASHED]]
    ];

    // Set Headers
    $sheet->setCellValue('A1', 'TC Kimlik No');
    $sheet->setCellValue('B1', 'Personel');
    for ($d = 1; $d <= $daysCount; $d++) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $d);
        $sheet->setCellValue($col . '1', $d);
    }
    $toplamCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $daysCount);
    $fiiliCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + $daysCount);
    $sheet->setCellValue($toplamCol . '1', 'Toplam Ç.G.');
    $sheet->setCellValue($fiiliCol . '1', 'Fiili Ç.G.');

    $lastCol = $fiiliCol;
    $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray($headerStyle);

    // Column Widths
    $sheet->getColumnDimension('A')->setWidth(18);
    $sheet->getColumnDimension('B')->setWidth(30);
    for ($d = 1; $d <= $daysCount; $d++) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $d);
        $sheet->getColumnDimension($col)->setWidth(4);
    }
    $sheet->getColumnDimension($toplamCol)->setWidth(12);
    $sheet->getColumnDimension($fiiliCol)->setWidth(12);

    // Fill Data
    $row = 2;
    foreach ($personel_list as $p) {
        $sheet->setCellValue('A' . $row, $p->tc_kimlik_no ?? '');
        $sheet->setCellValue('B' . $row, $p->adi_soyadi);
        $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray($pNameStyle);

        // Fetch entries for this personnel
        $izin_stmt = $PersonelIzinleri->db->prepare("
            SELECT pi.baslangic_tarihi, pi.bitis_tarihi, pi.izin_tipi_id, t.kisa_kod, t.renk 
            FROM personel_izinleri pi
            JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.personel_id = ? AND pi.silinme_tarihi IS NULL AND pi.onay_durumu = 'Onaylandı'
            AND (
                (pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ?)
            )
        ");
        $izin_stmt->execute([$p->id, $endDate, $startDate]);
        $izinler = $izin_stmt->fetchAll(PDO::FETCH_OBJ);

        $dayData = [];
        $mevcut_gunler = [];
        foreach ($izinler as $izin) {
            $cur = strtotime($izin->baslangic_tarihi);
            $end = strtotime($izin->bitis_tarihi);
            while ($cur <= $end) {
                $date_str = date('Y-m-d', $cur);
                if ($date_str >= $startDate && $date_str <= $endDate) {
                    $rawColor = $izin->renk ?: 'bg-success/10';
                    $hexColor = '34C38F'; 

                    if (strpos($rawColor, 'bg-primary') !== false)
                        $hexColor = 'D1E4FF';
                    elseif (strpos($rawColor, 'bg-amber') !== false || strpos($rawColor, 'warning') !== false)
                        $hexColor = 'FEF3C7';
                    elseif (strpos($rawColor, 'bg-purple') !== false)
                        $hexColor = 'F3E8FF';
                    elseif (strpos($rawColor, 'bg-red') !== false || strpos($rawColor, 'danger') !== false)
                        $hexColor = 'FEE2E2';
                    elseif (strpos($rawColor, 'bg-success') !== false)
                        $hexColor = 'D1FAE5';
                    elseif (strpos($rawColor, '#') !== false) {
                        $hexColor = str_replace('#', '', $rawColor);
                    }

                    $dayData[date('j', $cur)] = [
                        'code' => $izin->kisa_kod,
                        'color' => $hexColor,
                        'tip_id' => $izin->izin_tipi_id,
                        'is_default' => false
                    ];
                    $mevcut_gunler[$date_str] = true;
                }
                $cur = strtotime("+1 day", $cur);
            }
        }

        // Boş günleri X ve HT ile doldur
        $cur = strtotime($startDate);
        $end = strtotime($endDate);
        $p_giris = $p->ise_giris_tarihi && $p->ise_giris_tarihi != '0000-00-00' ? strtotime($p->ise_giris_tarihi) : 0;
        $p_cikis = $p->isten_cikis_tarihi && $p->isten_cikis_tarihi != '0000-00-00' ? strtotime($p->isten_cikis_tarihi) : PHP_INT_MAX;

        while ($cur <= $end) {
            $date_str = date('Y-m-d', $cur);
            $d = (int)date('j', $cur);
            $isWeekend = date('w', $cur) == 0; 

            if (!isset($mevcut_gunler[$date_str]) && $cur >= $p_giris && $cur <= $p_cikis) {
                if ($isWeekend && $ht_tanim) {
                    $dayData[$d] = [
                        'code' => $ht_tanim->kisa_kod,
                        'color' => 'F46A6A', 
                        'tip_id' => $ht_tanim->id,
                        'is_default' => true
                    ];
                } elseif (!$isWeekend && $x_tanim) {
                    $dayData[$d] = [
                        'code' => $x_tanim->kisa_kod,
                        'color' => '556EE6', 
                        'tip_id' => $x_tanim->id,
                        'is_default' => true
                    ];
                }
            }
            $cur = strtotime("+1 day", $cur);
        }

        foreach (array_keys($exportNobetHtc[(int) $p->id] ?? []) as $nobetHtcTarihi) {
            $dayData[(int) date('j', strtotime($nobetHtcTarihi))] = [
                'code' => 'HTÇ', 'color' => 'EDE9FE', 'tip_id' => $exportHtcId, 'is_default' => false,
            ];
        }

        $paidCount = 0;
        $fiiliCount = 0;

        for ($d = 1; $d <= $daysCount; $d++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $d);
            $cell = $col . $row;

            $currentStyle = [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DASHED,
                        'color' => ['argb' => 'FFCED4DA']
                    ]
                ]
            ];

            if (isset($dayData[$d])) {
                $dateStr = sprintf('%04d-%02d-%02d', (int) $yil, (int) $ay, $d);
                $manuelMesai = (float) ($exportMesailer[(int) $p->id][$dateStr]['saat'] ?? 0);
                $nobetMesaisi = (float) ($exportNobetMesaileri[(int) $p->id][$dateStr] ?? 0);
                $toplamMesai = $manuelMesai + $nobetMesaisi;
                $mesaiEtiketi = $toplamMesai > 0
                    ? '+' . rtrim(rtrim(number_format($toplamMesai, 1, '.', ''), '0'), '.') . 'S'
                    : '';
                $sheet->setCellValue($cell, $dayData[$d]['code'] . $mesaiEtiketi);
                $color = trim($dayData[$d]['color']);

                if (strlen($color) !== 6) {
                    $color = '34C385';
                }

                $currentStyle['font'] = [
                    'bold' => true,
                    'color' => ['argb' => 'FF000000']
                ];
                $currentStyle['fill'] = [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF' . strtoupper($color)]
                ];

                // Hesaplamalar (Web Arayüzü ile Uyumlu)
                if (in_array($dayData[$d]['tip_id'], $ucretliIds)) {
                    $paidCount++;
                }
                if ($dayData[$d]['is_default'] && $dayData[$d]['code'] !== 'HT') {
                    $fiiliCount++;
                }
            }

            $sheet->getStyle($cell)->applyFromArray($currentStyle);
        }

        $sheet->setCellValue($toplamCol . $row, max(0, $paidCount));
        $sheet->setCellValue($fiiliCol . $row, max(0, $fiiliCount));

        // Center totals
        $sheet->getStyle($toplamCol . $row . ':' . $fiiliCol . $row)->applyFromArray([
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'font' => ['bold' => true],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
        ]);

        $row++;
    }

    // İzin Türleri Açıklamalarını Ekle
    $row += 2;
    $sheet->setCellValue('B' . $row, 'İzin Kodları Açıklamaları');
    $sheet->getStyle('B' . $row)->getFont()->setBold(true);
    $row++;

    $izinTurleriQuery = $Tanimlamalar->db->prepare("SELECT kisa_kod, tur_adi, ucretli_mi FROM tanimlamalar WHERE grup = 'izin_turu' AND (firma_id = ? OR firma_id = 0) AND silinme_tarihi IS NULL ORDER BY ucretli_mi DESC, kisa_kod ASC");
    $izinTurleriQuery->execute([$firma_id]);
    $izinTurleriData = $izinTurleriQuery->fetchAll(PDO::FETCH_OBJ);

    foreach ($izinTurleriData as $it) {
        $typeStr = ($it->ucretli_mi == 1) ? "(Ücretli)" : "(Ücretsiz)";
        $sheet->setCellValue('B' . $row, $it->kisa_kod . " : " . $it->tur_adi . " " . $typeStr);
        $row++;
    }
    }
}
