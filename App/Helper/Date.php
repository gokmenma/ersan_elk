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




    /**
     * Gelen tarih değerini Türkiye formatı öncelikli olarak DateTime nesnesine çevirir.
     * Excel seri tarihleri, Unix timestamp, d.m.Y, d/m/Y, d-m-Y ve Y-m-d formatlarını destekler.
     */
    public static function toDateTime($date): ?\DateTime
    {
        if ($date === null) {
            return null;
        }

        if ($date instanceof \DateTimeInterface) {
            return $date instanceof \DateTime ? $date : new \DateTime($date->format('Y-m-d H:i:s'));
        }

        // Sayısal değer (Excel Seri Tarihi veya Unix Timestamp)
        if (is_numeric($date)) {
            $num = (float) $date;
            if ($num <= 0) {
                return null;
            }
            if ($num > 100000000) {
                $dt = new \DateTime();
                $dt->setTimestamp((int) $num);
                return $dt;
            }
            try {
                return PhpSpreadsheetDate::excelToDateTimeObject($num);
            } catch (\Throwable $e) {
                return null;
            }
        }

        if (!is_string($date)) {
            return null;
        }

        $raw = trim($date);
        if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
            return null;
        }

        // Tarih-saat arası tireyi boşluğa çevir (örn: 19/12/2025-13:40:20)
        $norm = preg_replace('/(\b\d{1,2}[\.\/\-]\d{1,2}[\.\/\-]\d{2,4})-(\d{1,2}:\d{1,2}(?::\d{1,2})?\b)/', '$1 $2', $raw);

        // Türkiye formatları (Gün önce, Ay sonra) ve ISO standartları
        $formats = [
            'd.m.Y H:i:s', 'd.m.Y H:i', 'd.m.Y',
            'j.n.Y H:i:s', 'j.n.Y H:i', 'j.n.Y',
            'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
            'j/n/Y H:i:s', 'j/n/Y H:i', 'j/n/Y',
            'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y',
            'j-n-Y H:i:s', 'j-n-Y H:i', 'j-n-Y',
            'd.m.y H:i:s', 'd.m.y H:i', 'd.m.y',
            'd/m/y H:i:s', 'd/m/y H:i', 'd/m/y',
            'd-m-y H:i:s', 'd-m-y H:i', 'd-m-y',
            'd-M-y', 'd M y', 'd-M-Y', 'd M Y', 'j-M-y', 'j-M-Y',
            'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d',
            'Y.m.d H:i:s', 'Y.m.d H:i', 'Y.m.d',
            'Y/m/d H:i:s', 'Y/m/d H:i', 'Y/m/d'
        ];

        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $norm);
            if ($dt instanceof \DateTimeInterface) {
                $errors = \DateTime::getLastErrors();
                if (empty($errors['error_count'])) {
                    if ((int) $dt->format('Y') < 100) {
                        $dt->modify('+2000 years');
                    }
                    return $dt;
                }
            }
        }

        // Regex ile Türkiye formatı (GÜN.AY.YIL veya GÜN/AY/YIL veya GÜN-AY-YIL)
        if (preg_match('/^(\d{1,2})[\.\/\-](\d{1,2})[\.\/\-](\d{2,4})(?:\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?$/', $norm, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if ($year < 100) {
                $year += 2000;
            }
            $hour = isset($m[4]) ? (int) $m[4] : 0;
            $min = isset($m[5]) ? (int) $m[5] : 0;
            $sec = isset($m[6]) ? (int) $m[6] : 0;
            if (checkdate($month, $day, $year)) {
                $dt = new \DateTime();
                $dt->setDate($year, $month, $day);
                $dt->setTime($hour, $min, $sec);
                return $dt;
            }
        }

        try {
            return new \DateTime($norm);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function dmY($date = null, $format = 'd.m.Y')
    {
        if ($date === null || $date === '' || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '';
        }

        $dt = self::toDateTime($date);
        return $dt ? $dt->format($format) : '';
    }

    public static function dttoeng($date)
    {
        if (empty($date)) {
            return null;
        }

        $dt = self::toDateTime($date);
        return $dt ? $dt->format('Y-m-d') : null;
    }

    public static function engtodt($date)
    {
        if (empty($date)) {
            return null;
        }

        $dt = self::toDateTime($date);
        return $dt ? $dt->format('d.m.Y') : null;
    }

    /** Tarih saat formatında döndürür */
    public static function dmYHis($date = null, $format = 'd.m.Y H:i:s')
    {
        if ($date === null || $date === '' || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '';
        }

        $dt = self::toDateTime($date);
        return $dt ? $dt->format($format) : '';
    }

    public static function Ymd($date, $format = 'Y-m-d')
    {
        if ($date === null || (is_string($date) && trim($date) === '') || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return ($format === 'Ymd') ? 0 : null;
        }

        $dt = self::toDateTime($date);
        if (!$dt) {
            return ($format === 'Ymd') ? 0 : null;
        }
        return $dt->format($format);
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
        return self::MONTHS[(int) $month];
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
     * Excel'den veya herhangi bir kaynaktan gelen tarih değerini istenen formatta döndürür.
     * Türkiye tarih formatını (gün-ay-yıl) esas alır.
     * @param mixed $dateValue Excel hücresinden gelen değer (sayı, string veya DateTime)
     * @param string $format Çıktı formatı (Y-m-d H:i:s, timestamp, vs)
     * @return string|int|null Başarılı ise istenen format, değilse null
     */
    public static function convertExcelDate($dateValue, $format = 'Y-m-d'): string|int|null
    {
        if (empty($dateValue)) {
            return null;
        }

        $dt = self::toDateTime($dateValue);
        if (!$dt) {
            return null;
        }

        return ($format === 'timestamp') ? (int) $dt->format('U') : $dt->format($format);
    }
}