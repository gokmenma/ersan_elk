<?php
namespace App\Service {
    class Gate { public static function authorizeOrDie(...$args): void {} public static function allows(string $permission): bool { return true; } }
}
namespace App\Model {
    class EInvoiceModel {
        public function invoiceCustomers(int $firm): array { return [['id'=>1,'CariAdi'=>'Test </script><script>window.fixtureXss=true</script>','Telefon'=>'','Email'=>'','firma'=>'Test','Adres'=>'Test','notlar'=>'']]; }
    }
    class EInvoiceSettingsModel { public function getSettings(int $firm): array { return ['environment'=>'TEST','api_username'=>'offline','efatura_seri'=>'ERS','earsiv_seri'=>'ERA']; } }
}
namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    chdir(dirname(__DIR__, 3));
    $_ENV['ENCRYPTION_KEY'] = str_repeat('ab', 32);
    $_SESSION = ['firm_id'=>2,'csrf_token'=>'offline-browser-token'];
    $mode = $argv[1] ?? 'olustur';
    if (!in_array($mode, ['olustur','giden-list','gelen-list','taslak-list','ayarlar'], true)) exit(1);
    echo '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/assets/libs/select2/css/select2.min.css"><link rel="stylesheet" href="/assets/libs/sweetalert2/sweetalert2.min.css"><link rel="stylesheet" href="/assets/libs/summernote/summernote-lite.min.css"><script src="/assets/libs/jquery/jquery.min.js"></script><script src="/assets/libs/select2/js/select2.min.js"></script><script src="/assets/libs/sweetalert2/sweetalert2.all.min.js"></script><script src="/assets/libs/datatables.net/js/jquery.dataTables.min.js"></script><script src="/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script><script src="/assets/libs/summernote/summernote-lite.min.js"></script><script src="/assets/libs/summernote/lang/summernote-tr-TR.min.js"></script></head><body>';
    require 'views/efatura/' . $mode . '.php';
    echo '</body></html>';
}
