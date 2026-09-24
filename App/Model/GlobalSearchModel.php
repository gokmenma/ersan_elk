<?php

namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use App\Helper\Helper;
use PDO;

class GlobalSearchModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Tüm yetkili modüllerde küresel arama yapar
     * 
     * @param string $query Arama kelimesi
     * @param int $firmaId Firma ID
     * @param int $userId Kullanıcı ID
     * @param string $category Modül filtresi ('all', 'personel', 'araclar', 'demirbaslar', 'cariler', 'evraklar', 'gorevler', 'kacak', 'aparatlar')
     * @param int $limit Modül başına maksimum sonuç sayısı
     * @param array $allowedModules Kullanıcının erişim izni olan modül listesi
     * @return array
     */
    public function search(
        string $query, 
        int $firmaId, 
        int $userId = 0, 
        string $category = 'all', 
        int $limit = 8, 
        array $allowedModules = ['personel', 'araclar', 'demirbaslar', 'cariler', 'evraklar', 'gorevler', 'kacak', 'aparatlar']
    ): array {
        $rawQuery = trim($query);
        $counts = [
            'all'         => 0,
            'personel'    => 0,
            'araclar'     => 0,
            'demirbaslar' => 0,
            'cariler'     => 0,
            'evraklar'    => 0,
            'gorevler'    => 0,
            'kacak'       => 0,
            'aparatlar'   => 0
        ];

        $results = [
            'personel'    => [],
            'araclar'     => [],
            'demirbaslar' => [],
            'cariler'     => [],
            'evraklar'    => [],
            'gorevler'    => [],
            'kacak'       => [],
            'aparatlar'   => []
        ];

        if (mb_strlen($rawQuery, 'UTF-8') < 1 || $firmaId <= 0) {
            return [
                'counts' => $counts,
                'results' => $results
            ];
        }

        // 1. PERSONELLER
        if (in_array('personel', $allowedModules) && ($category === 'all' || $category === 'personel')) {
            $results['personel'] = $this->searchPersonnel($rawQuery, $firmaId, $limit);
            $counts['personel'] = count($results['personel']);
        }

        // 2. ARAÇLAR
        if (in_array('araclar', $allowedModules) && ($category === 'all' || $category === 'araclar')) {
            $results['araclar'] = $this->searchVehicles($rawQuery, $firmaId, $limit);
            $counts['araclar'] = count($results['araclar']);
        }

        // 3. DEMİRBAŞLAR
        if (in_array('demirbaslar', $allowedModules) && ($category === 'all' || $category === 'demirbaslar')) {
            $results['demirbaslar'] = $this->searchDemirbas($rawQuery, $firmaId, $limit);
            $counts['demirbaslar'] = count($results['demirbaslar']);
        }

        // 4. CARİLER
        if (in_array('cariler', $allowedModules) && ($category === 'all' || $category === 'cariler')) {
            $results['cariler'] = $this->searchCariler($rawQuery, $firmaId, $limit);
            $counts['cariler'] = count($results['cariler']);
        }

        // 5. EVRAKLAR
        if (in_array('evraklar', $allowedModules) && ($category === 'all' || $category === 'evraklar')) {
            $results['evraklar'] = $this->searchEvraklar($rawQuery, $firmaId, $limit);
            $counts['evraklar'] = count($results['evraklar']);
        }

        // 6. GÖREVLER
        if (in_array('gorevler', $allowedModules) && ($category === 'all' || $category === 'gorevler')) {
            $results['gorevler'] = $this->searchGorevler($rawQuery, $firmaId, $limit);
            $counts['gorevler'] = count($results['gorevler']);
        }

        // 7. KAÇAK / SAHA TUTANAKLARI
        if (in_array('kacak', $allowedModules) && ($category === 'all' || $category === 'kacak')) {
            $results['kacak'] = $this->searchKacak($rawQuery, $firmaId, $limit);
            $counts['kacak'] = count($results['kacak']);
        }

        // 8. APARATLAR
        if (in_array('aparatlar', $allowedModules) && ($category === 'all' || $category === 'aparatlar')) {
            $results['aparatlar'] = $this->searchAparatlar($rawQuery, $firmaId, $limit);
            $counts['aparatlar'] = count($results['aparatlar']);
        }

        $counts['all'] = array_sum([
            $counts['personel'],
            $counts['araclar'],
            $counts['demirbaslar'],
            $counts['cariler'],
            $counts['evraklar'],
            $counts['gorevler'],
            $counts['kacak'],
            $counts['aparatlar']
        ]);

        return [
            'counts' => $counts,
            'results' => $results
        ];
    }

    /**
     * Personellerde arama yapar
     * 
     * @param string $term Arama kelimesi
     * @param int $firmaId Firma ID
     * @param int $limit Maksimum sonuç sayısı
     * @return array
     */
    public function searchPersonnel(string $term, int $firmaId, int $limit = 8): array
    {
        $term = trim($term);
        if (empty($term) || $firmaId <= 0) {
            return [];
        }

        $cleanTerm = preg_replace('/[^0-9]/', '', $term);
        $isNumericSearch = !empty($cleanTerm) && strlen($cleanTerm) >= 3;
        $isTcExact = strlen($cleanTerm) === 11;

        $params = [
            ':firma_id' => $firmaId,
            ':term_like' => '%' . $term . '%'
        ];

        $whereConditions = [
            "p.adi_soyadi LIKE :term_like",
            "p.email_adresi LIKE :term_like",
            "p.gorev LIKE :term_like",
            "p.departman LIKE :term_like"
        ];

        // Telefon veya TC bazlı arama
        if ($isNumericSearch) {
            $params[':phone_like'] = '%' . $cleanTerm . '%';
            $whereConditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(p.cep_telefonu, ' ', ''), '-', ''), '(', ''), ')', '') LIKE :phone_like";
        }

        if ($isTcExact) {
            $params[':tc_hash'] = hash('sha256', $cleanTerm);
            $whereConditions[] = "p.tc_hash = :tc_hash";
        }

        $whereSql = '(' . implode(' OR ', $whereConditions) . ')';

        $sql = "SELECT 
                    p.id,
                    p.adi_soyadi,
                    p.tc_kimlik_no,
                    p.cep_telefonu,
                    p.email_adresi,
                    p.gorev,
                    p.departman,
                    p.resim_yolu,
                    p.personel_resim_yolu,
                    p.isten_cikis_tarihi,
                    p.aktif_mi,
                    p.silinme_tarihi,
                    t.tur_adi as ekip_adi
                FROM personel p
                LEFT JOIN personel_ekip_gecmisi peg ON p.id = peg.personel_id 
                    AND peg.baslangic_tarihi <= CURDATE() 
                    AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi >= CURDATE())
                    AND peg.firma_id = :firma_id_ekip
                LEFT JOIN tanimlamalar t ON peg.ekip_kodu_id = t.id
                WHERE p.firma_id = :firma_id 
                    AND p.silinme_tarihi IS NULL 
                    AND p.personel_tipi = 'standart' 
                    AND (p.disardan_sigortali = 0 OR FIND_IN_SET('personel', p.gorunum_modulleri))
                    AND {$whereSql}
                GROUP BY p.id
                ORDER BY 
                    CASE 
                        WHEN p.adi_soyadi LIKE :term_exact THEN 1
                        WHEN p.adi_soyadi LIKE :term_starts THEN 2
                        ELSE 3
                    END,
                    p.id DESC
                LIMIT " . (int)$limit;

        $params[':firma_id_ekip'] = $firmaId;
        $params[':term_exact'] = $term;
        $params[':term_starts'] = $term . '%';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                $decryptedTc = '';
                if (!empty($row['tc_kimlik_no'])) {
                    $decryptedTc = Security::decrypt($row['tc_kimlik_no']) ?: '';
                }

                $maskedTc = !empty($decryptedTc) ? substr($decryptedTc, 0, 3) . '*****' . substr($decryptedTc, -2) : '';

                // Durum belirleme
                $isAktif = empty($row['isten_cikis_tarihi']) || 
                           $row['isten_cikis_tarihi'] === '0000-00-00' || 
                           $row['isten_cikis_tarihi'] > date('Y-m-d');
                
                if (isset($row['aktif_mi']) && $row['aktif_mi'] == 0) {
                    $isAktif = false;
                }

                $initials = $this->getInitials($row['adi_soyadi'] ?? '');
                $avatarColor = $this->getAvatarColor($row['adi_soyadi'] ?? '');

                $avatarUrl = null;
                $imgPath = $row['personel_resim_yolu'] ?: $row['resim_yolu'];
                if (!empty($imgPath)) {
                    $rawPath = ltrim($imgPath, '/');
                    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/ersan_elk/' . $rawPath) || file_exists(dirname(__DIR__, 2) . '/' . $rawPath)) {
                        $avatarUrl = Helper::base_url($rawPath);
                    }
                }

                $encryptedId = Security::encrypt($row['id']);
                $subParts = [];
                if (!empty($row['gorev'])) $subParts[] = $row['gorev'];
                if (!empty($row['departman'])) $subParts[] = $row['departman'];
                if (!empty($row['ekip_adi'])) $subParts[] = $row['ekip_adi'];

                $results[] = [
                    'id'            => (int)$row['id'],
                    'enc_id'        => $encryptedId,
                    'type'          => 'personel',
                    'title'         => $row['adi_soyadi'],
                    'subtitle'      => !empty($subParts) ? implode(' • ', $subParts) : 'Personel',
                    'extra_info'    => $maskedTc ? ('TC: ' . $maskedTc) : ($row['cep_telefonu'] ?? ''),
                    'date'          => '',
                    'badge'         => $isAktif ? 'Aktif' : 'Ayrıldı',
                    'badge_class'   => $isAktif ? 'badge-success' : 'badge-danger',
                    'initial'       => $initials,
                    'color_theme'   => 'blue',
                    'avatar_url'    => $avatarUrl,
                    'avatar_color'  => $avatarColor,
                    'url'           => 'index.php?p=personel/manage&id=' . $encryptedId
                ];
            }

            return $results;
        } catch (\PDOException $e) {
            error_log('GlobalSearchModel::searchPersonnel Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Araçlarda arama yapar
     * 
     * @param string $term
     * @param int $firmaId
     * @param int $limit
     * @return array
     */
    public function searchVehicles(string $term, int $firmaId, int $limit = 8): array
    {
        $term = trim($term);
        if (empty($term) || $firmaId <= 0) {
            return [];
        }

        $sql = "SELECT 
                    id, plaka, marka, model, model_yili, arac_tipi, departmani, ruhsat_sahibi, guncel_km, aktif_mi, durum
                FROM araclar
                WHERE firma_id = :firma_id 
                  AND silinme_tarihi IS NULL
                  AND (
                      plaka LIKE :term 
                      OR marka LIKE :term 
                      OR model LIKE :term 
                      OR ruhsat_sahibi LIKE :term 
                      OR sase_no LIKE :term 
                      OR motor_no LIKE :term 
                      OR departmani LIKE :term
                  )
                ORDER BY 
                    CASE 
                        WHEN plaka LIKE :term_exact THEN 1
                        WHEN plaka LIKE :term_starts THEN 2
                        ELSE 3
                    END,
                    id DESC
                LIMIT " . (int)$limit;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':firma_id' => $firmaId,
                ':term' => '%' . $term . '%',
                ':term_exact' => $term,
                ':term_starts' => $term . '%'
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                $isAktif = ((int)$row['aktif_mi'] === 1);
                $encryptedId = Security::encrypt($row['id']);
                $sub = trim(($row['marka'] ?? '') . ' ' . ($row['model'] ?? '') . ($row['model_yili'] ? ' (' . $row['model_yili'] . ')' : ''));
                $extra = $row['departmani'] ?: ($row['ruhsat_sahibi'] ?: '');

                $results[] = [
                    'id'          => (int)$row['id'],
                    'enc_id'      => $encryptedId,
                    'type'        => 'araclar',
                    'title'       => $row['plaka'],
                    'subtitle'    => $sub ?: 'Araç Bilgisi',
                    'extra_info'  => $extra,
                    'date'        => !empty($row['guncel_km']) ? number_format($row['guncel_km'], 0, ',', '.') . ' KM' : '',
                    'badge'       => $isAktif ? 'Aktif' : 'Pasif',
                    'badge_class' => $isAktif ? 'badge-success' : 'badge-danger',
                    'initial'     => 'AR',
                    'color_theme' => 'amber',
                    'avatar_url'  => null,
                    'url'         => 'index.php?p=arac-takip/list&search=' . urlencode($row['plaka'] ?? '')
                ];
            }

            return $results;
        } catch (\PDOException $e) {
            error_log('GlobalSearchModel::searchVehicles Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Demirbaşlarda arama yapar
     * 
     * @param string $term
     * @param int $firmaId
     * @param int $limit
     * @return array
     */
    public function searchDemirbas(string $term, int $firmaId, int $limit = 8): array
    {
        $term = trim($term);
        if (empty($term) || $firmaId <= 0) {
            return [];
        }

        $sql = "SELECT 
                    id, demirbas_adi, demirbas_no, seri_no, marka, model, lokasyon, edinme_tarihi, durum, miktar
                FROM demirbas
                WHERE firma_id = :firma_id 
                  AND silinme_tarihi IS NULL
                  AND (
                      demirbas_adi LIKE :term 
                      OR demirbas_no LIKE :term 
                      OR seri_no LIKE :term 
                      OR marka LIKE :term 
                      OR model LIKE :term 
                      OR lokasyon LIKE :term 
                      OR aciklama LIKE :term
                  )
                ORDER BY 
                    CASE 
                        WHEN demirbas_adi LIKE :term_exact THEN 1
                        WHEN demirbas_adi LIKE :term_starts THEN 2
                        ELSE 3
                    END,
                    id DESC
                LIMIT " . (int)$limit;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':firma_id' => $firmaId,
                ':term' => '%' . $term . '%',
                ':term_exact' => $term,
                ':term_starts' => $term . '%'
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                $encryptedId = Security::encrypt($row['id']);
                $subParts = [];
                if (!empty($row['marka']) || !empty($row['model'])) {
                    $subParts[] = trim(($row['marka'] ?? '') . ' ' . ($row['model'] ?? ''));
                }
                if (!empty($row['lokasyon'])) {
                    $subParts[] = $row['lokasyon'];
                }

                $durum = strtolower($row['durum'] ?? 'aktif');
                $badgeClass = 'badge-success';
                $badgeText = ucfirst($durum);

                if ($durum === 'arizali' || $durum === 'hurda') {
                    $badgeClass = 'badge-danger';
                } elseif ($durum === 'pasif' || $durum === 'serviste' || strpos($durum, 'teslim') !== false) {
                    $badgeClass = 'badge-warning';
                }

                $extra = $row['demirbas_no'] ? ('No: ' . $row['demirbas_no']) : ($row['seri_no'] ? ('SN: ' . $row['seri_no']) : '');

                $results[] = [
                    'id'          => (int)$row['id'],
                    'enc_id'      => $encryptedId,
                    'type'        => 'demirbaslar',
                    'title'       => $row['demirbas_adi'],
                    'subtitle'    => !empty($subParts) ? implode(' • ', $subParts) : 'Demirbaş',
                    'extra_info'  => $extra,
                    'date'        => $row['edinme_tarihi'] ?: '',
                    'badge'       => $badgeText,
                    'badge_class' => $badgeClass,
                    'initial'     => 'DM',
                    'color_theme' => 'purple',
                    'avatar_url'  => null,
                    'url'         => 'index.php?p=demirbas/list&search=' . urlencode($row['demirbas_adi'] ?: ($row['demirbas_no'] ?: ''))
                ];
            }

            return $results;
        } catch (\PDOException $e) {
            error_log('GlobalSearchModel::searchDemirbas Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Carilerde arama yapar
     * 
     * @param string $term
     * @param int $firmaId
     * @param int $limit
     * @return array
     */
    public function searchCariler(string $term, int $firmaId, int $limit = 8): array
    {
        $term = trim($term);
        if (empty($term)) {
            return [];
        }

        $sql = "SELECT 
                    id, CariAdi, Telefon, Email, firma, Adres, kayit_tarihi, Aktif
                FROM cari
                WHERE silinme_tarihi IS NULL
                  AND (
                      CariAdi LIKE :term 
                      OR Telefon LIKE :term 
                      OR Email LIKE :term 
                      OR firma LIKE :term 
                      OR Adres LIKE :term 
                      OR notlar LIKE :term
                  )
                ORDER BY 
                    CASE 
                        WHEN CariAdi LIKE :term_exact THEN 1
                        WHEN CariAdi LIKE :term_starts THEN 2
                        ELSE 3
                    END,
                    id DESC
                LIMIT " . (int)$limit;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':term' => '%' . $term . '%',
                ':term_exact' => $term,
                ':term_starts' => $term . '%'
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                $isAktif = ((int)$row['Aktif'] === 1);
                $encryptedId = Security::encrypt($row['id']);
                $sub = $row['firma'] ?: ($row['Adres'] ? mb_substr($row['Adres'], 0, 45) . '...' : 'Cari Hesap');

                $results[] = [
                    'id'          => (int)$row['id'],
                    'enc_id'      => $encryptedId,
                    'type'        => 'cariler',
                    'title'       => $row['CariAdi'],
                    'subtitle'    => $sub,
                    'extra_info'  => $row['Telefon'] ?: ($row['Email'] ?: ''),
                    'date'        => !empty($row['kayit_tarihi']) ? date('d.m.Y', strtotime($row['kayit_tarihi'])) : '',
                    'badge'       => $isAktif ? 'Aktif' : 'Pasif',
                    'badge_class' => $isAktif ? 'badge-success' : 'badge-danger',
                    'initial'     => 'CR',
                    'color_theme' => 'emerald',
                    'avatar_url'  => null,
                    'url'         => 'index.php?p=cari/list&search=' . urlencode($row['CariAdi'] ?? '')
                ];
            }

            return $results;
        } catch (\PDOException $e) {
            error_log('GlobalSearchModel::searchCariler Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Evrak Takipte arama yapar
     * 
     * @param string $term
     * @param int $firmaId
     * @param int $limit
     * @return array
     */
    public function searchEvraklar(string $term, int $firmaId, int $limit = 8): array
    {
        $term = trim($term);
        if (empty($term) || $firmaId <= 0) {
            return [];
        }

        $sql = "SELECT 
                    id, evrak_no, evrak_tipi, konu, kurum_adi, tarih, onay_durumu, aciklama
                FROM evrak_takip
                WHERE firma_id = :firma_id 
                  AND silinme_tarihi IS NULL
                  AND (
                      evrak_no LIKE :term 
                      OR konu LIKE :term 
                      OR kurum_adi LIKE :term 
                      OR aciklama LIKE :term
                  )
                ORDER BY id DESC
                LIMIT " . (int)$limit;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':firma_id' => $firmaId,
                ':term' => '%' . $term . '%'
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                $encryptedId = Security::encrypt($row['id']);
                $sub = trim(($row['kurum_adi'] ?? '') . ' (' . ($row['evrak_tipi'] === 'gelen' ? 'Gelen Evrak' : 'Giden Evrak') . ')');
                $onay = $row['onay_durumu'] ?? 'taslak';
                $badgeClass = 'badge-secondary';
                $badgeText = 'Taslak';

                if ($onay === 'onaylandi') {
                    $badgeClass = 'badge-success';
                    $badgeText = 'Onaylandı';
                } elseif ($onay === 'onay_bekliyor') {
                    $badgeClass = 'badge-warning';
                    $badgeText = 'Onay Bekliyor';
                }

                $results[] = [
                    'id'          => (int)$row['id'],
                    'enc_id'      => $encryptedId,
                    'type'        => 'evraklar',
                    'title'       => $row['konu'] ?: ('Evrak: ' . $row['evrak_no']),
                    'subtitle'    => $sub ?: 'Evrak Kaydı',
                    'extra_info'  => $row['evrak_no'] ? ('No: ' . $row['evrak_no']) : '',
                    'date'        => !empty($row['tarih']) ? date('d.m.Y', strtotime($row['tarih'])) : '',
                    'badge'       => $badgeText,
                    'badge_class' => $badgeClass,
                    'initial'     => 'EV',
                    'color_theme' => 'indigo',
                    'avatar_url'  => null,
                    'url'         => 'index.php?p=evrak-takip/list&search=' . urlencode($row['evrak_no'] ?: ($row['konu'] ?: ''))
                ];
            }

            return $results;
        } catch (\PDOException $e) {
            error_log('GlobalSearchModel::searchEvraklar Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Görevlerde arama yapar
     * 
     * @param string $term
     * @param int $firmaId
     * @param int $limit
     * @return array
     */
    public function searchGorevler(string $term, int $firmaId, int $limit = 8): array
    {
        $term = trim($term);
        if (empty($term) || $firmaId <= 0) {
            return [];
        }

        $sql = "SELECT 
                    id, baslik, aciklama, tarih, saat, tamamlandi
                FROM gorevler
                WHERE firma_id = :firma_id
                  AND (
                      baslik LIKE :term 
                      OR aciklama LIKE :term
                  )
                ORDER BY id DESC
                LIMIT " . (int)$limit;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':firma_id' => $firmaId,
                ':term' => '%' . $term . '%'
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                $encryptedId = Security::encrypt($row['id']);
                $isDone = ((int)$row['tamamlandi'] === 1);
                $desc = !empty($row['aciklama']) ? mb_substr(strip_tags($row['aciklama']), 0, 50) . '...' : 'Görev Tanımı';

                $results[] = [
                    'id'          => (int)$row['id'],
                    'enc_id'      => $encryptedId,
                    'type'        => 'gorevler',
                    'title'       => $row['baslik'],
                    'subtitle'    => $desc,
                    'extra_info'  => '',
                    'date'        => !empty($row['tarih']) ? date('d.m.Y', strtotime($row['tarih'])) : '',
                    'badge'       => $isDone ? 'Tamamlandı' : 'Bekliyor',
                    'badge_class' => $isDone ? 'badge-success' : 'badge-warning',
                    'initial'     => 'GR',
                    'color_theme' => 'cyan',
                    'avatar_url'  => null,
                    'url'         => 'index.php?p=gorevler/list&search=' . urlencode($row['baslik'] ?? '')
                ];
            }

            return $results;
        } catch (\PDOException $e) {
            error_log('GlobalSearchModel::searchGorevler Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Kaçak Kontrol / Saha Tutanaklarında arama yapar
     * 
     * @param string $term
     * @param int $firmaId
     * @param int $limit
     * @return array
     */
    public function searchKacak(string $term, int $firmaId, int $limit = 8): array
    {
        $term = trim($term);
        if (empty($term) || $firmaId <= 0) {
            return [];
        }

        $sql = "SELECT 
                    id, tutanak_no, abone_adi, sayac_no, abone_tel, ilce, tur, tarih, onay_durumu
                FROM kacak_kontrol
                WHERE firma_id = :firma_id 
                  AND silinme_tarihi IS NULL
                  AND (
                      tutanak_no LIKE :term 
                      OR abone_adi LIKE :term 
                      OR sayac_no LIKE :term 
                      OR abone_tel LIKE :term 
                      OR ilce LIKE :term 
                      OR abone_adres LIKE :term
                  )
                ORDER BY id DESC
                LIMIT " . (int)$limit;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':firma_id' => $firmaId,
                ':term' => '%' . $term . '%'
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                $encryptedId = Security::encrypt($row['id']);
                $sub = trim(($row['tur'] ?? 'Kaçak') . ($row['ilce'] ? ' • ' . $row['ilce'] : '') . ($row['sayac_no'] ? ' • Sayaç: ' . $row['sayac_no'] : ''));
                $onay = $row['onay_durumu'] ?? 'beklemede';
                $badgeClass = 'badge-warning';
                $badgeText = 'Beklemede';

                if ($onay === 'onaylandi') {
                    $badgeClass = 'badge-success';
                    $badgeText = 'Onaylandı';
                } elseif ($onay === 'reddedildi') {
                    $badgeClass = 'badge-danger';
                    $badgeText = 'Reddedildi';
                }

                $results[] = [
                    'id'          => (int)$row['id'],
                    'enc_id'      => $encryptedId,
                    'type'        => 'kacak',
                    'title'       => $row['abone_adi'] ?: ('Tutanak: ' . $row['tutanak_no']),
                    'subtitle'    => $sub ?: 'Saha Tutanağı',
                    'extra_info'  => $row['tutanak_no'] ? ('Tut: ' . $row['tutanak_no']) : '',
                    'date'        => !empty($row['tarih']) ? date('d.m.Y', strtotime($row['tarih'])) : '',
                    'badge'       => $badgeText,
                    'badge_class' => $badgeClass,
                    'initial'     => 'KÇ',
                    'color_theme' => 'purple',
                    'avatar_url'  => null,
                    'url'         => 'index.php?p=kacak/list&search=' . urlencode($row['tutanak_no'] ?: ($row['abone_adi'] ?: ''))
                ];
            }

            return $results;
        } catch (\PDOException $e) {
            error_log('GlobalSearchModel::searchKacak Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Aparat Tiplerinde arama yapar
     * 
     * @param string $term
     * @param int $firmaId
     * @param int $limit
     * @return array
     */
    public function searchAparatlar(string $term, int $firmaId, int $limit = 8): array
    {
        $term = trim($term);
        if (empty($term) || $firmaId <= 0) {
            return [];
        }

        $sql = "SELECT 
                    id, ad, kod, renk, aciklama, is_active
                FROM aparat_tipleri
                WHERE firma_id = :firma_id 
                  AND silinme_tarihi IS NULL
                  AND (
                      ad LIKE :term 
                      OR kod LIKE :term 
                      OR aciklama LIKE :term
                  )
                ORDER BY sira ASC, id DESC
                LIMIT " . (int)$limit;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':firma_id' => $firmaId,
                ':term' => '%' . $term . '%'
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                $encryptedId = Security::encrypt($row['id']);
                $isAktif = ((int)$row['is_active'] === 1);

                $results[] = [
                    'id'          => (int)$row['id'],
                    'enc_id'      => $encryptedId,
                    'type'        => 'aparatlar',
                    'title'       => $row['ad'],
                    'subtitle'    => $row['aciklama'] ?: 'Aparat Tipi',
                    'extra_info'  => $row['kod'] ? ('Kod: ' . $row['kod']) : '',
                    'date'        => '',
                    'badge'       => $isAktif ? 'Aktif' : 'Pasif',
                    'badge_class' => $isAktif ? 'badge-success' : 'badge-danger',
                    'initial'     => 'AP',
                    'color_theme' => 'amber',
                    'avatar_url'  => null,
                    'url'         => 'index.php?p=aparat-takip/list&tab=pane-tanimlar&search=' . urlencode($row['ad'] ?: ($row['kod'] ?: ''))
                ];
            }

            return $results;
        } catch (\PDOException $e) {
            error_log('GlobalSearchModel::searchAparatlar Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * İsimden baş harfleri çıkarır (Örn: "Ahmet Yılmaz" -> "AY")
     */
    private function getInitials(string $name): string
    {
        $name = trim($name);
        if (empty($name)) return 'P';

        $words = preg_split('/\s+/', $name);
        if (count($words) === 1) {
            return mb_strtoupper(mb_substr($words[0], 0, 2, 'UTF-8'), 'UTF-8');
        }

        $first = mb_substr($words[0], 0, 1, 'UTF-8');
        $last = mb_substr(end($words), 0, 1, 'UTF-8');
        return mb_strtoupper($first . $last, 'UTF-8');
    }

    /**
     * İsme göre sabit bir renk paleti döner
     */
    private function getAvatarColor(string $name): string
    {
        $colors = [
            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', 
            '#e74a3b', '#6f42c1', '#fd7e14', '#20c997', 
            '#007bff', '#6610f2', '#e83e8c', '#17a2b8'
        ];
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    }
}
