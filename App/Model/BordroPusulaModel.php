<?php
namespace App\Model;

use App\Service\BordroPusulaService;
use DomainException;
use PDO;

/** Resmî pusula yalnız kayıtlı dönem satırlarını okur; pasif personel de dahildir. */
final class BordroPusulaModel extends Model
{
    public function rapor(int $firmaId, int $donemId, ?int $bordroId = null): array
    {
        $stmt = $this->db->prepare('SELECT * FROM bordro_donemi WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL');
        $stmt->execute([$donemId, $firmaId]);
        $donem = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$donem) throw new DomainException('Dönem bulunamadı.');
        $stmt = $this->db->prepare('SELECT * FROM firmalar WHERE id = ? AND silinme_tarihi IS NULL');
        $stmt->execute([$firmaId]);
        $firma = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$firma) throw new DomainException('Firma bulunamadı.');
        $stmt = $this->db->prepare('SELECT bp.*, p.adi_soyadi, p.tc_kimlik_no, p.sgk_no,
            p.departman, p.gorev, p.ise_giris_tarihi, p.isten_cikis_tarihi, p.sgk_yapilan_firma
            FROM bordro_personel bp JOIN bordro_donemi d ON d.id = bp.donem_id
            JOIN personel p ON p.id = bp.personel_id AND p.firma_id = d.firma_id
            WHERE bp.donem_id = ? AND d.firma_id = ? AND bp.silinme_tarihi IS NULL'
            . ($bordroId !== null ? ' AND bp.id = ?' : '') . ' ORDER BY p.adi_soyadi, bp.id');
        $stmt->execute($bordroId !== null ? [$donemId, $firmaId, $bordroId] : [$donemId, $firmaId]);
        $model = new BordroPersonelModel();
        $kayitlar = [];
        $personelModel = new PersonelModel();
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
            $row = $personelModel->decryptFields($row);
            try {
                $pusula = BordroPusulaService::olustur($row, $donem, $model);
                $kayitlar[] = ['kayit' => $row, 'pusula' => $pusula, 'hata' => null];
            } catch (DomainException $e) {
                $kayitlar[] = ['kayit' => $row, 'pusula' => null, 'hata' => $e->getMessage()];
            }
        }
        return compact('donem', 'firma', 'kayitlar');
    }
}
