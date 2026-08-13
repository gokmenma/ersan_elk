<?php
namespace App\Model;

use App\Model\Model;
use App\Service\ImageUploadService;
use App\Service\VideoUploadService;
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

    const MAX_SAHA_FOTO = 15;

    const MAX_VIDEO = 2;

    const VIDEO_MAX_SURE = 20;

    const VIDEO_MAX_BYTE = 15728640;

    const VIDEO_MIMES = ['video/mp4', 'video/quicktime', 'video/webm', 'video/3gpp'];

    const UPLOAD_DIR = 'uploads/kacak_kontrol';

    const TUTANAK_MAX_KENAR = 2200;

    const SAHA_MAX_KENAR = 1600;

    const KUCUK_KENAR = 320;

    // Çekim ile yükleme arasındaki bu süreyi aşan fotoğraflar listede işaretlenir.
    const CEKIM_GECIKME_DK = 30;

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

    /**
     * Rapor ve ekip özetlerine (Günlük Rapor, Haftalık Rapor, Ekip Özeti, Teslim Alma Listesi)
     * dahil edilecek kayıtları belirleyen koşul.
     * Onaylanmış ('onaylandi') ve onay bekleyen ('beklemede') kayıtları kapsar.
     */
    public static function raporKosulu(string $alias = ''): string
    {
        $p = $alias !== '' ? $alias . '.' : '';
        return "{$p}silinme_tarihi IS NULL
                AND {$p}onay_durumu IN ('onaylandi', 'beklemede')
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
    public function getRecords(array $filters = [], int $limit = 0, int $offset = 0, string $orderColumn = 'tarih', string $orderDirection = 'DESC'): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $allowedOrderColumns = [
            'tarih' => 'k.tarih',
            'tutanak_no' => 'k.tutanak_no',
            'abone_adi' => 'k.abone_adi',
            'ilce' => 'k.ilce',
            'tur' => 'k.tur',
            'sayac_no' => 'k.sayac_no',
            'sayi' => 'k.sayi',
            'ekip_adi' => 'k.ekip_adi',
            'kaynak' => 'k.kaynak',
            'durum' => 'k.durum',
        ];
        $orderSql = $allowedOrderColumns[$orderColumn] ?? 'k.tarih';
        $directionSql = strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC';

        $limitSql = '';
        if ($limit > 0) {
            $limitSql = ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }

        $sql = "SELECT k.*,
                       bp.adi_soyadi AS bildiren_adi,
                       u.adi_soyadi  AS onaylayan_adi,
                       ui.adi_soyadi AS iptal_eden_adi,
                       (SELECT COUNT(*) FROM kacak_kontrol_fotograflari f
                         WHERE f.kacak_id = k.id AND f.medya_tipi = 'foto' AND f.silinme_tarihi IS NULL AND f.arsivlendi = 0) AS foto_sayisi,
                       (SELECT COUNT(*) FROM kacak_kontrol_fotograflari fv
                         WHERE fv.kacak_id = k.id AND fv.medya_tipi = 'video' AND fv.silinme_tarihi IS NULL AND fv.arsivlendi = 0) AS video_sayisi,
                       (SELECT COUNT(*) FROM kacak_kontrol_fotograflari fg
                         WHERE fg.kacak_id = k.id AND fg.silinme_tarihi IS NULL AND fg.arsivlendi = 0
                           AND fg.cekim_tarihi IS NOT NULL
                           AND TIMESTAMPDIFF(MINUTE, fg.cekim_tarihi, fg.olusturma_tarihi) > " . self::CEKIM_GECIKME_DK . ") AS gecikmeli_foto_sayisi
                FROM kacak_kontrol k
                LEFT JOIN personel bp ON bp.id = k.bildiren_personel_id
                LEFT JOIN users u     ON u.id = k.onaylayan_id
                LEFT JOIN users ui    ON ui.id = k.iptal_eden
                WHERE {$where}
                ORDER BY {$orderSql} {$directionSql}, k.id DESC{$limitSql}";

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
            $where[] = '(k.bildiren_personel_id = ? OR FIND_IN_SET(?, k.personel_ids))';
            $pid = (int) $filters['personel_id'];
            array_push($params, $pid, $pid);
        }
        if (!empty($filters['arama'])) {
            $where[] = '(k.tutanak_no LIKE ? OR k.abone_adi LIKE ? OR k.sayac_no LIKE ? OR k.ekip_adi LIKE ?)';
            $like = '%' . $filters['arama'] . '%';
            array_push($params, $like, $like, $like, $like);
        }

        foreach (($filters['kolon_aramalari'] ?? []) as $column => $rawVal) {
            $allowedColumns = [
                'tarih' => 'k.tarih', 'tutanak_no' => 'k.tutanak_no', 'abone_adi' => 'k.abone_adi',
                'ilce' => 'k.ilce', 'tur' => 'k.tur', 'sayac_no' => 'k.sayac_no', 'sayi' => 'k.sayi',
                'ekip_adi' => 'k.ekip_adi', 'kaynak' => 'k.kaynak', 'durum' => 'k.durum',
            ];
            if ($rawVal === '' || !isset($allowedColumns[$column])) {
                continue;
            }

            $dbCol = $allowedColumns[$column];
            $mode = 'contains';
            $valStr = (string) $rawVal;

            if (strpos($rawVal, ':') !== false) {
                list($mode, $valStr) = explode(':', $rawVal, 2);
            }

            $vals = array_values(array_filter(array_map('trim', explode('|', $valStr)), function ($v) {
                return $v !== '';
            }));

            if (empty($vals) && !in_array($mode, ['null', 'not_null'], true)) {
                continue;
            }

            $firstVal = reset($vals) ?: '';
            $secondVal = count($vals) > 1 ? $vals[1] : '';

            $isDateCol = ($column === 'tarih');
            if ($isDateCol) {
                if ($firstVal && strpos($firstVal, '.') !== false) {
                    $firstVal = \App\Helper\Date::dttoeng($firstVal);
                }
                if ($secondVal && strpos($secondVal, '.') !== false) {
                    $secondVal = \App\Helper\Date::dttoeng($secondVal);
                }
            }

            switch ($mode) {
                case 'multi':
                    $orClause = [];
                    foreach ($vals as $v) {
                        if ($isDateCol && strpos($v, '.') !== false) {
                            $v = \App\Helper\Date::dttoeng($v);
                            $orClause[] = "DATE($dbCol) = ?";
                            $params[] = $v;
                        } else {
                            $orClause[] = "$dbCol LIKE ?";
                            $params[] = '%' . $v . '%';
                        }
                    }
                    if (!empty($orClause)) {
                        $where[] = '(' . implode(' OR ', $orClause) . ')';
                    }
                    break;

                case 'equals':
                    if ($isDateCol) {
                        $where[] = "DATE($dbCol) = ?";
                        $params[] = $firstVal;
                    } else {
                        $where[] = "$dbCol = ?";
                        $params[] = $firstVal;
                    }
                    break;

                case 'not_equals':
                    $where[] = "$dbCol <> ?";
                    $params[] = $firstVal;
                    break;

                case 'starts_with':
                    $where[] = "$dbCol LIKE ?";
                    $params[] = $firstVal . '%';
                    break;

                case 'ends_with':
                    $where[] = "$dbCol LIKE ?";
                    $params[] = '%' . $firstVal;
                    break;

                case 'not_contains':
                    $where[] = "$dbCol NOT LIKE ?";
                    $params[] = '%' . $firstVal . '%';
                    break;

                case 'before':
                    $where[] = "DATE($dbCol) < ?";
                    $params[] = $firstVal;
                    break;

                case 'after':
                    $where[] = "DATE($dbCol) > ?";
                    $params[] = $firstVal;
                    break;

                case 'between':
                    if ($firstVal && $secondVal) {
                        $where[] = "DATE($dbCol) BETWEEN ? AND ?";
                        $params[] = $firstVal;
                        $params[] = $secondVal;
                    }
                    break;

                case 'null':
                    $where[] = "($dbCol IS NULL OR $dbCol = '')";
                    break;

                case 'not_null':
                    $where[] = "($dbCol IS NOT NULL AND $dbCol <> '')";
                    break;

                case 'contains':
                default:
                    $where[] = "$dbCol LIKE ?";
                    $params[] = '%' . $firstVal . '%';
                    break;
            }
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
     * Tutanak No veya Sayaç No + Tarih bilgisine göre mükerrer kayıt kontrolü yapar.
     * Reddedilen bildirimler personel tarafından düzeltilip yeniden gönderilebildiği
     * için mükerrer kabul edilmez.
     *
     * @param array $data ['tutanak_no' => string, 'sayac_no' => string, 'tarih' => string]
     * @param int|null $excludeId Güncellemede hariç tutulacak kayıt ID
     * @return array|null Mükerrer kayıt bulunduysa ['type' => string, 'record' => array], yoksa null
     */
    public function findDuplicateRecord(array $data, ?int $excludeId = null): ?array
    {
        $tutanakNo = trim((string) ($data['tutanak_no'] ?? ''));
        $sayacNo = trim((string) ($data['sayac_no'] ?? ''));
        $tarih = trim((string) ($data['tarih'] ?? ''));

        // 1. Tutanak No kontrolü (tutanak_no girilmişse kesin eşleşme aranır)
        if ($tutanakNo !== '') {
            $sql = "SELECT k.*, bp.adi_soyadi AS bildiren_adi
                    FROM kacak_kontrol k
                    LEFT JOIN personel bp ON bp.id = k.bildiren_personel_id
                    WHERE k.firma_id = ?
                      AND k.silinme_tarihi IS NULL
                      AND k.durum != 'iptal'
                      AND k.onay_durumu != 'reddedildi'
                      AND LOWER(TRIM(k.tutanak_no)) = LOWER(TRIM(?))";
            $params = [$this->firmaId(), $tutanakNo];

            if ($excludeId !== null && $excludeId > 0) {
                $sql .= " AND k.id != ?";
                $params[] = $excludeId;
            }

            $sql .= " LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'type' => 'tutanak_no',
                    'record' => $row,
                ];
            }
        }

        // 2. Sayaç No + Tarih kontrolü
        if ($sayacNo !== '' && $tarih !== '') {
            $sql = "SELECT k.*, bp.adi_soyadi AS bildiren_adi
                    FROM kacak_kontrol k
                    LEFT JOIN personel bp ON bp.id = k.bildiren_personel_id
                    WHERE k.firma_id = ?
                      AND k.silinme_tarihi IS NULL
                      AND k.durum != 'iptal'
                      AND k.onay_durumu != 'reddedildi'
                      AND LOWER(TRIM(k.sayac_no)) = LOWER(TRIM(?))
                      AND k.tarih = ?";
            $params = [$this->firmaId(), $sayacNo, $tarih];

            if ($excludeId !== null && $excludeId > 0) {
                $sql .= " AND k.id != ?";
                $params[] = $excludeId;
            }

            $sql .= " LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'type' => 'sayac_no',
                    'record' => $row,
                ];
            }
        }

        return null;
    }

    /**
     * PWA çevrimdışı kuyruğundan gelen kayıtların mükerrer düşmemesi için
     * istemcide üretilen UUID ile daha önce kaydedilmiş tutanağı arar.
     */
    public function findByClientUuid(string $clientUuid, bool $includeDeleted = false): ?array
    {
        $clientUuid = trim($clientUuid);
        if ($clientUuid === '') {
            return null;
        }

        $sql = "SELECT * FROM kacak_kontrol WHERE client_uuid = ?";
        if (!$includeDeleted) {
            $sql .= " AND silinme_tarihi IS NULL";
        }
        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientUuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function restoreRecord(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE kacak_kontrol SET silinme_tarihi = NULL, onay_durumu = 'beklemede' WHERE id = ?");
        return $stmt->execute([$id]);
    }

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

        $dup = $this->findDuplicateRecord([
            'tutanak_no' => $data['tutanak_no'] ?? null,
            'sayac_no' => $data['sayac_no'] ?? null,
            'tarih' => $tarih,
        ]);

        if ($dup) {
            $rec = $dup['record'];
            $tarihF = !empty($rec['tarih']) ? date('d.m.Y', strtotime($rec['tarih'])) : '';
            $bildiren = !empty($rec['bildiren_adi']) ? " ({$rec['bildiren_adi']})" : '';

            if ($dup['type'] === 'tutanak_no') {
                $no = htmlspecialchars($rec['tutanak_no'], ENT_QUOTES, 'UTF-8');
                throw new \Exception("'{$no}' numaralı tutanak zaten sistemde mevcuttur!{$bildiren} (Tarih: {$tarihF})");
            } elseif ($dup['type'] === 'sayac_no') {
                $no = htmlspecialchars($rec['sayac_no'], ENT_QUOTES, 'UTF-8');
                throw new \Exception("'{$no}' numaralı sayaç için {$tarihF} tarihinde zaten tutanak kaydedilmiş!{$bildiren}");
            }
        }

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
            (firma_id, personel_ids, bildiren_personel_id, kaynak, client_uuid, offline_olusturma, beklenen_foto_sayisi,
             onay_durumu, onaylayan_id, onay_tarihi,
             durum, tarih, ekip_adi, ilce, tur, tutanak_no, abone_adi, sayac_no, endeks, sayi, aciklama, islem_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $onayDurumu = $data['onay_durumu'] ?? 'onaylandi';
        $onaylayanId = $onayDurumu === 'onaylandi' ? ($data['onaylayan_id'] ?? ($_SESSION['user_id'] ?? null)) : null;
        $onayTarihi = $onayDurumu === 'onaylandi' ? date('Y-m-d H:i:s') : null;

        $clientUuid = trim((string) ($data['client_uuid'] ?? ''));
        $offlineOlusturma = !empty($data['offline_olusturma']) && strtotime((string) $data['offline_olusturma'])
            ? date('Y-m-d H:i:s', strtotime((string) $data['offline_olusturma']))
            : null;

        $stmt->execute([
            $this->firmaId(),
            implode(',', $personelIds),
            !empty($data['bildiren_personel_id']) ? (int) $data['bildiren_personel_id'] : null,
            $data['kaynak'] ?? 'masaustu',
            $clientUuid !== '' ? $clientUuid : null,
            $offlineOlusturma,
            max(0, min(self::MAX_SAHA_FOTO + 1, (int) ($data['beklenen_foto_sayisi'] ?? 0))),
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

    public function updateExpectedPhotoCount(int $id, int $count): bool
    {
        $count = max(0, min(self::MAX_SAHA_FOTO + 1, $count));
        $stmt = $this->db->prepare("UPDATE kacak_kontrol
                                    SET beklenen_foto_sayisi = GREATEST(beklenen_foto_sayisi, ?)
                                    WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        return $stmt->execute([$count, $id, $this->firmaId()]);
    }

    /**
     * Fotoğraf silindiğinde beklenen sayı olduğu gibi kalırsa listede kalıcı
     * "N bekleniyor" rozeti çıkar; beklenen sayı mevcut fotoğraf adedine çekilir.
     */
    public function syncExpectedPhotoCount(int $kacakId): bool
    {
        $stmt = $this->db->prepare("UPDATE kacak_kontrol
                                    SET beklenen_foto_sayisi = (
                                        SELECT COUNT(*) FROM kacak_kontrol_fotograflari
                                        WHERE kacak_id = ? AND medya_tipi = 'foto'
                                          AND silinme_tarihi IS NULL AND arsivlendi = 0
                                    )
                                    WHERE id = ? AND firma_id = ?");
        return $stmt->execute([$kacakId, $kacakId, $this->firmaId()]);
    }

    public function updateRecord(int $id, array $data): bool
    {
        $personelIds = $this->normalizePersonelIds($data['personel_ids'] ?? []);
        $ekipAdi = $this->buildEkipAdi($personelIds);
        $tur = in_array($data['tur'] ?? '', self::TURLER, true) ? $data['tur'] : 'Kaçak';

        $dup = $this->findDuplicateRecord([
            'tutanak_no' => $data['tutanak_no'] ?? null,
            'sayac_no' => $data['sayac_no'] ?? null,
            'tarih' => $data['tarih'] ?? null,
        ], $id);

        if ($dup) {
            $rec = $dup['record'];
            $tarihF = !empty($rec['tarih']) ? date('d.m.Y', strtotime($rec['tarih'])) : '';
            $bildiren = !empty($rec['bildiren_adi']) ? " ({$rec['bildiren_adi']})" : '';

            if ($dup['type'] === 'tutanak_no') {
                $no = htmlspecialchars($rec['tutanak_no'], ENT_QUOTES, 'UTF-8');
                throw new \Exception("'{$no}' numaralı tutanak başka bir kayıtta zaten mevcuttur!{$bildiren} (Tarih: {$tarihF})");
            } elseif ($dup['type'] === 'sayac_no') {
                $no = htmlspecialchars($rec['sayac_no'], ENT_QUOTES, 'UTF-8');
                throw new \Exception("'{$no}' numaralı sayaç için {$tarihF} tarihinde zaten başka bir tutanak kaydedilmiş!{$bildiren}");
            }
        }

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

    /** Personelin kendi oluşturduğu, henüz karara bağlanmamış PWA kaydını günceller. */
    public function updatePendingByReporter(int $id, int $personelId, array $data): bool
    {
        $personelIds = $this->normalizePersonelIds($data['personel_ids'] ?? []);
        if (count($personelIds) !== 2 || !in_array($personelId, $personelIds, true)) {
            throw new Exception('Kaçak ekibi iki kişiden oluşmalıdır.');
        }

        $tur = trim((string) ($data['tur'] ?? 'Kaçak'));
        if (!in_array($tur, self::TURLER, true)) {
            throw new Exception('Geçersiz kaçak türü.');
        }

        $ekipAdi = $this->buildEkipAdi($personelIds);
        $stmt = $this->db->prepare("UPDATE kacak_kontrol SET
                tarih = ?, personel_ids = ?, ekip_adi = ?, ilce = ?, tur = ?,
                tutanak_no = ?, abone_adi = ?, sayac_no = ?, endeks = ?, sayi = ?, aciklama = ?
            WHERE id = ? AND firma_id = ? AND bildiren_personel_id = ?
              AND onay_durumu = 'beklemede' AND durum <> 'iptal' AND silinme_tarihi IS NULL");
        return $stmt->execute([
            $data['tarih'] ?? date('Y-m-d'), implode(',', $personelIds), $ekipAdi,
            $this->normalizeIlce($data['ilce'] ?? ''), $tur,
            $data['tutanak_no'] ?? null, $data['abone_adi'] ?? null,
            $data['sayac_no'] ?? null, $data['endeks'] ?? null,
            max(1, (int) ($data['sayi'] ?? 1)), $data['aciklama'] ?? null,
            $id, $this->firmaId(), $personelId,
        ]);
    }

    /** Personelin kendi onay bekleyen kaydını geri alınabilir biçimde siler. */
    public function softDeletePendingByReporter(int $id, int $personelId): bool
    {
        $stmt = $this->db->prepare("UPDATE kacak_kontrol
            SET silinme_tarihi = NOW()
            WHERE id = ? AND firma_id = ? AND bildiren_personel_id = ?
              AND onay_durumu = 'beklemede' AND durum <> 'iptal' AND silinme_tarihi IS NULL");
        $stmt->execute([$id, $this->firmaId(), $personelId]);
        return $stmt->rowCount() > 0;
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

    public function getCancellationCandidates(string $arama, int $limit = 500): array
    {
        $limit = max(1, min(500, $limit));
        $like = '%' . trim($arama) . '%';
        $stmt = $this->db->prepare("SELECT id, tarih, tutanak_no, abone_adi, ilce, tur
                                    FROM kacak_kontrol
                                    WHERE firma_id = ? AND silinme_tarihi IS NULL
                                      AND durum = 'aktif' AND onay_durumu = 'onaylandi'
                                      AND (tutanak_no LIKE ? OR abone_adi LIKE ? OR sayac_no LIKE ?)
                                    ORDER BY tarih DESC, id DESC
                                    LIMIT {$limit}");
        $stmt->execute([$this->firmaId(), $like, $like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                                    WHERE kacak_id = ? AND tur = ? AND medya_tipi = 'foto'
                                      AND silinme_tarihi IS NULL AND arsivlendi = 0");
        $stmt->execute([$kacakId, $tur]);
        return (int) $stmt->fetchColumn();
    }

    public function countVideos(int $kacakId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM kacak_kontrol_fotograflari
                                    WHERE kacak_id = ? AND medya_tipi = 'video'
                                      AND silinme_tarihi IS NULL AND arsivlendi = 0");
        $stmt->execute([$kacakId]);
        return (int) $stmt->fetchColumn();
    }

    public function addPhoto(int $kacakId, string $tur, string $dosyaYolu, ?string $orijinalAd = null, ?int $personelId = null, ?int $userId = null, ?int $clientSira = null, ?array $cekim = null): int
    {
        $stmt = $this->db->prepare("INSERT INTO kacak_kontrol_fotograflari
            (firma_id, kacak_id, tur, client_sira, dosya_yolu, kucuk_yol, orijinal_ad, cekim_tarihi, cekim_kaynak, yukleyen_personel_id, yukleyen_user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $this->firmaId(),
            $kacakId,
            $tur,
            $clientSira,
            $dosyaYolu,
            self::kucukYolBul($dosyaYolu),
            $orijinalAd,
            $cekim['tarih'] ?? null,
            $cekim['kaynak'] ?? null,
            $personelId,
            $userId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Çekim anı önce sunucudaki EXIF'ten, o yoksa istemcinin küçültme öncesi
     * okuduğu "kaynak|Y-m-d H:i:s" değerinden belirlenir.
     */
    public static function cekimBilgisiCoz(?array $sunucu, $istemci): ?array
    {
        if (!empty($sunucu['tarih'])) {
            return $sunucu;
        }

        if (!is_string($istemci) || $istemci === '') {
            error_log('Kaçak fotoğrafı çekim anı olmadan kaydedildi: sunucu EXIF yok, istemci değeri boş.');
            return null;
        }

        $parcalar = explode('|', $istemci, 2);
        if (count($parcalar) !== 2) {
            error_log('Kaçak fotoğrafı çekim anı biçimsiz geldi: ' . substr($istemci, 0, 60));
            return null;
        }

        $kaynak = $parcalar[0] === 'exif' ? 'exif' : 'dosya';
        $zaman = strtotime(trim($parcalar[1]));
        if ($zaman === false) {
            return null;
        }

        // Cihaz saati bozuksa anlamsız değer yazılmasın.
        if ($zaman > time() + 3600 || $zaman < strtotime('-5 years')) {
            error_log('Kaçak fotoğrafı çekim anı aralık dışı, yok sayıldı: ' . substr($istemci, 0, 60));
            return null;
        }

        return ['tarih' => date('Y-m-d H:i:s', $zaman), 'kaynak' => $kaynak];
    }

    public function addVideo(
        int $kacakId,
        string $dosyaYolu,
        ?string $kapakYolu,
        ?int $sureSaniye,
        ?string $orijinalAd = null,
        ?int $personelId = null,
        ?int $userId = null
    ): int {
        $stmt = $this->db->prepare("INSERT INTO kacak_kontrol_fotograflari
            (firma_id, kacak_id, tur, medya_tipi, dosya_yolu, kucuk_yol, sure_saniye, orijinal_ad, yukleyen_personel_id, yukleyen_user_id)
            VALUES (?, ?, 'saha', 'video', ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $this->firmaId(),
            $kacakId,
            $dosyaYolu,
            $kapakYolu,
            $sureSaniye,
            $orijinalAd,
            $personelId,
            $userId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Parçalı yüklemede aynı sıradaki fotoğrafın tekrar kaydedilmemesi için kullanılır.
     */
    public function findPhotoBySira(int $kacakId, string $tur, int $clientSira): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM kacak_kontrol_fotograflari
                                    WHERE firma_id = ? AND kacak_id = ? AND tur = ? AND client_sira = ?
                                    LIMIT 1");
        $stmt->execute([$this->firmaId(), $kacakId, $tur, $clientSira]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
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
            $this->syncExpectedPhotoCount((int) $foto['kacak_id']);
            foreach ([$foto['dosya_yolu'], $foto['kucuk_yol'] ?? null] as $yol) {
                if (empty($yol)) {
                    continue;
                }
                $abs = self::rootPath() . '/' . ltrim($yol, '/');
                if (is_file($abs)) {
                    @unlink($abs);
                }
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
                                      AND " . self::raporKosulu() . "
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
                                      AND " . self::raporKosulu() . "
                                    GROUP BY ilce
                                    ORDER BY toplam DESC");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Haftalık teslim alma listesi.
     * Fiziki teslim kuralı: Onikişubat/Dulkadiroğlu'ndaki tüm tutanaklar,
     * diğer ilçelerde ise Kaçak ve Usülsüz evraklar teslim alınır.
     * Önceki dönemlerden teslim alınmamış kayıtlar seçilen döneme devreder.
     * Foto çıktısı ise yalnızca merkez ilçelerdeki Kaçak kayıtlar için gerekir.
     */
    public function getTeslimAlmaListesi(string $baslangic, string $bitis): array
    {
        $merkezPlaceholders = implode(',', array_fill(0, count(self::MERKEZ_ILCELER), '?'));

        $sql = "SELECT k.id, k.tarih, k.tutanak_no, k.abone_adi, k.ilce, k.tur, k.ekip_adi,
                       COALESCE(t.teslim_alindi, 0) AS teslim_alindi, t.teslim_tarihi
                FROM kacak_kontrol k
                LEFT JOIN kacak_teslim_takip t
                  ON t.kacak_id = k.id AND t.firma_id = k.firma_id
                 AND t.is_active = 1 AND t.deleted_at IS NULL
                WHERE k.firma_id = ? AND k.tarih BETWEEN ? AND ?
                  AND " . self::raporKosulu('k') . "
                  AND (k.ilce IN ($merkezPlaceholders) OR k.tur IN ('Kaçak', 'Usülsüz'))
                ORDER BY k.ilce ASC, k.tarih ASC, k.tutanak_no ASC";

        $params = array_merge([$this->firmaId(), $baslangic, $bitis], self::MERKEZ_ILCELER);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $merkezMi = in_array($row['ilce'], self::MERKEZ_ILCELER, true);
            $row['sebep'] = $merkezMi ? 'Onikişubat/Dulkadiroğlu (tümü)' : 'Kaçak/Usülsüz evrak';
            $row['foto_cikti_gerekli'] = ($merkezMi && $row['tur'] === 'Kaçak') ? 1 : 0;
            $row['teslim_durumu'] = (int) $row['teslim_alindi'] === 1 ? 'Teslim Alındı' : 'Teslim Alınmadı';
        }
        unset($row);

        return $rows;
    }

    public function teslimAlindiIsaretle(array $ids, int $userId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) return 0;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "INSERT INTO kacak_teslim_takip
                    (firma_id, kacak_id, teslim_alindi, teslim_tarihi, teslim_alan_user_id, is_active)
                SELECT k.firma_id, k.id, 1, NOW(), ?, 1
                FROM kacak_kontrol k
                WHERE k.firma_id = ? AND k.id IN ($placeholders)
                  AND " . self::raporKosulu('k') . "
                  AND (k.ilce IN ('Onikişubat', 'Dulkadiroğlu') OR k.tur IN ('Kaçak', 'Usülsüz'))
                ON DUPLICATE KEY UPDATE teslim_alindi = 1, teslim_tarihi = NOW(),
                    teslim_alan_user_id = VALUES(teslim_alan_user_id), is_active = 1, deleted_at = NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$userId, $this->firmaId()], $ids));
        return $stmt->rowCount();
    }

    /**
     * Ekran üstü özet kartları.
     */
    public function getOzet(string $baslangic, string $bitis, int $personelId = 0): array
    {
        $wherePersonel = '';
        $params = [$this->firmaId(), $baslangic, $bitis];
        if ($personelId > 0) {
            $wherePersonel = ' AND (bildiren_personel_id = ? OR FIND_IN_SET(?, personel_ids))';
            array_push($params, $personelId, $personelId);
        }

        $stmt = $this->db->prepare("SELECT
                    SUM(CASE WHEN onay_durumu = 'onaylandi' AND durum = 'aktif' THEN sayi ELSE 0 END) AS aktif,
                    SUM(CASE WHEN onay_durumu = 'onaylandi' AND durum = 'aktif' AND tur = 'Usülsüz' THEN sayi ELSE 0 END) AS usulsuz,
                    SUM(CASE WHEN onay_durumu = 'onaylandi' AND durum = 'aktif' AND tur = 'Kaçak' THEN sayi ELSE 0 END) AS kacak,
                    SUM(CASE WHEN onay_durumu = 'onaylandi' AND durum = 'aktif' AND tur = 'Abonesiz' THEN sayi ELSE 0 END) AS abonesiz,
                    SUM(CASE WHEN durum = 'iptal' THEN sayi ELSE 0 END) AS iptal,
                    SUM(CASE WHEN durum = 'iptal' AND hakedisten_dus = 1 THEN sayi ELSE 0 END) AS iptal_dusulen,
                    SUM(CASE WHEN onay_durumu = 'beklemede' THEN 1 ELSE 0 END) AS bekleyen
                FROM kacak_kontrol
                WHERE firma_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL{$wherePersonel}");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn($v) => (int) $v, $row);
    }

    public function getDashboard(string $baslangic, string $bitis, int $personelId = 0): array
    {
        $params = [$this->firmaId(), $baslangic, $bitis];
        $base = "firma_id = ? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL";
        if ($personelId > 0) {
            $base .= ' AND (bildiren_personel_id = ? OR FIND_IN_SET(?, personel_ids))';
            array_push($params, $personelId, $personelId);
        }

        $trend = $this->db->prepare("SELECT tarih,
                    SUM(CASE WHEN onay_durumu = 'onaylandi' AND durum = 'aktif' THEN sayi ELSE 0 END) aktif,
                    SUM(CASE WHEN durum = 'iptal' THEN sayi ELSE 0 END) iptal
                FROM kacak_kontrol WHERE {$base} GROUP BY tarih ORDER BY tarih");
        $trend->execute($params);

        $tur = $this->db->prepare("SELECT tur, SUM(sayi) toplam FROM kacak_kontrol
                WHERE {$base} AND onay_durumu = 'onaylandi' AND durum = 'aktif'
                GROUP BY tur ORDER BY toplam DESC");
        $tur->execute($params);

        $ilce = $this->db->prepare("SELECT ilce, SUM(sayi) toplam FROM kacak_kontrol
                WHERE {$base} AND onay_durumu = 'onaylandi' AND durum = 'aktif'
                GROUP BY ilce ORDER BY toplam DESC LIMIT 8");
        $ilce->execute($params);

        $ekip = $this->db->prepare("SELECT COALESCE(NULLIF(ekip_adi, ''), 'Belirtilmemiş') ekip,
                    SUM(CASE WHEN onay_durumu = 'onaylandi' AND durum = 'aktif' THEN sayi ELSE 0 END) aktif,
                    SUM(CASE WHEN durum = 'iptal' THEN sayi ELSE 0 END) iptal,
                    COUNT(DISTINCT tarih) calisilan_gun
                FROM kacak_kontrol WHERE {$base}
                GROUP BY ekip_adi ORDER BY aktif DESC LIMIT 8");
        $ekip->execute($params);

        $kaynak = $this->db->prepare("SELECT kaynak, SUM(sayi) toplam FROM kacak_kontrol
                WHERE {$base} GROUP BY kaynak ORDER BY toplam DESC");
        $kaynak->execute($params);

        $istatistik = $this->db->prepare("SELECT COUNT(*) kayit_sayisi, COALESCE(SUM(sayi), 0) toplam,
                    SUM(CASE WHEN onay_durumu = 'onaylandi' THEN sayi ELSE 0 END) onaylanan,
                    SUM(CASE WHEN onay_durumu = 'beklemede' THEN sayi ELSE 0 END) bekleyen_sayi,
                    SUM(CASE WHEN onay_durumu = 'reddedildi' THEN sayi ELSE 0 END) reddedilen,
                    SUM(CASE WHEN durum = 'iptal' THEN sayi ELSE 0 END) iptal,
                    COUNT(DISTINCT NULLIF(ekip_adi, '')) ekip_sayisi,
                    COUNT(DISTINCT tarih) aktif_gun
                FROM kacak_kontrol WHERE {$base}");
        $istatistik->execute($params);
        $istatistikSatiri = $istatistik->fetch(PDO::FETCH_ASSOC) ?: [];

        $gunSayisi = max(1, (int) ((strtotime($bitis) - strtotime($baslangic)) / 86400) + 1);
        $oncekiBitis = date('Y-m-d', strtotime($baslangic . ' -1 day'));
        $oncekiBaslangic = date('Y-m-d', strtotime($oncekiBitis . ' -' . ($gunSayisi - 1) . ' days'));
        $oncekiOzet = $this->getOzet($oncekiBaslangic, $oncekiBitis, $personelId);

        $toplam = (int) ($istatistikSatiri['toplam'] ?? 0);
        $onaylanan = (int) ($istatistikSatiri['onaylanan'] ?? 0);
        $iptalToplam = (int) ($istatistikSatiri['iptal'] ?? 0);
        $istatistikSatiri['onay_orani'] = $toplam > 0 ? round($onaylanan * 100 / $toplam, 1) : 0;
        $istatistikSatiri['iptal_orani'] = $toplam > 0 ? round($iptalToplam * 100 / $toplam, 1) : 0;
        $istatistikSatiri['gunluk_ortalama'] = round($toplam / $gunSayisi, 1);

        return [
            'ozet' => $this->getOzet($baslangic, $bitis, $personelId),
            'trend' => $trend->fetchAll(PDO::FETCH_ASSOC),
            'turler' => $tur->fetchAll(PDO::FETCH_ASSOC),
            'ilceler' => $ilce->fetchAll(PDO::FETCH_ASSOC),
            'ekipler' => $ekip->fetchAll(PDO::FETCH_ASSOC),
            'kaynaklar' => $kaynak->fetchAll(PDO::FETCH_ASSOC),
            'istatistik' => $istatistikSatiri,
            'onceki_ozet' => $oncekiOzet,
            'onceki_donem' => ['baslangic' => $oncekiBaslangic, 'bitis' => $oncekiBitis],
        ];
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
        sort($ids, SORT_NUMERIC);
        return array_slice($ids, 0, 2);
    }

    public static function normalizeEkipAdi(?string $ekipAdi): string
    {
        if (empty($ekipAdi)) return '';
        $parcalar = array_map('trim', explode(',', $ekipAdi));
        $parcalar = array_values(array_filter($parcalar));
        if (count($parcalar) <= 1) {
            return $parcalar[0] ?? '';
        }
        usort($parcalar, function ($a, $b) {
            return mb_strtolower($a, 'UTF-8') <=> mb_strtolower($b, 'UTF-8');
        });
        return implode(', ', $parcalar);
    }

    /**
     * DataTables kolon filtreleri için benzersiz (unique) değerleri döndürür.
     */
    public function getUniqueValues(string $column): array
    {
        $allowed = [
            'ilce' => 'ilce',
            'tur' => 'tur',
            'ekip_adi' => 'ekip_adi',
            'kaynak' => 'kaynak',
            'durum' => 'durum',
        ];

        if (!isset($allowed[$column])) {
            return [];
        }

        $colName = $allowed[$column];
        $stmt = $this->db->prepare("SELECT DISTINCT {$colName} FROM kacak_kontrol WHERE firma_id = ? AND silinme_tarihi IS NULL AND {$colName} IS NOT NULL AND {$colName} <> '' ORDER BY {$colName} ASC");
        $stmt->execute([$this->firmaId()]);
        $rawVals = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $unique = [];
        foreach ($rawVals as $v) {
            $vClean = trim((string) $v);
            if ($vClean === '') continue;

            if ($column === 'ekip_adi') {
                $vClean = self::normalizeEkipAdi($vClean);
            }
            if (!in_array($vClean, $unique, true)) {
                $unique[] = $vClean;
            }
        }

        usort($unique, function ($a, $b) {
            return mb_strtolower($a, 'UTF-8') <=> mb_strtolower($b, 'UTF-8');
        });

        return array_values($unique);
    }

    public function buildEkipAdi(array $personelIds): string
    {
        $personelIds = $this->normalizePersonelIds($personelIds);
        if (empty($personelIds)) {
            return '';
        }
        $placeholders = implode(',', array_fill(0, count($personelIds), '?'));
        $stmt = $this->db->prepare("SELECT id, adi_soyadi FROM personel WHERE id IN ($placeholders)");
        $stmt->execute($personelIds);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['id']] = trim($row['adi_soyadi']);
        }
        $isimler = [];
        foreach ($personelIds as $pid) {
            if (isset($map[$pid])) {
                $isimler[] = $map[$pid];
            }
        }
        sort($isimler, SORT_STRING | SORT_FLAG_CASE);
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
                                             WHERE f.kacak_id = k.id AND f.medya_tipi = 'foto' AND f.silinme_tarihi IS NULL AND f.arsivlendi = 0) AS foto_sayisi,
                       (SELECT COUNT(*) FROM kacak_kontrol_fotograflari fv
                         WHERE fv.kacak_id = k.id AND fv.medya_tipi = 'video' AND fv.silinme_tarihi IS NULL AND fv.arsivlendi = 0) AS video_sayisi
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
    public function storeUploadedFile(array $file, int $kacakId, string $tur, ?array &$cekim = null): string
    {
        $cekim = null;

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

        if ($ext !== 'pdf') {
            $sonuc = (new ImageUploadService())->store(
                $file,
                $hedefDizin,
                $tur . '_' . $kacakId,
                $tur === 'tutanak' ? self::TUTANAK_MAX_KENAR : self::SAHA_MAX_KENAR,
                $tur === 'tutanak' ? 82 : 75,
                10 * 1024 * 1024,
                self::KUCUK_KENAR
            );

            if (!empty($sonuc['captured_at'])) {
                $cekim = ['tarih' => $sonuc['captured_at'], 'kaynak' => 'exif'];
            }

            return $altDizin . '/' . $sonuc['filename'];
        }

        $dosyaAdi = $tur . '_' . $kacakId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $hedef = $hedefDizin . '/' . $dosyaAdi;

        if (!move_uploaded_file($file['tmp_name'], $hedef)) {
            throw new Exception('Dosya kaydedilemedi.');
        }

        return $altDizin . '/' . $dosyaAdi;
    }

    /**
     * Sunucunun izin verdiği gerçek video yükleme sınırı.
     *
     * Paylaşımlı hostingde upload_max_filesize / post_max_size uygulama sabitinden
     * küçük olabilir; bu durumda dosya PHP tarafından sessizce reddedilir. İstemci
     * bu sınırı bilirse videoyu göndermeden önce anlaşılır bir uyarı verebilir.
     */
    public static function videoYuklemeSiniri(): int
    {
        $sinirlar = [self::VIDEO_MAX_BYTE];

        foreach (['upload_max_filesize', 'post_max_size'] as $ayar) {
            $bayt = self::iniBaytaCevir((string) ini_get($ayar));
            if ($bayt > 0) {
                $sinirlar[] = $bayt;
            }
        }

        return min($sinirlar);
    }

    private static function iniBaytaCevir(string $deger): int
    {
        $deger = trim($deger);
        if ($deger === '') {
            return 0;
        }

        $birim = strtolower(substr($deger, -1));
        $sayi = (int) $deger;

        if ($birim === 'g') {
            return $sayi * 1073741824;
        }
        if ($birim === 'm') {
            return $sayi * 1048576;
        }
        if ($birim === 'k') {
            return $sayi * 1024;
        }

        return $sayi;
    }

    /**
     * Yüklenen videoyu doğrulayıp diske yazar ve kapak karesini kaydeder.
     */
    public function storeUploadedVideo(array $file, int $kacakId, ?int $sureSaniye, ?string $kapakVerisi): array
    {
        if ($this->countVideos($kacakId) >= self::MAX_VIDEO) {
            throw new Exception('Bir kayda en fazla ' . self::MAX_VIDEO . ' video eklenebilir.');
        }

        $altDizin = self::UPLOAD_DIR . '/' . date('Y/m');
        $hedefDizin = self::rootPath() . '/' . $altDizin;

        $sonuc = (new VideoUploadService())->store(
            $file,
            $hedefDizin,
            'video_' . $kacakId,
            self::VIDEO_MIMES,
            self::videoYuklemeSiniri(),
            self::VIDEO_MAX_SURE,
            $sureSaniye,
            $kapakVerisi,
            self::KUCUK_KENAR
        );

        return [
            'yol' => $altDizin . '/' . $sonuc['filename'],
            'kapak' => $sonuc['kapak_filename'] ? $altDizin . '/' . $sonuc['kapak_filename'] : null,
            'sure_saniye' => $sonuc['sure_saniye'],
        ];
    }

    /**
     * Optimize edilmiş dosyanın yanına üretilen küçük boyutun göreli yolunu döndürür.
     * PDF'lerde ve küçük boyut üretilemeyen durumlarda null döner.
     */
    public static function kucukYolBul(string $dosyaYolu): ?string
    {
        $ext = strtolower(pathinfo($dosyaYolu, PATHINFO_EXTENSION));
        if ($ext === '' || $ext === 'pdf') {
            return null;
        }

        $aday = substr($dosyaYolu, 0, -strlen($ext) - 1) . '_k.' . $ext;

        return is_file(self::rootPath() . '/' . ltrim($aday, '/')) ? $aday : null;
    }
}
