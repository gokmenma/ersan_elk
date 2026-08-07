<?php
namespace App\Model;

use App\Model\Model;
use PDO;
use Exception;

class KacakSicilEksikModel extends Model
{
    protected $table = 'kacak_sicil_eksik';

    const NEDENLER = [
        'tc_hatali' => 'TC Kimlik No Hatalı',
        'dogum_tarihi_hatali' => 'Doğum Tarihi Hatalı',
        'ad_soyad_hatali' => 'Ad Soyad Hatalı',
        'adres_hatali' => 'Adres Hatalı',
        'sayac_no_hatali' => 'Sayaç No Hatalı',
        'abone_bulunamadi' => 'Abone Bulunamadı',
        'tutanak_okunmuyor' => 'Tutanak Okunmuyor',
        'diger' => 'Diğer',
    ];

    const DURUMLAR = [
        'beklemede' => 'Ekip Yanıtı Bekleniyor',
        'yanitlandi' => 'Yanıtlandı, Kurum Kontrolünde',
        'cozuldu' => 'Çözüldü',
        'iptal' => 'İptal',
    ];

    const UYARI_GUN = 3;

    const KRITIK_GUN = 7;

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    // =====================================================
    // LİSTELEME
    // =====================================================

    public function getRecords(array $filters = [], int $limit = 0, int $offset = 0, string $orderColumn = 'bildirim_tarihi', string $orderDirection = 'DESC'): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $allowedOrderColumns = [
            'bildirim_tarihi' => 's.bildirim_tarihi',
            'tutanak_no' => 's.tutanak_no',
            'tutanak_tarihi' => 's.tutanak_tarihi',
            'neden' => 's.neden',
            'durum' => 's.durum',
            'tur_sira' => 's.tur_sira',
            'abone_adi' => 'k.abone_adi',
            'ekip_adi' => 'k.ekip_adi',
            'ilce' => 'k.ilce',
        ];
        $orderSql = $allowedOrderColumns[$orderColumn] ?? 's.bildirim_tarihi';
        $directionSql = strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC';

        $limitSql = '';
        if ($limit > 0) {
            $limitSql = ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }

        $sql = "SELECT s.*,
                       k.abone_adi, k.abone_tc, k.abone_dogum_tarihi, k.abone_adres,
                       k.ilce, k.tur, k.sayac_no, k.ekip_adi, k.durum AS tutanak_durumu,
                       ub.adi_soyadi AS bildiren_adi,
                       uk.adi_soyadi AS kapatan_adi,
                       py.adi_soyadi AS yanit_veren_adi,
                       uy.adi_soyadi AS yanit_veren_user_adi,
                       TIMESTAMPDIFF(DAY, s.bildirim_tarihi, NOW()) AS bekleme_gun
                FROM kacak_sicil_eksik s
                LEFT JOIN kacak_kontrol k ON k.id = s.kacak_id
                LEFT JOIN users ub    ON ub.id = s.bildiren_user_id
                LEFT JOIN users uk    ON uk.id = s.kapatan_user_id
                LEFT JOIN users uy    ON uy.id = s.yanit_veren_user_id
                LEFT JOIN personel py ON py.id = s.yanit_veren_personel_id
                WHERE {$where}
                ORDER BY {$orderSql} {$directionSql}, s.id DESC{$limitSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['neden_metin'] = self::NEDENLER[$row['neden']] ?? $row['neden'];
            $row['durum_metin'] = self::DURUMLAR[$row['durum']] ?? $row['durum'];
            $row['duzeltilen_veri_dizi'] = $this->jsonCoz($row['duzeltilen_veri']);
            $row['onceki_veri_dizi'] = $this->jsonCoz($row['onceki_veri']);
        }
        unset($row);

        return $rows;
    }

    public function countRecords(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare("SELECT COUNT(*)
                                    FROM kacak_sicil_eksik s
                                    LEFT JOIN kacak_kontrol k ON k.id = s.kacak_id
                                    WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function buildWhere(array $filters): array
    {
        $where = ['s.firma_id = ?', 's.silinme_tarihi IS NULL'];
        $params = [$this->firmaId()];

        if (!empty($filters['durum'])) {
            $durumlar = is_array($filters['durum']) ? $filters['durum'] : [$filters['durum']];
            $durumlar = array_values(array_intersect($durumlar, array_keys(self::DURUMLAR)));
            if ($durumlar) {
                $where[] = 's.durum IN (' . implode(',', array_fill(0, count($durumlar), '?')) . ')';
                $params = array_merge($params, $durumlar);
            }
        }
        if (!empty($filters['neden'])) {
            $where[] = 's.neden = ?';
            $params[] = $filters['neden'];
        }
        if (!empty($filters['tarih_baslangic'])) {
            $where[] = 's.bildirim_tarihi >= ?';
            $params[] = $filters['tarih_baslangic'] . ' 00:00:00';
        }
        if (!empty($filters['tarih_bitis'])) {
            $where[] = 's.bildirim_tarihi <= ?';
            $params[] = $filters['tarih_bitis'] . ' 23:59:59';
        }
        if (!empty($filters['kacak_id'])) {
            $where[] = 's.kacak_id = ?';
            $params[] = (int) $filters['kacak_id'];
        }
        if (!empty($filters['personel_id'])) {
            $where[] = 'FIND_IN_SET(?, s.atanan_personel_ids)';
            $params[] = (int) $filters['personel_id'];
        }
        if (!empty($filters['eslesmeyen'])) {
            $where[] = 's.kacak_id IS NULL';
        }
        if (!empty($filters['arama'])) {
            $where[] = '(s.tutanak_no LIKE ? OR s.aciklama LIKE ? OR k.abone_adi LIKE ? OR k.ekip_adi LIKE ?)';
            $like = '%' . $filters['arama'] . '%';
            array_push($params, $like, $like, $like, $like);
        }

        foreach (($filters['kolon_aramalari'] ?? []) as $column => $value) {
            $allowedColumns = [
                'tutanak_no' => 's.tutanak_no', 'tutanak_tarihi' => 's.tutanak_tarihi',
                'neden' => 's.neden', 'durum' => 's.durum', 'tur_sira' => 's.tur_sira',
                'abone_adi' => 'k.abone_adi', 'ekip_adi' => 'k.ekip_adi', 'ilce' => 'k.ilce',
            ];
            if ($value !== '' && isset($allowedColumns[$column])) {
                $where[] = $allowedColumns[$column] . ' LIKE ?';
                $params[] = '%' . $value . '%';
            }
        }

        return [implode(' AND ', $where), $params];
    }

    public function getRecord(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT s.*,
                                           k.abone_adi, k.abone_tc, k.abone_dogum_tarihi, k.abone_adres,
                                           k.ilce, k.tur, k.sayac_no, k.ekip_adi, k.tarih AS kacak_tarihi,
                                           k.personel_ids, k.bildiren_personel_id,
                                           ub.adi_soyadi AS bildiren_adi,
                                           py.adi_soyadi AS yanit_veren_adi
                                    FROM kacak_sicil_eksik s
                                    LEFT JOIN kacak_kontrol k ON k.id = s.kacak_id
                                    LEFT JOIN users ub    ON ub.id = s.bildiren_user_id
                                    LEFT JOIN personel py ON py.id = s.yanit_veren_personel_id
                                    WHERE s.id = ? AND s.firma_id = ? AND s.silinme_tarihi IS NULL");
        $stmt->execute([$id, $this->firmaId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['neden_metin'] = self::NEDENLER[$row['neden']] ?? $row['neden'];
        $row['durum_metin'] = self::DURUMLAR[$row['durum']] ?? $row['durum'];
        $row['duzeltilen_veri_dizi'] = $this->jsonCoz($row['duzeltilen_veri']);
        $row['onceki_veri_dizi'] = $this->jsonCoz($row['onceki_veri']);
        $row['gecmis'] = $this->getGecmis($row['tutanak_no'], (int) $id);

        return $row;
    }

    /**
     * Aynı tutanak numarası için önceki turların özeti.
     */
    public function getGecmis(string $tutanakNo, int $haricId = 0): array
    {
        $stmt = $this->db->prepare("SELECT s.id, s.tur_sira, s.neden, s.aciklama, s.durum,
                                           s.bildirim_tarihi, s.yanit_tarihi, s.yanit_aciklama,
                                           s.duzeltilen_veri, s.kapatma_tarihi, s.kapatma_aciklama,
                                           ub.adi_soyadi AS bildiren_adi,
                                           py.adi_soyadi AS yanit_veren_adi
                                    FROM kacak_sicil_eksik s
                                    LEFT JOIN users ub    ON ub.id = s.bildiren_user_id
                                    LEFT JOIN personel py ON py.id = s.yanit_veren_personel_id
                                    WHERE s.firma_id = ? AND s.tutanak_no = ? AND s.id <> ?
                                      AND s.silinme_tarihi IS NULL
                                    ORDER BY s.tur_sira ASC, s.id ASC");
        $stmt->execute([$this->firmaId(), $tutanakNo, $haricId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['neden_metin'] = self::NEDENLER[$row['neden']] ?? $row['neden'];
            $row['durum_metin'] = self::DURUMLAR[$row['durum']] ?? $row['durum'];
            $row['duzeltilen_veri_dizi'] = $this->jsonCoz($row['duzeltilen_veri']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Sekme rozetleri ve alt sekme sayaçları.
     */
    public function getCounts(int $personelId = 0): array
    {
        $where = 'firma_id = ? AND silinme_tarihi IS NULL';
        $params = [$this->firmaId()];

        if ($personelId > 0) {
            $where .= ' AND FIND_IN_SET(?, atanan_personel_ids)';
            $params[] = $personelId;
        }

        $stmt = $this->db->prepare("SELECT
                SUM(CASE WHEN durum = 'beklemede' THEN 1 ELSE 0 END) AS beklemede,
                SUM(CASE WHEN durum = 'yanitlandi' THEN 1 ELSE 0 END) AS yanitlandi,
                SUM(CASE WHEN durum = 'cozuldu' THEN 1 ELSE 0 END) AS cozuldu,
                SUM(CASE WHEN durum = 'iptal' THEN 1 ELSE 0 END) AS iptal,
                SUM(CASE WHEN durum = 'beklemede'
                          AND TIMESTAMPDIFF(DAY, bildirim_tarihi, NOW()) >= ? THEN 1 ELSE 0 END) AS geciken
            FROM kacak_sicil_eksik WHERE {$where}");

        array_unshift($params, self::KRITIK_GUN);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'beklemede' => (int) ($row['beklemede'] ?? 0),
            'yanitlandi' => (int) ($row['yanitlandi'] ?? 0),
            'cozuldu' => (int) ($row['cozuldu'] ?? 0),
            'iptal' => (int) ($row['iptal'] ?? 0),
            'geciken' => (int) ($row['geciken'] ?? 0),
        ];
    }

    /**
     * Kurum kullanıcısının tutanak no ile arama yapabilmesi için (Select2 AJAX).
     */
    public function tutanakAra(string $terim, int $limit = 20): array
    {
        $terim = trim($terim);
        if ($terim === '') {
            return [];
        }

        $stmt = $this->db->prepare("SELECT k.id, k.tutanak_no, k.tarih, k.abone_adi, k.ilce, k.tur,
                                           k.ekip_adi, k.sayac_no, k.abone_tc, k.abone_dogum_tarihi,
                                           k.sicil_durumu, k.durum AS tutanak_durumu
                                    FROM kacak_kontrol k
                                    WHERE k.firma_id = ? AND k.silinme_tarihi IS NULL
                                      AND k.tutanak_no LIKE ?
                                    ORDER BY k.tarih DESC, k.id DESC
                                    LIMIT " . (int) $limit);
        $stmt->execute([$this->firmaId(), '%' . $terim . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // KAYIT
    // =====================================================

    /**
     * Kurum kullanıcısı sicil oluşmadığını bildirir.
     *
     * @throws Exception
     */
    public function create(array $data, int $userId): int
    {
        $tutanakNo = trim((string) ($data['tutanak_no'] ?? ''));
        if ($tutanakNo === '') {
            throw new Exception('Tutanak numarası zorunludur.');
        }

        $neden = (string) ($data['neden'] ?? '');
        if (!isset(self::NEDENLER[$neden])) {
            throw new Exception('Geçersiz neden seçimi.');
        }

        $aciklama = trim((string) ($data['aciklama'] ?? ''));
        if ($neden === 'diger' && $aciklama === '') {
            throw new Exception('"Diğer" seçildiğinde açıklama zorunludur.');
        }

        $kacak = $this->tutanakBul($tutanakNo, $data['kacak_id'] ?? null);

        if ($this->acikKayitVarMi($tutanakNo)) {
            throw new Exception('Bu tutanak için halihazırda açık bir sicil eksik kaydı var. Mevcut kaydı kullanın.');
        }

        $turSira = $this->sonrakiTurSirasi($tutanakNo);

        $atananIds = null;
        if ($kacak) {
            $ids = [];
            if (!empty($kacak['personel_ids'])) {
                $ids = array_filter(array_map('intval', explode(',', $kacak['personel_ids'])));
            }
            if (!empty($kacak['bildiren_personel_id'])) {
                $ids[] = (int) $kacak['bildiren_personel_id'];
            }
            $ids = array_values(array_unique(array_filter($ids)));
            $atananIds = $ids ? implode(',', $ids) : null;
        }

        $stmt = $this->db->prepare("INSERT INTO kacak_sicil_eksik
            (firma_id, kacak_id, tutanak_no, tutanak_tarihi, tur_sira,
             neden, aciklama, durum, bildiren_user_id, bildirim_tarihi, atanan_personel_ids)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'beklemede', ?, NOW(), ?)");

        $stmt->execute([
            $this->firmaId(),
            $kacak ? (int) $kacak['id'] : null,
            $tutanakNo,
            $kacak['tarih'] ?? null,
            $turSira,
            $neden,
            $aciklama !== '' ? $aciklama : null,
            $userId,
            $atananIds,
        ]);

        $id = (int) $this->db->lastInsertId();

        if ($kacak) {
            $this->sicilDurumuTazele((int) $kacak['id']);
        }

        return $id;
    }

    /**
     * Ekip / ofis düzeltilmiş bilgiyi girer.
     * Sicil kaydı ve kacak_kontrol güncellemesi tek transaction içinde yapılır.
     *
     * @throws Exception
     */
    public function yanitla(int $id, array $veri, int $personelId = 0, int $userId = 0): array
    {
        $kayit = $this->getRecord($id);
        if (!$kayit) {
            throw new Exception('Kayıt bulunamadı.');
        }
        if ($kayit['durum'] !== 'beklemede') {
            throw new Exception('Bu kayıt zaten yanıtlanmış veya kapatılmış.');
        }

        $duzeltilen = $this->duzeltilenVeriHazirla($veri);
        $yanitAciklama = trim((string) ($veri['yanit_aciklama'] ?? ''));

        if (!$duzeltilen && $yanitAciklama === '') {
            throw new Exception('En az bir düzeltilmiş bilgi veya açıklama girmelisiniz.');
        }

        $onceki = [];
        if (!empty($kayit['kacak_id'])) {
            $onceki = [
                'abone_adi' => $kayit['abone_adi'] ?? null,
                'abone_tc' => $kayit['abone_tc'] ?? null,
                'abone_dogum_tarihi' => $kayit['abone_dogum_tarihi'] ?? null,
                'abone_adres' => $kayit['abone_adres'] ?? null,
                'sayac_no' => $kayit['sayac_no'] ?? null,
            ];
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE kacak_sicil_eksik
                SET durum = 'yanitlandi',
                    yanit_veren_personel_id = ?,
                    yanit_veren_user_id = ?,
                    yanit_tarihi = NOW(),
                    yanit_aciklama = ?,
                    duzeltilen_veri = ?,
                    onceki_veri = ?
                WHERE id = ? AND firma_id = ? AND durum = 'beklemede' AND silinme_tarihi IS NULL");

            $stmt->execute([
                $personelId > 0 ? $personelId : null,
                $userId > 0 ? $userId : null,
                $yanitAciklama !== '' ? $yanitAciklama : null,
                $duzeltilen ? json_encode($duzeltilen, JSON_UNESCAPED_UNICODE) : null,
                $onceki ? json_encode($onceki, JSON_UNESCAPED_UNICODE) : null,
                $id,
                $this->firmaId(),
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Kayıt bu sırada başka bir kullanıcı tarafından güncellenmiş. Sayfayı yenileyin.');
            }

            if (!empty($kayit['kacak_id']) && $duzeltilen) {
                $this->kacakKontrolGuncelle((int) $kayit['kacak_id'], $duzeltilen);
            }

            if (!empty($kayit['kacak_id'])) {
                $this->sicilDurumuTazele((int) $kayit['kacak_id']);
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->getRecord($id) ?? [];
    }

    /**
     * Kurum kullanıcısı kaydı çözüldü veya iptal olarak kapatır.
     *
     * @throws Exception
     */
    public function kapat(int $id, string $sonuc, string $aciklama, int $userId): array
    {
        if (!in_array($sonuc, ['cozuldu', 'iptal'], true)) {
            throw new Exception('Geçersiz kapatma işlemi.');
        }

        $kayit = $this->getRecord($id);
        if (!$kayit) {
            throw new Exception('Kayıt bulunamadı.');
        }
        if (in_array($kayit['durum'], ['cozuldu', 'iptal'], true)) {
            throw new Exception('Bu kayıt zaten kapatılmış.');
        }

        $aciklama = trim($aciklama);
        if ($sonuc === 'iptal' && $aciklama === '') {
            throw new Exception('İptal işlemi için açıklama zorunludur.');
        }

        $stmt = $this->db->prepare("UPDATE kacak_sicil_eksik
            SET durum = ?, kapatan_user_id = ?, kapatma_tarihi = NOW(), kapatma_aciklama = ?
            WHERE id = ? AND firma_id = ? AND durum IN ('beklemede','yanitlandi') AND silinme_tarihi IS NULL");

        $stmt->execute([
            $sonuc,
            $userId,
            $aciklama !== '' ? $aciklama : null,
            $id,
            $this->firmaId(),
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Kayıt bu sırada başka bir kullanıcı tarafından güncellenmiş. Sayfayı yenileyin.');
        }

        if (!empty($kayit['kacak_id'])) {
            $this->sicilDurumuTazele((int) $kayit['kacak_id']);
        }

        return $this->getRecord($id) ?? [];
    }

    /**
     * Sistemde bulunamamış bir bildirimi sonradan tutanakla eşleştirir.
     *
     * @throws Exception
     */
    public function eslestir(int $id, int $kacakId): array
    {
        $kayit = $this->getRecord($id);
        if (!$kayit) {
            throw new Exception('Kayıt bulunamadı.');
        }
        if (!empty($kayit['kacak_id'])) {
            throw new Exception('Bu kayıt zaten bir tutanakla eşleştirilmiş.');
        }

        $stmt = $this->db->prepare("SELECT id, tarih, personel_ids, bildiren_personel_id
                                    FROM kacak_kontrol
                                    WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$kacakId, $this->firmaId()]);
        $kacak = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$kacak) {
            throw new Exception('Eşleştirilecek tutanak bulunamadı.');
        }

        $ids = [];
        if (!empty($kacak['personel_ids'])) {
            $ids = array_filter(array_map('intval', explode(',', $kacak['personel_ids'])));
        }
        if (!empty($kacak['bildiren_personel_id'])) {
            $ids[] = (int) $kacak['bildiren_personel_id'];
        }
        $ids = array_values(array_unique(array_filter($ids)));

        $stmt = $this->db->prepare("UPDATE kacak_sicil_eksik
            SET kacak_id = ?, tutanak_tarihi = ?, atanan_personel_ids = ?
            WHERE id = ? AND firma_id = ? AND kacak_id IS NULL AND silinme_tarihi IS NULL");
        $stmt->execute([
            $kacakId,
            $kacak['tarih'],
            $ids ? implode(',', $ids) : null,
            $id,
            $this->firmaId(),
        ]);

        $this->sicilDurumuTazele($kacakId);

        return $this->getRecord($id) ?? [];
    }

    public function sil(int $id): bool
    {
        $kayit = $this->getRecord($id);
        if (!$kayit) {
            return false;
        }

        $stmt = $this->db->prepare("UPDATE kacak_sicil_eksik SET silinme_tarihi = NOW()
                                    WHERE id = ? AND firma_id = ?");
        $sonuc = $stmt->execute([$id, $this->firmaId()]);

        if ($sonuc && !empty($kayit['kacak_id'])) {
            $this->sicilDurumuTazele((int) $kayit['kacak_id']);
        }

        return $sonuc;
    }

    // =====================================================
    // PWA
    // =====================================================

    /**
     * Personelin ekibine atanmış düzeltme talepleri.
     */
    public function getPersonelTalepleri(int $personelId, array $filters = []): array
    {
        if ($personelId <= 0) {
            return [];
        }

        $filters['personel_id'] = $personelId;
        if (empty($filters['durum'])) {
            $filters['durum'] = ['beklemede', 'yanitlandi'];
        }

        return $this->getRecords($filters, (int) ($filters['limit'] ?? 100));
    }

    /**
     * Personelin bu kayda yanıt verme yetkisi var mı?
     */
    public function personelYetkiliMi(int $id, int $personelId): bool
    {
        if ($personelId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM kacak_sicil_eksik
                                    WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL
                                      AND FIND_IN_SET(?, atanan_personel_ids)");
        $stmt->execute([$id, $this->firmaId(), $personelId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    // =====================================================
    // YARDIMCILAR
    // =====================================================

    /**
     * kacak_kontrol.sicil_durumu alanını açık kayıtlara göre yeniden hesaplar.
     * Bu alan yalnızca rozet/filtre için tutulur, tek doğru kaynak sicil tablosudur.
     */
    private function sicilDurumuTazele(int $kacakId): void
    {
        $stmt = $this->db->prepare("SELECT durum FROM kacak_sicil_eksik
                                    WHERE kacak_id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$kacakId, $this->firmaId()]);
        $durumlar = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('beklemede', $durumlar, true)) {
            $sicilDurumu = 'eksik';
        } elseif (in_array('yanitlandi', $durumlar, true)) {
            $sicilDurumu = 'yanitlandi';
        } elseif (in_array('cozuldu', $durumlar, true)) {
            $sicilDurumu = 'cozuldu';
        } else {
            $sicilDurumu = 'normal';
        }

        $stmt = $this->db->prepare("UPDATE kacak_kontrol SET sicil_durumu = ?
                                    WHERE id = ? AND firma_id = ?");
        $stmt->execute([$sicilDurumu, $kacakId, $this->firmaId()]);
    }

    private function kacakKontrolGuncelle(int $kacakId, array $duzeltilen): void
    {
        $alanlar = [];
        $params = [];

        $izinliAlanlar = ['abone_adi', 'abone_tc', 'abone_dogum_tarihi', 'abone_adres', 'sayac_no'];
        foreach ($izinliAlanlar as $alan) {
            if (array_key_exists($alan, $duzeltilen)) {
                $alanlar[] = "{$alan} = ?";
                $params[] = $duzeltilen[$alan];
            }
        }

        if (!$alanlar) {
            return;
        }

        $params[] = $kacakId;
        $params[] = $this->firmaId();

        $sql = "UPDATE kacak_kontrol SET " . implode(', ', $alanlar) . "
                WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * @throws Exception
     */
    private function duzeltilenVeriHazirla(array $veri): array
    {
        $sonuc = [];

        $tc = preg_replace('/\D/', '', (string) ($veri['abone_tc'] ?? ''));
        if ($tc !== '') {
            if (strlen($tc) !== 11) {
                throw new Exception('TC Kimlik No 11 haneli olmalıdır.');
            }
            $sonuc['abone_tc'] = $tc;
        }

        $dogum = trim((string) ($veri['abone_dogum_tarihi'] ?? ''));
        if ($dogum !== '') {
            $normal = $this->tariheCevir($dogum);
            if ($normal === null) {
                throw new Exception('Doğum tarihi geçersiz.');
            }
            $sonuc['abone_dogum_tarihi'] = $normal;
        }

        foreach (['abone_adi' => 255, 'abone_adres' => 500, 'sayac_no' => 100] as $alan => $uzunluk) {
            $deger = trim((string) ($veri[$alan] ?? ''));
            if ($deger !== '') {
                $sonuc[$alan] = mb_substr($deger, 0, $uzunluk);
            }
        }

        return $sonuc;
    }

    private function tariheCevir(string $deger): ?string
    {
        $deger = trim($deger);
        if ($deger === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $deger, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $deger : null;
        }
        if (preg_match('/^(\d{2})[.\/](\d{2})[.\/](\d{4})$/', $deger, $m)) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3])
                ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1])
                : null;
        }

        return null;
    }

    private function tutanakBul(string $tutanakNo, $kacakId = null): ?array
    {
        if (!empty($kacakId)) {
            $stmt = $this->db->prepare("SELECT id, tarih, personel_ids, bildiren_personel_id
                                        FROM kacak_kontrol
                                        WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
            $stmt->execute([(int) $kacakId, $this->firmaId()]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        $stmt = $this->db->prepare("SELECT id, tarih, personel_ids, bildiren_personel_id
                                    FROM kacak_kontrol
                                    WHERE firma_id = ? AND tutanak_no = ? AND silinme_tarihi IS NULL
                                    ORDER BY tarih DESC, id DESC LIMIT 1");
        $stmt->execute([$this->firmaId(), $tutanakNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function acikKayitVarMi(string $tutanakNo): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM kacak_sicil_eksik
                                    WHERE firma_id = ? AND tutanak_no = ?
                                      AND durum IN ('beklemede','yanitlandi')
                                      AND silinme_tarihi IS NULL");
        $stmt->execute([$this->firmaId(), $tutanakNo]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function sonrakiTurSirasi(string $tutanakNo): int
    {
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(tur_sira), 0) FROM kacak_sicil_eksik
                                    WHERE firma_id = ? AND tutanak_no = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$this->firmaId(), $tutanakNo]);
        return min(127, (int) $stmt->fetchColumn() + 1);
    }

    private function jsonCoz($deger): array
    {
        if (empty($deger)) {
            return [];
        }
        $cozulen = json_decode((string) $deger, true);
        return is_array($cozulen) ? $cozulen : [];
    }
}
