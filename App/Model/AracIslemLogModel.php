<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class AracIslemLogModel extends Model
{
    protected $table = 'arac_islem_loglari';

    const TAKIP_ALANLARI = [
        'plaka' => 'Plaka',
        'marka' => 'Marka',
        'model' => 'Model',
        'model_yili' => 'Model Yılı',
        'arac_tipi' => 'Araç Tipi',
        'yakit_tipi' => 'Yakıt Tipi',
        'renk' => 'Renk',
        'mulkiyet' => 'Mülkiyet',
        'sase_no' => 'Şase No',
        'motor_no' => 'Motor No',
        'departmani' => 'Departman',
        'ruhsat_sahibi' => 'Ruhsat Sahibi',
        'muayene_bitis_tarihi' => 'Muayene Bitiş',
        'sigorta_bitis_tarihi' => 'Sigorta Bitiş',
        'kasko_bitis_tarihi' => 'Kasko Bitiş',
        'baslangic_km' => 'Başlangıç KM',
        'guncel_km' => 'Güncel KM',
        'aktif_mi' => 'Aktif',
        'ikame_mi' => 'İkame',
        'notlar' => 'Notlar',
    ];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function ekle(int $aracId, string $islemTuru, string $aciklama, array $degisiklikler = []): void
    {
        if ($aracId <= 0) {
            return;
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} (firma_id, arac_id, user_id, islem_turu, aciklama, degisiklikler, ip_adresi)
                VALUES (:firma_id, :arac_id, :user_id, :islem_turu, :aciklama, :degisiklikler, :ip_adresi)
            ");
            $stmt->execute([
                'firma_id' => (int) ($_SESSION['firma_id'] ?? 0),
                'arac_id' => $aracId,
                'user_id' => (int) ($_SESSION['user_id'] ?? 0),
                'islem_turu' => mb_substr($islemTuru, 0, 50),
                'aciklama' => mb_substr($aciklama, 0, 500),
                'degisiklikler' => $degisiklikler ? json_encode(array_values($degisiklikler), JSON_UNESCAPED_UNICODE) : null,
                'ip_adresi' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            error_log('AracIslemLogModel::ekle hata: ' . $e->getMessage());
        }
    }

    public function farklariBul($eski, array $yeni): array
    {
        $eski = (array) $eski;
        $farklar = [];
        foreach (self::TAKIP_ALANLARI as $alan => $etiket) {
            if (!array_key_exists($alan, $yeni)) {
                continue;
            }
            $eskiDeger = $this->normalize($alan, $eski[$alan] ?? null);
            $yeniDeger = $this->normalize($alan, $yeni[$alan]);
            if ($eskiDeger !== $yeniDeger) {
                $farklar[] = ['alan' => $etiket, 'eski' => $eskiDeger, 'yeni' => $yeniDeger];
            }
        }
        return $farklar;
    }

    private function normalize(string $alan, $deger): ?string
    {
        if ($deger === null || $deger === '' || $deger === '0000-00-00') {
            return null;
        }
        if (in_array($alan, ['aktif_mi', 'ikame_mi'], true)) {
            return (int) $deger === 1 ? 'Evet' : 'Hayır';
        }
        if (in_array($alan, ['baslangic_km', 'guncel_km', 'model_yili'], true)) {
            return (string) (int) $deger;
        }
        if (str_ends_with($alan, '_tarihi')) {
            $ts = strtotime((string) $deger);
            return $ts ? date('d.m.Y', $ts) : (string) $deger;
        }
        return trim((string) $deger);
    }

    public function aracGecmisi(int $aracId, int $firmaId, int $limit = 200): array
    {
        $stmt = $this->db->prepare("
            SELECT l.id, l.islem_turu, l.aciklama, l.degisiklikler, l.ip_adresi, l.created_at,
                   u.adi_soyadi AS kullanici_adi
            FROM {$this->table} l
            LEFT JOIN users u ON u.id = l.user_id
            WHERE l.arac_id = :arac_id AND l.firma_id = :firma_id
            ORDER BY l.created_at DESC, l.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':arac_id', $aracId, PDO::PARAM_INT);
        $stmt->bindValue(':firma_id', $firmaId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
