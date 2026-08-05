<?php

namespace App\Service;

use App\Model\AparatHareketModel;
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

        $this->db()->beginTransaction();

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

            $this->db()->commit();
        } catch (Exception $e) {
            if ($this->db()->inTransaction()) {
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
        $hedefHavuz = match ($aparatDurumu) {
            'hasarli' => 'hurda',
            'bulunamadi' => 'kayip',
            default => 'ekip',
        };

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

        $this->db()->beginTransaction();

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

            $this->db()->commit();
        } catch (Exception $e) {
            if ($this->db()->inTransaction()) {
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

        $transferId = $this->Transfer->ekle($veri);

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

        $this->db()->beginTransaction();

        try {
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

            $this->db()->commit();
        } catch (Exception $e) {
            if ($this->db()->inTransaction()) {
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
        $aciklama = trim((string) ($veri['aciklama'] ?? ''));

        if ($tipId <= 0 || $adet <= 0) {
            throw new Exception('Aparat tipi ve adet geçerli olmalıdır.');
        }

        $ekipGerekli = in_array($tur, ['depo_cikis', 'depo_iade', 'hurda', 'kayip', 'acilis'], true);
        if ($ekipGerekli) {
            if ($ekipId <= 0 || !$this->Stok->ekipGecerliMi($ekipId)) {
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
                ['sahip_tipi' => 'ekip', 'sahip_id' => $ekipId, 'aparat_tip_id' => $tipId, 'adet' => $adet],
            ],
            'depo_iade' => [
                ['sahip_tipi' => 'ekip', 'sahip_id' => $ekipId, 'aparat_tip_id' => $tipId, 'adet' => -$adet],
                ['sahip_tipi' => 'depo', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => $adet],
            ],
            'hurda' => [
                ['sahip_tipi' => 'ekip', 'sahip_id' => $ekipId, 'aparat_tip_id' => $tipId, 'adet' => -$adet],
                ['sahip_tipi' => 'hurda', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => $adet],
            ],
            'kayip' => [
                ['sahip_tipi' => 'ekip', 'sahip_id' => $ekipId, 'aparat_tip_id' => $tipId, 'adet' => -$adet],
                ['sahip_tipi' => 'kayip', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => $adet],
            ],
            'acilis' => [
                ['sahip_tipi' => 'ekip', 'sahip_id' => $ekipId, 'aparat_tip_id' => $tipId, 'adet' => $adet],
            ],
            default => throw new Exception('Geçersiz hareket türü.'),
        };

        $kendiTransaction = !$this->db()->inTransaction();
        if ($kendiTransaction) {
            $this->db()->beginTransaction();
        }

        try {
            $sonuc = $this->Hareket->uygula($satirlar, [
                'hareket_tipi' => $tur === 'acilis' ? 'acilis' : $tur,
                'ekip_id' => $ekipGerekli ? $ekipId : null,
                'referans_tipi' => 'manuel',
                'referans_id' => null,
                'aciklama' => $aciklama ?: null,
                'kaydeden_id' => $kullaniciId ?: null,
            ]);

            // Bir havuz hareketi birden fazla satır yazabilir (ör. depo çıkışı: depo -N, ekip +N).
            // Grubun tamamını birlikte geri alabilmek için ilk satırın id'sini grup kimliği yap.
            $this->Hareket->grupKimligiAta($sonuc['hareket_ids']);

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
            'Aparat Havuz Hareketi',
            sprintf('%s: tip #%d, %d adet%s', AparatHareketModel::HAREKET_TIPLERI[$tur === 'acilis' ? 'acilis' : $tur],
                $tipId, $adet, $ekipGerekli ? ', ekip: ' . $this->Stok->ekipAdi($ekipId) : ''),
            SystemLogModel::LEVEL_IMPORTANT
        );

        return $sonuc;
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

        foreach ($grup as $h) {
            if ((int) $h['iptal_mi'] === 1) {
                throw new Exception('Bu hareket zaten iptal edilmiş.');
            }
            if (($h['referans_tipi'] ?? '') !== 'manuel') {
                throw new Exception('Bu hareket bağlı olduğu kaydın (işlem, transfer veya sayım) kendi ekranından iptal edilmelidir.');
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

        $this->db()->beginTransaction();

        try {
            $this->Hareket->uygula([
                ['sahip_tipi' => 'ekip', 'sahip_id' => $ekipId, 'aparat_tip_id' => $tipId, 'adet' => $fark],
                ['sahip_tipi' => 'kayip', 'sahip_id' => 0, 'aparat_tip_id' => $tipId, 'adet' => -$fark],
            ], [
                'hareket_tipi' => 'sayim_duzeltme',
                'ekip_id' => $ekipId,
                'personel_id' => $personelId,
                'referans_tipi' => 'aparat_sayim_detay',
                'referans_id' => $detayId,
                'aciklama' => sprintf('Sayım farkı %+d. %s', $fark, (string) $detay['aciklama']),
                'kaydeden_id' => $kullaniciId ?: null,
            ]);

            $Sayim->islendiIsaretle($detayId);

            $this->db()->commit();
        } catch (Exception $e) {
            if ($this->db()->inTransaction()) {
                $this->db()->rollBack();
            }
            throw $e;
        }

        $this->logla($kullaniciId, 'Aparat Sayım Düzeltmesi',
            sprintf('Ekip #%d, tip #%d: %+d adet düzeltme işlendi.', $ekipId, $tipId, $fark),
            SystemLogModel::LEVEL_IMPORTANT);

        return true;
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
