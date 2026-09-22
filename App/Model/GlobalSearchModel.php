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
     * Personellerde arama yapar
     * 
     * @param string $term Arama kelimesi
     * @param int $firmaId Firma ID
     * @param int $limit Maksimum sonuç sayısı
     * @return array
     */
    public function searchPersonnel(string $term, int $firmaId, int $limit = 10): array
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
                // TC Kimlik Deşifre
                $decryptedTc = '';
                if (!empty($row['tc_kimlik_no'])) {
                    $decryptedTc = Security::decrypt($row['tc_kimlik_no']) ?: '';
                }

                // Sayısal arama yapılıp TC hash uymadıysa decrypted TC içinde arama kontrolü
                if ($isNumericSearch && !$isTcExact && !empty($decryptedTc)) {
                    if (strpos($decryptedTc, $cleanTerm) === false && 
                        strpos($row['adi_soyadi'], $term) === false && 
                        strpos($row['gorev'] ?? '', $term) === false &&
                        strpos($row['departman'] ?? '', $term) === false) {
                        // Eğer diğer alanlarda eşleşme yoksa ve TC'de de bu rakamlar yoksa atla
                        // (Fakat SQL zaten filtrelediği için genel eşleşme vardır)
                    }
                }

                // Durum belirleme
                $isAktif = empty($row['isten_cikis_tarihi']) || 
                           $row['isten_cikis_tarihi'] === '0000-00-00' || 
                           $row['isten_cikis_tarihi'] > date('Y-m-d');
                
                if (isset($row['aktif_mi']) && $row['aktif_mi'] == 0) {
                    $isAktif = false;
                }

                // Baş harfler ve renk kodu
                $initials = $this->getInitials($row['adi_soyadi'] ?? '');
                $avatarColor = $this->getAvatarColor($row['adi_soyadi'] ?? '');

                // Profil resmi kontrolü
                $avatarUrl = null;
                if (!empty($row['resim_yolu'])) {
                    $rawPath = ltrim($row['resim_yolu'], '/');
                    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/ersan_elk/' . $rawPath) || file_exists(dirname(__DIR__, 2) . '/' . $rawPath)) {
                        $avatarUrl = Helper::base_url($rawPath);
                    }
                }

                $encryptedId = Security::encrypt($row['id']);

                $results[] = [
                    'id' => $encryptedId,
                    'raw_id' => (int)$row['id'],
                    'title' => $row['adi_soyadi'],
                    'tc' => $decryptedTc,
                    'masked_tc' => !empty($decryptedTc) ? substr($decryptedTc, 0, 3) . '*****' . substr($decryptedTc, -2) : '',
                    'phone' => $row['cep_telefonu'] ?? '',
                    'email' => $row['email_adresi'] ?? '',
                    'duty' => $row['gorev'] ?? 'Belirtilmemiş',
                    'department' => $row['departman'] ?? '',
                    'team' => $row['ekip_adi'] ?? '',
                    'is_active' => $isAktif,
                    'status_text' => $isAktif ? 'Aktif' : 'Ayrıldı',
                    'status_badge' => $isAktif ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger',
                    'initials' => $initials,
                    'avatar_color' => $avatarColor,
                    'avatar_url' => $avatarUrl,
                    'url' => 'index.php?p=personel/manage&id=' . $encryptedId,
                    'category' => 'personel',
                    'category_label' => 'Personel'
                ];
            }

            return $results;
        } catch (\PDOException $e) {
            error_log('GlobalSearchModel::searchPersonnel Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Genel arama yürütücü (Genişletilebilir mimari)
     * 
     * @param string $term
     * @param int $firmaId
     * @param int $userId
     * @param array $categories
     * @param int $limit
     * @return array
     */
    public function searchGlobal(string $term, int $firmaId, int $userId = 0, array $categories = ['personel'], int $limit = 10): array
    {
        $response = [
            'total' => 0,
            'categories' => []
        ];

        if (in_array('personel', $categories)) {
            $personelResults = $this->searchPersonnel($term, $firmaId, $limit);
            if (!empty($personelResults)) {
                $response['categories']['personel'] = [
                    'label' => 'Personeller',
                    'icon' => 'bx-user',
                    'count' => count($personelResults),
                    'items' => $personelResults
                ];
                $response['total'] += count($personelResults);
            }
        }

        // İleride buraya 'arac', 'talep', 'evrak' gibi kategoriler kolayca eklenebilir.

        return $response;
    }

    /**
     * İsimden baş harfleri çıkarır (Örn: "Mehmet Ali Yılmaz" -> "MY" veya "MA")
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
