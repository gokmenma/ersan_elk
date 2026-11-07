<?php

namespace App\Model;

use App\Helper\Security;
use PDO;

class SiteMesajModel extends SiteModel
{
    public const DURUMLAR = ['yeni' => 'Yeni', 'okundu' => 'Okundu', 'cevaplandi' => 'Cevaplandı'];

    public function __construct()
    {
        parent::__construct('site_mesajlar');
    }

    public function ekle(array $veri): int
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (ad_soyad, telefon, email, konu, mesaj, ip_adresi, kvkk_onay_tarihi)
            VALUES (:ad_soyad, :telefon, :email, :konu, :mesaj, :ip_adresi, NOW())");
        $stmt->execute([
            'ad_soyad' => $veri['ad_soyad'],
            'telefon' => $veri['telefon'] !== '' ? $veri['telefon'] : null,
            'email' => $veri['email'] !== '' ? $veri['email'] : null,
            'konu' => $veri['konu'] !== '' ? $veri['konu'] : null,
            'mesaj' => $veri['mesaj'],
            'ip_adresi' => $veri['ip_adresi'] !== '' ? $veri['ip_adresi'] : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function denemeSayisi(string $ip, int $dakika = 60): int
    {
        return $this->sayac("SELECT COUNT(*) FROM site_mesaj_denemeleri WHERE ip_adresi = :ip AND created_at > (NOW() - INTERVAL " . max(1, $dakika) . " MINUTE)", ['ip' => $ip]);
    }

    public function denemeKaydet(string $ip, string $sonuc): void
    {
        $stmt = $this->db->prepare("INSERT INTO site_mesaj_denemeleri (ip_adresi, sonuc) VALUES (:ip, :sonuc)");
        $stmt->execute(['ip' => $ip, 'sonuc' => $sonuc]);
    }

    public function bul(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function durumGuncelle(int $id, string $durum, int $kullaniciId, ?string $not = null): bool
    {
        if (!isset(self::DURUMLAR[$durum])) {
            return false;
        }
        $stmt = $this->db->prepare("UPDATE {$this->table}
            SET durum = :durum,
                yonetici_notu = :not_metni,
                okuyan_id = COALESCE(okuyan_id, :okuyan),
                okunma_tarihi = COALESCE(okunma_tarihi, NOW())
            WHERE id = :id AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute([
            'durum' => $durum,
            'not_metni' => $not !== null && $not !== '' ? $not : null,
            'okuyan' => $kullaniciId > 0 ? $kullaniciId : null,
            'id' => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET deleted_at = NOW(), is_active = 0 WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function summary(): object
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS toplam,
                COALESCE(SUM(durum = 'yeni'), 0) AS yeni,
                COALESCE(SUM(durum = 'okundu'), 0) AS okundu,
                COALESCE(SUM(durum = 'cevaplandi'), 0) AS cevaplandi
            FROM {$this->table} WHERE is_active = 1 AND deleted_at IS NULL");
        $stmt->execute();
        $r = $stmt->fetch(PDO::FETCH_OBJ);
        foreach (['toplam', 'yeni', 'okundu', 'cevaplandi'] as $k) {
            $r->$k = (int) ($r->$k ?? 0);
        }
        return $r;
    }

    public function ajaxList(array $params): array
    {
        $kolonlar = [1 => 's.ad_soyad', 2 => 's.konu', 3 => 's.mesaj', 4 => 's.durum', 5 => 's.created_at'];
        $where = "s.is_active = 1 AND s.deleted_at IS NULL";
        $bind = [];

        $arama = trim((string) ($params['search']['value'] ?? ''));
        if ($arama !== '') {
            $where .= " AND (s.ad_soyad LIKE :arama OR s.konu LIKE :arama OR s.mesaj LIKE :arama OR s.email LIKE :arama OR s.telefon LIKE :arama)";
            $bind['arama'] = '%' . $arama . '%';
        }

        $durumFiltre = (string) ($params['durum_filtre'] ?? 'all');
        if (isset(self::DURUMLAR[$durumFiltre])) {
            $where .= " AND s.durum = :durum_filtre";
            $bind['durum_filtre'] = $durumFiltre;
        }

        foreach (($params['columns'] ?? []) as $i => $kolon) {
            $deger = trim((string) ($kolon['search']['value'] ?? ''));
            if ($deger === '' || !in_array((int) $i, [1, 2, 3], true)) {
                continue;
            }
            $where .= " AND {$kolonlar[(int) $i]} LIKE :kol_$i";
            $bind["kol_$i"] = '%' . $deger . '%';
        }

        $gelismis = [
            4 => ['select', 's.durum', array_flip(self::DURUMLAR)],
            5 => ['date', 's.created_at', []],
        ];
        foreach ($gelismis as $i => [$tip, $ifade, $harita]) {
            $where .= \App\Helper\DataTableColumnFilter::apply($tip, (string) ($params['columns'][$i]['search']['value'] ?? ''), $ifade, "gkol_$i", $bind, $harita);
        }

        $toplam = $this->sayac("SELECT COUNT(*) FROM {$this->table} s WHERE s.is_active = 1 AND s.deleted_at IS NULL");
        $filtreli = $this->sayac("SELECT COUNT(*) FROM {$this->table} s WHERE $where", $bind);

        $siralama = 's.created_at DESC, s.id DESC';
        $siraIndex = (int) ($params['order'][0]['column'] ?? -1);
        if (isset($kolonlar[$siraIndex])) {
            $yon = strtolower((string) ($params['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
            $siralama = $kolonlar[$siraIndex] . ' ' . $yon;
        }

        $baslangic = max(0, (int) ($params['start'] ?? 0));
        $uzunluk = (int) ($params['length'] ?? 25);
        $uzunluk = $uzunluk > 0 ? min($uzunluk, 200) : 25;

        $stmt = $this->db->prepare("SELECT s.id, s.ad_soyad, s.telefon, s.email, s.konu, LEFT(s.mesaj, 160) AS ozet, s.durum, s.created_at
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
