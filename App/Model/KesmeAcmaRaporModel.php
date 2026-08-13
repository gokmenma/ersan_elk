<?php

namespace App\Model;

use App\Helper\EkipHelper;
use PDO;

class KesmeAcmaRaporModel extends Model
{
    protected $table = 'yapilan_isler';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    /**
     * Kesme/açma birimindeki ekipler: ayarlardaki `ekip_aralik_kesme` aralığına
     * girenler ile mahalle ataması yapılmış ekiplerin birleşimi.
     */
    public function ekipler(): array
    {
        $stmt = $this->db->prepare("SELECT id, tur_adi, ekip_bolge FROM tanimlamalar
            WHERE grup = 'ekip_kodu' AND firma_id = ? AND silinme_tarihi IS NULL
            ORDER BY tur_adi ASC");
        $stmt->execute([$this->firmaId()]);
        $tumu = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $atamali = $this->atamaGormusEkipler();

        $liste = [];
        foreach ($tumu as $ekip) {
            if (in_array((int) $ekip['id'], $atamali, true)
                || EkipHelper::isTeamInTabRange($ekip['tur_adi'], 'kesme')) {
                $ekip['id'] = (int) $ekip['id'];
                $liste[] = $ekip;
            }
        }

        usort($liste, function ($a, $b) {
            return EkipHelper::extractTeamNo($a['tur_adi']) <=> EkipHelper::extractTeamNo($b['tur_adi']);
        });
        return $liste;
    }

    /**
     * Kesme/açma işini fiilen yürüten ekipler: açık ataması, kalan iş girişi
     * ya da son N günde sonuçlanmış işi olanlar.
     */
    public function hareketliEkipler(int $gun = 30): array
    {
        $baslangic = date('Y-m-d', strtotime('-' . $gun . ' day'));

        $stmt = $this->db->prepare("SELECT ekip_id FROM ekip_mahalle_atama WHERE firma_id = ?
            UNION SELECT ekip_id FROM ekip_gunluk_durum WHERE firma_id = ? AND tarih >= ?
            UNION SELECT ekip_kodu_id FROM {$this->table}
                WHERE firma_id = ? AND tarih >= ? AND silinme_tarihi IS NULL AND sonuclanmis > 0");
        $stmt->execute([
            $this->firmaId(), $this->firmaId(), $baslangic,
            $this->firmaId(), $baslangic,
        ]);

        return array_values(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }

    private function atamaGormusEkipler(): array
    {
        $stmt = $this->db->prepare("SELECT DISTINCT ekip_id FROM ekip_mahalle_atama WHERE firma_id = ?");
        $stmt->execute([$this->firmaId()]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function ekipUyeHaritasi(): array
    {
        $stmt = $this->db->prepare("SELECT pg.ekip_kodu_id,
                GROUP_CONCAT(p.adi_soyadi ORDER BY pg.ekip_sefi_mi DESC, p.adi_soyadi ASC SEPARATOR ', ') AS uyeler
            FROM personel_ekip_gecmisi pg
            INNER JOIN personel p ON p.id = pg.personel_id
            WHERE pg.firma_id = ?
              AND pg.baslangic_tarihi <= CURDATE()
              AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= CURDATE())
              AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '0000-00-00')
            GROUP BY pg.ekip_kodu_id");
        $stmt->execute([$this->firmaId()]);

        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $harita[(int) $satir['ekip_kodu_id']] = (string) $satir['uyeler'];
        }
        return $harita;
    }

    /**
     * Ekip x gün x sonuç kırılımı: [ekip_id][tarih][sonuc] = adet
     */
    public function matris(string $baslangic, string $bitis): array
    {
        $stmt = $this->db->prepare("SELECT ekip_kodu_id, tarih, is_emri_sonucu, SUM(sonuclanmis) AS adet
            FROM {$this->table}
            WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL
            GROUP BY ekip_kodu_id, tarih, is_emri_sonucu");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);

        $matris = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $ekipId = (int) $satir['ekip_kodu_id'];
            $sonuc = trim((string) $satir['is_emri_sonucu']) ?: 'TANIMSIZ';
            $matris[$ekipId][$satir['tarih']][$sonuc] = (int) $satir['adet'];
        }
        return $matris;
    }

    public function sonuclar(string $baslangic, string $bitis): array
    {
        $stmt = $this->db->prepare("SELECT is_emri_sonucu, SUM(sonuclanmis) AS adet
            FROM {$this->table}
            WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL
            GROUP BY is_emri_sonucu
            HAVING adet > 0
            ORDER BY adet DESC");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);

        $liste = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $liste[] = trim((string) $satir['is_emri_sonucu']) ?: 'TANIMSIZ';
        }
        return $liste;
    }

    /**
     * M11: bir ekibin verilen tarih aralığında sonuçlandırdığı iş sayısı.
     */
    public function ekipAralikToplamlari(string $baslangic, string $bitis): array
    {
        $stmt = $this->db->prepare("SELECT ekip_kodu_id, tarih, SUM(sonuclanmis) AS adet
            FROM {$this->table}
            WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL
            GROUP BY ekip_kodu_id, tarih");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);

        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $harita[(int) $satir['ekip_kodu_id']][$satir['tarih']] = (int) $satir['adet'];
        }
        return $harita;
    }

    public function gunlukToplamlar(string $baslangic, string $bitis): array
    {
        $stmt = $this->db->prepare("SELECT tarih, SUM(sonuclanmis) AS adet
            FROM {$this->table}
            WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL
            GROUP BY tarih ORDER BY tarih ASC");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);

        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $harita[$satir['tarih']] = (int) $satir['adet'];
        }
        return $harita;
    }

    /**
     * M9: ekibi kalmamış ya da işten ayrılmış personelin üstündeki açık işler.
     */
    public function sahipsizIsler(string $baslangic, string $bitis): array
    {
        $stmt = $this->db->prepare("SELECT y.personel_id, p.adi_soyadi, SUM(y.acik_olanlar) AS acik
            FROM {$this->table} y
            LEFT JOIN personel p ON p.id = y.personel_id
            WHERE y.firma_id = ? AND y.tarih BETWEEN ? AND ? AND y.silinme_tarihi IS NULL
              AND y.acik_olanlar > 0
              AND (
                    (p.isten_cikis_tarihi IS NOT NULL AND p.isten_cikis_tarihi <> '0000-00-00')
                 OR NOT EXISTS (
                        SELECT 1 FROM personel_ekip_gecmisi pg
                        WHERE pg.personel_id = y.personel_id AND pg.firma_id = y.firma_id
                          AND pg.baslangic_tarihi <= CURDATE()
                          AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= CURDATE())
                    )
              )
            GROUP BY y.personel_id, p.adi_soyadi
            HAVING acik > 0
            ORDER BY acik DESC");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sonAktarim(): ?string
    {
        $stmt = $this->db->prepare("SELECT MAX(created_at) FROM {$this->table} WHERE firma_id = ?");
        $stmt->execute([$this->firmaId()]);
        $deger = $stmt->fetchColumn();
        return $deger ?: null;
    }
}
