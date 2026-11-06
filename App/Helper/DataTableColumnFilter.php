<?php

namespace App\Helper;

class DataTableColumnFilter
{
    public static function parse(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '' || strpos($raw, ':') === false) {
            return ['', [], ''];
        }
        [$mode, $rest] = explode(':', $raw, 2);
        $parts = $rest === '' ? [] : explode('|', $rest);
        return [$mode, array_map('trim', $parts), (string) ($parts[1] ?? '')];
    }

    private static function toYmd(string $value): ?string
    {
        $value = trim($value);
        foreach (['d.m.Y', 'Y-m-d'] as $format) {
            $d = \DateTime::createFromFormat($format, $value);
            if ($d && $d->format($format) === $value) {
                return $d->format('Y-m-d');
            }
        }
        return null;
    }

    public static function apply(string $type, string $raw, string $expr, string $key, array &$bind, array $valueMap = []): string
    {
        [$mode, $values] = self::parse($raw);
        if ($mode === '') {
            return '';
        }

        if ($type === 'select') {
            $keys = [];
            foreach ($values as $v) {
                $mapped = $valueMap[$v] ?? ($valueMap === [] ? $v : null);
                if ($mapped !== null && $mapped !== '') {
                    $keys[] = $mapped;
                }
            }
            if (!$keys) {
                return ' AND 1 = 0';
            }
            $ph = [];
            foreach ($keys as $i => $k) {
                $ph[] = ":{$key}_$i";
                $bind["{$key}_$i"] = $k;
            }
            return " AND $expr IN (" . implode(',', $ph) . ')';
        }

        if ($mode === 'null') {
            return " AND $expr IS NULL";
        }
        if ($mode === 'not_null') {
            return " AND $expr IS NOT NULL";
        }

        if ($type === 'number') {
            $v = str_replace(',', '.', (string) ($values[0] ?? ''));
            if ($v === '' || !is_numeric($v)) {
                return '';
            }
            $ops = ['equals' => '=', 'not_equals' => '<>', 'greater_than' => '>', 'less_than' => '<', 'greater_equal' => '>=', 'less_equal' => '<='];
            if ($mode === 'contains') {
                $bind[$key] = '%' . $v . '%';
                return " AND CAST($expr AS CHAR) LIKE :$key";
            }
            if (!isset($ops[$mode])) {
                return '';
            }
            $bind[$key] = $v + 0;
            return " AND $expr {$ops[$mode]} :$key";
        }

        if ($type === 'date') {
            $d1 = self::toYmd((string) ($values[0] ?? ''));
            if ($d1 === null) {
                return '';
            }
            $col = "DATE($expr)";
            if ($mode === 'between') {
                $d2 = self::toYmd((string) ($values[1] ?? ''));
                if ($d2 === null) {
                    return '';
                }
                $bind["{$key}_1"] = $d1;
                $bind["{$key}_2"] = $d2;
                return " AND $col BETWEEN :{$key}_1 AND :{$key}_2";
            }
            $ops = ['equals' => '=', 'before' => '<', 'after' => '>'];
            if (!isset($ops[$mode])) {
                return '';
            }
            $bind[$key] = $d1;
            return " AND $col {$ops[$mode]} :$key";
        }

        return '';
    }
}
