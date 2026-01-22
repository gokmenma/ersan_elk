<?php

namespace App\Model;

use App\Model\Model;
use PDO;

use App\Helper\Security;

class UserModel extends Model
{
    protected $table = 'users';


    public function __construct()
    {
        parent::__construct();
    }


    public function getUserByEmail($email)
    {
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE email = ?");
        $sql->execute(array($email));
        return $sql->fetch(PDO::FETCH_OBJ) ?? null;
    }

    //Kullanıcı adı vey emailden kullanıcı kontrolü yapılır,true veya false döner
    public function checkUser($username)
    {
        $sql = $this->db->prepare("SELECT * FROM $this->table WHERE email_adresi = ? OR user_name = ?");
        $sql->execute(array($username, $username));
        return $sql->fetch(PDO::FETCH_OBJ) ?? null;
    }


    /**
     * Kullanıcıları listelemek için gerekli verileri getirir.
     * @param int $owner_id Verinin sahibi ID'si(Session ID'si gibi)
     * @return array
     */
    public function getUsers($ownerType = null): array
    {
        $ownerID = $_SESSION["owner_id"];

        $sql = $this->db->prepare("SELECT u.* ,ur.role_name   
                                   FROM $this->table u
                                   LEFT JOIN user_roles ur ON ur.id = u.roles
                                   WHERE u.owner_id = :owner_id 
                                   ORDER BY u.id DESC");
        $sql->execute([
            'owner_id' => $ownerID
        ]);

        return $sql->fetchAll(PDO::FETCH_OBJ) ?? [];
    }
    /** 
     * Kullanıcı id'sinden kullanıcının role id'sini döndürür.
     * @param int $userId Kullanıcı ID'si
     * @return int|null Kullanıcı rol ID'si veya null
     * @throws \Exception
     */
    public function getUserRoleID(int $userId): ?int
    {
        $sql = $this->db->prepare("SELECT roles FROM $this->table WHERE id = ?");
        $sql->execute([$userId]);
        $result = $sql->fetch(PDO::FETCH_OBJ);

        if ($result) {
            return (int) $result->roles;
        }

        return null; // Kullanıcı rolü bulunamadıysa null döner
    }


    /**
     * Belirtilen ID'ye sahip kullanıcıyı HTML tablo satırı olarak döndürür.
     *
     * @param int $id Kullanıcı ID'si
     * @return string HTML <tr> satırı
     */
    public function renderUserTableRow(int $id, $isNew = false): string
    {
        $user = $this->find($id);

        if (!$user) {
            return '';
        }

        // Güvenli veri
        $enc_id = htmlspecialchars(Security::encrypt($user->id), ENT_QUOTES, 'UTF-8');
        $userName = htmlspecialchars($user->user_name, ENT_QUOTES, 'UTF-8');
        $fullName = htmlspecialchars($user->adi_soyadi, ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($user->unvani, ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($user->email_adresi, ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars($user->telefon, ENT_QUOTES, 'UTF-8');
        $createdAt = htmlspecialchars($user->created_at, ENT_QUOTES, 'UTF-8');

        ob_start(); // 🔁 Output Buffer başlat
        ?>
        <?php if ($isNew): ?>
            <tr data-id="<?= $enc_id ?>">
            <?php endif; ?>
            <td class="text-center">1</td>
            <td><?= $userName ?></td>
            <td><?= $fullName ?></td>
            <td class="text-center"><?= $title ?></td>
            <td class="text-center"><?= $email ?></td>
            <td class="text-center"><?= $phone ?></td>
            <td><?= $createdAt ?></td>
            <td class="text-center" style="width:5%">
                <div class="flex-shrink-0">
                    <div class="dropdown align-self-start icon-demo-content">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true"
                            aria-expanded="false">
                            <i class="bx bx-list-ul font-size-24 text-dark"></i>
                        </a>
                        <div class="dropdown-menu">
                            <a href="javascript:void(0)" class="dropdown-item kullanici-duzenle" data-id="<?= $enc_id ?>">
                                <span class="mdi mdi-account-edit font-size-18"></span> Düzenle
                            </a>
                            <a href="#" class="dropdown-item kullanici-sil" data-id="<?= $enc_id ?>"
                                data-name="<?= $fullName ?>">
                                <span class="mdi mdi-delete font-size-18"></span> Sil
                            </a>
                        </div>
                    </div>
                </div>
            </td>
            <?php if ($isNew): ?>
            </tr>
        <?php endif; ?>
        <?php
        return ob_get_clean(); // 🔚 HTML'yi döndür
    }


    /** Kullanıcının yetkili olduğu Firmaları getirir */
    public function getUserFirmList()
    {
        $query = $this->db->prepare("Select id, firma_ids from $this->table");
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
    }


    /** İzin onayı yapacak ilk personeli getir */
    public function getIzinOnayPersonel()
    {
        $query = $this->db->prepare("SELECT * FROM $this->table where izin_onayi_yapacakmi = ?
        ORDER BY izin_onay_sirasi LIMIT 1");
        $query->execute(["Evet"]);
        return $query->fetch(PDO::FETCH_OBJ);
    }

    /** 
     * Belirli bir talep türü için mail bildirimlerini açık olan kullanıcıları getirir
     * @param string $talepTuru 'avans', 'izin', 'genel', 'ariza'
     * @return array Kullanıcı listesi
     */
    public function getMailBildirimKullanicilari(string $talepTuru): array
    {
        $column_map = [
            'avans' => 'mail_avans_talep',
            'izin' => 'mail_izin_talep',
            'genel' => 'mail_genel_talep',
            'ariza' => 'mail_ariza_talep'
        ];

        if (!isset($column_map[$talepTuru])) {
            return [];
        }

        $column = $column_map[$talepTuru];
        $query = $this->db->prepare("SELECT * FROM $this->table WHERE $column = ? AND email_adresi IS NOT NULL AND email_adresi != ''");
        $query->execute(['Evet']);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /** 
     * Belirli bir talep türü için uygulama içi bildirimleri açık olan kullanıcıları getirir
     * @param string $talepTuru 'avans', 'izin', 'genel', 'ariza'
     * @return array Kullanıcı listesi
     */
    public function getInAppBildirimKullanicilari(string $talepTuru): array
    {
        $column_map = [
            'avans' => 'mail_avans_talep',
            'izin' => 'mail_izin_talep',
            'genel' => 'mail_genel_talep',
            'ariza' => 'mail_ariza_talep'
        ];

        if (!isset($column_map[$talepTuru])) {
            return [];
        }

        $column = $column_map[$talepTuru];
        // Email adresi zorunluluğu yok
        $query = $this->db->prepare("SELECT * FROM $this->table WHERE $column = ?");
        $query->execute(['Evet']);
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Belirli bir kullanıcının belirli bir talep türü için mail alıp almadığını kontrol eder
     * @param int $userId Kullanıcı ID'si
     * @param string $talepTuru 'avans', 'izin', 'genel', 'ariza'
     * @return bool True ise mail alır, false ise almaz
     */
    public function checkMailBildirimi(int $userId, string $talepTuru): bool
    {
        $column_map = [
            'avans' => 'mail_avans_talep',
            'izin' => 'mail_izin_talep',
            'genel' => 'mail_genel_talep',
            'ariza' => 'mail_ariza_talep'
        ];

        if (!isset($column_map[$talepTuru])) {
            return false;
        }

        $column = $column_map[$talepTuru];
        $query = $this->db->prepare("SELECT $column FROM $this->table WHERE id = ?");
        $query->execute([$userId]);
        $result = $query->fetch(PDO::FETCH_OBJ);

        return $result && $result->$column === 'Evet';
    }

}
