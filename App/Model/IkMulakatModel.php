<?php

namespace App\Model;

use PDO;

class IkMulakatModel extends Model
{
    protected $table = 'ik_basvuru_mulakatlari';

    public const TURLER = ['telefon' => 'Telefon', 'yuz_yuze' => 'Yüz yüze', 'online' => 'Online'];
    public const SONUCLAR = ['olumlu' => 'Olumlu', 'olumsuz' => 'Olumsuz', 'beklemede' => 'Beklemede'];

    public const DUZELTILEBILIR_ALANLAR = [
        'egitim' => 'Eğitim',
        'deneyim_yili' => 'Deneyim (yıl)',
        'ehliyet' => 'Ehliyet',
        'maas_beklentisi' => 'Maaş beklentisi',
        'ise_baslama' => 'İşe başlama',
        'sehir' => 'Şehir',
        'deneyim_ozet' => 'Deneyim özeti',
    ];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function listele(int $basvuruId, int $firmaId): array
    {
        try {
            return $this->listeleSorgu($basvuruId, $firmaId);
        } catch (\PDOException $e) {
            error_log('İK mülakat kayıtları okunamadı: ' . $e->getMessage());
            return [];
        }
    }

    private function listeleSorgu(int $basvuruId, int $firmaId): array
    {
        $stmt = $this->db->prepare("SELECT m.id, m.mulakat_tarihi, m.tur, m.sonuc, m.genel_puan, m.genel_not, m.sorular_cevaplar, m.created_at, u.adi_soyadi AS yapan
            FROM {$this->table} m
            LEFT JOIN users u ON u.id = m.mulakat_yapan_id
            WHERE m.basvuru_id = :basvuru_id AND m.firma_id = :firma_id AND m.is_active = 1 AND m.deleted_at IS NULL
            ORDER BY m.mulakat_tarihi DESC, m.id DESC");
        $stmt->execute(['basvuru_id' => $basvuruId, 'firma_id' => $firmaId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function bul(int $id, int $firmaId)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute(['id' => $id, 'firma_id' => $firmaId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function kaydet(int $id, array $v): int
    {
        $bind = [
            'mulakat_tarihi' => $v['mulakat_tarihi'],
            'tur' => $v['tur'],
            'sonuc' => $v['sonuc'],
            'genel_puan' => $v['genel_puan'],
            'genel_not' => $v['genel_not'],
            'sorular_cevaplar' => $v['sorular_cevaplar'],
            'firma_id' => $v['firma_id'],
        ];
        if ($id > 0) {
            $stmt = $this->db->prepare("UPDATE {$this->table} SET mulakat_tarihi = :mulakat_tarihi, tur = :tur, sonuc = :sonuc,
                genel_puan = :genel_puan, genel_not = :genel_not, sorular_cevaplar = :sorular_cevaplar
                WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
            $stmt->execute($bind + ['id' => $id]);
            return $id;
        }
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, basvuru_id, mulakat_tarihi, tur, mulakat_yapan_id, sonuc, genel_puan, genel_not, sorular_cevaplar)
            VALUES (:firma_id, :basvuru_id, :mulakat_tarihi, :tur, :mulakat_yapan_id, :sonuc, :genel_puan, :genel_not, :sorular_cevaplar)");
        $stmt->execute($bind + ['basvuru_id' => $v['basvuru_id'], 'mulakat_yapan_id' => $v['mulakat_yapan_id'] > 0 ? $v['mulakat_yapan_id'] : null]);
        return (int) $this->db->lastInsertId();
    }

    public function softDeleteForFirma(int $id, int $firmaId): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET deleted_at = NOW(), is_active = 0
            WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL");
        $stmt->execute(['id' => $id, 'firma_id' => $firmaId]);
        return $stmt->rowCount() > 0;
    }

    public function guncelDegerler(int $basvuruId, int $firmaId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT g.alan, g.yeni_deger FROM ik_basvuru_bilgi_guncellemeleri g
                JOIN (SELECT alan, MAX(id) AS son_id FROM ik_basvuru_bilgi_guncellemeleri
                      WHERE basvuru_id = :basvuru_id AND firma_id = :firma_id GROUP BY alan) s ON s.son_id = g.id");
            $stmt->execute(['basvuru_id' => $basvuruId, 'firma_id' => $firmaId]);
        } catch (\PDOException $e) {
            error_log('İK bilgi güncellemeleri okunamadı: ' . $e->getMessage());
            return [];
        }
        $sonuc = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $satir) {
            $sonuc[$satir->alan] = $satir->yeni_deger;
        }
        return $sonuc;
    }

    public function guncellemeEkle(int $firmaId, int $basvuruId, ?int $mulakatId, string $alan, ?string $eski, ?string $yeni, int $kullaniciId): void
    {
        $stmt = $this->db->prepare("INSERT INTO ik_basvuru_bilgi_guncellemeleri
            (firma_id, basvuru_id, mulakat_id, alan, eski_deger, yeni_deger, kullanici_id)
            VALUES (:firma_id, :basvuru_id, :mulakat_id, :alan, :eski, :yeni, :kullanici_id)");
        $stmt->execute([
            'firma_id' => $firmaId,
            'basvuru_id' => $basvuruId,
            'mulakat_id' => $mulakatId,
            'alan' => $alan,
            'eski' => $eski,
            'yeni' => $yeni,
            'kullanici_id' => $kullaniciId > 0 ? $kullaniciId : null,
        ]);
    }

    public function guncelUygula(object $basvuru, array $guncel): object
    {
        $kopya = clone $basvuru;
        foreach ($guncel as $alan => $deger) {
            if (isset(self::DUZELTILEBILIR_ALANLAR[$alan])) {
                $kopya->$alan = $deger;
            }
        }
        return $kopya;
    }
}
