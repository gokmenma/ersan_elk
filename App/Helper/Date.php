<?php

namespace App\Helper;
use DateTime;
use PhpOffice\PhpSpreadsheet\Shared\Date as PhpSpreadsheetDate;

class Date
{

    const MONTHS = [
        '1' => 'Ocak',
        '2' => 'Şubat',
        '3' => 'Mart',
        '4' => 'Nisan',
        '5' => 'Mayıs',
        '6' => 'Haziran',
        '7' => 'Temmuz',
        '8' => 'Ağustos',
        '9' => 'Eylül',
        '10' => 'Ekim',
        '11' => 'Kasım',
        '12' => 'Aralık'
    ];




    public static function dmY($date = null, $format = 'd.m.Y')
    {
        //date boş ise geri döner
        if ($date == null) {
            return;
        }

        return date($format, strtotime($date));
    }

    /** Tarih saat formatında döndürür */
    public static function dmYHis($date = null, $format = 'd.m.Y H:i:s')
    {
        //date boş ise geri döner
        if ($date == null) {
            return;
        }

        return date($format, strtotime($date));
    }

    public static function Ymd($date, $format = 'Ymd')
    {
        if ($date == null) {
            return 0;
        }
        return date($format, strtotime($date));
    }

    /**Bugün */
    public static function today($format = 'd.m.Y')
    {
        return date($format);
    }

    public static function firstDay($month, $year)
    {
        return sprintf('%d%02d%02d', $year, $month, 1);
    }

    /**İçinde olduğumuz ayın ilk günü */
    public static function firstDayOfThisMonth($format = 'd.m.Y')
    {
        return date($format, strtotime('first day of this month'));
    }

    public static function lastDay($month, $year)
    {
        return sprintf(
            '%d%02d%02d',
            $year,
            $month,
            self::daysInMonth($month, $year),
        );
    }

    // Yarının tarihini d.m.Y formatında döndürür
    public static function getTomorrowDate($format = 'Ymd')
    {
        return date($format, strtotime('+1 day'));
    }
    public static function getDay($date = null, $leadingZero = true)
    {
        $format = $leadingZero ? 'd' : 'j';
        return $date ? date($format, strtotime($date)) : date($format);
    }

    public static function getYear($date = null)
    {
        return $date ? date('Y', strtotime($date)) : date('Y');
    }


    public static function daysInMonth($month, $year)
    {
        return cal_days_in_month(CAL_GREGORIAN, $month, $year);
    }

    public static function generateDates($year, $month, $days)
    {
        $dateList = [];
        for ($day = 1; $day <= $days; $day++) {
            // Tarih formatını ayarlama (d.m.Y)
            $formattedDate = sprintf('%2d%02d%02d', $year, $month, $day);
            $dateList[] = $formattedDate;
        }
        return $dateList;
    }


    public static function isWeekend($date)
    {
        $dateTime = new \DateTime($date);
        $dayOfWeek = $dateTime->format('N');
        return ($dayOfWeek == 7);
    }

    public static function isDate($date)
    {
        return strtotime($date);
    }

    public static function isBetween($date, $startDate, $endDate)
    {
        $date = strtotime($date);
        $startDate = strtotime($startDate);
        $endDate = strtotime($endDate);
        return ($date >= $startDate && $date <= $endDate);
    }

    public static function isBefore($date, $compareDate)
    {
        $date = self::Ymd($date);
        $compareDate = self::Ymd($compareDate);
        return ($date < $compareDate);
    }

    public static function gunAdi($gun)
    {
        $gun = date('D', strtotime($gun));
        $gunler = array(
            'Mon' => 'Pzt',
            'Tue' => 'Sal',
            'Wed' => 'Çar',
            'Thu' => 'Per',
            'Fri' => 'Cum',
            'Sat' => 'Cmt',
            'Sun' => 'Paz'
        );
        return $gunler[$gun];
    }


    public static function monthName($month)
    {
        //09 şeklinde gelen ayları 9 şekline çevir

        // $month = ltrim($month, '0');
        return self::MONTHS[$month];
    }

    public static function getMonthsSelect(
        $name = 'months',
        $month = null
    ) {
        if ($month == null) {
            $month = date('m');
        }
        $select = '<select name="' . $name . '" class="form-select select2" id="' . $name . '" style="width:100%">';
        $select .= '<option value="">Ay Seçiniz</option>';
        foreach (self::MONTHS as $key => $value) {
            $selected = $month == $key ? ' selected' : '';
            $select .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
        }
        $select .= '</select>';
        return $select;
    }

    public static function getYearsSelect(
        $name = 'years',
        $year = null
    ) {
        if ($year == null) {
            $year = date('Y');
        }
        $select = '<select name="' . $name . '" class="form-select select2" id="' . $name . '" style="width:100%">';
        $select .= '<option value="">Yıl Seçiniz</option>';
        for ($i = 2021; $i <= 2030; $i++) {
            $selected = $year == $i ? ' selected' : '';
            $select .= '<option value="' . $i . '"' . $selected . '>' . $i . '</option>';
        }
        $select .= '</select>';
        return $select;
    }

    /**
     * İki tarih arasındaki gün farkını hesaplar.
     *
     * @param string $date1 İlk tarih (Y-m-d H:i:s formatında)
     * @param string $date2 İkinci tarih (Y-m-d H:i:s formatında - boş ise bugünün tarihi alınır)
     * @return int İki tarih arasındaki gün farkı
     */
    public static function getDateDiff($date1, $date2 = '')
    {
        //date2 boş ise bugünün tarihi alınır
        if ($date2 == '') {
            $date2 = date('Y-m-d H:i:s');
        }
        $datetime1 = new \DateTime($date1);
        $datetime2 = new \DateTime($date2);
        $interval = $datetime1->diff($datetime2);
        return (int) $interval->format('%a');
    }









    //12.01.2025 tarihinde gelen tarihten sadece günü döndürür
    public static function getDate($date)
    {

        //tarih boş ise boş döndür
        if (empty($date))
            return "";

        //tarihi . dan ayırarak diziye atar
        $date = explode(".", $date);
        return $date[0];

    }

    //12.01.2025 tarihinde gelen tarihten sadece ayı Oca şeklinde döndürür
    public static function getMonth($date)
    {
        //tarih boş ise boş döndür
        if (empty($date))
            return "";

        //tarihi . dan ayırarak diziye atar
        $date = explode(".", $date);
        return self::MONTHS[$date[1]];

    }


    /**
     * Excel'den gelen sayısal bir tarih değerini istenen formatta döndürür.
     * @param mixed $dateValue Excel hücresinden gelen değer (sayı veya string)
     * @param string $format Çıktı formatı (Y-m-d H:i:s, timestamp, vs)
     * @return string|int|null Başarılı ise istenen format, değilse null
     */
    public static function convertExcelDate($dateValue, $format = 'Y-m-d'): string|int|null
    {
        // DEBUG LOG
        $logFile = dirname(__DIR__, 2) . '/debug_date.txt';
        $log = "Input: " . print_r($dateValue, true) . " Type: " . gettype($dateValue) . "\n";

        if (empty($dateValue)) {
            file_put_contents($logFile, $log . "Result: Empty\n----------------\n", FILE_APPEND);
            return null;
        }

        // 1. Sayısal ise (Excel seri numarası: 45948.70138888889 gibi)
        if (is_numeric($dateValue)) {
            try {
                // TIMESTAMP İSTİYORSANIZ:
                if ($format === 'timestamp') {
                    $res = PhpSpreadsheetDate::excelToTimestamp((float) $dateValue);
                    file_put_contents($logFile, $log . "Result (Numeric Timestamp): $res\n----------------\n", FILE_APPEND);
                    return $res;
                }

                // TARİH STRING İSTİYORSANIZ (saat+dakika dahil):
                $dateTimeObject = PhpSpreadsheetDate::excelToDateTimeObject((float) $dateValue);
                $res = $dateTimeObject->format($format);
                file_put_contents($logFile, $log . "Result (Numeric Object): $res\n----------------\n", FILE_APPEND);
                return $res;

            } catch (\Exception $e) {
                file_put_contents($logFile, $log . "Result (Numeric Error): " . $e->getMessage() . "\n----------------\n", FILE_APPEND);
                return null;
            }
        }
        // 2. Metin ise (örn: "18.02.2025 10:41:41")
        if (is_string($dateValue)) {

            $raw = trim($dateValue);
            if ($raw === '') {
                return null;
            }

            // 2.a) Bazı kaynaklar tarih-saat arası '-' gönderiyor: 19/12/2025-13:40:20
            // Bunu güvenle boşluğa çevir (tarih içindeki '-' karakterlerine dokunma)
            $norm = preg_replace('/(\b\d{2}\/\d{2}\/\d{4})-(\d{2}:\d{2}(?::\d{2})?\b)/', '$1 $2', $raw);
            $norm = preg_replace('/(\b\d{2}\.\d{2}\.\d{4})-(\d{2}:\d{2}(?::\d{2})?\b)/', '$1 $2', $norm);
            $norm = preg_replace('/(\b\d{2}-\d{2}-\d{4})-(\d{2}:\d{2}(?::\d{2})?\b)/', '$1 $2', $norm);

            // 2.b) Önce en net formatları dene
            $knownFormats = [
                'j.n.Y H:i:s',
                'j.n.Y H:i',
                'j.n.Y',
                'j/n/Y H:i:s',
                'j/n/Y H:i',
                'j/n/Y',
                'd.m.Y H:i:s',
                'd.m.Y H:i',
                'd/m/Y H:i:s',
                'd/m/Y H:i',
                'd-m-Y H:i:s',
                'd-m-Y H:i',
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                'Y/m/d H:i:s',
                'Y/m/d H:i',
                'd/m/Y',
                'd.m.Y',
                'd-m-Y',
                'Y-m-d',
                'Y/m/d',
            ];

            foreach ($knownFormats as $fmt) {
                $dt = \DateTime::createFromFormat($fmt, $norm);
                if ($dt instanceof \DateTimeInterface) {
                    $errors = \DateTime::getLastErrors();
                    if (empty($errors['warning_count']) && empty($errors['error_count'])) {
                        $res = ($format === 'timestamp') ? (int) $dt->format('U') : $dt->format($format);
                        file_put_contents($logFile, $log . "Result (String Match $fmt): $res\n----------------\n", FILE_APPEND);
                        return $res;
                    }
                }
            }

            // 2.c) Son çare: PHP'nin DateTime parser'ı
            try {
                $dt = new \DateTime($norm);
                $res = ($format === 'timestamp') ? (int) $dt->format('U') : $dt->format($format);
                file_put_contents($logFile, $log . "Result (String DateTime): $res\n----------------\n", FILE_APPEND);
                return $res;
            } catch (\Throwable $e) {
                file_put_contents($logFile, $log . "Result (String Error): " . $e->getMessage() . "\n----------------\n", FILE_APPEND);
                return null;
            }
        }

        return null;
    }

}