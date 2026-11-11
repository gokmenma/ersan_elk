<?php

namespace App\Model;

use PDO;

class PersonelPerformansModel extends Model
{
    private const KESME_CUTOFF = '2026-04-01';
    private const ENDEKS_EKIP_REGEX = 'EK[İI]P-?[[:space:]]?[0-9]+';

    public function __construct()
    {
        parent::__construct('yapilan_isler');
    }

    public function getAnaSayfaPerformans(object $personel, int $firmaId, bool $okumaSefi): array
    {
        $personelId = (int) $personel->id;
        $today = date('Y-m-d');
        $ayBas = date('Y-m-01');
        $aySon = date('Y-m-t');
        $departmanHam = (string) ($personel->departman ?? '');
        $isSayacEkibi = stripos($departmanHam, 'Sayaç Sökme Takma') !== false;
        $isKacakEkibi = stripos($departmanHam, 'Kaçak') !== false;

        $isFiltre = "AND tn.is_turu_ucret > 0 AND tn.rapor_sekmesi IS NOT NULL AND tn.rapor_sekmesi != ''";
        $cutoffFiltre = $okumaSefi ? " AND (tn.rapor_sekmesi != 'kesme' OR t.tarih >= :cutoff)" : '';
        $cutoffParam = $okumaSefi ? ['cutoff' => self::KESME_CUTOFF] : [];

        $isToplam = function (string $bas, string $bit) use ($personelId, $firmaId, $isFiltre, $cutoffFiltre, $cutoffParam): int {
            $stmt = $this->db->prepare("SELECT SUM(t.sonuclanmis) FROM yapilan_isler t
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                WHERE t.personel_id = :pid AND t.firma_id = :fid AND t.silinme_tarihi IS NULL
                AND t.tarih BETWEEN :bas AND :bit $isFiltre $cutoffFiltre");
            $stmt->execute(['pid' => $personelId, 'fid' => $firmaId, 'bas' => $bas, 'bit' => $bit] + $cutoffParam);
            return (int) $stmt->fetchColumn();
        };
        $sekmeDagilim = function (string $bas, string $bit) use ($personelId, $firmaId, $isFiltre, $cutoffFiltre, $cutoffParam): array {
            $stmt = $this->db->prepare("SELECT tn.rapor_sekmesi, SUM(t.sonuclanmis) FROM yapilan_isler t
                LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
                WHERE t.personel_id = :pid AND t.firma_id = :fid AND t.silinme_tarihi IS NULL
                AND t.tarih BETWEEN :bas AND :bit $isFiltre $cutoffFiltre GROUP BY tn.rapor_sekmesi");
            $stmt->execute(['pid' => $personelId, 'fid' => $firmaId, 'bas' => $bas, 'bit' => $bit] + $cutoffParam);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
        };
        $sayacToplam = function (string $bas, string $bit) use ($personelId, $firmaId): int {
            $stmt = $this->db->prepare('SELECT SUM(is_sayisi) FROM sayac_degisim
                WHERE personel_id = :pid AND firma_id = :fid AND silinme_tarihi IS NULL AND tarih BETWEEN :bas AND :bit');
            $stmt->execute(['pid' => $personelId, 'fid' => $firmaId, 'bas' => $bas, 'bit' => $bit]);
            return (int) $stmt->fetchColumn();
        };
        $endeksToplam = function (string $bas, string $bit) use ($personelId, $firmaId): int {
            $stmt = $this->db->prepare('SELECT SUM(t.okunan_abone_sayisi) FROM endeks_okuma t
                LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id
                WHERE t.personel_id = :pid AND t.firma_id = :fid AND t.silinme_tarihi IS NULL
                AND t.tarih BETWEEN :bas AND :bit AND def.tur_adi REGEXP :rx');
            $stmt->execute(['pid' => $personelId, 'fid' => $firmaId, 'bas' => $bas, 'bit' => $bit, 'rx' => self::ENDEKS_EKIP_REGEX]);
            return (int) $stmt->fetchColumn();
        };
        $kacakToplam = function (string $bas, string $bit) use ($personelId, $firmaId): int {
            $stmt = $this->db->prepare("SELECT SUM(COALESCE(sayi, 1)) FROM kacak_kontrol
                WHERE (bildiren_personel_id = :pid OR FIND_IN_SET(:pid2, personel_ids))
                AND firma_id = :fid AND silinme_tarihi IS NULL
                AND onay_durumu IN ('onaylandi', 'beklemede') AND NOT (durum = 'iptal' AND hakedisten_dus = 1)
                AND tarih BETWEEN :bas AND :bit");
            $stmt->execute(['pid' => $personelId, 'pid2' => (string) $personelId, 'fid' => $firmaId, 'bas' => $bas, 'bit' => $bit]);
            return (int) $stmt->fetchColumn();
        };

        $dailyIsler = $isToplam($today, $today) + $sayacToplam($today, $today);
        $monthlyIsler = $isToplam($ayBas, $aySon) + $sayacToplam($ayBas, $aySon);
        $dailyEndeks = $isSayacEkibi ? 0 : $endeksToplam($today, $today);
        $monthlyEndeks = $isSayacEkibi ? 0 : $endeksToplam($ayBas, $aySon);
        $dailyKacak = $kacakToplam($today, $today);
        $monthlyKacak = $kacakToplam($ayBas, $aySon);

        [$departman, $ekipBolge, $siralama] = $this->siralama($personel, $firmaId, $ayBas, $aySon);

        return [
            'today' => $dailyIsler + $dailyEndeks + $dailyKacak,
            'month' => $monthlyIsler + $monthlyEndeks + $monthlyKacak,
            'is_sayac_ekibi' => $isSayacEkibi,
            'is_kacak_ekibi' => $isKacakEkibi,
            'departman' => $departman,
            'ekip_bolge' => $ekipBolge,
            'siralama' => $siralama,
            'details' => [
                'daily_isler' => $dailyIsler,
                'daily_endeks' => $dailyEndeks,
                'daily_kacak' => $dailyKacak,
                'monthly_isler' => $monthlyIsler,
                'monthly_endeks' => $monthlyEndeks,
                'monthly_kacak' => $monthlyKacak,
                'daily_sekme' => $sekmeDagilim($today, $today),
                'monthly_sekme' => $sekmeDagilim($ayBas, $aySon),
            ],
        ];
    }

    private function aktifEkip(PersonelModel $personelModel, TanimlamalarModel $tanimlar, int $personelId): ?object
    {
        foreach ($personelModel->getEkipGecmisi($personelId) as $gecmis) {
            if (empty($gecmis->bitis_tarihi) || $gecmis->bitis_tarihi >= date('Y-m-d')) {
                return $tanimlar->find($gecmis->ekip_kodu_id) ?: null;
            }
        }
        return null;
    }

    private function siralama(object $personel, int $firmaId, string $ayBas, string $aySon): array
    {
        $personelId = (int) $personel->id;
        $personelModel = new PersonelModel();
        $tanimlar = new TanimlamalarModel();
        $kucuk = static fn(string $v): string => mb_strtolower(trim($v), 'UTF-8');

        $ekip = $this->aktifEkip($personelModel, $tanimlar, $personelId);
        $ekipBolge = (string) ($ekip->ekip_bolge ?? '');
        $departman = trim((string) ($personel->departman ?? ''));
        if ($departman === '') {
            $departman = trim((string) ($ekip->tur_adi ?? '')) ?: 'Genel';
        }

        $stmt = $this->db->prepare("SELECT p.id, p.departman,
            (SELECT t.ekip_bolge FROM personel_ekip_gecmisi peg
             LEFT JOIN tanimlamalar t ON peg.ekip_kodu_id = t.id
             WHERE peg.personel_id = p.id AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi >= CURDATE())
             ORDER BY peg.baslangic_tarihi DESC LIMIT 1) AS bolge
            FROM personel p
            WHERE p.firma_id = :fid AND p.silinme_tarihi IS NULL
            AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '0000-00-00')");
        $stmt->execute(['fid' => $firmaId]);

        $departmanIds = [];
        $bolgeIds = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $p) {
            $pDept = trim((string) ($p->departman ?? ''));
            if ($pDept === '') {
                $pEkip = $this->aktifEkip($personelModel, $tanimlar, (int) $p->id);
                $pDept = trim((string) ($pEkip->tur_adi ?? '')) ?: 'Genel';
            }
            if ($kucuk($pDept) === $kucuk($departman)) {
                $departmanIds[] = (int) $p->id;
            }
            if ($ekipBolge !== '' && $kucuk((string) ($p->bolge ?? '')) === $kucuk($ekipBolge)) {
                $bolgeIds[] = (int) $p->id;
            }
        }
        if (!in_array($personelId, $departmanIds, true)) $departmanIds[] = $personelId;
        if ($ekipBolge !== '' && !in_array($personelId, $bolgeIds, true)) $bolgeIds[] = $personelId;

        $skor = $this->db->prepare("SELECT personel_id, SUM(toplam) FROM (
            SELECT t.personel_id, t.sonuclanmis AS toplam FROM yapilan_isler t
            LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
            WHERE t.firma_id = :f1 AND t.silinme_tarihi IS NULL AND t.tarih BETWEEN :b1 AND :e1
            AND tn.is_turu_ucret > 0 AND tn.rapor_sekmesi IS NOT NULL AND tn.rapor_sekmesi != ''
            UNION ALL
            SELECT t.personel_id, t.okunan_abone_sayisi FROM endeks_okuma t
            LEFT JOIN tanimlamalar def ON t.ekip_kodu_id = def.id
            WHERE t.firma_id = :f2 AND t.silinme_tarihi IS NULL AND t.tarih BETWEEN :b2 AND :e2 AND def.tur_adi REGEXP :rx
            UNION ALL
            SELECT t.personel_id, t.is_sayisi FROM sayac_degisim t
            WHERE t.firma_id = :f3 AND t.silinme_tarihi IS NULL AND t.tarih BETWEEN :b3 AND :e3
            UNION ALL
            SELECT t.bildiren_personel_id, COALESCE(t.sayi, 1) FROM kacak_kontrol t
            WHERE t.firma_id = :f4 AND t.silinme_tarihi IS NULL AND t.tarih BETWEEN :b4 AND :e4
            AND t.onay_durumu IN ('onaylandi', 'beklemede') AND NOT (t.durum = 'iptal' AND t.hakedisten_dus = 1)
        ) AS birlesik GROUP BY personel_id");
        $params = ['rx' => self::ENDEKS_EKIP_REGEX];
        for ($i = 1; $i <= 4; $i++) {
            $params["f$i"] = $firmaId;
            $params["b$i"] = $ayBas;
            $params["e$i"] = $aySon;
        }
        $skor->execute($params);
        $skorMap = array_map('intval', $skor->fetchAll(PDO::FETCH_KEY_PAIR));

        $sira = static function (array $ids) use ($skorMap, $personelId): int {
            $liste = [];
            foreach ($ids as $id) {
                $liste[$id] = $skorMap[$id] ?? 0;
            }
            arsort($liste);
            $konum = array_search($personelId, array_keys($liste), true);
            return $konum === false ? 0 : $konum + 1;
        };

        return [$departman, $ekipBolge, [
            'departman_sira' => $sira($departmanIds),
            'departman_kisi' => count($departmanIds),
            'ekip_sira' => $sira($bolgeIds),
            'ekip_kisi' => count($bolgeIds),
        ]];
    }
}
