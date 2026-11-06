<?php

namespace App\Model;

use App\Helper\Security;
use PDO;

class IkBasvuruModel extends Model
{
    protected $table = 'ik_basvurular';

    public const DURUMLAR = [
        'yeni' => 'Yeni',
        'degerlendirildi' => 'Değerlendirildi',
        'gorusme' => 'Görüşme',
        'olumlu' => 'Olumlu',
        'olumsuz' => 'Olumsuz',
        'personele_donustu' => 'Personele Dönüştü',
    ];

    public const ETIKETLER = ['olumlu' => 'Olumlu', 'belirsiz' => 'Belirsiz', 'olumsuz' => 'Olumsuz'];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function bul(int $id, int $firmaId)
    {
        $stmt = $this->db->prepare("SELECT b.*, i.baslik AS ilan_baslik, i.departman AS ilan_departman, i.gorev AS ilan_gorev
            FROM {$this->table} b
            JOIN ik_ilanlar i ON i.id = b.ilan_id
            WHERE b.id = :id AND b.firma_id = :firma_id AND b.is_active = 1 AND b.deleted_at IS NULL");
        $stmt->execute(['id' => $id, 'firma_id' => $firmaId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function telefonVarMi(int $ilanId, string $telefon): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM {$this->table} WHERE ilan_id = :ilan_id AND telefon = :telefon LIMIT 1");
        $stmt->execute(['ilan_id' => $ilanId, 'telefon' => $telefon]);
        return (bool) $stmt->fetchColumn();
    }

    public function ekle(array $v): int
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, ilan_id, ad_soyad, telefon, email, dogum_tarihi, sehir, egitim, deneyim_yili, deneyim_ozet, ehliyet,
             maas_beklentisi, ise_baslama, cevaplar, cv_dosya, cv_mime, cv_metin, foto_dosya, kvkk_onay_tarihi, ip_adresi)
            VALUES
            (:firma_id, :ilan_id, :ad_soyad, :telefon, :email, :dogum_tarihi, :sehir, :egitim, :deneyim_yili, :deneyim_ozet, :ehliyet,
             :maas_beklentisi, :ise_baslama, :cevaplar, :cv_dosya, :cv_mime, :cv_metin, :foto_dosya, NOW(), :ip_adresi)");
        $stmt->execute([
            'firma_id' => $v['firma_id'],
            'ilan_id' => $v['ilan_id'],
            'ad_soyad' => $v['ad_soyad'],
            'telefon' => $v['telefon'],
            'email' => $v['email'],
            'dogum_tarihi' => $v['dogum_tarihi'],
            'sehir' => $v['sehir'],
            'egitim' => $v['egitim'],
            'deneyim_yili' => $v['deneyim_yili'],
            'deneyim_ozet' => $v['deneyim_ozet'],
            'ehliyet' => $v['ehliyet'],
            'maas_beklentisi' => $v['maas_beklentisi'],
            'ise_baslama' => $v['ise_baslama'],
            'cevaplar' => $v['cevaplar'],
            'cv_dosya' => $v['cv_dosya'],
            'cv_mime' => $v['cv_mime'],
            'cv_metin' => $v['cv_metin'],
            'foto_dosya' => $v['foto_dosya'] ?? null,
            'ip_adresi' => $v['ip_adresi'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function aiSonucKaydet(int $id, array $sonuc): void
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET
                ai_durum = 'tamam', ai_puan = :puan, ai_etiket = :etiket, ai_ozet = :ozet,
                ai_guclu = :guclu, ai_zayif = :zayif, ai_riskler = :riskler, ai_mulakat_sorulari = :mulakat,
                ai_tarihi = NOW(),
                durum = IF(durum = 'yeni', 'degerlendirildi', durum)
            WHERE id = :id");
        $stmt->execute([
            'puan' => $sonuc['puan'],
            'etiket' => $sonuc['etiket'],
            'ozet' => $sonuc['ozet'],
            'guclu' => json_encode($sonuc['guclu'], JSON_UNESCAPED_UNICODE),
            'zayif' => json_encode($sonuc['zayif'], JSON_UNESCAPED_UNICODE),
            'riskler' => json_encode($sonuc['riskler'], JSON_UNESCAPED_UNICODE),
            'mulakat' => json_encode($sonuc['mulakat_sorulari'], JSON_UNESCAPED_UNICODE),
            'id' => $id,
        ]);
    }

    public function aiHataKaydet(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET ai_durum = 'hata', ai_tarihi = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function durumGuncelle(int $id, int $firmaId, string $durum): bool
    {
        if (!isset(self::DURUMLAR[$durum]) || $durum === 'personele_donustu') {
            return false;
        }
        $stmt = $this->db->prepare("UPDATE {$this->table} SET durum = :durum
            WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL AND durum <> 'personele_donustu'");
        $stmt->execute(['durum' => $durum, 'id' => $id, 'firma_id' => $firmaId]);
        return $stmt->rowCount() > 0;
    }

    public function notKaydet(int $id, int $firmaId, string $not): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET ik_notu = :notu
            WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute(['notu' => $not !== '' ? $not : null, 'id' => $id, 'firma_id' => $firmaId]);
        return true;
    }

    public function personeleDonustuIsaretle(int $id, int $firmaId, int $personelId): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET durum = 'personele_donustu', personel_id = :personel_id
            WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL AND durum <> 'personele_donustu'");
        $stmt->execute(['personel_id' => $personelId, 'id' => $id, 'firma_id' => $firmaId]);
        return $stmt->rowCount() > 0;
    }

    public function softDeleteForFirma(int $id, int $firmaId): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET deleted_at = NOW(), is_active = 0
            WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id, 'firma_id' => $firmaId]);
        return $stmt->rowCount() > 0;
    }

    public function olayEkle(int $firmaId, int $basvuruId, ?int $kullaniciId, string $olay, ?string $aciklama = null): void
    {
        $stmt = $this->db->prepare("INSERT INTO ik_basvuru_olaylari (firma_id, basvuru_id, kullanici_id, olay, aciklama)
            VALUES (:firma_id, :basvuru_id, :kullanici_id, :olay, :aciklama)");
        $stmt->execute([
            'firma_id' => $firmaId,
            'basvuru_id' => $basvuruId,
            'kullanici_id' => $kullaniciId > 0 ? $kullaniciId : null,
            'olay' => mb_substr($olay, 0, 60, 'UTF-8'),
            'aciklama' => $aciklama !== null ? mb_substr($aciklama, 0, 500, 'UTF-8') : null,
        ]);
    }

    public function olaylar(int $basvuruId, int $firmaId): array
    {
        $stmt = $this->db->prepare("SELECT o.olay, o.aciklama, o.created_at, u.adi_soyadi AS kullanici
            FROM ik_basvuru_olaylari o
            LEFT JOIN users u ON u.id = o.kullanici_id
            WHERE o.basvuru_id = :basvuru_id AND o.firma_id = :firma_id
            ORDER BY o.id DESC");
        $stmt->execute(['basvuru_id' => $basvuruId, 'firma_id' => $firmaId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function denemeSayisi(string $ip, int $dakika = 60): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM ik_basvuru_denemeleri
            WHERE ip_adresi = :ip AND created_at > DATE_SUB(NOW(), INTERVAL " . (int) $dakika . " MINUTE)");
        $stmt->execute(['ip' => $ip]);
        return (int) $stmt->fetchColumn();
    }

    public function denemeKaydet(?int $ilanId, string $ip, string $sonuc): void
    {
        $stmt = $this->db->prepare("INSERT INTO ik_basvuru_denemeleri (ilan_id, ip_adresi, sonuc) VALUES (:ilan_id, :ip, :sonuc)");
        $stmt->execute(['ilan_id' => $ilanId, 'ip' => $ip, 'sonuc' => $sonuc]);
    }

    public function ajaxList(array $params, int $firmaId): array
    {
        $kolonlar = [
            1 => 'b.ad_soyad',
            2 => 'i.baslik',
            3 => 'b.telefon',
            4 => 'b.sehir',
            5 => 'b.ai_puan',
            6 => 'b.ai_etiket',
            7 => 'b.durum',
            8 => 'b.created_at',
        ];
        $where = "b.firma_id = :firma_id AND b.is_active = 1 AND b.deleted_at IS NULL";
        $bind = ['firma_id' => $firmaId];

        $arama = trim((string) ($params['search']['value'] ?? ''));
        if ($arama !== '') {
            $where .= " AND (b.ad_soyad LIKE :arama OR b.telefon LIKE :arama OR b.email LIKE :arama OR b.sehir LIKE :arama OR i.baslik LIKE :arama)";
            $bind['arama'] = '%' . $arama . '%';
        }

        $durumFiltre = (string) ($params['durum_filtre'] ?? 'all');
        if ($durumFiltre === 'bekleyen') {
            $where .= " AND b.durum IN ('yeni','degerlendirildi','gorusme')";
        } elseif (isset(self::DURUMLAR[$durumFiltre])) {
            $where .= " AND b.durum = :durum_filtre";
            $bind['durum_filtre'] = $durumFiltre;
        }

        $etiketFiltre = (string) ($params['etiket_filtre'] ?? '');
        if (isset(self::ETIKETLER[$etiketFiltre])) {
            $where .= " AND b.ai_etiket = :etiket_filtre";
            $bind['etiket_filtre'] = $etiketFiltre;
        }

        $ilanFiltre = (int) ($params['ilan_filtre'] ?? 0);
        if ($ilanFiltre > 0) {
            $where .= " AND b.ilan_id = :ilan_filtre";
            $bind['ilan_filtre'] = $ilanFiltre;
        }

        foreach (($params['columns'] ?? []) as $i => $kolon) {
            $deger = trim((string) ($kolon['search']['value'] ?? ''));
            if ($deger === '' || !in_array((int) $i, [1, 2, 3, 4], true)) {
                continue;
            }
            $where .= " AND {$kolonlar[(int) $i]} LIKE :kol_$i";
            $bind["kol_$i"] = '%' . $deger . '%';
        }

        $gelismis = [
            5 => ['number', 'b.ai_puan', []],
            6 => ['select', 'b.ai_etiket', array_flip(self::ETIKETLER)],
            7 => ['select', 'b.durum', array_flip(self::DURUMLAR)],
            8 => ['date', 'b.created_at', []],
        ];
        foreach ($gelismis as $i => [$tip, $ifade, $harita]) {
            $where .= \App\Helper\DataTableColumnFilter::apply($tip, (string) ($params['columns'][$i]['search']['value'] ?? ''), $ifade, "gkol_$i", $bind, $harita);
        }

        $baz = "FROM {$this->table} b JOIN ik_ilanlar i ON i.id = b.ilan_id";
        $toplam = $this->sayac("SELECT COUNT(*) $baz WHERE b.firma_id = :firma_id AND b.is_active = 1 AND b.deleted_at IS NULL", ['firma_id' => $firmaId]);
        $filtreli = $this->sayac("SELECT COUNT(*) $baz WHERE $where", $bind);

        $siralama = 'b.created_at DESC';
        $siraIndex = (int) ($params['order'][0]['column'] ?? -1);
        if (isset($kolonlar[$siraIndex])) {
            $yon = strtolower((string) ($params['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
            $siralama = $kolonlar[$siraIndex] . ' ' . $yon;
        }

        $baslangic = max(0, (int) ($params['start'] ?? 0));
        $uzunluk = (int) ($params['length'] ?? 25);
        $uzunluk = $uzunluk > 0 ? min($uzunluk, 200) : 25;

        $stmt = $this->db->prepare("SELECT b.id, b.ad_soyad, b.telefon, b.sehir, b.ai_puan, b.ai_etiket, b.ai_durum, b.durum, b.created_at,
                i.baslik AS ilan_baslik
            $baz WHERE $where ORDER BY $siralama LIMIT $baslangic, $uzunluk");
        $stmt->execute($bind);

        $veri = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $satir) {
            $satir->enc_id = Security::encrypt((int) $satir->id);
            unset($satir->id);
            $veri[] = $satir;
        }

        return [
            'draw' => (int) ($params['draw'] ?? 0),
            'recordsTotal' => $toplam,
            'recordsFiltered' => $filtreli,
            'data' => $veri,
        ];
    }

    public function summary(int $firmaId): object
    {
        $stmt = $this->db->prepare("SELECT
                COUNT(*) AS toplam,
                SUM(durum IN ('yeni','degerlendirildi','gorusme')) AS bekleyen,
                SUM(durum = 'olumlu') AS olumlu,
                SUM(durum = 'olumsuz') AS olumsuz,
                SUM(durum = 'personele_donustu') AS donusen,
                SUM(ai_etiket = 'olumlu') AS ai_olumlu,
                SUM(ai_etiket = 'belirsiz') AS ai_belirsiz,
                SUM(ai_etiket = 'olumsuz') AS ai_olumsuz,
                SUM(ai_durum = 'hata') AS ai_hata,
                SUM(ai_durum = 'bekliyor') AS ai_bekleyen,
                ROUND(AVG(ai_puan), 1) AS ortalama_puan,
                SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS son_7_gun
            FROM {$this->table}
            WHERE firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute(['firma_id' => $firmaId]);
        $r = $stmt->fetch(PDO::FETCH_OBJ);
        foreach ($r as $k => $v) {
            $r->$k = $k === 'ortalama_puan' ? (float) ($v ?? 0) : (int) ($v ?? 0);
        }
        return $r;
    }

    public function dashboardStats(int $firmaId, int $gun = 30): array
    {
        $gun = in_array($gun, [7, 30, 90, 365], true) ? $gun : 30;
        $ozet = $this->summary($firmaId);

        $stmt = $this->db->prepare("SELECT DATE(created_at) AS tarih, COUNT(*) AS adet
            FROM {$this->table}
            WHERE firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL " . ($gun - 1) . " DAY)
            GROUP BY DATE(created_at) ORDER BY tarih");
        $stmt->execute(['firma_id' => $firmaId]);
        $gunluk = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("SELECT i.baslik, COUNT(b.id) AS adet,
                SUM(b.ai_etiket = 'olumlu') AS olumlu, ROUND(AVG(b.ai_puan), 1) AS ortalama
            FROM ik_ilanlar i
            LEFT JOIN {$this->table} b ON b.ilan_id = i.id AND b.is_active = 1 AND b.deleted_at IS NULL
            WHERE i.firma_id = :firma_id AND i.is_active = 1 AND i.deleted_at IS NULL
            GROUP BY i.id, i.baslik ORDER BY adet DESC LIMIT 10");
        $stmt->execute(['firma_id' => $firmaId]);
        $ilanlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("SELECT FLOOR(ai_puan / 10) * 10 AS aralik, COUNT(*) AS adet
            FROM {$this->table}
            WHERE firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL AND ai_puan IS NOT NULL
            GROUP BY aralik ORDER BY aralik");
        $stmt->execute(['firma_id' => $firmaId]);
        $puanlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("SELECT b.ad_soyad, b.ai_puan, b.ai_etiket, b.durum, b.created_at, i.baslik AS ilan_baslik, b.id
            FROM {$this->table} b JOIN ik_ilanlar i ON i.id = b.ilan_id
            WHERE b.firma_id = :firma_id AND b.is_active = 1 AND b.deleted_at IS NULL
              AND b.ai_etiket = 'olumlu' AND b.durum IN ('yeni','degerlendirildi','gorusme','olumlu')
            ORDER BY b.ai_puan DESC, b.created_at DESC LIMIT 8");
        $stmt->execute(['firma_id' => $firmaId]);
        $enIyiler = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($enIyiler as &$satir) {
            $satir['enc_id'] = Security::encrypt((int) $satir['id']);
            unset($satir['id']);
        }
        unset($satir);

        $stmt = $this->db->prepare("SELECT b.ad_soyad, b.created_at, b.ai_durum, i.baslik AS ilan_baslik, b.id
            FROM {$this->table} b JOIN ik_ilanlar i ON i.id = b.ilan_id
            WHERE b.firma_id = :firma_id AND b.is_active = 1 AND b.deleted_at IS NULL AND b.ai_durum IN ('hata','bekliyor')
            ORDER BY b.created_at DESC LIMIT 8");
        $stmt->execute(['firma_id' => $firmaId]);
        $aiSorunlu = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($aiSorunlu as &$satir) {
            $satir['enc_id'] = Security::encrypt((int) $satir['id']);
            unset($satir['id']);
        }
        unset($satir);

        return [
            'summary' => $ozet,
            'gunluk' => $gunluk,
            'ilanlar' => $ilanlar,
            'puanlar' => $puanlar,
            'en_iyiler' => $enIyiler,
            'ai_sorunlu' => $aiSorunlu,
            'gun' => $gun,
        ];
    }

    private function sayac(string $sql, array $bind): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bind);
        return (int) $stmt->fetchColumn();
    }
}
