<?php

namespace App\Model;

use PDO;

class KesmeNobetModel extends Model
{
    protected $table = 'kesme_acma_nobet_taslak';

    const ILCELER = ['turkoglu' => 'Türkoğlu', 'pazarcik' => 'Pazarcık'];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    public function sahaPlani(string $baslangic, string $bitis): array
    {
        $stmt = $this->db->prepare("SELECT n.id, n.nobet_tarihi AS tarih, n.personel_id,
                p.adi_soyadi,
                COALESCE((SELECT pg.ekip_kodu_id FROM personel_ekip_gecmisi pg
                    WHERE pg.firma_id = n.firma_id AND pg.personel_id = n.personel_id
                      AND pg.baslangic_tarihi <= n.nobet_tarihi
                      AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= n.nobet_tarihi)
                    ORDER BY pg.baslangic_tarihi DESC LIMIT 1), p.ekip_no) AS ekip_id,
                COALESCE((SELECT t2.tur_adi FROM personel_ekip_gecmisi pg2
                    INNER JOIN tanimlamalar t2 ON t2.id = pg2.ekip_kodu_id
                    WHERE pg2.firma_id = n.firma_id AND pg2.personel_id = n.personel_id
                      AND pg2.baslangic_tarihi <= n.nobet_tarihi
                      AND (pg2.bitis_tarihi IS NULL OR pg2.bitis_tarihi >= n.nobet_tarihi)
                    ORDER BY pg2.baslangic_tarihi DESC LIMIT 1), t.tur_adi) AS ekip_adi
            FROM nobetler n
            INNER JOIN personel p ON p.id = n.personel_id
            LEFT JOIN tanimlamalar t ON t.id = p.ekip_no
            WHERE n.firma_id = ? AND n.nobet_tarihi BETWEEN ? AND ?
              AND n.silinme_tarihi IS NULL
              AND (n.durum IS NULL OR n.durum NOT IN ('reddedildi', 'iptal', 'talep_edildi'))
            ORDER BY n.nobet_tarihi ASC, n.id ASC");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);

        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $kayit = [
                'personel_id' => (int) $satir['personel_id'],
                'personel' => $satir['adi_soyadi'],
                'ekip_id' => (int) $satir['ekip_id'],
                'ekip_adi' => $satir['ekip_adi'],
                'elle' => 0,
                'kaynak' => 'canli',
            ];
            if (isset($harita[$satir['tarih']])) {
                $harita[$satir['tarih']]['personel'] .= ', ' . $satir['adi_soyadi'];
                $harita[$satir['tarih']]['coklu'] = 1;
            } else {
                $harita[$satir['tarih']] = $kayit;
            }
        }

        if (!$this->taslakTablosuVar()) {
            return $harita;
        }

        $stmt = $this->db->prepare("SELECT n.tarih, n.personel_id, n.elle_degistirildi,
                p.adi_soyadi,
                (SELECT pg.ekip_kodu_id FROM personel_ekip_gecmisi pg
                    WHERE pg.firma_id = n.firma_id AND pg.personel_id = n.personel_id
                      AND pg.baslangic_tarihi <= n.tarih
                      AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= n.tarih)
                    ORDER BY pg.baslangic_tarihi DESC LIMIT 1) AS ekip_id,
                (SELECT t.tur_adi FROM personel_ekip_gecmisi pg
                    INNER JOIN tanimlamalar t ON t.id = pg.ekip_kodu_id
                    WHERE pg.firma_id = n.firma_id AND pg.personel_id = n.personel_id
                      AND pg.baslangic_tarihi <= n.tarih
                      AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= n.tarih)
                    ORDER BY pg.baslangic_tarihi DESC LIMIT 1) AS ekip_adi
            FROM {$this->table} n
            INNER JOIN personel p ON p.id = n.personel_id
            WHERE n.firma_id = ? AND n.tarih BETWEEN ? AND ?
              AND n.is_active = 1 AND n.deleted_at IS NULL
            ORDER BY n.tarih ASC");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $harita[$satir['tarih']] = [
                'personel_id' => (int) $satir['personel_id'],
                'personel' => $satir['adi_soyadi'],
                'ekip_id' => (int) $satir['ekip_id'],
                'ekip_adi' => $satir['ekip_adi'],
                'elle' => (int) $satir['elle_degistirildi'],
                'kaynak' => 'taslak',
            ];
        }
        return $harita;
    }

    public function sahaYaz(string $tarih, ?int $personelId, bool $elle, ?int $olusturanId): bool
    {
        if (!$this->taslakTablosuVar()) {
            throw new \RuntimeException('Kesme/Açma nöbet taslak tablosu kurulmamış.');
        }
        $sil = $this->db->prepare("UPDATE {$this->table}
            SET deleted_at = NOW(), is_active = 0
            WHERE firma_id = ? AND tarih = ? AND is_active = 1 AND deleted_at IS NULL");
        $sil->execute([$this->firmaId(), $tarih]);
        if ($personelId === null) return true;

        $tip = in_array((int) date('w', strtotime($tarih)), [0, 6], true) ? 'hafta_sonu' : 'standart';
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, personel_id, tarih, nobet_tipi, elle_degistirildi, olusturan_id)
            VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$this->firmaId(), $personelId, $tarih, $tip, $elle ? 1 : 0, $olusturanId]);
    }

    public function otomatikGunleriSil(string $baslangic, string $bitis): bool
    {
        if (!$this->taslakTablosuVar()) return false;
        $stmt = $this->db->prepare("UPDATE {$this->table}
            SET deleted_at = NOW(), is_active = 0
            WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND elle_degistirildi = 0
              AND is_active = 1 AND deleted_at IS NULL");
        return $stmt->execute([$this->firmaId(), $baslangic, $bitis]);
    }

    private function taslakTablosuVar(): bool
    {
        static $var = null;
        if ($var === null) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $stmt->execute([$this->table]);
            $var = (int) $stmt->fetchColumn() > 0;
        }
        return $var;
    }

    public function bekleyenDegisimTalebiVar(string $tarih): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*)
            FROM nobetler n
            INNER JOIN nobet_degisim_talepleri dt ON dt.nobet_id = n.id
            WHERE n.firma_id = ? AND n.nobet_tarihi = ?
              AND n.silinme_tarihi IS NULL
              AND dt.durum IN ('beklemede', 'personel_onayladi')");
        $stmt->execute([$this->firmaId(), $tarih]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function sahaPersonelleri(string $tarih, array $ekipIds): array
    {
        if (!$ekipIds) return [];
        $yerler = implode(',', array_fill(0, count($ekipIds), '?'));
        $stmt = $this->db->prepare("SELECT p.id, p.adi_soyadi, pg.ekip_kodu_id AS ekip_id,
                t.tur_adi AS ekip_adi
            FROM personel_ekip_gecmisi pg
            INNER JOIN personel p ON p.id = pg.personel_id
            INNER JOIN tanimlamalar t ON t.id = pg.ekip_kodu_id
            WHERE pg.firma_id = ? AND pg.baslangic_tarihi <= ?
              AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= ?)
              AND pg.ekip_kodu_id IN ($yerler)
              AND p.silinme_tarihi IS NULL
              AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '0000-00-00' OR p.isten_cikis_tarihi >= ?)
            ORDER BY t.tur_adi ASC, pg.ekip_sefi_mi DESC, p.adi_soyadi ASC");
        $stmt->execute(array_merge([$this->firmaId(), $tarih, $tarih], $ekipIds, [$tarih]));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ilcePlani(string $baslangic, string $bitis): array
    {
        $stmt = $this->db->prepare("SELECT i.hafta_basi, i.ilce, i.ekip_id, i.elle_degistirildi, t.tur_adi AS ekip_adi
            FROM nobet_ilce_plani i
            LEFT JOIN tanimlamalar t ON t.id = i.ekip_id
            WHERE i.firma_id = ? AND i.hafta_basi BETWEEN ? AND ?
            ORDER BY i.hafta_basi ASC");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);

        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $harita[$satir['hafta_basi']][$satir['ilce']] = [
                'ekip_id' => (int) $satir['ekip_id'],
                'ekip_adi' => $satir['ekip_adi'],
                'elle' => (int) $satir['elle_degistirildi'],
            ];
        }
        return $harita;
    }

    public function ilceYaz(string $haftaBasi, string $ilce, int $ekipId, bool $elle, ?int $olusturanId): bool
    {
        $stmt = $this->db->prepare("INSERT INTO nobet_ilce_plani
            (firma_id, hafta_basi, ilce, ekip_id, elle_degistirildi, olusturan_id) VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE ekip_id = VALUES(ekip_id),
                elle_degistirildi = VALUES(elle_degistirildi), olusturan_id = VALUES(olusturan_id)");
        return $stmt->execute([$this->firmaId(), $haftaBasi, $ilce, $ekipId, $elle ? 1 : 0, $olusturanId]);
    }

    public function ilceGecmisi(string $oncesi): array
    {
        $stmt = $this->db->prepare("SELECT ekip_id, MAX(hafta_basi) AS son_hafta
            FROM nobet_ilce_plani
            WHERE firma_id = ? AND hafta_basi < ?
            GROUP BY ekip_id");
        $stmt->execute([$this->firmaId(), $oncesi]);

        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $harita[(int) $satir['ekip_id']] = $satir['son_hafta'];
        }
        return $harita;
    }

    public function telefonPlani(string $baslangic, string $bitis): array
    {
        $stmt = $this->db->prepare("SELECT tn.tarih, tn.personel_id, tn.elle_degistirildi, p.adi_soyadi
            FROM telefon_nobet tn
            LEFT JOIN personel p ON p.id = tn.personel_id
            WHERE tn.firma_id = ? AND tn.tarih BETWEEN ? AND ?
            ORDER BY tn.tarih ASC");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);

        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $harita[$satir['tarih']] = [
                'personel_id' => (int) $satir['personel_id'],
                'adi_soyadi' => $satir['adi_soyadi'],
                'elle' => (int) $satir['elle_degistirildi'],
            ];
        }
        return $harita;
    }

    public function telefonYaz(string $tarih, ?int $personelId, bool $elle, ?int $olusturanId = null): bool
    {
        if (!$personelId) {
            $stmt = $this->db->prepare("DELETE FROM telefon_nobet WHERE firma_id = ? AND tarih = ?");
            return $stmt->execute([$this->firmaId(), $tarih]);
        }
        $stmt = $this->db->prepare("INSERT INTO telefon_nobet
            (firma_id, tarih, personel_id, elle_degistirildi) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE personel_id = VALUES(personel_id),
                elle_degistirildi = VALUES(elle_degistirildi)");
        return $stmt->execute([$this->firmaId(), $tarih, $personelId, $elle ? 1 : 0]);
    }

    public function telefonOtomatikSil(string $baslangic, string $bitis): bool
    {
        $stmt = $this->db->prepare("DELETE FROM telefon_nobet
            WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND elle_degistirildi = 0");
        return $stmt->execute([$this->firmaId(), $baslangic, $bitis]);
    }

    /**
     * K3: şirket aracı kullanan personeli olan ekipler ilçeye gönderilmez.
     */
    public function sirketAracliEkipler(): array
    {
        $ekipler = [];
        if ($this->sirketAraciSutunuVar()) {
            $stmt = $this->db->prepare("SELECT DISTINCT pg.ekip_kodu_id
                FROM personel_ekip_gecmisi pg
                INNER JOIN personel p ON p.id = pg.personel_id
                WHERE pg.firma_id = ? AND p.sirket_araci = 1
                  AND pg.baslangic_tarihi <= CURDATE()
                  AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= CURDATE())");
            $stmt->execute([$this->firmaId()]);
            $ekipler = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $stmt = $this->db->prepare("SELECT deger FROM kesme_acma_kural_degeri
            WHERE firma_id = ? AND kural_kodu = 'nobet_arac_kisitli_ekipler' LIMIT 1");
        $stmt->execute([$this->firmaId()]);
        $adlar = json_decode((string) $stmt->fetchColumn(), true);
        if (is_array($adlar) && $adlar) {
            $yerTutucu = implode(',', array_fill(0, count($adlar), '?'));
            $stmt = $this->db->prepare("SELECT id FROM tanimlamalar
                WHERE firma_id = ? AND grup = 'ekip_kodu' AND silinme_tarihi IS NULL AND tur_adi IN ($yerTutucu)");
            $stmt->execute(array_merge([$this->firmaId()], array_values($adlar)));
            $ekipler = array_merge($ekipler, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        }
        return array_values(array_unique($ekipler));
    }

    private function sirketAraciSutunuVar(): bool
    {
        return self::sutunVar('personel', 'sirket_araci');
    }

    public static function sutunVar(string $tablo, string $sutun): bool
    {
        static $onbellek = [];
        $anahtar = $tablo . '.' . $sutun;

        if (!array_key_exists($anahtar, $onbellek)) {
            $model = new self();
            $stmt = $model->db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute([$tablo, $sutun]);
            $onbellek[$anahtar] = (int) $stmt->fetchColumn() > 0;
        }

        return $onbellek[$anahtar];
    }

    /**
     * Telefon nöbetine girecek personel: personel kartında "telefon nöbeti
     * tutar" işaretli olanlar. Ayar sütunu yoksa aktif personelin tamamı döner.
     */
    public function telefonHavuzu(): array
    {
        $stmt = $this->db->prepare("SELECT deger FROM kesme_acma_kural_degeri
            WHERE firma_id = ? AND kural_kodu = 'nobet_telefon_personelleri' LIMIT 1");
        $stmt->execute([$this->firmaId()]);
        $kuralPersonelleri = json_decode((string) $stmt->fetchColumn(), true);

        $parametreler = [$this->firmaId()];
        if (is_array($kuralPersonelleri) && $kuralPersonelleri) {
            $yerTutucu = implode(',', array_fill(0, count($kuralPersonelleri), '?'));
            $kosul = " AND adi_soyadi IN ($yerTutucu)";
            $parametreler = array_merge($parametreler, array_values($kuralPersonelleri));
        } else {
            $kosul = self::sutunVar('personel', 'telefon_nobeti_tutar') ? ' AND telefon_nobeti_tutar = 1' : '';
        }

        $stmt = $this->db->prepare("SELECT id, adi_soyadi FROM personel
            WHERE firma_id = ? AND (silinme_tarihi IS NULL)
              AND (isten_cikis_tarihi IS NULL OR isten_cikis_tarihi = '0000-00-00')" . $kosul . "
            ORDER BY adi_soyadi ASC");
        $stmt->execute($parametreler);
        $liste = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$liste && is_array($kuralPersonelleri) && $kuralPersonelleri) {
            $yedekKosul = self::sutunVar('personel', 'telefon_nobeti_tutar') ? ' AND telefon_nobeti_tutar = 1' : '';
            $stmt = $this->db->prepare("SELECT id, adi_soyadi FROM personel
                WHERE firma_id = ? AND silinme_tarihi IS NULL
                  AND (isten_cikis_tarihi IS NULL OR isten_cikis_tarihi = '0000-00-00')" . $yedekKosul . "
                ORDER BY adi_soyadi ASC");
            $stmt->execute([$this->firmaId()]);
            $liste = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $liste;
    }

    public function telefonKuralSecenekleri(): array
    {
        $stmt = $this->db->prepare("SELECT id, adi_soyadi FROM personel
            WHERE firma_id = ? AND silinme_tarihi IS NULL
              AND (isten_cikis_tarihi IS NULL OR isten_cikis_tarihi = '0000-00-00')
            ORDER BY adi_soyadi ASC");
        $stmt->execute([$this->firmaId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
