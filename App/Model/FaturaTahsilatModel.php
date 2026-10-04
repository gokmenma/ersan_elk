<?php
namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use App\Helper\Helper;
use PDO;
use Exception;

class FaturaTahsilatModel extends Model
{
    protected $table = 'fatura_tahsilatlari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Faturaya ait aktif tahsilat kayıtlarını getirir
     */
    public function getPaymentsByInvoice(int $invoiceId, int $firmId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT ft.*, k.kasa_adi, k.kasa_tipi, u.adi_soyadi as kullanici_adi
                FROM fatura_tahsilatlari ft
                LEFT JOIN kasalar k ON k.id = ft.kasa_id
                LEFT JOIN users u ON u.id = ft.olusturan_user_id
                WHERE ft.fatura_id = :fatura_id AND ft.firm_id = :firm_id AND ft.deleted_at IS NULL AND ft.is_active = 1
                ORDER BY ft.islem_tarihi DESC, ft.id DESC
            ");
            $stmt->execute(['fatura_id' => $invoiceId, 'firm_id' => $firmId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("FaturaTahsilatModel::getPaymentsByInvoice Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Faturaya ait tahsil edilmiş toplam tutarı döner
     */
    public function getTotalPaidByInvoice(int $invoiceId, int $firmId): float
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(tutar), 0) as toplam_odenen
                FROM fatura_tahsilatlari
                WHERE fatura_id = :fatura_id AND firm_id = :firm_id AND deleted_at IS NULL AND is_active = 1
            ");
            $stmt->execute(['fatura_id' => $invoiceId, 'firm_id' => $firmId]);
            return (float)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log("FaturaTahsilatModel::getTotalPaidByInvoice Error: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Modal ve detay için fatura ödeme ve kasa bilgilerini döner
     */
    public function getInvoicePaymentInfo(int $invoiceId, int $firmId): ?array
    {
        try {
            // Fatura Bilgisi
            $stmt = $this->db->prepare("
                SELECT f.id, f.ettn, f.fatura_no, f.fatura_tarihi, f.alici_unvan, f.alici_vkn_tckn,
                       f.odenecek_tutar, f.para_birimi, f.cari_id, f.belge_turu, f.entegrator_durum_kodu,
                       c.CariAdi
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

            // Tahsilat Geçmişi
            $payments = $this->getPaymentsByInvoice($invoiceId, $firmId);
            $totalPaid = 0.0;
            $formattedPayments = [];

            foreach ($payments as $p) {
                $totalPaid += (float)$p['tutar'];
                $formattedPayments[] = [
                    'id' => Security::encrypt((string)$p['id']),
                    'raw_id' => (int)$p['id'],
                    'tahsilat_tipi' => $p['tahsilat_tipi'],
                    'kasa_adi' => htmlspecialchars($p['kasa_adi'] ?? 'Hesap Belirtilmemiş', ENT_QUOTES, 'UTF-8'),
                    'islem_tarihi' => date('d.m.Y', strtotime($p['islem_tarihi'])),
                    'tutar' => number_format((float)$p['tutar'], 2, ',', '.') . ' ' . ($p['para_birimi'] ?: 'TRY'),
                    'tutar_raw' => (float)$p['tutar'],
                    'aciklama' => htmlspecialchars($p['aciklama'] ?? '-', ENT_QUOTES, 'UTF-8'),
                    'kullanici' => htmlspecialchars($p['kullanici_adi'] ?? '-', ENT_QUOTES, 'UTF-8'),
                    'created_at' => date('d.m.Y H:i', strtotime($p['created_at']))
                ];
            }

            $odenecekTutar = (float)$invoice['odenecek_tutar'];
            $kalanTutar = max(0, $odenecekTutar - $totalPaid);

            // Firmanın Aktif Kasaları
            $kasaStmt = $this->db->prepare("
                SELECT id, kasa_kodu, kasa_adi, kasa_tipi, para_birimi, hesap_no, varsayilan_mi
                FROM kasalar
                WHERE (owner_id = :firm_id OR owner_id = 1) AND silinme_tarihi IS NULL AND aktif = 1
                ORDER BY varsayilan_mi DESC, kasa_adi ASC
            ");
            $kasaStmt->execute(['firm_id' => $firmId]);
            $kasalar = $kasaStmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'invoice' => [
                    'id' => Security::encrypt((string)$invoice['id']),
                    'raw_id' => (int)$invoice['id'],
                    'fatura_no' => !empty($invoice['fatura_no']) ? $invoice['fatura_no'] : 'Taslak Fatura',
                    'fatura_tarihi' => date('d.m.Y', strtotime($invoice['fatura_tarihi'])),
                    'alici_unvan' => htmlspecialchars($invoice['alici_unvan'] ?: ($invoice['CariAdi'] ?: '-'), ENT_QUOTES, 'UTF-8'),
                    'alici_vkn_tckn' => htmlspecialchars($invoice['alici_vkn_tckn'] ?: '-', ENT_QUOTES, 'UTF-8'),
                    'odenecek_tutar' => number_format($odenecekTutar, 2, ',', '.') . ' ' . $invoice['para_birimi'],
                    'odenecek_tutar_raw' => $odenecekTutar,
                    'toplam_odenen' => number_format($totalPaid, 2, ',', '.') . ' ' . $invoice['para_birimi'],
                    'toplam_odenen_raw' => $totalPaid,
                    'kalan_tutar' => number_format($kalanTutar, 2, ',', '.') . ' ' . $invoice['para_birimi'],
                    'kalan_tutar_raw' => $kalanTutar,
                    'para_birimi' => $invoice['para_birimi'] ?: 'TRY',
                    'durum' => $invoice['entegrator_durum_kodu'],
                    'belge_turu' => $invoice['belge_turu']
                ],
                'payments' => $formattedPayments,
                'kasalar' => array_map(function($k) {
                    return [
                        'id' => (int)$k['id'],
                        'kasa_adi' => $k['kasa_adi'] . (!empty($k['kasa_kodu']) ? ' (' . $k['kasa_kodu'] . ')' : ''),
                        'kasa_tipi' => $k['kasa_tipi'],
                        'para_birimi' => $k['para_birimi'] ?: 'TRY',
                        'varsayilan' => (int)$k['varsayilan_mi'] === 1
                    ];
                }, $kasalar)
            ];
        } catch (\PDOException $e) {
            error_log("FaturaTahsilatModel::getInvoicePaymentInfo Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Yeni Tahsilat Ekle (Transaction ile Kasa ve Cari Hareketi de işler)
     */
    public function addPayment(int $firmId, int $invoiceId, array $data, int $userId): array
    {
        $this->db->beginTransaction();
        try {
            // 1. Faturayı Doğrula
            $invStmt = $this->db->prepare("
                SELECT id, cari_id, fatura_no, alici_unvan, odenecek_tutar, para_birimi, firm_id
                FROM faturalar
                WHERE id = :id AND firm_id = :firm_id AND deleted_at IS NULL
                LIMIT 1
            ");
            $invStmt->execute(['id' => $invoiceId, 'firm_id' => $firmId]);
            $invoice = $invStmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                throw new Exception('Fatura bulunamadı veya bu işlem için yetkiniz yok.');
            }

            // 2. Kasa Doğrula
            $kasaId = (int)($data['kasa_id'] ?? 0);
            $kasaStmt = $this->db->prepare("
                SELECT id, kasa_adi, para_birimi FROM kasalar
                WHERE id = :id AND silinme_tarihi IS NULL
                LIMIT 1
            ");
            $kasaStmt->execute(['id' => $kasaId]);
            $kasa = $kasaStmt->fetch(PDO::FETCH_ASSOC);

            if (!$kasa) {
                throw new Exception('Geçerli bir Kasa/Banka hesabı seçilmedi.');
            }

            // 3. Tutar ve Tarih
            $tutar = (float)($data['tutar'] ?? 0);
            if ($tutar <= 0) {
                throw new Exception('Tahsilat tutarı sıfırdan büyük olmalıdır.');
            }

            $islemTarihi = !empty($data['islem_tarihi']) ? date('Y-m-d', strtotime($data['islem_tarihi'])) : date('Y-m-d');
            $tahsilatTipi = !empty($data['tahsilat_tipi']) ? trim($data['tahsilat_tipi']) : 'nakit';
            $paraBirimi = !empty($data['para_birimi']) ? trim($data['para_birimi']) : ($invoice['para_birimi'] ?: 'TRY');
            $faturaNo = !empty($invoice['fatura_no']) ? $invoice['fatura_no'] : 'Taslak Fatura';
            $aciklama = !empty($data['aciklama']) ? trim($data['aciklama']) : ($faturaNo . ' tahsilatı (' . $invoice['alici_unvan'] . ')');

            // 4. Kasa Hareketi Ekle
            $kasaHareketId = null;
            $kasaHrkStmt = $this->db->prepare("
                INSERT INTO kasa_hareketleri (
                    hareket_tipi, kasa_id, aciklama, tutar, para_birimi, islem_tarihi,
                    kayit_tarihi, cari_id, kullanici_id, referans_no, aktif
                ) VALUES (
                    'gelir', :kasa_id, :aciklama, :tutar, :para_birimi, :islem_tarihi,
                    NOW(), :cari_id, :kullanici_id, :referans_no, 1
                )
            ");
            $kasaHrkStmt->execute([
                'kasa_id' => $kasaId,
                'aciklama' => $aciklama,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'islem_tarihi' => $islemTarihi,
                'cari_id' => !empty($invoice['cari_id']) ? (int)$invoice['cari_id'] : null,
                'kullanici_id' => $userId,
                'referans_no' => $faturaNo
            ]);
            $kasaHareketId = (int)$this->db->lastInsertId();

            // 5. Cari Hareketi Ekle (Eğer faturaya bağlı cari varsa)
            $cariHareketId = null;
            if (!empty($invoice['cari_id'])) {
                $cariHrkStmt = $this->db->prepare("
                    INSERT INTO cari_hareketleri (
                        cari_id, islem_tarihi, belge_no, aciklama, borc, alacak, kayit_tarihi
                    ) VALUES (
                        :cari_id, :islem_tarihi, :belge_no, :aciklama, 0.00, :alacak, NOW()
                    )
                ");
                $cariHrkStmt->execute([
                    'cari_id' => (int)$invoice['cari_id'],
                    'islem_tarihi' => $islemTarihi . ' ' . date('H:i:s'),
                    'belge_no' => $faturaNo,
                    'aciklama' => $aciklama,
                    'alacak' => $tutar
                ]);
                $cariHareketId = (int)$this->db->lastInsertId();
            }

            // 6. Fatura Tahsilat Kaydı Ekle
            $ftStmt = $this->db->prepare("
                INSERT INTO fatura_tahsilatlari (
                    firm_id, fatura_id, cari_id, kasa_id, tahsilat_tipi,
                    islem_tarihi, tutar, para_birimi, aciklama,
                    kasa_hareket_id, cari_hareket_id, olusturan_user_id,
                    is_active, created_at
                ) VALUES (
                    :firm_id, :fatura_id, :cari_id, :kasa_id, :tahsilat_tipi,
                    :islem_tarihi, :tutar, :para_birimi, :aciklama,
                    :kasa_hareket_id, :cari_hareket_id, :olusturan_user_id,
                    1, NOW()
                )
            ");
            $ftStmt->execute([
                'firm_id' => $firmId,
                'fatura_id' => $invoiceId,
                'cari_id' => !empty($invoice['cari_id']) ? (int)$invoice['cari_id'] : null,
                'kasa_id' => $kasaId,
                'tahsilat_tipi' => $tahsilatTipi,
                'islem_tarihi' => $islemTarihi,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'aciklama' => $aciklama,
                'kasa_hareket_id' => $kasaHareketId,
                'cari_hareket_id' => $cariHareketId,
                'olusturan_user_id' => $userId
            ]);
            $paymentId = (int)$this->db->lastInsertId();

            $this->db->commit();

            return [
                'status' => 'success',
                'message' => 'Tahsilat başarıyla kaydedildi.',
                'payment_id' => $paymentId
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("FaturaTahsilatModel::addPayment Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Tahsilatı Sil (Soft Delete ve ilişkili kasa/cari hareketlerini silme)
     */
    public function deletePayment(int $paymentId, int $firmId, int $userId): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                SELECT id, kasa_hareket_id, cari_hareket_id
                FROM fatura_tahsilatlari
                WHERE id = :id AND firm_id = :firm_id AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute(['id' => $paymentId, 'firm_id' => $firmId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new Exception('Tahsilat kaydı bulunamadı.');
            }

            // 1. Fatura Tahsilat soft delete
            $delFt = $this->db->prepare("
                UPDATE fatura_tahsilatlari
                SET is_active = 0, deleted_at = NOW()
                WHERE id = :id
            ");
            $delFt->execute(['id' => $paymentId]);

            // 2. Kasa Hareketi soft delete
            if (!empty($payment['kasa_hareket_id'])) {
                $delKh = $this->db->prepare("
                    UPDATE kasa_hareketleri
                    SET aktif = 0, silinme_tarihi = NOW(), silen_kullanici = :user_id
                    WHERE id = :id
                ");
                $delKh->execute(['id' => (int)$payment['kasa_hareket_id'], 'user_id' => $userId]);
            }

            // 3. Cari Hareketi soft delete
            if (!empty($payment['cari_hareket_id'])) {
                $delCh = $this->db->prepare("
                    UPDATE cari_hareketleri
                    SET silinme_tarihi = NOW(), silen_kullanici = :user_id
                    WHERE id = :id
                ");
                $delCh->execute(['id' => (int)$payment['cari_hareket_id'], 'user_id' => $userId]);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("FaturaTahsilatModel::deletePayment Error: " . $e->getMessage());
            return false;
        }
    }
}
