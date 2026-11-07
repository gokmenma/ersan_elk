<?php

namespace App\Model;

use App\Helper\Security;
use PDO;

class SiteSssModel extends SiteModel
{
    public const DURUMLAR = ['taslak' => 'Taslak', 'yayinda' => 'Yayında'];

    public function __construct()
    {
        parent::__construct('site_sss');
    }

    public function bul(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function kaydet(array $veri, int $kullaniciId): int
    {
        $id = (int) ($veri['id'] ?? 0);
        $bos = static fn($v) => $v !== '' && $v !== null ? $v : null;
        $alanlar = [
            'soru' => $veri['soru'],
            'cevap' => $veri['cevap'],
            'sira' => (int) $veri['sira'],
            'durum' => $veri['durum'],
        ];

        if ($id > 0) {
            $alanlar['id'] = $id;
            $stmt = $this->db->prepare("UPDATE {$this->table} SET soru = :soru, cevap = :cevap, sira = :sira, durum = :durum
                WHERE id = :id AND is_active = 1 AND deleted_at IS NULL");
            $stmt->execute($alanlar);
            return $id;
        }

        $alanlar['olusturan_id'] = $kullaniciId > 0 ? $kullaniciId : null;
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (soru, cevap, sira, durum, olusturan_id)
            VALUES (:soru, :cevap, :sira, :durum, :olusturan_id)");
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
        $stmt = $this->db->prepare("UPDATE {$this->table} SET deleted_at = NOW(), is_active = 0, durum = 'taslak' WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function yayindakiListe(): array
    {
        $stmt = $this->db->prepare("SELECT soru, cevap, sira
            FROM {$this->table}
            WHERE is_active = 1 AND deleted_at IS NULL AND durum = 'yayinda'
            ORDER BY sira ASC, id ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function summary(): object
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS toplam,
                COALESCE(SUM(durum = 'yayinda'), 0) AS yayinda,
                COALESCE(SUM(durum = 'taslak'), 0) AS taslak,
                MAX(COALESCE(updated_at, created_at)) AS ekstra
            FROM {$this->table} WHERE is_active = 1 AND deleted_at IS NULL");
        $stmt->execute();
        $r = $stmt->fetch(PDO::FETCH_OBJ);
        foreach (['toplam', 'yayinda', 'taslak'] as $k) {
            $r->$k = (int) ($r->$k ?? 0);
        }
        $r->ekstra = $r->ekstra;
        return $r;
    }

    public function ajaxList(array $params): array
    {
        $kolonlar = [1 => 's.soru', 2 => 's.cevap', 3 => 's.sira', 4 => 's.durum', 5 => 's.updated_at'];
        $where = "s.is_active = 1 AND s.deleted_at IS NULL";
        $bind = [];

        $arama = trim((string) ($params['search']['value'] ?? ''));
        if ($arama !== '') {
            $where .= " AND (s.soru LIKE :arama OR s.cevap LIKE :arama)";
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

        $stmt = $this->db->prepare("SELECT s.id, s.soru, LEFT(s.cevap, 140) AS ozet, s.sira, s.durum,
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
