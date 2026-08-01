<?php
namespace App\Model;

use App\Model\Model;
use PDO;
use Exception;

class KacakKontrolModel extends Model
{
    protected $table = 'kacak_kontrol';

    const ILCELER = [
        'Onikişubat',
        'Dulkadiroğlu',
        'Afşin',
        'Andırın',
        'Çağlayancerit',
        'Ekinözü',
        'Elbistan',
        'Göksun',
        'Nurhak',
        'Pazarcık',
        'Türkoğlu',
    ];

    const MERKEZ_ILCELER = ['Onikişubat', 'Dulkadiroğlu'];

    const TURLER = ['Kaçak', 'Abonesiz', 'Usülsüz'];

    const MAX_SAHA_FOTO = 4;

    const UPLOAD_DIR = 'uploads/kacak_kontrol';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Hakediş/prim sayımına dahil edilecek kayıtları belirleyen ortak SQL koşulu.
     * İptal edilmiş olsa bile "hakedişten düşülmeyecek" işaretli kayıtlar sayılır.
     */
    public static function hakedisKosulu(string $alias = ''): string
    {
        $p = $alias !== '' ? $alias . '.' : '';
        return "{$p}silinme_tarihi IS NULL
                AND {$p}onay_durumu = 'onaylandi'
                AND NOT ({$p}durum = 'iptal' AND {$p}hakedisten_dus = 1)";
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    /**
     * bootstrap.php yüklenmemiş olabilecek giriş noktaları için güvenli kök dizin.
     */
    public static function rootPath(): string
    {
        return defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2);
    }

    // =====================================================
    // LİSTELEME
    // =====================================================

    /**
     * @param array $filters tarih_baslangic, tarih_bitis, ilce, tur, durum, onay_durumu, personel_id, kaynak, arama
     */
    public function getRecords(array $filters = [], int $limit = 0, int $offset = 0): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $limitSql = '';
        if ($limit > 0) {
            $limitSql = ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }

        $sql = "SELECT k.*,
                       bp.adi_soyadi AS bildiren_adi,
                       u.adi_soyadi  AS onaylayan_adi,
                       ui.adi_soyadi AS iptal_eden_adi,
                       (SELECT COUNT(*) FROM kacak_kontrol_fotograflari f
                         WHERE f.kacak_id = k.id AND f.silinme_tarihi IS NULL AND f.arsivlendi = 0) AS foto_sayisi
                FROM kacak_kontrol k
                LEFT JOIN personel bp ON bp.id = k.bildiren_personel_id
                LEFT JOIN users u     ON u.id = k.onaylayan_id
                LEFT JOIN users ui    ON ui.id = k.iptal_eden
                WHERE {$where}
                ORDER BY k.tarih DESC, k.id DESC{$limitSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countRecords(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM kacak_kontrol k WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function buildWhere(array $filters): array
    {
        $where = ['k.firma_id = ?', 'k.silinme_tarihi IS NULL'];
        $params = [$this->firmaId()];

        if (!empty($filters['tarih_baslangic'])) {
            $where[] = 'k.tarih >= ?';
            $params[] = $filters['tarih_baslangic'];
        }
        if (!empty($filters['tarih_bitis'])) {
            $where[] = 'k.tarih <= ?';
            $params[] = $filters['tarih_bitis'];
        }
        if (!empty($filters['ilce'])) {
            $where[] = 'k.ilce = ?';
            $params[] = $filters['ilce'];
        }
        if (!empty($filters['tur'])) {
            $where[] = 'k.tur = ?';
            $params[] = $filters['tur'];
        }
        if (!empty($filters['durum'])) {
            $where[] = 'k.durum = ?';
            $params[] = $filters['durum'];
        }
        if (!empty($filters['onay_durumu'])) {
            $where[] = 'k.onay_durumu = ?';
            $params[] = $filters['onay_durumu'];
        }
        if (!empty($filters['kaynak'])) {
            $where[] = 'k.kaynak = ?';
            $params[] = $filters['kaynak'];
        }
        if (!empty($filters['personel_id'])) {
            $where[] = 'FIND_IN_SET(?, k.personel_ids)';
            $params[] = (int) $filters['personel_id'];
        }
        if (!empty($filters['arama'])) {
            $where[] = '(k.tutanak_no LIKE ? OR k.abone_adi LIKE ? OR k.sayac_no LIKE ? OR k.ekip_adi LIKE ?)';
            $like = '%' . $filters['arama'] . '%';
            array_push($params, $like, $like, $like, $like);
        }

        return [implode(' AND ', $where), $params];
    }

    public function getRecord(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT k.*, bp.adi_soyadi AS bildiren_adi
                                    FROM kacak_kontrol k
                                    LEFT JOIN personel bp ON bp.id = k.bildiren_personel_id
                                    WHERE k.id = ? AND k.firma_id = ? AND k.silinme_tarihi IS NULL");
        $stmt->execute([$id, $this->firmaId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['personel_ids_array'] = !empty($row['personel_ids'])
            ? array_values(array_filter(array_map('intval', explode(',', $row['personel_ids']))))
            : [];
        $row['fotograflar'] = $this->getPhotos($id);
        return $row;
    }

    public function getPendingCount(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM kacak_kontrol
                                    WHERE firma_id = ? AND onay_durumu = 'beklemede' AND silinme_tarihi IS NULL");
        $stmt->execute([$this->firmaId()]);
        return (int) $stmt->fetchColumn();
    }

    // =====================================================
    // KAYIT
    // =====================================================

    /**
     * Tek bir kaçak/abonesiz tutanak kaydı oluşturur.
     */
    public function createRecord(array $data): int
    {
        $personelIds = $this->normalizePersonelIds($data['personel_ids'] ?? []);
        $ekipAdi = $this->buildEkipAdi($personelIds);

        $tarih = $data['tarih'] ?? date('Y-m-d');
        $ilce = $this->normalizeIlce($data['ilce'] ?? '');
        $tur = in_array($data['tur'] ?? '', self::TURLER, true) ? $data['tur'] : 'Kaçak';
        $sayi = max(1, (int) ($data['sayi'] ?? 1));

        $islemId = md5(implode('|', [
            $tarih,
            implode(',', $personelIds),
            $ilce,
            $tur,
            $data['tutanak_no'] ?? '',
            $data['abone_adi'] ?? '',
            $data['sayac_no'] ?? '',
            $data['endeks'] ?? '',
            $sayi,
            microtime(true),
            random_int(1000, 9999),
        ]));

        $stmt = $this->db->prepare("INSERT INTO kacak_kontrol
            (firma_id, personel_ids, bildiren_personel_id, kaynak, onay_durumu, onaylayan_id, onay_tarihi,
             durum, tarih, ekip_adi, ilce, tur, tutanak_no, abone_adi, sayac_no, endeks, sayi, aciklama, islem_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'aktif', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $onayDurumu = $data['onay_durumu'] ?? 'onaylandi';
        $onaylayanId = $onayDurumu === 'onaylandi' ? ($data['onaylayan_id'] ?? ($_SESSION['user_id'] ?? null)) : null;
        $onayTarihi = $onayDurumu === 'onaylandi' ? date('Y-m-d H:i:s') : null;

        $stmt->execute([
            $this->firmaId(),
            implode(',', $personelIds),
            !empty($data['bildiren_personel_id']) ? (int) $data['bildiren_personel_id'] : null,
            $data['kaynak'] ?? 'masaustu',
            $onayDurumu,
            $onaylayanId,
            $onayTarihi,
            $tarih,
            $ekipAdi,
            $ilce,
            $tur,
            $data['tutanak_no'] ?? null,
            $data['abone_adi'] ?? null,
            $data['sayac_no'] ?? null,
            $data['endeks'] ?? null,
            $sayi,
            $data['aciklama'] ?? null,
            $islemId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateRecord(int $id, array $data): bool
    {
        $personelIds = $this->normalizePersonelIds($data['personel_ids'] ?? []);
        $ekipAdi = $this->buildEkipAdi($personelIds);
        $tur = in_array($data['tur'] ?? '', self::TURLER, true) ? $data['tur'] : 'Kaçak';

        $stmt = $this->db->prepare("UPDATE kacak_kontrol SET
                tarih = ?, personel_ids = ?, ekip_adi = ?, ilce = ?, tur = ?,
                tutanak_no = ?, abone_adi = ?, sayac_no = ?, endeks = ?, sayi = ?, aciklama = ?
            WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");

        return $stmt->execute([
            $data['tarih'] ?? date('Y-m-d'),
            implode(',', $personelIds),
            $ekipAdi,
            $this->normalizeIlce($data['ilce'] ?? ''),
            $tur,
            $data['tutanak_no'] ?? null,
            $data['abone_adi'] ?? null,
            $data['sayac_no'] ?? null,
            $data['endeks'] ?? null,
            max(1, (int) ($data['sayi'] ?? 1)),
            $data['aciklama'] ?? null,
            $id,
            $this->firmaId(),
        ]);
    }

    public function softDeleteRecord(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE kacak_kontrol SET silinme_tarihi = NOW()
                                    WHERE id = ? AND firma_id = ?");
        return $stmt->execute([$id, $this->firmaId()]);
    }

    // =====================================================
    // ONAY AKIŞI
    // =====================================================

    public function approve(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("UPDATE kacak_kontrol
                                    SET onay_durumu = 'onaylandi', onaylayan_id = ?, onay_tarihi = NOW(), red_nedeni = NULL
                                    WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        return $stmt->execute([$userId, $id, $this->firmaId()]);
    }

    public function reject(int $id, int $userId, string $neden): bool
    {
        $stmt = $this->db->prepare("UPDATE kacak_kontrol
                                    SET onay_durumu = 'reddedildi', onaylayan_id = ?, onay_tarihi = NOW(), red_nedeni = ?
                                    WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        return $stmt->execute([$userId, $neden, $id, $this->firmaId()]);
    }

    // =====================================================
    // İPTAL
    // =====================================================

    public function cancel(int $id, int $userId, string $aciklama, bool $hakedistenDus): bool
    {
        $stmt = $this->db->prepare("UPDATE kacak_kontrol
                                    SET durum = 'iptal', iptal_aciklama = ?, hakedisten_dus = ?,
                                        iptal_tarihi = NOW(), iptal_eden = ?
                                    WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        return $stmt->execute([$aciklama, $hakedistenDus ? 1 : 0, $userId, $id, $this->firmaId()]);
    }

    public function revertCancel(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE kacak_kontrol
                                    SET durum = 'aktif', iptal_aciklama = NULL, hakedisten_dus = 0,
                                        iptal_tarihi = NULL, iptal_eden = NULL
                                    WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        return $stmt->execute([$id, $this->firmaId()]);
    }

    // =====================================================
    // FOTOĞRAF
    // =====================================================

    public function getPhotos(int $kacakId, ?string $tur = null): array
    {
        $sql = "SELECT * FROM kacak_kontrol_fotograflari
                WHERE kacak_id = ? AND silinme_tarihi IS NULL AND arsivlendi = 0";
        $params = [$kacakId];
        if ($tur !== null) {
            $sql .= " AND tur = ?";
            $params[] = $tur;
        }
        $sql .= " ORDER BY tur DESC, id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPhotos(int $kacakId, string $tur): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM kacak_kontrol_fotograflari
                                    WHERE kacak_id = ? AND tur = ? AND silinme_tarihi IS NULL AND arsivlendi = 0");
        $stmt->execute([$kacakId, $tur]);
        return (int) $stmt->fetchColumn();
    }

    public function addPhoto(int $kacakId, string $tur, string $dosyaYolu, ?string $orijinalAd = null, ?int $personelId = null, ?int $userId = null): int
    {
        $stmt = $this->db->prepare("INSERT INTO kacak_kontrol_fotograflari
            (firma_id, kacak_id, tur, dosya_yolu, orijinal_ad, yukleyen_personel_id, yukleyen_user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->firmaId(), $kacakId, $tur, $dosyaYolu, $orijinalAd, $personelId, $userId]);
        return (int) $this->db->lastInsertId();
    }

    public function getPhoto(int $fotoId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM kacak_kontrol_fotograflari
                                    WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$fotoId, $this->firmaId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function deletePhoto(int $fotoId): bool
    {
        $foto = $this->getPhoto($fotoId);
        if (!$foto) {
            return false;
        }
        $stmt = $this->db->prepare("UPDATE kacak_kontrol_fotograflari SET silinme_tarihi = NOW() WHERE id = ?");
        $ok = $stmt->execute([$fotoId]);
        if ($ok) {
            $abs = self::rootPath() . '/' . ltrim($foto['dosya_yolu'], '/');
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
        return $ok;
    }

    /**
     * Arşivleme için tarih aralığındaki fotoğrafları listeler.
     */
    public function getPhotosForArchive(string $baslangic, string $bitis): array
    {
        $stmt = $this->db->prepare("SELECT f.*, k.tarih, k.ilce, k.tur AS kayit_turu, k.tutanak_no, k.abone_adi, k.ekip_adi
                                    FROM kacak_kontrol_fotograflari f
                                    INNER JOIN kacak_kontrol k ON k.id = f.kacak_id
                                    WHERE f.firma_id = ? AND f.silinme_tarihi IS NULL AND f.arsivlendi = 0
                                      AND k.tarih BETWEEN ? AND ?
                                    ORDER BY k.tarih ASC, k.ilce ASC, f.id ASC");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markPhotosArchived(array $fotoIds): bool
    {
        if (empty($fotoIds)) {
            return true;
        }
        $ids = array_map('intval', $fotoIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("UPDATE kacak_kontrol_fotograflari
                                    SET arsivlendi = 1, arsiv_tarihi = NOW()
                                    WHERE id IN ($placeholders) AND firma_id = ?");
        return $stmt->execute(array_merge($ids, [$this->firmaId()]));
    }

    // =====================================================
    // RAPORLAR
    // =====================================================

    /**
     * Günlük rapor: seçilen güne ait ilçe bazlı tür kırılımlı tutanak sayıları.
     */
    public function getGunlukRapor(string $tarih): array
    {
        $stmt = $this->db->prepare("SELECT ilce, tur, SUM(sayi) AS toplam
                                    FROM kacak_kontrol
                                    WHERE firma_id = ? AND tarih = ?
                                      AND " . self::hakedisKosulu() . "
                                    GROUP BY ilce, tur");
        $stmt->execute([$this->firmaId(), $tarih]);

        $sonuc = array_fill_keys(self::TURLER, []);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ilce = $row['ilce'] !== null && $row['ilce'] !== '' ? $row['ilce'] : 'Belirtilmemiş';
            $tur = $row['tur'] ?: 'Kaçak';
            if (!isset($sonuc[$tur])) {
                $sonuc[$tur] = [];
            }
            $sonuc[$tur][$ilce] = ($sonuc[$tur][$ilce] ?? 0) + (int) $row['toplam'];
        }

        foreach ($sonuc as $tur => $liste) {
            arsort($sonuc[$tur]);
        }

        return $sonuc;
    }

    /**
     * Günlük raporun panoya kopyalanabilir metin hâli.
     */
    public function getGunlukRaporMetni(string $tarih): string
    {
        $veri = $this->getGunlukRapor($tarih);
        $satirlar = [date('d.m.Y', strtotime($tarih))];

        $basliklar = [
            'Kaçak' => 'Kaçak Tutanak Sayısı:',
            'Abonesiz' => 'Sayaçlı Abonesiz Tutanak Sayısı:',
            'Usülsüz' => 'Usülsüz Tutanak Sayısı:',
        ];

        foreach ($basliklar as $tur => $baslik) {
            // Hiç kaydı olmayan türü rapor metnine hiç yazma
            if (empty($veri[$tur])) {
                continue;
            }
            $satirlar[] = $baslik;
            foreach ($veri[$tur] as $ilce => $adet) {
                $satirlar[] = $ilce . ': ' . $adet;
            }
        }

        return implode("\n", $satirlar);
    }

    /**
     * Haftalık rapor: tarih aralığında bölge (ilçe) bazlı tür kırılımlı özet.
     */
    public function getBolgeBazliOzet(string $baslangic, string $bitis): array
    {
        $stmt = $this->db->prepare("SELECT ilce,
                                           SUM(CASE WHEN tur = 'Abonesiz' THEN sayi ELSE 0 END) AS abonesiz,
                                           SUM(CASE WHEN tur = 'Kaçak' THEN sayi ELSE 0 END) AS kacak,
                                           SUM(CASE WHEN tur = 'Usülsüz' THEN sayi ELSE 0 END) AS usulsuz,
                                           SUM(sayi) AS toplam
                                    FROM kacak_kontrol
                                    WHERE firma_id = ? AND tarih BETWEEN ? AND ?
                                      AND " . self::hakedisKosulu() . "
                                    GROUP BY ilce
                                    ORDER BY toplam DESC");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Haftalık teslim alma listesi.
     * Fiziki teslim kuralı: Onikişubat/Dulkadiroğlu'ndaki tüm tutanaklar (Abonesiz + Kaçak),
     * diğer ilçelerde ise sadece Kaçak evraklar teslim alınır.
     * Foto çıktısı ise yalnızca merkez ilçelerdeki Kaçak kayıtlar için gerekir.
     */
    public function getTeslimAlmaListesi(string $baslangic, string $bitis): array
    {
        $merkezPlaceholders = implode(',', array_fill(0, count(self::MERKEZ_ILCELER), '?'));

        $sql = "SELECT k.id, k.tarih, k.tutanak_no, k.abone_adi, k.ilce, k.tur, k.ekip_adi
                FROM kacak_kontrol k
                WHERE k.firma_id = ? AND k.tarih BETWEEN ? AND ?
                  AND " . self::hakedisKosulu('k') . "
                  AND (k.ilce IN ($merkezPlaceholders) OR k.tur = 'Kaçak')
                ORDER BY k.ilce ASC, k.tarih ASC, k.tutanak_no ASC";

        $params = array_merge([$this->firmaId(), $baslangic, $bitis], self::MERKEZ_ILCELER);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $merkezMi = in_array($row['ilce'], self::MERKEZ_ILCELER, true);
            $row['sebep'] = $merkezMi ? 'Onikişubat/Dulkadiroğlu (tümü)' : 'Kaçak evrak';
            $row['foto_cikti_gerekli'] = ($merkezMi && $row['tur'] === 'Kaçak') ? 1 : 0;
        }
        unset($row);

        return $rows;
    }

    /**
     * Ekran üstü özet kartları.
     */
    public function getOzet(string $baslangic, string $bitis): array
    {
        $stmt = $this->db->prepare("SELECT
                    SUM(CASE WHEN onay_durumu = 'onaylandi' AND durum = 'aktif' THEN sayi ELSE 0 END) AS aktif,
                    SUM(CASE WHEN onay_durumu = 'onaylandi' AND durum = 'aktif' AND tur = 'Usülsüz' THEN sayi ELSE 0 END) AS usulsuz,
                    SUM(CASE WHEN onay_durumu = 'onaylandi' AND durum = 'aktif' AND tur = 'Kaçak' THEN sayi ELSE 0 END) AS kacak,
                    SUM(CASE WHEN onay_durumu = 'onaylandi' AND durum = 'aktif' AND tur = 'Abonesiz' THEN sayi ELSE 0 END) AS abonesiz,
                    SUM(CASE WHEN durum = 'iptal' THEN sayi ELSE 0 END) AS iptal,
                    SUM(CASE WHEN durum = 'iptal' AND hakedisten_dus = 1 THEN sayi ELSE 0 END) AS iptal_dusulen,
                    SUM(CASE WHEN onay_durumu = 'beklemede' THEN 1 ELSE 0 END) AS bekleyen
                FROM kacak_kontrol
                WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn($v) => (int) $v, $row);
    }

    // =====================================================
    // YARDIMCILAR
    // =====================================================

    public function normalizePersonelIds($ids): array
    {
        if (!is_array($ids)) {
            $ids = $ids === null || $ids === '' ? [] : explode(',', (string) $ids);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        return array_slice($ids, 0, 2);
    }

    public function buildEkipAdi(array $personelIds): string
    {
        if (empty($personelIds)) {
            return '';
        }
        $placeholders = implode(',', array_fill(0, count($personelIds), '?'));
        $stmt = $this->db->prepare("SELECT id, adi_soyadi FROM personel WHERE id IN ($placeholders)");
        $stmt->execute($personelIds);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['id']] = $row['adi_soyadi'];
        }
        $isimler = [];
        foreach ($personelIds as $pid) {
            if (isset($map[$pid])) {
                $isimler[] = $map[$pid];
            }
        }
        return implode(', ', $isimler);
    }

    public function normalizeIlce(string $ilce): string
    {
        $ilce = trim($ilce);
        if ($ilce === '') {
            return '';
        }
        foreach (self::ILCELER as $gecerli) {
            if (mb_strtolower($gecerli, 'UTF-8') === mb_strtolower($ilce, 'UTF-8')) {
                return $gecerli;
            }
        }
        return $ilce;
    }

    /**
     * PWA tarafında ekip arkadaşı olarak seçilebilecek personeller.
     */
    public function getEkipAdaylari(int $haricPersonelId = 0): array
    {
        $sql = "SELECT id, adi_soyadi, gorev, departman
                FROM personel
                WHERE firma_id = ?
                  AND departman LIKE ?
                  AND (isten_cikis_tarihi IS NULL OR isten_cikis_tarihi = '0000-00-00' OR isten_cikis_tarihi = '')
                  AND silinme_tarihi IS NULL";
        $params = [$this->firmaId(), '%Kaçak%'];

        if ($haricPersonelId > 0) {
            $sql .= " AND id <> ?";
            $params[] = $haricPersonelId;
        }
        $sql .= " ORDER BY adi_soyadi ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sadece ilgili personelin kendi bildirdiği ya da ekibinde fiilen yer aldığı kayıtlar (PWA listesi).
     * Varsayılan olarak son 30 gün ile sınırlıdır.
     */
    public function getPersonelKayitlari(int $personelId, string $baslangic = '', string $bitis = '', int $limit = 200): array
    {
        [$baslangic, $bitis] = $this->personelTarihAraligi($baslangic, $bitis);

        $stmt = $this->db->prepare("SELECT k.*,
                                           (SELECT COUNT(*) FROM kacak_kontrol_fotograflari f
                                             WHERE f.kacak_id = k.id AND f.silinme_tarihi IS NULL AND f.arsivlendi = 0) AS foto_sayisi
                                    FROM kacak_kontrol k
                                    WHERE k.silinme_tarihi IS NULL
                                      AND k.firma_id = ?
                                      AND k.tarih BETWEEN ? AND ?
                                      AND (k.bildiren_personel_id = ? OR FIND_IN_SET(?, k.personel_ids))
                                    ORDER BY k.tarih DESC, k.id DESC
                                    LIMIT " . (int) $limit);
        $stmt->execute([$this->firmaId(), $baslangic, $bitis, $personelId, $personelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPersonelIstatistik(int $personelId, string $baslangic = '', string $bitis = ''): array
    {
        [$baslangic, $bitis] = $this->personelTarihAraligi($baslangic, $bitis);

        $stmt = $this->db->prepare("SELECT
                    COUNT(*) AS toplam,
                    SUM(CASE WHEN onay_durumu = 'beklemede' THEN 1 ELSE 0 END) AS bekleyen,
                    SUM(CASE WHEN onay_durumu = 'onaylandi' THEN 1 ELSE 0 END) AS onayli,
                    SUM(CASE WHEN onay_durumu = 'reddedildi' THEN 1 ELSE 0 END) AS reddedilen
                FROM kacak_kontrol
                WHERE silinme_tarihi IS NULL
                  AND firma_id = ?
                  AND tarih BETWEEN ? AND ?
                  AND (bildiren_personel_id = ? OR FIND_IN_SET(?, personel_ids))");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis, $personelId, $personelId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn($v) => (int) $v, $row);
    }

    private function personelTarihAraligi(string $baslangic, string $bitis): array
    {
        $bitis = $bitis !== '' && strtotime($bitis) ? date('Y-m-d', strtotime($bitis)) : date('Y-m-d');
        $baslangic = $baslangic !== '' && strtotime($baslangic)
            ? date('Y-m-d', strtotime($baslangic))
            : date('Y-m-d', strtotime($bitis . ' -29 days'));

        return [$baslangic, $bitis];
    }

    /**
     * Yüklenen dosyayı kaçak fotoğraf dizinine taşır, göreli yolu döndürür.
     */
    public function storeUploadedFile(array $file, int $kacakId, string $tur): string
    {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Dosya yüklenemedi.');
        }

        $izinliUzantilar = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $izinliUzantilar, true)) {
            throw new Exception('Sadece JPG, PNG, WEBP veya PDF dosyası yüklenebilir.');
        }

        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new Exception('Dosya boyutu 10 MB üzerinde olamaz.');
        }

        if ($ext !== 'pdf') {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                throw new Exception('Geçersiz görsel dosyası.');
            }
        }

        $altDizin = self::UPLOAD_DIR . '/' . date('Y/m');
        $hedefDizin = self::rootPath() . '/' . $altDizin;
        if (!is_dir($hedefDizin) && !mkdir($hedefDizin, 0775, true) && !is_dir($hedefDizin)) {
            error_log('Kaçak yükleme dizini oluşturulamadı: ' . $hedefDizin . ' (üst dizin yazılabilir mi?)');
            throw new Exception('Yükleme dizini oluşturulamadı.');
        }
        if (!is_writable($hedefDizin)) {
            error_log('Kaçak yükleme dizini yazılabilir değil: ' . $hedefDizin);
            throw new Exception('Yükleme dizinine yazılamıyor.');
        }

        $dosyaAdi = $tur . '_' . $kacakId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $hedef = $hedefDizin . '/' . $dosyaAdi;

        if (!move_uploaded_file($file['tmp_name'], $hedef)) {
            throw new Exception('Dosya kaydedilemedi.');
        }

        return $altDizin . '/' . $dosyaAdi;
    }
}
