<?php

namespace App\Model;

use PDO;

class MobilePersonelModel extends Model
{
    protected $table = 'personel';

    private function baseWhere(array &$params): string
    {
        $restricted = $this->getRestrictedDeptSql('p.departman', $params);
        return "p.firma_id = :firma_id AND p.silinme_tarihi IS NULL
            AND p.personel_tipi = 'standart'
            AND (p.disardan_sigortali = 0 OR FIND_IN_SET(:modul, p.gorunum_modulleri)) {$restricted}";
    }

    public function mobileDirectory(array $filters, int $firmaId): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(50, max(10, (int) ($filters['limit'] ?? 24)));
        $offset = ($page - 1) * $limit;
        $status = in_array(($filters['status'] ?? 'active'), ['all', 'active', 'passive'], true) ? $filters['status'] : 'active';
        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 80);
        $department = mb_substr(trim((string) ($filters['department'] ?? '')), 0, 100);
        $baseParams = ['firma_id' => $firmaId, 'modul' => 'personel'];
        $baseWhere = $this->baseWhere($baseParams);

        $stmt = $this->db->prepare("SELECT COUNT(*) total,
            SUM(p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '0000-00-00') active,
            SUM(p.isten_cikis_tarihi IS NOT NULL AND p.isten_cikis_tarihi <> '0000-00-00') passive,
            COUNT(DISTINCT NULLIF(TRIM(p.departman), '')) departments
            FROM personel p WHERE {$baseWhere}");
        $stmt->execute($baseParams);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->db->prepare("SELECT DISTINCT TRIM(p.departman) department FROM personel p
            WHERE {$baseWhere} AND NULLIF(TRIM(p.departman), '') IS NOT NULL ORDER BY department");
        $stmt->execute($baseParams);
        $departments = array_values(array_filter(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'department')));

        $params = $baseParams;
        $where = $baseWhere;
        if ($status === 'active') $where .= " AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '0000-00-00')";
        if ($status === 'passive') $where .= " AND (p.isten_cikis_tarihi IS NOT NULL AND p.isten_cikis_tarihi <> '0000-00-00')";
        if ($department !== '') { $where .= ' AND TRIM(p.departman) = :department'; $params['department'] = $department; }
        if ($search !== '') {
            $where .= ' AND (p.adi_soyadi LIKE :s1 OR p.cep_telefonu LIKE :s2 OR p.email_adresi LIKE :s3 OR p.gorev LIKE :s4 OR p.departman LIKE :s5 OR p.ekip_bolge LIKE :s6)';
            for ($i = 1; $i <= 6; $i++) $params['s' . $i] = '%' . $search . '%';
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM personel p WHERE {$where}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $sql = "SELECT p.id,p.adi_soyadi,p.cep_telefonu,p.email_adresi,p.gorev,p.departman,p.ekip_bolge,
            p.ise_giris_tarihi,p.isten_cikis_tarihi,
            GROUP_CONCAT(DISTINCT REPLACE(t.tur_adi,'ER-SAN ELEKTRİK ','') ORDER BY t.tur_adi SEPARATOR ', ') team,
            GROUP_CONCAT(DISTINCT t.ekip_bolge ORDER BY t.ekip_bolge SEPARATOR ', ') team_region
            FROM personel p LEFT JOIN personel_ekip_gecmisi peg ON peg.personel_id=p.id AND peg.firma_id=p.firma_id
            AND peg.baslangic_tarihi<=CURDATE() AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi>=CURDATE())
            LEFT JOIN tanimlamalar t ON t.id=peg.ekip_kodu_id WHERE {$where} GROUP BY p.id
            ORDER BY p.adi_soyadi LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return [
            'summary' => ['total'=>(int)($summary['total']??0),'active'=>(int)($summary['active']??0),'passive'=>(int)($summary['passive']??0),'departments'=>(int)($summary['departments']??0)],
            'departments' => $departments,
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'pagination' => ['page'=>$page,'limit'=>$limit,'total'=>$total,'has_more'=>($offset+$limit)<$total],
        ];
    }

    public function mobileDirectoryDetail(int $id, int $firmaId): ?array
    {
        $params = ['id'=>$id,'firma_id'=>$firmaId,'modul'=>'personel'];
        $where = $this->baseWhere($params);
        $stmt = $this->db->prepare("SELECT p.id,p.adi_soyadi,p.cep_telefonu,p.email_adresi,p.gorev,p.departman,p.ekip_bolge,
            p.ise_giris_tarihi,p.isten_cikis_tarihi,
            GROUP_CONCAT(DISTINCT REPLACE(t.tur_adi,'ER-SAN ELEKTRİK ','') SEPARATOR ', ') team,
            GROUP_CONCAT(DISTINCT t.ekip_bolge SEPARATOR ', ') team_region
            FROM personel p LEFT JOIN personel_ekip_gecmisi peg ON peg.personel_id=p.id AND peg.firma_id=p.firma_id
            AND peg.baslangic_tarihi<=CURDATE() AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi>=CURDATE())
            LEFT JOIN tanimlamalar t ON t.id=peg.ekip_kodu_id WHERE p.id=:id AND {$where} GROUP BY p.id LIMIT 1");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveMobileProfile(array $data, int $firmaId, int $id = 0): int
    {
        $values = [
            'adi_soyadi'=>mb_substr(trim((string)($data['name']??'')),0,150),
            'cep_telefonu'=>($v=trim((string)($data['phone']??'')))!==''?mb_substr($v,0,15):null,
            'email_adresi'=>($v=trim((string)($data['email']??'')))!==''?mb_substr($v,0,150):null,
            'gorev'=>($v=trim((string)($data['job']??'')))!==''?mb_substr($v,0,100):null,
            'departman'=>($v=trim((string)($data['department']??'')))!==''?mb_substr($v,0,255):null,
            'ekip_bolge'=>($v=trim((string)($data['region']??'')))!==''?mb_substr($v,0,100):null,
            'ise_giris_tarihi'=>trim((string)($data['start_date']??'')),
            'isten_cikis_tarihi'=>($v=trim((string)($data['end_date']??'')))!==''?$v:null,
        ];
        if ($values['adi_soyadi']==='' || !\DateTime::createFromFormat('Y-m-d',$values['ise_giris_tarihi'])) throw new \InvalidArgumentException('Ad soyad ve geçerli işe giriş tarihi zorunludur.');
        if ($values['email_adresi'] && !filter_var($values['email_adresi'],FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('E-posta adresi geçersiz.');
        $values['aktif_mi']=$values['isten_cikis_tarihi']?0:1;
        if ($id>0) {
            if (!$this->mobileDirectoryDetail($id,$firmaId)) throw new \RuntimeException('Personel bulunamadı veya yetki kapsamınızın dışında.');
            $set=[]; foreach(array_keys($values) as $key)$set[]="$key=:$key";
            $stmt=$this->db->prepare('UPDATE personel SET '.implode(',',$set).',guncelleme_tarihi=NOW() WHERE id=:id AND firma_id=:firma_id AND silinme_tarihi IS NULL');
            $stmt->execute([...$values,'id'=>$id,'firma_id'=>$firmaId]); return $id;
        }
        $stmt=$this->db->prepare("INSERT INTO personel (firma_id,personel_tipi,adi_soyadi,cep_telefonu,email_adresi,gorev,departman,ekip_bolge,ise_giris_tarihi,isten_cikis_tarihi,aktif_mi,disardan_sigortali,saha_takibi,puantaj_hakedis_dahil) VALUES (:firma_id,'standart',:adi_soyadi,:cep_telefonu,:email_adresi,:gorev,:departman,:ekip_bolge,:ise_giris_tarihi,:isten_cikis_tarihi,:aktif_mi,0,0,0)");
        $stmt->execute(['firma_id'=>$firmaId,...$values]); return (int)$this->db->lastInsertId();
    }
}
