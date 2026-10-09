<?php

namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use App\Helper\Helper;
use App\Model\SystemLogModel;
use PDO;
use Exception;

class SozlesmeDonemModel extends Model
{
    protected $table = 'sozlesme_donemleri';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Firmanın aktif olan sözleşme/çalışma dönemini getirir.
     * Eğer hiç aktif yoksa, ilk dönemi otomatik aktif yapar ve döner.
     */
    public function getActiveDonem(int $firma_id): ?object
    {
        $db = $this->getDb();
        $stmt = $db->prepare("
            SELECT * FROM {$this->table} 
            WHERE firma_id = ? AND silinme_tarihi IS NULL AND is_active = 1 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$firma_id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$row) {
            // Aktif kayıt bulunamadıysa var olan en son kaydı aktif yap
            $stmtLast = $db->prepare("
                SELECT id FROM {$this->table} 
                WHERE firma_id = ? AND silinme_tarihi IS NULL 
                ORDER BY baslangic_tarihi DESC, id DESC LIMIT 1
            ");
            $stmtLast->execute([$firma_id]);
            $lastId = (int) $stmtLast->fetchColumn();

            if ($lastId > 0) {
                $db->prepare("UPDATE {$this->table} SET is_active = 1 WHERE id = ?")->execute([$lastId]);
                $stmt->execute([$firma_id]);
                $row = $stmt->fetch(PDO::FETCH_OBJ);
            }
        }

        return $row ?: null;
    }

    /**
     * ID'ye ve firmaya göre tekil dönem getirir.
     */
    public function getDonemById(int $id, int $firma_id): ?object
    {
        $db = $this->getDb();
        $stmt = $db->prepare("
            SELECT * FROM {$this->table} 
            WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL
        ");
        $stmt->execute([$id, $firma_id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    /**
     * Firmanın tüm aktif/pasif dönemlerini sıralı getirir.
     */
    public function getAllDonemler(int $firma_id): array
    {
        $db = $this->getDb();
        $stmt = $db->prepare("
            SELECT *,
                DATEDIFF(bitis_tarihi, baslangic_tarihi) + 1 AS toplam_gun,
                DATEDIFF(bitis_tarihi, CURDATE()) AS kalan_gun
            FROM {$this->table} 
            WHERE firma_id = ? AND silinme_tarihi IS NULL 
            ORDER BY is_active DESC, baslangic_tarihi DESC, id DESC
        ");
        $stmt->execute([$firma_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
    }

    /**
     * Belirtilen dönemi aktif yapar, firmanın diğer dönemlerini pasife alır.
     * En az ve en çok 1 aktif dönem kuralını işletir.
     */
    public function setActive(int $id, int $firma_id, int $userId = 0): array
    {
        $db = $this->getDb();
        try {
            $db->beginTransaction();

            // Kaydın varlığını doğrula
            $stmtCheck = $db->prepare("SELECT id, donem_adi FROM {$this->table} WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
            $stmtCheck->execute([$id, $firma_id]);
            $donem = $stmtCheck->fetch(PDO::FETCH_OBJ);

            if (!$donem) {
                $db->rollBack();
                return ['status' => 'error', 'message' => 'Seçilen dönem kaydı bulunamadı.'];
            }

            // Önce firmanın tüm dönemlerini pasife al
            $stmtReset = $db->prepare("UPDATE {$this->table} SET is_active = 0, guncelleme_tarihi = NOW() WHERE firma_id = ?");
            $stmtReset->execute([$firma_id]);

            // Hedef dönemi aktif yap
            $stmtSet = $db->prepare("UPDATE {$this->table} SET is_active = 1, guncelleme_tarihi = NOW() WHERE id = ? AND firma_id = ?");
            $stmtSet->execute([$id, $firma_id]);

            // Session değerlerini güncelle
            $_SESSION['aktif_donem_id'] = $id;
            $_SESSION['aktif_donem_adi'] = $donem->donem_adi;

            // Log kaydı
            (new SystemLogModel())->logAction(
                $userId ?: (int)($_SESSION['user_id'] ?? 0),
                'Dönem Aktivasyonu',
                "Aktif dönem değiştirildi: [ID: {$id}] {$donem->donem_adi}",
                SystemLogModel::LEVEL_IMPORTANT
            );

            $db->commit();
            return [
                'status' => 'success',
                'message' => "'{$donem->donem_adi}' başarıyla aktif dönem olarak belirlendi.",
                'donem_id' => $id,
                'donem_adi' => $donem->donem_adi
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("SozlesmeDonemModel::setActive hatası: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Dönem aktif edilirken bir hata oluştu.'];
        }
    }

    /**
     * Yeni dönem ekler veya var olanı günceller.
     */
    public function saveDonem(array $data, int $firma_id, int $userId = 0): array
    {
        $id = (int) ($data['id'] ?? 0);
        $donem_adi = trim($data['donem_adi'] ?? '');
        $baslangic_tarihi = trim($data['baslangic_tarihi'] ?? '');
        $bitis_tarihi = trim($data['bitis_tarihi'] ?? '');
        $is_active = !empty($data['is_active']) ? 1 : 0;
        $aciklama = trim($data['aciklama'] ?? '');

        if (empty($donem_adi)) {
            return ['status' => 'error', 'message' => 'Lütfen dönem adını belirtiniz.'];
        }

        if (empty($baslangic_tarihi) || empty($bitis_tarihi)) {
            return ['status' => 'error', 'message' => 'Başlangıç ve bitiş tarihlerini eksiksiz giriniz.'];
        }

        if (strtotime($bitis_tarihi) < strtotime($baslangic_tarihi)) {
            return ['status' => 'error', 'message' => 'Bitiş tarihi başlangıç tarihinden önce olamaz.'];
        }

        $db = $this->getDb();

        try {
            $db->beginTransaction();

            // Firmanın mevcut dönem sayısını kontrol et
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE firma_id = ? AND silinme_tarihi IS NULL");
            $stmtCount->execute([$firma_id]);
            $existingCount = (int) $stmtCount->fetchColumn();

            // Eğer ilk kayıt ise veya tek kayıt varsa zorunlu olarak aktif olsun
            if ($existingCount === 0) {
                $is_active = 1;
            }

            if ($id > 0) {
                // Güncelleme işlemi
                $stmtCurrent = $db->prepare("SELECT * FROM {$this->table} WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
                $stmtCurrent->execute([$id, $firma_id]);
                $current = $stmtCurrent->fetch(PDO::FETCH_OBJ);

                if (!$current) {
                    $db->rollBack();
                    return ['status' => 'error', 'message' => 'Güncellenecek dönem bulunamadı.'];
                }

                // Eğer aktif olan dönem pasife çekilmeye çalışılıyorsa ve başka aktif yoksa engelle
                if ($current->is_active == 1 && $is_active == 0) {
                    $stmtOtherActive = $db->prepare("
                        SELECT COUNT(*) FROM {$this->table} 
                        WHERE firma_id = ? AND id != ? AND is_active = 1 AND silinme_tarihi IS NULL
                    ");
                    $stmtOtherActive->execute([$firma_id, $id]);
                    if ((int)$stmtOtherActive->fetchColumn() === 0) {
                        $db->rollBack();
                        return ['status' => 'error', 'message' => 'Sistemde en az bir aktif dönem bulunmalıdır. Bu dönemi pasife almadan önce lütfen başka bir dönemi aktif yapınız.'];
                    }
                }

                // Eğer bu dönem aktif yapılıyorsa diğerlerini pasife al
                if ($is_active == 1) {
                    $db->prepare("UPDATE {$this->table} SET is_active = 0, guncelleme_tarihi = NOW() WHERE firma_id = ? AND id != ?")->execute([$firma_id, $id]);
                }

                $stmtUpdate = $db->prepare("
                    UPDATE {$this->table} SET
                        donem_adi = :donem_adi,
                        baslangic_tarihi = :baslangic_tarihi,
                        bitis_tarihi = :bitis_tarihi,
                        is_active = :is_active,
                        aciklama = :aciklama,
                        guncelleme_tarihi = NOW()
                    WHERE id = :id AND firma_id = :firma_id
                ");

                $stmtUpdate->execute([
                    ':donem_adi' => $donem_adi,
                    ':baslangic_tarihi' => $baslangic_tarihi,
                    ':bitis_tarihi' => $bitis_tarihi,
                    ':is_active' => $is_active,
                    ':aciklama' => $aciklama,
                    ':id' => $id,
                    ':firma_id' => $firma_id
                ]);

                if ($is_active == 1) {
                    $_SESSION['aktif_donem_id'] = $id;
                    $_SESSION['aktif_donem_adi'] = $donem_adi;
                }

                (new SystemLogModel())->logAction(
                    $userId ?: (int)($_SESSION['user_id'] ?? 0),
                    'Dönem Güncelleme',
                    "Dönem güncellendi: [ID: {$id}] {$donem_adi}",
                    SystemLogModel::LEVEL_IMPORTANT
                );

                $db->commit();
                return ['status' => 'success', 'message' => 'Dönem bilgileri başarıyla güncellendi.', 'id' => $id];
            } else {
                // Yeni kayıt işlemi
                if ($is_active == 1) {
                    $db->prepare("UPDATE {$this->table} SET is_active = 0, guncelleme_tarihi = NOW() WHERE firma_id = ?")->execute([$firma_id]);
                }

                $stmtInsert = $db->prepare("
                    INSERT INTO {$this->table} 
                        (firma_id, donem_adi, baslangic_tarihi, bitis_tarihi, is_active, aciklama, olusturan_id, olusturma_tarihi)
                    VALUES 
                        (:firma_id, :donem_adi, :baslangic_tarihi, :bitis_tarihi, :is_active, :aciklama, :olusturan_id, NOW())
                ");

                $stmtInsert->execute([
                    ':firma_id' => $firma_id,
                    ':donem_adi' => $donem_adi,
                    ':baslangic_tarihi' => $baslangic_tarihi,
                    ':bitis_tarihi' => $bitis_tarihi,
                    ':is_active' => $is_active,
                    ':aciklama' => $aciklama,
                    ':olusturan_id' => $userId ?: (int)($_SESSION['user_id'] ?? 0)
                ]);

                $newId = (int) $db->lastInsertId();

                if ($is_active == 1) {
                    $_SESSION['aktif_donem_id'] = $newId;
                    $_SESSION['aktif_donem_adi'] = $donem_adi;
                }

                (new SystemLogModel())->logAction(
                    $userId ?: (int)($_SESSION['user_id'] ?? 0),
                    'Dönem Ekleme',
                    "Yeni dönem eklendi: [ID: {$newId}] {$donem_adi}",
                    SystemLogModel::LEVEL_IMPORTANT
                );

                $db->commit();
                return ['status' => 'success', 'message' => 'Yeni dönem başarıyla tanımlandı.', 'id' => $newId];
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("SozlesmeDonemModel::saveDonem hatası: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Dönem kaydedilirken bir hata meydana geldi.'];
        }
    }

    /**
     * Dönemi soft delete ile siler.
     * Aktif olan dönem silinemez kuralını uygular.
     */
    public function deleteDonem(int $id, int $firma_id, int $userId = 0): array
    {
        $db = $this->getDb();
        try {
            $stmt = $db->prepare("SELECT * FROM {$this->table} WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
            $stmt->execute([$id, $firma_id]);
            $donem = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$donem) {
                return ['status' => 'error', 'message' => 'Silinecek dönem kaydı bulunamadı.'];
            }

            if ($donem->is_active == 1) {
                return [
                    'status' => 'error',
                    'message' => 'Aktif olan dönem silinemez! Lütfen silmeden önce başka bir dönemi aktif yapınız.'
                ];
            }

            $stmtDelete = $db->prepare("UPDATE {$this->table} SET silinme_tarihi = NOW(), is_active = 0 WHERE id = ? AND firma_id = ?");
            $stmtDelete->execute([$id, $firma_id]);

            (new SystemLogModel())->logAction(
                $userId ?: (int)($_SESSION['user_id'] ?? 0),
                'Dönem Silme',
                "Dönem silindi: [ID: {$id}] {$donem->donem_adi}",
                SystemLogModel::LEVEL_IMPORTANT
            );

            return ['status' => 'success', 'message' => "'{$donem->donem_adi}' başarıyla silindi."];
        } catch (Exception $e) {
            error_log("SozlesmeDonemModel::deleteDonem hatası: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Dönem silinirken bir hata meydana geldi.'];
        }
    }

    /**
     * DataTables ServerSide Ajax Listesi
     */
    public function ajaxList(array $params, int $firma_id): array
    {
        $draw = (int) ($params['draw'] ?? 0);
        $start = (int) ($params['start'] ?? 0);
        $length = (int) ($params['length'] ?? 10);
        $search = trim($params['search']['value'] ?? '');
        $orders = $params['order'] ?? [];
        $columns = $params['columns'] ?? [];
        $status_filter = $params['status_filter'] ?? 'all';

        $where = "firma_id = :firma_id AND silinme_tarihi IS NULL";
        $bindParams = [':firma_id' => $firma_id];

        // Hızlı Durum Filtresi
        if ($status_filter === 'aktif') {
            $where .= " AND is_active = 1";
        } elseif ($status_filter === 'pasif') {
            $where .= " AND is_active = 0";
        }

        // Genel Arama
        if (!empty($search)) {
            $where .= " AND (donem_adi LIKE :search OR aciklama LIKE :search)";
            $bindParams[':search'] = "%{$search}%";
        }

        // Sütun Bazlı Filtreleme (Header Filters)
        $colMap = [
            0 => 'id',
            1 => 'donem_adi',
            2 => 'baslangic_tarihi',
            3 => 'bitis_tarihi',
            4 => 'is_active',
            5 => 'aciklama'
        ];

        if (!empty($columns)) {
            foreach ($columns as $i => $column) {
                if (!empty($column['search']['value']) && isset($colMap[$i])) {
                    $field = $colMap[$i];
                    $val = $column['search']['value'];
                    $paramName = "col_" . $i;

                    if ($field === 'is_active') {
                        if ($val === '1' || strtolower($val) === 'aktif') {
                            $where .= " AND is_active = 1";
                        } elseif ($val === '0' || strtolower($val) === 'pasif') {
                            $where .= " AND is_active = 0";
                        }
                    } elseif (strpos($val, ':') !== false) {
                        list($mode, $filterVal) = explode(':', $val, 2);
                        if ($mode === 'equals') {
                            $where .= " AND {$field} = :{$paramName}";
                            $bindParams[":{$paramName}"] = $filterVal;
                        } elseif ($mode === 'between' && strpos($filterVal, '|') !== false) {
                            list($d1, $d2) = explode('|', $filterVal, 2);
                            if ($d1 && $d2) {
                                $where .= " AND {$field} BETWEEN :{$paramName}_1 AND :{$paramName}_2";
                                $bindParams[":{$paramName}_1"] = $d1;
                                $bindParams[":{$paramName}_2"] = $d2;
                            }
                        } else {
                            $where .= " AND {$field} LIKE :{$paramName}";
                            $bindParams[":{$paramName}"] = "%{$filterVal}%";
                        }
                    } else {
                        $where .= " AND {$field} LIKE :{$paramName}";
                        $bindParams[":{$paramName}"] = "%{$val}%";
                    }
                }
            }
        }

        $db = $this->getDb();

        // Toplam Kayıt Sayısı
        $stmtTotal = $db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE firma_id = ? AND silinme_tarihi IS NULL");
        $stmtTotal->execute([$firma_id]);
        $recordsTotal = (int) $stmtTotal->fetchColumn();

        // Filtrelenmiş Kayıt Sayısı
        $stmtFiltered = $db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$where}");
        $stmtFiltered->execute($bindParams);
        $recordsFiltered = (int) $stmtFiltered->fetchColumn();

        // Sıralama
        $orderBy = "is_active DESC, baslangic_tarihi DESC, id DESC";
        if (!empty($orders)) {
            $orderColIdx = (int) $orders[0]['column'];
            $orderDir = strtoupper($orders[0]['dir']) === 'ASC' ? 'ASC' : 'DESC';
            if (isset($colMap[$orderColIdx])) {
                $orderBy = "{$colMap[$orderColIdx]} {$orderDir}";
            }
        }

        // Verileri Çek
        $sql = "
            SELECT *,
                DATEDIFF(bitis_tarihi, baslangic_tarihi) + 1 AS toplam_gun,
                DATEDIFF(bitis_tarihi, CURDATE()) AS kalan_gun
            FROM {$this->table}
            WHERE {$where}
            ORDER BY {$orderBy}
            LIMIT {$start}, {$length}
        ";

        $stmtData = $db->prepare($sql);
        $stmtData->execute($bindParams);
        $rows = $stmtData->fetchAll(PDO::FETCH_OBJ);

        $data = [];
        $index = $start + 1;

        foreach ($rows as $row) {
            $encId = Security::encrypt($row->id);
            $rawId = (int)$row->id;
            
            // Tarih biçimlendirme
            $baslangicFormatted = date('d.m.Y', strtotime($row->baslangic_tarihi));
            $bitisFormatted = date('d.m.Y', strtotime($row->bitis_tarihi));

            // Durum rozeti
            if ($row->is_active == 1) {
                $statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 font-size-12 fw-semibold d-inline-flex align-items-center gap-1"><span class="badge-dot bg-success me-1"></span>Aktif Dönem</span>';
            } else {
                $statusBadge = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 font-size-12 fw-semibold">Pasif</span>';
            }

            // Kalan gün / süre bilgisi
            $gunStr = $row->toplam_gun . ' Gün';
            if ($row->is_active == 1) {
                if ($row->kalan_gun > 0) {
                    $gunStr .= ' <small class="text-primary fw-medium">(' . $row->kalan_gun . ' gün kaldı)</small>';
                } elseif ($row->kalan_gun == 0) {
                    $gunStr .= ' <small class="text-warning fw-medium">(Son gün)</small>';
                } else {
                    $gunStr .= ' <small class="text-danger fw-medium">(Süre bitti)</small>';
                }
            }

            // İşlem Butonları (Standard subtle action buttons)
            $actions = '<div class="d-flex align-items-center justify-content-center gap-1 action-btn-group">';
            
            if ($row->is_active != 1) {
                $actions .= '<button type="button" class="btn btn-sm btn-subtle-success table-action-btn btn-set-active" data-id="' . $encId . '" data-raw-id="' . $rawId . '" data-name="' . htmlspecialchars($row->donem_adi, ENT_QUOTES, 'UTF-8') . '" title="Aktif Dönem Yap"><i class="bx bx-check-circle"></i></button>';
            }

            $actions .= '<button type="button" class="btn btn-sm btn-subtle-warning table-action-btn btn-edit-donem" data-id="' . $encId . '" data-raw-id="' . $rawId . '" title="Düzenle"><i class="bx bx-pencil"></i></button>';

            if ($row->is_active != 1) {
                $actions .= '<button type="button" class="btn btn-sm btn-subtle-danger table-action-btn btn-delete-donem" data-id="' . $encId . '" data-raw-id="' . $rawId . '" data-name="' . htmlspecialchars($row->donem_adi, ENT_QUOTES, 'UTF-8') . '" title="Sil"><i class="bx bx-trash"></i></button>';
            }

            $actions .= '</div>';

            $data[] = [
                'sira' => '<div class="text-center font-size-12 fw-medium text-muted">' . $index++ . '</div>',
                'donem_adi' => '<div class="d-flex flex-column"><span class="fw-bold text-dark font-size-13">' . htmlspecialchars($row->donem_adi, ENT_QUOTES, 'UTF-8') . '</span>' . (!empty($row->aciklama) ? '<span class="text-muted font-size-11 text-truncate" style="max-width: 320px;">' . htmlspecialchars($row->aciklama, ENT_QUOTES, 'UTF-8') . '</span>' : '') . '</div>',
                'baslangic_tarihi' => '<div class="text-center"><i class="bx bx-calendar text-muted me-1 font-size-12"></i>' . $baslangicFormatted . '</div>',
                'bitis_tarihi' => '<div class="text-center"><i class="bx bx-calendar text-muted me-1 font-size-12"></i>' . $bitisFormatted . '</div>',
                'toplam_gun' => '<div class="text-center">' . $gunStr . '</div>',
                'durum' => '<div class="text-center">' . $statusBadge . '</div>',
                'actions' => $actions,
                'raw_id' => $rawId,
                'enc_id' => $encId
            ];
        }

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data
        ];
    }

    /**
     * Sayfa üstü 4 adet KPI Özet Kartı verisi
     */
    public function summary(int $firma_id): object
    {
        $db = $this->getDb();

        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as toplam_sayi,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as aktif_sayi,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as pasif_sayi
            FROM {$this->table}
            WHERE firma_id = ? AND silinme_tarihi IS NULL
        ");
        $stmt->execute([$firma_id]);
        $stats = $stmt->fetch(PDO::FETCH_OBJ);

        $activeDonem = $this->getActiveDonem($firma_id);
        $kalanGun = 0;
        $activeName = '-';
        $dateRange = '-';

        if ($activeDonem) {
            $activeName = $activeDonem->donem_adi;
            $kalanGun = (int) (ceil((strtotime($activeDonem->bitis_tarihi) - time()) / 86400));
            $dateRange = date('d.m.Y', strtotime($activeDonem->baslangic_tarihi)) . ' - ' . date('d.m.Y', strtotime($activeDonem->bitis_tarihi));
        }

        return (object)[
            'toplam_donem' => (int)($stats->toplam_sayi ?? 0),
            'aktif_sayi' => (int)($stats->aktif_sayi ?? 0),
            'pasif_sayi' => (int)($stats->pasif_sayi ?? 0),
            'aktif_donem_adi' => $activeName,
            'aktif_donem_tarih' => $dateRange,
            'aktif_kalan_gun' => $kalanGun
        ];
    }
}
