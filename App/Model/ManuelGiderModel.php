<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class ManuelGiderModel extends Model
{
    protected $table = 'manuel_giderler';
    private ?array $columnMap = null;

    /** Kategori sabitleri */
    const KATEGORILER = [
        'Araç'        => 'Araç',
        'Personel'    => 'Personel',
        'Demirbaş'    => 'Demirbaş',
        'Operasyonel' => 'Operasyonel',
        'Diğer'       => 'Diğer',
    ];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Firma bazlı tüm manuel gider kayıtlarını getirir
     */
    public function all()
    {
                $dateCol = $this->getColumnMap()['date'];

        $sql = $this->db->prepare("
                        SELECT mg.*,
                                     mg.{$dateCol} AS tarih
            FROM {$this->table} mg
            WHERE mg.firma_id = :firma_id
              AND mg.silinme_tarihi IS NULL
                        ORDER BY mg.{$dateCol} DESC, mg.id DESC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tarih aralığı ve kategori filtresiyle kayıtları getirir
     */
    public function getFiltered($baslangic = null, $bitis = null, $kategori = null)
    {
                $map = $this->getColumnMap();
                $dateCol = $map['date'];
                $categoryCol = $map['category'];

                $sqlStr = "SELECT mg.*, mg.{$dateCol} AS tarih
                   FROM {$this->table} mg
                   WHERE mg.firma_id = :firma_id
                     AND mg.silinme_tarihi IS NULL";

        $params = ['firma_id' => $_SESSION['firma_id']];

        if ($baslangic && $bitis) {
            $sqlStr .= " AND mg.{$dateCol} BETWEEN :baslangic AND :bitis";
            $params['baslangic'] = $baslangic;
            $params['bitis'] = $bitis;
        }

        if ($kategori) {
            $sqlStr .= " AND mg.{$categoryCol} = :kategori";
            $params['kategori'] = $kategori;
        }

        $sqlStr .= " ORDER BY mg.{$dateCol} DESC, mg.id DESC";

        $stmt = $this->db->prepare($sqlStr);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    private function getColumnMap(): array
    {
        if ($this->columnMap !== null) {
            return $this->columnMap;
        }

        $defaults = [
            'date' => 'tarih',
            'category' => 'kategori',
        ];

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM {$this->table}");
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $find = function (array $candidates, string $fallback) use ($cols): string {
                foreach ($candidates as $name) {
                    if (in_array($name, $cols, true)) {
                        return $name;
                    }
                }
                return $fallback;
            };

            $this->columnMap = [
                'date' => $find(['tarih', 'islem_tarihi', 'kayit_tarihi', 'olusturma_tarihi'], 'tarih'),
                'category' => $find(['kategori', 'kategori_adi'], 'kategori'),
            ];
        } catch (\Throwable $e) {
            $this->columnMap = $defaults;
        }

        return $this->columnMap;
    }

    /**
     * Düzenleme formu için kayıt detayını standart alan adlarıyla getirir.
     */
    public function getDetailById(int $id)
    {
        $map = $this->getColumnMap();
        $dateCol = $map['date'];
        $categoryCol = $map['category'];

        $stmt = $this->db->prepare("\n            SELECT mg.*,\n                   mg.{$dateCol} AS tarih,\n                   mg.{$categoryCol} AS kategori\n            FROM {$this->table} mg\n            WHERE mg.id = :id\n              AND mg.firma_id = :firma_id\n              AND mg.silinme_tarihi IS NULL\n            LIMIT 1\n        ");

        $stmt->execute([
            'id' => $id,
            'firma_id' => $_SESSION['firma_id'],
        ]);

        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Kayıt ekle
     */
    public function create($data)
    {
        $map = $this->getColumnMap();
        $dateCol = $map['date'];
        $categoryCol = $map['category'];

        $this->attributes = [
            'firma_id'              => $_SESSION['firma_id'],
            $categoryCol            => $data['kategori'],
            'alt_kategori'          => $data['alt_kategori'] ?? null,
            'tutar'                 => $data['tutar'],
            $dateCol                => $data['tarih'],
            'aciklama'              => $data['aciklama'] ?? null,
            'belge_no'              => $data['belge_no'] ?? null,
            'olusturan_kullanici_id' => $_SESSION['user_id'] ?? null,
        ];
        $this->isNew = true;
        return $this->save();
    }

    /**
     * Kayıt güncelle
     */
    public function updateById($id, $data)
    {
        $map = $this->getColumnMap();
        $dateCol = $map['date'];
        $categoryCol = $map['category'];

        $this->attributes = [
            'id'           => $id,
            $categoryCol   => $data['kategori'],
            'alt_kategori' => $data['alt_kategori'] ?? null,
            'tutar'        => $data['tutar'],
            $dateCol       => $data['tarih'],
            'aciklama'     => $data['aciklama'] ?? null,
            'belge_no'     => $data['belge_no'] ?? null,
        ];
        $this->isNew = false;
        return $this->save();
    }
}
