<?php

namespace App\Model;

use App\Helper\Security;
use PDO;

class IkIlanModel extends Model
{
    protected $table = 'ik_ilanlar';

    public const DURUMLAR = ['taslak' => 'Taslak', 'yayinda' => 'Yayında', 'kapali' => 'Kapalı'];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public static function linkKoku(): string
    {
        $httpsAktif = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $appBase = trim((string) ($_ENV['APP_BASE'] ?? ''), " \t\n\r\0\x0B\"'");
        if ($appBase !== '') {
            return 'https://' . $appBase;
        }
        $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        $altDizin = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '', 3)), '/');
        return ($httpsAktif ? 'https://' : 'http://') . $host . $altDizin;
    }

    public static function linkOlustur(string $token): string
    {
        return rtrim(self::linkKoku(), '/') . '/basvuru.php?i=' . $token;
    }

    public function bul(int $id, int $firmaId)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table}
            WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute(['id' => $id, 'firma_id' => $firmaId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function tokenIleBul(string $token)
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT i.*, COALESCE(NULLIF(f.firma_unvan, ''), f.firma_adi) AS firma_adi
            FROM {$this->table} i
            LEFT JOIN firmalar f ON f.id = i.firma_id
            WHERE i.token_hash = :hash AND i.is_active = 1 AND i.deleted_at IS NULL
              AND i.durum = 'yayinda'
              AND (i.gecerlilik_tarihi IS NULL OR i.gecerlilik_tarihi > NOW())
            LIMIT 1");
        $stmt->execute(['hash' => hash('sha256', $token)]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function tokenGetir(object $ilan): string
    {
        return (string) Security::decrypt((string) $ilan->token_sifreli);
    }

    public function yayindakiListe(int $firmaId): array
    {
        $stmt = $this->db->prepare("SELECT id, baslik, departman, gorev, calisma_yeri, aciklama, aranan_nitelikler, token_sifreli, created_at
            FROM {$this->table}
            WHERE firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL
              AND durum = 'yayinda'
              AND (gecerlilik_tarihi IS NULL OR gecerlilik_tarihi > NOW())
            ORDER BY created_at DESC");
        $stmt->execute(['firma_id' => $firmaId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function goruntulemeKaydet(string $token, string $ziyaretciHash, int $firmaId): bool
    {
        $ilan = $this->tokenIleBul($token);
        if (!$ilan || (int) $ilan->firma_id !== $firmaId || !preg_match('/^[a-f0-9]{64}$/', $ziyaretciHash)) {
            return false;
        }
        $stmt = $this->db->prepare("INSERT INTO ik_ilan_goruntulemeler (firma_id, ilan_id, ziyaretci_hash)
            VALUES (:firma_id, :ilan_id, :hash)
            ON DUPLICATE KEY UPDATE goruntuleme_sayisi = goruntuleme_sayisi + 1, son_goruntuleme = NOW()");
        $stmt->execute(['firma_id' => $firmaId, 'ilan_id' => (int) $ilan->id, 'hash' => $ziyaretciHash]);
        return true;
    }

    public function aktifListe(int $firmaId): array
    {
        $stmt = $this->db->prepare("SELECT id, baslik FROM {$this->table}
            WHERE firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL ORDER BY created_at DESC");
        $stmt->execute(['firma_id' => $firmaId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function kaydet(array $veri, int $firmaId, int $userId): int
    {
        $id = (int) ($veri['id'] ?? 0);
        $alanlar = [
            'baslik' => $veri['baslik'],
            'departman' => $veri['departman'] !== '' ? $veri['departman'] : null,
            'gorev' => $veri['gorev'] !== '' ? $veri['gorev'] : null,
            'calisma_yeri' => $veri['calisma_yeri'] !== '' ? $veri['calisma_yeri'] : null,
            'aciklama' => $veri['aciklama'] !== '' ? $veri['aciklama'] : null,
            'aranan_nitelikler' => $veri['aranan_nitelikler'] !== '' ? $veri['aranan_nitelikler'] : null,
            'ai_kriterleri' => $veri['ai_kriterleri'] !== '' ? $veri['ai_kriterleri'] : null,
            'sorular' => $veri['sorular'],
            'durum' => $veri['durum'],
            'gecerlilik_tarihi' => $veri['gecerlilik_tarihi'] !== '' ? $veri['gecerlilik_tarihi'] : null,
        ];

        if ($id > 0) {
            $alanlar['id'] = $id;
            $alanlar['firma_id'] = $firmaId;
            $stmt = $this->db->prepare("UPDATE {$this->table} SET
                baslik = :baslik, departman = :departman, gorev = :gorev, calisma_yeri = :calisma_yeri,
                aciklama = :aciklama, aranan_nitelikler = :aranan_nitelikler, ai_kriterleri = :ai_kriterleri,
                sorular = :sorular, durum = :durum, gecerlilik_tarihi = :gecerlilik_tarihi
                WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
            $stmt->execute($alanlar);
            return $id;
        }

        $token = bin2hex(random_bytes(24));
        $alanlar['firma_id'] = $firmaId;
        $alanlar['token_hash'] = hash('sha256', $token);
        $alanlar['token_sifreli'] = Security::encrypt($token);
        $alanlar['olusturan_id'] = $userId > 0 ? $userId : null;
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, baslik, departman, gorev, calisma_yeri, aciklama, aranan_nitelikler, ai_kriterleri, sorular,
             durum, gecerlilik_tarihi, token_hash, token_sifreli, olusturan_id)
            VALUES (:firma_id, :baslik, :departman, :gorev, :calisma_yeri, :aciklama, :aranan_nitelikler, :ai_kriterleri,
             :sorular, :durum, :gecerlilik_tarihi, :token_hash, :token_sifreli, :olusturan_id)");
        $stmt->execute($alanlar);
        return (int) $this->db->lastInsertId();
    }

    public function durumGuncelle(int $id, int $firmaId, string $durum): bool
    {
        if (!isset(self::DURUMLAR[$durum])) {
            return false;
        }
        $stmt = $this->db->prepare("UPDATE {$this->table} SET durum = :durum
            WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute(['durum' => $durum, 'id' => $id, 'firma_id' => $firmaId]);
        return $stmt->rowCount() > 0;
    }

    public function softDeleteForFirma(int $id, int $firmaId): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET deleted_at = NOW(), is_active = 0, durum = 'kapali'
            WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id, 'firma_id' => $firmaId]);
        return $stmt->rowCount() > 0;
    }

    public function ajaxList(array $params, int $firmaId): array
    {
        $kolonlar = [
            1 => 'i.baslik',
            2 => 'i.departman',
            3 => 'i.durum',
            4 => 'goruntuleme_sayisi',
            5 => 'basvuru_sayisi',
            6 => 'olumlu_sayisi',
            7 => 'i.created_at',
        ];
        $where = "i.firma_id = :firma_id AND i.is_active = 1 AND i.deleted_at IS NULL";
        $bind = ['firma_id' => $firmaId];

        $arama = trim((string) ($params['search']['value'] ?? ''));
        if ($arama !== '') {
            $where .= " AND (i.baslik LIKE :arama OR i.departman LIKE :arama OR i.gorev LIKE :arama)";
            $bind['arama'] = '%' . $arama . '%';
        }

        $durumFiltre = (string) ($params['durum_filtre'] ?? 'all');
        if (isset(self::DURUMLAR[$durumFiltre])) {
            $where .= " AND i.durum = :durum_filtre";
            $bind['durum_filtre'] = $durumFiltre;
        }

        foreach (($params['columns'] ?? []) as $i => $kolon) {
            $deger = trim((string) ($kolon['search']['value'] ?? ''));
            if ($deger === '' || !in_array((int) $i, [1, 2], true)) {
                continue;
            }
            $where .= " AND {$kolonlar[(int) $i]} LIKE :kol_$i";
            $bind["kol_$i"] = '%' . $deger . '%';
        }

        $ilanBasvuru = "(SELECT COUNT(*) FROM ik_basvurular b WHERE b.ilan_id = i.id AND b.is_active = 1 AND b.deleted_at IS NULL)";
        $ilanOlumlu = "(SELECT COUNT(*) FROM ik_basvurular b WHERE b.ilan_id = i.id AND b.is_active = 1 AND b.deleted_at IS NULL AND b.ai_etiket = 'olumlu')";
        $ilanGoruntuleme = "(SELECT COUNT(*) FROM ik_ilan_goruntulemeler g WHERE g.ilan_id = i.id)";
        $gelismis = [
            3 => ['select', 'i.durum', array_flip(self::DURUMLAR)],
            4 => ['number', $ilanGoruntuleme, []],
            5 => ['number', $ilanBasvuru, []],
            6 => ['number', $ilanOlumlu, []],
            7 => ['date', 'i.created_at', []],
        ];
        foreach ($gelismis as $i => [$tip, $ifade, $harita]) {
            $where .= \App\Helper\DataTableColumnFilter::apply($tip, (string) ($params['columns'][$i]['search']['value'] ?? ''), $ifade, "gkol_$i", $bind, $harita);
        }

        $toplam = $this->sayac("SELECT COUNT(*) FROM {$this->table} i WHERE i.firma_id = :firma_id AND i.is_active = 1 AND i.deleted_at IS NULL", ['firma_id' => $firmaId]);
        $filtreli = $this->sayac("SELECT COUNT(*) FROM {$this->table} i WHERE $where", $bind);

        $siralama = 'i.created_at DESC';
        $siraIndex = (int) ($params['order'][0]['column'] ?? -1);
        if (isset($kolonlar[$siraIndex])) {
            $yon = strtolower((string) ($params['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
            $siralama = $kolonlar[$siraIndex] . ' ' . $yon;
        }

        $baslangic = max(0, (int) ($params['start'] ?? 0));
        $uzunluk = (int) ($params['length'] ?? 25);
        $uzunluk = $uzunluk > 0 ? min($uzunluk, 200) : 25;

        $stmt = $this->db->prepare("SELECT i.id, i.baslik, i.departman, i.gorev, i.durum, i.created_at,
                (SELECT COUNT(*) FROM ik_ilan_goruntulemeler g WHERE g.ilan_id = i.id) AS goruntuleme_sayisi,
                (SELECT COUNT(*) FROM ik_basvurular b WHERE b.ilan_id = i.id AND b.is_active = 1 AND b.deleted_at IS NULL) AS basvuru_sayisi,
                (SELECT COUNT(*) FROM ik_basvurular b WHERE b.ilan_id = i.id AND b.is_active = 1 AND b.deleted_at IS NULL AND b.ai_etiket = 'olumlu') AS olumlu_sayisi
            FROM {$this->table} i
            WHERE $where
            ORDER BY $siralama
            LIMIT $baslangic, $uzunluk");
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
                SUM(durum = 'yayinda') AS yayinda,
                SUM(durum = 'taslak') AS taslak,
                SUM(durum = 'kapali') AS kapali
            FROM {$this->table}
            WHERE firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute(['firma_id' => $firmaId]);
        $r = $stmt->fetch(PDO::FETCH_OBJ);
        foreach (['toplam', 'yayinda', 'taslak', 'kapali'] as $k) {
            $r->$k = (int) ($r->$k ?? 0);
        }
        $b = $this->db->prepare("SELECT COUNT(*) FROM ik_basvurular WHERE firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
        $b->execute(['firma_id' => $firmaId]);
        $r->basvuru = (int) $b->fetchColumn();
        return $r;
    }

    public function firmaAdi(int $firmaId): string
    {
        $stmt = $this->db->prepare("SELECT COALESCE(NULLIF(firma_unvan, ''), firma_adi) FROM firmalar WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $firmaId]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    private function sayac(string $sql, array $bind): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bind);
        return (int) $stmt->fetchColumn();
    }
}
