<?php

namespace App\Service\AiContext;

use App\Model\AracModel;
use App\Model\AracServisModel;
use App\Model\AracZimmetModel;
use App\Model\PersonelModel;
use PDO;

class AracTakipContext
{
    /**
     * Kullanıcı promptuna göre Araç Takip modülü için özet veritabanı bağlamı üretir.
     * Bu yöntem ham veriyi LLM'e göndermek yerine deterministik SQL özeti sunarak
     * token kullanımını %90-95 oranında düşürür.
     */
    public static function buildContext($firmaId, $userPrompt)
    {
        $aracModel = new AracModel();
        $servisModel = new AracServisModel();
        $zimmetModel = new AracZimmetModel();
        $personelModel = new PersonelModel();
        $db = $aracModel->getDb();

        $contextData = [];

        // 1. Genel Filo Özet İstatistikleri
        $statsSql = $db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM araclar WHERE firma_id = :f1 AND silinme_tarihi IS NULL AND ikame_mi = 0) as toplam_arac,
                (SELECT COUNT(DISTINCT arac_id) FROM arac_servis_kayitlari WHERE firma_id = :f2 AND iade_tarihi IS NULL AND silinme_tarihi IS NULL) as servisteki_arac,
                (SELECT COUNT(*) FROM araclar WHERE firma_id = :f3 AND silinme_tarihi IS NULL AND ikame_mi = 1) as ikame_arac_sayisi,
                (SELECT COUNT(DISTINCT arac_id) FROM arac_zimmetleri WHERE firma_id = :f4 AND durum = 'aktif') as zimmetli_arac
        ");
        $statsSql->execute(['f1' => $firmaId, 'f2' => $firmaId, 'f3' => $firmaId, 'f4' => $firmaId]);
        $contextData['filo_ozet'] = $statsSql->fetch(PDO::FETCH_ASSOC);

        // 1b. Filo Toplam Harcama İstatistikleri (Yakıt + Servis)
        $maliyetSql = $db->prepare("
            SELECT 
                (SELECT SUM(toplam_tutar) FROM arac_yakit_kayitlari WHERE firma_id = :f1 AND silinme_tarihi IS NULL) as toplam_yakit_tutar,
                (SELECT SUM(yakit_miktari) FROM arac_yakit_kayitlari WHERE firma_id = :f2 AND silinme_tarihi IS NULL) as toplam_yakit_litre,
                (SELECT COUNT(*) FROM arac_yakit_kayitlari WHERE firma_id = :f3 AND silinme_tarihi IS NULL) as toplam_yakit_kaydi,
                (SELECT SUM(tutar) FROM arac_servis_kayitlari WHERE firma_id = :f4 AND silinme_tarihi IS NULL) as toplam_servis_tutar,
                (SELECT COUNT(*) FROM arac_servis_kayitlari WHERE firma_id = :f5 AND silinme_tarihi IS NULL) as toplam_servis_kaydi,
                (SELECT COUNT(*) FROM arac_servis_kayitlari WHERE firma_id = :f6 AND silinme_tarihi IS NULL AND (tutar IS NULL OR tutar = 0)) as tutarsiz_servis_kaydi
        ");
        $maliyetSql->execute(['f1' => $firmaId, 'f2' => $firmaId, 'f3' => $firmaId, 'f4' => $firmaId, 'f5' => $firmaId, 'f6' => $firmaId]);
        $contextData['maliyet_ozeti'] = $maliyetSql->fetch(PDO::FETCH_ASSOC);

        // 2b. En Çok Yakıt Harcayan Araçlar
        $topYakitSql = $db->prepare("
            SELECT 
                a.plaka, a.marka, a.model,
                COUNT(y.id) as dolum_sayisi,
                SUM(y.yakit_miktari) as toplam_litre,
                SUM(y.toplam_tutar) as toplam_yakit_maliyeti,
                p.adi_soyadi as mevcut_surucu
            FROM arac_yakit_kayitlari y
            INNER JOIN araclar a ON y.arac_id = a.id
            LEFT JOIN (
                SELECT az1.* FROM arac_zimmetleri az1
                INNER JOIN (
                    SELECT MAX(id) as max_id FROM arac_zimmetleri WHERE durum = 'aktif' GROUP BY arac_id
                ) az2 ON az1.id = az2.max_id
            ) az ON a.id = az.arac_id
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE y.firma_id = :firma_id AND y.silinme_tarihi IS NULL
            GROUP BY a.id, a.plaka, a.marka, a.model, p.adi_soyadi
            ORDER BY toplam_yakit_maliyeti DESC
            LIMIT 5
        ");
        $topYakitSql->execute(['firma_id' => $firmaId]);
        $contextData['en_cok_yakit_harcayan_araclar'] = $topYakitSql->fetchAll(PDO::FETCH_ASSOC);

        // 2. En Çok Servise Giden Araçlar ve Zimmetli Sürücüleri
        $topServisSql = $db->prepare("
            SELECT 
                a.plaka, a.marka, a.model,
                COUNT(s.id) as servis_sayisi,
                SUM(COALESCE(s.tutar, 0)) as toplam_servis_maliyeti,
                p.adi_soyadi as mevcut_surucu
            FROM arac_servis_kayitlari s
            INNER JOIN araclar a ON s.arac_id = a.id
            LEFT JOIN (
                SELECT az1.* FROM arac_zimmetleri az1
                INNER JOIN (
                    SELECT MAX(id) as max_id FROM arac_zimmetleri WHERE durum = 'aktif' GROUP BY arac_id
                ) az2 ON az1.id = az2.max_id
            ) az ON a.id = az.arac_id
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE s.firma_id = :firma_id AND s.silinme_tarihi IS NULL
            GROUP BY a.id, a.plaka, a.marka, a.model, p.adi_soyadi
            ORDER BY servis_sayisi DESC
            LIMIT 5
        ");
        $topServisSql->execute(['firma_id' => $firmaId]);
        $contextData['en_cok_servise_giden_araclar'] = $topServisSql->fetchAll(PDO::FETCH_ASSOC);

        // 3. İkame Araç Verilen ve İkame Araçta da Problem Yaşanan Sürücü / Servis Analizi
        $ikameProblemSql = $db->prepare("
            SELECT 
                s.id as servis_id,
                a.plaka as asil_arac_plaka,
                COALESCE(NULLIF(s.ikame_plaka, ''), ikame_a.plaka, 'İkame Araç') as ikame_plaka,
                COALESCE(NULLIF(CONCAT(s.ikame_marka, ' ', s.ikame_model), ' '), CONCAT(ikame_a.marka, ' ', ikame_a.model), 'İkame Araç') as ikame_bilgisi,
                s.servis_nedeni,
                s.servis_tarihi,
                p.adi_soyadi as surucu_adi
            FROM arac_servis_kayitlari s
            INNER JOIN araclar a ON s.arac_id = a.id
            LEFT JOIN araclar ikame_a ON s.ikame_arac_id = ikame_a.id
            LEFT JOIN (
                SELECT az1.* FROM arac_zimmetleri az1
                INNER JOIN (
                    SELECT MAX(id) as max_id FROM arac_zimmetleri WHERE durum = 'aktif' GROUP BY arac_id
                ) az2 ON az1.id = az2.max_id
            ) az ON a.id = az.arac_id
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE s.firma_id = :firma_id 
            AND s.silinme_tarihi IS NULL
            AND ((s.ikame_plaka IS NOT NULL AND s.ikame_plaka != '') OR (s.ikame_arac_id IS NOT NULL AND s.ikame_arac_id > 0))
            ORDER BY s.servis_tarihi DESC
            LIMIT 10
        ");
        $ikameProblemSql->execute(['firma_id' => $firmaId]);
        $contextData['ikame_arac_kullananlar'] = $ikameProblemSql->fetchAll(PDO::FETCH_ASSOC);

        // 4. Prompt İçinde Belirli Bir Personel / Sürücü Geçip Geçmediğini Tespit Etme
        $personelAnalizi = self::searchSpecificEntity($db, $firmaId, $userPrompt);
        if ($personelAnalizi) {
            $contextData['sorgulanan_personel_detayi'] = $personelAnalizi;
        }

        // 5. Prompt İçinde Belirli Bir Plaka Geçip Geçmediğini Tespit Etme
        $plakaAnalizi = self::searchSpecificPlate($db, $firmaId, $userPrompt);
        if ($plakaAnalizi) {
            $contextData['sorgulanan_plaka_detayi'] = $plakaAnalizi;
        }

        return json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Türkçe karakter eşleşmesini normalize eder (İ/i/I/ı/Ç/ç vb. uyuşmazlıkları çözer)
     */
    private static function normalizeTurkishText($text)
    {
        if (empty($text)) return '';
        $text = str_replace(
            ['İ', 'I', 'ı', 'Ç', 'ç', 'Ş', 'ş', 'Ğ', 'ğ', 'Ü', 'ü', 'Ö', 'ö'],
            ['i', 'i', 'i', 'c', 'c', 's', 's', 'g', 'g', 'u', 'u', 'o', 'o'],
            $text
        );
        return strtolower(trim($text));
    }

    /**
     * Prompt metninde geçen isim veya soyisimi arar ve o sürücünün zimmet, servis, ikame araç geçmişini çıkarır.
     */
    private static function searchSpecificEntity($db, $firmaId, $userPrompt)
    {
        // Firma personellerini çek
        $personelSql = $db->prepare("SELECT id, adi_soyadi FROM personel WHERE firma_id = :firma_id AND silinme_tarihi IS NULL");
        $personelSql->execute(['firma_id' => $firmaId]);
        $personeller = $personelSql->fetchAll(PDO::FETCH_OBJ);

        $matchedPersonel = null;
        $normPrompt = self::normalizeTurkishText($userPrompt);

        foreach ($personeller as $p) {
            $normName = self::normalizeTurkishText($p->adi_soyadi);
            $parts = explode(' ', $normName);
            
            // Eğer tam isim prompt içinde varsa
            if (!empty($normName) && str_contains($normPrompt, $normName)) {
                $matchedPersonel = $p;
                break;
            }

            // Ad ve soyad ayrı ayrı prompt içinde var mı?
            if (count($parts) >= 2) {
                $firstName = $parts[0];
                $lastName = end($parts);
                if (strlen($firstName) > 2 && strlen($lastName) > 2) {
                    if (str_contains($normPrompt, $firstName) && str_contains($normPrompt, $lastName)) {
                        $matchedPersonel = $p;
                        break;
                    }
                }
            }
        }

        if (!$matchedPersonel) {
            return null;
        }

        // Sürücünün Zimmet Geçmişi ve Servis Kayıtları
        $pId = $matchedPersonel->id;
        
        $pZimmetSql = $db->prepare("
            SELECT az.*, a.plaka, a.marka, a.model
            FROM arac_zimmetleri az
            INNER JOIN araclar a ON az.arac_id = a.id
            WHERE az.personel_id = :personel_id AND az.firma_id = :firma_id
            ORDER BY az.zimmet_tarihi DESC
        ");
        $pZimmetSql->execute(['personel_id' => $pId, 'firma_id' => $firmaId]);
        $zimmetler = $pZimmetSql->fetchAll(PDO::FETCH_ASSOC);

        // Personelin Kullandığı Araçların Servis Kayıtları (ve İkame araç durumu)
        $pServisSql = $db->prepare("
            SELECT s.*, a.plaka as asil_arac_plaka
            FROM arac_servis_kayitlari s
            INNER JOIN araclar a ON s.arac_id = a.id
            INNER JOIN arac_zimmetleri az ON a.id = az.arac_id
            WHERE az.personel_id = :personel_id 
            AND s.firma_id = :firma_id 
            AND s.silinme_tarihi IS NULL
            ORDER BY s.servis_tarihi DESC
        ");
        $pServisSql->execute(['personel_id' => $pId, 'firma_id' => $firmaId]);
        $servisler = $pServisSql->fetchAll(PDO::FETCH_ASSOC);

        // İkame Araç Verilip İkame Araçta da Arıza Olup Olmadığı
        $ikameArizaSayisi = 0;
        foreach ($servisler as $servisItem) {
            if (!empty($servisItem['ikame_plaka']) || !empty($servisItem['ikame_arac_id'])) {
                $ikameArizaSayisi++;
            }
        }

        return [
            'personel_id'          => $matchedPersonel->id,
            'personel_adi'         => $matchedPersonel->adi_soyadi,
            'toplam_zimmetli_arac' => count($zimmetler),
            'zimmetli_araclar'     => array_map(fn($z) => $z['plaka'] . ' (' . $z['marka'] . ' ' . $z['model'] . ')', $zimmetler),
            'zimmet_kayitlari_detay' => array_map(function($z) {
                return [
                    'plaka'         => $z['plaka'],
                    'arac'          => $z['marka'] . ' ' . $z['model'],
                    'zimmet_tarihi' => $z['zimmet_tarihi'] ? date('d.m.Y', strtotime($z['zimmet_tarihi'])) : '-',
                    'iade_tarihi'   => $z['iade_tarihi'] ? date('d.m.Y', strtotime($z['iade_tarihi'])) : 'Devam Ediyor (Aktif)',
                    'durum'         => $z['durum'] === 'aktif' ? 'Aktif Zimmet' : 'İade Edildi',
                    'teslim_km'     => $z['teslim_km'] ? number_format($z['teslim_km'], 0, ',', '.') . ' KM' : '-',
                    'iade_km'       => $z['iade_km'] ? number_format($z['iade_km'], 0, ',', '.') . ' KM' : '-'
                ];
            }, $zimmetler),
            'toplam_servis_sayisi' => count($servisler),
            'ikame_arac_verilme_sayisi' => $ikameArizaSayisi,
            'servis_nedenleri_ozet' => array_map(fn($s) => $s['asil_arac_plaka'] . ': ' . ($s['servis_nedeni'] ?? 'Belirtilmedi') . ' (Tarih: ' . ($s['servis_tarihi'] ?? '-') . ')', array_slice($servisler, 0, 10))
        ];
    }

    /**
     * Prompt metninde plaka formatı arar ve o aracın detaylarını getirir.
     */
    private static function searchSpecificPlate($db, $firmaId, $userPrompt)
    {
        // Firma araçlarının plakalarını çek
        $aracSql = $db->prepare("SELECT id, plaka, marka, model, model_yili, guncel_km FROM araclar WHERE firma_id = :firma_id AND silinme_tarihi IS NULL");
        $aracSql->execute(['firma_id' => $firmaId]);
        $araclar = $aracSql->fetchAll(PDO::FETCH_OBJ);

        $matchedArac = null;
        $normPrompt = str_replace(' ', '', self::normalizeTurkishText($userPrompt));

        foreach ($araclar as $a) {
            $normPlaka = str_replace(' ', '', self::normalizeTurkishText($a->plaka));
            if (!empty($normPlaka) && str_contains($normPrompt, $normPlaka)) {
                $matchedArac = $a;
                break;
            }
        }

        if (!$matchedArac) {
            return null;
        }

        $aracId = $matchedArac->id;

        // Mevcut Zimmetli Sürücü
        $zimmetSql = $db->prepare("
            SELECT az.*, p.adi_soyadi as surucu_adi
            FROM arac_zimmetleri az
            LEFT JOIN personel p ON az.personel_id = p.id
            WHERE az.arac_id = :arac_id AND az.firma_id = :firma_id AND az.durum = 'aktif'
            LIMIT 1
        ");
        $zimmetSql->execute(['arac_id' => $aracId, 'firma_id' => $firmaId]);
        $zimmet = $zimmetSql->fetch(PDO::FETCH_OBJ);

        // Servis Geçmişi
        $servisSql = $db->prepare("
            SELECT s.* 
            FROM arac_servis_kayitlari s
            WHERE s.arac_id = :arac_id AND s.firma_id = :firma_id AND s.silinme_tarihi IS NULL
            ORDER BY s.servis_tarihi DESC
        ");
        $servisSql->execute(['arac_id' => $aracId, 'firma_id' => $firmaId]);
        $servisler = $servisSql->fetchAll(PDO::FETCH_ASSOC);

        // Toplam Servis Maliyeti
        $toplamMaliyet = array_sum(array_column($servisler, 'tutar'));

        return [
            'arac_id'         => $matchedArac->id,
            'plaka'           => $matchedArac->plaka,
            'marka_model'     => $matchedArac->marka . ' ' . $matchedArac->model . ' (' . ($matchedArac->model_yili ?? '-') . ')',
            'guncel_km'       => $matchedArac->guncel_km ? number_format($matchedArac->guncel_km, 0, ',', '.') . ' KM' : '-',
            'aktif_surucu'    => $zimmet ? $zimmet->surucu_adi : 'Boşta / Atanmamış',
            'toplam_servis'   => count($servisler),
            'toplam_maliyet'  => number_format($toplamMaliyet, 2, ',', '.') . ' TL',
            'servis_kayitlari'=> array_map(function($s) {
                return [
                    'tarih'        => $s['servis_tarihi'] ? date('d.m.Y', strtotime($s['servis_tarihi'])) : '-',
                    'servis_adi'   => $s['servis_adi'] ?? 'Belirtilmedi',
                    'nedeni'       => $s['servis_nedeni'] ?? 'Belirtilmedi',
                    'islemler'     => $s['yapilan_islemler'] ?? '-',
                    'tutar'        => number_format($s['tutar'] ?? 0, 2, ',', '.') . ' TL',
                    'ikame_arac'   => $s['ikame_plaka'] ?: 'Yok'
                ];
            }, array_slice($servisler, 0, 10))
        ];
    }
}
