<?php

namespace App\Service;

use App\Helper\Security;
use App\Helper\Validator;
use App\Model\IkBasvuruModel;
use App\Model\PersonelEvrakModel;
use App\Model\PersonelModel;
use App\Model\SystemLogModel;
use DomainException;
use Throwable;

class IkBasvuruDonusturService
{
    public function donustur(object $basvuru, string $tc, string $iseGirisTarihi, int $firmaId, int $userId): array
    {
        if ($basvuru->durum === 'personele_donustu') {
            throw new DomainException('Bu aday zaten personele dönüştürülmüş.');
        }
        if (!Validator::tcKimlik($tc)) {
            throw new DomainException('Geçerli bir T.C. kimlik numarası girin.');
        }
        $tarih = \DateTime::createFromFormat('Y-m-d', $iseGirisTarihi);
        if (!$tarih || $tarih->format('Y-m-d') !== $iseGirisTarihi) {
            throw new DomainException('İşe giriş tarihi geçersiz.');
        }

        $Personel = new PersonelModel();
        if ($Personel->findByTc($tc) !== []) {
            throw new DomainException('Bu T.C. kimlik numarasıyla kayıtlı bir personel zaten var.');
        }

        $Basvuru = new IkBasvuruModel();
        $Evrak = new PersonelEvrakModel();
        $db = $Personel->getDb();
        $kopyaYol = null;

        $telefon = (string) $basvuru->telefon;
        $cepTelefonu = strlen($telefon) === 12 ? '0' . substr($telefon, 2) : $telefon;

        $resimYolu = $this->fotoAktar($basvuru, $firmaId);

        $db->beginTransaction();
        try {
            $veri = [
                'id' => 0,
                'firma_id' => $firmaId,
                'tc_kimlik_no' => $tc,
                'adi_soyadi' => $basvuru->ad_soyad,
                'ise_giris_tarihi' => $iseGirisTarihi,
                'cep_telefonu' => $cepTelefonu,
                'email_adresi' => $basvuru->email ?: null,
                'dogum_tarihi' => $basvuru->dogum_tarihi ?: null,
                'ehliyet_sinifi' => $basvuru->ehliyet ?: null,
                'resim_yolu' => $resimYolu,
                'aktif_mi' => 1,
                'olusturma_tarihi' => date('Y-m-d H:i:s'),
            ];
            $sifreliId = $Personel->saveWithAttr($veri);
            $personelId = (int) Security::decrypt((string) $sifreliId);
            if ($personelId <= 0) {
                throw new \RuntimeException('Personel kaydı oluşturulamadı.');
            }

            if (!empty($basvuru->cv_dosya)) {
                $kaynak = dirname(__DIR__, 2) . '/uploads/ik_basvuru/' . $firmaId . '/' . basename((string) $basvuru->cv_dosya);
                if (is_file($kaynak)) {
                    $dizin = dirname(__DIR__, 2) . '/uploads/personel_evraklar/' . $personelId . '/';
                    if (!is_dir($dizin) && !@mkdir($dizin, 0733, true) && !is_dir($dizin)) {
                        throw new \RuntimeException('Evrak klasörü oluşturulamadı.');
                    }
                    $dosyaAdi = bin2hex(random_bytes(16)) . '.enc';
                    $kopyaYol = $dizin . $dosyaAdi;
                    if (!@copy($kaynak, $kopyaYol)) {
                        $kopyaYol = null;
                        throw new \RuntimeException('CV evrakı kopyalanamadı.');
                    }
                    $Evrak->saveWithAttr([
                        'id' => 0,
                        'personel_id' => $personelId,
                        'evrak_adi' => 'CV',
                        'evrak_turu' => 'cv',
                        'dosya_adi' => $dosyaAdi,
                        'orijinal_dosya_adi' => preg_replace('/[^\p{L}\p{N} ._()-]/u', '', (string) $basvuru->ad_soyad) . ' - CV.pdf',
                        'dosya_boyutu' => (int) filesize($kopyaYol),
                        'dosya_tipi' => $basvuru->cv_mime ?: 'application/pdf',
                        'aciklama' => 'İşe alım başvurusundan aktarıldı (Başvuru ID: ' . (int) $basvuru->id . ')',
                        'yukleyen_id' => $userId > 0 ? $userId : null,
                        'aktif' => 1,
                    ]);
                }
            }

            if (!$Basvuru->personeleDonustuIsaretle((int) $basvuru->id, $firmaId, $personelId)) {
                throw new DomainException('Başvuru durumu güncellenemedi; aday daha önce dönüştürülmüş olabilir.');
            }
            $Basvuru->olayEkle($firmaId, (int) $basvuru->id, $userId, 'Personele dönüştürüldü', 'Personel ID: ' . $personelId);
            (new SystemLogModel())->logAction($userId, 'İşe Alım Personel Kaydı', 'Aday personel olarak eklendi: ' . $basvuru->ad_soyad . ' (Başvuru ID: ' . (int) $basvuru->id . ', Personel ID: ' . $personelId . ')', SystemLogModel::LEVEL_IMPORTANT);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($kopyaYol !== null) {
                @unlink($kopyaYol);
            }
            if ($resimYolu !== null) {
                @unlink(dirname(__DIR__, 2) . '/' . $resimYolu);
            }
            throw $e;
        }

        return ['personel_id' => $personelId, 'personel_enc_id' => Security::encrypt($personelId)];
    }

    private function fotoAktar(object $basvuru, int $firmaId): ?string
    {
        if (empty($basvuru->foto_dosya)) {
            return null;
        }
        $kaynak = dirname(__DIR__, 2) . '/uploads/ik_basvuru/' . $firmaId . '/' . basename((string) $basvuru->foto_dosya);
        if (!is_file($kaynak)) {
            return null;
        }
        try {
            $icerik = Security::decryptFile((string) file_get_contents($kaynak));
            $dizin = dirname(__DIR__, 2) . '/assets/images/users/';
            $ad = 'personel_' . bin2hex(random_bytes(12)) . '.jpg';
            if ($icerik === '' || @file_put_contents($dizin . $ad, $icerik, LOCK_EX) === false) {
                error_log('İK başvuru fotoğrafı personel klasörüne yazılamadı.');
                return null;
            }
            return 'assets/images/users/' . $ad;
        } catch (Throwable $e) {
            error_log('İK başvuru fotoğrafı aktarılamadı: ' . $e->getMessage());
            return null;
        }
    }
}
