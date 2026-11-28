<?php

namespace App\Service;

use App\Model\AparatHareketModel;
use App\Model\AparatOtomatikKirmaModel;
use App\Model\AparatSayimModel;
use App\Model\AparatStokModel;
use App\Model\AparatTransferModel;
use App\Model\KesmeAcmaIslemModel;
use App\Model\SystemLogModel;
use Exception;

/**
 * Aparat hareketlerinin tek giriş noktası. Kayıt ve stok etkisi daima
 * aynı transaction içinde işlenir; stok eksiye düşerse işlem durdurulmaz,
 * kayıt "negatif stok" olarak işaretlenip şefe raporlanır.
 */
class AparatStokService
{
    private AparatHareketModel $Hareket;
    private AparatStokModel $Stok;
    private KesmeAcmaIslemModel $Islem;
    private AparatTransferModel $Transfer;
    private SystemLogModel $Log;

    public function __construct()
    {
        $this->Hareket = new AparatHareketModel();
        $this->Stok = new AparatStokModel();
        $this->Islem = new KesmeAcmaIslemModel();
        $this->Transfer = new AparatTransferModel();
        $this->Log = new SystemLogModel();
    }

    private function db()
    {
        return $this->Hareket->db;
    }

    // =====================================================
    // SAHA İŞLEMİ (kesme / açma)
    // =====================================================

    /**
     * @return array ['id', 'negatif', 'mukerrer', 'bakiye']
     */
    public function islemKaydet(array $veri): array
    {
        $islemTipi = $veri['islem_tipi'] ?? '';
        if (!in_array($islemTipi, ['kesme', 'acma'], true)) {
            throw new Exception('Geçersiz işlem tipi.');
        }

        $ekipId = (int) ($veri['ekip_id'] ?? 0);
        if ($ekipId <= 0 || !$this->Stok->ekipGecerliMi($ekipId)) {
            throw new Exception('Geçerli bir ekip seçilmedi.');
        }

        $aparatsiz = (int) ($veri['aparatsiz'] ?? 0) === 1;
        $tipId = (int) ($veri['aparat_tip_id'] ?? 0);
        $adet = max(1, (int) ($veri['adet'] ?? 1));

        if (!$aparatsiz && $tipId <= 0) {
            throw new Exception('Aparat tipi seçilmedi.');
        }

        $aparatDurumu = $veri['aparat_durumu'] ?? null;
        if ($islemTipi === 'acma' && !$aparatsiz) {
            if (!array_key_exists((string) $aparatDurumu, KesmeAcmaIslemModel::APARAT_DURUMLARI)) {
                throw new Exception('Aparatın geri alınıp alınmadığı belirtilmedi.');
            }
        }

        $tarih = $veri['tarih'] ?? date('Y-m-d');
        $aboneNo = trim((string) ($veri['abone_no'] ?? ''));

        $mukerrer = $this->Islem->mukerrerVarMi($aboneNo, $islemTipi, $tarih, $veri['client_uuid'] ?? null);

        $kendiTransaction = !$this->db()->inTransaction();
        if ($kendiTransaction) {
            $this->db()->beginTransaction();
        }

        try {
            $islemId = $this->Islem->ekle(array_merge($veri, [
                'ekip_id' => $ekipId,
                'ekip_adi' => $veri['ekip_adi'] ?? $this->Stok->ekipAdi($ekipId),
                'aparat_tip_id' => $aparatsiz ? null : $tipId,
                'adet' => $adet,
                'aparatsiz' => $aparatsiz ? 1 : 0,
                'aparat_durumu' => $islemTipi === 'acma' && !$aparatsiz ? $aparatDurumu : null,
                'mukerrer_uyari' => $mukerrer ? 1 : 0,
                'tarih' => $tarih,
            ]));

            $sonuc = ['hareket_ids' => [], 'negatif' => false, 'negatif_detay' => []];

            if (!$aparatsiz) {
                $satirlar = $this->islemSatirlari($islemTipi, $aparatDurumu, $ekipId, $tipId, $adet);

                $sonuc = $this->Hareket->uygula($satirlar, [
                    'hareket_tipi' => $islemTipi,
                    'ekip_id' => $ekipId,
                    'personel_id' => $veri['personel_id'] ?? null,
                    'referans_tipi' => 'kesme_acma_islem',
                    'referans_id' => $islemId,
                    'aciklama' => $aboneNo !== '' ? 'Abone: ' . $aboneNo : null,
                    'tarih' => $veri['cihaz_zamani'] ?? date('Y-m-d H:i:s'),
                    'kaydeden_id' => $veri['kaydeden_id'] ?? null,
                ]);

                if ($sonuc['negatif']) {
                    $this->Islem->negatifIsaretle($islemId);
                }
            }

            if ($kendiTransaction) {
                $this->db()->commit();
            }
        } catch (Exception $e) {
            if ($kendiTransaction && $this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }

        if ($sonuc['negatif']) {
            error_log(sprintf(
                'Aparat negatif stok: islem_id=%d ekip_id=%d tip_id=%d personel=%s',
                $islemId,
                $ekipId,
                $tipId,
                $veri['personel_id'] ?? '-'
            ));
        }

        $this->logla(
            $veri['kaydeden_id'] ?? 0,
            'Aparat Saha İşlemi',
            sprintf(
                '%s kaydı eklendi. Ekip: %s, Abone: %s, Aparat: %s x%d',
                ucfirst($islemTipi),
                $this->Stok->ekipAdi($ekipId),
                $aboneNo !== '' ? $aboneNo : '-',
                $aparatsiz ? 'kullanılmadı' : (string) $tipId,
                $adet
            )
        );

        return [
            'id' => $islemId,
            'negatif' => $sonuc['negatif'],
            'mukerrer' => $mukerrer,
            'bakiye' => $aparatsiz ? null : $this->Stok->bakiye('ekip', $ekipId, $tipId),
        ];
    }

    private function islemSatirlari(string $islemTipi, ?string $aparatDurumu, int $ekipId, int $tipId, int $adet): array
    {
        if ($islemTipi === 'kesme') {
            return [
                ['sahip_tipi' => 'ekip', 'sahip_id' => $ekipId, 'aparat_tip_id' => $tipId, 'adet' => -$adet],
                ['sahip_tipi' => 'saha', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => $adet],
            ];
        }

        // Açmada aparat sahadan düşer; nereye gittiği aparatın durumuna bağlıdır.
        $hedefHavuz = $aparatDurumu === 'bulunamadi' ? 'kayip' : 'ekip';

        return [
            ['sahip_tipi' => 'saha', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => -$adet],
            [
                'sahip_tipi' => $hedefHavuz,
                'sahip_id' => $hedefHavuz === 'ekip' ? $ekipId : 0,
                'aparat_tip_id' => $tipId,
                'adet' => $adet,
            ],
        ];
    }

    public function islemIptal(int $islemId, string $aciklama, int $kullaniciId, ?int $personelId = null): bool
    {
        $islem = $this->Islem->getir($islemId);
        if (!$islem) {
            throw new Exception('İşlem kaydı bulunamadı.');
        }
        if ($islem['durum'] === 'iptal') {
            throw new Exception('Bu kayıt zaten iptal edilmiş.');
        }

        $kendiTransaction = !$this->db()->inTransaction();
        if ($kendiTransaction) {
            $this->db()->beginTransaction();
        }

        try {
            if (!$this->Islem->iptalEt($islemId, $aciklama, $kullaniciId)) {
                throw new Exception('Kayıt iptal edilemedi.');
            }

            $this->Hareket->tersle('kesme_acma_islem', $islemId, [
                'personel_id' => $personelId,
                'aciklama' => 'İptal: ' . $aciklama,
                'kaydeden_id' => $kullaniciId,
                'tarih' => date('Y-m-d H:i:s'),
            ]);

            if ($kendiTransaction) {
                $this->db()->commit();
            }
        } catch (Exception $e) {
            if ($kendiTransaction && $this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }

        $this->logla(
            $kullaniciId,
            'Aparat İşlem İptali',
            sprintf('İşlem #%d iptal edildi. Gerekçe: %s', $islemId, $aciklama),
            SystemLogModel::LEVEL_IMPORTANT
        );

        return true;
    }

    public function islemSil(int $islemId, string $aciklama, int $kullaniciId): bool
    {
        $islem = $this->Islem->getir($islemId);
        if (!$islem) {
            throw new Exception('İşlem kaydı bulunamadı.');
        }
        if ($islem['durum'] !== 'iptal') {
            throw new Exception('Yalnızca iptal edilmiş kayıtlar silinebilir. Önce kaydı iptal edin.');
        }

        $kendiTransaction = !$this->db()->inTransaction();
        if ($kendiTransaction) {
            $this->db()->beginTransaction();
        }

        try {
            if (!$this->Islem->silEt($islemId)) {
                throw new Exception('Kayıt silinemedi.');
            }

            if ($kendiTransaction) {
                $this->db()->commit();
            }
        } catch (Exception $e) {
            if ($kendiTransaction && $this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }

        $this->logla(
            $kullaniciId,
            'Aparat İşlem Silindi',
            sprintf('İşlem #%d silindi (önceden iptal edilmişti). Gerekçe: %s', $islemId, $aciklama),
            SystemLogModel::LEVEL_IMPORTANT
        );

        return true;
    }

    // =====================================================
    // TRANSFER (çift onaylı)
    // =====================================================

    public function transferOlustur(array $veri): int
    {
        $verenId = (int) ($veri['veren_ekip_id'] ?? 0);
        $alanId = (int) ($veri['alan_ekip_id'] ?? 0);
        $tipId = (int) ($veri['aparat_tip_id'] ?? 0);
        $adet = (int) ($veri['adet'] ?? 0);

        if ($verenId <= 0 || $alanId <= 0) {
            throw new Exception('Veren ve alan ekip seçilmelidir.');
        }
        if ($verenId === $alanId) {
            throw new Exception('Aparat aynı ekibe transfer edilemez.');
        }
        if (!$this->Stok->ekipGecerliMi($verenId) || !$this->Stok->ekipGecerliMi($alanId)) {
            throw new Exception('Geçersiz ekip seçimi.');
        }
        if ($tipId <= 0 || $adet <= 0) {
            throw new Exception('Aparat tipi ve adet geçerli olmalıdır.');
        }

        $kendiTransaction = !$this->db()->inTransaction();
        if ($kendiTransaction) {
            $this->db()->beginTransaction();
        }

        try {
            $zimmet = $this->Stok->bakiyeKilitle('ekip', $verenId, $tipId);
            $bekleyen = (int) ($this->Transfer->bekleyenGidenAdetleri($verenId)[$tipId] ?? 0);
            $gonderilebilir = max(0, $zimmet - $bekleyen);

            if ($adet > $gonderilebilir) {
                $mesaj = $gonderilebilir > 0
                    ? sprintf('Zimmetinizde bu aparattan en fazla %d adet gönderebilirsiniz.', $gonderilebilir)
                    : 'Zimmetinizde gönderilebilecek bu aparat bulunmuyor.';
                if ($bekleyen > 0) {
                    $mesaj .= sprintf(' (Zimmet: %d, onay bekleyen gönderiminiz: %d)', max(0, $zimmet), $bekleyen);
                }
                throw new Exception($mesaj);
            }

            $transferId = $this->Transfer->ekle($veri);

            if ($kendiTransaction) {
                $this->db()->commit();
            }
        } catch (Exception $e) {
            if ($kendiTransaction && $this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }

        $this->logla(
            $veri['olusturan_user_id'] ?? 0,
            'Aparat Transfer Talebi',
            sprintf('#%d: %s -> %s, %d adet (onay bekliyor)', $transferId,
                $this->Stok->ekipAdi($verenId), $this->Stok->ekipAdi($alanId), $adet)
        );

        return $transferId;
    }

    public function transferOnayla(int $transferId, ?int $onaylananAdet, ?int $personelId, int $kullaniciId): array
    {
        $transfer = $this->Transfer->getir($transferId);
        if (!$transfer) {
            throw new Exception('Transfer kaydı bulunamadı.');
        }
        if ($transfer['durum'] !== 'beklemede') {
            throw new Exception('Bu transfer daha önce sonuçlandırılmış.');
        }

        $adet = $onaylananAdet !== null ? (int) $onaylananAdet : (int) $transfer['adet'];
        if ($adet <= 0) {
            throw new Exception('Onaylanan adet sıfırdan büyük olmalıdır.');
        }
        if ($adet > (int) $transfer['adet']) {
            throw new Exception('Onaylanan adet, gönderilen adetten fazla olamaz.');
        }

        $kendiTransaction = !$this->db()->inTransaction();
        if ($kendiTransaction) {
            $this->db()->beginTransaction();
        }

        try {
            $verenZimmet = $this->Stok->bakiyeKilitle('ekip', (int) $transfer['veren_ekip_id'], (int) $transfer['aparat_tip_id']);
            if ($adet > $verenZimmet) {
                throw new Exception($verenZimmet > 0
                    ? sprintf('Gönderen ekibin zimmetinde artık %d adet var; en fazla %d adet onaylanabilir.', $verenZimmet, $verenZimmet)
                    : 'Gönderen ekibin zimmetinde bu aparat kalmadı; transfer onaylanamaz.');
            }

            $guncellendi = $this->Transfer->durumGuncelle($transferId, 'onaylandi', [
                'onaylanan_adet' => $adet,
                'onaylayan_personel_id' => $personelId,
                'onaylayan_user_id' => $kullaniciId ?: null,
            ]);

            if (!$guncellendi) {
                throw new Exception('Transfer güncellenemedi.');
            }

            $sonuc = $this->Hareket->uygula([
                [
                    'sahip_tipi' => 'ekip',
                    'sahip_id' => (int) $transfer['veren_ekip_id'],
                    'aparat_tip_id' => (int) $transfer['aparat_tip_id'],
                    'adet' => -$adet,
                ],
                [
                    'sahip_tipi' => 'ekip',
                    'sahip_id' => (int) $transfer['alan_ekip_id'],
                    'aparat_tip_id' => (int) $transfer['aparat_tip_id'],
                    'adet' => $adet,
                ],
            ], [
                'hareket_tipi' => 'transfer',
                'ekip_id' => (int) $transfer['veren_ekip_id'],
                'personel_id' => $personelId,
                'referans_tipi' => 'aparat_transfer',
                'referans_id' => $transferId,
                'aciklama' => sprintf('%s -> %s', $transfer['veren_ekip_adi'], $transfer['alan_ekip_adi']),
                'kaydeden_id' => $kullaniciId ?: null,
            ]);

            if ($kendiTransaction) {
                $this->db()->commit();
            }
        } catch (Exception $e) {
            if ($kendiTransaction && $this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }

        $this->logla(
            $kullaniciId,
            'Aparat Transfer Onayı',
            sprintf('#%d onaylandı: %s -> %s, %d adet', $transferId,
                $transfer['veren_ekip_adi'], $transfer['alan_ekip_adi'], $adet)
        );

        return ['negatif' => $sonuc['negatif'], 'adet' => $adet];
    }

    public function transferReddet(int $transferId, string $neden, ?int $personelId, int $kullaniciId): bool
    {
        $transfer = $this->Transfer->getir($transferId);
        if (!$transfer) {
            throw new Exception('Transfer kaydı bulunamadı.');
        }
        if ($transfer['durum'] !== 'beklemede') {
            throw new Exception('Bu transfer daha önce sonuçlandırılmış.');
        }

        $sonuc = $this->Transfer->durumGuncelle($transferId, 'reddedildi', [
            'onaylayan_personel_id' => $personelId,
            'onaylayan_user_id' => $kullaniciId ?: null,
            'red_nedeni' => $neden,
        ]);

        if ($sonuc) {
            $this->logla($kullaniciId, 'Aparat Transfer Reddi',
                sprintf('#%d reddedildi. Gerekçe: %s', $transferId, $neden));
        }

        return $sonuc;
    }

    public function transferIptal(int $transferId, int $kullaniciId): bool
    {
        $transfer = $this->Transfer->getir($transferId);
        if (!$transfer) {
            throw new Exception('Transfer kaydı bulunamadı.');
        }
        if ($transfer['durum'] !== 'beklemede') {
            throw new Exception('Yalnızca bekleyen transfer iptal edilebilir.');
        }

        $sonuc = $this->Transfer->durumGuncelle($transferId, 'iptal', [
            'onaylayan_user_id' => $kullaniciId ?: null,
            'red_nedeni' => 'Şef tarafından iptal edildi.',
        ]);

        if ($sonuc) {
            $this->logla($kullaniciId, 'Aparat Transfer İptali', sprintf('#%d iptal edildi.', $transferId),
                SystemLogModel::LEVEL_IMPORTANT);
        }

        return $sonuc;
    }

    // =====================================================
    // DEPO VE DİĞER HAVUZ HAREKETLERİ
    // =====================================================

    /**
     * @param string $tur depo_giris | depo_cikis | depo_iade | hurda | kayip | acilis
     */
    public function havuzHareketi(string $tur, array $veri, int $kullaniciId): array
    {
        $tipId = (int) ($veri['aparat_tip_id'] ?? 0);
        $adet = (int) ($veri['adet'] ?? 0);
        $ekipId = (int) ($veri['ekip_id'] ?? 0);
        $personelId = (int) ($veri['personel_id'] ?? 0);
        $aciklama = trim((string) ($veri['aciklama'] ?? ''));
        $birimCeza = $tur === 'kayip' ? max(0.0, round((float) ($veri['birim_ceza'] ?? 0), 2)) : 0.0;
        $toplamCeza = $birimCeza > 0 ? round($adet * $birimCeza, 2) : 0.0;

        if ($tipId <= 0 || $adet <= 0) {
            throw new Exception('Aparat tipi ve adet geçerli olmalıdır.');
        }

        $ekipGerekli = in_array($tur, ['depo_cikis', 'depo_iade', 'depo_iade_hurda', 'hurda', 'kayip', 'acilis'], true);
        $sahipTipi = $personelId > 0 ? 'personel' : 'ekip';
        $sahipId = $personelId > 0 ? $personelId : $ekipId;
        if ($ekipGerekli) {
            if ($personelId > 0) {
                if (!$this->Stok->personelGecerliMi($personelId)) {
                    throw new Exception('Geçerli bir personel seçilmedi.');
                }
            } elseif ($ekipId <= 0 || !$this->Stok->ekipGecerliMi($ekipId)) {
                throw new Exception('Geçerli bir ekip seçilmedi.');
            }
        }

        if (in_array($tur, ['hurda', 'kayip'], true) && $aciklama === '') {
            throw new Exception('Hurda / kayıp kaydı için gerekçe zorunludur.');
        }

        $satirlar = match ($tur) {
            'depo_giris' => [
                ['sahip_tipi' => 'depo', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => $adet],
            ],
            'depo_cikis' => [
                ['sahip_tipi' => 'depo', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => -$adet],
                ['sahip_tipi' => $sahipTipi, 'sahip_id' => $sahipId, 'aparat_tip_id' => $tipId, 'adet' => $adet],
            ],
            'depo_iade' => [
                ['sahip_tipi' => $sahipTipi, 'sahip_id' => $sahipId, 'aparat_tip_id' => $tipId, 'adet' => -$adet],
                ['sahip_tipi' => 'depo', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => $adet],
            ],
            'hurda', 'depo_iade_hurda' => [
                ['sahip_tipi' => $sahipTipi, 'sahip_id' => $sahipId, 'aparat_tip_id' => $tipId, 'adet' => -$adet],
                ['sahip_tipi' => 'hurda', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => $adet],
            ],
            'kayip' => [
                ['sahip_tipi' => $sahipTipi, 'sahip_id' => $sahipId, 'aparat_tip_id' => $tipId, 'adet' => -$adet],
                ['sahip_tipi' => 'kayip', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => $adet],
            ],
            'acilis' => [
                ['sahip_tipi' => $sahipTipi, 'sahip_id' => $sahipId, 'aparat_tip_id' => $tipId, 'adet' => $adet],
            ],
            default => throw new Exception('Geçersiz hareket türü.'),
        };

        $kendiTransaction = !$this->db()->inTransaction();
        if ($kendiTransaction) {
            $this->db()->beginTransaction();
        }

        try {
            $sonuc = $this->Hareket->uygula($satirlar, [
                'hareket_tipi' => $tur === 'depo_iade_hurda' ? 'hurda' : $tur,
                'ekip_id' => $ekipGerekli && $personelId <= 0 ? $ekipId : null,
                'personel_id' => $personelId > 0 ? $personelId : null,
                'referans_tipi' => $tur === 'depo_iade_hurda' ? 'bozuk_iade' : 'manuel',
                'referans_id' => null,
                'birim_ceza' => $birimCeza > 0 ? $birimCeza : null,
                'ceza_tutari' => $toplamCeza > 0 ? $toplamCeza : null,
                'aciklama' => $tur === 'depo_iade_hurda'
                    ? trim('Bozuk iade alındı, hurdaya ayrıldı. ' . $aciklama)
                    : ($tur === 'kayip' && $toplamCeza > 0
                        ? trim($aciklama . ' (Ceza: ' . number_format($toplamCeza, 2, ',', '.') . ' ₺)')
                        : ($aciklama ?: null)),
                'kaydeden_id' => $kullaniciId ?: null,
            ]);

            // Bir havuz hareketi birden fazla satır yazabilir (ör. depo çıkışı: depo -N, ekip +N).
            // Grubun tamamını birlikte geri alabilmek için ilk satırın id'sini grup kimliği yap.
            $this->Hareket->grupKimligiAta($sonuc['hareket_ids']);

            $grupId = (int) ($sonuc['hareket_ids'][0] ?? 0);
            if ($tur === 'kayip' && $toplamCeza > 0 && $grupId > 0) {
                $this->kayipCezaKesintisiYaz($tipId, $adet, $birimCeza, $toplamCeza, $ekipId, $personelId, $grupId, $kullaniciId, $aciklama);
            }

            if ($kendiTransaction) {
                $this->db()->commit();
            }
        } catch (Exception $e) {
            if ($kendiTransaction && $this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }

        $sonuc['grup_id'] = !empty($sonuc['hareket_ids']) ? min($sonuc['hareket_ids']) : 0;

        $this->logla(
            $kullaniciId,
            'Aparat Havuz Hareketi',
            sprintf('%s: tip #%d, %d adet%s', $tur === 'depo_iade_hurda' ? 'Bozuk İade (Hurda)' : AparatHareketModel::HAREKET_TIPLERI[$tur],
                $tipId, $adet, $ekipGerekli ? ', ekip: ' . $this->Stok->ekipAdi($ekipId) : ''),
            SystemLogModel::LEVEL_IMPORTANT
        );

        return $sonuc;
    }

    public function havuzHareketiToplu(string $tur, array $satirlar, string $aciklama, int $kullaniciId, float $birimCeza = 0.0): array
    {
        if (!in_array($tur, ['depo_giris', 'depo_cikis', 'depo_iade', 'depo_iade_hurda', 'kayip', 'acilis'], true)) {
            throw new Exception('Geçersiz hareket türü.');
        }
        if (empty($satirlar)) {
            throw new Exception('Girilmiş adet bulunamadı.');
        }

        $kendiTransaction = !$this->db()->inTransaction();
        if ($kendiTransaction) {
            $this->db()->beginTransaction();
        }

        $islenen = 0;
        $toplamAdet = 0;
        $negatif = false;
        $grupIdleri = [];

        try {
            foreach ($satirlar as $satir) {
                $sonuc = $this->havuzHareketi($tur, [
                    'aparat_tip_id' => (int) ($satir['aparat_tip_id'] ?? 0),
                    'adet' => (int) ($satir['adet'] ?? 0),
                    'ekip_id' => (int) ($satir['ekip_id'] ?? 0),
                    'personel_id' => (int) ($satir['personel_id'] ?? 0),
                    'aciklama' => $aciklama,
                    'birim_ceza' => $birimCeza,
                ], $kullaniciId);

                $islenen++;
                $toplamAdet += (int) $satir['adet'];
                $negatif = $negatif || !empty($sonuc['negatif']);
                if (!empty($sonuc['grup_id'])) {
                    $grupIdleri[] = (int) $sonuc['grup_id'];
                }
            }

            if ($kendiTransaction) {
                $this->db()->commit();
            }
        } catch (Exception $e) {
            if ($kendiTransaction && $this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }

        return ['islenen' => $islenen, 'adet' => $toplamAdet, 'negatif' => $negatif, 'grup_idleri' => $grupIdleri];
    }

    /**
     * Hatalı girilmiş bir havuz hareketini ters kayıtla geri alır.
     * Defter kaydı silinmez; orijinal satırlar "iptal" işareti alır ve
     * stok bakiyesini eski hâline getiren ters satırlar yazılır.
     */
    public function havuzHareketiIptal(int $hareketId, string $aciklama, int $kullaniciId): array
    {
        $aciklama = trim($aciklama);
        if ($aciklama === '') {
            throw new Exception('İptal gerekçesi zorunludur.');
        }

        $grup = $this->Hareket->grubuGetir($hareketId);
        if (empty($grup)) {
            throw new Exception('Hareket kaydı bulunamadı.');
        }

        $sayimDetayId = 0;
        if (($grup[0]['referans_tipi'] ?? '') === 'aparat_sayim_detay') {
            $grup = array_values(array_filter($grup, static fn($h) => strpos((string) $h['aciklama'], 'İptal: ') !== 0 && (int) $h['iptal_mi'] === 0));
            if (empty($grup)) {
                throw new Exception('Bu hareket zaten geri alınmış.');
            }

            $sayimDetayId = (int) $grup[0]['referans_id'];
            $Sayim = new AparatSayimModel();
            $detay = $Sayim->detayGetir($sayimDetayId);
            $sayim = $detay ? $Sayim->getir((int) $detay['sayim_id']) : null;
            if (!$sayim || $sayim['durum'] !== 'acik') {
                throw new Exception('Bu sayım tamamlandığı veya iptal edildiği için geri alınamaz.');
            }
        }

        foreach ($grup as $h) {
            if ((int) $h['iptal_mi'] === 1) {
                throw new Exception('Bu hareket zaten geri alınmış.');
            }
            if (!in_array($h['referans_tipi'] ?? '', ['manuel', 'bozuk_iade', 'aparat_sayim_detay'], true)) {
                throw new Exception('Bu kayıt Saha İşlemleri veya Transferler sekmesinden geri alınmalıdır.');
            }
        }

        $kendiTransaction = !$this->db()->inTransaction();
        if ($kendiTransaction) {
            $this->db()->beginTransaction();
        }

        try {
            $sonuc = $this->Hareket->hareketleriTersle($grup, [
                'aciklama' => 'İptal: ' . $aciklama,
                'kaydeden_id' => $kullaniciId ?: null,
                'tarih' => date('Y-m-d H:i:s'),
            ]);

            if ($sayimDetayId > 0) {
                (new AparatSayimModel())->islendiSifirla($sayimDetayId);
            }
            if (!empty($grup[0]['referans_id']) && in_array($grup[0]['hareket_tipi'] ?? '', ['kayip', 'hurda'], true)) {
                (new \App\Model\PersonelKesintileriModel())->iptalByAparatGrup((int) $grup[0]['referans_id']);
            }

            if ($kendiTransaction) {
                $this->db()->commit();
            }
        } catch (Exception $e) {
            if ($kendiTransaction && $this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }

        $this->logla(
            $kullaniciId,
            'Aparat Hareket İptali',
            sprintf(
                'Hareket #%d (%s, %d satır) geri alındı. Gerekçe: %s',
                $hareketId,
                AparatHareketModel::HAREKET_TIPLERI[$grup[0]['hareket_tipi']] ?? $grup[0]['hareket_tipi'],
                count($grup),
                $aciklama
            ),
            SystemLogModel::LEVEL_IMPORTANT
        );

        return $sonuc;
    }

    private function kayipCezaKesintisiYaz(
        int $tipId,
        int $adet,
        float $birimCeza,
        float $toplamCeza,
        int $ekipId,
        int $personelId,
        int $grupId,
        int $kullaniciId,
        string $aciklama
    ): void {
        $hedefPersoneller = $personelId > 0
            ? [$personelId]
            : $this->Stok->ekipAktifPersonelIdleri($ekipId);

        if (empty($hedefPersoneller)) {
            return;
        }

        $tipAdi = $this->Stok->aparatTipAdi($tipId) ?: ('Aparat #' . $tipId);
        $ekipEtiket = $ekipId > 0 ? ($this->Stok->ekipAdi($ekipId) ?: ('Ekip #' . $ekipId)) : 'Personel';
        $n = count($hedefPersoneller);
        $toplamKurus = (int) round($toplamCeza * 100);
        $payKurus = intdiv($toplamKurus, $n);
        $kalanKurus = $toplamKurus % $n;

        $kesintiNotu = sprintf(
            '%s — %d adet kayıp aparat cezası (%s, Birim: %s ₺%s)%s',
            $tipAdi,
            $adet,
            $ekipEtiket,
            number_format($birimCeza, 2, ',', '.'),
            $n > 1 ? ', ' . $n . ' kişiye bölündü' : '',
            $aciklama !== '' ? ' · ' . $aciklama : ''
        );

        $Kesinti = new \App\Model\PersonelKesintileriModel();
        foreach ($hedefPersoneller as $index => $pid) {
            $kisiKurus = $payKurus + ($index < $kalanKurus ? 1 : 0);
            if ($kisiKurus <= 0) {
                continue;
            }
            $Kesinti->ekleAparatCezasi([
                'personel_id' => $pid,
                'aparat_hareket_grup_id' => $grupId,
                'tutar' => $kisiKurus / 100,
                'aciklama' => $kesintiNotu,
                'kayit_yapan' => $kullaniciId,
            ]);
        }
    }

    // =====================================================
    // SAYIM FARKI
    // =====================================================

    /**
     * Sayım farkını stoğa işler. Eksik çıkan aparat kayıp havuzuna yazılır,
     * fazla çıkan aparat kayıp havuzundan düşülür; böylece toplam adet korunur.
     */
    public function sayimFarkiIsle(int $detayId, int $kullaniciId, ?int $personelId = null): bool
    {
        $Sayim = new AparatSayimModel();
        $detay = $Sayim->detayGetir($detayId);

        if (!$detay) {
            throw new Exception('Sayım satırı bulunamadı.');
        }
        if ((int) $detay['islendi'] === 1) {
            throw new Exception('Bu satır daha önce işlenmiş.');
        }
        if ($detay['sayilan_adet'] === null) {
            throw new Exception('Sayım adedi girilmemiş.');
        }

        $fark = (int) $detay['fark'];
        if ($fark === 0) {
            $Sayim->islendiIsaretle($detayId);
            return true;
        }

        $ekipId = (int) $detay['ekip_id'];
        $tipId = (int) $detay['aparat_tip_id'];

        $kendiTransaction = !$this->db()->inTransaction();
        if ($kendiTransaction) {
            $this->db()->beginTransaction();
        }

        try {
            $sahipPersonel = $ekipId < 0;
            $this->Hareket->uygula([
                ['sahip_tipi' => $sahipPersonel ? 'personel' : 'ekip', 'sahip_id' => abs($ekipId), 'aparat_tip_id' => $tipId, 'adet' => $fark],
                ['sahip_tipi' => 'kayip', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => -$fark],
            ], [
                'hareket_tipi' => 'sayim_duzeltme',
                'ekip_id' => $sahipPersonel ? null : $ekipId,
                'personel_id' => $sahipPersonel ? abs($ekipId) : $personelId,
                'referans_tipi' => 'aparat_sayim_detay',
                'referans_id' => $detayId,
                'aciklama' => sprintf('Sayım farkı %+d. %s', $fark, (string) $detay['aciklama']),
                'kaydeden_id' => $kullaniciId ?: null,
            ]);

            $Sayim->islendiIsaretle($detayId);

            if ($kendiTransaction) {
                $this->db()->commit();
            }
        } catch (Exception $e) {
            if ($kendiTransaction && $this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }

        $this->logla($kullaniciId, 'Aparat Sayım Düzeltmesi',
            sprintf('%s #%d, tip #%d: %+d adet düzeltme işlendi.', $ekipId < 0 ? 'Personel' : 'Ekip', abs($ekipId), $tipId, $fark),
            SystemLogModel::LEVEL_IMPORTANT);

        return true;
    }

    public function havuzHareketleriGeriAl(array $grupIdleri, int $kullaniciId, int $dakika = 15): array
    {
        $grupIdleri = array_values(array_unique(array_filter(array_map('intval', $grupIdleri))));
        if (empty($grupIdleri)) {
            throw new Exception('Geri alınacak kayıt bulunamadı.');
        }

        $kendiTransaction = !$this->db()->inTransaction();
        if ($kendiTransaction) {
            $this->db()->beginTransaction();
        }

        $negatif = false;

        try {
            foreach ($grupIdleri as $grupId) {
                $grup = $this->Hareket->grubuGetir($grupId);
                if (empty($grup)) {
                    throw new Exception('Geri alınacak kayıt bulunamadı.');
                }
                if ((int) ($grup[0]['kaydeden_id'] ?? 0) !== $kullaniciId
                    || strtotime((string) $grup[0]['tarih']) < time() - $dakika * 60) {
                    throw new Exception('Bu kayıt artık hızlı geri alınamaz. Hareket Dökümü sekmesinden geri alabilirsiniz.');
                }

                $sonuc = $this->havuzHareketiIptal($grupId, 'Hemen geri alındı', $kullaniciId);
                $negatif = $negatif || !empty($sonuc['negatif']);
            }

            if ($kendiTransaction) {
                $this->db()->commit();
            }
        } catch (Exception $e) {
            if ($kendiTransaction && $this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }

        return ['adet' => count($grupIdleri), 'negatif' => $negatif];
    }

    public function sayimSatiriGeriAl(int $detayId, int $kullaniciId): array
    {
        $hareketler = $this->Hareket->referansHareketleri('aparat_sayim_detay', $detayId);
        if (empty($hareketler)) {
            throw new Exception('Bu satırda geri alınacak bir stok işlemi yok.');
        }

        return $this->havuzHareketiIptal((int) $hareketler[0]['id'], 'Sayım satırı geri alındı', $kullaniciId);
    }

    public function otomatikKirmaSenkronize(int $firmaId): array
    {
        return $this->otomatikOnlineSenkronize($firmaId);
    }

    public function otomatikOnlineSenkronize(int $firmaId): array
    {
        $sonuc = ['islenen' => 0, 'bekleyen' => 0, 'kesme' => 0, 'acma' => 0, 'hurda' => 0];
        if ($firmaId <= 0) {
            return $sonuc;
        }

        $Kirma = new AparatOtomatikKirmaModel();
        $kirmaBas = $Kirma->baslangicTarihi($firmaId, 'hurda');
        $kesmeAcmaBas = $Kirma->baslangicTarihi($firmaId, 'kesme');
        if (($kirmaBas === null && $kesmeAcmaBas === null) || !$Kirma->kilitAl($firmaId)) {
            return $sonuc;
        }

        try {
            $varsayilanTip = $Kirma->varsayilanTipId($firmaId);
            $telefon = $kesmeAcmaBas !== null ? $Kirma->telefonKayitlari($firmaId, $kesmeAcmaBas) : [];
            $kacakOnbellek = [];

            foreach ($Kirma->karsilastirma($firmaId, $kirmaBas, $kesmeAcmaBas) as $satir) {
                $islem = $satir['islem'];
                if (!in_array($islem, ['kesme', 'acma', 'hurda'], true)) {
                    continue;
                }

                $hedef = $satir['hedef'];
                if ($islem !== 'hurda') {
                    $telefonAnahtar = $satir['personel_id'] . '|' . $satir['tarih'] . '|' . $islem;
                    $dusulen = min($hedef, $telefon[$telefonAnahtar] ?? 0);
                    $hedef -= $dusulen;
                    if (isset($telefon[$telefonAnahtar])) {
                        $telefon[$telefonAnahtar] -= $dusulen;
                    }
                }

                $kayit = $satir['kayit'];
                $islenen = $kayit ? (int) $kayit['islenen_adet'] : 0;
                $uygulanan = $kayit ? (int) $kayit['uygulanan_adet'] : 0;
                if ($hedef === $islenen) {
                    continue;
                }

                $tipId = $kayit && (int) $kayit['aparat_tip_id'] > 0 ? (int) $kayit['aparat_tip_id'] : $varsayilanTip;
                if ($tipId <= 0) {
                    $sonuc['bekleyen'] += max(0, $hedef - $islenen);
                    continue;
                }

                $ekipId = $kayit && (int) $kayit['ekip_id'] > 0
                    ? (int) $kayit['ekip_id']
                    : $Kirma->ekipBul($firmaId, $satir['personel_id'], $satir['tarih']);
                $sahipTipi = 'ekip';
                $sahipId = $ekipId;
                if ($islem !== 'hurda' && $ekipId <= 0) {
                    $personelId = (int) $satir['personel_id'];
                    if (!isset($kacakOnbellek[$personelId])) {
                        $kacakOnbellek[$personelId] = $this->Stok->kacakKontrolPersoneliMi($firmaId, $personelId);
                    }
                    if (!$kacakOnbellek[$personelId]) {
                        continue;
                    }
                    $sahipTipi = 'personel';
                    $sahipId = $personelId;
                }

                if ($islem === 'acma') {
                    if ($hedef > $islenen) {
                        $sahada = max(0, $this->Stok->bakiyeFirma($firmaId, 'saha', 0, $tipId));
                        $yeniUygulanan = min($hedef, $uygulanan + min($hedef - $islenen, $sahada));
                    } else {
                        $yeniUygulanan = min($uygulanan, $hedef);
                    }
                } else {
                    $yeniUygulanan = $hedef;
                }
                $fark = $yeniUygulanan - $uygulanan;

                $satirlar = match ($islem) {
                    'kesme' => [
                        ['sahip_tipi' => $sahipTipi, 'sahip_id' => $sahipId, 'aparat_tip_id' => $tipId, 'adet' => -$fark],
                        ['sahip_tipi' => 'saha', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => $fark],
                    ],
                    'acma' => [
                        ['sahip_tipi' => 'saha', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => -$fark],
                        ['sahip_tipi' => $sahipTipi, 'sahip_id' => $sahipId, 'aparat_tip_id' => $tipId, 'adet' => $fark],
                    ],
                    default => [
                        ['sahip_tipi' => 'saha', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => -$fark],
                        ['sahip_tipi' => 'hurda', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => $fark],
                    ],
                };
                $isTarihi = date('d.m.Y', strtotime($satir['tarih']));
                $aciklama = match ($islem) {
                    'kesme' => $fark >= 0
                        ? "Online aparatlı kesim: zimmetten aboneye geçti (iş tarihi $isTarihi)."
                        : "Online kesim kaydı azaldı/silindi, aparat zimmete geri alındı (iş tarihi $isTarihi).",
                    'acma' => $fark >= 0
                        ? "Online açma: aparat aboneden açan ekibe döndü (iş tarihi $isTarihi)."
                        : "Online açma kaydı azaldı/silindi, aparat aboneye geri yazıldı (iş tarihi $isTarihi).",
                    default => $fark >= 0
                        ? "Aparat kırma: abonedeki aparat hurdaya ayrıldı (iş tarihi $isTarihi, puantajdan)."
                        : "Aparat kırma kaydı puantajdan kaldırıldı, hurda geri alındı (iş tarihi $isTarihi).",
                };

                $this->db()->beginTransaction();
                try {
                    $kayitId = $Kirma->kaydet($firmaId, [
                        'personel_id' => $satir['personel_id'],
                        'tarih' => $satir['tarih'],
                        'is_emri_sonucu_id' => $satir['is_emri_sonucu_id'],
                        'islem' => $islem,
                        'ekip_id' => $ekipId,
                        'aparat_tip_id' => $tipId,
                        'islenen_adet' => $hedef,
                        'uygulanan_adet' => $yeniUygulanan,
                    ]);

                    if ($fark !== 0) {
                        $this->Hareket->uygula($satirlar, [
                            'firma_id' => $firmaId,
                            'hareket_tipi' => $islem,
                            'ekip_id' => $ekipId ?: null,
                            'personel_id' => $satir['personel_id'],
                            'referans_tipi' => $islem === 'hurda' ? 'otomatik_kirma' : 'online_' . $islem,
                            'referans_id' => $kayitId,
                            'aciklama' => $aciklama,
                            'tarih' => $fark > 0 && !empty($satir['kayit_zamani'])
                                ? $satir['kayit_zamani']
                                : date('Y-m-d H:i:s'),
                        ]);
                    }

                    $this->db()->commit();
                    $sonuc['islenen'] += $fark;
                    $sonuc[$islem] += $fark;
                } catch (Exception $e) {
                    if ($this->db()->inTransaction()) {
                        $this->db()->rollBack();
                    }
                    error_log('Aparat online senkronu işlenemedi: ' . $e->getMessage());
                }
            }
        } finally {
            $Kirma->kilitBirak($firmaId);
        }

        if ($sonuc['kesme'] !== 0 || $sonuc['acma'] !== 0 || $sonuc['hurda'] !== 0) {
            $this->logla(0, 'Aparat Online Senkron', sprintf(
                'Puantajdan aparat hareketi işlendi (net): kesme %d, açma %d, kırma/hurda %d.',
                $sonuc['kesme'], $sonuc['acma'], $sonuc['hurda']
            ), SystemLogModel::LEVEL_IMPORTANT);
        }

        return $sonuc;
    }

    private function logla(int $kullaniciId, string $baslik, string $aciklama, int $seviye = SystemLogModel::LEVEL_INFO): void
    {
        try {
            $this->Log->logAction($kullaniciId, $baslik, $aciklama, $seviye);
        } catch (Exception $e) {
            error_log('Aparat log kaydı yazılamadı: ' . $e->getMessage());
        }
    }
}
