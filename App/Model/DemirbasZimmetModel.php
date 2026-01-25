<?php

namespace App\Model;

use App\Helper\Date;
use App\Helper\Helper;
use App\Model\Model;
use App\Helper\Security;
use PDO;

class DemirbasZimmetModel extends Model
{
    protected $table = 'demirbas_zimmet';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Tüm zimmet kayıtlarını detaylı getirir
     */
    public function getAllWithDetails()
    {
        $sql = $this->db->prepare("
            SELECT 
                z.*,
                d.demirbas_no,
                d.demirbas_adi,
                d.marka,
                d.model,
                d.seri_no,
                k.kategori_adi,
                p.adi_soyadi AS personel_adi,
                p.cep_telefonu AS personel_telefon
            FROM {$this->table} z
            LEFT JOIN demirbas d ON z.demirbas_id = d.id
            LEFT JOIN demirbas_kategorileri k ON d.kategori_id = k.id
            LEFT JOIN personel p ON z.personel_id = p.id
            ORDER BY z.kayit_tarihi DESC
        ");
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personele ait zimmetleri getirir
     */
    public function getByPersonel($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT 
                z.*,
                d.demirbas_no,
                d.demirbas_adi,
                d.marka,
                d.model,
                d.seri_no,
                k.kategori_adi
            FROM {$this->table} z
            LEFT JOIN demirbas d ON z.demirbas_id = d.id
            LEFT JOIN demirbas_kategorileri k ON d.kategori_id = k.id
            WHERE z.personel_id = ?
            ORDER BY z.teslim_tarihi DESC
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Demirbaşa ait zimmetleri getirir
     */
    public function getByDemirbas($demirbas_id)
    {
        $sql = $this->db->prepare("
            SELECT 
                z.*,
                p.adi_soyadi AS personel_adi,
                p.cep_telefonu AS personel_telefon
            FROM {$this->table} z
            LEFT JOIN personel p ON z.personel_id = p.id
            WHERE z.demirbas_id = ?
            ORDER BY z.teslim_tarihi DESC
        ");
        $sql->execute([$demirbas_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Aktif zimmetleri getirir (iade edilmemiş)
     */
    public function getActiveZimmetler()
    {
        $sql = $this->db->prepare("
            SELECT 
                z.*,
                d.demirbas_no,
                d.demirbas_adi,
                d.marka,
                d.model,
                k.kategori_adi,
                p.adi_soyadi AS personel_adi
            FROM {$this->table} z
            LEFT JOIN demirbas d ON z.demirbas_id = d.id
            LEFT JOIN demirbas_kategorileri k ON d.kategori_id = k.id
            LEFT JOIN personel p ON z.personel_id = p.id
            WHERE z.durum = 'teslim'
            ORDER BY z.teslim_tarihi DESC
        ");
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Zimmet iade işlemi
     */
    public function iadeYap($zimmet_id, $iade_tarihi, $iade_miktar, $aciklama = null)
    {
        $this->db->beginTransaction();

        try {
            // Zimmet bilgisini al
            $zimmet = $this->find($zimmet_id);
            if (!$zimmet) {
                throw new \Exception("Zimmet kaydı bulunamadı.");
            }

            // Zimmet durumunu güncelle
            $sql = $this->db->prepare("
                UPDATE {$this->table} 
                SET durum = 'iade', 
                    iade_tarihi = ?, 
                    iade_miktar = ?,
                    aciklama = CONCAT(COALESCE(aciklama, ''), '\n', ?)
                WHERE id = ?
            ");
            $sql->execute([Date::Ymd($iade_tarihi), $iade_miktar, $aciklama ?? '', $zimmet_id]);

            // Demirbaş stok miktarını artır
            $sqlDemirbas = $this->db->prepare("
                UPDATE demirbas 
                SET kalan_miktar = kalan_miktar + ?
                WHERE id = ?
            ");
            $sqlDemirbas->execute([$iade_miktar, $zimmet->demirbas_id]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Yeni zimmet eklerken stok kontrolü ve düşürme
     */
    public function zimmetVer($data)
    {
        $this->db->beginTransaction();

        try {
            // Demirbaş stok kontrolü
            $demirbas = $this->db->prepare("SELECT * FROM demirbas WHERE id = ?");
            $demirbas->execute([$data['demirbas_id']]);
            $demirbasData = $demirbas->fetch(PDO::FETCH_OBJ);

            if (!$demirbasData) {
                throw new \Exception("Demirbaş bulunamadı.");
            }

            $teslim_miktar = $data['teslim_miktar'] ?? 1;

            if ($demirbasData->kalan_miktar < $teslim_miktar) {
                throw new \Exception("Yeterli stok bulunmuyor. Mevcut: {$demirbasData->kalan_miktar}");
            }

            // Zimmet kaydı oluştur
            $insertSql = $this->db->prepare("
                INSERT INTO {$this->table} 
                (demirbas_id, personel_id, teslim_tarihi, teslim_miktar, durum, aciklama, teslim_eden_id)
                VALUES (?, ?, ?, ?, 'teslim', ?, ?)
            ");
            $insertSql->execute([
                $data['demirbas_id'],
                $data['personel_id'],
                $data['teslim_tarihi'],
                $teslim_miktar,
                $data['aciklama'] ?? null,
                $data['teslim_eden_id'] ?? null
            ]);

            $lastId = $this->db->lastInsertId();

            // Stok miktarını düşür
            $updateStock = $this->db->prepare("
                UPDATE demirbas 
                SET kalan_miktar = kalan_miktar - ?
                WHERE id = ?
            ");
            $updateStock->execute([$teslim_miktar, $data['demirbas_id']]);

            $this->db->commit();
            return Security::encrypt($lastId);
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Zimmet özet istatistikleri
     */
    public function getStats()
    {
        $sql = $this->db->prepare("
            SELECT 
                COUNT(CASE WHEN durum = 'teslim' THEN 1 END) as aktif_zimmet,
                COUNT(CASE WHEN durum = 'iade' THEN 1 END) as iade_edilen,
                COUNT(CASE WHEN durum = 'kayip' THEN 1 END) as kayip,
                COUNT(*) as toplam
            FROM {$this->table}
        ");
        $sql->execute();
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Tablo satırı oluştur
     */
    public function getTableRow($id)
    {
        $sql = $this->db->prepare("
            SELECT 
                z.*,
                d.demirbas_no,
                d.demirbas_adi,
                d.marka,
                d.model,
                k.kategori_adi,
                p.adi_soyadi AS personel_adi
            FROM {$this->table} z
            LEFT JOIN demirbas d ON z.demirbas_id = d.id
            LEFT JOIN demirbas_kategorileri k ON d.kategori_id = k.id
            LEFT JOIN personel p ON z.personel_id = p.id
            WHERE z.id = ?
        ");
        $sql->execute([$id]);
        $data = $sql->fetch(PDO::FETCH_OBJ);

        if (!$data) {
            return '';
        }

        $enc_id = Security::encrypt($data->id);
        $durumBadge = $this->getDurumBadge($data->durum);
        $teslimTarihi = date('d.m.Y', strtotime($data->teslim_tarihi));

        return '<tr data-id="' . $enc_id . '">
            <td class="text-center">' . $data->id . '</td>
            <td>' . $data->kategori_adi . '</td>
            <td>' . $data->demirbas_adi . '</td>
            <td>' . $data->personel_adi . '</td>
            <td class="text-center">' . $data->teslim_miktar . '</td>
            <td>' . $teslimTarihi . '</td>
            <td class="text-center">' . $durumBadge . '</td>
            <td class="text-center" style="width:5%">
                <div class="flex-shrink-0">
                    <div class="dropdown align-self-start">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                        </a>
                        <div class="dropdown-menu">
                            ' . ($data->durum === 'teslim' ? '<a href="#" data-id="' . $enc_id . '" class="dropdown-item zimmet-iade">
                                <span class="mdi mdi-undo font-size-18"></span> İade Al
                            </a>' : '') . '
                            <a href="#" data-id="' . $enc_id . '" class="dropdown-item zimmet-detay">
                                <span class="mdi mdi-eye font-size-18"></span> Detay
                            </a>
                            <a href="#" class="dropdown-item zimmet-sil" data-id="' . $enc_id . '">
                                <span class="mdi mdi-delete font-size-18"></span> Sil
                            </a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>';
    }

    private function getDurumBadge($durum)
    {
        $badges = [
            'teslim' => '<span class="badge bg-warning">Zimmetli</span>',
            'iade' => '<span class="badge bg-success">İade Edildi</span>',
            'kayip' => '<span class="badge bg-danger">Kayıp</span>',
            'arizali' => '<span class="badge bg-secondary">Arızalı</span>'
        ];
        return $badges[$durum] ?? '<span class="badge bg-info">Bilinmiyor</span>';
    }
}
