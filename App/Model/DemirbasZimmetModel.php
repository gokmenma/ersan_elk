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

            // Zimmet kaydı oluştur (eski tablo - geriye uyumluluk)
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

            // Hareket tablosuna kaydet (yeni sistem)
            $hareketSql = $this->db->prepare("
                INSERT INTO demirbas_hareketler 
                (demirbas_id, personel_id, zimmet_id, hareket_tipi, miktar, tarih, islem_id, is_emri_sonucu, aciklama, islem_yapan_id, kaynak)
                VALUES (?, ?, ?, 'zimmet', ?, ?, ?, ?, ?, ?, ?)
            ");
            $hareketSql->execute([
                $data['demirbas_id'],
                $data['personel_id'],
                $lastId,
                $teslim_miktar,
                $data['teslim_tarihi'],
                $data['islem_id'] ?? null,
                $data['is_emri_sonucu'] ?? null,
                $data['aciklama'] ?? null,
                $data['teslim_eden_id'] ?? ($_SESSION['id'] ?? null),
                $data['kaynak'] ?? 'manuel'
            ]);

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
            <td>' . ($data->marka ?? '-') . ' ' . ($data->model ?? '') . '</td>
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

    public function filter($term = null, $colSearches = [])
    {
        $sql = "SELECT 
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
                WHERE 1=1";

        $params = [];

        if (!empty($term)) {
            $term = "%$term%";
            $sql .= " AND (d.demirbas_no LIKE :term OR d.demirbas_adi LIKE :term OR p.adi_soyadi LIKE :term OR k.kategori_adi LIKE :term)";
            $params['term'] = $term;
        }

        if (!empty($colSearches)) {
            $colMap = [0 => 'z.id', 1 => 'k.kategori_adi', 2 => 'd.demirbas_adi', 3 => 'd.marka', 4 => 'p.adi_soyadi', 5 => 'z.teslim_miktar', 6 => 'z.teslim_tarihi', 7 => 'z.durum'];
            foreach ($colSearches as $idx => $val) {
                if (isset($colMap[$idx]) && $val !== '') {
                    $field = $colMap[$idx];
                    $paramName = "col_" . $idx;
                    if ($idx == 6) {
                        $sql .= " AND DATE_FORMAT($field, '%d.%m.%Y') LIKE :$paramName";
                    } else {
                        $sql .= " AND $field LIKE :$paramName";
                    }
                    $params[$paramName] = "%$val%";
                }
            }
        }

        $sql .= " ORDER BY z.kayit_tarihi DESC";
        $query = $this->db->prepare($sql);
        $query->execute($params);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }
    /**
     * Zimmet iade işlemi (Kısmi iade destekli)
     */
    public function iadeYap($zimmet_id, $iade_tarihi, $iade_miktar, $aciklama = null, $islem_id = null, $is_emri_sonucu = null, $kaynak = 'manuel')
    {
        $this->db->beginTransaction();

        try {
            // Zimmet bilgisini al
            $sql = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $sql->execute([$zimmet_id]);
            $zimmet = $sql->fetch(PDO::FETCH_OBJ);

            if (!$zimmet) {
                throw new \Exception("Zimmet kaydı bulunamadı.");
            }

            // Mevcut iade edilmiş miktar ve kalan kontrolü
            $mevcutIadeMiktari = (int) ($zimmet->iade_miktar ?? 0);
            $teslimMiktari = (int) $zimmet->teslim_miktar;
            $kalanZimmetMiktari = $teslimMiktari - $mevcutIadeMiktari;

            // İade edilecek miktar, elimizdeki zimmetten fazla olamaz
            if ($iade_miktar > $kalanZimmetMiktari) {
                $iade_miktar = $kalanZimmetMiktari;
            }

            if ($iade_miktar <= 0) {
                $this->db->rollBack();
                return true; // Zaten iade edilmiş veya kalan yok
            }

            $yeniToplamIade = $mevcutIadeMiktari + $iade_miktar;
            $yeniDurum = ($yeniToplamIade >= $teslimMiktari) ? 'iade' : 'teslim';

            // Zimmet durumunu güncelle (eski tablo - geriye uyumluluk)
            $sql = $this->db->prepare("
                UPDATE {$this->table} 
                SET durum = ?, 
                    iade_tarihi = ?, 
                    iade_miktar = ?,
                    aciklama = CONCAT(COALESCE(aciklama, ''), '\n', ?)
                WHERE id = ?
            ");
            $sql->execute([$yeniDurum, Date::Ymd($iade_tarihi, 'Y-m-d'), $yeniToplamIade, $aciklama ?? '', $zimmet_id]);

            // Hareket tablosuna kaydet (yeni sistem)
            $hareketSql = $this->db->prepare("
                INSERT INTO demirbas_hareketler 
                (demirbas_id, personel_id, zimmet_id, hareket_tipi, miktar, tarih, islem_id, is_emri_sonucu, aciklama, islem_yapan_id, kaynak)
                VALUES (?, ?, ?, 'iade', ?, ?, ?, ?, ?, ?, ?)
            ");
            $hareketSql->execute([
                $zimmet->demirbas_id,
                $zimmet->personel_id,
                $zimmet_id,
                $iade_miktar,
                Date::Ymd($iade_tarihi, 'Y-m-d'),
                $islem_id,
                $is_emri_sonucu,
                $aciklama,
                $_SESSION['id'] ?? null,
                $kaynak
            ]);

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


    private static $_autoTriggers = null;

    /**
     * İş emri sonucuna göre otomatik zimmet/iade işlemlerini kontrol eder ve yürütür
     */
    public function checkAndProcessAutomaticZimmet($personel_id, $is_emri_sonucu, $tarih, $islem_id = null, $miktar = 1, $mode = 'both')
    {
        if (empty($personel_id) || empty($is_emri_sonucu))
            return ['status' => 'ignored'];

        // Tetikleyicileri bir kez çek ve cache'le (Performans için)
        if (self::$_autoTriggers === null) {
            $sql = $this->db->query("
                SELECT id, demirbas_adi, otomatik_zimmet_is_emri, otomatik_iade_is_emri 
                FROM demirbas 
                WHERE (otomatik_zimmet_is_emri IS NOT NULL AND otomatik_zimmet_is_emri != '')
                OR (otomatik_iade_is_emri IS NOT NULL AND otomatik_iade_is_emri != '')
            ");
            self::$_autoTriggers = $sql->fetchAll(PDO::FETCH_OBJ);
        }

        $miktar = (int) ($miktar <= 0 ? 1 : $miktar);
        $searchSonuc = trim($is_emri_sonucu);

        $zimmetAdaylari = [];
        $iadeAdaylari = [];

        foreach (self::$_autoTriggers as $t) {
            if ($t->otomatik_zimmet_is_emri && trim($t->otomatik_zimmet_is_emri) === $searchSonuc) {
                $zimmetAdaylari[] = $t;
            }
            if ($t->otomatik_iade_is_emri && trim($t->otomatik_iade_is_emri) === $searchSonuc) {
                $iadeAdaylari[] = $t;
            }
        }

        if (empty($zimmetAdaylari) && empty($iadeAdaylari)) {
            return ['status' => 'no_trigger'];
        }

        $results = ['zimmet' => [], 'iade' => []];

        // 1. ZİMMET İŞLEMLERİ
        if (($mode === 'both' || $mode === 'zimmet') && !empty($zimmetAdaylari)) {
            foreach ($zimmetAdaylari as $d) {
                try {
                    // Mükerrer kontrolü
                    if ($islem_id) {
                        $checkSql = $this->db->prepare("SELECT id FROM {$this->table} WHERE demirbas_id = ? AND personel_id = ? AND aciklama LIKE ? LIMIT 1");
                        $checkSql->execute([$d->id, $personel_id, "%İşlem ID: $islem_id%"]);
                        if ($checkSql->fetch())
                            continue;
                    }

                    $this->zimmetVer([
                        'demirbas_id' => $d->id,
                        'personel_id' => $personel_id,
                        'teslim_tarihi' => $tarih,
                        'teslim_miktar' => $miktar,
                        'aciklama' => "Otomatik Zimmet (İşlem ID: $islem_id - Sonuç: $is_emri_sonucu)",
                        'islem_id' => $islem_id,
                        'is_emri_sonucu' => $is_emri_sonucu,
                        'kaynak' => 'puantaj_excel'
                    ]);
                    $results['zimmet'][] = ['status' => 'success'];
                } catch (\Exception $e) {
                }
            }
        }

        // 2. İADE İŞLEMLERİ
        if (($mode === 'both' || $mode === 'iade') && !empty($iadeAdaylari)) {
            // Sadece bu iş emri sonucuna bağlı iadeleri bul
            $sqlIade = $this->db->prepare("
                SELECT z.id, d.demirbas_adi, d.id as demirbas_id, z.teslim_miktar, z.iade_miktar
                FROM {$this->table} z
                INNER JOIN demirbas d ON z.demirbas_id = d.id
                WHERE TRIM(d.otomatik_iade_is_emri) = ? 
                AND z.personel_id = ? 
                AND z.durum = 'teslim'
                ORDER BY z.teslim_tarihi ASC
            ");
            $sqlIade->execute([$searchSonuc, $personel_id]);
            $mevcutZimmetler = $sqlIade->fetchAll(PDO::FETCH_OBJ);

            $kalanIadeIhtiyaci = $miktar;
            foreach ($mevcutZimmetler as $z) {
                if ($kalanIadeIhtiyaci <= 0)
                    break;

                // Mükerrer kontrolü
                if ($islem_id) {
                    $sqlCheckAuto = $this->db->prepare("SELECT id FROM {$this->table} WHERE id = ? AND aciklama LIKE ? LIMIT 1");
                    $sqlCheckAuto->execute([$z->id, "%İşlem ID: $islem_id%"]);
                    if ($sqlCheckAuto->fetch())
                        continue;
                }

                $mevcutIade = (int) ($z->iade_miktar ?? 0);
                $kalanZimmet = (int) $z->teslim_miktar - $mevcutIade;
                if ($kalanZimmet <= 0)
                    continue;

                $suAnkiIade = min($kalanZimmet, $kalanIadeIhtiyaci);
                try {
                    $this->iadeYap($z->id, $tarih, $suAnkiIade, "Otomatik İade (İşlem ID: $islem_id - Sonuç: $is_emri_sonucu)", $islem_id, $is_emri_sonucu, 'puantaj_excel');
                    $kalanIadeIhtiyaci -= $suAnkiIade;
                    $results['iade'][] = ['status' => 'success'];
                } catch (\Exception $e) {
                }
            }
        }

        return $results;
    }

}
