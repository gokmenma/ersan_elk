<?php

namespace App\Model;

use App\Service\ImageUploadService;
use Exception;
use PDO;

class KesmeAcmaIslemModel extends Model
{
    protected $table = 'kesme_acma_islem';

    const UPLOAD_DIR = 'uploads/aparat_takip';

    const FOTO_TURLERI = ['sayac', 'aparat', 'iptal'];

    const APARAT_DURUMLARI = [
        'alindi' => 'Aparat alındı',
        'hasarli' => 'Hasarlı geldi',
        'bulunamadi' => 'Bulunamadı / kayıp',
    ];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    public static function rootPath(): string
    {
        return defined('ROOT') ? ROOT : dirname(__DIR__, 2);
    }

    public function ekle(array $veri): int
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, client_uuid, islem_tipi, ekip_id, ekip_adi, personel_id, abone_no, sayac_no,
             abone_adi, ilce, mahalle, adres, enlem, boylam, aparat_tip_id, adet, aparatsiz,
             aparat_durumu, kaynak, cihaz_zamani, offline_olusturma, negatif_stok, mukerrer_uyari,
             aciklama, tarih, kaydeden_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $this->firmaId(),
            $veri['client_uuid'] ?: null,
            $veri['islem_tipi'],
            (int) $veri['ekip_id'],
            $veri['ekip_adi'] ?? null,
            $veri['personel_id'] ?? null,
            $veri['abone_no'] ?? null,
            $veri['sayac_no'] ?? null,
            $veri['abone_adi'] ?? null,
            $veri['ilce'] ?? null,
            $veri['mahalle'] ?? null,
            $veri['adres'] ?? null,
            $veri['enlem'] ?? null,
            $veri['boylam'] ?? null,
            $veri['aparat_tip_id'] ?: null,
            (int) ($veri['adet'] ?? 1),
            (int) ($veri['aparatsiz'] ?? 0),
            $veri['aparat_durumu'] ?: null,
            $veri['kaynak'] ?? 'pwa',
            $veri['cihaz_zamani'] ?? null,
            $veri['offline_olusturma'] ?? null,
            (int) ($veri['negatif_stok'] ?? 0),
            (int) ($veri['mukerrer_uyari'] ?? 0),
            $veri['aciklama'] ?? null,
            $veri['tarih'] ?? date('Y-m-d'),
            $veri['kaydeden_id'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function negatifIsaretle(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET negatif_stok = 1
            WHERE id = ? AND firma_id = ?");
        $stmt->execute([$id, $this->firmaId()]);
    }

    public function getir(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT i.*, t.ad AS aparat_adi, t.kod AS aparat_kod,
                                           ek.tur_adi AS ekip_kodu, p.adi_soyadi AS personel_adi
                                    FROM {$this->table} i
                                    LEFT JOIN aparat_tipleri t ON t.id = i.aparat_tip_id
                                    LEFT JOIN tanimlamalar ek ON ek.id = i.ekip_id
                                    LEFT JOIN personel p ON p.id = i.personel_id
                                    WHERE i.id = ? AND i.firma_id = ? AND i.silinme_tarihi IS NULL");
        $stmt->execute([$id, $this->firmaId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function clientUuidIleBul(string $uuid): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table}
                                    WHERE firma_id = ? AND client_uuid = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$this->firmaId(), $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Aynı abonede aynı gün aynı işlem daha önce girilmiş mi (mükerrer uyarısı).
     */
    public function mukerrerVarMi(string $aboneNo, string $islemTipi, string $tarih, ?string $haricUuid = null): bool
    {
        if (trim($aboneNo) === '') {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE firma_id = ? AND abone_no = ? AND islem_tipi = ? AND tarih = ?
                  AND durum = 'aktif' AND silinme_tarihi IS NULL";
        $parametreler = [$this->firmaId(), $aboneNo, $islemTipi, $tarih];

        if ($haricUuid) {
            $sql .= " AND (client_uuid IS NULL OR client_uuid <> ?)";
            $parametreler[] = $haricUuid;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametreler);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function listele(array $filtre = [], int $limit = 500, int $offset = 0): array
    {
        [$kosul, $parametreler] = $this->filtreKosulu($filtre);

        $sql = "SELECT i.*, t.ad AS aparat_adi, t.kod AS aparat_kod, t.renk AS aparat_renk,
                       ek.tur_adi AS ekip_kodu, p.adi_soyadi AS personel_adi,
                       (SELECT COUNT(*) FROM kesme_acma_islem_foto f
                         WHERE f.islem_id = i.id AND f.silinme_tarihi IS NULL AND f.arsivlendi = 0) AS foto_sayisi
                FROM {$this->table} i
                LEFT JOIN aparat_tipleri t ON t.id = i.aparat_tip_id
                LEFT JOIN tanimlamalar ek ON ek.id = i.ekip_id
                LEFT JOIN personel p ON p.id = i.personel_id
                WHERE {$kosul}
                ORDER BY i.tarih DESC, i.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametreler);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sayisi(array $filtre = []): int
    {
        [$kosul, $parametreler] = $this->filtreKosulu($filtre);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} i WHERE {$kosul}");
        $stmt->execute($parametreler);
        return (int) $stmt->fetchColumn();
    }

    private function filtreKosulu(array $filtre): array
    {
        $kosul = 'i.firma_id = ? AND i.silinme_tarihi IS NULL';
        $parametreler = [$this->firmaId()];

        if (!empty($filtre['baslangic'])) {
            $kosul .= ' AND i.tarih >= ?';
            $parametreler[] = $filtre['baslangic'];
        }
        if (!empty($filtre['bitis'])) {
            $kosul .= ' AND i.tarih <= ?';
            $parametreler[] = $filtre['bitis'];
        }
        if (!empty($filtre['ekip_id'])) {
            $kosul .= ' AND i.ekip_id = ?';
            $parametreler[] = (int) $filtre['ekip_id'];
        }
        if (!empty($filtre['personel_id'])) {
            $kosul .= ' AND i.personel_id = ?';
            $parametreler[] = (int) $filtre['personel_id'];
        }
        if (!empty($filtre['islem_tipi'])) {
            $kosul .= ' AND i.islem_tipi = ?';
            $parametreler[] = $filtre['islem_tipi'];
        }
        if (!empty($filtre['aparat_tip_id'])) {
            $kosul .= ' AND i.aparat_tip_id = ?';
            $parametreler[] = (int) $filtre['aparat_tip_id'];
        }
        if (!empty($filtre['durum'])) {
            $kosul .= ' AND i.durum = ?';
            $parametreler[] = $filtre['durum'];
        }
        if (!empty($filtre['abone_no'])) {
            $kosul .= ' AND i.abone_no LIKE ?';
            $parametreler[] = '%' . $filtre['abone_no'] . '%';
        }
        if (!empty($filtre['sadece_negatif'])) {
            $kosul .= ' AND i.negatif_stok = 1';
        }

        return [$kosul, $parametreler];
    }

    public function iptalEt(int $id, string $aciklama, int $kullaniciId): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table}
            SET durum = 'iptal', iptal_aciklama = ?, iptal_tarihi = NOW(), iptal_eden = ?
            WHERE id = ? AND firma_id = ? AND durum = 'aktif'");
        $stmt->execute([$aciklama, $kullaniciId, $id, $this->firmaId()]);
        return $stmt->rowCount() > 0;
    }

    // ---------- Fotoğraflar ----------

    public function fotograflar(int $islemId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM kesme_acma_islem_foto
            WHERE islem_id = ? AND firma_id = ? AND silinme_tarihi IS NULL AND arsivlendi = 0
            ORDER BY FIELD(tur, 'sayac', 'aparat', 'iptal')");
        $stmt->execute([$islemId, $this->firmaId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fotografVarMi(int $islemId, string $tur): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM kesme_acma_islem_foto
            WHERE islem_id = ? AND tur = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$islemId, $tur]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function fotografEkle(int $islemId, string $tur, string $dosyaYolu, ?string $orijinalAd = null, ?int $personelId = null, ?int $userId = null): int
    {
        $stmt = $this->db->prepare("INSERT INTO kesme_acma_islem_foto
            (firma_id, islem_id, tur, dosya_yolu, orijinal_ad, yukleyen_personel_id, yukleyen_user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->firmaId(), $islemId, $tur, $dosyaYolu, $orijinalAd, $personelId, $userId]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Fotoğrafı küçülterek yıl/ay klasörüne yazar ve web köküne göre yolunu döner.
     */
    public function fotografKaydet(array $file, int $islemId, string $tur): string
    {
        if (!in_array($tur, self::FOTO_TURLERI, true)) {
            throw new Exception('Geçersiz fotoğraf türü.');
        }

        $altKlasor = self::UPLOAD_DIR . '/' . date('Y/m');
        $hedefDizin = self::rootPath() . '/' . $altKlasor;

        if (!is_dir($hedefDizin) && !mkdir($hedefDizin, 0755, true) && !is_dir($hedefDizin)) {
            throw new Exception('Fotoğraf klasörü oluşturulamadı.');
        }

        $servis = new ImageUploadService();
        $sonuc = $servis->store($file, $hedefDizin, 'aparat_' . $islemId . '_' . $tur, 1280, 70);

        $dosyaAdi = (string) ($sonuc['filename'] ?? basename((string) ($sonuc['path'] ?? '')));
        if ($dosyaAdi === '') {
            throw new Exception('Fotoğraf kaydedilemedi.');
        }

        return $altKlasor . '/' . $dosyaAdi;
    }

    public function fotografGetir(int $fotoId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM kesme_acma_islem_foto
            WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$fotoId, $this->firmaId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ---------- Raporlar ----------

    /**
     * Dönemsel özet: hareket tipi ve aparat tipi bazında saha işlem adetleri.
     */
    public function donemselOzet(string $baslangic, string $bitis, ?int $ekipId = null): array
    {
        $sql = "SELECT i.islem_tipi, i.aparat_tip_id, t.ad AS aparat_adi,
                       COUNT(*) AS kayit_sayisi, SUM(i.adet) AS aparat_adedi,
                       SUM(CASE WHEN i.aparat_durumu = 'hasarli' THEN i.adet ELSE 0 END) AS hasarli,
                       SUM(CASE WHEN i.aparat_durumu = 'bulunamadi' THEN i.adet ELSE 0 END) AS kayip
                FROM {$this->table} i
                LEFT JOIN aparat_tipleri t ON t.id = i.aparat_tip_id
                WHERE i.firma_id = ? AND i.durum = 'aktif' AND i.silinme_tarihi IS NULL
                  AND i.tarih BETWEEN ? AND ?";
        $parametreler = [$this->firmaId(), $baslangic, $bitis];

        if ($ekipId) {
            $sql .= " AND i.ekip_id = ?";
            $parametreler[] = $ekipId;
        }

        $sql .= " GROUP BY i.islem_tipi, i.aparat_tip_id, t.ad ORDER BY i.islem_tipi, t.ad";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametreler);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * KASKİ API özetindeki aparatlı kesim adetleri ile panel kayıtlarını karşılaştırır.
     */
    public function apiKarsilastirma(string $baslangic, string $bitis): array
    {
        $sql = "SELECT ek.id AS ekip_id, ek.tur_adi AS ekip_adi, y.tarih,
                       SUM(y.sonuclanmis) AS api_adet
                FROM yapilan_isler y
                INNER JOIN tanimlamalar ek ON ek.id = y.ekip_kodu_id
                WHERE y.firma_id = ? AND y.silinme_tarihi IS NULL
                  AND y.tarih BETWEEN ? AND ?
                  AND y.is_emri_sonucu LIKE '%APARAT%'
                GROUP BY ek.id, ek.tur_adi, y.tarih";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);
        $api = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT i.ekip_id, i.tarih, ek.tur_adi AS ekip_adi, SUM(i.adet) AS panel_adet
                FROM {$this->table} i
                LEFT JOIN tanimlamalar ek ON ek.id = i.ekip_id
                WHERE i.firma_id = ? AND i.islem_tipi = 'kesme' AND i.durum = 'aktif'
                  AND i.silinme_tarihi IS NULL AND i.aparatsiz = 0
                  AND i.tarih BETWEEN ? AND ?
                GROUP BY i.ekip_id, i.tarih, ek.tur_adi";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);
        $panel = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $panel[$satir['ekip_id'] . '|' . $satir['tarih']] = [
                'adet' => (int) $satir['panel_adet'],
                'ekip_adi' => (string) $satir['ekip_adi'],
            ];
        }

        $sonuc = [];
        foreach ($api as $satir) {
            $anahtar = $satir['ekip_id'] . '|' . $satir['tarih'];
            $panelAdet = (int) ($panel[$anahtar]['adet'] ?? 0);
            unset($panel[$anahtar]);

            $sonuc[] = [
                'ekip_id' => (int) $satir['ekip_id'],
                'ekip_adi' => $satir['ekip_adi'],
                'tarih' => $satir['tarih'],
                'api_adet' => (int) $satir['api_adet'],
                'panel_adet' => $panelAdet,
                'fark' => $panelAdet - (int) $satir['api_adet'],
            ];
        }

        foreach ($panel as $anahtar => $bilgi) {
            [$ekipId, $tarih] = explode('|', $anahtar);
            $sonuc[] = [
                'ekip_id' => (int) $ekipId,
                'ekip_adi' => $bilgi['ekip_adi'],
                'tarih' => $tarih,
                'api_adet' => 0,
                'panel_adet' => (int) $bilgi['adet'],
                'fark' => (int) $bilgi['adet'],
            ];
        }

        usort($sonuc, function ($a, $b) {
            return [$b['tarih'], $a['ekip_adi']] <=> [$a['tarih'], $b['ekip_adi']];
        });

        return $sonuc;
    }
}
