<?php
// App/Controllers/RegisterActivateController.php
namespace App\Controllers;

use Model\UserModel;
use Database\Db;
use PDOException;
use App\Services\Gate;
use Model\DefinesModel;
use App\Helper\Security;
use App\Services\MailGonderService;
use App\Services\FlashMessageService;
use Model\KasaModel;
use Model\SitelerModel;
use App\Modules\Onboarding\Models\UserOnboardingProgressModel;

class RegisterActivateController
{
    public static function handleActivation(array $request)
    {
        $User = new UserModel();
        $token_renegate = false;
        if (isset($request["action"]) && $request["action"] == 'token_renegate') {
            $email = $request["email"];
            $user = $User->checkToken($email);
            if (empty($user)) {
                FlashMessageService::add('error', 'Hata!', 'Kullanıcı Bulunamadı');
            } else {
                $token = Security::encrypt(time() + 3600);
                $data = [
                    'id' => $user->id,
                    'activate_token' => $token,
                    'status' => 0
                ];
                $User->setActivateToken($data);

                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $baseUrl = $protocol . '://' . $host;

                $activate_link = "$baseUrl/register-activate.php?email=" . ($email) . "&token=" . $token;

                // Burada mail gönderim servisi çağrılabilir
                if (MailGonderService::gonder([$email], $user->full_name, $activate_link)) {
                    FlashMessageService::add('success', 'Başarılı!', 'Yeni aktivasyon e-postası gönderildi.');
                } else {
                    FlashMessageService::add('error', 'Hata!', 'Aktivasyon e-postası gönderilemedi. Lütfen daha sonra tekrar deneyiniz.');
                }
                $token_renegate = true;
            }
        } else if (isset($request['token']) && isset($request['email'])) {
            $token = $request['token'];
            $email = $request['email'];
            $user = $User->checkToken($email);
            $token_dec = Security::decrypt($token);
            if (empty($user)) {
                FlashMessageService::add('error', 'Hata!', 'Kullanıcı Bulunamadı');
            } elseif ($token_dec < time() || $user->activate_token != urlencode($token)) {
                FlashMessageService::add('error', 'Hata!', 'Geçersiz Token!');
                $token_renegate = true;
            } elseif (empty($token_dec)) {
                FlashMessageService::add('error', 'Hata!', 'Token bilgisi boş');
            } elseif (empty($email)) {
                FlashMessageService::add('error', 'Hata!', 'Email bilgisi boş');
            } elseif ($user->status == 1) {
                FlashMessageService::add('info', 'Bilgi', 'Kullanıcı zaten aktif');
            
            
            } else {
                
                /**transaction başlat */
                try {
                    
               
                $db = Db::getInstance();
                $db->beginTransaction();
                $OnBoardingProgressModel = new UserOnboardingProgressModel();
                
                $User->ActivateUser($email);


                /**Varsayilan site oluştur */
                $SiteModel = new SitelerModel();
                $lastSiteId = $SiteModel->saveWithAttr([
                    "id" => 0,
                    "user_id" => $user->id,
                    "site_adi" => "ÖRNEK SİTE",
                    "aktif_mi" => 1,
                ]);

                /**Site Ekleme Görevini tamamla(create_site) */
                $OnBoardingProgressModel->saveWithAttr([
                    "id"            => 0,
                    "user_id"       => $user->id,
                    "site_id"       => Security::decrypt($lastSiteId),
                    "task_key"      => "create_site",
                    "is_completed"  => 1,
                    "completed_at"  => date('Y-m-d H:i:s'),
                    "source"        => "system"
                ]);



                /**varsayılan olaak kasa oluştur */
                $KasaModel = new kasamodel();
                $data = [
                    "id" => 0,
                    "site_id" => Security::decrypt($lastSiteId),
                    "kasa_adi" => "Ai̇dat Kasasi",
                    "aktif_mi" => 1,
                    "kasa_tipi" => "Banka",
                    "varsayilan_mi" => 1,
                ];

                $KasaModel->saveWithAttr($data);

                /**Kasa ekleme görevini tamamla(create_default_cash_account) */
                $OnBoardingProgressModel->saveWithAttr([
                    "id"            => 0,
                    "user_id"       => $user->id,
                    "site_id"       => Security::decrypt($lastSiteId),
                    "task_key"      => "create_default_cash_account",
                    "is_completed"  => 1,
                    "completed_at"  => date('Y-m-d H:i:s'),
                    "source"        => "system"
                ]);

                /**Varsayılan kasa ayarlama görevini tamamla(set_default_cash_account) */
                $OnBoardingProgressModel->saveWithAttr([
                    "id"            => 0,
                    "user_id"       => $user->id,
                    "site_id"       => Security::decrypt($lastSiteId),
                    "task_key"      => "set_default_cash_account",
                    "is_completed"  => 1,
                    "completed_at"  => date('Y-m-d H:i:s'),
                    "source"        => "system"
                ]);

                
                /** Daire tipleri oluştur 3+1 , 2+1,1+1, İşyeri */
                $DefinesModel = new DefinesModel();

                $type = ["3+1","2+1","1+1","İsyeri"];
                foreach ($type as $item) {
                    $DefinesModel->saveWithAttr([
                        "id" => 0,
                        "site_id" => Security::decrypt($lastSiteId),
                        "type" => 3,
                        "define_name" => $item,
                        "mulk_tipi" => $item == "İsyeri" ? "İşyeri" : "Konut",
                        "description" => "İlk Kayıtta eklendi",
                    ]);
                }
               
                /**Daire tipleri ekleme görevini tamamla(add_flat_types) */
                $OnBoardingProgressModel->saveWithAttr([
                    "id"            => 0,
                    "user_id"       => $user->id,
                    "site_id"       => Security::decrypt($lastSiteId),
                    "task_key"      => "add_flat_types",
                    "is_completed"  => 1,
                    "completed_at"  => date('Y-m-d H:i:s'),
                    "source"        => "system"
                ]);


                /**Site sakini ise mail metnine sakin ekle */
                $sakin = $user->kisi_id > 0 ? " (Site Sakini)" : "";
                MailGonderService::gonder(["beyzade83@gmail.com", "bilgekazaz@gmail.com", "ertanguness@gmail.com"], $user->full_name, $user->full_name .  $sakin . " isimli kullanıcı hesabını aktifleştirdi.");
                
                FlashMessageService::add('success', 'Başarılı!', 'Hesabınız başarı ile aktifleştirildi!', "onay2.png");
                $db->commit();
                
             } catch (PDOException $ex) {
                $db->rollBack();
                FlashMessageService::add('error', 'Hata!', 'Kullanıcı aktifleştirilirken bir hata oluştu: ' . $ex->getMessage());
            
            }

            }
        }
        return $token_renegate;
    }
}
