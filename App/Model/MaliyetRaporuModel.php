<?php

namespace App\Model;

use App\Core\Db;
use PDO;

class MaliyetRaporuModel extends Db
{
    public const HARIC = 'haric';

    public const KALEMLER = [
        'maas'             => ['ad' => 'Maaş Gideri',               'renk' => '#f43f5e', 'ikon' => 'bx-wallet',        'kaynak' => 'Bordro net maaş'],
        'sgk'              => ['ad' => 'SGK Gideri',                'renk' => '#14b8a6', 'ikon' => 'bx-shield-quarter', 'kaynak' => 'Bordro SGK + işsizlik (işçi + işveren)'],
        'vergi'            => ['ad' => 'Vergi Gideri',              'renk' => '#6366f1', 'ikon' => 'bx-receipt',       'kaynak' => 'Personel maaşından kesilen gelir + damga vergisi (bordro)'],
        'arac_kirasi'      => ['ad' => 'Araç Kirası',               'renk' => '#0ea5e9', 'ikon' => 'bx-car',           'kaynak' => 'Eşleştirilen firma faturaları'],
        'yakit'            => ['ad' => 'Yakıt Gideri',              'renk' => '#f59e0b', 'ikon' => 'bx-gas-pump',      'kaynak' => 'Yakıt faturaları + elle girilen yakıt + kasa'],
        'arac_bakim'       => ['ad' => 'Araç Bakım / Servis',       'renk' => '#0891b2', 'ikon' => 'bx-wrench',        'kaynak' => 'Araç bakım, servis, sigorta kayıtları'],
        'telefon_internet' => ['ad' => 'Telefon / İnternet',        'renk' => '#8b5cf6', 'ikon' => 'bx-phone',         'kaynak' => 'Eşleştirilen operatör faturaları'],
        'malzeme_demirbas' => ['ad' => 'Malzeme / Demirbaş',        'renk' => '#10b981', 'ikon' => 'bx-package',       'kaynak' => 'Eşleştirilen faturalar + demirbaş servis + manuel'],
        'ofis_diger'       => ['ad' => 'Ofis ve Diğer Harcamalar',  'renk' => '#64748b', 'ikon' => 'bx-briefcase',     'kaynak' => 'Gelir-Gider giderleri + manuel'],
    ];

    public const KURUMLAR_VERGISI_ORANI = 0.25;

    public const KAYNAK_ADLARI = [
        'bordro_personel'           => 'Bordro',
        'faturalar'                 => 'Gelen Fatura',
        'arac_yakit_kayitlari'      => 'Yakıt Kaydı',
        'arac_bakim_kayitlari'      => 'Araç Bakım',
        'arac_servis_kayitlari'     => 'Araç Servis',
        'arac_sigorta_kayitlari'    => 'Araç Sigorta',
        'demirbas_servis_kayitlari' => 'Demirbaş Servis',
        'gelir_gider'               => 'Gelir-Gider',
        'manuel_giderler'           => 'Manuel Gider',
    ];

    private int $firmaId;

    private static ?array $kalemCache = null;

    public function __construct()
    {
        parent::__construct();
        $this->firmaId = (int) ($_SESSION['firma_id'] ?? 0);
    }

    public static function kalemGecerliMi(?string $kalem, bool $haricDahil = false): bool
    {
        if ($kalem === null || $kalem === '') {
            return false;
        }
        return isset(self::kalemler()[$kalem]) || ($haricDahil && $kalem === self::HARIC);
    }

    public static function kalemAdi(?string $kalem): string
    {
        if ($kalem === self::HARIC) {
            return 'Rapora Dahil Etme';
        }
        return self::kalemler()[$kalem]['ad'] ?? (string) $kalem;
    }

    public static function kalemler(): array
    {
        if (self::$kalemCache !== null) {
            return self::$kalemCache;
        }
        $kalemler = self::KALEMLER;
        try {
            foreach ((new MaliyetOzelKalemModel())->liste() as $r) {
                $kalemler[MaliyetOzelKalemModel::ONEK . $r->id] = [
                    'ad' => $r->ad, 'renk' => $r->renk, 'ikon' => 'bx-purchase-tag-alt', 'kaynak' => 'Özel kalem (eşleştirme + manuel)',
                ];
            }
        } catch (\Throwable $e) {
            error_log('maliyet ozel kalemler: ' . $e->getMessage());
        }
        return self::$kalemCache = $kalemler;
    }

    public static function tarihAraligi($yil, $ay): array
    {
        $yil = (int) $yil;
        $ay  = (int) $ay;
        if ($yil <= 0) {
            return [null, null];
        }
        if ($ay >= 1 && $ay <= 12) {
            $bas = sprintf('%04d-%02d-01', $yil, $ay);
            return [$bas, date('Y-m-t', strtotime($bas))];
        }
        return ["{$yil}-01-01", "{$yil}-12-31"];
    }

    public function kalemOzet(?string $bas = null, ?string $bit = null): array
    {
        [$inner, $params] = $this->birlesik($bas, $bit);
        $stmt = $this->db->prepare("SELECT kalem, COALESCE(SUM(tutar), 0) AS toplam, COUNT(*) AS adet
                                    FROM ({$inner}) AS u GROUP BY kalem");
        $stmt->execute($params);

        $sonuc = [];
        foreach (array_keys(self::kalemler()) as $k) {
            $sonuc[$k] = ['toplam' => 0.0, 'adet' => 0];
        }
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $r) {
            if (isset($sonuc[$r->kalem])) {
                $sonuc[$r->kalem] = ['toplam' => round((float) $r->toplam, 2), 'adet' => (int) $r->adet];
            }
        }
        return $sonuc;
    }

    private function kdvMatrahExpr(): string
    {
        return "(COALESCE(f.kdv_matrahi, 0) * CASE WHEN COALESCE(f.para_birimi, 'TRY') IN ('TRY', 'TL') THEN 1 ELSE COALESCE(NULLIF(f.doviz_kuru, 0), 1) END)";
    }

    public function kurumlarVergisiGiderDetay(?string $bas = null, ?string $bit = null): array
    {
        $matrah = $this->kdvMatrahExpr();
        $tutar = $this->faturaTutarExpr();
        $vkn = $this->faturaVknExpr();
        $kalem = 'COALESCE(mfk.kalem, mke.kalem)';

        $params = ['f_bd' => $this->firmaId];
        $sql = "SELECT bd.id, bd.donem_adi, bd.baslangic_tarihi,
                       COUNT(DISTINCT bp.personel_id) AS personel,
                       COALESCE(SUM(COALESCE(bp.net_maas, 0)), 0) AS maas,
                       COALESCE(SUM(COALESCE(bp.sgk_isci, 0) + COALESCE(bp.issizlik_isci, 0) + COALESCE(bp.sgk_isveren, 0) + COALESCE(bp.issizlik_isveren, 0)), 0) AS sgk
                FROM bordro_personel bp
                INNER JOIN bordro_donemi bd ON bp.donem_id = bd.id
                INNER JOIN personel p ON bp.personel_id = p.id
                WHERE bp.silinme_tarihi IS NULL AND bd.silinme_tarihi IS NULL
                  AND bp.hesaplama_tarihi IS NOT NULL AND bd.firma_id = :f_bd";
        $sql .= $this->tarihFiltre('bd.baslangic_tarihi', 'bd', $params, $bas, $bit);
        $sql .= " GROUP BY bd.id, bd.donem_adi, bd.baslangic_tarihi ORDER BY bd.baslangic_tarihi, bd.id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $bordro = array_map(static fn($r) => [
            'donem'    => (string) $r->donem_adi,
            'personel' => (int) $r->personel,
            'maas'     => round((float) $r->maas, 2),
            'sgk'      => round((float) $r->sgk, 2),
        ], $stmt->fetchAll(PDO::FETCH_OBJ));

        $params = ['f_dm' => $this->firmaId, 'f_de' => $this->firmaId, 'f_dt' => $this->firmaId];
        $sql = "SELECT f.fatura_tarihi, f.fatura_no,
                       COALESCE(NULLIF(f.alici_unvan, ''), c.unvan, '') AS unvan,
                       {$kalem} AS kalem,
                       {$matrah} AS matrah,
                       {$tutar} AS tutar
                FROM faturalar f
                LEFT JOIN efatura_cariler c ON c.id = f.cari_id
                LEFT JOIN maliyet_fatura_kalem mfk
                       ON mfk.fatura_id = f.id AND mfk.firma_id = :f_dm AND mfk.deleted_at IS NULL AND mfk.is_active = 1
                LEFT JOIN maliyet_kalem_eslestirme mke
                       ON mke.firma_id = :f_de AND mke.kaynak = 'fatura' AND mke.deleted_at IS NULL AND mke.is_active = 1
                      AND mke.eslesme_degeri = {$vkn}
                WHERE f.firm_id = :f_dt AND f.yon = 'GELEN' AND f.deleted_at IS NULL
                  AND COALESCE(f.is_active, 1) = 1 AND COALESCE(f.ticari_yanit, '') <> 'RED'
                  AND {$kalem} IS NOT NULL AND {$kalem} <> 'haric'";
        $sql .= $this->tarihFiltre('f.fatura_tarihi', 'df', $params, $bas, $bit);
        $sql .= " ORDER BY matrah DESC, f.fatura_tarihi DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $faturalar = [];
        $kalemGrup = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $r) {
            $m = round((float) $r->matrah, 2);
            $t = round((float) $r->tutar, 2);
            $faturalar[] = [
                'tarih'    => $r->fatura_tarihi ? date('d.m.Y', strtotime($r->fatura_tarihi)) : '-',
                'fatura_no' => (string) ($r->fatura_no ?? ''),
                'unvan'    => (string) $r->unvan,
                'kalem'    => self::kalemAdi($r->kalem),
                'matrah'   => $m,
                'tutar'    => $t,
            ];
            $ad = self::kalemAdi($r->kalem);
            $kalemGrup[$ad] ??= ['kalem' => $ad, 'adet' => 0, 'matrah' => 0.0, 'tutar' => 0.0];
            $kalemGrup[$ad]['adet']++;
            $kalemGrup[$ad]['matrah'] = round($kalemGrup[$ad]['matrah'] + $m, 2);
            $kalemGrup[$ad]['tutar'] = round($kalemGrup[$ad]['tutar'] + $t, 2);
        }
        $kalemGrup = array_values($kalemGrup);
        usort($kalemGrup, static fn($a, $b) => $b['matrah'] <=> $a['matrah']);

        $params = ['f_ud' => $this->firmaId, 'f_um' => $this->firmaId, 'f_ue' => $this->firmaId];
        $sql = "SELECT COUNT(*) AS adet, COALESCE(SUM({$matrah}), 0) AS matrah, COALESCE(SUM({$tutar}), 0) AS tutar
                FROM faturalar f
                LEFT JOIN efatura_cariler c ON c.id = f.cari_id
                LEFT JOIN maliyet_fatura_kalem mfk
                       ON mfk.fatura_id = f.id AND mfk.firma_id = :f_um AND mfk.deleted_at IS NULL AND mfk.is_active = 1
                LEFT JOIN maliyet_kalem_eslestirme mke
                       ON mke.firma_id = :f_ue AND mke.kaynak = 'fatura' AND mke.deleted_at IS NULL AND mke.is_active = 1
                      AND mke.eslesme_degeri = {$vkn}
                WHERE f.firm_id = :f_ud AND f.yon = 'GELEN' AND f.deleted_at IS NULL
                  AND COALESCE(f.is_active, 1) = 1 AND COALESCE(f.ticari_yanit, '') <> 'RED'
                  AND mfk.id IS NULL AND mke.id IS NULL";
        $sql .= $this->tarihFiltre('f.fatura_tarihi', 'du', $params, $bas, $bit);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $u = $stmt->fetch(PDO::FETCH_OBJ);

        return [
            'bordro'     => $bordro,
            'kalem_grup' => $kalemGrup,
            'faturalar'  => $faturalar,
            'baglanmamis' => [
                'adet'   => (int) ($u->adet ?? 0),
                'matrah' => round((float) ($u->matrah ?? 0), 2),
                'tutar'  => round((float) ($u->tutar ?? 0), 2),
            ],
        ];
    }

    public function kurumlarVergisi(array $ozet, ?string $bas = null, ?string $bit = null): array
    {
        $matrah = $this->kdvMatrahExpr();

        $params = ['f_kg' => $this->firmaId];
        $sql = "SELECT COALESCE(SUM({$matrah}), 0) FROM faturalar f
                WHERE f.firm_id = :f_kg AND f.yon = 'GIDEN' AND f.deleted_at IS NULL
                  AND COALESCE(f.is_active, 1) = 1 AND COALESCE(f.ticari_yanit, '') <> 'RED'";
        $sql .= $this->tarihFiltre('f.fatura_tarihi', 'kg', $params, $bas, $bit);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $gelir = round((float) $stmt->fetchColumn(), 2);

        $vkn = $this->faturaVknExpr();
        $kalem = 'COALESCE(mfk.kalem, mke.kalem)';
        $params = ['f_kgm' => $this->firmaId, 'f_kge' => $this->firmaId, 'f_kgt' => $this->firmaId];
        $sql = "SELECT COALESCE(SUM({$matrah}), 0) FROM faturalar f
                LEFT JOIN efatura_cariler c ON c.id = f.cari_id
                LEFT JOIN maliyet_fatura_kalem mfk
                       ON mfk.fatura_id = f.id AND mfk.firma_id = :f_kgm AND mfk.deleted_at IS NULL AND mfk.is_active = 1
                LEFT JOIN maliyet_kalem_eslestirme mke
                       ON mke.firma_id = :f_kge AND mke.kaynak = 'fatura' AND mke.deleted_at IS NULL AND mke.is_active = 1
                      AND mke.eslesme_degeri = {$vkn}
                WHERE f.firm_id = :f_kgt AND f.yon = 'GELEN' AND f.deleted_at IS NULL
                  AND COALESCE(f.is_active, 1) = 1 AND COALESCE(f.ticari_yanit, '') <> 'RED'
                  AND {$kalem} IS NOT NULL AND {$kalem} <> 'haric'";
        $sql .= $this->tarihFiltre('f.fatura_tarihi', 'kgf', $params, $bas, $bit);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $faturaGider = round((float) $stmt->fetchColumn(), 2);

        $maas = (float) ($ozet['maas']['toplam'] ?? 0);
        $sgk = (float) ($ozet['sgk']['toplam'] ?? 0);
        $gider = round($maas + $sgk + $faturaGider, 2);
        $kar = round($gelir - $gider, 2);
        $vergi = $kar > 0 ? round($kar * self::KURUMLAR_VERGISI_ORANI, 2) : 0.0;

        return [
            'gelir' => $gelir, 'maas' => $maas, 'sgk' => $sgk, 'fatura_gider' => $faturaGider,
            'gider' => $gider, 'kar' => $kar, 'vergi' => $vergi, 'oran' => self::KURUMLAR_VERGISI_ORANI,
        ];
    }

    public function aylikKalem(int $yil): array
    {
        [$inner, $params] = $this->birlesik("{$yil}-01-01", "{$yil}-12-31");
        $stmt = $this->db->prepare("SELECT MONTH(tarih) AS ay, kalem, COALESCE(SUM(tutar), 0) AS toplam
                                    FROM ({$inner}) AS u GROUP BY MONTH(tarih), kalem");
        $stmt->execute($params);

        $matris = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $r) {
            $matris[$r->kalem][(int) $r->ay] = round((float) $r->toplam, 2);
        }
        return $matris;
    }

    public function eslesmemisFaturalar(?string $bas = null, ?string $bit = null): array
    {
        $params = ['f_ef' => $this->firmaId, 'f_ef2' => $this->firmaId, 'f_ef3' => $this->firmaId];
        $vkn = $this->faturaVknExpr();
        $tutar = $this->faturaTutarExpr();
        $sql = "SELECT {$vkn} AS vkn,
                       MAX(COALESCE(NULLIF(f.alici_unvan, ''), c.unvan)) AS unvan,
                       COUNT(*) AS adet,
                       COALESCE(SUM({$tutar}), 0) AS toplam,
                       MAX(f.fatura_tarihi) AS son_tarih
                FROM faturalar f
                LEFT JOIN efatura_cariler c ON c.id = f.cari_id
                LEFT JOIN maliyet_fatura_kalem mfk
                       ON mfk.fatura_id = f.id AND mfk.firma_id = :f_ef2 AND mfk.deleted_at IS NULL AND mfk.is_active = 1
                LEFT JOIN maliyet_kalem_eslestirme mke
                       ON mke.firma_id = :f_ef3 AND mke.kaynak = 'fatura' AND mke.deleted_at IS NULL AND mke.is_active = 1
                      AND mke.eslesme_degeri = {$vkn}
                WHERE f.firm_id = :f_ef
                  AND f.yon = 'GELEN'
                  AND f.deleted_at IS NULL
                  AND COALESCE(f.is_active, 1) = 1
                  AND COALESCE(f.ticari_yanit, '') <> 'RED'
                  AND mfk.id IS NULL
                  AND mke.id IS NULL";
        if ($bas && $bit) {
            $sql .= " AND f.fatura_tarihi BETWEEN :bas_ef AND :bit_ef";
            $params['bas_ef'] = $bas;
            $params['bit_ef'] = $bit;
        }
        $sql .= " GROUP BY {$vkn} ORDER BY toplam DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function ajaxList(array $params, ?string $baslangic = null, ?string $bitis = null, ?string $kalem = null): array
    {
        [$inner, $bind] = $this->birlesik($baslangic, $bitis);
        $draw = (int) ($params['draw'] ?? 0);

        $adCase = $this->kalemAdiCase($bind);
        $kaynakCase = $this->kaynakAdiCase($bind);
        $base = "SELECT u.*, {$adCase} AS kalem_adi, {$kaynakCase} AS kaynak_adi FROM ({$inner}) AS u";

        $onWhere = [];
        if ($kalem && self::kalemGecerliMi($kalem)) {
            $onWhere[] = 'u.kalem = :q_kalem';
            $bind['q_kalem'] = $kalem;
        }
        $onWhereSql = $onWhere ? ' WHERE ' . implode(' AND ', $onWhere) : '';
        $base .= $onWhereSql;

        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM ({$base}) AS t");
        $totalStmt->execute($bind);
        $total = (int) $totalStmt->fetchColumn();

        $kolonlar = [1 => 'tarih', 2 => 'kalem_adi', 3 => 'alt_kategori', 4 => 'tutar', 5 => 'kaynak_adi', 6 => 'aciklama'];
        $metinKolonlari = ['kalem_adi', 'alt_kategori', 'kaynak_adi', 'aciklama'];

        $where = [];
        $filter = [];
        $arama = trim((string) ($params['search']['value'] ?? ''));
        if ($arama !== '') {
            $parcalar = [];
            foreach ($kolonlar as $i => $ad) {
                $parcalar[] = in_array($ad, $metinKolonlari, true) ? "{$ad} LIKE :q_ara_{$i}" : "CAST({$ad} AS CHAR) LIKE :q_ara_{$i}";
                $filter["q_ara_{$i}"] = '%' . $arama . '%';
            }
            $where[] = '(' . implode(' OR ', $parcalar) . ')';
        }
        foreach (($params['columns'] ?? []) as $i => $kolon) {
            $i = (int) $i;
            $deger = trim((string) ($kolon['search']['value'] ?? ''));
            if ($deger === '' || !isset($kolonlar[$i])) {
                continue;
            }
            $ad = $kolonlar[$i];
            $where[] = in_array($ad, $metinKolonlari, true) ? "{$ad} LIKE :q_col_{$i}" : "CAST({$ad} AS CHAR) LIKE :q_col_{$i}";
            $filter["q_col_{$i}"] = '%' . $deger . '%';
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $filteredStmt = $this->db->prepare("SELECT COUNT(*), COALESCE(SUM(tutar), 0) FROM ({$base}) AS t{$whereSql}");
        $filteredStmt->execute($bind + $filter);
        [$filtered, $toplam] = $filteredStmt->fetch(PDO::FETCH_NUM);

        $siralama = [];
        foreach (($params['order'] ?? []) as $o) {
            $idx = (int) ($o['column'] ?? -1);
            if (isset($kolonlar[$idx])) {
                $siralama[] = $kolonlar[$idx] . (strtolower((string) ($o['dir'] ?? 'asc')) === 'asc' ? ' ASC' : ' DESC');
            }
        }
        if (!$siralama) {
            $siralama[] = 'tarih DESC';
        }
        $siralama[] = 'kaynak_tablo ASC';
        $siralama[] = 'kaynak_id DESC';

        $start = max(0, (int) ($params['start'] ?? 0));
        $length = (int) ($params['length'] ?? 25);
        $length = $length > 0 ? min($length, 500) : 25;

        $stmt = $this->db->prepare("SELECT * FROM ({$base}) AS t{$whereSql} ORDER BY " . implode(', ', $siralama) . " LIMIT :baslangic, :uzunluk");
        foreach ($bind + $filter as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('baslangic', $start, PDO::PARAM_INT);
        $stmt->bindValue('uzunluk', $length, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => (int) $filtered,
            'toplam'          => round((float) $toplam, 2),
            'data'            => $stmt->fetchAll(PDO::FETCH_OBJ),
        ];
    }

    private function birlesik(?string $bas, ?string $bit): array
    {
        $parts = [];
        $params = [];
        $this->addBordro($parts, $params, $bas, $bit);
        $this->addFaturalar($parts, $params, $bas, $bit);
        $this->addYakitKayitlari($parts, $params, $bas, $bit);
        $this->addAracBakim($parts, $params, $bas, $bit);
        $this->addAracServis($parts, $params, $bas, $bit);
        $this->addAracSigorta($parts, $params, $bas, $bit);
        $this->addDemirbasServis($parts, $params, $bas, $bit);
        $this->addGelirGider($parts, $params, $bas, $bit);
        $this->addManuelGiderler($parts, $params, $bas, $bit);
        return [implode("\nUNION ALL\n", $parts), $params];
    }

    private function satir(string $tarih, string $kalem, string $alt, string $tutar, string $kaynak, string $aciklama, string $id): string
    {
        return "SELECT {$tarih} AS tarih,
                       {$this->utf8Expr($kalem)} AS kalem,
                       {$this->utf8Expr($alt)} AS alt_kategori,
                       ROUND({$tutar}, 2) AS tutar,
                       {$this->utf8Expr($kaynak)} AS kaynak_tablo,
                       {$this->utf8Expr($aciklama)} AS aciklama,
                       {$id} AS kaynak_id";
    }

    private function tarihFiltre(string $kolon, string $sfx, array &$params, ?string $bas, ?string $bit): string
    {
        if (!$bas || !$bit) {
            return '';
        }
        $params["bas_{$sfx}"] = $bas;
        $params["bit_{$sfx}"] = $bit;
        return " AND {$kolon} BETWEEN :bas_{$sfx} AND :bit_{$sfx}";
    }

    private function addBordro(array &$parts, array &$params, ?string $bas, ?string $bit): void
    {
        $parcalar = [
            'bm' => ['maas',  'Net Maaş',              'COALESCE(bp.net_maas, 0)'],
            'bs' => ['sgk',   'SGK + İşsizlik Primi',  '(COALESCE(bp.sgk_isci, 0) + COALESCE(bp.issizlik_isci, 0) + COALESCE(bp.sgk_isveren, 0) + COALESCE(bp.issizlik_isveren, 0))'],
            'bv' => ['vergi', 'Gelir + Damga Vergisi', '(COALESCE(bp.gelir_vergisi, 0) + COALESCE(bp.damga_vergisi, 0))'],
        ];
        foreach ($parcalar as $sfx => [$kalem, $alt, $tutar]) {
            $sql = $this->satir('bd.baslangic_tarihi', "'{$kalem}'", "'{$alt}'", $tutar, "'bordro_personel'",
                    "CONCAT(p.adi_soyadi, ' - ', bd.donem_adi)", 'bp.id') . "
                    FROM bordro_personel bp
                    INNER JOIN bordro_donemi bd ON bp.donem_id = bd.id
                    INNER JOIN personel p ON bp.personel_id = p.id
                    WHERE bp.silinme_tarihi IS NULL
                      AND bd.silinme_tarihi IS NULL
                      AND bp.hesaplama_tarihi IS NOT NULL
                      AND bd.firma_id = :f_{$sfx}
                      AND {$tutar} <> 0";
            $params["f_{$sfx}"] = $this->firmaId;
            $sql .= $this->tarihFiltre('bd.baslangic_tarihi', $sfx, $params, $bas, $bit);
            $parts[] = "({$sql})";
        }
    }

    private function faturaVknExpr(): string
    {
        return "COALESCE(NULLIF(f.alici_vkn_tckn, ''), c.vkn_tckn)";
    }

    private function faturaTutarExpr(): string
    {
        return "(COALESCE(f.odenecek_tutar, 0) * CASE WHEN COALESCE(f.para_birimi, 'TRY') IN ('TRY', 'TL') THEN 1 ELSE COALESCE(NULLIF(f.doviz_kuru, 0), 1) END)";
    }

    private function addFaturalar(array &$parts, array &$params, ?string $bas, ?string $bit): void
    {
        $vkn = $this->faturaVknExpr();
        $kalem = 'COALESCE(mfk.kalem, mke.kalem)';
        $sql = $this->satir('f.fatura_tarihi', $kalem,
                "CASE WHEN mfk.id IS NOT NULL THEN 'Fatura (elle atandı)' ELSE 'Fatura' END",
                $this->faturaTutarExpr(), "'faturalar'",
                "CONCAT(COALESCE(NULLIF(f.alici_unvan, ''), c.unvan, ''), ' - ', COALESCE(f.fatura_no, ''))", 'f.id') . "
                FROM faturalar f
                LEFT JOIN efatura_cariler c ON c.id = f.cari_id
                LEFT JOIN maliyet_fatura_kalem mfk
                       ON mfk.fatura_id = f.id AND mfk.firma_id = :f_fm AND mfk.deleted_at IS NULL AND mfk.is_active = 1
                LEFT JOIN maliyet_kalem_eslestirme mke
                       ON mke.firma_id = :f_fe AND mke.kaynak = 'fatura' AND mke.deleted_at IS NULL AND mke.is_active = 1
                      AND mke.eslesme_degeri = {$vkn}
                WHERE f.firm_id = :f_ft
                  AND f.yon = 'GELEN'
                  AND f.deleted_at IS NULL
                  AND COALESCE(f.is_active, 1) = 1
                  AND COALESCE(f.ticari_yanit, '') <> 'RED'
                  AND {$kalem} IS NOT NULL
                  AND {$kalem} <> 'haric'
                  AND COALESCE(f.odenecek_tutar, 0) <> 0";
        $params['f_fm'] = $this->firmaId;
        $params['f_fe'] = $this->firmaId;
        $params['f_ft'] = $this->firmaId;
        $sql .= $this->tarihFiltre('f.fatura_tarihi', 'ft', $params, $bas, $bit);
        $parts[] = "({$sql})";
    }

    private function addYakitKayitlari(array &$parts, array &$params, ?string $bas, ?string $bit): void
    {
        $sql = $this->satir('y.tarih', "'yakit'", "'Yakıt Kaydı (fatura dışı)'", 'y.toplam_tutar', "'arac_yakit_kayitlari'",
                "CONCAT(a.plaka, ' - ', y.yakit_miktari, ' Lt')", 'y.id') . "
                FROM arac_yakit_kayitlari y
                INNER JOIN araclar a ON y.arac_id = a.id
                WHERE y.silinme_tarihi IS NULL
                  AND y.firma_id = :f_yk
                  AND COALESCE(y.toplam_tutar, 0) <> 0
                  AND NOT EXISTS (
                      SELECT 1 FROM faturalar fy
                      WHERE fy.firm_id = y.firma_id
                        AND fy.yon = 'GELEN'
                        AND fy.deleted_at IS NULL
                        AND COALESCE(y.fatura_no, '') <> ''
                        AND fy.fatura_no = y.fatura_no
                  )
                  AND NOT (
                      y.external_id IS NOT NULL
                      AND COALESCE(y.fatura_no, '') = ''
                      AND y.tarih >= COALESCE((
                          SELECT MIN(fm.fatura_tarihi) FROM faturalar fm
                          WHERE fm.firm_id = y.firma_id AND fm.yon = 'GELEN' AND fm.deleted_at IS NULL
                      ), '9999-12-31')
                  )";
        $params['f_yk'] = $this->firmaId;
        $sql .= $this->tarihFiltre('y.tarih', 'yk', $params, $bas, $bit);
        $parts[] = "({$sql})";
    }

    private function addAracBakim(array &$parts, array &$params, ?string $bas, ?string $bit): void
    {
        $sql = $this->satir('b.tarih', "'arac_bakim'", "CONCAT('Bakım - ', COALESCE(b.bakim_tipi, ''))", 'b.tutar', "'arac_bakim_kayitlari'",
                "CONCAT(a.plaka, ' - ', COALESCE(b.servis_adi, LEFT(b.aciklama, 80), ''))", 'b.id') . "
                FROM arac_bakim_kayitlari b
                INNER JOIN araclar a ON b.arac_id = a.id
                WHERE b.silinme_tarihi IS NULL
                  AND b.firma_id = :f_bk
                  AND COALESCE(b.tutar, 0) <> 0";
        $params['f_bk'] = $this->firmaId;
        $sql .= $this->tarihFiltre('b.tarih', 'bk', $params, $bas, $bit);
        $parts[] = "({$sql})";
    }

    private function addAracServis(array &$parts, array &$params, ?string $bas, ?string $bit): void
    {
        $sql = $this->satir('s.servis_tarihi', "'arac_bakim'", "'Servis'", 's.tutar', "'arac_servis_kayitlari'",
                "CONCAT(a.plaka, ' - ', COALESCE(LEFT(s.servis_nedeni, 80), ''))", 's.id') . "
                FROM arac_servis_kayitlari s
                INNER JOIN araclar a ON s.arac_id = a.id
                WHERE s.silinme_tarihi IS NULL
                  AND s.firma_id = :f_sv
                  AND COALESCE(s.tutar, 0) <> 0";
        $params['f_sv'] = $this->firmaId;
        $sql .= $this->tarihFiltre('s.servis_tarihi', 'sv', $params, $bas, $bit);
        $parts[] = "({$sql})";
    }

    private function addAracSigorta(array &$parts, array &$params, ?string $bas, ?string $bit): void
    {
        $sql = $this->satir('si.baslangic_tarihi', "'arac_bakim'", "CONCAT('Sigorta - ', COALESCE(si.sigorta_tipi, ''))", 'si.prim_tutari', "'arac_sigorta_kayitlari'",
                "CONCAT(a.plaka, ' - ', COALESCE(si.sigorta_sirketi, ''), ' ', COALESCE(si.police_no, ''))", 'si.id') . "
                FROM arac_sigorta_kayitlari si
                INNER JOIN araclar a ON si.arac_id = a.id
                WHERE si.silinme_tarihi IS NULL
                  AND si.firma_id = :f_sg
                  AND COALESCE(si.prim_tutari, 0) <> 0";
        $params['f_sg'] = $this->firmaId;
        $sql .= $this->tarihFiltre('si.baslangic_tarihi', 'sg', $params, $bas, $bit);
        $parts[] = "({$sql})";
    }

    private function addDemirbasServis(array &$parts, array &$params, ?string $bas, ?string $bit): void
    {
        $sql = $this->satir('ds.servis_tarihi', "'malzeme_demirbas'", "'Demirbaş Servis'", 'ds.tutar', "'demirbas_servis_kayitlari'",
                "CONCAT(d.demirbas_adi, ' (', COALESCE(d.demirbas_no, ''), ')')", 'ds.id') . "
                FROM demirbas_servis_kayitlari ds
                INNER JOIN demirbas d ON ds.demirbas_id = d.id
                WHERE ds.silinme_tarihi IS NULL
                  AND ds.firma_id = :f_ds
                  AND COALESCE(ds.tutar, 0) <> 0";
        $params['f_ds'] = $this->firmaId;
        $sql .= $this->tarihFiltre('ds.servis_tarihi', 'ds', $params, $bas, $bit);
        $parts[] = "({$sql})";
    }

    private function addGelirGider(array &$parts, array &$params, ?string $bas, ?string $bit): void
    {
        $kategori = "CONVERT(TRIM(COALESCE(g.kategori, '')) USING utf8mb4) COLLATE utf8mb4_unicode_ci";
        $kalem = "COALESCE(mke.kalem, 'ofis_diger')";
        $tutar = 'CAST(g.tutar AS DECIMAL(15,2))';
        $sql = $this->satir('DATE(g.tarih)', $kalem, "COALESCE(NULLIF(TRIM(g.kategori), ''), 'Kategorisiz')", $tutar, "'gelir_gider'",
                "CONCAT_WS(' - ', NULLIF(g.hesap_adi, ''), NULLIF(LEFT(g.aciklama, 120), ''))", 'g.id') . "
                FROM gelir_gider g
                LEFT JOIN maliyet_kalem_eslestirme mke
                       ON mke.firma_id = :f_gge AND mke.kaynak = 'gelir_gider' AND mke.deleted_at IS NULL AND mke.is_active = 1
                      AND mke.eslesme_degeri = {$kategori}
                WHERE g.silinme_tarihi IS NULL
                  AND g.type = 2
                  AND {$kalem} <> 'haric'
                  AND {$tutar} <> 0";
        $params['f_gge'] = $this->firmaId;
        $sql .= $this->tarihFiltre('DATE(g.tarih)', 'gg', $params, $bas, $bit);
        $parts[] = "({$sql})";
    }

    private function addManuelGiderler(array &$parts, array &$params, ?string $bas, ?string $bit): void
    {
        $anahtarlar = [];
        foreach (array_keys(self::kalemler()) as $i => $k) {
            $anahtarlar[] = ":mgk_{$i}";
            $params["mgk_{$i}"] = $k;
        }
        $kalem = "CASE
                      WHEN mg.kategori IN (" . implode(', ', $anahtarlar) . ") THEN mg.kategori
                      WHEN mg.kategori = 'Demirbaş' THEN 'malzeme_demirbas'
                      WHEN mg.kategori = 'Araç' THEN 'arac_bakim'
                      WHEN mg.kategori = 'Personel' THEN 'maas'
                      ELSE 'ofis_diger'
                  END";
        $sql = $this->satir('mg.gider_tarihi', $kalem, "COALESCE(NULLIF(mg.alt_kategori, ''), 'Manuel')", 'mg.tutar', "'manuel_giderler'",
                "CONCAT_WS(' - ', NULLIF(mg.belge_no, ''), NULLIF(mg.aciklama, ''))", 'mg.id') . "
                FROM manuel_giderler mg
                WHERE mg.silinme_tarihi IS NULL
                  AND mg.firma_id = :f_mg
                  AND COALESCE(mg.tutar, 0) <> 0";
        $params['f_mg'] = $this->firmaId;
        $sql .= $this->tarihFiltre('mg.gider_tarihi', 'mg', $params, $bas, $bit);
        $parts[] = "({$sql})";
    }

    private function kalemAdiCase(array &$params): string
    {
        $parcalar = [];
        $i = 0;
        foreach (self::kalemler() as $k => $cfg) {
            $parcalar[] = "WHEN :kak_{$i} THEN :kad_{$i}";
            $params["kak_{$i}"] = $k;
            $params["kad_{$i}"] = $cfg['ad'];
            $i++;
        }
        return 'CASE u.kalem ' . implode(' ', $parcalar) . ' ELSE u.kalem END';
    }

    private function kaynakAdiCase(array &$params): string
    {
        $parcalar = [];
        $i = 0;
        foreach (self::KAYNAK_ADLARI as $k => $ad) {
            $parcalar[] = "WHEN :kyk_{$i} THEN :kyd_{$i}";
            $params["kyk_{$i}"] = $k;
            $params["kyd_{$i}"] = $ad;
            $i++;
        }
        return 'CASE u.kaynak_tablo ' . implode(' ', $parcalar) . ' ELSE u.kaynak_tablo END';
    }

    private function utf8Expr(string $expr): string
    {
        return "CONVERT(({$expr}) USING utf8mb4) COLLATE utf8mb4_unicode_ci";
    }
}
