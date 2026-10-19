var textMessages = [];

textMessages[PWSigner.prototype.codes.RESPONSE_SUCCESSFUL] = 'Islem basari ile gerçeklestirilmistir.';
textMessages[PWSigner.prototype.codes.ERROR_NO_CLIENT] = 'ArkSigner Uygulamasi Bulunamamistir. Bilgisayariniza indirmek için <a href="http://arksigner.com/indir" target="_blank">tiklayiniz</a>';
textMessages[PWSigner.prototype.codes.ERROR_CANNOT_INITIALIZE_SIGNER] = 'ArkSigner Uygulamasi Baslatilamamistir.';
textMessages[PWSigner.prototype.codes.ERROR_CANNOT_LOAD_LICENSE] = 'ArkSigner Lisansi Geçersizdir. Geçerli Lisansınız için lütfen yetkili firma ile irtibata geçin.';
textMessages[PWSigner.prototype.codes.ERROR_CANNOT_START_PWSIGNER_CLIENT] = 'ArkSigner Uygulamasi Baslatilamamistir.';
textMessages[PWSigner.prototype.codes.ERROR_INVALID_CERTIFICATE] = "Kullanmak istediginiz sertifika geçersizdir.";
textMessages[PWSigner.prototype.codes.ERROR_INVALID_CERTIFICATE_SERIAL_NUMBER] = "Kullanmak istediginiz sertifikanin seri numarasi geçersizdir.";
textMessages[PWSigner.prototype.codes.ERROR_NO_SIGNING_CERTIFICATE_FOUND] = 'Herhangi bir imzalama sertifikasi bulunamamistir.';
textMessages[PWSigner.prototype.codes.ERROR_NO_TERMINAL_FOUND] = 'Takili herhangi bir akilli kart bulunamamistir.Lütfen akıllı kartınızın bilgisayarınıza takılı olduğundan emin olunuz.';
textMessages[PWSigner.prototype.codes.ERROR_NO_TERMINAL_FOR_PROVIDED_SLOT_ID_AND_CARD_TYPE] = 'Geçersiz slot numarasi ve kart tipi.';
textMessages[PWSigner.prototype.codes.ERROR_SIGNER_IS_NOT_INITIALIZED] = 'ArkSigner uygulamasi baslatilmamistir.';
textMessages[PWSigner.prototype.codes.ERROR_SMART_CARD_EXCEPTION] = 'Akilli kart uygulamasi hatasi.';
textMessages[PWSigner.prototype.codes.ERROR_UNDEFINED_SIGNER_EXCEPTION] = 'Tanimsiz hata. Site yöneticisi ile iletisime geçiniz.';

textMessages[PWSigner.prototype.codes.ERROR_CANNOT_LOAD_POLICY_FILE] = 'e-imza politikasi dosyasi yüklenememektedir.';

textMessages[PWSigner.prototype.codes.ERROR_INVALID_CMS_CONTENT_TO_SIGN] = 'Imzalanmak istenen veri geçersizdir.';
textMessages[PWSigner.prototype.codes.ERROR_CANNOT_SET_SIGNING_TIME] = 'Imzalama zamani set edilememektedir.';
textMessages[PWSigner.prototype.codes.ERROR_CANNOT_LOGIN_TO_THE_SIGNER] = 'Akilli karta login olunamamaktadir.';
textMessages[PWSigner.prototype.codes.ERROR_CANNOT_SIGN_CMS_DOCUMENT] = 'Veri imzalanamamaktadir.';
textMessages[PWSigner.prototype.codes.ERROR_CANNOT_LOGOUT_SIGNER] = 'Akilli karttan logout olunamamaktadir.';
textMessages[PWSigner.prototype.codes.ERROR_CANNOT_VALIDATE_CERTIFICATE] = 'Imzalama sertifikasi dogrulanamamaktadir.';

textMessages[PWSigner.prototype.codes.ERROR_CERTIFICATE_REVOCATION_CHECK_FAILURE] = 'Imzalama sertifikasi geçerliligi kontrol edilememektedir.';
textMessages[PWSigner.prototype.codes.ERROR_CERTIFICATE_SELF_CHECK_FAILURE] = 'Imzalama sertifikasi geçerliligi kontrol edilememektedir.';
textMessages[PWSigner.prototype.codes.ERROR_CERTIFICATE_NO_TRUSTED_CERT_FOUND] = 'Akilli Kart Üzerinde Geçerli Imzalama Sertifikasi Bulunmamaktadir.';
textMessages[PWSigner.prototype.codes.ERROR_CERTIFICATE_PATH_VALIDATION_FAILURE] = 'Imzalama sertifikasi zinciri dogrulanamamaktadir.';
textMessages[PWSigner.prototype.codes.ERROR_CERTIFICATE_NOT_CHECKED] = 'Imzalama sertifikasi geçerliligi kontrol edilememektedir.';
textMessages[PWSigner.prototype.codes.ERROR_INVALID_URL] = 'Lisans ile farkli bir adres üzerinden uygulamayi çalistirmaya çalisiyorsunuz.';
textMessages[PWSigner.prototype.codes.ERROR_INVALID_PIN] = 'Hatali sifre girdiniz.';
textMessages[PWSigner.prototype.codes.ERROR_NO_DRIVER_FOUND] = 'Yüklü driver bulunamamistir.';
textMessages[PWSigner.prototype.codes.ERROR_NO_EXTENSION] = 'You are currently using Chrome Browser. The PW WebSigner Extension is not installed yet. If you would like to sign documents, please install the extension first. If you confirm, a new tab with the installation page will be opened.';

textMessages[PWSigner.prototype.codes.ERROR_NOT_BROWSER_CHROME] = 'Lütfen Chrome tarayici kullaniniz.';
textMessages[PWSigner.prototype.codes.ERROR_DISCONNECT] = '';
textMessages[PWSigner.prototype.codes.ERROR_UNDEFINED] = 'Tanimsiz Hata. Lütfen web sitesi yöneticileri ile iletisime geçiniz.';
textMessages[PWSigner.prototype.codes.ERROR_PIN_LOCKED] = 'PIN bloke edilmis.Lütfen akıllı kartınızı almış olduğunuz yer ile irtibata geçiniz ve kullanmak için blokeyi kaldiriniz.';
textMessages[PWSigner.prototype.codes.ERROR_TASK_TIMEOUT] = 'Islem zaman asimina ugramistir.';
textMessages[PWSigner.prototype.codes.ERROR_NO_PRIVATE_KEY_FOUND_CORRESPONDING_TO_PUBLIC_KEY] = 'Seçili Sertifika ile iliskilendirilmis imzalama anahtari bulunamamistir. Sistem Yetkilisi ile iletisime geçiniz.';