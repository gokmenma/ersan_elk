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

        $sql = "SELECT p.id,p.adi_soyadi,p.cep_telefonu,p.email_adresi,p.gorev,p.departman,p.ekip_bolge,p.resim_yolu,p.personel_resim_yolu,
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
        $stmt = $this->db->prepare("SELECT p.*,
            GROUP_CONCAT(DISTINCT REPLACE(t.tur_adi,'ER-SAN ELEKTRİK ','') SEPARATOR ', ') team,
            GROUP_CONCAT(DISTINCT t.ekip_bolge SEPARATOR ', ') team_region
            FROM personel p LEFT JOIN personel_ekip_gecmisi peg ON peg.personel_id=p.id AND peg.firma_id=p.firma_id
            AND peg.baslangic_tarihi<=CURDATE() AND (peg.bitis_tarihi IS NULL OR peg.bitis_tarihi>=CURDATE())
            LEFT JOIN tanimlamalar t ON t.id=peg.ekip_kodu_id WHERE p.id=:id AND {$where} GROUP BY p.id LIMIT 1");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        // Güvenli alanları deşifre et
        foreach (['tc_kimlik_no', 'kan_grubu', 'kaski_sifre', 'kaski_kullanici_adi'] as $f) {
            if (!empty($row[$f])) {
                $dec = \App\Helper\Security::decrypt($row[$f]);
                $row[$f] = ($dec !== false && $dec !== '') ? $dec : $row[$f];
            }
        }
        return $row;
    }

    public function saveMobileGeneralInfo(int $id, array $data, int $firmaId): bool
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Geçersiz personel kaydı.');
        }
        $existing = $this->mobileDirectoryDetail($id, $firmaId);
        if (!$existing) {
            throw new \RuntimeException('Personel bulunamadı veya yetki kapsamınızın dışında.');
        }

        $fields = [];
        $params = ['id' => $id, 'firma_id' => $firmaId];

        // 1. Kimlik ve Temel Bilgiler
        if (array_key_exists('name', $data) || array_key_exists('adi_soyadi', $data)) {
            $name = trim((string) ($data['name'] ?? $data['adi_soyadi'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('Ad soyad boş bırakılamaz.');
            }
            $fields[] = 'adi_soyadi = :adi_soyadi';
            $params['adi_soyadi'] = mb_substr($name, 0, 150);
        }

        if (array_key_exists('tc_kimlik_no', $data)) {
            $rawTc = preg_replace('/\D/', '', (string) ($data['tc_kimlik_no'] ?? ''));
            if ($rawTc !== '' && strlen($rawTc) !== 11) {
                throw new \InvalidArgumentException('TC Kimlik No 11 haneli olmalıdır.');
            }
            $fields[] = 'tc_kimlik_no = :tc_kimlik_no';
            $fields[] = 'tc_hash = :tc_hash';
            $params['tc_kimlik_no'] = $rawTc !== '' ? \App\Helper\Security::encrypt($rawTc) : null;
            $params['tc_hash'] = $rawTc !== '' ? hash('sha256', $rawTc) : null;
        }

        if (array_key_exists('dogum_tarihi', $data)) {
            $dt = trim((string) ($data['dogum_tarihi'] ?? ''));
            $fields[] = 'dogum_tarihi = :dogum_tarihi';
            $params['dogum_tarihi'] = ($dt !== '' && $dt !== '0000-00-00') ? $dt : null;
        }

        if (array_key_exists('cinsiyet', $data)) {
            $cinsiyet = trim((string) ($data['cinsiyet'] ?? ''));
            $fields[] = 'cinsiyet = :cinsiyet';
            $params['cinsiyet'] = in_array($cinsiyet, ['Erkek', 'Kadın'], true) ? $cinsiyet : null;
        }

        if (array_key_exists('medeni_durum', $data)) {
            $medeni = trim((string) ($data['medeni_durum'] ?? ''));
            $fields[] = 'medeni_durum = :medeni_durum';
            $params['medeni_durum'] = in_array($medeni, ['Evli', 'Bekar'], true) ? $medeni : null;
        }

        if (array_key_exists('kan_grubu', $data)) {
            $kan = trim((string) ($data['kan_grubu'] ?? ''));
            $fields[] = 'kan_grubu = :kan_grubu';
            $params['kan_grubu'] = $kan !== '' ? \App\Helper\Security::encrypt($kan) : null;
        }

        // 2. Kişisel Detaylar
        foreach (['anne_adi', 'baba_adi', 'dogum_yeri_il', 'dogum_yeri_ilce', 'ehliyet_sinifi'] as $strField) {
            if (array_key_exists($strField, $data)) {
                $val = trim((string) ($data[$strField] ?? ''));
                $fields[] = "{$strField} = :{$strField}";
                $params[$strField] = $val !== '' ? mb_substr($val, 0, 100) : null;
            }
        }

        if (array_key_exists('seyahat_engeli', $data)) {
            $engeli = trim((string) ($data['seyahat_engeli'] ?? ''));
            $fields[] = 'seyahat_engeli = :seyahat_engeli';
            $params['seyahat_engeli'] = in_array($engeli, ['Var', 'Yok'], true) ? $engeli : 'Yok';
        }

        // 3. İletişim & Adres
        if (array_key_exists('cep_telefonu', $data) || array_key_exists('phone', $data)) {
            $phone = trim((string) ($data['cep_telefonu'] ?? $data['phone'] ?? ''));
            $fields[] = 'cep_telefonu = :cep_telefonu';
            $params['cep_telefonu'] = $phone !== '' ? mb_substr($phone, 0, 20) : null;
        }

        if (array_key_exists('cep_telefonu_2', $data)) {
            $phone2 = trim((string) ($data['cep_telefonu_2'] ?? ''));
            $fields[] = 'cep_telefonu_2 = :cep_telefonu_2';
            $params['cep_telefonu_2'] = $phone2 !== '' ? mb_substr($phone2, 0, 20) : null;
        }

        if (array_key_exists('email_adresi', $data) || array_key_exists('email', $data)) {
            $email = trim((string) ($data['email_adresi'] ?? $data['email'] ?? ''));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Geçersiz e-posta adresi.');
            }
            $fields[] = 'email_adresi = :email_adresi';
            $params['email_adresi'] = $email !== '' ? mb_substr($email, 0, 150) : null;
        }

        if (array_key_exists('adres', $data)) {
            $adres = trim((string) ($data['adres'] ?? ''));
            $fields[] = 'adres = :adres';
            $params['adres'] = $adres !== '' ? $adres : null;
        }

        // 4. Fiziksel Bilgiler & Aile
        foreach (['ayakkabi_numarasi', 'ust_beden_no', 'alt_beden_no'] as $sizeField) {
            if (array_key_exists($sizeField, $data)) {
                $val = trim((string) ($data[$sizeField] ?? ''));
                $fields[] = "{$sizeField} = :{$sizeField}";
                $params[$sizeField] = $val !== '' ? mb_substr($val, 0, 20) : null;
            }
        }

        if (array_key_exists('esi_calisiyor_mu', $data)) {
            $esi = trim((string) ($data['esi_calisiyor_mu'] ?? ''));
            $fields[] = 'esi_calisiyor_mu = :esi_calisiyor_mu';
            $params['esi_calisiyor_mu'] = in_array($esi, ['Evet', 'Hayır'], true) ? $esi : 'Hayır';
        }

        // Acil Durum Kişisi
        foreach (['acil_kisi_adi_soyadi', 'acil_kisi_yakinlik', 'acil_kisi_telefonu'] as $emField) {
            if (array_key_exists($emField, $data)) {
                $val = trim((string) ($data[$emField] ?? ''));
                $fields[] = "{$emField} = :{$emField}";
                $params[$emField] = $val !== '' ? mb_substr($val, 0, 100) : null;
            }
        }

        // 5. Giriş ve Şifre Bilgileri
        if (!empty($data['sifre'])) {
            $sifre = (string) $data['sifre'];
            if (strlen($sifre) < 6) {
                throw new \InvalidArgumentException('Program şifresi en az 6 karakter olmalıdır.');
            }
            $fields[] = 'sifre = :sifre';
            $params['sifre'] = password_hash($sifre, PASSWORD_DEFAULT);
        }

        if (array_key_exists('kaski_kullanici_adi', $data)) {
            $kaskiUser = trim((string) ($data['kaski_kullanici_adi'] ?? ''));
            $fields[] = 'kaski_kullanici_adi = :kaski_kullanici_adi';
            $params['kaski_kullanici_adi'] = $kaskiUser !== '' ? \App\Helper\Security::encrypt($kaskiUser) : null;
        }

        if (array_key_exists('kaski_sifre', $data)) {
            $kaskiPass = (string) ($data['kaski_sifre'] ?? '');
            if ($kaskiPass !== '') {
                $fields[] = 'kaski_sifre = :kaski_sifre';
                $params['kaski_sifre'] = \App\Helper\Security::encrypt($kaskiPass);
            }
        }

        if (empty($fields)) {
            return true;
        }

        $fields[] = 'guncelleme_tarihi = NOW()';
        $sql = "UPDATE personel SET " . implode(', ', $fields) . " WHERE id = :id AND firma_id = :firma_id AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
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

    public function getWorkDetails(int $id, int $firmaId): array
    {
        $person = $this->mobileDirectoryDetail($id, $firmaId);
        if (!$person) {
            throw new \RuntimeException('Personel bulunamadı veya erişim yetkiniz yok.');
        }

        $personelModel = new PersonelModel();
        $ekipGecmisi = $personelModel->getEkipGecmisi($id);
        $calismaGecmisi = $personelModel->getCalismaGecmisi($id);

        $bugun = date('Y-m-d');
        $ekipList = [];
        foreach ($ekipGecmisi as $item) {
            $isAktif = ($item->baslangic_tarihi <= $bugun && (empty($item->bitis_tarihi) || $item->bitis_tarihi >= $bugun));
            $ekipList[] = [
                'id' => (int) $item->id,
                'ekip_kodu_id' => (int) $item->ekip_kodu_id,
                'ekip_adi' => (string) ($item->ekip_adi ?? ''),
                'baslangic_tarihi' => $item->baslangic_tarihi,
                'bitis_tarihi' => $item->bitis_tarihi,
                'ekip_sefi_mi' => (int) ($item->ekip_sefi_mi ?? 0),
                'aktif_mi' => $isAktif,
            ];
        }

        $calismaList = [];
        foreach ($calismaGecmisi as $c) {
            $isAktif = ($c->ise_giris_tarihi <= $bugun && (empty($c->isten_cikis_tarihi) || $c->isten_cikis_tarihi >= $bugun));
            $calismaList[] = [
                'id' => (int) $c->id,
                'sgk_yapilan_firma' => (string) ($c->sgk_yapilan_firma ?? ''),
                'ise_giris_tarihi' => $c->ise_giris_tarihi,
                'isten_cikis_tarihi' => $c->isten_cikis_tarihi,
                'personel_sinifi' => (string) ($c->personel_sinifi ?? 'Beyaz Yaka'),
                'saha_takibi' => (int) ($c->saha_takibi ?? 0),
                'arac_kullanim' => (string) ($c->arac_kullanim ?? 'Yok'),
                'isten_ayrilis_nedeni' => (string) ($c->isten_ayrilis_nedeni ?? ''),
                'isten_ayrilis_belge_yolu' => !empty($c->isten_ayrilis_belge_yolu) ? '/' . ltrim($c->isten_ayrilis_belge_yolu, '/') : null,
                'aktif_mi' => $isAktif,
            ];
        }

        $aktifCalismaVar = false;
        foreach ($calismaList as $c) {
            if ($c['aktif_mi']) {
                $aktifCalismaVar = true;
                break;
            }
        }
        if (!$aktifCalismaVar && empty($calismaList) && !empty($person['ise_giris_tarihi'])) {
            $aktifCalismaVar = $person['ise_giris_tarihi'] <= $bugun
                && (empty($person['isten_cikis_tarihi']) || $person['isten_cikis_tarihi'] >= $bugun);
        }

        return [
            'ise_giris_tarihi' => $person['ise_giris_tarihi'] ?? null,
            'isten_cikis_tarihi' => $person['isten_cikis_tarihi'] ?? null,
            'personel_sinifi' => $person['personel_sinifi'] ?? 'Beyaz Yaka',
            'saha_takibi' => (int) ($person['saha_takibi'] ?? 0),
            'arac_kullanim' => $person['arac_kullanim'] ?? 'Yok',
            'sgk_yapilan_firma' => $person['sgk_yapilan_firma'] ?? 'Yok',
            'isten_ayrilis_nedeni' => $person['isten_ayrilis_nedeni'] ?? null,
            'isten_ayrilis_belge_yolu' => !empty($person['isten_ayrilis_belge_yolu']) ? '/' . ltrim($person['isten_ayrilis_belge_yolu'], '/') : null,
            'departman' => $person['departman'] ?? '',
            'gorev' => $person['gorev'] ?? '',
            'aktif_calisma_var' => $aktifCalismaVar,
            'ekip_gecmisi' => $ekipList,
            'calisma_gecmisi' => $calismaList,
        ];
    }

    public function getWorkOptions(int $firmaId): array
    {
        $tanimModel = new TanimlamalarModel();
        $ekipler = $tanimModel->getMusaitEkipKodlari();
        $bolgeler = $tanimModel->getFilteredEkipBolgeleri();

        $stmt = $this->db->prepare("SELECT DISTINCT TRIM(sgk_yapilan_firma) as firma FROM personel WHERE firma_id = :firma_id AND sgk_yapilan_firma IS NOT NULL AND TRIM(sgk_yapilan_firma) != '' ORDER BY firma");
        $stmt->execute(['firma_id' => $firmaId]);
        $sgkFirmalari = array_values(array_filter(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'firma')));

        if (empty($sgkFirmalari)) {
            $sgkFirmalari = ['ER-SAN ELEKTRİK', 'Dışarıdan Sigortalı'];
        }

        $ekipOptions = [];
        foreach ($ekipler as $e) {
            $ekipOptions[] = [
                'id' => (int) $e->id,
                'tur_adi' => (string) $e->tur_adi,
                'ekip_bolge' => (string) ($e->ekip_bolge ?? ''),
            ];
        }

        return [
            'ekipler' => $ekipOptions,
            'bolgeler' => array_values(array_filter($bolgeler)),
            'sgk_firmalari' => $sgkFirmalari,
            'personel_siniflari' => ['Beyaz Yaka', 'Mavi Yaka'],
            'arac_secenekleri' => ['Yok', 'Şirket Aracı', 'Özel Araç', 'Havuz Aracı'],
        ];
    }

    public function saveWorkInfo(int $id, array $data, int $firmaId): bool
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Geçersiz personel ID.');
        }
        $existing = $this->mobileDirectoryDetail($id, $firmaId);
        if (!$existing) {
            throw new \RuntimeException('Personel bulunamadı veya yetki kapsamınızın dışında.');
        }

        $fields = [];
        $params = ['id' => $id, 'firma_id' => $firmaId];

        if (array_key_exists('ise_giris_tarihi', $data)) {
            $igt = trim((string) ($data['ise_giris_tarihi'] ?? ''));
            if ($igt === '' || !\DateTime::createFromFormat('Y-m-d', $igt)) {
                throw new \InvalidArgumentException('Geçerli bir işe giriş tarihi zorunludur.');
            }
            $fields[] = 'ise_giris_tarihi = :ise_giris_tarihi';
            $params['ise_giris_tarihi'] = $igt;
        }

        if (array_key_exists('isten_cikis_tarihi', $data)) {
            $ict = trim((string) ($data['isten_cikis_tarihi'] ?? ''));
            $fields[] = 'isten_cikis_tarihi = :isten_cikis_tarihi';
            $params['isten_cikis_tarihi'] = ($ict !== '' && $ict !== '0000-00-00') ? $ict : null;
            $fields[] = 'aktif_mi = :aktif_mi';
            $params['aktif_mi'] = ($ict !== '' && $ict !== '0000-00-00') ? 0 : 1;
        }

        if (array_key_exists('personel_sinifi', $data)) {
            $sinif = trim((string) ($data['personel_sinifi'] ?? ''));
            $fields[] = 'personel_sinifi = :personel_sinifi';
            $params['personel_sinifi'] = in_array($sinif, ['Beyaz Yaka', 'Mavi Yaka'], true) ? $sinif : 'Beyaz Yaka';
        }

        if (array_key_exists('saha_takibi', $data)) {
            $fields[] = 'saha_takibi = :saha_takibi';
            $params['saha_takibi'] = !empty($data['saha_takibi']) ? 1 : 0;
        }

        if (array_key_exists('arac_kullanim', $data)) {
            $arac = trim((string) ($data['arac_kullanim'] ?? ''));
            $fields[] = 'arac_kullanim = :arac_kullanim';
            $params['arac_kullanim'] = $arac !== '' ? mb_substr($arac, 0, 50) : 'Yok';
        }

        if (array_key_exists('sgk_yapilan_firma', $data)) {
            $sgk = trim((string) ($data['sgk_yapilan_firma'] ?? ''));
            $fields[] = 'sgk_yapilan_firma = :sgk_yapilan_firma';
            $params['sgk_yapilan_firma'] = $sgk !== '' ? mb_substr($sgk, 0, 150) : 'Yok';
        }

        if (array_key_exists('isten_ayrilis_nedeni', $data)) {
            $neden = trim((string) ($data['isten_ayrilis_nedeni'] ?? ''));
            $fields[] = 'isten_ayrilis_nedeni = :isten_ayrilis_nedeni';
            $params['isten_ayrilis_nedeni'] = $neden !== '' ? $neden : null;
        }

        if (array_key_exists('departman', $data)) {
            $dept = trim((string) ($data['departman'] ?? ''));
            $fields[] = 'departman = :departman';
            $params['departman'] = $dept !== '' ? mb_substr($dept, 0, 255) : null;
        }

        if (array_key_exists('gorev', $data)) {
            $job = trim((string) ($data['gorev'] ?? ''));
            $fields[] = 'gorev = :gorev';
            $params['gorev'] = $job !== '' ? mb_substr($job, 0, 100) : null;
        }

        if (empty($fields)) {
            return true;
        }

        $fields[] = 'guncelleme_tarihi = NOW()';
        $sql = "UPDATE personel SET " . implode(', ', $fields) . " WHERE id = :id AND firma_id = :firma_id AND silinme_tarihi IS NULL";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function saveEkipGecmisi(array $data, int $firmaId): array
    {
        $personelModel = new PersonelModel();
        $personelId = (int) ($data['personel_id'] ?? 0);
        $ekipKoduId = (int) ($data['ekip_kodu_id'] ?? 0);
        $baslangic = trim((string) ($data['baslangic_tarihi'] ?? ''));
        $bitis = trim((string) ($data['bitis_tarihi'] ?? '')) ?: null;
        $ekipSefiMi = !empty($data['ekip_sefi_mi']) ? 1 : 0;
        $id = (int) ($data['id'] ?? 0);

        if ($personelId <= 0 || $ekipKoduId <= 0 || !$baslangic) {
            throw new \InvalidArgumentException('Personel, ekip kodu ve başlangıç tarihi zorunludur.');
        }

        // Personel işe giriş kontrolü
        $personel = $personelModel->find($personelId);
        if ($personel && !empty($personel->ise_giris_tarihi) && $personel->ise_giris_tarihi !== '0000-00-00') {
            if ($baslangic < $personel->ise_giris_tarihi) {
                throw new \InvalidArgumentException("Ekip başlangıç tarihi, personelin işe giriş tarihinden ({$personel->ise_giris_tarihi}) önce olamaz.");
            }
        }

        // Tarih çakışması kontrolü (bireysel)
        if ($personelModel->hasEkipOverlap($personelId, $ekipKoduId, $baslangic, $bitis, $id)) {
            throw new \InvalidArgumentException("Seçilen tarih aralığı bu personel için mevcut bir ekip atamasıyla çakışıyor.");
        }

        // Tarih çakışması kontrolü (global)
        $cakisan = $personelModel->isEkipKoduAvailable($ekipKoduId, $baslangic, $bitis, $id);
        if ($cakisan && (int)$cakisan->id !== $personelId) {
            throw new \InvalidArgumentException("Bu ekip kodu seçilen tarihte zaten '{$cakisan->adi_soyadi}' isimli personele atanmıştır.");
        }

        $saveData = [
            'personel_id' => $personelId,
            'ekip_kodu_id' => $ekipKoduId,
            'baslangic_tarihi' => $baslangic,
            'bitis_tarihi' => $bitis,
            'firma_id' => $firmaId,
            'ekip_sefi_mi' => $ekipSefiMi,
        ];

        if ($id > 0) {
            $saveData['id'] = $id;
            $personelModel->updateEkipGecmisi($saveData);
            return ['success' => true, 'message' => 'Ekip ataması güncellendi.', 'id' => $id];
        }

        $newId = $personelModel->addEkipGecmisi($saveData);
        return ['success' => true, 'message' => 'Yeni ekip ataması başarıyla oluşturuldu.', 'id' => $newId];
    }

    public function deleteEkipGecmisi(int $id, int $firmaId, bool $force = false): array
    {
        $personelModel = new PersonelModel();
        $gecmis = $personelModel->getSingleEkipGecmisi($id);
        if (!$gecmis) {
            throw new \RuntimeException('Ekip atama kaydı bulunamadı.');
        }

        if (!$force && $personelModel->hasRelatedTasks($gecmis->personel_id, $gecmis->ekip_kodu_id)) {
            return [
                'status' => 'confirm',
                'message' => 'Bu ekip atamasına ait çalışma kayıtları (Yapılan İşler vb.) bulunmaktadır. Silmeniz durumunda ilişkili kayıtlar da etkilenecektir. Devam etmek istiyor musunuz?'
            ];
        }

        $personelModel->deleteEkipGecmisi($id, $force);
        return ['success' => true, 'message' => 'Ekip ataması başarıyla silindi.'];
    }

    public function saveCalismaGecmisi(array $data, int $firmaId): array
    {
        $personelModel = new PersonelModel();
        $personelId = (int) ($data['personel_id'] ?? 0);
        $iseGiris = trim((string) ($data['ise_giris_tarihi'] ?? ''));
        $istenCikis = trim((string) ($data['isten_cikis_tarihi'] ?? '')) ?: null;
        $id = (int) ($data['id'] ?? 0);

        if ($personelId <= 0 || !$iseGiris) {
            throw new \InvalidArgumentException('Personel ve işe giriş tarihi zorunludur.');
        }

        $saveData = [
            'personel_id' => $personelId,
            'ise_giris_tarihi' => $iseGiris,
            'isten_cikis_tarihi' => $istenCikis,
            'personel_sinifi' => in_array(($data['personel_sinifi'] ?? ''), ['Beyaz Yaka', 'Mavi Yaka'], true) ? $data['personel_sinifi'] : 'Beyaz Yaka',
            'saha_takibi' => !empty($data['saha_takibi']) ? 1 : 0,
            'arac_kullanim' => trim((string) ($data['arac_kullanim'] ?? 'Yok')),
            'sgk_yapilan_firma' => trim((string) ($data['sgk_yapilan_firma'] ?? 'Yok')),
            'disardan_sigortali' => 0,
            'gorunum_modulleri' => null,
            'isten_ayrilis_nedeni' => trim((string) ($data['isten_ayrilis_nedeni'] ?? '')) ?: null,
            'isten_ayrilis_belge_yolu' => trim((string) ($data['isten_ayrilis_belge_yolu'] ?? '')) ?: null,
        ];

        if ($id > 0) {
            $saveData['id'] = $id;
            $personelModel->updateCalismaGecmisi($saveData);
            return ['success' => true, 'message' => 'Çalışma geçmişi dönemi güncellendi.', 'id' => $id];
        }

        $newId = $personelModel->addCalismaGecmisi($saveData);
        return ['success' => true, 'message' => 'Yeni çalışma dönemi başarıyla kaydedildi.', 'id' => $newId];
    }

    public function deleteCalismaGecmisi(int $id, int $firmaId): array
    {
        $personelModel = new PersonelModel();
        $gecmis = $personelModel->getSingleCalismaGecmisi($id);
        if (!$gecmis) {
            throw new \RuntimeException('Çalışma geçmişi kaydı bulunamadı.');
        }

        $personelModel->deleteCalismaGecmisi($id);
        return ['success' => true, 'message' => 'Çalışma geçmişi kaydı başarıyla silindi.'];
    }

    // ================= İZİN VE RAPOR METOTLARI =================

    public function getLeaveDetails(int $id, int $firmaId): array
    {
        $personelIzinModel = new PersonelIzinleriModel();
        
        // 1. Hakediş hesapla
        $entitlement = $personelIzinModel->calculateLeaveEntitlement($id);

        // 2. İzin listesi
        $rawLeaves = $personelIzinModel->getPersonelIzinleri($id);
        $leaves = [];
        foreach ($rawLeaves as $l) {
            $leaves[] = [
                'id' => (int) $l->id,
                'personel_id' => (int) $l->personel_id,
                'izin_tipi_id' => (int) $l->izin_tipi_id,
                'izin_tipi_adi' => (string) ($l->izin_tipi_adi ?: 'Belirtilmemiş'),
                'baslangic_tarihi' => (string) ($l->baslangic_tarihi ?: ''),
                'bitis_tarihi' => (string) ($l->bitis_tarihi ?: ''),
                'toplam_gun' => (float) ($l->toplam_gun ?? 0),
                'yillik_izne_etki' => (string) ($l->yillik_izne_etki ?? 'IslemYapma'),
                'ucretli_mi' => (int) ($l->ucretli_mi ?? 0),
                'yetkili_onayina_tabi' => (int) ($l->yetkili_onayina_tabi ?? 0),
                'onay_durumu' => (string) ($l->son_durum ?: ($l->onay_durumu ?: 'Beklemede')),
                'aciklama' => (string) ($l->aciklama ?: ''),
                'onaylar' => array_map(function ($o) {
                    return [
                        'adi' => (string) ($o->adi ?? ''),
                        'tarih' => (string) ($o->tarih ?? ''),
                        'durum' => (string) ($o->durum ?? ''),
                        'aciklama' => (string) ($o->aciklama ?? ''),
                    ];
                }, $l->onaylar ?? []),
            ];
        }

        // 3. İzin türleri tanımları
        $leaveTypes = $this->getLeaveTypes($firmaId);

        return [
            'entitlement' => [
                'toplam_hakedis' => (float) ($entitlement['toplam_hakedis'] ?? 0),
                'kullanilan_izin' => (float) ($entitlement['kullanilan_izin'] ?? 0),
                'kalan_izin' => (float) ($entitlement['kalan_izin'] ?? 0),
                'detay' => $entitlement['detay'] ?? [],
            ],
            'leaves' => $leaves,
            'leave_types' => $leaveTypes,
        ];
    }

    public function getLeaveTypes(int $firmaId): array
    {
        $stmt = $this->db->prepare("SELECT id, tur_adi, ucretli_mi, kisa_kod
            FROM tanimlamalar
            WHERE grup = 'izin_turu'
              AND silinme_tarihi IS NULL
              AND kisa_kod NOT IN ('X', 'x', 'HT', 'HTÇ', 'RTÇ')
              AND (normal_mesai_sayilir IS NULL OR normal_mesai_sayilir = 0)
            ORDER BY tur_adi ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveLeave(array $data, int $firmaId, int $userId): array
    {
        $personelId = (int) ($data['personel_id'] ?? 0);
        $izinTipiId = (int) ($data['izin_tipi_id'] ?? 0);
        $baslangic = trim((string) ($data['baslangic_tarihi'] ?? ''));
        $bitis = trim((string) ($data['bitis_tarihi'] ?? ''));
        $toplamGun = (float) ($data['toplam_gun'] ?? 0);
        $aciklama = trim((string) ($data['aciklama'] ?? ''));
        $onayDurumu = in_array(($data['onay_durumu'] ?? ''), ['Onaylandı', 'Reddedildi', 'Beklemede'], true) ? $data['onay_durumu'] : 'Onaylandı';
        $yillikIzneEtki = !empty($data['yillik_izne_etki']) ? 'Dus' : 'IslemYapma';
        $id = (int) ($data['id'] ?? 0);

        if ($personelId <= 0 || $izinTipiId <= 0) {
            throw new \InvalidArgumentException('Personel ve izin türü seçimi zorunludur.');
        }
        if (!$baslangic || !$bitis) {
            throw new \InvalidArgumentException('Başlangıç ve bitiş tarihleri zorunludur.');
        }
        if ($toplamGun <= 0) {
            throw new \InvalidArgumentException('Geçerli bir izin süresi (gün) girilmelidir.');
        }

        // Personel firmaya ait mi kontrol et
        $stmtP = $this->db->prepare("SELECT id, adi_soyadi FROM personel WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $stmtP->execute([$personelId, $firmaId]);
        $personel = $stmtP->fetch(PDO::FETCH_OBJ);
        if (!$personel) {
            throw new \RuntimeException('Personel kaydı bulunamadı.');
        }

        $logModel = new SystemLogModel();

        if ($id > 0) {
            // Güncelleme
            $stmt = $this->db->prepare("UPDATE personel_izinleri SET
                izin_tipi_id = :izin_tipi_id,
                baslangic_tarihi = :baslangic,
                bitis_tarihi = :bitis,
                toplam_gun = :toplam_gun,
                yillik_izne_etki = :yillik_izne_etki,
                aciklama = :aciklama,
                onay_durumu = :onay_durumu
                WHERE id = :id AND personel_id = :personel_id AND silinme_tarihi IS NULL");
            $stmt->execute([
                ':izin_tipi_id' => $izinTipiId,
                ':baslangic' => $baslangic,
                ':bitis' => $bitis,
                ':toplam_gun' => $toplamGun,
                ':yillik_izne_etki' => $yillikIzneEtki,
                ':aciklama' => $aciklama,
                ':onay_durumu' => $onayDurumu,
                ':id' => $id,
                ':personel_id' => $personelId,
            ]);

            $logModel->logAction($userId, 'İzin Güncelleme', "{$personel->adi_soyadi} isimli personelin izin kaydı güncellendi (ID: {$id}).", SystemLogModel::LEVEL_INFO);
            return ['success' => true, 'message' => 'İzin kaydı başarıyla güncellendi.', 'id' => $id];
        }

        // Yeni Ekleme
        $stmt = $this->db->prepare("INSERT INTO personel_izinleri (
            personel_id, izin_tipi_id, baslangic_tarihi, bitis_tarihi, toplam_gun,
            yillik_izne_etki, aciklama, onay_durumu, talep_tarihi, olusturma_tarihi
        ) VALUES (
            :personel_id, :izin_tipi_id, :baslangic, :bitis, :toplam_gun,
            :yillik_izne_etki, :aciklama, :onay_durumu, NOW(), CURDATE()
        )");
        $stmt->execute([
            ':personel_id' => $personelId,
            ':izin_tipi_id' => $izinTipiId,
            ':baslangic' => $baslangic,
            ':bitis' => $bitis,
            ':toplam_gun' => $toplamGun,
            ':yillik_izne_etki' => $yillikIzneEtki,
            ':aciklama' => $aciklama,
            ':onay_durumu' => $onayDurumu,
        ]);
        $newId = (int) $this->db->lastInsertId();

        // Eğer onaylandı olarak kaydedildiyse onay kaydını da oluştur
        if ($onayDurumu === 'Onaylandı') {
            $stmtOnay = $this->db->prepare("INSERT INTO izin_onaylari (izin_id, onaylayan_id, onay_durumu, onay_tarihi, seviye_no, aciklama)
                VALUES (?, ?, 'Onaylandı', NOW(), 1, 'Mobil SPA üzerinden onaylı oluşturuldu')");
            $stmtOnay->execute([$newId, $userId]);
        }

        $logModel->logAction($userId, 'İzin Ekleme', "{$personel->adi_soyadi} isimli personele {$toplamGun} günlük izin kaydı eklendi (ID: {$newId}).", SystemLogModel::LEVEL_INFO);
        return ['success' => true, 'message' => 'İzin kaydı başarıyla oluşturuldu.', 'id' => $newId];
    }

    public function deleteLeave(int $id, int $personelId, int $firmaId, int $userId): array
    {
        // Kaydı kontrol et
        $stmt = $this->db->prepare("SELECT pi.id, pi.onay_durumu, t.yetkili_onayina_tabi, p.adi_soyadi
            FROM personel_izinleri pi
            JOIN personel p ON p.id = pi.personel_id
            LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.id = ? AND pi.personel_id = ? AND p.firma_id = ? AND pi.silinme_tarihi IS NULL");
        $stmt->execute([$id, $personelId, $firmaId]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$row) {
            throw new \RuntimeException('İzin kaydı bulunamadı.');
        }

        // Soft delete
        $stmtDel = $this->db->prepare("UPDATE personel_izinleri SET
            silinme_tarihi = NOW(),
            silen_kullanici = ?,
            silinme_aciklama = 'Mobil SPA üzerinden silindi'
            WHERE id = ? AND personel_id = ?");
        $stmtDel->execute([$userId, $id, $personelId]);

        $logModel = new SystemLogModel();
        $logModel->logAction($userId, 'İzin Silme', "{$row->adi_soyadi} isimli personelin izin kaydı silindi (ID: {$id}).", SystemLogModel::LEVEL_IMPORTANT);

        return ['success' => true, 'message' => 'İzin kaydı başarıyla silindi.'];
    }

    public function approveOrRejectLeave(int $id, int $personelId, string $status, ?string $note, int $firmaId, int $userId): array
    {
        if (!in_array($status, ['Onaylandı', 'Reddedildi'], true)) {
            throw new \InvalidArgumentException('Geçersiz onay durumu.');
        }

        $stmt = $this->db->prepare("SELECT pi.id, p.adi_soyadi
            FROM personel_izinleri pi
            JOIN personel p ON p.id = pi.personel_id
            WHERE pi.id = ? AND pi.personel_id = ? AND p.firma_id = ? AND pi.silinme_tarihi IS NULL");
        $stmt->execute([$id, $personelId, $firmaId]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$row) {
            throw new \RuntimeException('İzin kaydı bulunamadı.');
        }

        // Durumu güncelle
        $stmtUp = $this->db->prepare("UPDATE personel_izinleri SET onay_durumu = ? WHERE id = ?");
        $stmtUp->execute([$status, $id]);

        // Onay geçmişine ekle/güncelle
        $stmtCheck = $this->db->prepare("SELECT id FROM izin_onaylari WHERE izin_id = ? ORDER BY seviye_no ASC LIMIT 1");
        $stmtCheck->execute([$id]);
        $existing = $stmtCheck->fetch(PDO::FETCH_OBJ);

        if ($existing) {
            $stmtOnay = $this->db->prepare("UPDATE izin_onaylari SET onaylayan_id = ?, onay_durumu = ?, onay_tarihi = NOW(), aciklama = ? WHERE id = ?");
            $stmtOnay->execute([$userId, $status, $note ?: "Mobil SPA üzerinden {$status}", $existing->id]);
        } else {
            $stmtOnay = $this->db->prepare("INSERT INTO izin_onaylari (izin_id, onaylayan_id, onay_durumu, onay_tarihi, seviye_no, aciklama)
                VALUES (?, ?, ?, NOW(), 1, ?)");
            $stmtOnay->execute([$id, $userId, $status, $note ?: "Mobil SPA üzerinden {$status}"]);
        }

        $logModel = new SystemLogModel();
        $logModel->logAction($userId, "İzin {$status}", "{$row->adi_soyadi} personeline ait izin talebi {$status} (ID: {$id}).", SystemLogModel::LEVEL_INFO);

        return ['success' => true, 'message' => "İzin talebi {$status}."];
    }

    // ================= EK ÖDEME METOTLARI =================

    public function getEkOdemelerDetails(int $personelId, int $firmaId): array
    {
        $ekOdemeModel = new PersonelEkOdemelerModel();
        $rawEkOdemeler = $ekOdemeModel->getPersonelEkOdemeler($personelId);

        $toplamEkOdeme = 0.0;
        $tekSeferAdet = 0;
        $tekSeferTutar = 0.0;
        $surekliAdet = 0;
        $surekliTutar = 0.0;
        $bankaTutar = 0.0;
        $eldenTutar = 0.0;

        $items = [];
        foreach ($rawEkOdemeler as $k) {
            $tutar = (float) ($k->tutar ?? 0);
            $resmiTutar = (float) ($k->resmi_tutar ?? 0);
            $tekrarTipi = (string) ($k->tekrar_tipi ?? 'tek_sefer');
            $hesaplamaTipi = (string) ($k->hesaplama_tipi ?? 'sabit');
            $aktif = (int) ($k->aktif ?? 1);
            $bankaMatrah = (int) ($k->banka_matrahina_ekle ?? 1);

            if ($tekrarTipi === 'surekli') {
                if ($aktif === 1) {
                    $surekliAdet++;
                    if ($hesaplamaTipi === 'sabit') {
                        $surekliTutar += $tutar;
                        $toplamEkOdeme += $tutar;
                    }
                }
            } else {
                $tekSeferAdet++;
                $tekSeferTutar += $tutar;
                $toplamEkOdeme += $tutar;
            }

            if ($bankaMatrah === 1) {
                $bankaTutar += $tutar;
            } else {
                $eldenTutar += $tutar;
            }

            $items[] = [
                'id' => (int) $k->id,
                'personel_id' => (int) $k->personel_id,
                'parametre_id' => !empty($k->parametre_id) ? (int) $k->parametre_id : null,
                'parametre_adi' => (string) ($k->parametre_adi ?: ($k->tur ? ucfirst($k->tur) : 'Ek Ödeme')),
                'parametre_kodu' => (string) ($k->parametre_kodu ?? ''),
                'tur' => (string) ($k->tur ?? 'diger'),
                'tekrar_tipi' => $tekrarTipi,
                'hesaplama_tipi' => $hesaplamaTipi,
                'tutar' => $tutar,
                'resmi_tutar' => $resmiTutar,
                'oran' => (float) ($k->oran ?? 0),
                'tarih' => (string) ($k->tarih ?? ''),
                'donem_id' => !empty($k->donem_id) ? (int) $k->donem_id : null,
                'donem_adi' => (string) ($k->donem_adi ?? ''),
                'kapali_mi' => (int) ($k->kapali_mi ?? 0),
                'baslangic_donemi' => (string) ($k->baslangic_donemi ?? ''),
                'bitis_donemi' => (string) ($k->bitis_donemi ?? ''),
                'banka_matrahina_ekle' => $bankaMatrah,
                'aciklama' => (string) ($k->aciklama ?? ''),
                'aktif' => $aktif,
                'is_puantaj' => (strpos((string) ($k->aciklama ?? ''), '[Puantaj]') !== false),
                'kayit_yapan_ad_soyad' => (string) ($k->kayit_yapan_ad_soyad ?? ''),
            ];
        }

        $parametreModel = new BordroParametreModel();
        $gelirParametreleri = [];
        foreach ($parametreModel->getGelirTurleri() as $gp) {
            $gelirParametreleri[] = [
                'id' => (int) $gp->id,
                'kod' => (string) $gp->kod,
                'etiket' => (string) $gp->etiket,
                'hesaplama_tipi' => (string) ($gp->hesaplama_tipi ?? 'sabit'),
                'oran' => (float) ($gp->oran ?? 0),
                'varsayilan_tutar' => (float) ($gp->varsayilan_tutar ?? 0),
                'sgk_matrahi_dahil' => (int) ($gp->sgk_matrahi_dahil ?? 0),
                'gelir_vergisi_dahil' => (int) ($gp->gelir_vergisi_dahil ?? 1),
                'damga_vergisi_dahil' => (int) ($gp->damga_vergisi_dahil ?? 0),
            ];
        }

        $donemModel = new BordroDonemModel();
        $rawDonemler = $donemModel->getAllDonemsForFilter();
        $donemler = [];
        foreach ($rawDonemler as $d) {
            $donemler[] = [
                'id' => (int) $d->id,
                'donem_adi' => (string) $d->donem_adi,
                'baslangic_tarihi' => (string) $d->baslangic_tarihi,
                'bitis_tarihi' => (string) $d->bitis_tarihi,
                'kapali_mi' => (int) ($d->kapali_mi ?? 0),
            ];
        }

        return [
            'items' => $items,
            'summary' => [
                'toplam_tutar' => $toplamEkOdeme,
                'tek_sefer_adet' => $tekSeferAdet,
                'tek_sefer_tutar' => $tekSeferTutar,
                'surekli_adet' => $surekliAdet,
                'surekli_tutar' => $surekliTutar,
                'banka_tutar' => $bankaTutar,
                'elden_tutar' => $eldenTutar,
            ],
            'gelir_parametreleri' => $gelirParametreleri,
            'donemler' => $donemler,
        ];
    }

    public function saveEkOdeme(array $data, int $firmaId, int $userId): array
    {
        $personelId = (int) ($data['personel_id'] ?? 0);
        $personel = $this->mobileDirectoryDetail($personelId, $firmaId);
        if (!$personel) {
            throw new \RuntimeException('Personel bulunamadı veya erişim yetkiniz yok.');
        }

        $id = (int) ($data['id'] ?? 0);
        $tekrarTipi = in_array(($data['tekrar_tipi'] ?? 'tek_sefer'), ['tek_sefer', 'surekli'], true) ? $data['tekrar_tipi'] : 'tek_sefer';
        $hesaplamaTipi = in_array(($data['hesaplama_tipi'] ?? 'sabit'), ['sabit', 'oran', 'oran_net', 'oran_brut', 'aylik_fiili_gun_net', 'aylik_gun_net'], true) ? $data['hesaplama_tipi'] : 'sabit';

        if ($tekrarTipi === 'tek_sefer' && !empty($data['donem_id'])) {
            $donemModel = new BordroDonemModel();
            $donem = $donemModel->getDonemById((int) $data['donem_id']);
            if ($donem && (int) ($donem->kapali_mi ?? 0) === 1) {
                throw new \InvalidArgumentException('Seçilen dönem kapatılmış. Kapalı dönemlere ek ödeme eklenemez/düzenlenemez.');
            }
        }

        $ekOdemeModel = new PersonelEkOdemelerModel();
        $tarih = !empty($data['tarih']) ? \App\Helper\Date::dttoeng($data['tarih']) : date('Y-m-d');
        if (!$tarih || $tarih === '1970-01-01') {
            $tarih = date('Y-m-d');
        }

        $saveData = [
            'personel_id' => $personelId,
            'tur' => mb_substr(trim((string) ($data['tur'] ?? 'diger')), 0, 50),
            'tekrar_tipi' => $tekrarTipi,
            'hesaplama_tipi' => $hesaplamaTipi,
            'tutar' => max(0, (float) ($data['tutar'] ?? 0)),
            'resmi_tutar' => max(0, (float) ($data['resmi_tutar'] ?? 0)),
            'oran' => max(0, (float) ($data['oran'] ?? 0)),
            'tarih' => $tarih,
            'aciklama' => mb_substr(trim((string) ($data['aciklama'] ?? '')), 0, 500),
            'parametre_id' => !empty($data['parametre_id']) ? (int) $data['parametre_id'] : null,
            'banka_matrahina_ekle' => isset($data['banka_matrahina_ekle']) ? (int) $data['banka_matrahina_ekle'] : 1,
            'aktif' => 1,
        ];

        if ($tekrarTipi === 'tek_sefer') {
            $saveData['donem_id'] = !empty($data['donem_id']) ? (int) $data['donem_id'] : null;
            $saveData['baslangic_donemi'] = null;
            $saveData['bitis_donemi'] = null;
        } else {
            $saveData['donem_id'] = null;
            $baslangic = !empty($data['baslangic_donemi']) ? \App\Helper\Date::dttoeng($data['baslangic_donemi']) : date('Y-m-01');
            $bitis = !empty($data['bitis_donemi']) ? \App\Helper\Date::dttoeng($data['bitis_donemi']) : null;
            $saveData['baslangic_donemi'] = $baslangic ?: date('Y-m-01');
            $saveData['bitis_donemi'] = $bitis;
        }

        $logModel = new SystemLogModel();

        if ($id > 0) {
            $mevcut = $ekOdemeModel->getEkOdeme($id);
            if (!$mevcut || (int) $mevcut->personel_id !== $personelId) {
                throw new \RuntimeException('Ek ödeme kaydı bulunamadı.');
            }
            if (!empty($mevcut->donem_id)) {
                $donemModel = new BordroDonemModel();
                $donem = $donemModel->getDonemById((int) $mevcut->donem_id);
                if ($donem && (int) ($donem->kapali_mi ?? 0) === 1) {
                    throw new \InvalidArgumentException('Kapalı döneme ait ek ödeme düzenlenemez.');
                }
            }

            $ekOdemeModel->updateEkOdeme($id, $saveData);
            if ($tekrarTipi === 'tek_sefer') {
                $ekOdemeModel->temizleKapsamDisiDonemKayitlari($id);
            } else {
                $ekOdemeModel->temizleKapsamDisiDonemKayitlari($id, $saveData['baslangic_donemi'], $saveData['bitis_donemi']);
            }

            $logModel->logAction($userId, 'Ek Ödeme Güncelleme', "{$personel['adi_soyadi']} personeline ait ek ödeme güncellendi (ID: {$id}).", SystemLogModel::LEVEL_INFO);
            return ['success' => true, 'message' => 'Ek ödeme başarıyla güncellendi.', 'id' => $id];
        }

        $saveData['kayit_yapan'] = $userId;
        $newId = $ekOdemeModel->saveWithAttr($saveData);
        if (!$newId) {
            throw new \RuntimeException('Ek ödeme kaydedilemedi.');
        }

        $logModel->logAction($userId, 'Ek Ödeme Ekleme', "{$personel['adi_soyadi']} personeline yeni ek ödeme eklendi (ID: {$newId}).", SystemLogModel::LEVEL_INFO);
        return ['success' => true, 'message' => 'Ek ödeme başarıyla oluşturuldu.', 'id' => (int) $newId];
    }

    public function deleteEkOdeme(int $id, int $personelId, int $firmaId, int $userId): array
    {
        $personel = $this->mobileDirectoryDetail($personelId, $firmaId);
        if (!$personel) {
            throw new \RuntimeException('Personel bulunamadı veya erişim yetkiniz yok.');
        }

        $ekOdemeModel = new PersonelEkOdemelerModel();
        $ekOdeme = $ekOdemeModel->getEkOdeme($id);
        if (!$ekOdeme || (int) $ekOdeme->personel_id !== $personelId) {
            throw new \RuntimeException('Ek ödeme kaydı bulunamadı.');
        }

        if (!empty($ekOdeme->donem_id)) {
            $donemModel = new BordroDonemModel();
            $donem = $donemModel->getDonemById((int) $ekOdeme->donem_id);
            if ($donem && (int) ($donem->kapali_mi ?? 0) === 1) {
                throw new \InvalidArgumentException('Kapalı dönemlerdeki ek ödemeler silinemez.');
            }
        }

        $res = $ekOdemeModel->softDelete($id);
        if (!$res) {
            throw new \RuntimeException('Silme işlemi başarısız.');
        }

        $logModel = new SystemLogModel();
        $logModel->logAction($userId, 'Ek Ödeme Silme', "{$personel['adi_soyadi']} personeline ait ek ödeme silindi (ID: {$id}).", SystemLogModel::LEVEL_IMPORTANT);

        return ['success' => true, 'message' => 'Ek ödeme başarıyla silindi.'];
    }

    public function sonlandirEkOdeme(int $id, int $personelId, ?string $bitisDonemi, int $firmaId, int $userId): array
    {
        $personel = $this->mobileDirectoryDetail($personelId, $firmaId);
        if (!$personel) {
            throw new \RuntimeException('Personel bulunamadı veya erişim yetkiniz yok.');
        }

        $ekOdemeModel = new PersonelEkOdemelerModel();
        $bitis = !empty($bitisDonemi) ? \App\Helper\Date::dttoeng($bitisDonemi) : date('Y-m-d');
        $res = $ekOdemeModel->sonlandirSurekliOdeme($id, $bitis);
        if (!$res) {
            throw new \RuntimeException('Sonlandırma işlemi başarısız.');
        }

        $logModel = new SystemLogModel();
        $logModel->logAction($userId, 'Ek Ödeme Sonlandırma', "{$personel['adi_soyadi']} personeline ait sürekli ek ödeme sonlandırıldı (ID: {$id}).", SystemLogModel::LEVEL_INFO);

        return ['success' => true, 'message' => 'Sürekli ek ödeme başarıyla sonlandırıldı.'];
    }

    // ================= KESİNTİ METOTLARI =================

    public function getKesintilerDetails(int $personelId, int $firmaId): array
    {
        $kesintiModel = new PersonelKesintileriModel();
        $rawKesintiler = $kesintiModel->getPersonelKesintileri($personelId);

        $toplamKesinti = 0.0;
        $tekSeferAdet = 0;
        $tekSeferTutar = 0.0;
        $taksitliAdet = 0;
        $taksitliTutar = 0.0;
        $surekliAdet = 0;
        $surekliTutar = 0.0;
        $bankaTutar = 0.0;
        $eldenTutar = 0.0;

        $items = [];
        foreach ($rawKesintiler as $k) {
            $tutar = (float) ($k->tutar ?? 0);
            $tekrarTipi = (string) ($k->tekrar_tipi ?? 'tek_sefer');
            $hesaplamaTipi = (string) ($k->hesaplama_tipi ?? 'sabit');
            $aktif = (int) ($k->aktif ?? 1);
            $bankaMatrah = (int) ($k->banka_matrahina_ekle ?? 1);

            if ($tekrarTipi === 'taksitli') {
                $taksitliAdet++;
                $taksitliTutar += $tutar;
                $toplamKesinti += $tutar;
            } elseif ($tekrarTipi === 'surekli') {
                if ($aktif === 1) {
                    $surekliAdet++;
                    if ($hesaplamaTipi === 'sabit') {
                        $surekliTutar += $tutar;
                        $toplamKesinti += $tutar;
                    }
                }
            } else {
                $tekSeferAdet++;
                $tekSeferTutar += $tutar;
                $toplamKesinti += $tutar;
            }

            if ($bankaMatrah === 1) {
                $bankaTutar += $tutar;
            } else {
                $eldenTutar += $tutar;
            }

            $items[] = [
                'id' => (int) $k->id,
                'personel_id' => (int) $k->personel_id,
                'parametre_id' => !empty($k->parametre_id) ? (int) $k->parametre_id : null,
                'parametre_adi' => (string) ($k->parametre_adi ?: ($k->tur ? ucfirst($k->tur) : 'Kesinti')),
                'parametre_kodu' => (string) ($k->parametre_kodu ?? ''),
                'tur' => (string) ($k->tur ?? 'diger'),
                'tekrar_tipi' => $tekrarTipi,
                'hesaplama_tipi' => $hesaplamaTipi,
                'tutar' => $tutar,
                'oran' => (float) ($k->oran ?? 0),
                'taksit_sayisi' => !empty($k->taksit_sayisi) ? (int) $k->taksit_sayisi : 1,
                'tarih' => (string) ($k->tarih ?? ''),
                'donem_id' => !empty($k->donem_id) ? (int) $k->donem_id : null,
                'donem_adi' => (string) ($k->donem_adi ?? ''),
                'kapali_mi' => (int) ($k->kapali_mi ?? 0),
                'baslangic_donemi' => (string) ($k->baslangic_donemi ?? ''),
                'bitis_donemi' => (string) ($k->bitis_donemi ?? ''),
                'banka_matrahina_ekle' => $bankaMatrah,
                'icra_id' => !empty($k->icra_id) ? (int) $k->icra_id : null,
                'dosya_no' => (string) ($k->dosya_no ?? ''),
                'icra_dairesi' => (string) ($k->icra_dairesi ?? ''),
                'durum' => (string) ($k->durum ?? 'beklemede'),
                'aciklama' => (string) ($k->aciklama ?? ''),
                'aktif' => $aktif,
                'kayit_yapan_ad_soyad' => (string) ($k->kayit_yapan_ad_soyad ?? ''),
            ];
        }

        $parametreModel = new BordroParametreModel();
        $kesintiParametreleri = [];
        foreach ($parametreModel->getKesintiTurleri() as $kp) {
            $kesintiParametreleri[] = [
                'id' => (int) $kp->id,
                'kod' => (string) $kp->kod,
                'etiket' => (string) $kp->etiket,
                'hesaplama_tipi' => (string) ($kp->hesaplama_tipi ?? 'sabit'),
                'oran' => (float) ($kp->oran ?? 0),
                'varsayilan_tutar' => (float) ($kp->varsayilan_tutar ?? 0),
            ];
        }

        $donemModel = new BordroDonemModel();
        $rawDonemler = $donemModel->getAllDonemsForFilter();
        $donemler = [];
        foreach ($rawDonemler as $d) {
            $donemler[] = [
                'id' => (int) $d->id,
                'donem_adi' => (string) $d->donem_adi,
                'baslangic_tarihi' => (string) $d->baslangic_tarihi,
                'bitis_tarihi' => (string) $d->bitis_tarihi,
                'kapali_mi' => (int) ($d->kapali_mi ?? 0),
            ];
        }

        $icraModel = new PersonelIcralariModel();
        $rawIcralar = $icraModel->getDevamEdenIcralar($personelId);
        $icralar = [];
        foreach ($rawIcralar as $ic) {
            $icralar[] = [
                'id' => (int) $ic->id,
                'dosya_no' => (string) $ic->dosya_no,
                'icra_dairesi' => (string) $ic->icra_dairesi,
                'alacakli' => (string) ($ic->alacakli ?? ''),
                'toplam_borc' => (float) ($ic->toplam_borc ?? 0),
                'aylik_kesinti_tutari' => (float) ($ic->aylik_kesinti_tutari ?? 0),
            ];
        }

        return [
            'items' => $items,
            'summary' => [
                'toplam_tutar' => $toplamKesinti,
                'tek_sefer_adet' => $tekSeferAdet,
                'tek_sefer_tutar' => $tekSeferTutar,
                'taksitli_adet' => $taksitliAdet,
                'taksitli_tutar' => $taksitliTutar,
                'surekli_adet' => $surekliAdet,
                'surekli_tutar' => $surekliTutar,
                'banka_tutar' => $bankaTutar,
                'elden_tutar' => $eldenTutar,
            ],
            'kesinti_parametreleri' => $kesintiParametreleri,
            'donemler' => $donemler,
            'icralar' => $icralar,
        ];
    }

    public function saveKesinti(array $data, int $firmaId, int $userId): array
    {
        $personelId = (int) ($data['personel_id'] ?? 0);
        $personel = $this->mobileDirectoryDetail($personelId, $firmaId);
        if (!$personel) {
            throw new \RuntimeException('Personel bulunamadı veya erişim yetkiniz yok.');
        }

        $id = (int) ($data['id'] ?? 0);
        $tekrarTipi = in_array(($data['tekrar_tipi'] ?? 'tek_sefer'), ['tek_sefer', 'surekli', 'taksitli'], true) ? $data['tekrar_tipi'] : 'tek_sefer';
        $hesaplamaTipi = in_array(($data['hesaplama_tipi'] ?? 'sabit'), ['sabit', 'oran', 'oran_net', 'oran_brut'], true) ? $data['hesaplama_tipi'] : 'sabit';

        if (($tekrarTipi === 'tek_sefer' || $tekrarTipi === 'taksitli') && !empty($data['donem_id'])) {
            $donemModel = new BordroDonemModel();
            $donem = $donemModel->getDonemById((int) $data['donem_id']);
            if ($donem && (int) ($donem->kapali_mi ?? 0) === 1) {
                throw new \InvalidArgumentException('Seçilen dönem kapatılmış. Kapalı dönemlere kesinti eklenemez/düzenlenemez.');
            }
        }

        $kesintiModel = new PersonelKesintileriModel();
        $tarih = !empty($data['tarih']) ? \App\Helper\Date::dttoeng($data['tarih']) : date('Y-m-d');
        if (!$tarih || $tarih === '1970-01-01') {
            $tarih = date('Y-m-d');
        }

        $saveData = [
            'personel_id' => $personelId,
            'tur' => mb_substr(trim((string) ($data['tur'] ?? 'diger')), 0, 50),
            'tekrar_tipi' => $tekrarTipi,
            'hesaplama_tipi' => $hesaplamaTipi,
            'tutar' => max(0, (float) ($data['tutar'] ?? 0)),
            'oran' => max(0, (float) ($data['oran'] ?? 0)),
            'tarih' => $tarih,
            'banka_matrahina_ekle' => isset($data['banka_matrahina_ekle']) ? (int) $data['banka_matrahina_ekle'] : 1,
            'aciklama' => mb_substr(trim((string) ($data['aciklama'] ?? '')), 0, 500),
            'parametre_id' => !empty($data['parametre_id']) ? (int) $data['parametre_id'] : null,
            'icra_id' => !empty($data['icra_id']) ? (int) $data['icra_id'] : null,
            'aktif' => 1,
        ];

        if ($tekrarTipi === 'taksitli') {
            $taksitSayisi = max(1, min(60, (int) ($data['taksit_sayisi'] ?? 1)));
            $saveData['taksit_sayisi'] = $taksitSayisi;
            $saveData['donem_id'] = !empty($data['donem_id']) ? (int) $data['donem_id'] : null;
            $saveData['baslangic_donemi'] = null;
            $saveData['bitis_donemi'] = null;
        } elseif ($tekrarTipi === 'tek_sefer') {
            $saveData['donem_id'] = !empty($data['donem_id']) ? (int) $data['donem_id'] : null;
            $saveData['baslangic_donemi'] = null;
            $saveData['bitis_donemi'] = null;
            $saveData['taksit_sayisi'] = null;
        } else {
            $saveData['donem_id'] = null;
            $saveData['taksit_sayisi'] = null;
            $baslangic = !empty($data['baslangic_donemi']) ? \App\Helper\Date::dttoeng($data['baslangic_donemi']) : date('Y-m-01');
            $bitis = !empty($data['bitis_donemi']) ? \App\Helper\Date::dttoeng($data['bitis_donemi']) : null;
            $saveData['baslangic_donemi'] = $baslangic ?: date('Y-m-01');
            $saveData['bitis_donemi'] = $bitis;
        }

        $logModel = new SystemLogModel();

        if ($id > 0) {
            $mevcut = $kesintiModel->getKesinti($id);
            if (!$mevcut || (int) $mevcut->personel_id !== $personelId) {
                throw new \RuntimeException('Kesinti kaydı bulunamadı.');
            }
            if (!empty($mevcut->donem_id)) {
                $donemModel = new BordroDonemModel();
                $donem = $donemModel->getDonemById((int) $mevcut->donem_id);
                if ($donem && (int) ($donem->kapali_mi ?? 0) === 1) {
                    throw new \InvalidArgumentException('Kapalı döneme ait kesinti düzenlenemez.');
                }
            }

            $kesintiModel->updateKesinti($id, $saveData);
            $logModel->logAction($userId, 'Kesinti Güncelleme', "{$personel['adi_soyadi']} personeline ait kesinti güncellendi (ID: {$id}).", SystemLogModel::LEVEL_INFO);
            return ['success' => true, 'message' => 'Kesinti başarıyla güncellendi.', 'id' => $id];
        }

        $saveData['kayit_yapan'] = $userId;
        $newId = $kesintiModel->saveWithAttr($saveData);
        if (!$newId) {
            throw new \RuntimeException('Kesinti kaydedilemedi.');
        }

        $logModel->logAction($userId, 'Kesinti Ekleme', "{$personel['adi_soyadi']} personeline yeni kesinti eklendi (ID: {$newId}).", SystemLogModel::LEVEL_INFO);
        return ['success' => true, 'message' => 'Kesinti başarıyla oluşturuldu.', 'id' => (int) $newId];
    }

    public function deleteKesinti(int $id, int $personelId, int $firmaId, int $userId): array
    {
        $personel = $this->mobileDirectoryDetail($personelId, $firmaId);
        if (!$personel) {
            throw new \RuntimeException('Personel bulunamadı veya erişim yetkiniz yok.');
        }

        $kesintiModel = new PersonelKesintileriModel();
        $kesinti = $kesintiModel->getKesinti($id);
        if (!$kesinti || (int) $kesinti->personel_id !== $personelId) {
            throw new \RuntimeException('Kesinti kaydı bulunamadı.');
        }

        if (!empty($kesinti->donem_id)) {
            $donemModel = new BordroDonemModel();
            $donem = $donemModel->getDonemById((int) $kesinti->donem_id);
            if ($donem && (int) ($donem->kapali_mi ?? 0) === 1) {
                throw new \InvalidArgumentException('Kapalı dönemlerdeki kesintiler silinemez.');
            }
        }

        $res = $kesintiModel->softDelete($id);
        if (!$res) {
            throw new \RuntimeException('Silme işlemi başarısız.');
        }

        $logModel = new SystemLogModel();
        $logModel->logAction($userId, 'Kesinti Silme', "{$personel['adi_soyadi']} personeline ait kesinti silindi (ID: {$id}).", SystemLogModel::LEVEL_IMPORTANT);

        return ['success' => true, 'message' => 'Kesinti başarıyla silindi.'];
    }

    public function sonlandirKesinti(int $id, int $personelId, ?string $bitisDonemi, int $firmaId, int $userId): array
    {
        $personel = $this->mobileDirectoryDetail($personelId, $firmaId);
        if (!$personel) {
            throw new \RuntimeException('Personel bulunamadı veya erişim yetkiniz yok.');
        }

        $kesintiModel = new PersonelKesintileriModel();
        $bitis = !empty($bitisDonemi) ? \App\Helper\Date::dttoeng($bitisDonemi) : date('Y-m-d');
        $res = $kesintiModel->sonlandirSurekliKesinti($id, $bitis);
        if (!$res) {
            throw new \RuntimeException('Sonlandırma işlemi başarısız.');
        }

        $logModel = new SystemLogModel();
        $logModel->logAction($userId, 'Kesinti Sonlandırma', "{$personel['adi_soyadi']} personeline ait sürekli kesinti sonlandırıldı (ID: {$id}).", SystemLogModel::LEVEL_INFO);

        return ['success' => true, 'message' => 'Sürekli kesinti başarıyla sonlandırıldı.'];
    }

    public function mobileDirectoryQuickList(int $firmaId): array
    {
        $params = ['firma_id' => $firmaId, 'modul' => 'personel'];
        $where = $this->baseWhere($params);
        $sql = "SELECT p.id, p.adi_soyadi, p.gorev, p.departman, p.resim_yolu, p.personel_resim_yolu,
                       (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '0000-00-00') AS active
                FROM personel p
                WHERE {$where}
                ORDER BY active DESC, p.adi_soyadi ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];
        foreach ($rows as $r) {
            $encId = \App\Helper\Security::encrypt((string) $r['id']);
            $hasPhoto = trim((string) ($r['personel_resim_yolu'] ?? $r['resim_yolu'] ?? '')) !== '';
            $result[] = [
                'id' => $encId,
                'name' => trim((string) $r['adi_soyadi']),
                'job' => trim((string) ($r['gorev'] ?? '')),
                'department' => trim((string) ($r['departman'] ?? '')),
                'active' => (bool) $r['active'],
                'avatar' => $hasPhoto ? '/api/personnel-photo.php?id=' . rawurlencode($encId) : null,
            ];
        }
        return $result;
    }

    public function getEvrakDetails(int $personelId, int $firmaId): array
    {
        $personel = $this->mobileDirectoryDetail($personelId, $firmaId);
        if (!$personel) {
            throw new \RuntimeException('Personel bulunamadı veya erişim yetkiniz yok.');
        }

        $sql = "SELECT pe.*, u.adi_soyadi as yukleyen_adi
                FROM personel_evraklar pe
                LEFT JOIN users u ON pe.yukleyen_id = u.id
                WHERE pe.personel_id = :personel_id AND pe.aktif = 1
                ORDER BY pe.yukleme_tarihi DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':personel_id' => $personelId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        $turSayilari = [];
        $evrakTurleri = \App\Helper\Helper::EVRAK_TURLERI;

        foreach ($rows as $row) {
            $encId = \App\Helper\Security::encrypt((string) $row['id']);
            $tur = (string) ($row['evrak_turu'] ?? 'diger');
            $turLabel = $evrakTurleri[$tur] ?? 'Diğer';
            $turSayilari[$tur] = ($turSayilari[$tur] ?? 0) + 1;

            $items[] = [
                'id' => $encId,
                'raw_id' => (int) $row['id'],
                'evrak_adi' => (string) ($row['evrak_adi'] ?? ''),
                'evrak_turu' => $tur,
                'evrak_turu_label' => $turLabel,
                'dosya_adi' => (string) ($row['dosya_adi'] ?? ''),
                'orijinal_dosya_adi' => (string) ($row['orijinal_dosya_adi'] ?? ''),
                'dosya_boyutu' => (int) ($row['dosya_boyutu'] ?? 0),
                'dosya_boyutu_formatted' => $this->formatBytes((int) ($row['dosya_boyutu'] ?? 0)),
                'dosya_tipi' => (string) ($row['dosya_tipi'] ?? ''),
                'aciklama' => (string) ($row['aciklama'] ?? ''),
                'yukleme_tarihi' => !empty($row['yukleme_tarihi']) ? date('d.m.Y H:i', strtotime((string) $row['yukleme_tarihi'])) : '',
                'yukleyen_adi' => (string) ($row['yukleyen_adi'] ?? 'Sistem'),
                'view_url' => '/views/personel/evrak-indir.php?id=' . rawurlencode($encId) . '&inline=1',
                'download_url' => '/views/personel/evrak-indir.php?id=' . rawurlencode($encId),
            ];
        }

        return [
            'items' => $items,
            'summary' => [
                'toplam_evrak' => count($items),
                'tur_sayilari' => $turSayilari,
            ],
        ];
    }

    public function uploadEvrak(int $personelId, array $file, array $data, int $firmaId, int $userId): array
    {
        $personel = $this->mobileDirectoryDetail($personelId, $firmaId);
        if (!$personel) {
            throw new \RuntimeException('Personel bulunamadı veya erişim yetkiniz yok.');
        }

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'Dosya boyutu sunucu limitini aşıyor.',
                UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu form limitini aşıyor.',
                UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi.',
                UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi.',
                UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı.',
                UPLOAD_ERR_CANT_WRITE => 'Dosya yazılamadı.',
                UPLOAD_ERR_EXTENSION => 'Dosya uzantısı engellendi.'
            ];
            $errCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            throw new \RuntimeException($uploadErrors[$errCode] ?? 'Dosya yükleme hatası oluştu.');
        }

        $maxSize = 10 * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxSize) {
            throw new \RuntimeException('Dosya boyutu 10MB sınırını aşamaz.');
        }

        $allowedTypes = [
            'application/pdf',
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/webp',
            'image/heic',
            'image/heif',
            'image/gif',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes, true)) {
            throw new \RuntimeException('Desteklenmeyen dosya formatı. Desteklenen: PDF, JPG, PNG, WEBP, DOC, DOCX, XLS, XLSX.');
        }

        $uploadDir = dirname(__DIR__, 2) . '/uploads/personel_evraklar/' . $personelId . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0733, true);
        }

        $newFileName = bin2hex(random_bytes(16)) . '.enc';
        $targetPath = $uploadDir . $newFileName;

        $binaryData = file_get_contents($file['tmp_name']);
        @unlink($file['tmp_name']);

        if ($binaryData === false || file_put_contents($targetPath, \App\Helper\Security::encryptFile($binaryData), LOCK_EX) === false) {
            throw new \RuntimeException('Dosya güvenli biçimde şifrelenemedi veya kaydedilemedi.');
        }

        $evrakAdi = trim((string) ($data['evrak_adi'] ?? ''));
        if ($evrakAdi === '') {
            $evrakAdi = pathinfo((string) ($file['name'] ?? 'Evrak'), PATHINFO_FILENAME);
        }

        $evrakTuru = trim((string) ($data['evrak_turu'] ?? 'diger'));
        if (!array_key_exists($evrakTuru, \App\Helper\Helper::EVRAK_TURLERI)) {
            $evrakTuru = 'diger';
        }

        $aciklama = trim((string) ($data['aciklama'] ?? ''));

        $sql = "INSERT INTO personel_evraklar 
                (personel_id, evrak_adi, evrak_turu, dosya_adi, orijinal_dosya_adi, dosya_boyutu, dosya_tipi, aciklama, yukleyen_id, yukleme_tarihi, aktif)
                VALUES (:personel_id, :evrak_adi, :evrak_turu, :dosya_adi, :orijinal_dosya_adi, :dosya_boyutu, :dosya_tipi, :aciklama, :yukleyen_id, NOW(), 1)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':personel_id' => $personelId,
            ':evrak_adi' => $evrakAdi,
            ':evrak_turu' => $evrakTuru,
            ':dosya_adi' => $newFileName,
            ':orijinal_dosya_adi' => (string) ($file['name'] ?? $newFileName),
            ':dosya_boyutu' => (int) ($file['size'] ?? 0),
            ':dosya_tipi' => $mimeType,
            ':aciklama' => $aciklama,
            ':yukleyen_id' => $userId,
        ]);

        try {
            $logModel = new SystemLogModel();
            $logModel->logAction(
                $userId,
                'Personel Evrak Yükleme (Mobil)',
                sprintf("%s personeline '%s' (%s) adlı evrak yüklendi.", $personel['adi_soyadi'], $evrakAdi, $evrakTuru),
                SystemLogModel::LEVEL_IMPORTANT
            );
        } catch (\Throwable $ignored) {
        }

        return ['success' => true, 'message' => 'Evrak başarıyla yüklendi ve şifrelendi.'];
    }

    public function deleteEvrak(int $id, int $personelId, int $firmaId, int $userId): array
    {
        $personel = $this->mobileDirectoryDetail($personelId, $firmaId);
        if (!$personel) {
            throw new \RuntimeException('Personel bulunamadı veya erişim yetkiniz yok.');
        }

        $stmt = $this->db->prepare("SELECT id, evrak_adi FROM personel_evraklar WHERE id = :id AND personel_id = :personel_id AND aktif = 1");
        $stmt->execute([':id' => $id, ':personel_id' => $personelId]);
        $evrak = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$evrak) {
            throw new \RuntimeException('Evrak kaydı bulunamadı veya zaten silinmiş.');
        }

        $updateStmt = $this->db->prepare("UPDATE personel_evraklar SET aktif = 0, guncelleme_tarihi = NOW() WHERE id = :id");
        $res = $updateStmt->execute([':id' => $id]);
        if (!$res) {
            throw new \RuntimeException('Evrak silme işlemi başarısız oldu.');
        }

        try {
            $logModel = new SystemLogModel();
            $logModel->logAction(
                $userId,
                'Personel Evrak Silme (Mobil)',
                sprintf("%s personeline ait '%s' (ID: %d) evrak silindi.", $personel['adi_soyadi'], $evrak['evrak_adi'], $id),
                SystemLogModel::LEVEL_IMPORTANT
            );
        } catch (\Throwable $ignored) {
        }

        return ['success' => true, 'message' => 'Evrak başarıyla silindi.'];
    }

    private function formatBytes(int $bytes, int $precision = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int) $pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}


