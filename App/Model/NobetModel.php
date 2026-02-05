<?php

namespace App\Model;

use App\Model\Model;
use PDO;

/**
 * Nöbet Yönetimi Model
 * - Nöbet atamaları
 * - Değişim talepleri
 * - Devir işlemleri
 */
class NobetModel extends Model
{
    protected $table = 'nobetler';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Tüm nöbetleri takvim formatında getirir
     * @param string $baslangic Başlangıç tarihi (Y-m-d)
     * @param string $bitis Bitiş tarihi (Y-m-d)
     * @return array
     */
    public function getCalendarEvents($baslangic, $bitis)
    {
        $sql = "SELECT n.*, p.adi_soyadi, p.departman, p.resim_yolu, p.cep_telefonu,
                t.tur_adi as ekip_adi, t.ekip_bolge,
                (SELECT COUNT(*) FROM nobet_degisim_talepleri dt WHERE dt.nobet_id = n.id AND dt.durum IN ('beklemede', 'personel_onayladi')) as has_talep
                FROM {$this->table} n
                LEFT JOIN personel p ON n.personel_id = p.id
                LEFT JOIN tanimlamalar t ON p.ekip_no = t.id
                WHERE n.firma_id = :firma_id 
                AND n.silinme_tarihi IS NULL
                AND n.nobet_tarihi BETWEEN :baslangic AND :bitis
                ORDER BY n.nobet_tarihi ASC, n.baslangic_saati ASC";

        $query = $this->db->prepare($sql);
        $query->execute([
            'firma_id' => $_SESSION['firma_id'],
            'baslangic' => $baslangic,
            'bitis' => $bitis
        ]);

        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personelin belirli bir tarihte başka nöbeti olup olmadığını kontrol eder
     * @param int $personel_id Personel ID
     * @param string $tarih Tarih (Y-m-d)
     * @param int|null $exclude_id Kontrol dışı bırakılacak nöbet ID (güncelleme işlemleri için)
     * @return bool Varsa true, yoksa false
     */
    public function hasNobetOnDate($personel_id, $tarih, $exclude_id = null)
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} 
                WHERE personel_id = :personel_id 
                AND nobet_tarihi = :tarih 
                AND silinme_tarihi IS NULL";

        $params = [
            'personel_id' => $personel_id,
            'tarih' => $tarih
        ];

        if ($exclude_id) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $exclude_id;
        }

        $query = $this->db->prepare($sql);
        $query->execute($params);
        return $query->fetchColumn() > 0;
    }

    /**
     * Personelin nöbetlerini getirir
     */
    public function getPersonelNobetleri($personel_id, $baslangic = null, $bitis = null)
    {
        $sql = "SELECT n.*, p.adi_soyadi, p.departman
                FROM {$this->table} n
                LEFT JOIN personel p ON n.personel_id = p.id
                WHERE n.personel_id = :personel_id 
                AND n.silinme_tarihi IS NULL";

        $params = ['personel_id' => $personel_id];

        if ($baslangic && $bitis) {
            $sql .= " AND n.nobet_tarihi BETWEEN :baslangic AND :bitis";
            $params['baslangic'] = $baslangic;
            $params['bitis'] = $bitis;
        }

        $sql .= " ORDER BY n.nobet_tarihi ASC";

        $query = $this->db->prepare($sql);
        $query->execute($params);

        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Nöbet ekler
     */
    public function addNobet($data)
    {
        $sql = "INSERT INTO {$this->table} 
                (firma_id, personel_id, nobet_tarihi, baslangic_saati, bitis_saati, nobet_tipi, aciklama, olusturan_id, olusturma_tarihi)
                VALUES 
                (:firma_id, :personel_id, :nobet_tarihi, :baslangic_saati, :bitis_saati, :nobet_tipi, :aciklama, :olusturan_id, NOW())";

        $query = $this->db->prepare($sql);
        $result = $query->execute([
            'firma_id' => $_SESSION['firma_id'],
            'personel_id' => $data['personel_id'],
            'nobet_tarihi' => $data['nobet_tarihi'],
            'baslangic_saati' => $data['baslangic_saati'] ?? '18:00:00',
            'bitis_saati' => $data['bitis_saati'] ?? '08:00:00',
            'nobet_tipi' => $data['nobet_tipi'] ?? 'standart',
            'aciklama' => $data['aciklama'] ?? null,
            'olusturan_id' => $_SESSION['user_id'] ?? null
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Nöbet günceller
     */
    public function updateNobet($id, $data)
    {
        $setClauses = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            $setClauses[] = "$key = :$key";
            $params[$key] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $query = $this->db->prepare($sql);
        return $query->execute($params);
    }

    /**
     * Nöbet siler (soft delete)
     */
    public function deleteNobet($id)
    {
        $sql = "UPDATE {$this->table} SET silinme_tarihi = NOW() WHERE id = :id";
        $query = $this->db->prepare($sql);
        return $query->execute(['id' => $id]);
    }

    /**
     * Nöbet taşır (sürükle-bırak için)
     */
    public function moveNobet($id, $yeni_tarih, $personel_id = null)
    {
        $data = ['nobet_tarihi' => $yeni_tarih];

        if ($personel_id !== null) {
            $data['personel_id'] = $personel_id;
        }

        return $this->updateNobet($id, $data);
    }

    /**
     * Belirli bir tarihteki nöbetleri getirir
     */
    public function getNobetlerByTarih($tarih)
    {
        $sql = "SELECT n.*, p.adi_soyadi, p.departman, p.resim_yolu, p.cep_telefonu,
                t.tur_adi as ekip_adi, t.ekip_bolge
                FROM {$this->table} n
                LEFT JOIN personel p ON n.personel_id = p.id
                LEFT JOIN tanimlamalar t ON p.ekip_no = t.id
                WHERE n.firma_id = :firma_id 
                AND n.silinme_tarihi IS NULL
                AND n.nobet_tarihi = :tarih
                ORDER BY n.baslangic_saati ASC";

        $query = $this->db->prepare($sql);
        $query->execute([
            'firma_id' => $_SESSION['firma_id'],
            'tarih' => $tarih
        ]);

        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tek bir nöbet kaydını getirir
     */
    public function find($id)
    {
        $sql = "SELECT n.*, p.adi_soyadi, p.departman, p.resim_yolu, p.cep_telefonu,
                t.tur_adi as ekip_adi, t.ekip_bolge
                FROM {$this->table} n
                LEFT JOIN personel p ON n.personel_id = p.id
                LEFT JOIN tanimlamalar t ON p.ekip_no = t.id
                WHERE n.id = :id AND n.silinme_tarihi IS NULL";

        $query = $this->db->prepare($sql);
        $query->execute(['id' => $id]);

        return $query->fetch(PDO::FETCH_OBJ);
    }

    // =====================================================
    // DEĞIŞIM TALEPLERI
    // =====================================================

    /**
     * Değişim talebi oluşturur
     */
    public function createDegisimTalebi($data)
    {
        $sql = "INSERT INTO nobet_degisim_talepleri 
                (nobet_id, talep_eden_id, talep_edilen_id, aciklama, durum, talep_tarihi)
                VALUES 
                (:nobet_id, :talep_eden_id, :talep_edilen_id, :aciklama, 'beklemede', NOW())";

        $query = $this->db->prepare($sql);
        $result = $query->execute([
            'nobet_id' => $data['nobet_id'],
            'talep_eden_id' => $data['talep_eden_id'],
            'talep_edilen_id' => $data['talep_edilen_id'],
            'aciklama' => $data['aciklama'] ?? null
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Değişim talebini günceller
     */
    public function updateDegisimTalebi($id, $data)
    {
        $setClauses = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            $setClauses[] = "$key = :$key";
            $params[$key] = $value;
        }

        $sql = "UPDATE nobet_degisim_talepleri SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $query = $this->db->prepare($sql);
        return $query->execute($params);
    }

    /**
     * Personelin değişim taleplerini getirir (Gelen/Giden)
     */
    public function getPersonelDegisimTalepleri($personel_id, $tip = 'hepsi')
    {
        $sql = "SELECT dt.*, 
                n.nobet_tarihi, n.baslangic_saati, n.bitis_saati,
                p1.adi_soyadi as talep_eden_adi,
                p2.adi_soyadi as talep_edilen_adi,
                p3.adi_soyadi as onaylayan_adi
                FROM nobet_degisim_talepleri dt
                LEFT JOIN nobetler n ON dt.nobet_id = n.id
                LEFT JOIN personel p1 ON dt.talep_eden_id = p1.id
                LEFT JOIN personel p2 ON dt.talep_edilen_id = p2.id
                LEFT JOIN users p3 ON dt.amir_onaylayan_id = p3.id
                WHERE 1=1";

        $params = [];

        if ($tip == 'gelen') {
            $sql .= " AND dt.talep_edilen_id = :personel_id";
            $params['personel_id'] = $personel_id;
        } elseif ($tip == 'giden') {
            $sql .= " AND dt.talep_eden_id = :personel_id";
            $params['personel_id'] = $personel_id;
        } else {
            $sql .= " AND (dt.talep_eden_id = :personel_id1 OR dt.talep_edilen_id = :personel_id2)";
            $params['personel_id1'] = $personel_id;
            $params['personel_id2'] = $personel_id;
        }

        $sql .= " ORDER BY dt.talep_tarihi DESC";

        $query = $this->db->prepare($sql);
        $query->execute($params);

        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Bekleyen değişim taleplerini getirir (Amir için)
     */
    public function getBekleyenDegisimTalepleri()
    {
        $sql = "SELECT dt.*, 
                n.nobet_tarihi, n.baslangic_saati, n.bitis_saati, n.firma_id,
                p1.adi_soyadi as talep_eden_adi, p1.departman as talep_eden_departman,
                p2.adi_soyadi as talep_edilen_adi, p2.departman as talep_edilen_departman
                FROM nobet_degisim_talepleri dt
                LEFT JOIN nobetler n ON dt.nobet_id = n.id
                LEFT JOIN personel p1 ON dt.talep_eden_id = p1.id
                LEFT JOIN personel p2 ON dt.talep_edilen_id = p2.id
                WHERE dt.durum = 'personel_onayladi' 
                AND n.firma_id = :firma_id
                ORDER BY dt.talep_tarihi ASC";

        $query = $this->db->prepare($sql);
        $query->execute(['firma_id' => $_SESSION['firma_id']]);

        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Değişim talebini karşı taraf onaylar
     */
    public function onaylaPersonelTalebi($talep_id)
    {
        return $this->updateDegisimTalebi($talep_id, [
            'durum' => 'personel_onayladi',
            'personel_onay_tarihi' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Değişim talebini amir onaylar ve nöbeti değiştirir
     */
    public function onaylaAmirTalebi($talep_id, $amir_id)
    {
        // Talep bilgilerini al
        $sql = "SELECT * FROM nobet_degisim_talepleri WHERE id = :id";
        $query = $this->db->prepare($sql);
        $query->execute(['id' => $talep_id]);
        $talep = $query->fetch(PDO::FETCH_OBJ);

        if (!$talep) {
            throw new \Exception("Talep bulunamadı.");
        }

        // Nöbetin personelini değiştir
        $this->updateNobet($talep->nobet_id, [
            'personel_id' => $talep->talep_edilen_id
        ]);

        // Talebi güncelle
        return $this->updateDegisimTalebi($talep_id, [
            'durum' => 'onaylandi',
            'amir_onaylayan_id' => $amir_id,
            'amir_onay_tarihi' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Değişim talebini reddeder
     */
    public function reddetTalebi($talep_id, $reddeden_id, $red_nedeni = null)
    {
        return $this->updateDegisimTalebi($talep_id, [
            'durum' => 'reddedildi',
            'amir_onaylayan_id' => $reddeden_id,
            'red_nedeni' => $red_nedeni,
            'amir_onay_tarihi' => date('Y-m-d H:i:s')
        ]);
    }

    // =====================================================
    // NÖBET DEVİR İŞLEMLERİ
    // =====================================================

    /**
     * Nöbet devri yapar
     */
    public function devirYap($nobet_id, $personel_id)
    {
        $sql = "INSERT INTO nobet_devir_kayitlari 
                (nobet_id, devralan_personel_id, devir_zamani, konum_lat, konum_lng)
                VALUES 
                (:nobet_id, :personel_id, NOW(), :lat, :lng)";

        $query = $this->db->prepare($sql);
        $result = $query->execute([
            'nobet_id' => $nobet_id,
            'personel_id' => $personel_id,
            'lat' => $_POST['lat'] ?? null,
            'lng' => $_POST['lng'] ?? null
        ]);

        if ($result) {
            // Nöbet durumunu ve asıl personelini güncelle
            // Durum 'devir_alindi' yerine 'standart' veya NULL yapılabilir ki mazeret listesinden düşsün 
            // ve takvimde normal görünsün. Ama sistemde 'devir_alindi' özel rengi varsa o da kalabilir.
            $this->updateNobet($nobet_id, [
                'durum' => 'devir_alindi',
                'personel_id' => $personel_id
            ]);
        }

        return $result ? true : false;
    }

    /**
     * Devir kayıtlarını getirir
     */
    public function getDevirKayitlari($nobet_id)
    {
        $sql = "SELECT dk.*, p.adi_soyadi
                FROM nobet_devir_kayitlari dk
                LEFT JOIN personel p ON dk.devralan_personel_id = p.id
                WHERE dk.nobet_id = :nobet_id
                ORDER BY dk.devir_zamani DESC";

        $query = $this->db->prepare($sql);
        $query->execute(['nobet_id' => $nobet_id]);

        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    // =====================================================
    // İSTATİSTİKLER
    // =====================================================

    /**
     * Personel nöbet istatistikleri
     */
    public function getPersonelNobetIstatistikleri($personel_id, $yil = null, $ay = null)
    {
        $yil = $yil ?? date('Y');
        $ay = $ay ?? date('m');

        $sql = "SELECT 
                COUNT(*) as toplam_nobet,
                SUM(CASE WHEN nobet_tipi = 'hafta_sonu' THEN 1 ELSE 0 END) as hafta_sonu_nobet,
                SUM(CASE WHEN nobet_tipi = 'resmi_tatil' THEN 1 ELSE 0 END) as resmi_tatil_nobet
                FROM {$this->table}
                WHERE personel_id = :personel_id 
                AND silinme_tarihi IS NULL
                AND YEAR(nobet_tarihi) = :yil
                AND MONTH(nobet_tarihi) = :ay";

        $query = $this->db->prepare($sql);
        $query->execute([
            'personel_id' => $personel_id,
            'yil' => $yil,
            'ay' => $ay
        ]);

        return $query->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Aylık nöbet dağılımı (tüm personeller)
     */
    public function getAylikNobetDagilimi($yil, $ay)
    {
        $sql = "SELECT p.id, p.adi_soyadi, p.departman, p.resim_yolu,
                COUNT(n.id) as nobet_sayisi,
                t.tur_adi as ekip_adi, t.ekip_bolge
                FROM personel p
                LEFT JOIN {$this->table} n ON p.id = n.personel_id 
                    AND YEAR(n.nobet_tarihi) = :yil 
                    AND MONTH(n.nobet_tarihi) = :ay
                    AND n.silinme_tarihi IS NULL
                LEFT JOIN tanimlamalar t ON p.ekip_no = t.id
                WHERE p.firma_id = :firma_id 
                AND p.silinme_tarihi IS NULL 
                AND p.aktif_mi = 1
                GROUP BY p.id
                ORDER BY nobet_sayisi DESC";

        $query = $this->db->prepare($sql);
        $query->execute([
            'firma_id' => $_SESSION['firma_id'],
            'yil' => $yil,
            'ay' => $ay
        ]);

        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Yaklaşan nöbetleri getirir (Bildirim için)
     * @param int $saat Kaç saat içindeki nöbetler
     */
    public function getYaklasanNobetler($saat = 24)
    {
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', strtotime("+{$saat} hours"));

        $sql = "SELECT n.*, p.adi_soyadi, p.cep_telefonu, p.email_adresi,
                t.tur_adi as ekip_adi
                FROM {$this->table} n
                LEFT JOIN personel p ON n.personel_id = p.id
                LEFT JOIN tanimlamalar t ON p.ekip_no = t.id
                WHERE n.firma_id = :firma_id 
                AND n.silinme_tarihi IS NULL
                AND n.bildirim_gonderildi = 0
                AND CONCAT(n.nobet_tarihi, ' ', n.baslangic_saati) BETWEEN :now AND :future
                ORDER BY n.nobet_tarihi ASC, n.baslangic_saati ASC";

        $query = $this->db->prepare($sql);
        $query->execute([
            'firma_id' => $_SESSION['firma_id'],
            'now' => $now,
            'future' => $future
        ]);

        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Bildirim gönderildi olarak işaretle
     */
    public function bildirimGonderildi($nobet_id)
    {
        return $this->updateNobet($nobet_id, [
            'bildirim_gonderildi' => 1,
            'bildirim_tarihi' => date('Y-m-d H:i:s')
        ]);
    }

    // =====================================================
    // PWA PUSH BİLDİRİMLERİ
    // =====================================================

    /**
     * Nöbet atandığında personele PWA bildirimi gönderir
     * @param int $personel_id Personel ID
     * @param string $nobet_tarihi Nöbet tarihi (Y-m-d)
     * @return bool
     */
    public function sendNobetAtamaBildirimi($personel_id, $nobet_tarihi)
    {
        try {
            $pushService = new \App\Service\PushNotificationService();

            // Tarihi formatla
            $tarihFormatli = date('d.m.Y', strtotime($nobet_tarihi));
            $gun = $this->getGunAdi($nobet_tarihi);

            $payload = [
                'title' => '📅 Nöbet Ataması',
                'body' => "{$tarihFormatli} ({$gun}) tarihine nöbet atandınız.",
                'url' => 'index?p=nobet/list'
            ];

            // Bildirim logla
            $this->logBildirim($personel_id, 'atama', $payload);

            return $pushService->sendToPersonel($personel_id, $payload);
        } catch (\Exception $e) {
            error_log("Nöbet atama bildirimi hatası: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Nöbet hatırlatma bildirimi gönderir (Cron job için)
     * @param int $saat Kaç saat önce hatırlatma
     * @return int Gönderilen bildirim sayısı
     */
    public function sendNobetHatirlatmaBildirimleri($saat = 24)
    {
        $gonderildi = 0;
        $nobetler = $this->getYaklasanNobetler($saat);

        $pushService = new \App\Service\PushNotificationService();

        foreach ($nobetler as $nobet) {
            try {
                $tarihFormatli = date('d.m.Y', strtotime($nobet->nobet_tarihi));
                $saatFormatli = date('H:i', strtotime($nobet->baslangic_saati));

                $payload = [
                    'title' => "⏰ Nöbet Hatırlatma",
                    'body' => "{$tarihFormatli} saat {$saatFormatli}'de nöbetiniz başlayacak.",
                    'url' => 'index?p=nobet/list'
                ];

                if ($pushService->sendToPersonel($nobet->personel_id, $payload)) {
                    $this->bildirimGonderildi($nobet->id);
                    $this->logBildirim($nobet->personel_id, 'hatirlatma', $payload);
                    $gonderildi++;
                }
            } catch (\Exception $e) {
                error_log("Nöbet hatırlatma bildirimi hatası: " . $e->getMessage());
            }
        }

        return $gonderildi;
    }

    /**
     * Değişim talebi bildirimi gönderir
     * @param int $personel_id Alıcı personel
     * @param string $talep_eden_adi Talep eden kişinin adı
     * @param string $nobet_tarihi Nöbet tarihi
     * @return bool
     */
    public function sendDegisimTalepBildirimi($personel_id, $talep_eden_adi, $nobet_tarihi)
    {
        try {
            $pushService = new \App\Service\PushNotificationService();

            $tarihFormatli = date('d.m.Y', strtotime($nobet_tarihi));

            $payload = [
                'title' => '🔄 Nöbet Değişim Talebi',
                'body' => "{$talep_eden_adi}, {$tarihFormatli} tarihli nöbetini sizinle değiştirmek istiyor.",
                'url' => 'index?p=nobet/list#talepler'
            ];

            $this->logBildirim($personel_id, 'degisim_talebi', $payload);

            return $pushService->sendToPersonel($personel_id, $payload);
        } catch (\Exception $e) {
            error_log("Değişim talebi bildirimi hatası: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Değişim talebi onay/red bildirimi gönderir
     * @param int $personel_id Alıcı personel
     * @param string $durum 'onaylandi' veya 'reddedildi'
     * @param string $nobet_tarihi Nöbet tarihi
     * @return bool
     */
    public function sendDegisimSonucBildirimi($personel_id, $durum, $nobet_tarihi)
    {
        try {
            $pushService = new \App\Service\PushNotificationService();

            $tarihFormatli = date('d.m.Y', strtotime($nobet_tarihi));

            if ($durum === 'onaylandi') {
                $payload = [
                    'title' => '✅ Değişim Talebi Onaylandı',
                    'body' => "{$tarihFormatli} tarihli nöbet değişim talebiniz onaylandı.",
                    'url' => 'index?p=nobet/list'
                ];
            } else {
                $payload = [
                    'title' => '❌ Değişim Talebi Reddedildi',
                    'body' => "{$tarihFormatli} tarihli nöbet değişim talebiniz reddedildi.",
                    'url' => 'index?p=nobet/list'
                ];
            }

            $this->logBildirim($personel_id, 'degisim_sonuc', $payload);

            return $pushService->sendToPersonel($personel_id, $payload);
        } catch (\Exception $e) {
            error_log("Değişim sonuç bildirimi hatası: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bildirim loglar
     */
    private function logBildirim($personel_id, $tip, $payload)
    {
        try {
            $sql = "INSERT INTO nobet_bildirim_loglari 
                    (personel_id, bildirim_turu, baslik, mesaj, gonderim_durumu, gonderim_tarihi)
                    VALUES 
                    (:personel_id, :tip, :baslik, :mesaj, 'gonderildi', NOW())";

            $query = $this->db->prepare($sql);
            $query->execute([
                'personel_id' => $personel_id,
                'tip' => $tip,
                'baslik' => $payload['title'] ?? '',
                'mesaj' => $payload['body'] ?? ''
            ]);
        } catch (\Exception $e) {
            // Loglama hatası için sessizce devam et
            error_log("Bildirim loglama hatası: " . $e->getMessage());
        }
    }

    /**
     * Gün adını getirir
     */
    private function getGunAdi($tarih)
    {
        $gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
        return $gunler[date('w', strtotime($tarih))];
    }

    /**
     * Tüm değişim taleplerini getir (Yönetici görünümü)
     */
    public function getAllDegisimTalepleri($ay = null, $yil = null)
    {
        $firma_id = $_SESSION['firma_id'] ?? null;
        $params = [':firma_id' => $firma_id];
        $where = "WHERE n.firma_id = :firma_id";

        if ($ay && $yil) {
            $where .= " AND MONTH(n.nobet_tarihi) = :ay AND YEAR(n.nobet_tarihi) = :yil";
            $params[':ay'] = $ay;
            $params[':yil'] = $yil;
        }

        $sql = "SELECT dt.*, 
                n.nobet_tarihi, n.baslangic_saati, n.bitis_saati,
                pe.adi_soyadi as talep_eden_adi,
                ped.adi_soyadi as talep_edilen_adi
                FROM nobet_degisim_talepleri dt
                LEFT JOIN nobetler n ON dt.nobet_id = n.id
                LEFT JOIN personel pe ON dt.talep_eden_id = pe.id
                LEFT JOIN personel ped ON dt.talep_edilen_id = ped.id
                $where
                ORDER BY 
                    CASE dt.durum WHEN 'personel_onayladi' THEN 1 WHEN 'beklemede' THEN 2 ELSE 3 END,
                    dt.talep_tarihi DESC
                LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Mazeret bildirilmiş nöbetleri getir (Yönetici görünümü)
     */
    public function getMazeretBildirimleri($ay = null, $yil = null)
    {
        $firma_id = $_SESSION['firma_id'] ?? null;
        $params = [':firma_id' => $firma_id];
        $where = "WHERE n.firma_id = :firma_id AND n.durum = 'mazeret_bildirildi'";

        if ($ay && $yil) {
            $where .= " AND MONTH(n.nobet_tarihi) = :ay AND YEAR(n.nobet_tarihi) = :yil";
            $params[':ay'] = $ay;
            $params[':yil'] = $yil;
        } else {
            $where .= " AND n.nobet_tarihi >= CURDATE()";
        }

        $sql = "SELECT n.*, 
                p.adi_soyadi as personel_adi
                FROM nobetler n
                LEFT JOIN personel p ON n.personel_id = p.id
                $where
                ORDER BY n.nobet_tarihi ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Talep istatistiklerini getirir (Yönetici Dashboard için)
     */
    public function getTalepIstatistikleri($ay = null, $yil = null)
    {
        $firma_id = $_SESSION['firma_id'] ?? null;

        if ($ay && $yil) {
            $bas_tarih = "$yil-$ay-01";
            $bit_tarih = date('Y-m-t', strtotime($bas_tarih));
        } else {
            $bas_tarih = date('Y-m-01');
            $bit_tarih = date('Y-m-t');
        }

        $stats = [
            'onaylanan' => 0,
            'reddedilen' => 0
        ];

        // Onaylananlar
        $sql = "SELECT COUNT(*) FROM nobet_degisim_talepleri dt
                LEFT JOIN nobetler n ON dt.nobet_id = n.id
                WHERE n.firma_id = :firma_id 
                AND dt.durum = 'onaylandi'
                AND dt.amir_onay_tarihi BETWEEN :bas AND :bit";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['firma_id' => $firma_id, 'bas' => $bas_tarih, 'bit' => $bit_tarih]);
        $stats['onaylanan'] = $stmt->fetchColumn();

        // Reddedilenler
        $sql = "SELECT COUNT(*) FROM nobet_degisim_talepleri dt
                LEFT JOIN nobetler n ON dt.nobet_id = n.id
                WHERE n.firma_id = :firma_id 
                AND dt.durum = 'reddedildi'
                AND dt.amir_onay_tarihi BETWEEN :bas AND :bit";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['firma_id' => $firma_id, 'bas' => $bas_tarih, 'bit' => $bit_tarih]);
        $stats['reddedilen'] = $stmt->fetchColumn();

        return $stats;
    }
}

