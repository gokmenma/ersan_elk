<?php
namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use App\Helper\Helper;
use PDO;

class EInvoiceModel extends Model
{
    protected $table = 'faturalar';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Yeni Fatura ve Kalemlerini Transaction İçinde Kaydeder
     */
    public function createInvoice(int $firmId, array $header, array $lines, int $userId): ?int
    {
        $this->db->beginTransaction();
        try {
            // ETTN (UUID v4) yoksa üret
            $ettn = !empty($header['ettn']) ? $header['ettn'] : Helper::generateUuid();

            // Alt Toplamları Hesapla
            $satirToplami = 0.0;
            $iskontoToplami = 0.0;
            $kdvMatrahi = 0.0;
            $hesaplananKdv = 0.0;
            $tevkifatTutari = 0.0;
            $odenecekTutar = 0.0;

            foreach ($lines as $line) {
                $miktar = (float)($line['miktar'] ?? 1);
                $birimFiyat = (float)($line['birim_fiyat'] ?? 0);
                $iskontoOrani = (float)($line['iskonto_orani'] ?? 0);
                $kdvOrani = (float)($line['kdv_orani'] ?? 20);
                $tevkifatOrani = (float)($line['tevkifat_orani'] ?? 0);

                $hamTutar = round($miktar * $birimFiyat, 2);
                $iskontoTutari = round($hamTutar * ($iskontoOrani / 100), 2);
                $netMatrah = $hamTutar - $iskontoTutari;
                $kdvTutari = round($netMatrah * ($kdvOrani / 100), 2);
                $tevkifat = round($kdvTutari * ($tevkifatOrani / 100), 2);
                $satirNet = $netMatrah + $kdvTutari - $tevkifat;

                $satirToplami += $hamTutar;
                $iskontoToplami += $iskontoTutari;
                $kdvMatrahi += $netMatrah;
                $hesaplananKdv += $kdvTutari;
                $tevkifatTutari += $tevkifat;
                $odenecekTutar += $satirNet;
            }

            $stmt = $this->db->prepare("
                INSERT INTO faturalar (
                    firm_id, cari_id, yon, belge_turu, fatura_profili, fatura_tipi, ettn, fatura_no,
                    fatura_tarihi, duzenleme_saati, vade_tarihi, alici_vkn_tckn, alici_unvan,
                    alici_vergi_dairesi, alici_adres, alici_il, alici_ilce, alici_ulke,
                    alici_eposta, alici_telefon, alici_posta_kutusu, para_birimi, doviz_kuru,
                    satir_toplami, iskonto_toplami, kdv_matrahi, hesaplanan_kdv, tevkifat_tutari,
                    odenecek_tutar, notlar, siparis_no, siparis_tarihi, irsaliye_no, irsaliye_tarihi,
                    entegrator_durum_kodu, olusturan_user_id, is_active, created_at
                ) VALUES (
                    :firm_id, :cari_id, :yon, :belge_turu, :fatura_profili, :fatura_tipi, :ettn, :fatura_no,
                    :fatura_tarihi, :duzenleme_saati, :vade_tarihi, :alici_vkn_tckn, :alici_unvan,
                    :alici_vergi_dairesi, :alici_adres, :alici_il, :alici_ilce, :alici_ulke,
                    :alici_eposta, :alici_telefon, :alici_posta_kutusu, :para_birimi, :doviz_kuru,
                    :satir_toplami, :iskonto_toplami, :kdv_matrahi, :hesaplanan_kdv, :tevkifat_tutari,
                    :odenecek_tutar, :notlar, :siparis_no, :siparis_tarihi, :irsaliye_no, :irsaliye_tarihi,
                    :entegrator_durum_kodu, :olusturan_user_id, 1, NOW()
                )
            ");

            $stmt->execute([
                'firm_id'               => $firmId,
                'cari_id'               => !empty($header['cari_id']) ? (int)$header['cari_id'] : null,
                'yon'                   => $header['yon'] ?? 'GIDEN',
                'belge_turu'            => $header['belge_turu'] ?? 'EFATURA',
                'fatura_profili'        => $header['fatura_profili'] ?? 'TICARIFATURA',
                'fatura_tipi'           => $header['fatura_tipi'] ?? 'SATIS',
                'ettn'                  => $ettn,
                'fatura_no'             => $header['fatura_no'] ?? null,
                'fatura_tarihi'         => $header['fatura_tarihi'] ?? date('Y-m-d'),
                'duzenleme_saati'       => $header['duzenleme_saati'] ?? date('H:i:s'),
                'vade_tarihi'           => !empty($header['vade_tarihi']) ? $header['vade_tarihi'] : null,
                'alici_vkn_tckn'        => $header['alici_vkn_tckn'] ?? '',
                'alici_unvan'           => $header['alici_unvan'] ?? '',
                'alici_vergi_dairesi'   => $header['alici_vergi_dairesi'] ?? null,
                'alici_adres'           => $header['alici_adres'] ?? null,
                'alici_il'              => $header['alici_il'] ?? null,
                'alici_ilce'            => $header['alici_ilce'] ?? null,
                'alici_ulke'            => $header['alici_ulke'] ?? 'Türkiye',
                'alici_eposta'          => $header['alici_eposta'] ?? null,
                'alici_telefon'         => $header['alici_telefon'] ?? null,
                'alici_posta_kutusu'    => $header['alici_posta_kutusu'] ?? null,
                'para_birimi'           => $header['para_birimi'] ?? 'TRY',
                'doviz_kuru'            => $header['doviz_kuru'] ?? 1.0000,
                'satir_toplami'         => $satirToplami,
                'iskonto_toplami'       => $iskontoToplami,
                'kdv_matrahi'           => $kdvMatrahi,
                'hesaplanan_kdv'        => $hesaplananKdv,
                'tevkifat_tutari'       => $tevkifatTutari,
                'odenecek_tutar'        => $odenecekTutar,
                'notlar'                => $header['notlar'] ?? null,
                'siparis_no'            => $header['siparis_no'] ?? null,
                'siparis_tarihi'        => !empty($header['siparis_tarihi']) ? $header['siparis_tarihi'] : null,
                'irsaliye_no'           => $header['irsaliye_no'] ?? null,
                'irsaliye_tarihi'       => !empty($header['irsaliye_tarihi']) ? $header['irsaliye_tarihi'] : null,
                'entegrator_durum_kodu' => $header['entegrator_durum_kodu'] ?? 'TASLAK',
                'olusturan_user_id'     => $userId
            ]);

            $faturaId = (int)$this->db->lastInsertId();

            // Satırları Ekle
            $lineStmt = $this->db->prepare("
                INSERT INTO fatura_satirlari (
                    fatura_id, sira_no, urun_hizmet_adi, urun_kodu, miktar, birim,
                    birim_fiyat, iskonto_orani, iskonto_tutari, kdv_orani, kdv_tutari,
                    tevkifat_kodu, tevkifat_orani, tevkifat_tutari, istisna_kodu, satir_toplami
                ) VALUES (
                    :fatura_id, :sira_no, :urun_hizmet_adi, :urun_kodu, :miktar, :birim,
                    :birim_fiyat, :iskonto_orani, :iskonto_tutari, :kdv_orani, :kdv_tutari,
                    :tevkifat_kodu, :tevkifat_orani, :tevkifat_tutari, :istisna_kodu, :satir_toplami
                )
            ");

            $siraNo = 1;
            foreach ($lines as $line) {
                $miktar = (float)($line['miktar'] ?? 1);
                $birimFiyat = (float)($line['birim_fiyat'] ?? 0);
                $iskontoOrani = (float)($line['iskonto_orani'] ?? 0);
                $kdvOrani = (float)($line['kdv_orani'] ?? 20);
                $tevkifatOrani = (float)($line['tevkifat_orani'] ?? 0);

                $hamTutar = round($miktar * $birimFiyat, 2);
                $iskontoTutari = round($hamTutar * ($iskontoOrani / 100), 2);
                $netMatrah = $hamTutar - $iskontoTutari;
                $kdvTutari = round($netMatrah * ($kdvOrani / 100), 2);
                $tevkifat = round($kdvTutari * ($tevkifatOrani / 100), 2);
                $satirNet = $netMatrah + $kdvTutari - $tevkifat;

                $lineStmt->execute([
                    'fatura_id'       => $faturaId,
                    'sira_no'         => $siraNo++,
                    'urun_hizmet_adi' => $line['urun_hizmet_adi'] ?? '',
                    'urun_kodu'       => $line['urun_kodu'] ?? null,
                    'miktar'          => $miktar,
                    'birim'           => $line['birim'] ?? 'C62',
                    'birim_fiyat'     => $birimFiyat,
                    'iskonto_orani'   => $iskontoOrani,
                    'iskonto_tutari'  => $iskontoTutari,
                    'kdv_orani'       => $kdvOrani,
                    'kdv_tutari'      => $kdvTutari,
                    'tevkifat_kodu'   => $line['tevkifat_kodu'] ?? null,
                    'tevkifat_orani'  => $tevkifatOrani > 0 ? $tevkifatOrani : null,
                    'tevkifat_tutari' => $tevkifat,
                    'istisna_kodu'    => $line['istisna_kodu'] ?? null,
                    'satir_toplami'   => $satirNet
                ]);
            }

            $this->db->commit();
            return $faturaId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("EInvoiceModel::createInvoice Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Taslak Faturayı Günceller
     */
    public function updateInvoice(int $invoiceId, int $firmId, array $header, array $lines, int $userId): bool
    {
        $this->db->beginTransaction();
        try {
            // Kontrol: Fatura taslak mı?
            $checkStmt = $this->db->prepare("SELECT entegrator_durum_kodu FROM faturalar WHERE id = :id AND firm_id = :firm_id AND deleted_at IS NULL LIMIT 1");
            $checkStmt->execute(['id' => $invoiceId, 'firm_id' => $firmId]);
            $currentStatus = $checkStmt->fetchColumn();

            if (!$currentStatus) {
                $this->db->rollBack();
                return false;
            }

            if ($currentStatus !== 'TASLAK') {
                $this->db->rollBack();
                throw new \Exception("Sadece taslak durumundaki faturalar düzenlenebilir.");
            }

            // Alt Toplamları Hesapla
            $satirToplami = 0.0;
            $iskontoToplami = 0.0;
            $kdvMatrahi = 0.0;
            $hesaplananKdv = 0.0;
            $tevkifatTutari = 0.0;
            $odenecekTutar = 0.0;

            foreach ($lines as $line) {
                $miktar = (float)($line['miktar'] ?? 1);
                $birimFiyat = (float)($line['birim_fiyat'] ?? 0);
                $iskontoOrani = (float)($line['iskonto_orani'] ?? 0);
                $kdvOrani = (float)($line['kdv_orani'] ?? 20);
                $tevkifatOrani = (float)($line['tevkifat_orani'] ?? 0);

                $hamTutar = round($miktar * $birimFiyat, 2);
                $iskontoTutari = round($hamTutar * ($iskontoOrani / 100), 2);
                $netMatrah = $hamTutar - $iskontoTutari;
                $kdvTutari = round($netMatrah * ($kdvOrani / 100), 2);
                $tevkifat = round($kdvTutari * ($tevkifatOrani / 100), 2);
                $satirNet = $netMatrah + $kdvTutari - $tevkifat;

                $satirToplami += $hamTutar;
                $iskontoToplami += $iskontoTutari;
                $kdvMatrahi += $netMatrah;
                $hesaplananKdv += $kdvTutari;
                $tevkifatTutari += $tevkifat;
                $odenecekTutar += $satirNet;
            }

            $stmt = $this->db->prepare("
                UPDATE faturalar SET
                    cari_id = :cari_id,
                    belge_turu = :belge_turu,
                    fatura_profili = :fatura_profili,
                    fatura_tipi = :fatura_tipi,
                    fatura_tarihi = :fatura_tarihi,
                    duzenleme_saati = :duzenleme_saati,
                    vade_tarihi = :vade_tarihi,
                    alici_vkn_tckn = :alici_vkn_tckn,
                    alici_unvan = :alici_unvan,
                    alici_vergi_dairesi = :alici_vergi_dairesi,
                    alici_adres = :alici_adres,
                    alici_il = :alici_il,
                    alici_ilce = :alici_ilce,
                    alici_ulke = :alici_ulke,
                    alici_eposta = :alici_eposta,
                    alici_telefon = :alici_telefon,
                    alici_posta_kutusu = :alici_posta_kutusu,
                    para_birimi = :para_birimi,
                    doviz_kuru = :doviz_kuru,
                    satir_toplami = :satir_toplami,
                    iskonto_toplami = :iskonto_toplami,
                    kdv_matrahi = :kdv_matrahi,
                    hesaplanan_kdv = :hesaplanan_kdv,
                    tevkifat_tutari = :tevkifat_tutari,
                    odenecek_tutar = :odenecek_tutar,
                    notlar = :notlar,
                    siparis_no = :siparis_no,
                    siparis_tarihi = :siparis_tarihi,
                    irsaliye_no = :irsaliye_no,
                    irsaliye_tarihi = :irsaliye_tarihi,
                    updated_at = NOW()
                WHERE id = :id AND firm_id = :firm_id
            ");

            $stmt->execute([
                'id'                    => $invoiceId,
                'firm_id'               => $firmId,
                'cari_id'               => !empty($header['cari_id']) ? (int)$header['cari_id'] : null,
                'belge_turu'            => $header['belge_turu'] ?? 'EFATURA',
                'fatura_profili'        => $header['fatura_profili'] ?? 'TICARIFATURA',
                'fatura_tipi'           => $header['fatura_tipi'] ?? 'SATIS',
                'fatura_tarihi'         => $header['fatura_tarihi'] ?? date('Y-m-d'),
                'duzenleme_saati'       => $header['duzenleme_saati'] ?? date('H:i:s'),
                'vade_tarihi'           => !empty($header['vade_tarihi']) ? $header['vade_tarihi'] : null,
                'alici_vkn_tckn'        => $header['alici_vkn_tckn'] ?? '',
                'alici_unvan'           => $header['alici_unvan'] ?? '',
                'alici_vergi_dairesi'   => $header['alici_vergi_dairesi'] ?? null,
                'alici_adres'           => $header['alici_adres'] ?? null,
                'alici_il'              => $header['alici_il'] ?? null,
                'alici_ilce'            => $header['alici_ilce'] ?? null,
                'alici_ulke'            => $header['alici_ulke'] ?? 'Türkiye',
                'alici_eposta'          => $header['alici_eposta'] ?? null,
                'alici_telefon'         => $header['alici_telefon'] ?? null,
                'alici_posta_kutusu'    => $header['alici_posta_kutusu'] ?? null,
                'para_birimi'           => $header['para_birimi'] ?? 'TRY',
                'doviz_kuru'            => $header['doviz_kuru'] ?? 1.0000,
                'satir_toplami'         => $satirToplami,
                'iskonto_toplami'       => $iskontoToplami,
                'kdv_matrahi'           => $kdvMatrahi,
                'hesaplanan_kdv'        => $hesaplananKdv,
                'tevkifat_tutari'       => $tevkifatTutari,
                'odenecek_tutar'        => $odenecekTutar,
                'notlar'                => $header['notlar'] ?? null,
                'siparis_no'            => $header['siparis_no'] ?? null,
                'siparis_tarihi'        => !empty($header['siparis_tarihi']) ? $header['siparis_tarihi'] : null,
                'irsaliye_no'           => $header['irsaliye_no'] ?? null,
                'irsaliye_tarihi'       => !empty($header['irsaliye_tarihi']) ? $header['irsaliye_tarihi'] : null
            ]);

            // Eski Satırları Sil
            $delStmt = $this->db->prepare("DELETE FROM fatura_satirlari WHERE fatura_id = :fatura_id");
            $delStmt->execute(['fatura_id' => $invoiceId]);

            // Yeni Satırları Ekle
            $lineStmt = $this->db->prepare("
                INSERT INTO fatura_satirlari (
                    fatura_id, sira_no, urun_hizmet_adi, urun_kodu, miktar, birim,
                    birim_fiyat, iskonto_orani, iskonto_tutari, kdv_orani, kdv_tutari,
                    tevkifat_kodu, tevkifat_orani, tevkifat_tutari, istisna_kodu, satir_toplami
                ) VALUES (
                    :fatura_id, :sira_no, :urun_hizmet_adi, :urun_kodu, :miktar, :birim,
                    :birim_fiyat, :iskonto_orani, :iskonto_tutari, :kdv_orani, :kdv_tutari,
                    :tevkifat_kodu, :tevkifat_orani, :tevkifat_tutari, :istisna_kodu, :satir_toplami
                )
            ");

            $siraNo = 1;
            foreach ($lines as $line) {
                $miktar = (float)($line['miktar'] ?? 1);
                $birimFiyat = (float)($line['birim_fiyat'] ?? 0);
                $iskontoOrani = (float)($line['iskonto_orani'] ?? 0);
                $kdvOrani = (float)($line['kdv_orani'] ?? 20);
                $tevkifatOrani = (float)($line['tevkifat_orani'] ?? 0);

                $hamTutar = round($miktar * $birimFiyat, 2);
                $iskontoTutari = round($hamTutar * ($iskontoOrani / 100), 2);
                $netMatrah = $hamTutar - $iskontoTutari;
                $kdvTutari = round($netMatrah * ($kdvOrani / 100), 2);
                $tevkifat = round($kdvTutari * ($tevkifatOrani / 100), 2);
                $satirNet = $netMatrah + $kdvTutari - $tevkifat;

                $lineStmt->execute([
                    'fatura_id'       => $invoiceId,
                    'sira_no'         => $siraNo++,
                    'urun_hizmet_adi' => $line['urun_hizmet_adi'] ?? '',
                    'urun_kodu'       => $line['urun_kodu'] ?? null,
                    'miktar'          => $miktar,
                    'birim'           => $line['birim'] ?? 'C62',
                    'birim_fiyat'     => $birimFiyat,
                    'iskonto_orani'   => $iskontoOrani,
                    'iskonto_tutari'  => $iskontoTutari,
                    'kdv_orani'       => $kdvOrani,
                    'kdv_tutari'      => $kdvTutari,
                    'tevkifat_kodu'   => $line['tevkifat_kodu'] ?? null,
                    'tevkifat_orani'  => $tevkifatOrani > 0 ? $tevkifatOrani : null,
                    'tevkifat_tutari' => $tevkifat,
                    'istisna_kodu'    => $line['istisna_kodu'] ?? null,
                    'satir_toplami'   => $satirNet
                ]);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("EInvoiceModel::updateInvoice Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fatura ve Satır Detaylarını Getirir
     */
    public function getInvoiceById(int $invoiceId, int $firmId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT f.*, c.CariAdi, c.Telefon as cari_telefon, c.Email as cari_email
                FROM faturalar f
                LEFT JOIN cari c ON c.id = f.cari_id
                WHERE f.id = :id AND f.firm_id = :firm_id AND f.deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute(['id' => $invoiceId, 'firm_id' => $firmId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                return null;
            }

            $linesStmt = $this->db->prepare("
                SELECT * FROM fatura_satirlari 
                WHERE fatura_id = :fatura_id 
                ORDER BY sira_no ASC
            ");
            $linesStmt->execute(['fatura_id' => $invoiceId]);
            $invoice['satirlar'] = $linesStmt->fetchAll(PDO::FETCH_ASSOC);

            return $invoice;
        } catch (\PDOException $e) {
            error_log("EInvoiceModel::getInvoiceById Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ETTN (UUID) İle Faturayı Getirir
     */
    public function getInvoiceByEttn(string $ettn, int $firmId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM faturalar WHERE ettn = :ettn AND firm_id = :firm_id AND deleted_at IS NULL LIMIT 1");
            $stmt->execute(['ettn' => $ettn, 'firm_id' => $firmId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $this->getInvoiceById((int)$row['id'], $firmId);
            }
            return null;
        } catch (\PDOException $e) {
            error_log("EInvoiceModel::getInvoiceByEttn Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fatura Durumunu Günceller
     */
    public function updateInvoiceStatus(int $invoiceId, int $firmId, array $data): bool
    {
        try {
            $fields = [];
            $params = ['id' => $invoiceId, 'firm_id' => $firmId];

            $allowed = [
                'fatura_no', 'entegrator_durum_kodu', 'gib_durum_kodu', 'gib_durum_aciklamasi',
                'edm_referans_no', 'ticari_yanit', 'ubl_xml_path', 'pdf_path'
            ];

            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }

            if (empty($fields)) {
                return false;
            }

            $fieldsSql = implode(', ', $fields);
            $stmt = $this->db->prepare("UPDATE faturalar SET $fieldsSql, updated_at = NOW() WHERE id = :id AND firm_id = :firm_id");
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log("EInvoiceModel::updateInvoiceStatus Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * DataTables AJAX Sunucu Taraflı Liste Sorgusu
     */
    public function ajaxList(array $params, int $firmId, string $yon = 'GIDEN'): array
    {
        $draw = (int)($params['draw'] ?? 1);
        $start = (int)($params['start'] ?? 0);
        $length = (int)($params['length'] ?? 10);
        $search = $params['search']['value'] ?? '';

        $where = "f.firm_id = :firm_id AND f.yon = :yon AND f.deleted_at IS NULL";
        $bind = ['firm_id' => $firmId, 'yon' => $yon];

        if (!empty($params['durum_filtre']) && $params['durum_filtre'] !== 'all') {
            $where .= " AND f.entegrator_durum_kodu = :durum_filtre";
            $bind['durum_filtre'] = $params['durum_filtre'];
        }

        if (!empty($params['belge_turu_filtre']) && $params['belge_turu_filtre'] !== 'all') {
            $where .= " AND f.belge_turu = :belge_turu_filtre";
            $bind['belge_turu_filtre'] = $params['belge_turu_filtre'];
        }

        $colsMap = [
            2 => 'f.fatura_no',
            3 => 'f.fatura_tarihi',
            4 => 'f.alici_unvan',
            5 => 'f.alici_vkn_tckn',
            6 => 'f.belge_turu',
            7 => 'f.fatura_profili',
            8 => 'f.odenecek_tutar',
            9 => 'f.entegrator_durum_kodu'
        ];

        // Header column filters
        if (!empty($params['columns']) && is_array($params['columns'])) {
            foreach ($params['columns'] as $idx => $colData) {
                $colSearch = trim($colData['search']['value'] ?? '');
                if ($colSearch !== '' && isset($colsMap[$idx])) {
                    $colField = $colsMap[$idx];
                    $paramKey = 'col_f_' . $idx;
                    if ($colField === 'f.fatura_tarihi') {
                        $where .= " AND $colField = :$paramKey";
                        $bind[$paramKey] = date('Y-m-d', strtotime($colSearch));
                    } elseif ($colField === 'f.belge_turu' || $colField === 'f.entegrator_durum_kodu') {
                        $where .= " AND $colField = :$paramKey";
                        $bind[$paramKey] = $colSearch;
                    } else {
                        $where .= " AND $colField LIKE :$paramKey";
                        $bind[$paramKey] = "%$colSearch%";
                    }
                }
            }
        }

        if (!empty($search)) {
            $where .= " AND (f.fatura_no LIKE :s OR f.alici_unvan LIKE :s OR f.alici_vkn_tckn LIKE :s OR f.ettn LIKE :s)";
            $bind['s'] = "%$search%";
        }

        // Toplam Kayıt Sayısı
        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM faturalar f WHERE f.firm_id = :firm_id AND f.yon = :yon AND f.deleted_at IS NULL");
        $totalStmt->execute(['firm_id' => $firmId, 'yon' => $yon]);
        $recordsTotal = (int)$totalStmt->fetchColumn();

        // Filtrelenmiş Kayıt Sayısı
        $filteredStmt = $this->db->prepare("SELECT COUNT(*) FROM faturalar f WHERE $where");
        $filteredStmt->execute($bind);
        $recordsFiltered = (int)$filteredStmt->fetchColumn();

        // Veri Listesi Sıralama
        $orderCol = 'f.fatura_tarihi';
        $orderDir = 'DESC';
        if (!empty($params['order'][0]['column'])) {
            $colIdx = (int)$params['order'][0]['column'];
            if (isset($colsMap[$colIdx])) {
                $orderCol = $colsMap[$colIdx];
                $orderDir = strtoupper($params['order'][0]['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            }
        }

        $sql = "
            SELECT f.*
            FROM faturalar f
            WHERE $where
            ORDER BY $orderCol $orderDir, f.id DESC
            LIMIT $start, $length
        ";

        $dataStmt = $this->db->prepare($sql);
        $dataStmt->execute($bind);
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        foreach ($rows as $row) {
            $encryptedId = Security::encrypt((string)$row['id']);
            $data[] = [
                'id'                    => $row['id'],
                'encrypted_id'          => $encryptedId,
                'fatura_no'             => !empty($row['fatura_no']) ? $row['fatura_no'] : 'Taslak',
                'ettn'                  => $row['ettn'],
                'fatura_tarihi'         => date('d.m.Y', strtotime($row['fatura_tarihi'])),
                'alici_unvan'           => htmlspecialchars($row['alici_unvan'], ENT_QUOTES, 'UTF-8'),
                'alici_vkn_tckn'        => htmlspecialchars($row['alici_vkn_tckn'], ENT_QUOTES, 'UTF-8'),
                'belge_turu'            => $row['belge_turu'],
                'fatura_profili'        => $row['fatura_profili'],
                'fatura_tipi'           => $row['fatura_tipi'],
                'odenecek_tutar'        => number_format($row['odenecek_tutar'], 2, ',', '.') . ' ' . $row['para_birimi'],
                'entegrator_durum_kodu' => $row['entegrator_durum_kodu'],
                'gib_durum_kodu'        => $row['gib_durum_kodu'],
                'gib_durum_aciklamasi'  => $row['gib_durum_aciklamasi'],
                'pdf_path'              => $row['pdf_path'],
                'ubl_xml_path'          => $row['ubl_xml_path']
            ];
        }

        return [
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data
        ];
    }

    /**
     * Dashboard ve Özet Kartları İstatistikleri
     */
    public function getSummaryStats(int $firmId, string $yon = 'GIDEN'): array
    {
        try {
            $currentMonthStart = date('Y-m-01');
            $currentMonthEnd = date('Y-m-t');

            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as toplam_adet,
                    COALESCE(SUM(odenecek_tutar), 0) as toplam_tutar,
                    COUNT(CASE WHEN belge_turu = 'EFATURA' THEN 1 END) as efatura_adet,
                    COUNT(CASE WHEN belge_turu = 'EARSIV' THEN 1 END) as earsiv_adet,
                    
                    COUNT(CASE WHEN entegrator_durum_kodu = 'ONAYLANDI' THEN 1 END) as onaylanan_adet,
                    COALESCE(SUM(CASE WHEN entegrator_durum_kodu = 'ONAYLANDI' THEN odenecek_tutar ELSE 0 END), 0) as onaylanan_tutar,
                    
                    COUNT(CASE WHEN entegrator_durum_kodu IN ('TASLAK', 'KUYRUKTA', 'GONDERILDI') THEN 1 END) as bekleyen_adet,
                    COALESCE(SUM(CASE WHEN entegrator_durum_kodu IN ('TASLAK', 'KUYRUKTA', 'GONDERILDI') THEN odenecek_tutar ELSE 0 END), 0) as bekleyen_tutar,
                    
                    COUNT(CASE WHEN entegrator_durum_kodu = 'HATALI' THEN 1 END) as hatali_adet,
                    COUNT(CASE WHEN entegrator_durum_kodu = 'IPTAL' THEN 1 END) as iptal_adet,

                    COUNT(CASE WHEN fatura_tarihi BETWEEN :start AND :end THEN 1 END) as bu_ay_adet,
                    COALESCE(SUM(CASE WHEN fatura_tarihi BETWEEN :start AND :end THEN odenecek_tutar ELSE 0 END), 0) as bu_ay_tutar
                FROM faturalar 
                WHERE firm_id = :firm_id 
                  AND yon = :yon 
                  AND deleted_at IS NULL
            ");
            $stmt->execute([
                'firm_id' => $firmId,
                'yon'     => $yon,
                'start'   => $currentMonthStart,
                'end'     => $currentMonthEnd
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log("EInvoiceModel::getSummaryStats Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Faturayı İptal Eder
     */
    public function cancelInvoice(int $invoiceId, int $firmId, string $reason = ''): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE faturalar SET 
                    entegrator_durum_kodu = 'IPTAL',
                    gib_durum_aciklamasi = CONCAT(COALESCE(gib_durum_aciklamasi, ''), ' [İPTAL: ', :reason, ']'),
                    updated_at = NOW()
                WHERE id = :id AND firm_id = :firm_id
            ");
            return $stmt->execute([
                'id'      => $invoiceId,
                'firm_id' => $firmId,
                'reason'  => $reason
            ]);
        } catch (\PDOException $e) {
            error_log("EInvoiceModel::cancelInvoice Error: " . $e->getMessage());
            return false;
        }
    }
}
