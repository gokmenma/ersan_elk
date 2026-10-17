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
}
