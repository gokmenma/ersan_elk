<?php 
namespace App\Helper;
class Alert
{
    public static function danger(string $message): void
    {
        echo "
            <div class='alert alert-danger alert-dismissible fade show' role='alert'>
                <strong>Uyarı!</strong> {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>
        ";
    }
}
