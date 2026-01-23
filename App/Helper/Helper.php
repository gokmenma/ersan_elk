<?php

namespace App\Helper;

class Helper
{

    const MONEY_UNIT = [
        '1' => '₺',
        '2' => '$',
        '3' => '€',
    ];

    const EVRAK_TANIMI = [
        '1' => "Giden Evrak",
        '2' => "Gelen Evrak",
    ];


    //Evrakın Giittiği yer Bilgisi. Örn: Kurum Dışı Dağıtım Planı, Kurum İçi, Gerçek Kişi
    const GIDEN_EVRAK_YERI = [
        '' => "Seçiniz",
        '1' => "Kurum Dışı Dağıtım Planı",
        '2' => "Kurum İçi ve Dışı",
        '3' => "Gerçek Kişi",
        '4' => "Kamu Kurumu",
        '5' => "Firma",
        '6' => "Diğer",
    ];

    const GIDEN_EVRAK_TURU = [
        '' => "Seçiniz",
        '1' => "Zimmetle Teslim",
        '2' => "Koli",
        '3' => "İadeli Taahhütlü Mektup",
        '4' => "E-Posta",
        '5' => "Adi Mektup",
        '6' => "APS",
        '7' => "Tebligat",
        '8' => "E-Posta/İadeli Taahhütlü Mektup",
        '9' => "E-Posta/Adi Mektup",
        '10' => "Resmi Taahhütlü Mektup",
        '11' => "Faks",
        '12' => "Kayıtlı E-Posta (Kep)",
        '13' => "enVision",
    ];

    const GIDEN_EVRAK_AMACI = [
        '1' => "BİLGİSİNE",
        '2' => "GEREĞİNE",
    ];


    const IMZA_SABLON = [
        '' => "Seçiniz",
        '1' => "Kurum Dışı Giden İki İmzalı (Tek Satırlı Makam Hitabı)",
        '2' => "Kurum Dışı Giden İki İmzalı Yazı (Birden Fazla Satırlı Makam Hitabı)",
        '3' => "Kurum Dışı Giden Tek İmzalı (Tek Satırlı Makam Hitabı)",
        '4' => "Kurum Dışı Giden Tek İmzalı Yazı (Birden Fazla Satırlı Makam Hitabı)",
    ];

    // Genel Başkan a.
    const KIMIN_ADINA = [
        '' => "Seçiniz",
        'Genel Başkan a.' => "Genel Başkan a.",
        'Genel Başkan Yardımcısı a.' => "Genel Başkan Yardımcısı a.",

    ];


    //Gelen evrak türü
    const GELEN_EVRAK_TURU = [
        "" => "Seçiniz",
        "1" => "Kurum Dışı Gelen Evrak",
        "2" => "Kurum İçi Gelen Evrak",
    ];



    // GİDEN EVRAK YERİNİ DÖNDÜR
    public static function getGidenEvrakTuru($type = 1)
    {
        return self::GIDEN_EVRAK_TURU[$type];
    }

    // GİDEN EVRAK AMACINI DÖNDÜR
    public static function getGidenEvrakAmaci($type = 1)
    {
        return self::GIDEN_EVRAK_AMACI[$type];
    }


    public static function formattedMoneyToNumber($value)
    {
        if (empty($value))
            return 0;
        if (is_numeric($value) && !is_string($value))
            return $value;

        // Para birimi ve boşlukları temizle
        $value = str_replace(['₺', ' ', '$', '€'], '', $value);

        // Sadece rakam, nokta, virgül ve eksi kalsın
        $value = preg_replace('/[^\d.,-]/', '', $value);

        $dotPos = strrpos($value, '.');
        $commaPos = strrpos($value, ',');

        if ($dotPos !== false && $commaPos !== false) {
            if ($commaPos > $dotPos) {
                // TR formatı: 1.234,56
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                // US formatı: 1,234.56
                $value = str_replace(',', '', $value);
            }
        } elseif ($commaPos !== false) {
            // Sadece virgül var
            if (preg_match('/,\d{3}$/', $value)) {
                $value = str_replace(',', '', $value);
            } else {
                $value = str_replace(',', '.', $value);
            }
        } elseif ($dotPos !== false) {
            // Sadece nokta var
            if (preg_match('/\.\d{3}$/', $value)) {
                $value = str_replace('.', '', $value);
            }
        }

        return $value;
    }

    public static function formattedMoney($value, $currency = 1)
    {
        $formattedNumber = number_format($value, 2, ',', '.');
        return self::MONEY_UNIT[$currency] . $formattedNumber;
    }

    //Evrak tanımını getir
    public static function getEvrakTanimi($type = 1)
    {
        return self::EVRAK_TANIMI[$type];
    }

    public static function base_url($path = '')
    {

        $page = $_SERVER['SCRIPT_FILENAME'];
        $main_file = ['index.php', 'login.php', 'register.php', 'forgot-password.php', 'reset-password.php', 'logout.php'];

        if (!in_array(basename($page), $main_file)) {
            $prefix = "../../";
        } else {
            $prefix = "";
        }

        return $prefix . $path;

    }


    //Gelir ve gider türü için badge oluştur
    public static function getBadge($type)
    {
        $badge = $type == 1 ? '<span class="badge bg-success">Gelir</span>' : '<span class="badge bg-danger">Gider</span>';
        return $badge;
    }

    //Gelen değeri kısalt
    public static function short($text, $limit = 50)
    {
        return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
    }


    public static function trUpper($text)
    {
        $search = ['i', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç'];
        $replace = ['İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'];
        $text = str_replace($search, $replace, $text);
        return mb_strtoupper($text, "UTF-8");
    }

    public static function gidenEki($kurumAdi)
    {
        $kurumAdi = (string) ($kurumAdi ?? '');
        $kurumAdi = trim($kurumAdi);
        if ($kurumAdi === '') {
            return '';
        }

        // Sonda noktalama varsa eki noktalamanın önüne eklemek için ayır
        // Örn: "... Bakanlığı." => "... Bakanlığına."
        $punct = '';
        if (preg_match('/([\.!\?,;:\)\]\}]+)$/u', $kurumAdi, $m)) {
            $punct = $m[1];
            $kurumAdi = mb_substr($kurumAdi, 0, mb_strlen($kurumAdi) - mb_strlen($punct));
            $kurumAdi = rtrim($kurumAdi);
        }

        // Zaten yönelme ekiyle bitiyorsa tekrar ekleme (büyük/küçük harf duyarsız)
        // Örn: "... REKTÖRLÜĞÜNE" veya "... Bakanlığına"
        $lower = mb_strtolower($kurumAdi, 'UTF-8');
        if (preg_match('/(\s|^)[^\s]+(na|ne)$/u', $lower)) {
            return $kurumAdi . $punct;
        }

        // Türkçe ünlüler (hem küçük hem büyük)
        $kalinUnluler = ['a', 'ı', 'o', 'u', 'A', 'I', 'O', 'U', 'İ', 'Â', 'Û'];
        $inceUnluler = ['e', 'i', 'ö', 'ü', 'E', 'İ', 'Ö', 'Ü'];
        $unluler = array_unique(array_merge($kalinUnluler, $inceUnluler));

        // Son ünlüyü bul
        $sonUnlu = null;
        for ($i = mb_strlen($kurumAdi) - 1; $i >= 0; $i--) {
            $harf = mb_substr($kurumAdi, $i, 1);
            if (in_array($harf, $unluler, true)) {
                $sonUnlu = $harf;
                break;
            }
        }

        if ($sonUnlu === null) {
            return $kurumAdi . $punct;
        }

        $yonelmeEki = in_array($sonUnlu, $kalinUnluler, true) ? 'a' : 'e';

        // Kurum adları genellikle "...lığı" / "...lüğü" gibi ünlü ile biter; pratik kullanımda "-na/-ne" bekleniyor.
        // Ünlüyle bitiyorsa kaynaştırma "n" (örn: "Bakanlığı" -> "Bakanlığına"), ünsüzle bitiyorsa yok.
        $sonHarf = mb_substr($kurumAdi, -1);
        $kaynastirma = in_array($sonHarf, $unluler, true) ? 'n' : '';

        return $kurumAdi . $kaynastirma . $yonelmeEki . $punct;
    }


    public static function getDonemAdi($donem)
    {
        if (empty($donem)) {
            return '-';
        }

        $aylar = [
            '01' => 'Ocak',
            '02' => 'Şubat',
            '03' => 'Mart',
            '04' => 'Nisan',
            '05' => 'Mayıs',
            '06' => 'Haziran',
            '07' => 'Temmuz',
            '08' => 'Ağustos',
            '09' => 'Eylül',
            '10' => 'Ekim',
            '11' => 'Kasım',
            '12' => 'Aralık'
        ];

        $parts = explode('-', $donem);
        if (count($parts) < 2)
            return $donem;

        $yil = $parts[0];
        $ay = $parts[1];

        return (isset($aylar[$ay]) ? $aylar[$ay] : $ay) . ' ' . $yil;
    }


    /* dd fonskiyonu
     * 
     * @param mixed $data
     * @return void
     */
    public static function dd($data)
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        die();
    }

    public static function timeAgo($datetime, $full = false)
    {
        $now = new \DateTime;
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $string = array(
            'y' => 'yıl',
            'm' => 'ay',
            'd' => 'gün',
            'h' => 'saat',
            'i' => 'dakika',
            's' => 'saniye',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v;
            } else {
                unset($string[$k]);
            }
        }

        if (!$full)
            $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' önce' : 'şimdi';
    }
}