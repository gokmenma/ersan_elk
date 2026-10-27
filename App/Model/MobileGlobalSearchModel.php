<?php

namespace App\Model;

use App\Helper\Security;
use PDO;

class MobileGlobalSearchModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function search(string $term, int $firmaId, int $userId, array $modules, int $limit = 6): array
    {
        $term = trim($term);
        $results = [];
        if ($firmaId <= 0 || $term === '') {
            return $results;
        }

        $like = $this->likeTerms($term);
        $limit = max(1, min(10, $limit));

        foreach ($modules as $module => $options) {
            $results[$module] = match ($module) {
                'personnel' => $this->personnel($like, $term, $firmaId, $limit),
                'invoices' => $this->invoices($like, $firmaId, $limit, (array) ($options['types'] ?? [])),
                'vehicles' => $this->vehicles($like, $firmaId, $limit),
                'current_accounts' => $this->currentAccounts($like, $firmaId, $limit),
                'documents' => $this->documents($like, $firmaId, $limit, (array) ($options['types'] ?? [])),
                'illegal' => $this->illegal($like, $firmaId, $limit),
                default => [],
            };
        }

        return $results;
    }

    private function likeTerms(string $term): array
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
        return [
            ':term' => '%' . $escaped . '%',
            ':term_exact' => $escaped,
            ':term_starts' => $escaped . '%',
        ];
    }

    private function fetch(string $sql, array $params, string $context): array
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('MobileGlobalSearchModel::' . $context . ' Error: ' . $e->getMessage());
            return [];
        }
    }

    private function item(int $id, string $title, string $subtitle, string $extra, string $badge, string $tone, string $route, ?string $avatar = null): array
    {
        return [
            'id' => Security::encrypt((string) $id),
            'title' => $title,
            'subtitle' => $subtitle,
            'extra' => $extra,
            'badge' => $badge,
            'tone' => $tone,
            'route' => $route,
            'avatar' => $avatar,
        ];
    }

    private function searchRoute(string $path, string $term, array $query = []): string
    {
        return $path . '?' . http_build_query($query + ['search' => $term]);
    }

    private function personnel(array $like, string $rawTerm, int $firmaId, int $limit): array
    {
        $digits = preg_replace('/[^0-9]/', '', $rawTerm);
        $conditions = ['p.adi_soyadi LIKE :term', 'p.email_adresi LIKE :term', 'p.gorev LIKE :term', 'p.departman LIKE :term'];
        $params = $like + [':firma_id' => $firmaId];

        if (strlen($digits) >= 3) {
            $conditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(p.cep_telefonu, ' ', ''), '-', ''), '(', ''), ')', '') LIKE :phone";
            $params[':phone'] = '%' . $digits . '%';
        }
        if (strlen($digits) === 11) {
            $conditions[] = 'p.tc_hash = :tc_hash';
            $params[':tc_hash'] = hash('sha256', $digits);
        }

        $sql = "SELECT p.id, p.adi_soyadi, p.gorev, p.departman, p.isten_cikis_tarihi, p.aktif_mi
                FROM personel p
                WHERE p.firma_id = :firma_id
                  AND p.silinme_tarihi IS NULL
                  AND p.personel_tipi = 'standart'
                  AND (p.disardan_sigortali = 0 OR FIND_IN_SET('personel', p.gorunum_modulleri))
                  AND (" . implode(' OR ', $conditions) . ")
                ORDER BY CASE WHEN p.adi_soyadi LIKE :term_exact THEN 1 WHEN p.adi_soyadi LIKE :term_starts THEN 2 ELSE 3 END, p.id DESC
                LIMIT " . (int) $limit;

        $items = [];
        foreach ($this->fetch($sql, $params, 'personnel') as $row) {
            $active = (empty($row['isten_cikis_tarihi']) || $row['isten_cikis_tarihi'] === '0000-00-00' || $row['isten_cikis_tarihi'] > date('Y-m-d'))
                && !(isset($row['aktif_mi']) && (int) $row['aktif_mi'] === 0);
            $encId = Security::encrypt((string) $row['id']);
            $items[] = $this->item(
                (int) $row['id'],
                (string) $row['adi_soyadi'],
                implode(' • ', array_filter([$row['gorev'] ?? '', $row['departman'] ?? ''])) ?: 'Personel',
                '',
                $active ? 'Aktif' : 'Ayrıldı',
                $active ? 'success' : 'danger',
                '/personel/' . rawurlencode($encId),
                '/api/personnel-photo.php?id=' . rawurlencode($encId)
            );
        }
        return $items;
    }

    private function invoices(array $like, int $firmaId, int $limit, array $types): array
    {
        $types = array_values(array_intersect($types, ['taslak', 'gelen', 'giden']));
        if ($types === []) {
            return [];
        }

        $draft = "(f.fatura_no IS NULL OR TRIM(f.fatura_no) = '' OR f.fatura_no = 'Taslak')";
        $typeClauses = [];
        foreach ($types as $type) {
            $typeClauses[] = match ($type) {
                'taslak' => $draft,
                'gelen' => "(NOT {$draft} AND UPPER(f.yon) = 'GELEN')",
                default => "(NOT {$draft} AND (f.yon IS NULL OR UPPER(f.yon) <> 'GELEN'))",
            };
        }

        $sql = "SELECT f.id, f.yon, f.fatura_no, f.fatura_tarihi, f.alici_unvan, f.odenecek_tutar, f.para_birimi, f.ettn
                FROM faturalar f
                WHERE f.firm_id = :firma_id
                  AND f.deleted_at IS NULL
                  AND (" . implode(' OR ', $typeClauses) . ")
                  AND (
                      f.fatura_no LIKE :term OR f.alici_unvan LIKE :term OR f.alici_vkn_tckn LIKE :term
                      OR f.ettn LIKE :term OR f.siparis_no LIKE :term OR f.irsaliye_no LIKE :term
                  )
                ORDER BY CASE WHEN f.fatura_no LIKE :term_exact THEN 1 WHEN f.fatura_no LIKE :term_starts THEN 2 ELSE 3 END,
                         f.fatura_tarihi DESC, f.id DESC
                LIMIT " . (int) $limit;

        $items = [];
        foreach ($this->fetch($sql, $like + [':firma_id' => $firmaId], 'invoices') as $row) {
            $no = trim((string) ($row['fatura_no'] ?? ''));
            $isDraft = $no === '' || $no === 'Taslak';
            $isIncoming = strtoupper((string) ($row['yon'] ?? '')) === 'GELEN';
            $tab = $isDraft ? 'taslak' : ($isIncoming ? 'gelen' : 'giden');
            $currency = (string) ($row['para_birimi'] ?: 'TRY');
            $symbol = in_array($currency, ['TRY', 'TL'], true) ? '₺' : $currency;
            $amount = number_format((float) ($row['odenecek_tutar'] ?? 0), 2, ',', '.') . ' ' . $symbol;
            $title = $no !== '' ? $no : ($row['ettn'] ? 'Taslak (' . mb_substr((string) $row['ettn'], 0, 8) . '...)' : 'Taslak Fatura');
            $searchTerm = $no !== '' && !$isDraft ? $no : (string) ($row['alici_unvan'] ?: $title);
            $items[] = $this->item(
                (int) $row['id'],
                $title,
                trim((string) ($row['alici_unvan'] ?? '')) ?: 'Alıcı belirtilmemiş',
                !empty($row['fatura_tarihi']) ? date('d.m.Y', strtotime((string) $row['fatura_tarihi'])) . ' • ' . $amount : $amount,
                $isDraft ? 'Taslak' : ($isIncoming ? 'Gelen' : 'Giden'),
                $isDraft ? 'warning' : ($isIncoming ? 'info' : 'success'),
                $this->searchRoute('/fatura', $searchTerm, ['tab' => $tab])
            );
        }
        return $items;
    }

    private function vehicles(array $like, int $firmaId, int $limit): array
    {
        $sql = "SELECT id, plaka, marka, model, model_yili, departmani, ruhsat_sahibi, guncel_km, aktif_mi
                FROM araclar
                WHERE firma_id = :firma_id AND silinme_tarihi IS NULL
                  AND (plaka LIKE :term OR marka LIKE :term OR model LIKE :term OR ruhsat_sahibi LIKE :term
                       OR sase_no LIKE :term OR motor_no LIKE :term OR departmani LIKE :term)
                ORDER BY CASE WHEN plaka LIKE :term_exact THEN 1 WHEN plaka LIKE :term_starts THEN 2 ELSE 3 END, id DESC
                LIMIT " . (int) $limit;

        $items = [];
        foreach ($this->fetch($sql, $like + [':firma_id' => $firmaId], 'vehicles') as $row) {
            $active = (int) $row['aktif_mi'] === 1;
            $items[] = $this->item(
                (int) $row['id'],
                (string) $row['plaka'],
                trim(($row['marka'] ?? '') . ' ' . ($row['model'] ?? '') . ($row['model_yili'] ? ' (' . $row['model_yili'] . ')' : '')) ?: 'Araç',
                (string) ($row['departmani'] ?: ($row['ruhsat_sahibi'] ?: '')),
                $active ? 'Aktif' : 'Pasif',
                $active ? 'success' : 'danger',
                $this->searchRoute('/arac-takip', (string) $row['plaka'])
            );
        }
        return $items;
    }

    private function currentAccounts(array $like, int $firmaId, int $limit): array
    {
        $sql = "SELECT id, CariAdi, Telefon, Email, firma, Adres, Aktif
                FROM cari
                WHERE silinme_tarihi IS NULL
                  AND (CariAdi LIKE :term OR Telefon LIKE :term OR Email LIKE :term OR firma LIKE :term OR Adres LIKE :term)
                ORDER BY CASE WHEN CariAdi LIKE :term_exact THEN 1 WHEN CariAdi LIKE :term_starts THEN 2 ELSE 3 END, id DESC
                LIMIT " . (int) $limit;

        $items = [];
        foreach ($this->fetch($sql, $like, 'currentAccounts') as $row) {
            $active = (int) $row['Aktif'] === 1;
            $items[] = $this->item(
                (int) $row['id'],
                (string) $row['CariAdi'],
                (string) ($row['firma'] ?: 'Cari Hesap'),
                (string) ($row['Telefon'] ?: ($row['Email'] ?: '')),
                $active ? 'Aktif' : 'Pasif',
                $active ? 'success' : 'danger',
                '/cari-takip/' . rawurlencode(Security::encrypt((string) $row['id']))
            );
        }
        return $items;
    }

    private function documents(array $like, int $firmaId, int $limit, array $types): array
    {
        $types = array_values(array_intersect($types, ['gelen', 'giden']));
        if ($types === []) {
            return [];
        }

        $params = $like + [':firma_id' => $firmaId];
        $typeParams = [];
        foreach ($types as $i => $type) {
            $typeParams[] = ':tip' . $i;
            $params[':tip' . $i] = $type;
        }

        $sql = "SELECT id, evrak_no, evrak_tipi, konu, kurum_adi, tarih, onay_durumu
                FROM evrak_takip
                WHERE firma_id = :firma_id AND silinme_tarihi IS NULL
                  AND evrak_tipi IN (" . implode(',', $typeParams) . ")
                  AND (evrak_no LIKE :term OR konu LIKE :term OR kurum_adi LIKE :term OR aciklama LIKE :term)
                ORDER BY CASE WHEN evrak_no LIKE :term_exact THEN 1 WHEN evrak_no LIKE :term_starts THEN 2 ELSE 3 END, id DESC
                LIMIT " . (int) $limit;

        $items = [];
        foreach ($this->fetch($sql, $params, 'documents') as $row) {
            $approval = (string) ($row['onay_durumu'] ?? 'taslak');
            [$badge, $tone] = match ($approval) {
                'onaylandi' => ['Onaylandı', 'success'],
                'onay_bekliyor' => ['Onay Bekliyor', 'warning'],
                default => ['Taslak', 'muted'],
            };
            $items[] = $this->item(
                (int) $row['id'],
                (string) ($row['konu'] ?: ('Evrak: ' . $row['evrak_no'])),
                trim(($row['kurum_adi'] ?? '') . ' (' . ($row['evrak_tipi'] === 'gelen' ? 'Gelen' : 'Giden') . ')'),
                (string) ($row['evrak_no'] ? 'No: ' . $row['evrak_no'] : ''),
                $badge,
                $tone,
                $this->searchRoute('/evrak-takip', (string) ($row['evrak_no'] ?: $row['konu']))
            );
        }
        return $items;
    }

    private function illegal(array $like, int $firmaId, int $limit): array
    {
        $sql = "SELECT id, tutanak_no, abone_adi, sayac_no, ilce, tur, tarih, onay_durumu
                FROM kacak_kontrol
                WHERE firma_id = :firma_id AND silinme_tarihi IS NULL
                  AND (tutanak_no LIKE :term OR abone_adi LIKE :term OR sayac_no LIKE :term OR abone_tel LIKE :term
                       OR ilce LIKE :term OR abone_adres LIKE :term)
                ORDER BY id DESC
                LIMIT " . (int) $limit;

        $items = [];
        foreach ($this->fetch($sql, [':firma_id' => $firmaId, ':term' => $like[':term']], 'illegal') as $row) {
            [$badge, $tone] = match ((string) ($row['onay_durumu'] ?? 'beklemede')) {
                'onaylandi' => ['Onaylandı', 'success'],
                'reddedildi' => ['Reddedildi', 'danger'],
                default => ['Beklemede', 'warning'],
            };
            $items[] = $this->item(
                (int) $row['id'],
                (string) ($row['abone_adi'] ?: ('Tutanak: ' . $row['tutanak_no'])),
                trim(($row['tur'] ?: 'Kaçak') . ($row['ilce'] ? ' • ' . $row['ilce'] : '') . ($row['sayac_no'] ? ' • Sayaç: ' . $row['sayac_no'] : '')),
                (string) ($row['tutanak_no'] ? 'Tut: ' . $row['tutanak_no'] : ''),
                $badge,
                $tone,
                $this->searchRoute('/kacak-kontrol', (string) ($row['tutanak_no'] ?: $row['abone_adi']))
            );
        }
        return $items;
    }
}
