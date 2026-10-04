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
            $satirToplami = '0.00';
            $iskontoToplami = '0.00';
            $kdvMatrahi = '0.00';
            $hesaplananKdv = '0.00';
            $tevkifatTutari = '0.00';
            $odenecekTutar = '0.00';

            foreach ($lines as $line) {
                $miktar = (float)($line['miktar'] ?? 1);
                $birimFiyat = (float)($line['birim_fiyat'] ?? 0);
                $iskontoOrani = (float)($line['iskonto_orani'] ?? 0);
                $kdvOrani = (float)($line['kdv_orani'] ?? 20);
                $tevkifatOrani = (float)($line['tevkifat_orani'] ?? 0);

                $calculated = (new \App\Service\InvoiceCalculationService())->calculate([$line]);
                $line = $calculated['lines'][0];
                $hamTutar = $calculated['header']['satir_toplami'];
                $iskontoTutari = $line['iskonto_tutari'];
                $netMatrah = $calculated['header']['kdv_matrahi'];
                $kdvTutari = $line['kdv_tutari'];
                $tevkifat = $line['tevkifat_tutari'];
                $satirNet = $line['satir_toplami'];

                $satirToplami = bcadd($satirToplami, $hamTutar, 2);
                $iskontoToplami = bcadd($iskontoToplami, $iskontoTutari, 2);
                $kdvMatrahi = bcadd($kdvMatrahi, $netMatrah, 2);
                $hesaplananKdv = bcadd($hesaplananKdv, $kdvTutari, 2);
                $tevkifatTutari = bcadd($tevkifatTutari, $tevkifat, 2);
                $odenecekTutar = bcadd($odenecekTutar, $satirNet, 2);
            }

            $stmt = $this->db->prepare("
                INSERT INTO faturalar (
                    firm_id, cari_id, yon, belge_turu, fatura_profili, fatura_tipi, ettn, fatura_no,
                    fatura_tarihi, duzenleme_saati, vade_tarihi, alici_vkn_tckn, alici_unvan,
                    alici_vergi_dairesi, alici_adres, alici_il, alici_ilce, alici_ulke,
                    alici_eposta, alici_telefon, alici_posta_kutusu, para_birimi, doviz_kuru,
                    satir_toplami, iskonto_toplami, kdv_matrahi, hesaplanan_kdv, tevkifat_tutari,
                    odenecek_tutar, notlar, iade_fatura_no, iade_fatura_tarihi, siparis_no, siparis_tarihi, irsaliye_no, irsaliye_tarihi,
                    entegrator_durum_kodu, olusturan_user_id, is_active, created_at
                ) VALUES (
                    :firm_id, :cari_id, :yon, :belge_turu, :fatura_profili, :fatura_tipi, :ettn, :fatura_no,
                    :fatura_tarihi, :duzenleme_saati, :vade_tarihi, :alici_vkn_tckn, :alici_unvan,
                    :alici_vergi_dairesi, :alici_adres, :alici_il, :alici_ilce, :alici_ulke,
                    :alici_eposta, :alici_telefon, :alici_posta_kutusu, :para_birimi, :doviz_kuru,
                    :satir_toplami, :iskonto_toplami, :kdv_matrahi, :hesaplanan_kdv, :tevkifat_tutari,
                    :odenecek_tutar, :notlar, :iade_fatura_no, :iade_fatura_tarihi, :siparis_no, :siparis_tarihi, :irsaliye_no, :irsaliye_tarihi,
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
                'iade_fatura_no' => $header['iade_fatura_no'] ?? null,
                'iade_fatura_tarihi' => $header['iade_fatura_tarihi'] ?? null,
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
                    tevkifat_kodu, tevkifat_orani, tevkifat_tutari, istisna_kodu, istisna_aciklama, satir_toplami
                ) VALUES (
                    :fatura_id, :sira_no, :urun_hizmet_adi, :urun_kodu, :miktar, :birim,
                    :birim_fiyat, :iskonto_orani, :iskonto_tutari, :kdv_orani, :kdv_tutari,
                    :tevkifat_kodu, :tevkifat_orani, :tevkifat_tutari, :istisna_kodu, :istisna_aciklama, :satir_toplami
                )
            ");

            $siraNo = 1;
            foreach ($lines as $line) {
                $miktar = (float)($line['miktar'] ?? 1);
                $birimFiyat = (float)($line['birim_fiyat'] ?? 0);
                $iskontoOrani = (float)($line['iskonto_orani'] ?? 0);
                $kdvOrani = (float)($line['kdv_orani'] ?? 20);
                $tevkifatOrani = (float)($line['tevkifat_orani'] ?? 0);

                $calculated = (new \App\Service\InvoiceCalculationService())->calculate([$line]);
                $line = $calculated['lines'][0];
                $hamTutar = $calculated['header']['satir_toplami'];
                $iskontoTutari = $line['iskonto_tutari'];
                $netMatrah = $calculated['header']['kdv_matrahi'];
                $kdvTutari = $line['kdv_tutari'];
                $tevkifat = $line['tevkifat_tutari'];
                $satirNet = $line['satir_toplami'];

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
                    'istisna_aciklama' => $line['istisna_aciklama'] ?? null,
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
            $checkStmt = $this->db->prepare("SELECT entegrator_durum_kodu FROM faturalar WHERE id = :id AND firm_id = :firm_id AND deleted_at IS NULL AND yon = 'GIDEN' AND kaynak_xml IS NULL AND islem_belirsiz IS NULL LIMIT 1 FOR UPDATE");
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
            $satirToplami = '0.00';
            $iskontoToplami = '0.00';
            $kdvMatrahi = '0.00';
            $hesaplananKdv = '0.00';
            $tevkifatTutari = '0.00';
            $odenecekTutar = '0.00';

            foreach ($lines as $line) {
                $miktar = (float)($line['miktar'] ?? 1);
                $birimFiyat = (float)($line['birim_fiyat'] ?? 0);
                $iskontoOrani = (float)($line['iskonto_orani'] ?? 0);
                $kdvOrani = (float)($line['kdv_orani'] ?? 20);
                $tevkifatOrani = (float)($line['tevkifat_orani'] ?? 0);

                $calculated = (new \App\Service\InvoiceCalculationService())->calculate([$line]);
                $line = $calculated['lines'][0];
                $hamTutar = $calculated['header']['satir_toplami'];
                $iskontoTutari = $line['iskonto_tutari'];
                $netMatrah = $calculated['header']['kdv_matrahi'];
                $kdvTutari = $line['kdv_tutari'];
                $tevkifat = $line['tevkifat_tutari'];
                $satirNet = $line['satir_toplami'];

                $satirToplami = bcadd($satirToplami, $hamTutar, 2);
                $iskontoToplami = bcadd($iskontoToplami, $iskontoTutari, 2);
                $kdvMatrahi = bcadd($kdvMatrahi, $netMatrah, 2);
                $hesaplananKdv = bcadd($hesaplananKdv, $kdvTutari, 2);
                $tevkifatTutari = bcadd($tevkifatTutari, $tevkifat, 2);
                $odenecekTutar = bcadd($odenecekTutar, $satirNet, 2);
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
                    notlar = :notlar, iade_fatura_no = :iade_fatura_no, iade_fatura_tarihi = :iade_fatura_tarihi,
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
                'iade_fatura_no' => $header['iade_fatura_no'] ?? null,
                'iade_fatura_tarihi' => $header['iade_fatura_tarihi'] ?? null,
                'siparis_no'            => $header['siparis_no'] ?? null,
                'siparis_tarihi'        => !empty($header['siparis_tarihi']) ? $header['siparis_tarihi'] : null,
                'irsaliye_no'           => $header['irsaliye_no'] ?? null,
                'irsaliye_tarihi'       => !empty($header['irsaliye_tarihi']) ? $header['irsaliye_tarihi'] : null
            ]);

            // Eski Satırları Sil
            $delStmt = $this->db->prepare("UPDATE fatura_satirlari SET deleted_at = NOW(), is_active = 0 WHERE fatura_id = :fatura_id AND deleted_at IS NULL");
            $delStmt->execute(['fatura_id' => $invoiceId]);

            // Yeni Satırları Ekle
            $lineStmt = $this->db->prepare("
                INSERT INTO fatura_satirlari (
                    fatura_id, sira_no, urun_hizmet_adi, urun_kodu, miktar, birim,
                    birim_fiyat, iskonto_orani, iskonto_tutari, kdv_orani, kdv_tutari,
                    tevkifat_kodu, tevkifat_orani, tevkifat_tutari, istisna_kodu, istisna_aciklama, satir_toplami
                ) VALUES (
                    :fatura_id, :sira_no, :urun_hizmet_adi, :urun_kodu, :miktar, :birim,
                    :birim_fiyat, :iskonto_orani, :iskonto_tutari, :kdv_orani, :kdv_tutari,
                    :tevkifat_kodu, :tevkifat_orani, :tevkifat_tutari, :istisna_kodu, :istisna_aciklama, :satir_toplami
                )
            ");

            $siraNo = 1;
            foreach ($lines as $line) {
                $miktar = (float)($line['miktar'] ?? 1);
                $birimFiyat = (float)($line['birim_fiyat'] ?? 0);
                $iskontoOrani = (float)($line['iskonto_orani'] ?? 0);
                $kdvOrani = (float)($line['kdv_orani'] ?? 20);
                $tevkifatOrani = (float)($line['tevkifat_orani'] ?? 0);

                $calculated = (new \App\Service\InvoiceCalculationService())->calculate([$line]);
                $line = $calculated['lines'][0];
                $hamTutar = $calculated['header']['satir_toplami'];
                $iskontoTutari = $line['iskonto_tutari'];
                $netMatrah = $calculated['header']['kdv_matrahi'];
                $kdvTutari = $line['kdv_tutari'];
                $tevkifat = $line['tevkifat_tutari'];
                $satirNet = $line['satir_toplami'];

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
                    'istisna_aciklama' => $line['istisna_aciklama'] ?? null,
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
                WHERE fatura_id = :fatura_id AND deleted_at IS NULL AND is_active = 1
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
                'edm_referans_no', 'ticari_yanit', 'ubl_xml_path', 'pdf_path',
                'alici_unvan', 'alici_vkn_tckn', 'fatura_tarihi', 'odenecek_tutar',
                'satir_toplami', 'kdv_matrahi', 'hesaplanan_kdv', 'tevkifat_tutari',
                'fatura_profili', 'fatura_tipi', 'belge_turu', 'edm_durum', 'zarf_id',
                'earsiv_rapor_durum', 'earsiv_rapor_aciklama', 'earsiv_iptal_rapor_durum', 'earsiv_iptal_rapor_aciklama', 'islem_belirsiz'
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
            $stmt = $this->db->prepare("UPDATE faturalar SET $fieldsSql, updated_at = NOW() WHERE id = :id AND firm_id = :firm_id AND deleted_at IS NULL");
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log("EInvoiceModel::updateInvoiceStatus Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kolon Filtreleri İçin Tablodaki Tüm Benzersiz Değerleri Döndürür
     */
    public function getUniqueValues(string $column, int $firmId, string $yon = 'GIDEN', string $listType = 'giden'): array
    {
        $columnMap = [
            'belge_turu'            => 'f.belge_turu',
            'fatura_profili'        => 'f.fatura_profili',
            'senaryo'               => 'f.fatura_profili',
            'fatura_tipi'           => 'f.fatura_tipi',
            'durum'                 => ($listType === 'gelen') ? 'f.ticari_yanit' : 'f.entegrator_durum_kodu',
            'entegrator_durum_kodu' => 'f.entegrator_durum_kodu',
            'ticari_yanit'          => 'f.ticari_yanit',
            'alici_unvan'           => 'f.alici_unvan',
            'gonderici_unvan'       => 'f.alici_unvan',
            'para_birimi'           => 'f.para_birimi',
            'edm_durum'             => 'f.edm_durum'
        ];

        $dbCol = $columnMap[$column] ?? null;
        if (!$dbCol) {
            $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
            if (in_array($safeCol, ['belge_turu', 'fatura_profili', 'fatura_tipi', 'entegrator_durum_kodu', 'alici_unvan', 'para_birimi', 'ticari_yanit', 'edm_durum'], true)) {
                $dbCol = "f.$safeCol";
            }
        }

        if (!$dbCol) {
            return [];
        }

        $where = "f.firm_id = :firm_id AND f.deleted_at IS NULL AND $dbCol IS NOT NULL AND $dbCol <> ''";
        $bind = ['firm_id' => $firmId];

        if ($listType === 'taslak') {
            $where .= " AND f.yon = 'GIDEN' AND f.entegrator_durum_kodu = 'TASLAK'";
        } elseif ($listType === 'gelen') {
            $where .= " AND f.yon = 'GELEN'";
        } else {
            $where .= " AND f.yon = 'GIDEN' AND f.entegrator_durum_kodu <> 'TASLAK'";
        }

        try {
            $stmt = $this->db->prepare("SELECT DISTINCT $dbCol as val FROM faturalar f WHERE $where ORDER BY val ASC");
            $stmt->execute($bind);
            $rawVals = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $formatted = [];
            foreach ($rawVals as $v) {
                if ($v === null || $v === '') continue;
                $formatted[] = (string)$v;
            }

            return $formatted;
        } catch (\PDOException $e) {
            error_log("EInvoiceModel::getUniqueValues Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * DataTables AJAX Sunucu Taraflı Liste Sorgusu
     */
    public function ajaxList(array $params, int $firmId, string $yon = 'GIDEN', string $listType = 'giden'): array
    {
        $draw = (int)($params['draw'] ?? 1);
        $start = max(0, (int)($params['start'] ?? 0));
        $length = (int)($params['length'] ?? 10);
        $search = $params['search']['value'] ?? '';

        $where = "f.firm_id = :firm_id AND f.deleted_at IS NULL";
        $bind = ['firm_id' => $firmId];

        if ($listType === 'taslak') {
            $where .= " AND f.yon = 'GIDEN' AND f.entegrator_durum_kodu = 'TASLAK'";
        } elseif ($listType === 'gelen') {
            $where .= " AND f.yon = 'GELEN'";
        } else {
            // Gönderilmiş ve süreçteki faturalar taslak listesine dahil edilmez.
            $where .= " AND f.yon = 'GIDEN' AND f.entegrator_durum_kodu <> 'TASLAK'";
        }

        if (!empty($params['durum_filtre']) && $params['durum_filtre'] !== 'all') {
            if ($listType === 'giden' && $params['durum_filtre'] === 'BEKLEYEN_ILETILEN') {
                $where .= " AND f.entegrator_durum_kodu IN (:pending_queue, :pending_sent, :pending_wait)";
                $bind += ['pending_queue' => 'KUYRUKTA', 'pending_sent' => 'GONDERILDI', 'pending_wait' => 'BEKLIYOR'];
            } else {
                $where .= " AND f.entegrator_durum_kodu = :durum_filtre";
                $bind['durum_filtre'] = $params['durum_filtre'];
            }
        }

        if (!empty($params['belge_turu_filtre']) && $params['belge_turu_filtre'] !== 'all') {
            $where .= " AND f.belge_turu = :belge_turu_filtre";
            $bind['belge_turu_filtre'] = $params['belge_turu_filtre'];
        }

        // Genel Tarih Aralığı Filtresi (Varsayılan: İçinde Bulunulan Ay)
        if (!empty($params['baslangic_tarihi'])) {
            $where .= " AND f.fatura_tarihi >= :range_start_date";
            $bind['range_start_date'] = date('Y-m-d', strtotime($params['baslangic_tarihi']));
        }
        if (!empty($params['bitis_tarihi'])) {
            $where .= " AND f.fatura_tarihi <= :range_end_date";
            $bind['range_end_date'] = date('Y-m-d', strtotime($params['bitis_tarihi']));
        }

        if ($listType === 'gelen') {
            $colsMap = [
                2 => 'f.fatura_no',
                3 => 'f.fatura_tarihi',
                4 => 'f.alici_unvan',
                5 => 'f.alici_vkn_tckn',
                6 => 'f.belge_turu',
                7 => 'f.fatura_profili',
                8 => 'f.odenecek_tutar',
                9 => 'f.ticari_yanit'
            ];
        } else {
            $colsMap = [
                2 => 'f.fatura_no',
                3 => 'f.fatura_tarihi',
                4 => 'f.alici_unvan',
                5 => 'f.alici_vkn_tckn',
                6 => 'f.belge_turu',
                7 => 'f.fatura_profili',
                8 => 'f.odenecek_tutar',
                10 => 't.toplam_tahsilat',
                11 => 'f.entegrator_durum_kodu'
            ];
        }

        // Header column filters (datatable-filters.js gelişmiş filtre desteği)
        if (!empty($params['columns']) && is_array($params['columns'])) {
            foreach ($params['columns'] as $idx => $colData) {
                $rawVal = trim($colData['search']['value'] ?? '');
                if ($rawVal === '' || !isset($colsMap[$idx])) {
                    continue;
                }

                $field = $colsMap[$idx];
                $paramKey = 'col_f_' . $idx;

                if (strpos($rawVal, ':') !== false) {
                    list($mode, $filterVal) = explode(':', $rawVal, 2);
                    $vals = explode('|', $filterVal);
                    $primaryVal = trim($vals[0] ?? '');

                    // Sayısal alanlar için temizlik
                    if ($field === 'f.odenecek_tutar') {
                        if (!in_array($mode, ['null', 'not_null'])) {
                            $primaryVal = \App\Helper\Helper::formattedMoneyToNumber($primaryVal);
                        }
                    }

                    switch ($mode) {
                        case 'multi':
                            if (!empty($vals)) {
                                $multiConditions = [];
                                foreach ($vals as $vIdx => $v) {
                                    $v = trim($v);
                                    if ($v === '') continue;
                                    $vParam = "{$paramKey}_m_{$vIdx}";
                                    $multiConditions[] = "$field LIKE :$vParam";
                                    $bind[$vParam] = "%$v%";
                                }
                                if (!empty($multiConditions)) {
                                    $where .= " AND (" . implode(" OR ", $multiConditions) . ")";
                                }
                            }
                            break;
                        case 'contains':
                            $where .= " AND $field LIKE :$paramKey";
                            $bind[$paramKey] = "%$primaryVal%";
                            break;
                        case 'not_contains':
                            $where .= " AND $field NOT LIKE :$paramKey";
                            $bind[$paramKey] = "%$primaryVal%";
                            break;
                        case 'equals':
                            if ($field === 'f.fatura_tarihi') {
                                $where .= " AND $field = :$paramKey";
                                $bind[$paramKey] = date('Y-m-d', strtotime($primaryVal));
                            } else {
                                $where .= " AND $field = :$paramKey";
                                $bind[$paramKey] = $primaryVal;
                            }
                            break;
                        case 'not_equals':
                            if ($field === 'f.fatura_tarihi') {
                                $where .= " AND $field != :$paramKey";
                                $bind[$paramKey] = date('Y-m-d', strtotime($primaryVal));
                            } else {
                                $where .= " AND $field != :$paramKey";
                                $bind[$paramKey] = $primaryVal;
                            }
                            break;
                        case 'starts_with':
                            $where .= " AND $field LIKE :$paramKey";
                            $bind[$paramKey] = "$primaryVal%";
                            break;
                        case 'ends_with':
                            $where .= " AND $field LIKE :$paramKey";
                            $bind[$paramKey] = "%$primaryVal";
                            break;
                        case 'greater_than':
                            $where .= " AND $field > :$paramKey";
                            $bind[$paramKey] = $primaryVal;
                            break;
                        case 'less_than':
                            $where .= " AND $field < :$paramKey";
                            $bind[$paramKey] = $primaryVal;
                            break;
                        case 'greater_equal':
                            $where .= " AND $field >= :$paramKey";
                            $bind[$paramKey] = $primaryVal;
                            break;
                        case 'less_equal':
                            $where .= " AND $field <= :$paramKey";
                            $bind[$paramKey] = $primaryVal;
                            break;
                        case 'before':
                            $where .= " AND $field <= :$paramKey";
                            $bind[$paramKey] = date('Y-m-d', strtotime($primaryVal));
                            break;
                        case 'after':
                            $where .= " AND $field >= :$paramKey";
                            $bind[$paramKey] = date('Y-m-d', strtotime($primaryVal));
                            break;
                        case 'between':
                            if (count($vals) >= 2) {
                                $p1 = "{$paramKey}_b1";
                                $p2 = "{$paramKey}_b2";
                                if ($field === 'f.fatura_tarihi') {
                                    $where .= " AND $field BETWEEN :$p1 AND :$p2";
                                    $bind[$p1] = date('Y-m-d', strtotime(trim($vals[0])));
                                    $bind[$p2] = date('Y-m-d', strtotime(trim($vals[1])));
                                } elseif ($field === 'f.odenecek_tutar') {
                                    $where .= " AND $field BETWEEN :$p1 AND :$p2";
                                    $bind[$p1] = \App\Helper\Helper::formattedMoneyToNumber(trim($vals[0]));
                                    $bind[$p2] = \App\Helper\Helper::formattedMoneyToNumber(trim($vals[1]));
                                } else {
                                    $where .= " AND $field BETWEEN :$p1 AND :$p2";
                                    $bind[$p1] = trim($vals[0]);
                                    $bind[$p2] = trim($vals[1]);
                                }
                            }
                            break;
                        case 'null':
                            $where .= " AND ($field IS NULL OR $field = '')";
                            break;
                        case 'not_null':
                            $where .= " AND ($field IS NOT NULL AND $field != '')";
                            break;
                        default:
                            $where .= " AND $field LIKE :$paramKey";
                            $bind[$paramKey] = "%$primaryVal%";
                            break;
                    }
                } else {
                    if ($field === 'f.fatura_tarihi') {
                        $where .= " AND $field = :$paramKey";
                        $bind[$paramKey] = date('Y-m-d', strtotime($rawVal));
                    } elseif ($field === 'f.belge_turu' || $field === 'f.entegrator_durum_kodu' || $field === 'f.ticari_yanit') {
                        $where .= " AND $field = :$paramKey";
                        $bind[$paramKey] = $rawVal;
                    } else {
                        $where .= " AND $field LIKE :$paramKey";
                        $bind[$paramKey] = "%$rawVal%";
                    }
                }
            }
        }

        if (!empty($search)) {
            $where .= " AND (f.fatura_no LIKE :s OR f.alici_unvan LIKE :s OR f.alici_vkn_tckn LIKE :s OR f.ettn LIKE :s)";
            $bind['s'] = "%$search%";
        }

        // Toplam Kayıt Sayısı
        $totalWhere = "f.firm_id = :firm_id AND f.deleted_at IS NULL";
        if ($listType === 'taslak') {
            $totalWhere .= " AND f.yon = 'GIDEN' AND f.entegrator_durum_kodu = 'TASLAK'";
        } elseif ($listType === 'gelen') {
            $totalWhere .= " AND f.yon = 'GELEN'";
        } else {
            $totalWhere .= " AND f.yon = 'GIDEN' AND f.entegrator_durum_kodu <> 'TASLAK'";
        }
        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM faturalar f WHERE $totalWhere");
        $totalStmt->execute(['firm_id' => $firmId]);
        $recordsTotal = (int)$totalStmt->fetchColumn();

        // Filtrelenmiş Kayıt Sayısı
        $summary = null;
        if ($listType === 'giden') {
            // Aggregate using the exact WHERE/bind set used by the table, before pagination.
            $filteredStmt = $this->db->prepare("SELECT
                COUNT(*) AS toplam_adet,
                COALESCE(SUM(f.odenecek_tutar), 0) AS toplam_tutar,
                COUNT(CASE WHEN f.belge_turu = 'EFATURA' THEN 1 END) AS efatura_adet,
                COUNT(CASE WHEN f.belge_turu = 'EARSIV' THEN 1 END) AS earsiv_adet,
                COUNT(CASE WHEN f.entegrator_durum_kodu = 'ONAYLANDI' THEN 1 END) AS onaylanan_adet,
                COALESCE(SUM(CASE WHEN f.entegrator_durum_kodu = 'ONAYLANDI' THEN f.odenecek_tutar ELSE 0 END), 0) AS onaylanan_tutar,
                COUNT(CASE WHEN f.entegrator_durum_kodu IN ('KUYRUKTA','GONDERILDI','BEKLIYOR') THEN 1 END) AS bekleyen_adet,
                COALESCE(SUM(CASE WHEN f.entegrator_durum_kodu IN ('KUYRUKTA','GONDERILDI','BEKLIYOR') THEN f.odenecek_tutar ELSE 0 END), 0) AS bekleyen_tutar,
                COUNT(CASE WHEN f.fatura_tarihi BETWEEN :summary_month_start AND :summary_month_end THEN 1 END) AS bu_ay_adet,
                COALESCE(SUM(CASE WHEN f.fatura_tarihi BETWEEN :summary_month_start AND :summary_month_end THEN f.odenecek_tutar ELSE 0 END), 0) AS bu_ay_tutar
                FROM faturalar f WHERE $where");
            $filteredStmt->execute($bind + ['summary_month_start' => date('Y-m-01'), 'summary_month_end' => date('Y-m-t')]);
            $summary = $filteredStmt->fetch(PDO::FETCH_ASSOC);
            $recordsFiltered = (int)$summary['toplam_adet'];
        } else {
            $filteredStmt = $this->db->prepare("SELECT COUNT(*) FROM faturalar f WHERE $where");
            $filteredStmt->execute($bind);
            $recordsFiltered = (int)$filteredStmt->fetchColumn();
        }

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

        $limitClause = "";
        if ($length > 0) {
            $limitClause = "LIMIT $start, $length";
        }

        $sql = "
            SELECT
                f.id, f.ettn, f.fatura_no, f.fatura_tarihi, f.duzenleme_saati, f.alici_unvan, f.alici_vkn_tckn,
                f.belge_turu, f.fatura_profili, f.fatura_tipi, f.odenecek_tutar, f.para_birimi,
                f.entegrator_durum_kodu, f.gib_durum_kodu, f.gib_durum_aciklamasi, f.ticari_yanit,
                f.pdf_path, f.ubl_xml_path, f.earsiv_rapor_durum, f.earsiv_iptal_rapor_durum, f.islem_belirsiz,
                COALESCE(t.toplam_tahsilat, 0) AS toplam_tahsilat,
                COALESCE(t.tahsilat_adedi, 0) AS tahsilat_adedi
            FROM faturalar f
            LEFT JOIN (
                SELECT fatura_id, SUM(tutar) AS toplam_tahsilat, COUNT(*) AS tahsilat_adedi
                FROM fatura_tahsilatlari
                WHERE deleted_at IS NULL AND is_active = 1
                GROUP BY fatura_id
            ) t ON t.fatura_id = f.id
            WHERE $where
            ORDER BY $orderCol $orderDir, f.id DESC
            $limitClause
        ";

        $dataStmt = $this->db->prepare($sql);
        $dataStmt->execute($bind);
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $encryptedId = Security::encrypt((string)$row['id']);
                $saatFormatted = !empty($row['duzenleme_saati']) ? date('H:i', strtotime($row['duzenleme_saati'])) : '';
                $odenecekTutar = (float)$row['odenecek_tutar'];
                $toplamTahsilat = (float)$row['toplam_tahsilat'];
                $kalanTutar = max(0, $odenecekTutar - $toplamTahsilat);
                $tahsilatDurumu = ($toplamTahsilat <= 0.0001) ? 'ODENMEDI' : (($toplamTahsilat >= $odenecekTutar - 0.0001) ? 'ODENDI' : 'KISMI_ODENDI');

                $data[] = [
                    'id'                    => $encryptedId,
                    'encrypted_id'          => $encryptedId,
                    'raw_id'                => (int)$row['id'],
                    'fatura_no'             => !empty($row['fatura_no']) ? $row['fatura_no'] : 'Taslak',
                    'ettn'                  => $row['ettn'],
                    'fatura_tarihi'         => date('d.m.Y', strtotime($row['fatura_tarihi'])),
                    'duzenleme_saati'       => $saatFormatted,
                    'alici_unvan'           => htmlspecialchars($row['alici_unvan'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'alici_vkn_tckn'        => htmlspecialchars($row['alici_vkn_tckn'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'belge_turu'            => $row['belge_turu'],
                    'fatura_profili'        => $row['fatura_profili'],
                    'fatura_tipi'           => $row['fatura_tipi'],
                    'odenecek_tutar'        => number_format($odenecekTutar, 2, ',', '.') . ' ' . $row['para_birimi'],
                    'odenecek_tutar_raw'    => $odenecekTutar,
                    'tahsil_edilen_tutar'   => number_format($toplamTahsilat, 2, ',', '.') . ' ' . $row['para_birimi'],
                    'tahsil_edilen_tutar_raw' => $toplamTahsilat,
                    'kalan_tutar'           => number_format($kalanTutar, 2, ',', '.') . ' ' . $row['para_birimi'],
                    'kalan_tutar_raw'       => $kalanTutar,
                    'tahsilat_durumu'       => $tahsilatDurumu,
                    'tahsilat_adedi'        => (int)$row['tahsilat_adedi'],
                    'entegrator_durum_kodu' => $row['entegrator_durum_kodu'],
                    'gib_durum_kodu'        => $row['gib_durum_kodu'],
                    'gib_durum_aciklamasi'  => htmlspecialchars($row['gib_durum_aciklamasi'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'ticari_yanit'          => $row['ticari_yanit'] ?? 'BEKLIYOR',
                    'earsiv_rapor_durum'    => $row['earsiv_rapor_durum'],
                    'earsiv_iptal_rapor_durum' => $row['earsiv_iptal_rapor_durum'],
                    'islem_belirsiz'        => $row['islem_belirsiz'],
                    'pdf_path'              => !empty($row['pdf_path']),
                    'ubl_xml_path'          => !empty($row['ubl_xml_path'])
                ];
            }
        }

        return [
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data
        ] + ($summary !== null ? ['summary' => $summary] : []);
    }

    /**
     * Dashboard ve Özet Kartları İstatistikleri
     */
    public function getSummaryStats(int $firmId, string $yon = 'GIDEN', string $listType = 'giden', ?string $startDate = null, ?string $endDate = null): array
    {
        try {
            $currentMonthStart = date('Y-m-01');
            $currentMonthEnd = date('Y-m-t');

            $dateWhere = "";
            $params = [
                'firm_id'     => $firmId,
                'month_start' => $currentMonthStart,
                'month_end'   => $currentMonthEnd
            ];

            if (!empty($startDate) && !empty($endDate)) {
                $dateWhere = " AND fatura_tarihi BETWEEN :start_date AND :end_date";
                $params['start_date'] = $startDate;
                $params['end_date'] = $endDate;
            } elseif (!empty($startDate)) {
                $dateWhere = " AND fatura_tarihi >= :start_date";
                $params['start_date'] = $startDate;
            } elseif (!empty($endDate)) {
                $dateWhere = " AND fatura_tarihi <= :end_date";
                $params['end_date'] = $endDate;
            }

            if ($listType === 'taslak') {
                $stmt = $this->db->prepare("
                    SELECT
                        COUNT(*) as toplam_adet,
                        COALESCE(SUM(odenecek_tutar), 0) as toplam_tutar,
                        COUNT(CASE WHEN belge_turu = 'EFATURA' THEN 1 END) as efatura_adet,
                        COUNT(CASE WHEN belge_turu = 'EARSIV' THEN 1 END) as earsiv_adet,
                        COUNT(CASE WHEN fatura_tarihi BETWEEN :month_start AND :month_end THEN 1 END) as bu_ay_adet,
                        COALESCE(SUM(CASE WHEN fatura_tarihi BETWEEN :month_start AND :month_end THEN odenecek_tutar ELSE 0 END), 0) as bu_ay_tutar
                    FROM faturalar
                    WHERE firm_id = :firm_id
                      AND yon = 'GIDEN'
                      AND entegrator_durum_kodu = 'TASLAK'
                      AND deleted_at IS NULL
                      {$dateWhere}
                ");
                $stmt->execute($params);
                return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } elseif ($listType === 'gelen') {
                $stmt = $this->db->prepare("
                    SELECT
                        COUNT(*) as toplam_adet,
                        COALESCE(SUM(odenecek_tutar), 0) as toplam_tutar,
                        COUNT(CASE WHEN ticari_yanit = 'KABUL' THEN 1 END) as kabul_adet,
                        COUNT(CASE WHEN ticari_yanit = 'RED' THEN 1 END) as red_adet,
                        COUNT(CASE WHEN ticari_yanit = 'BEKLIYOR' THEN 1 END) as bekleyen_adet,
                        COUNT(CASE WHEN fatura_tarihi BETWEEN :month_start AND :month_end THEN 1 END) as bu_ay_adet,
                        COALESCE(SUM(CASE WHEN fatura_tarihi BETWEEN :month_start AND :month_end THEN odenecek_tutar ELSE 0 END), 0) as bu_ay_tutar
                    FROM faturalar
                    WHERE firm_id = :firm_id
                      AND yon = 'GELEN'
                      AND deleted_at IS NULL
                      {$dateWhere}
                ");
                $stmt->execute($params);
                return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } else {
                // Giden (Gönderilen & Onaylanan) Faturalar
                $stmt = $this->db->prepare("
                    SELECT
                        COUNT(*) as toplam_adet,
                        COALESCE(SUM(odenecek_tutar), 0) as toplam_tutar,
                        COUNT(CASE WHEN belge_turu = 'EFATURA' THEN 1 END) as efatura_adet,
                        COUNT(CASE WHEN belge_turu = 'EARSIV' THEN 1 END) as earsiv_adet,
                        COUNT(CASE WHEN entegrator_durum_kodu = 'ONAYLANDI' THEN 1 END) as onaylanan_adet,
                        COALESCE(SUM(CASE WHEN entegrator_durum_kodu = 'ONAYLANDI' THEN odenecek_tutar ELSE 0 END), 0) as onaylanan_tutar,
                        COUNT(CASE WHEN entegrator_durum_kodu IN ('KUYRUKTA', 'GONDERILDI', 'BEKLIYOR') THEN 1 END) as bekleyen_adet,
                        COALESCE(SUM(CASE WHEN entegrator_durum_kodu IN ('KUYRUKTA', 'GONDERILDI', 'BEKLIYOR') THEN odenecek_tutar ELSE 0 END), 0) as bekleyen_tutar,
                        COUNT(CASE WHEN entegrator_durum_kodu = 'HATALI' THEN 1 END) as hatali_adet,
                        COUNT(CASE WHEN entegrator_durum_kodu = 'IPTAL' THEN 1 END) as iptal_adet,
                        COUNT(CASE WHEN fatura_tarihi BETWEEN :month_start AND :month_end THEN 1 END) as bu_ay_adet,
                        COALESCE(SUM(CASE WHEN fatura_tarihi BETWEEN :month_start AND :month_end THEN odenecek_tutar ELSE 0 END), 0) as bu_ay_tutar
                    FROM faturalar
                    WHERE firm_id = :firm_id
                      AND yon = 'GIDEN'
                      AND entegrator_durum_kodu <> 'TASLAK'
                      AND deleted_at IS NULL
                      {$dateWhere}
                ");
                $stmt->execute($params);
                return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\PDOException $e) {
            error_log("EInvoiceModel::getSummaryStats Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Taslak Durumundaki Faturayı Siler (Soft Delete)
     */
    public function deleteDraftInvoice(int $invoiceId, int $firmId): bool
    {
        try {
            // Kontrol: Sadece TASLAK durumundaki fatura silinebilir
            $checkStmt = $this->db->prepare("SELECT entegrator_durum_kodu FROM faturalar WHERE id = :id AND firm_id = :firm_id AND deleted_at IS NULL LIMIT 1");
            $checkStmt->execute(['id' => $invoiceId, 'firm_id' => $firmId]);
            $status = $checkStmt->fetchColumn();

            if (!$status) {
                return false;
            }

            if ($status !== 'TASLAK') {
                throw new \Exception("Yalnızca taslak durumundaki faturalar silinebilir. GİB'e iletilen faturalar iptal edilmelidir.");
            }

            $stmt = $this->db->prepare("
                UPDATE faturalar SET
                    deleted_at = NOW(),
                    is_active = 0,
                    updated_at = NOW()
                WHERE id = :id AND firm_id = :firm_id AND entegrator_durum_kodu = 'TASLAK' AND yon = 'GIDEN' AND kaynak_xml IS NULL AND edm_referans_no IS NULL AND islem_belirsiz IS NULL
            ");
            $stmt->execute(['id' => $invoiceId, 'firm_id' => $firmId]);
            return $stmt->rowCount() === 1;
        } catch (\Exception $e) {
            error_log("EInvoiceModel::deleteDraftInvoice Error: " . $e->getMessage());
            return false;
        }
    }
    public function acquireInvoiceLock(int $invoiceId, int $firmId): bool
    {
        $stmt = $this->db->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $stmt->execute(['lock_name' => 'efatura:' . $firmId . ':' . $invoiceId]);
        return (int)$stmt->fetchColumn() === 1;
    }

    public function releaseInvoiceLock(int $invoiceId, int $firmId): void
    {
        $stmt = $this->db->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $stmt->execute(['lock_name' => 'efatura:' . $firmId . ':' . $invoiceId]);
    }

    public function reserveSend(int $invoiceId, int $firmId): bool
    {
        $stmt = $this->db->prepare("UPDATE faturalar SET entegrator_durum_kodu = 'GONDERILIYOR', islem_belirsiz = NULL, updated_at = NOW() WHERE id = :id AND firm_id = :firm_id AND yon = 'GIDEN' AND deleted_at IS NULL AND entegrator_durum_kodu IN ('TASLAK','HATALI') AND islem_belirsiz IS NULL");
        $stmt->execute(['id' => $invoiceId, 'firm_id' => $firmId]);
        return $stmt->rowCount() === 1;
    }

    public function recordEvent(int $invoiceId, int $firmId, string $action, string $result, string $description, ?string $occurredAt = null): void
    {
        $stmt = $this->db->prepare('INSERT INTO efatura_islem_gecmisi (firm_id, fatura_id, islem, sonuc, aciklama, user_id, olay_tarihi) SELECT :firm, id, :action, :result, :description, :user, :occurred FROM faturalar WHERE id = :id AND firm_id = :owner AND deleted_at IS NULL');
        $stmt->execute(['firm' => $firmId, 'id' => $invoiceId, 'owner' => $firmId, 'action' => $action, 'result' => $result, 'description' => $description, 'user' => $_SESSION['user_id'] ?? $_SESSION['id'] ?? null, 'occurred' => $occurredAt ?? date('Y-m-d H:i:s')]);
    }

    public function history(int $invoiceId, int $firmId): array
    {
        $stmt = $this->db->prepare('SELECT h.islem, h.sonuc, h.aciklama, h.olay_tarihi FROM efatura_islem_gecmisi h JOIN faturalar f ON f.id = h.fatura_id AND f.firm_id = h.firm_id WHERE h.fatura_id = :id AND h.firm_id = :firm AND f.deleted_at IS NULL ORDER BY h.olay_tarihi DESC, h.id DESC');
        $stmt->execute(['id' => $invoiceId, 'firm' => $firmId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recordRemoteResponse(int $invoiceId, int $firmId, string $response, string $date): void
    {
        $stmt = $this->db->prepare("INSERT INTO efatura_islem_gecmisi (firm_id, fatura_id, islem, sonuc, aciklama, olay_tarihi) SELECT :firm, :invoice, 'TICARI_YANIT_EDM', :response, 'EDM yanıt tarihi', :date WHERE NOT EXISTS (SELECT 1 FROM efatura_islem_gecmisi WHERE firm_id = :owner AND fatura_id = :id AND islem = 'TICARI_YANIT_EDM' AND sonuc = :result AND olay_tarihi = :occurred)");
        $stmt->execute(['firm' => $firmId, 'invoice' => $invoiceId, 'response' => $response, 'date' => $date, 'owner' => $firmId, 'id' => $invoiceId, 'result' => $response, 'occurred' => $date]);
    }

    public function recordSync(int $firmId, string $direction, string $start, string $end, array $result): void
    {
        $stmt = $this->db->prepare('INSERT INTO efatura_senkronizasyon (firm_id, yon, baslangic, bitis, tamamlandi, sonuc, created_at) VALUES (:firm, :direction, :start, :end, :complete, :result, NOW())');
        $stmt->execute(['firm' => $firmId, 'direction' => $direction, 'start' => $start, 'end' => $end, 'complete' => (int)$result['complete'], 'result' => json_encode($result, JSON_UNESCAPED_UNICODE)]);
    }

    public function invoiceCustomers(int $firmId): array
    {
        // Cari uses the application's shared customer catalogue, with no firm_id column.
        $stmt = $this->db->prepare('SELECT id, CariAdi, Telefon, Email, web_sitesi, firma, vkn_tckn, vergi_dairesi, alici_turu, belge_turu, posta_kutusu, ulke, il, ilce, posta_kodu, Adres, notlar, ticaret_sicil_no, mersis_no FROM cari WHERE silinme_tarihi IS NULL ORDER BY CariAdi ASC');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Trusted XML import: preserve source amounts instead of draft recalculation. */
    public function importInvoice(int $firmId, array $header, array $lines, int $userId): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT id, deleted_at, yon, entegrator_durum_kodu, kaynak_xml, islem_belirsiz FROM faturalar WHERE ettn = :uuid AND firm_id = :firm FOR UPDATE');
            $stmt->execute(['uuid' => $header['ettn'], 'firm' => $firmId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing && ($existing['deleted_at'] || $existing['yon'] !== $header['yon'] || $existing['entegrator_durum_kodu'] === 'GONDERILIYOR' || !empty($existing['islem_belirsiz']))) throw new \RuntimeException('Fatura silinmiş, işlemde veya yönü uyuşmuyor.');
            if ($existing && !empty($header['kaynak_xml']) && $existing['kaynak_xml'] === $header['kaynak_xml']) { $this->db->commit(); return (int)$existing['id']; }
            $allowed = ['yon','belge_turu','fatura_profili','fatura_tipi','ettn','fatura_no','fatura_tarihi','duzenleme_saati','vade_tarihi','alici_vkn_tckn','alici_unvan','alici_vergi_dairesi','alici_adres','alici_il','alici_ilce','alici_ulke','alici_eposta','alici_telefon','para_birimi','doviz_kuru','satir_toplami','iskonto_toplami','kdv_matrahi','hesaplanan_kdv','tevkifat_tutari','odenecek_tutar','notlar','iade_fatura_no','iade_fatura_tarihi','ubl_xml_path','kaynak_xml','entegrator_durum_kodu','edm_durum'];
            $data = array_intersect_key($header, array_flip($allowed));
            if ($existing) {
                // Imported content may refresh; confirmed local response/cancellation is preserved.
                unset($data['entegrator_durum_kodu']);
                $sets = implode(', ', array_map(static fn($key) => "$key = :$key", array_keys($data)));
                $stmt = $this->db->prepare("UPDATE faturalar SET $sets, updated_at = NOW() WHERE id = :id AND firm_id = :firm");
                $id = (int)$existing['id'];
                $stmt->execute($data + ['id' => $id, 'firm' => $firmId]);
                $stmt = $this->db->prepare('UPDATE fatura_satirlari SET deleted_at = NOW(), is_active = 0 WHERE fatura_id = :id AND deleted_at IS NULL');
                $stmt->execute(['id' => $id]);
            } else {
                $data += ['firm_id' => $firmId, 'olusturan_user_id' => $userId];
                $columns = implode(',', array_keys($data)); $values = ':' . implode(',:', array_keys($data));
                $stmt = $this->db->prepare("INSERT INTO faturalar ($columns) VALUES ($values)");
                $stmt->execute($data); $id = (int)$this->db->lastInsertId();
            }
            $allowedLines = ['urun_hizmet_adi','urun_kodu','miktar','birim','birim_fiyat','iskonto_orani','iskonto_tutari','kdv_orani','kdv_tutari','tevkifat_kodu','tevkifat_orani','tevkifat_tutari','istisna_kodu','istisna_aciklama','satir_toplami'];
            foreach ($lines as $index => $line) {
                $data = array_intersect_key($line, array_flip($allowedLines)) + ['fatura_id' => $id, 'sira_no' => $index + 1];
                $columns = implode(',', array_keys($data)); $values = ':' . implode(',:', array_keys($data));
                $stmt = $this->db->prepare("INSERT INTO fatura_satirlari ($columns) VALUES ($values)");
                $stmt->execute($data);
            }
            $this->db->commit(); return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
