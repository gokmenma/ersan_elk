<?php

namespace App\Model;

use App\Helper\Security;
use PDO;

class SiteProjeModel extends SiteModel
{
    public const DURUMLAR = ['taslak' => 'Taslak', 'yayinda' => 'Yayında'];

    public function __construct()
    {
        parent::__construct('site_projeler');
    }

    public function bul(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function slugKullanimda(string $slug, int $haricId = 0): bool
    {
        return $this->sayac("SELECT COUNT(*) FROM {$this->table} WHERE slug = :slug AND id <> :id", ['slug' => $slug, 'id' => $haricId]) > 0;
    }

    public function kaydet(array $veri, int $kullaniciId): int
    {
        $id = (int) ($veri['id'] ?? 0);
        $bos = static fn($v) => $v !== '' && $v !== null ? $v : null;
        $alanlar = [
            'slug' => $veri['slug'],
            'baslik' => $veri['baslik'],
            'kategori' => $bos($veri['kategori']),
            'musteri' => $bos($veri['musteri']),
            'konum' => $bos($veri['konum']),
            'proje_yili' => $bos($veri['proje_yili']),
            'kisa_aciklama' => $bos($veri['kisa_aciklama']),
            'aciklama' => $bos($veri['aciklama']),
            'sira' => (int) $veri['sira'],
            'durum' => $veri['durum'],
        ];

        if ($id > 0) {
            $alanlar['id'] = $id;
            $gorselSql = '';
            if (array_key_exists('gorsel', $veri)) {
                $gorselSql = ', gorsel = :gorsel';
                $alanlar['gorsel'] = $veri['gorsel'];
            }
            $stmt = $this->db->prepare("UPDATE {$this->table} SET slug = :slug, baslik = :baslik, kategori = :kategori, musteri = :musteri,
                konum = :konum, proje_yili = :proje_yili, kisa_aciklama = :kisa_aciklama, aciklama = :aciklama,
                sira = :sira, durum = :durum{$gorselSql}
                WHERE id = :id AND is_active = 1 AND deleted_at IS NULL");
            $stmt->execute($alanlar);
            return $id;
        }

        $alanlar['gorsel'] = $veri['gorsel'] ?? null;
        $alanlar['olusturan_id'] = $kullaniciId > 0 ? $kullaniciId : null;
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (slug, baslik, kategori, musteri, konum, proje_yili, kisa_aciklama, aciklama, gorsel, sira, durum, olusturan_id)
            VALUES (:slug, :baslik, :kategori, :musteri, :konum, :proje_yili, :kisa_aciklama, :aciklama, :gorsel, :sira, :durum, :olusturan_id)");
        $stmt->execute($alanlar);
        return (int) $this->db->lastInsertId();
    }

    public function durumGuncelle(int $id, string $durum): bool
    {
        if (!isset(self::DURUMLAR[$durum])) {
            return false;
        }
        $stmt = $this->db->prepare("UPDATE {$this->table} SET durum = :durum WHERE id = :id AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute(['durum' => $durum, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table}
            SET deleted_at = NOW(), is_active = 0, durum = 'taslak', slug = CONCAT(slug, '-sil-', id)
            WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function yayindakiListe(): array
    {
        $stmt = $this->db->prepare("SELECT slug, baslik, kategori, musteri, konum, proje_yili, kisa_aciklama, aciklama, gorsel, sira
            FROM {$this->table}
            WHERE is_active = 1 AND deleted_at IS NULL AND durum = 'yayinda'
            ORDER BY sira ASC, id ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function kategoriler(): array
    {
        $stmt = $this->db->prepare("SELECT DISTINCT kategori FROM {$this->table}
            WHERE is_active = 1 AND deleted_at IS NULL AND kategori IS NOT NULL AND kategori <> '' ORDER BY kategori ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function summary(): object
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS toplam,
                COALESCE(SUM(durum = 'yayinda'), 0) AS yayinda,
                COALESCE(SUM(durum = 'taslak'), 0) AS taslak,
                COALESCE(SUM(gorsel IS NULL OR gorsel = ''), 0) AS gorselsiz
            FROM {$this->table} WHERE is_active = 1 AND deleted_at IS NULL");
        $stmt->execute();
        $r = $stmt->fetch(PDO::FETCH_OBJ);
        foreach (['toplam', 'yayinda', 'taslak', 'gorselsiz'] as $k) {
            $r->$k = (int) ($r->$k ?? 0);
        }
        return $r;
    }

    public function ajaxList(array $params): array
    {
        $kolonlar = [1 => 's.baslik', 2 => 's.kategori', 3 => 's.sira', 4 => 's.durum', 5 => 's.updated_at'];
        $where = "s.is_active = 1 AND s.deleted_at IS NULL";
        $bind = [];

        $arama = trim((string) ($params['search']['value'] ?? ''));
        if ($arama !== '') {
            $where .= " AND (s.baslik LIKE :arama OR s.slug LIKE :arama OR s.kategori LIKE :arama OR s.musteri LIKE :arama OR s.konum LIKE :arama)";
            $bind['arama'] = '%' . $arama . '%';
        }

        $durumFiltre = (string) ($params['durum_filtre'] ?? 'all');
        if (isset(self::DURUMLAR[$durumFiltre])) {
            $where .= " AND s.durum = :durum_filtre";
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

        $gelismis = [
            3 => ['number', 's.sira', []],
            4 => ['select', 's.durum', array_flip(self::DURUMLAR)],
            5 => ['date', 'COALESCE(s.updated_at, s.created_at)', []],
        ];
        foreach ($gelismis as $i => [$tip, $ifade, $harita]) {
            $where .= \App\Helper\DataTableColumnFilter::apply($tip, (string) ($params['columns'][$i]['search']['value'] ?? ''), $ifade, "gkol_$i", $bind, $harita);
        }

        $toplam = $this->sayac("SELECT COUNT(*) FROM {$this->table} s WHERE s.is_active = 1 AND s.deleted_at IS NULL");
        $filtreli = $this->sayac("SELECT COUNT(*) FROM {$this->table} s WHERE $where", $bind);

        $siralama = 's.sira ASC, s.id ASC';
        $siraIndex = (int) ($params['order'][0]['column'] ?? -1);
        if (isset($kolonlar[$siraIndex])) {
            $yon = strtolower((string) ($params['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $siralama = $kolonlar[$siraIndex] . ' ' . $yon;
        }

        $baslangic = max(0, (int) ($params['start'] ?? 0));
        $uzunluk = (int) ($params['length'] ?? 25);
        $uzunluk = $uzunluk > 0 ? min($uzunluk, 200) : 25;

        $stmt = $this->db->prepare("SELECT s.id, s.slug, s.baslik, s.kategori, s.konum, s.gorsel, s.sira, s.durum,
                COALESCE(s.updated_at, s.created_at) AS guncelleme
            FROM {$this->table} s
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
}
