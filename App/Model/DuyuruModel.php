<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class DuyuruModel extends Model
{
    protected $table = 'duyurular';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getAll($activeOnly = false, $adminOnly = false)
    {
        $sql = "SELECT * FROM {$this->table} WHERE firma_id = :firma_id AND silinme_tarihi IS NULL";
        $params = [
            'firma_id' => $_SESSION['firma_id'],
        ];

        if ($activeOnly) {
            $sql .= " AND durum = 'Yayında' AND (etkinlik_tarihi IS NULL OR DATE(etkinlik_tarihi) >= :today)";
            $params['today'] = date('Y-m-d');
        }

        if ($adminOnly) {
            $sql .= " AND ana_sayfada_goster = 1";
        }

        $sql .= " ORDER BY tarih DESC";

        $query = $this->db->prepare($sql);
        $query->execute($params);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    public function createDuyuru($data)
    {
        return $this->saveWithAttr($data);
    }

    public function updateDuyuru($id, $data)
    {
        $data['id'] = $id;
        return $this->saveWithAttr($data);
    }

    public function getStats()
    {
        $sql = "SELECT 
                COUNT(*) as toplam,
                SUM(CASE WHEN durum = 'Yayında' AND (DATE(etkinlik_tarihi) >= ? OR etkinlik_tarihi IS NULL) AND silinme_tarihi IS NULL THEN 1 ELSE 0 END) as aktif,
                SUM(CASE WHEN durum = 'Yayında' AND DATE(etkinlik_tarihi) < ? AND etkinlik_tarihi IS NOT NULL AND silinme_tarihi IS NULL THEN 1 ELSE 0 END) as suresi_dolmus
                FROM {$this->table} 
                WHERE firma_id = ? AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        $today = date('Y-m-d');
        $stmt->execute([$today, $today, $_SESSION['firma_id']]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function ajaxList(array $params, int $firma_id): array
    {
        $draw = (int) ($params['draw'] ?? 1);
        $start = max(0, (int) ($params['start'] ?? 0));
        $length = (int) ($params['length'] ?? 10);
        if ($length < 1 || $length > 500) {
            $length = 10;
        }
        $search = trim((string) ($params['search']['value'] ?? ''));
        $today = date('Y-m-d');

        $hedefExpr = "CONCAT_WS(', ', IF(d.ana_sayfada_goster = 1, 'Admin Ana Sayfa', NULL), IF(d.pwa_goster = 1, 'Personel Ana Sayfa', NULL), IF(d.ana_sayfada_goster = 0 AND d.pwa_goster = 0, 'Gösterilmiyor', NULL))";
        $kitleExpr = "IF(d.alici_tipi = 'toplu', 'Tüm Personel', 'Özel Liste')";
        $expiredExpr = "(d.etkinlik_tarihi IS NOT NULL AND DATE(d.etkinlik_tarihi) < :today_expr)";

        $colMap = [
            1 => 'd.baslik',
            2 => 'd.icerik',
            3 => $hedefExpr,
            4 => $kitleExpr,
            5 => 'DATE(d.tarih)',
            6 => 'd.durum',
        ];
        $orderMap = [
            0 => 'd.id',
            1 => 'd.baslik',
            2 => 'd.icerik',
            3 => $hedefExpr,
            4 => $kitleExpr,
            5 => 'd.tarih',
            6 => 'd.durum',
        ];

        $baseWhere = "d.firma_id = :firma_id AND d.silinme_tarihi IS NULL";
        $where = $baseWhere;
        $bind = ['firma_id' => $firma_id];
        $needsToday = false;

        $statusFilter = (string) ($params['status_filter'] ?? 'all');
        if ($statusFilter === 'published') {
            $where .= " AND d.durum = 'Yayında'";
        } elseif ($statusFilter === 'draft') {
            $where .= " AND d.durum = 'Taslak'";
        } elseif ($statusFilter === 'closed') {
            $where .= " AND d.durum = 'Kapalı'";
        } elseif ($statusFilter === 'expired') {
            $where .= " AND {$expiredExpr}";
            $needsToday = true;
        }

        if ($search !== '') {
            $where .= " AND (d.baslik LIKE :search OR d.icerik LIKE :search OR d.durum LIKE :search OR {$hedefExpr} LIKE :search OR {$kitleExpr} LIKE :search)";
            $bind['search'] = '%' . $search . '%';
        }

        foreach (($params['columns'] ?? []) as $i => $column) {
            $val = (string) ($column['search']['value'] ?? '');
            if ($val === '' || !isset($colMap[(int) $i])) {
                continue;
            }
            $i = (int) $i;
            $field = $colMap[$i];
            $p = 'col_' . $i;
            $mode = 'contains';
            $filterVal = $val;
            if (strpos($val, ':') !== false) {
                [$mode, $filterVal] = explode(':', $val, 2);
            }

            switch ($mode) {
                case 'multi':
                    $conds = [];
                    foreach (explode('|', $filterVal) as $vIdx => $v) {
                        if ($v === '') {
                            continue;
                        }
                        $conds[] = "$field LIKE :{$p}_m_{$vIdx}";
                        $bind["{$p}_m_{$vIdx}"] = '%' . $v . '%';
                    }
                    if ($conds) {
                        $where .= ' AND (' . implode(' OR ', $conds) . ')';
                    }
                    break;
                case 'equals':
                    $where .= " AND $field = :$p";
                    $bind[$p] = $filterVal;
                    break;
                case 'not_equals':
                    $where .= " AND $field != :$p";
                    $bind[$p] = $filterVal;
                    break;
                case 'greater_than':
                    $where .= " AND $field > :$p";
                    $bind[$p] = $filterVal;
                    break;
                case 'less_than':
                    $where .= " AND $field < :$p";
                    $bind[$p] = $filterVal;
                    break;
                case 'greater_equal':
                    $where .= " AND $field >= :$p";
                    $bind[$p] = $filterVal;
                    break;
                case 'less_equal':
                    $where .= " AND $field <= :$p";
                    $bind[$p] = $filterVal;
                    break;
                case 'not_contains':
                    $where .= " AND $field NOT LIKE :$p";
                    $bind[$p] = '%' . $filterVal . '%';
                    break;
                case 'starts_with':
                    $where .= " AND $field LIKE :$p";
                    $bind[$p] = $filterVal . '%';
                    break;
                case 'ends_with':
                    $where .= " AND $field LIKE :$p";
                    $bind[$p] = '%' . $filterVal;
                    break;
                case 'null':
                    $where .= " AND ($field IS NULL OR $field = '')";
                    break;
                case 'not_null':
                    $where .= " AND ($field IS NOT NULL AND $field != '')";
                    break;
                default:
                    $where .= " AND $field LIKE :$p";
                    $bind[$p] = '%' . $filterVal . '%';
            }
        }

        $orderParts = [];
        foreach (($params['order'] ?? []) as $order) {
            $idx = (int) ($order['column'] ?? -1);
            $dir = strtolower((string) ($order['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
            if (isset($orderMap[$idx])) {
                $orderParts[] = $orderMap[$idx] . ' ' . $dir;
            }
        }
        $orderSql = $orderParts ? implode(', ', $orderParts) : 'd.tarih DESC';

        $stmtTotal = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} d WHERE {$baseWhere}");
        $stmtTotal->execute(['firma_id' => $firma_id]);
        $total = (int) $stmtTotal->fetchColumn();

        if ($needsToday) {
            $bind['today_expr'] = $today;
        }
        $bindMain = $bind;
        $bindMain['today_sel'] = $today;

        $stmtFiltered = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} d WHERE {$where}");
        $stmtFiltered->execute($bind);
        $filtered = (int) $stmtFiltered->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT d.*, (d.etkinlik_tarihi IS NOT NULL AND DATE(d.etkinlik_tarihi) < :today_sel) AS suresi_doldu
             FROM {$this->table} d
             WHERE {$where}
             ORDER BY {$orderSql}
             LIMIT :lim_start, :lim_length"
        );
        foreach ($bindMain as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('lim_start', $start, PDO::PARAM_INT);
        $stmt->bindValue('lim_length', $length, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $stmt->fetchAll(PDO::FETCH_OBJ),
        ];
    }

    public function summary(int $firma_id): object
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS toplam,
                COALESCE(SUM(durum = 'Yayında'), 0) AS yayinda,
                COALESCE(SUM(durum = 'Taslak'), 0) AS taslak,
                COALESCE(SUM(durum = 'Kapalı'), 0) AS kapali,
                COALESCE(SUM(durum = 'Yayında' AND (etkinlik_tarihi IS NULL OR DATE(etkinlik_tarihi) >= :t1)), 0) AS aktif,
                COALESCE(SUM(etkinlik_tarihi IS NOT NULL AND DATE(etkinlik_tarihi) < :t2), 0) AS suresi_dolmus,
                COALESCE(SUM(ana_sayfada_goster = 1), 0) AS admin_ana_sayfa,
                COALESCE(SUM(pwa_goster = 1), 0) AS pwa_ana_sayfa
             FROM {$this->table}
             WHERE firma_id = :firma_id AND silinme_tarihi IS NULL"
        );
        $today = date('Y-m-d');
        $stmt->execute(['t1' => $today, 't2' => $today, 'firma_id' => $firma_id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        foreach (['toplam', 'yayinda', 'taslak', 'kapali', 'aktif', 'suresi_dolmus', 'admin_ana_sayfa', 'pwa_ana_sayfa'] as $k) {
            $row->$k = (int) ($row->$k ?? 0);
        }
        return $row;
    }

    public function getPwaSlider(int $firmaId, int $personelId): array
    {
        $stmt = $this->db->prepare("SELECT id, baslik, icerik, resim, hedef_sayfa, tarih, etkinlik_tarihi
            FROM {$this->table}
            WHERE silinme_tarihi IS NULL
            AND firma_id = ?
            AND pwa_goster = 1
            AND durum = 'Yayında'
            AND (alici_tipi = 'toplu' OR FIND_IN_SET(?, alici_ids))
            AND (etkinlik_tarihi IS NULL OR DATE(etkinlik_tarihi) >= ?)
            ORDER BY id DESC
            LIMIT 5");
        $stmt->execute([$firmaId, $personelId, date('Y-m-d')]);

        $bugun = new \DateTime('today');
        return array_map(function ($d) use ($bugun) {
            $tarih = date('d.m.Y H:i', strtotime($d['tarih']));
            $kalanGun = null;
            if ($d['etkinlik_tarihi']) {
                $tarih = 'Son: ' . date('d.m.Y', strtotime($d['etkinlik_tarihi']));
                $etkinlik = (new \DateTime($d['etkinlik_tarihi']))->setTime(0, 0, 0);
                if ($etkinlik >= $bugun) {
                    $kalanGun = sprintf('%02d', $bugun->diff($etkinlik)->days);
                }
            }
            return [
                'id' => $d['id'],
                'baslik' => $d['baslik'],
                'icerik' => $d['icerik'],
                'resim' => $d['resim'],
                'tarih' => $tarih,
                'kalan_gun' => $kalanGun,
                'hedef_sayfa' => $d['hedef_sayfa'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
