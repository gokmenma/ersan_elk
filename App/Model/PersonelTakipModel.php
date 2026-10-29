<?php

namespace App\Model;

use PDO;

class PersonelTakipModel extends Model
{
    public const VARSAYILAN_MESAI = '08:30';
    public const MAKS_ACIK_GOREV_DK = 840;
    public const KONUM_ISTEK_ARALIK_DK = 2;

    protected $table = 'personel_hareketleri';
    protected $primaryKey = 'id';

    private ?array $mesaiAyarlari = null;

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function departmanMesaileri(): array
    {
        if ($this->mesaiAyarlari === null) {
            $json = (new SettingsModel())->getSettings('personel_takip_departman_mesaileri');
            $decoded = $json ? json_decode($json, true) : [];
            $this->mesaiAyarlari = is_array($decoded) ? $decoded : [];
        }
        return $this->mesaiAyarlari;
    }

    public function mesaiSaati(?string $departman): string
    {
        $ayarlar = $this->departmanMesaileri();
        $saat = ($departman !== null && $departman !== '' && isset($ayarlar[$departman])) ? (string) $ayarlar[$departman] : '';
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $saat) ? $saat : self::VARSAYILAN_MESAI;
    }

    public function departmanMesaiListesi(int $firmaId): array
    {
        $stmt = $this->db->prepare("SELECT DISTINCT departman FROM personel
            WHERE firma_id = :firma_id AND silinme_tarihi IS NULL AND departman IS NOT NULL AND departman <> ''
            ORDER BY departman ASC");
        $stmt->execute([':firma_id' => $firmaId]);
        $liste = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $departman) {
            $liste[] = ['departman' => (string) $departman, 'mesai_saati' => $this->mesaiSaati((string) $departman)];
        }
        return $liste;
    }

    public function departmanMesaileriniKaydet(array $ayarlar): bool
    {
        $mevcut = $this->departmanMesaileri();
        foreach ($ayarlar as $departman => $saat) {
            $departman = trim((string) $departman);
            $saat = trim((string) $saat);
            if ($departman === '' || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $saat)) {
                continue;
            }
            $mevcut[$departman] = $saat;
        }
        $ok = (new SettingsModel())->upsertSetting('personel_takip_departman_mesaileri', json_encode($mevcut, JSON_UNESCAPED_UNICODE));
        if ($ok) {
            $this->mesaiAyarlari = $mevcut;
        }
        return $ok;
    }

    public function takipPersonelleri(int $firmaId, ?string $departman = null, ?int $personelId = null): array
    {
        $sql = "SELECT id, adi_soyadi, departman, gorev, cep_telefonu, resim_yolu
                FROM personel
                WHERE firma_id = :firma_id AND silinme_tarihi IS NULL AND aktif_mi = 1 AND saha_takibi = 1";
        $params = [':firma_id' => $firmaId];
        if ($departman !== null && $departman !== '') {
            $sql .= " AND departman = :departman";
            $params[':departman'] = $departman;
        }
        if ($personelId) {
            $sql .= " AND id = :personel_id";
            $params[':personel_id'] = $personelId;
        }
        $sql .= " ORDER BY adi_soyadi ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function takipPersoneli(int $personelId, int $firmaId): ?array
    {
        $rows = $this->takipPersonelleri($firmaId, null, $personelId);
        return $rows[0] ?? null;
    }

    public function hareketler(int $firmaId, string $baslangic, string $bitis, ?int $personelId = null): array
    {
        $sql = "SELECT ph.id, ph.personel_id, ph.islem_tipi, ph.zaman, ph.konum_enlem, ph.konum_boylam,
                       ph.konum_hassasiyeti, ph.cihaz_bilgisi, ph.aciklama
                FROM personel_hareketleri ph
                INNER JOIN personel p ON p.id = ph.personel_id AND p.firma_id = :firma_id
                WHERE ph.silinme_tarihi IS NULL AND ph.zaman >= :bas AND ph.zaman <= :bit";
        $params = [':firma_id' => $firmaId, ':bas' => $baslangic . ' 00:00:00', ':bit' => $bitis . ' 23:59:59'];
        if ($personelId) {
            $sql .= " AND ph.personel_id = :personel_id";
            $params[':personel_id'] = $personelId;
        }
        $sql .= " ORDER BY ph.personel_id ASC, ph.zaman ASC, ph.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function izinGunleri(int $firmaId, string $baslangic, string $bitis, ?int $personelId = null): array
    {
        $sql = "SELECT pi.personel_id, DATE(pi.baslangic_tarihi) AS bas, DATE(pi.bitis_tarihi) AS bit, t.tur_adi
                FROM personel_izinleri pi
                INNER JOIN personel p ON p.id = pi.personel_id AND p.firma_id = :firma_id
                LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
                WHERE pi.silinme_tarihi IS NULL AND pi.onay_durumu = 'Onaylandı'
                  AND DATE(pi.baslangic_tarihi) <= :bit AND DATE(pi.bitis_tarihi) >= :bas
                  AND (t.kisa_kod IS NULL OR (t.kisa_kod NOT IN ('X', 'x') AND (t.normal_mesai_sayilir IS NULL OR t.normal_mesai_sayilir = 0)))";
        $params = [':firma_id' => $firmaId, ':bas' => $baslangic, ':bit' => $bitis];
        if ($personelId) {
            $sql .= " AND pi.personel_id = :personel_id";
            $params[':personel_id'] = $personelId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $gunler = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cur = max(strtotime($row['bas']), strtotime($baslangic));
            $end = min(strtotime($row['bit']), strtotime($bitis));
            while ($cur <= $end) {
                $gunler[(int) $row['personel_id']][date('Y-m-d', $cur)] = $row['tur_adi'] ?: 'İzin';
                $cur = strtotime('+1 day', $cur);
            }
        }
        return $gunler;
    }

    public function canliKonumlar(int $firmaId): array
    {
        $stmt = $this->db->prepare("SELECT ck.personel_id, ck.enlem, ck.boylam, ck.hassasiyet, ck.son_guncelleme
            FROM personel_canli_konumlari ck
            INNER JOIN personel p ON p.id = ck.personel_id AND p.firma_id = :firma_id
            WHERE p.silinme_tarihi IS NULL");
        $stmt->execute([':firma_id' => $firmaId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['personel_id']] = $row;
        }
        return $map;
    }

    public function bekleyenKonumIstekleri(int $firmaId): array
    {
        $stmt = $this->db->prepare("SELECT k.personel_id, MAX(k.istek_zamani) AS istek_zamani
            FROM personel_konum_istekleri k
            INNER JOIN personel p ON p.id = k.personel_id AND p.firma_id = :firma_id
            WHERE k.durum = 'BEKLIYOR' AND k.istek_zamani >= DATE_SUB(NOW(), INTERVAL " . self::KONUM_ISTEK_ARALIK_DK . " MINUTE)
            GROUP BY k.personel_id");
        $stmt->execute([':firma_id' => $firmaId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['personel_id']] = $row['istek_zamani'];
        }
        return $map;
    }

    public function konumIste(int $personelId): bool
    {
        $check = $this->db->prepare("SELECT id FROM personel_konum_istekleri WHERE personel_id = :pid AND durum = 'BEKLIYOR'
            AND istek_zamani >= DATE_SUB(NOW(), INTERVAL " . self::KONUM_ISTEK_ARALIK_DK . " MINUTE) LIMIT 1");
        $check->execute([':pid' => $personelId]);
        if ($check->fetchColumn()) {
            return false;
        }
        $stmt = $this->db->prepare("INSERT INTO personel_konum_istekleri (personel_id, durum, istek_zamani) VALUES (:pid, 'BEKLIYOR', NOW())");
        return $stmt->execute([':pid' => $personelId]);
    }

    public function aciklamalar(int $firmaId, string $baslangic, string $bitis, ?int $personelId = null): array
    {
        $sql = "SELECT a.personel_id, a.tarih, a.aciklama, a.guncellenme_tarihi, u.adi_soyadi AS guncelleyen_ad
                FROM personel_takip_aciklamalar a
                INNER JOIN personel p ON p.id = a.personel_id AND p.firma_id = :firma_id
                LEFT JOIN users u ON u.id = a.guncelleyen_kullanici_id
                WHERE a.tarih BETWEEN :bas AND :bit";
        $params = [':firma_id' => $firmaId, ':bas' => $baslangic, ':bit' => $bitis];
        if ($personelId) {
            $sql .= " AND a.personel_id = :personel_id";
            $params[':personel_id'] = $personelId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['personel_id']][$row['tarih']] = $row;
        }
        return $map;
    }

    public function aciklamaGecmisi(int $personelId, int $limit = 15): array
    {
        $stmt = $this->db->prepare("SELECT a.tarih, a.aciklama, a.guncellenme_tarihi, u.adi_soyadi AS guncelleyen_ad
            FROM personel_takip_aciklamalar a
            LEFT JOIN users u ON u.id = a.guncelleyen_kullanici_id
            WHERE a.personel_id = :pid AND a.aciklama IS NOT NULL AND a.aciklama <> ''
            ORDER BY a.tarih DESC LIMIT " . max(1, min(50, $limit)));
        $stmt->execute([':pid' => $personelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function aciklamaKaydet(int $personelId, string $tarih, string $aciklama, int $userId): bool
    {
        $stmt = $this->db->prepare("INSERT INTO personel_takip_aciklamalar
                (personel_id, tarih, aciklama, ekleyen_kullanici_id, guncelleyen_kullanici_id)
            VALUES (:pid, :tarih, :aciklama, :uid, :uid2)
            ON DUPLICATE KEY UPDATE aciklama = VALUES(aciklama), guncelleyen_kullanici_id = VALUES(guncelleyen_kullanici_id),
                guncellenme_tarihi = NOW()");
        return $stmt->execute([':pid' => $personelId, ':tarih' => $tarih, ':aciklama' => $aciklama, ':uid' => $userId, ':uid2' => $userId]);
    }

    public function gunlukOzetler(array $hareketler, string $bugun, int $nowTs): array
    {
        $gruplar = [];
        foreach ($hareketler as $h) {
            $gruplar[(int) $h['personel_id']][substr((string) $h['zaman'], 0, 10)][] = $h;
        }

        $sonuc = [];
        foreach ($gruplar as $pid => $gunler) {
            foreach ($gunler as $tarih => $liste) {
                $ilkBasla = null;
                $sonBitir = null;
                $acikBasla = null;
                $dakika = 0.0;
                $otomatik = false;
                foreach ($liste as $h) {
                    $ts = strtotime((string) $h['zaman']);
                    if ($h['islem_tipi'] === 'BASLA') {
                        $ilkBasla = $ilkBasla ?? $h;
                        $acikBasla = $ts;
                    } else {
                        $sonBitir = $h;
                        if ($acikBasla !== null && $ts > $acikBasla) {
                            $dakika += ($ts - $acikBasla) / 60;
                        }
                        $acikBasla = null;
                        if (!empty($h['aciklama']) && stripos((string) $h['aciklama'], 'otomatik') !== false) {
                            $otomatik = true;
                        }
                    }
                }
                $devamEdiyor = $acikBasla !== null;
                if ($devamEdiyor && $tarih === $bugun && $nowTs > $acikBasla) {
                    $fark = ($nowTs - $acikBasla) / 60;
                    if ($fark < self::MAKS_ACIK_GOREV_DK) {
                        $dakika += $fark;
                    }
                }
                if ($dakika <= 0 && $ilkBasla && $sonBitir) {
                    $fark = strtotime((string) $sonBitir['zaman']) - strtotime((string) $ilkBasla['zaman']);
                    $dakika = max(0, $fark / 60);
                }
                $son = end($liste);
                $sonuc[$pid][$tarih] = [
                    'ilk_basla' => $ilkBasla['zaman'] ?? null,
                    'ilk_konum' => $ilkBasla ? ['lat' => (float) $ilkBasla['konum_enlem'], 'lng' => (float) $ilkBasla['konum_boylam']] : null,
                    'son_bitir' => $sonBitir['zaman'] ?? null,
                    'son_konum' => $sonBitir ? ['lat' => (float) $sonBitir['konum_enlem'], 'lng' => (float) $sonBitir['konum_boylam']] : null,
                    'son_hareket' => [
                        'tip' => $son['islem_tipi'],
                        'zaman' => $son['zaman'],
                        'lat' => (float) $son['konum_enlem'],
                        'lng' => (float) $son['konum_boylam'],
                        'hassasiyet' => $son['konum_hassasiyeti'] !== null ? (float) $son['konum_hassasiyeti'] : null,
                    ],
                    'devam_ediyor' => $devamEdiyor,
                    'otomatik_kapandi' => $otomatik,
                    'dakika' => (int) round($dakika),
                    'hareket_sayisi' => count($liste),
                ];
            }
        }
        return $sonuc;
    }

    public function gecikmeDakika(string $tarih, ?string $ilkBasla, string $mesai, int $nowTs, bool $bugunMu): ?int
    {
        $limitTs = strtotime($tarih . ' ' . $mesai);
        if ($ilkBasla) {
            $basTs = strtotime($ilkBasla);
            return date('H:i', $basTs) > $mesai ? (int) max(0, floor(($basTs - $limitTs) / 60)) : null;
        }
        if ($bugunMu) {
            return $nowTs > $limitTs ? (int) floor(($nowTs - $limitTs) / 60) : null;
        }
        return null;
    }
}
