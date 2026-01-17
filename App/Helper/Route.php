<?php
namespace App\Helper;
use App\Helper\Security;

class Route
{
    private static $baseUrl = "/cansen/admin/views/";
    private static $base_path = "/admin/index?p=";
    private static $menuUrl = "index?p=";
    // private static $baseUrl = "/views/";
    // private static $base_path = "/index?p=";
 

    public static function to($path = '')
    {
        echo self::$baseUrl . $path;
    }

    // public static function redirect($path = '')
    // {
    //     header("Location: " . $path);

    // }
    public static function redirect($path = '')
    {
        header("Location: " . $path);

    }

    //gelen path'e gönder
    public static function get($path = '')
    {
        echo self::$base_path . $path;
    }

    /**
     * Menu için link oluştur 
    */
    public static function Link($path = '')
    {
        return Security::escape(self::$menuUrl . $path);
    }

}

?>