<?php

namespace App\Model;

use App\Helper\EkipHelper;
use PDO;

/**
 * Anlık aparat bakiyelerinin okunması ve ekip × tip matrisinin üretilmesi.
 * Yazma işlemleri AparatHareketModel üzerinden yapılır.
 */
class AparatStokModel extends Model
{
    protected $table = 'aparat_stok';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    public function bakiye(string $sahipTipi, int $sahipId, int $aparatTipId): int
    {
        $stmt = $this->db->prepare("SELECT adet FROM {$this->table}
            WHERE firma_id = ? AND sahip_tipi = ? AND sahip_id = ? AND aparat_tip_id = ?");
        $stmt->execute([$this->firmaId(), $sahipTipi, $sahipId, $aparatTipId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Bir ekibin tip bazında güncel stoğu: [aparat_tip_id => adet]
     */
    public function ekipBakiyeleri(int $ekipId): array
    {
        $stmt = $this->db->prepare("SELECT aparat_tip_id, adet FROM {$this->table}
            WHERE firma_id = ? AND sahip_tipi = 'ekip' AND sahip_id = ?");
        $stmt->execute([$this->firmaId(), $ekipId]);

        $sonuc = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $sonuc[(int) $satir['aparat_tip_id']] = (int) $satir['adet'];
        }
        return $sonuc;
    }

    /**
     * Tüm havuzların tip bazında bakiyesi: [sahip_tipi][sahip_id][tip_id] => adet
     */
    public function tumBakiyeler(): array
    {
        $stmt = $this->db->prepare("SELECT sahip_tipi, sahip_id, aparat_tip_id, adet
            FROM {$this->table} WHERE firma_id = ?");
        $stmt->execute([$this->firmaId()]);

        $sonuc = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $sonuc[$satir['sahip_tipi']][(int) $satir['sahip_id']][(int) $satir['aparat_tip_id']] = (int) $satir['adet'];
        }
        return $sonuc;
    }

    /**
     * Kesme-açma işi yapan ekipler. Ayarlardaki `ekip_aralik_kesme` aralığı
     * dışında kalsa da stok hareketi görmüş ekipler listeye dahil edilir.
     */
    public function ekipler(bool $sadeceKesme = true): array
    {
        $stmt = $this->db->prepare("SELECT id, tur_adi, ekip_bolge FROM tanimlamalar
            WHERE grup = 'ekip_kodu' AND firma_id = ? AND silinme_tarihi IS NULL
            ORDER BY tur_adi ASC");
        $stmt->execute([$this->firmaId()]);
        $tumEkipler = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$sadeceKesme) {
            return $this->ekipleriSirala($tumEkipler);
        }

        $stokluEkipler = $this->stokHareketiOlanEkipler();

        $liste = [];
        foreach ($tumEkipler as $ekip) {
            $ekipId = (int) $ekip['id'];
            if (in_array($ekipId, $stokluEkipler, true)
                || EkipHelper::isTeamInTabRange($ekip['tur_adi'], 'kesme')) {
                $liste[] = $ekip;
            }
        }

        return $this->ekipleriSirala($liste);
    }

    private function ekipleriSirala(array $ekipler): array
    {
        usort($ekipler, function ($a, $b) {
            return EkipHelper::extractTeamNo($a['tur_adi']) <=> EkipHelper::extractTeamNo($b['tur_adi']);
        });
        return $ekipler;
    }

    private function stokHareketiOlanEkipler(): array
    {
        $stmt = $this->db->prepare("SELECT DISTINCT sahip_id FROM {$this->table}
            WHERE firma_id = ? AND sahip_tipi = 'ekip' AND sahip_id > 0");
        $stmt->execute([$this->firmaId()]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Personelin belirtilen tarihte geçerli olan ekibi (varsayılan: bugün).
     *
     * Çevrimdışı kayıt günler sonra gönderilebildiği için hareket, kaydın
     * gönderildiği gün değil işlemin yapıldığı gün geçerli olan ekibe yazılır;
     * aksi halde ekip değiştiren personelin eski kaydı yanlış ekipten düşer.
     */
    public function aktifEkip(int $personelId, ?string $tarih = null): ?array
    {
        $tarih = $tarih ?: date('Y-m-d');

        $stmt = $this->db->prepare("SELECT pg.ekip_kodu_id AS id, t.tur_adi, t.ekip_bolge, pg.ekip_sefi_mi
            FROM personel_ekip_gecmisi pg
            INNER JOIN tanimlamalar t ON t.id = pg.ekip_kodu_id
            WHERE pg.personel_id = ? AND pg.firma_id = ?
              AND pg.baslangic_tarihi <= ?
              AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= ?)
            ORDER BY pg.baslangic_tarihi DESC
            LIMIT 1");
        $stmt->execute([$personelId, $this->firmaId(), $tarih, $tarih]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Bir ekibin bugün geçerli üyeleri. Stok ekibe zimmetli olsa da ekranlarda
     * ekip kodu yerine kimlerin çalıştığı gösterilir.
     */
    public function ekipUyeleri(int $ekipId): array
    {
        $stmt = $this->db->prepare("SELECT p.id, p.adi_soyadi, pg.ekip_sefi_mi
            FROM personel_ekip_gecmisi pg
            INNER JOIN personel p ON p.id = pg.personel_id
            WHERE pg.ekip_kodu_id = ? AND pg.firma_id = ?
              AND pg.baslangic_tarihi <= CURDATE()
              AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= CURDATE())
              AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '0000-00-00')
            ORDER BY pg.ekip_sefi_mi DESC, p.adi_soyadi ASC");
        $stmt->execute([$ekipId, $this->firmaId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tüm ekiplerin üye adları: [ekip_id => "Ad Soyad, Ad Soyad"]
     */
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

    public function ekipAdi(int $ekipId): string
    {
        $stmt = $this->db->prepare("SELECT tur_adi FROM tanimlamalar
            WHERE id = ? AND grup = 'ekip_kodu' AND firma_id = ?");
        $stmt->execute([$ekipId, $this->firmaId()]);
        return (string) $stmt->fetchColumn();
    }

    public function ekipGecerliMi(int $ekipId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM tanimlamalar
            WHERE id = ? AND grup = 'ekip_kodu' AND firma_id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$ekipId, $this->firmaId()]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Panel ana ekranı: satırlar = ekipler + depo + saha + hurda + kayıp,
     * sütunlar = aparat tipleri.
     */
    public function matris(): array
    {
        $tipModel = new AparatTipiModel();
        $tipler = $tipModel->listele(false);
        $bakiyeler = $this->tumBakiyeler();
        $ekipler = $this->ekipler();
        $uyeler = $this->ekipUyeHaritasi();

        $satirlar = [];
        $sutunToplam = [];
        $genelToplam = 0;

        foreach ($tipler as $tip) {
            $sutunToplam[(int) $tip['id']] = 0;
        }

        foreach ($ekipler as $ekip) {
            $ekipId = (int) $ekip['id'];
            $satir = [
                'sahip_tipi' => 'ekip',
                'sahip_id' => $ekipId,
                'baslik' => $uyeler[$ekipId] ?? $ekip['tur_adi'],
                'ekip_kodu' => $ekip['tur_adi'],
                'uyeler' => $uyeler[$ekipId] ?? '',
                'bolge' => $ekip['ekip_bolge'],
                'adetler' => [],
                'toplam' => 0,
                'negatif' => false,
            ];

            foreach ($tipler as $tip) {
                $tipId = (int) $tip['id'];
                $adet = (int) ($bakiyeler['ekip'][$ekipId][$tipId] ?? 0);
                $satir['adetler'][$tipId] = $adet;
                $satir['toplam'] += $adet;
                $sutunToplam[$tipId] += $adet;
                $genelToplam += $adet;
                if ($adet < 0) {
                    $satir['negatif'] = true;
                }
            }

            $satirlar[] = $satir;
        }

        foreach (['depo', 'saha', 'hurda', 'kayip'] as $havuz) {
            $satir = [
                'sahip_tipi' => $havuz,
                'sahip_id' => 0,
                'baslik' => AparatHareketModel::HAVUZLAR[$havuz],
                'ekip_kodu' => '',
                'uyeler' => '',
                'bolge' => '',
                'adetler' => [],
                'toplam' => 0,
                'negatif' => false,
            ];

            foreach ($tipler as $tip) {
                $tipId = (int) $tip['id'];
                $adet = (int) ($bakiyeler[$havuz][0][$tipId] ?? 0);
                $satir['adetler'][$tipId] = $adet;
                $satir['toplam'] += $adet;
                $sutunToplam[$tipId] += $adet;
                $genelToplam += $adet;
                if ($adet < 0 && $havuz !== 'kayip') {
                    $satir['negatif'] = true;
                }
            }

            $satirlar[] = $satir;
        }

        return [
            'tipler' => $tipler,
            'satirlar' => $satirlar,
            'sutun_toplam' => $sutunToplam,
            'genel_toplam' => $genelToplam,
        ];
    }

    /**
     * Sahada takılı aparatların abone bazlı dökümü (kaç gündür takılı).
     */
    public function sahadaTakililar(?int $aparatTipId = null, int $minGun = 0): array
    {
        $sql = "SELECT i.id, i.abone_no, i.sayac_no, i.abone_adi, i.ilce, i.mahalle,
                       i.tarih, i.adet, i.aparat_tip_id, t.ad AS aparat_adi,
                       ek.tur_adi AS ekip_adi, p.adi_soyadi AS personel_adi,
                       DATEDIFF(CURDATE(), i.tarih) AS gun_sayisi
                FROM kesme_acma_islem i
                LEFT JOIN aparat_tipleri t ON t.id = i.aparat_tip_id
                LEFT JOIN tanimlamalar ek ON ek.id = i.ekip_id
                LEFT JOIN personel p ON p.id = i.personel_id
                WHERE i.firma_id = ?
                  AND i.islem_tipi = 'kesme'
                  AND i.durum = 'aktif'
                  AND i.silinme_tarihi IS NULL
                  AND i.aparatsiz = 0
                  AND i.abone_no IS NOT NULL AND i.abone_no <> ''
                  AND NOT EXISTS (
                        SELECT 1 FROM kesme_acma_islem a
                        WHERE a.firma_id = i.firma_id
                          AND a.abone_no = i.abone_no
                          AND a.islem_tipi = 'acma'
                          AND a.durum = 'aktif'
                          AND a.silinme_tarihi IS NULL
                          AND a.tarih >= i.tarih
                  )";

        $parametreler = [$this->firmaId()];

        if ($aparatTipId) {
            $sql .= " AND i.aparat_tip_id = ?";
            $parametreler[] = $aparatTipId;
        }
        if ($minGun > 0) {
            $sql .= " AND DATEDIFF(CURDATE(), i.tarih) >= ?";
            $parametreler[] = $minGun;
        }

        $sql .= " ORDER BY i.tarih ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametreler);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
