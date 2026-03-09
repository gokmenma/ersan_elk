<?php

namespace App\Model;

use App\Core\Db;
use PDO;

/**
 * Maliyet Raporu Model
 * 
 * Tüm gider kaynaklarını UNION ALL ile birleştirerek
 * aylık, yıllık ve kategorize edilmiş raporlar üretir.
 *
 * Kaynaklar:
 *   - arac_yakit_kayitlari    → Araç / Yakıt
 *   - arac_bakim_kayitlari    → Araç / Bakım
 *   - arac_servis_kayitlari   → Araç / Servis
 *   - arac_sigorta_kayitlari  → Araç / Sigorta
 *   - demirbas_servis_kayitlari → Demirbaş / Servis
 *   - bordro_personel          → Personel / Maaş
 *   - manuel_giderler          → Kullanıcı tanımlı
 */
class MaliyetRaporuModel extends Db
{
    private int $firmaId;
    private ?array $manuelGiderColumns = null;

    public function __construct()
    {
        parent::__construct();
        $this->firmaId = (int) ($_SESSION['firma_id'] ?? 0);
    }

    /* ------------------------------------------------------------------ */
    /*  BİRLEŞİK UNION SORGUSU                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Tüm giderleri birleştirerek döndürür
     * Her satır: tarih, kategori, alt_kategori, tutar, kaynak_tablo, aciklama
     */
    public function getAll(?string $baslangic = null, ?string $bitis = null, ?string $kategori = null): array
    {
        $parts  = [];
        $params = [];

        $this->addAracYakit($parts, $params, $baslangic, $bitis, $kategori);
        $this->addAracBakim($parts, $params, $baslangic, $bitis, $kategori);
        $this->addAracServis($parts, $params, $baslangic, $bitis, $kategori);
        $this->addAracSigorta($parts, $params, $baslangic, $bitis, $kategori);
        $this->addDemirbasServis($parts, $params, $baslangic, $bitis, $kategori);
        $this->addPersonelBordro($parts, $params, $baslangic, $bitis, $kategori);
        $this->addManuelGiderler($parts, $params, $baslangic, $bitis, $kategori);

        if (empty($parts)) {
            return [];
        }

        $sql = implode("\nUNION ALL\n", $parts) . "\nORDER BY tarih DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /* ------------------------------------------------------------------ */
    /*  ÖZET SORGULARI                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Kategori bazlı toplam
     */
    public function getCategorySummary(?string $baslangic = null, ?string $bitis = null): array
    {
        $parts  = [];
        $params = [];

        $this->addAracYakit($parts, $params, $baslangic, $bitis);
        $this->addAracBakim($parts, $params, $baslangic, $bitis);
        $this->addAracServis($parts, $params, $baslangic, $bitis);
        $this->addAracSigorta($parts, $params, $baslangic, $bitis);
        $this->addDemirbasServis($parts, $params, $baslangic, $bitis);
        $this->addPersonelBordro($parts, $params, $baslangic, $bitis);
        $this->addManuelGiderler($parts, $params, $baslangic, $bitis);

        if (empty($parts)) {
            return [];
        }

        $inner = implode("\nUNION ALL\n", $parts);

        $sql = "SELECT kategori, SUM(tutar) as toplam
                FROM ({$inner}) AS unified
                GROUP BY kategori
                ORDER BY toplam DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Aylık toplam (belirli yıl için)
     */
    public function getMonthlyTotals(int $yil): array
    {
        $baslangic = "{$yil}-01-01";
        $bitis     = "{$yil}-12-31";

        $parts  = [];
        $params = [];

        $this->addAracYakit($parts, $params, $baslangic, $bitis);
        $this->addAracBakim($parts, $params, $baslangic, $bitis);
        $this->addAracServis($parts, $params, $baslangic, $bitis);
        $this->addAracSigorta($parts, $params, $baslangic, $bitis);
        $this->addDemirbasServis($parts, $params, $baslangic, $bitis);
        $this->addPersonelBordro($parts, $params, $baslangic, $bitis);
        $this->addManuelGiderler($parts, $params, $baslangic, $bitis);

        if (empty($parts)) {
            return [];
        }

        $inner = implode("\nUNION ALL\n", $parts);

        $sql = "SELECT
                    MONTH(tarih) as ay,
                    kategori,
                    SUM(tutar) as toplam
                FROM ({$inner}) AS unified
                GROUP BY MONTH(tarih), kategori
                ORDER BY ay ASC, kategori ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Yıllık toplam
     */
    public function getYearlyTotals(): array
    {
        $parts  = [];
        $params = [];

        $this->addAracYakit($parts, $params);
        $this->addAracBakim($parts, $params);
        $this->addAracServis($parts, $params);
        $this->addAracSigorta($parts, $params);
        $this->addDemirbasServis($parts, $params);
        $this->addPersonelBordro($parts, $params);
        $this->addManuelGiderler($parts, $params);

        if (empty($parts)) {
            return [];
        }

        $inner = implode("\nUNION ALL\n", $parts);

        $sql = "SELECT
                    YEAR(tarih) as yil,
                    kategori,
                    SUM(tutar) as toplam
                FROM ({$inner}) AS unified
                GROUP BY YEAR(tarih), kategori
                ORDER BY yil DESC, kategori ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Genel toplam (tek sayı)
     */
    public function getGrandTotal(?string $baslangic = null, ?string $bitis = null): float
    {
        $parts  = [];
        $params = [];

        $this->addAracYakit($parts, $params, $baslangic, $bitis);
        $this->addAracBakim($parts, $params, $baslangic, $bitis);
        $this->addAracServis($parts, $params, $baslangic, $bitis);
        $this->addAracSigorta($parts, $params, $baslangic, $bitis);
        $this->addDemirbasServis($parts, $params, $baslangic, $bitis);
        $this->addPersonelBordro($parts, $params, $baslangic, $bitis);
        $this->addManuelGiderler($parts, $params, $baslangic, $bitis);

        if (empty($parts)) {
            return 0;
        }

        $inner = implode("\nUNION ALL\n", $parts);

        $sql = "SELECT COALESCE(SUM(tutar), 0) as toplam FROM ({$inner}) AS unified";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    /* ------------------------------------------------------------------ */
    /*  ALT SORGU BİLEŞENLERİ                                             */
    /* ------------------------------------------------------------------ */

    private function addAracYakit(array &$parts, array &$params, ?string $bas = null, ?string $bit = null, ?string $kat = null): void
    {
        if ($kat && $kat !== 'Araç') return;

        $kategoriExpr = $this->utf8Expr("'Araç'");
        $altKategoriExpr = $this->utf8Expr("'Yakıt'");
        $kaynakExpr = $this->utf8Expr("'arac_yakit_kayitlari'");
        $aciklamaExpr = $this->utf8Expr("CONCAT(a.plaka, ' - ', y.yakit_miktari, ' Lt')");

        $sql = "SELECT y.tarih,
                       {$kategoriExpr} AS kategori,
                       {$altKategoriExpr} AS alt_kategori,
                       y.toplam_tutar AS tutar,
                       {$kaynakExpr} AS kaynak_tablo,
                       {$aciklamaExpr} AS aciklama
                FROM arac_yakit_kayitlari y
                INNER JOIN araclar a ON y.arac_id = a.id
                                WHERE y.silinme_tarihi IS NULL
                                    AND y.firma_id = :f_yk
                                    AND COALESCE(y.toplam_tutar, 0) <> 0";
        $params['f_yk'] = $this->firmaId;

        if ($bas && $bit) {
            $sql .= " AND y.tarih BETWEEN :bas_yk AND :bit_yk";
            $params['bas_yk'] = $bas;
            $params['bit_yk'] = $bit;
        }

        $parts[] = "({$sql})";
    }

    private function addAracBakim(array &$parts, array &$params, ?string $bas = null, ?string $bit = null, ?string $kat = null): void
    {
        if ($kat && $kat !== 'Araç') return;

        $kategoriExpr = $this->utf8Expr("'Araç'");
        $altKategoriExpr = $this->utf8Expr("CONCAT('Bakım - ', b.bakim_tipi)");
        $kaynakExpr = $this->utf8Expr("'arac_bakim_kayitlari'");
        $aciklamaExpr = $this->utf8Expr("CONCAT(a.plaka, ' - ', COALESCE(b.servis_adi, LEFT(b.aciklama, 80)))");

        $sql = "SELECT b.tarih,
                       {$kategoriExpr} AS kategori,
                       {$altKategoriExpr} AS alt_kategori,
                       b.tutar,
                       {$kaynakExpr} AS kaynak_tablo,
                       {$aciklamaExpr} AS aciklama
                FROM arac_bakim_kayitlari b
                INNER JOIN araclar a ON b.arac_id = a.id
                                WHERE b.silinme_tarihi IS NULL
                                    AND b.firma_id = :f_bk
                                    AND COALESCE(b.tutar, 0) <> 0";
        $params['f_bk'] = $this->firmaId;

        if ($bas && $bit) {
            $sql .= " AND b.tarih BETWEEN :bas_bk AND :bit_bk";
            $params['bas_bk'] = $bas;
            $params['bit_bk'] = $bit;
        }

        $parts[] = "({$sql})";
    }

    private function addAracServis(array &$parts, array &$params, ?string $bas = null, ?string $bit = null, ?string $kat = null): void
    {
        if ($kat && $kat !== 'Araç') return;

        $kategoriExpr = $this->utf8Expr("'Araç'");
        $altKategoriExpr = $this->utf8Expr("'Servis'");
        $kaynakExpr = $this->utf8Expr("'arac_servis_kayitlari'");
        $aciklamaExpr = $this->utf8Expr("CONCAT(a.plaka, ' - ', COALESCE(LEFT(s.servis_nedeni, 80), ''))");

        $sql = "SELECT s.servis_tarihi AS tarih,
                       {$kategoriExpr} AS kategori,
                       {$altKategoriExpr} AS alt_kategori,
                       s.tutar,
                       {$kaynakExpr} AS kaynak_tablo,
                       {$aciklamaExpr} AS aciklama
                FROM arac_servis_kayitlari s
                INNER JOIN araclar a ON s.arac_id = a.id
                                WHERE s.silinme_tarihi IS NULL
                                    AND s.firma_id = :f_sv
                                    AND COALESCE(s.tutar, 0) <> 0";
        $params['f_sv'] = $this->firmaId;

        if ($bas && $bit) {
            $sql .= " AND s.servis_tarihi BETWEEN :bas_sv AND :bit_sv";
            $params['bas_sv'] = $bas;
            $params['bit_sv'] = $bit;
        }

        $parts[] = "({$sql})";
    }

    private function addAracSigorta(array &$parts, array &$params, ?string $bas = null, ?string $bit = null, ?string $kat = null): void
    {
        if ($kat && $kat !== 'Araç') return;

        $kategoriExpr = $this->utf8Expr("'Araç'");
        $altKategoriExpr = $this->utf8Expr("CONCAT('Sigorta - ', si.sigorta_tipi)");
        $kaynakExpr = $this->utf8Expr("'arac_sigorta_kayitlari'");
        $aciklamaExpr = $this->utf8Expr("CONCAT(a.plaka, ' - ', COALESCE(si.sigorta_sirketi, ''), ' ', COALESCE(si.police_no, ''))");

        $sql = "SELECT si.baslangic_tarihi AS tarih,
                       {$kategoriExpr} AS kategori,
                       {$altKategoriExpr} AS alt_kategori,
                       si.prim_tutari AS tutar,
                       {$kaynakExpr} AS kaynak_tablo,
                       {$aciklamaExpr} AS aciklama
                FROM arac_sigorta_kayitlari si
                INNER JOIN araclar a ON si.arac_id = a.id
                                WHERE si.silinme_tarihi IS NULL
                                    AND si.firma_id = :f_sg
                                    AND COALESCE(si.prim_tutari, 0) <> 0";
        $params['f_sg'] = $this->firmaId;

        if ($bas && $bit) {
            $sql .= " AND si.baslangic_tarihi BETWEEN :bas_sg AND :bit_sg";
            $params['bas_sg'] = $bas;
            $params['bit_sg'] = $bit;
        }

        $parts[] = "({$sql})";
    }

    private function addDemirbasServis(array &$parts, array &$params, ?string $bas = null, ?string $bit = null, ?string $kat = null): void
    {
        if ($kat && $kat !== 'Demirbaş') return;

        $kategoriExpr = $this->utf8Expr("'Demirbaş'");
        $altKategoriExpr = $this->utf8Expr("'Servis'");
        $kaynakExpr = $this->utf8Expr("'demirbas_servis_kayitlari'");
        $aciklamaExpr = $this->utf8Expr("CONCAT(d.demirbas_adi, ' (', d.demirbas_no, ')')");

        $sql = "SELECT ds.servis_tarihi AS tarih,
                       {$kategoriExpr} AS kategori,
                       {$altKategoriExpr} AS alt_kategori,
                       ds.tutar,
                       {$kaynakExpr} AS kaynak_tablo,
                       {$aciklamaExpr} AS aciklama
                FROM demirbas_servis_kayitlari ds
                INNER JOIN demirbas d ON ds.demirbas_id = d.id
                                WHERE ds.silinme_tarihi IS NULL
                                    AND ds.firma_id = :f_ds
                                    AND COALESCE(ds.tutar, 0) <> 0";
        $params['f_ds'] = $this->firmaId;

        if ($bas && $bit) {
            $sql .= " AND ds.servis_tarihi BETWEEN :bas_ds AND :bit_ds";
            $params['bas_ds'] = $bas;
            $params['bit_ds'] = $bit;
        }

        $parts[] = "({$sql})";
    }

    private function addPersonelBordro(array &$parts, array &$params, ?string $bas = null, ?string $bit = null, ?string $kat = null): void
    {
        if ($kat && $kat !== 'Personel') return;

        $kategoriExpr = $this->utf8Expr("'Personel'");
        $altKategoriExpr = $this->utf8Expr("'Maaş'");
        $kaynakExpr = $this->utf8Expr("'bordro_personel'");
        $aciklamaExpr = $this->utf8Expr("CONCAT(p.adi_soyadi, ' - ', bd.donem_adi)");

        $sql = "SELECT bd.baslangic_tarihi AS tarih,
                       {$kategoriExpr} AS kategori,
                       {$altKategoriExpr} AS alt_kategori,
                       bp.toplam_maliyet AS tutar,
                       {$kaynakExpr} AS kaynak_tablo,
                       {$aciklamaExpr} AS aciklama
                FROM bordro_personel bp
                INNER JOIN bordro_donemi bd ON bp.donem_id = bd.id
                INNER JOIN personel p ON bp.personel_id = p.id
                                WHERE bp.silinme_tarihi IS NULL
                                    AND bd.firma_id = :f_bp
                                    AND COALESCE(bp.toplam_maliyet, 0) <> 0";
        $params['f_bp'] = $this->firmaId;

        if ($bas && $bit) {
            $sql .= " AND bd.baslangic_tarihi BETWEEN :bas_bp AND :bit_bp";
            $params['bas_bp'] = $bas;
            $params['bit_bp'] = $bit;
        }

        $parts[] = "({$sql})";
    }

    private function addManuelGiderler(array &$parts, array &$params, ?string $bas = null, ?string $bit = null, ?string $kat = null): void
    {
        $cols = $this->resolveManuelGiderColumns();
        $dateCol = $cols['date'];
        $categoryCol = $cols['category'];
        $subCategoryCol = $cols['sub_category'];
        $amountCol = $cols['amount'];
        $descCol = $cols['description'];

                $kategoriExpr = $this->utf8Expr("COALESCE(mg.{$categoryCol}, 'Diğer')");
                $altKategoriExpr = $this->utf8Expr("COALESCE(mg.{$subCategoryCol}, 'Manuel')");
                $kaynakExpr = $this->utf8Expr("'manuel_giderler'");
                $aciklamaExpr = $this->utf8Expr("mg.{$descCol}");

        if ($kat) {
            // Manuel giderler kendi kategorisine sahip olduğu için filtre uygulanır
            $sql = "SELECT mg.{$dateCol} AS tarih,
                                                     {$kategoriExpr} AS kategori,
                                                     {$altKategoriExpr} AS alt_kategori,
                           mg.{$amountCol} AS tutar,
                                                     {$kaynakExpr} AS kaynak_tablo,
                                                     {$aciklamaExpr} AS aciklama
                    FROM manuel_giderler mg
                                        WHERE mg.silinme_tarihi IS NULL
                                            AND mg.firma_id = :f_mg
                                            AND COALESCE(mg.{$amountCol}, 0) <> 0
                                            AND {$kategoriExpr} = CONVERT(:kat_mg USING utf8mb4) COLLATE utf8mb4_unicode_ci";
            $params['f_mg']   = $this->firmaId;
            $params['kat_mg'] = $kat;
        } else {
            $sql = "SELECT mg.{$dateCol} AS tarih,
                                                     {$kategoriExpr} AS kategori,
                                                     {$altKategoriExpr} AS alt_kategori,
                           mg.{$amountCol} AS tutar,
                                                     {$kaynakExpr} AS kaynak_tablo,
                                                     {$aciklamaExpr} AS aciklama
                    FROM manuel_giderler mg
                                        WHERE mg.silinme_tarihi IS NULL
                                            AND mg.firma_id = :f_mg
                                            AND COALESCE(mg.{$amountCol}, 0) <> 0";
            $params['f_mg'] = $this->firmaId;
        }

        if ($bas && $bit) {
            $sql .= " AND mg.{$dateCol} BETWEEN :bas_mg AND :bit_mg";
            $params['bas_mg'] = $bas;
            $params['bit_mg'] = $bit;
        }

        $parts[] = "({$sql})";
    }

    private function resolveManuelGiderColumns(): array
    {
        if ($this->manuelGiderColumns !== null) {
            return $this->manuelGiderColumns;
        }

        $defaults = [
            'date' => 'tarih',
            'category' => 'kategori',
            'sub_category' => 'alt_kategori',
            'amount' => 'tutar',
            'description' => 'aciklama',
        ];

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM manuel_giderler");
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($cols)) {
                $this->manuelGiderColumns = $defaults;
                return $this->manuelGiderColumns;
            }

            $find = function (array $candidates, string $fallback) use ($cols): string {
                foreach ($candidates as $name) {
                    if (in_array($name, $cols, true)) {
                        return $name;
                    }
                }
                return $fallback;
            };

            $this->manuelGiderColumns = [
                'date' => $find(['tarih', 'islem_tarihi', 'kayit_tarihi', 'olusturma_tarihi'], 'tarih'),
                'category' => $find(['kategori', 'kategori_adi'], 'kategori'),
                'sub_category' => $find(['alt_kategori', 'alt_kategori_adi'], 'alt_kategori'),
                'amount' => $find(['tutar', 'miktar', 'toplam_tutar'], 'tutar'),
                'description' => $find(['aciklama', 'notlar', 'not'], 'aciklama'),
            ];
        } catch (\Throwable $e) {
            $this->manuelGiderColumns = $defaults;
        }

        return $this->manuelGiderColumns;
    }

    private function utf8Expr(string $expr): string
    {
        return "CONVERT(($expr) USING utf8mb4) COLLATE utf8mb4_unicode_ci";
    }
}
