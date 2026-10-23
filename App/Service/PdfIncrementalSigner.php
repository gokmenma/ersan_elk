<?php

namespace App\Service;

final class PdfIncrementalSigner
{
    public const SIGNATURE_BYTES = 16384;
    private const BYTE_RANGE_PLACEHOLDER = '/ByteRange [0 ********** ********** **********]';

    private string $pdf = '';
    private array $offsets = [];
    private array $objectStreams = [];
    private array $trailer = [];
    private bool $xrefStream = false;
    private int $startXref = 0;

    public function prepare(string $pdf, array $meta): array
    {
        $this->load($pdf);
        if (isset($this->trailer['Encrypt'])) {
            throw new \RuntimeException('Şifreli PDF mobil imza ile imzalanamaz.');
        }
        $rootRef = $this->trailer['Root'] ?? null;
        if (!$this->isRef($rootRef)) {
            throw new \RuntimeException('PDF katalog nesnesi bulunamadı.');
        }

        $size = (int) ($this->trailer['Size'] ?? 0);
        $sigNumber = $size;
        $fieldNumber = $size + 1;
        $nextNumber = $size + 2;
        $updated = [];

        $catalog = $this->object($rootRef['num']);
        $pageRef = $this->firstPageRef($catalog);
        $page = $this->object($pageRef['num']);

        $fieldRef = ['__ref' => true, 'num' => $fieldNumber, 'gen' => 0];
        $acroForm = $catalog['AcroForm'] ?? null;
        if ($this->isRef($acroForm)) {
            $form = $this->object($acroForm['num']);
            $updated[$acroForm['num']] = [$acroForm['gen'], $this->withField($form, $fieldRef, $updated)];
        } else {
            $catalog['AcroForm'] = $this->withField($this->isDict($acroForm) ? $acroForm : ['__dict' => true], $fieldRef, $updated);
            $updated[$rootRef['num']] = [$rootRef['gen'], $catalog];
        }

        $annots = $page['Annots'] ?? null;
        if ($this->isRef($annots)) {
            $list = $this->object($annots['num']);
            $list = is_array($list) && !$this->isDict($list) ? $list : [];
            $list[] = $fieldRef;
            $updated[$annots['num']] = [$annots['gen'], $list];
        } else {
            $page['Annots'] = array_merge(is_array($annots) ? $annots : [], [$fieldRef]);
            $updated[$pageRef['num']] = [$pageRef['gen'], $page];
        }

        $signatureDictionary = '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached '
            . self::BYTE_RANGE_PLACEHOLDER
            . ' /Contents <' . str_repeat('0', self::SIGNATURE_BYTES * 2) . '>'
            . ' /M ' . $this->literal('D:' . gmdate('YmdHis') . "+00'00'")
            . ' /Name ' . $this->text((string) ($meta['name'] ?? ''))
            . ' /Reason ' . $this->text((string) ($meta['reason'] ?? ''))
            . ' /Location ' . $this->text((string) ($meta['location'] ?? ''))
            . ' /Prop_Build << /App << /Name /ErsanMobilImza >> >> >>';
        $fieldDictionary = '<< /Type /Annot /Subtype /Widget /FT /Sig /F 132 /Rect [0 0 0 0]'
            . ' /T ' . $this->text('MobilImza_' . bin2hex(random_bytes(4)))
            . ' /V ' . $sigNumber . ' 0 R /P ' . $pageRef['num'] . ' ' . $pageRef['gen'] . ' R >>';

        $output = $this->pdf;
        if (!str_ends_with($output, "\n")) {
            $output .= "\n";
        }
        $entries = [];
        foreach ($updated as $number => [$generation, $value]) {
            $entries[$number] = [strlen($output), $generation];
            $output .= $number . ' ' . $generation . " obj\n" . $this->serialize($value) . "\nendobj\n";
        }
        $entries[$sigNumber] = [strlen($output), 0];
        $output .= $sigNumber . " 0 obj\n" . $signatureDictionary . "\nendobj\n";
        $entries[$fieldNumber] = [strlen($output), 0];
        $output .= $fieldNumber . " 0 obj\n" . $fieldDictionary . "\nendobj\n";

        $output = $this->appendXref($output, $entries, $nextNumber);

        $placeholderOffset = strrpos($output, self::BYTE_RANGE_PLACEHOLDER);
        $contentsStart = strpos($output, '/Contents <', (int) $placeholderOffset);
        if ($placeholderOffset === false || $contentsStart === false) {
            throw new \RuntimeException('İmza alanı PDF içine yerleştirilemedi.');
        }
        $gapStart = $contentsStart + strlen('/Contents ');
        $gapEnd = $gapStart + self::SIGNATURE_BYTES * 2 + 2;
        $byteRange = [0, $gapStart, $gapEnd, strlen($output) - $gapEnd];
        $rangeText = '/ByteRange [' . implode(' ', $byteRange) . ']';
        $rangeText = str_pad($rangeText, strlen(self::BYTE_RANGE_PLACEHOLDER), ' ');
        if (strlen($rangeText) !== strlen(self::BYTE_RANGE_PLACEHOLDER)) {
            throw new \RuntimeException('PDF ByteRange alanı için çok büyük.');
        }
        $output = substr_replace($output, $rangeText, $placeholderOffset, strlen($rangeText));

        return [
            'pdf' => $output,
            'byte_range' => $byteRange,
            'digest' => hash('sha256', substr($output, 0, $byteRange[1]) . substr($output, $byteRange[2], $byteRange[3]), true),
        ];
    }

    public function embed(string $preparedPdf, array $byteRange, string $cms): string
    {
        [$start1, $length1, $start2, $length2] = array_map('intval', $byteRange);
        $hex = strtoupper(bin2hex($cms));
        $capacity = $start2 - $length1 - 2;
        if ($start1 !== 0 || $start2 + $length2 !== strlen($preparedPdf) || $preparedPdf[$length1] !== '<' || $preparedPdf[$start2 - 1] !== '>') {
            throw new \RuntimeException('Hazırlanan PDF imza alanı bozulmuş.');
        }
        if (strlen($hex) > $capacity) {
            throw new \RuntimeException('İmza paketi PDF imza alanına sığmıyor.');
        }
        return substr_replace($preparedPdf, str_pad($hex, $capacity, '0'), $length1 + 1, $capacity);
    }

    private function load(string $pdf): void
    {
        $this->pdf = $pdf;
        $this->offsets = [];
        $this->objectStreams = [];
        $this->trailer = [];
        if (!preg_match_all('/startxref\s+(\d+)\s+%%EOF/', $pdf, $matches) || $matches[1] === []) {
            throw new \RuntimeException('PDF çapraz referans tablosu bulunamadı.');
        }
        $this->startXref = (int) end($matches[1]);
        $offset = $this->startXref;
        $visited = [];
        $first = true;
        while ($offset > 0 && !isset($visited[$offset])) {
            $visited[$offset] = true;
            [$trailer, $isStream] = $this->readXref($offset);
            if ($first) {
                $this->trailer = $trailer;
                $this->xrefStream = $isStream;
                $first = false;
            }
            if (isset($trailer['XRefStm']) && is_int($trailer['XRefStm'])) {
                $this->readXref($trailer['XRefStm']);
            }
            $offset = isset($trailer['Prev']) && is_int($trailer['Prev']) ? $trailer['Prev'] : 0;
        }
    }

    private function readXref(int $offset): array
    {
        if (substr($this->pdf, $offset, 4) === 'xref') {
            $position = $offset + 4;
            while (preg_match('/\G\s*(\d+)\s+(\d+)[ \t]*\r?\n/', $this->pdf, $m, 0, $position)) {
                $position += strlen($m[0]);
                $start = (int) $m[1];
                $count = (int) $m[2];
                for ($i = 0; $i < $count; $i++) {
                    if (!preg_match('/\G\s*(\d{10})\s+(\d{5})\s+([nf])/', $this->pdf, $e, 0, $position)) {
                        throw new \RuntimeException('PDF xref kaydı okunamadı.');
                    }
                    $position += strlen($e[0]);
                    $number = $start + $i;
                    if (!array_key_exists($number, $this->offsets)) {
                        $this->offsets[$number] = $e[3] === 'n' ? ['type' => 1, 'offset' => (int) $e[1], 'gen' => (int) $e[2]] : null;
                    }
                }
            }
            $trailerPosition = strpos($this->pdf, 'trailer', $position);
            if ($trailerPosition === false) {
                throw new \RuntimeException('PDF trailer bulunamadı.');
            }
            $position = $trailerPosition + 7;
            return [$this->parseValue($position), false];
        }

        $position = $offset;
        if (!preg_match('/\G\s*\d+\s+\d+\s+obj/', $this->pdf, $m, 0, $position)) {
            throw new \RuntimeException('PDF xref akışı okunamadı.');
        }
        $position += strlen($m[0]);
        $dictionary = $this->parseValue($position);
        if (($dictionary['Type'] ?? null) !== '/XRef') {
            throw new \RuntimeException('PDF xref akışı geçersiz.');
        }
        $data = $this->streamData($dictionary, $position);
        $widths = array_map('intval', $dictionary['W'] ?? []);
        $index = $dictionary['Index'] ?? [0, (int) ($dictionary['Size'] ?? 0)];
        $cursor = 0;
        for ($i = 0; $i + 1 < count($index); $i += 2) {
            for ($j = 0; $j < (int) $index[$i + 1]; $j++) {
                $fields = [];
                foreach ($widths as $k => $width) {
                    $value = $width === 0 ? ($k === 0 ? 1 : 0) : 0;
                    for ($b = 0; $b < $width; $b++) {
                        $value = ($value << 8) | ord($data[$cursor + $b] ?? "\x00");
                    }
                    $cursor += $width;
                    $fields[] = $value;
                }
                $number = (int) $index[$i] + $j;
                if (array_key_exists($number, $this->offsets)) {
                    continue;
                }
                $this->offsets[$number] = match ($fields[0]) {
                    1 => ['type' => 1, 'offset' => $fields[1], 'gen' => $fields[2]],
                    2 => ['type' => 2, 'stream' => $fields[1], 'index' => $fields[2], 'gen' => 0],
                    default => null,
                };
            }
        }
        return [$dictionary, true];
    }

    private function object(int $number)
    {
        $entry = $this->offsets[$number] ?? null;
        if ($entry === null) {
            throw new \RuntimeException('PDF nesnesi bulunamadı: ' . $number);
        }
        if ($entry['type'] === 2) {
            return $this->objectFromStream($entry['stream'], $entry['index']);
        }
        $position = $entry['offset'];
        if (!preg_match('/\G\s*' . $number . '\s+\d+\s+obj/', $this->pdf, $m, 0, $position)) {
            throw new \RuntimeException('PDF nesne başlığı okunamadı: ' . $number);
        }
        $position += strlen($m[0]);
        return $this->parseValue($position);
    }

    private function objectFromStream(int $streamNumber, int $index)
    {
        if (!isset($this->objectStreams[$streamNumber])) {
            $entry = $this->offsets[$streamNumber] ?? null;
            if ($entry === null || $entry['type'] !== 1) {
                throw new \RuntimeException('PDF nesne akışı bulunamadı.');
            }
            $position = $entry['offset'];
            preg_match('/\G\s*\d+\s+\d+\s+obj/', $this->pdf, $m, 0, $position);
            $position += strlen($m[0] ?? '');
            $dictionary = $this->parseValue($position);
            $this->objectStreams[$streamNumber] = [$dictionary, $this->streamData($dictionary, $position)];
        }
        [$dictionary, $data] = $this->objectStreams[$streamNumber];
        $count = (int) ($dictionary['N'] ?? 0);
        $first = (int) ($dictionary['First'] ?? 0);
        $pairs = preg_split('/\s+/', trim(substr($data, 0, $first)));
        if ($index >= $count || !isset($pairs[$index * 2 + 1])) {
            throw new \RuntimeException('PDF nesne akışı dizini geçersiz.');
        }
        $saved = $this->pdf;
        $this->pdf = $data;
        try {
            $position = $first + (int) $pairs[$index * 2 + 1];
            return $this->parseValue($position);
        } finally {
            $this->pdf = $saved;
        }
    }

    private function streamData(array $dictionary, int $position): string
    {
        if (!preg_match('/\G\s*stream\r?\n/', $this->pdf, $m, 0, $position)) {
            throw new \RuntimeException('PDF akış verisi bulunamadı.');
        }
        $start = $position + strlen($m[0]);
        $length = $dictionary['Length'] ?? 0;
        if ($this->isRef($length)) {
            $length = $this->object($length['num']);
        }
        $data = substr($this->pdf, $start, (int) $length);
        $filter = $dictionary['Filter'] ?? null;
        $filters = is_array($filter) ? $filter : ($filter === null ? [] : [$filter]);
        foreach ($filters as $name) {
            if ($name !== '/FlateDecode') {
                throw new \RuntimeException('Desteklenmeyen PDF akış filtresi: ' . $name);
            }
            $decoded = @gzuncompress($data);
            if ($decoded === false) {
                throw new \RuntimeException('PDF akışı açılamadı.');
            }
            $data = $decoded;
        }
        $parameters = $dictionary['DecodeParms'] ?? null;
        if (is_array($parameters) && !$this->isDict($parameters)) {
            $parameters = $parameters[0] ?? null;
        }
        $predictor = is_array($parameters) ? (int) ($parameters['Predictor'] ?? 1) : 1;
        if ($predictor >= 10) {
            $columns = (int) ($parameters['Columns'] ?? 1);
            $data = $this->unpredict($data, $columns);
        }
        return $data;
    }

    private function unpredict(string $data, int $columns): string
    {
        $output = '';
        $previous = str_repeat("\x00", $columns);
        $rowLength = $columns + 1;
        for ($offset = 0; $offset + $rowLength <= strlen($data); $offset += $rowLength) {
            $type = ord($data[$offset]);
            $row = substr($data, $offset + 1, $columns);
            $decoded = '';
            for ($i = 0; $i < $columns; $i++) {
                $raw = ord($row[$i]);
                $left = $i > 0 ? ord($decoded[$i - 1]) : 0;
                $up = ord($previous[$i]);
                $upLeft = $i > 0 ? ord($previous[$i - 1]) : 0;
                $value = match ($type) {
                    0 => $raw,
                    1 => $raw + $left,
                    2 => $raw + $up,
                    3 => $raw + intdiv($left + $up, 2),
                    4 => $raw + $this->paeth($left, $up, $upLeft),
                    default => throw new \RuntimeException('PDF PNG tahmin türü desteklenmiyor.'),
                };
                $decoded .= chr($value & 0xff);
            }
            $output .= $decoded;
            $previous = $decoded;
        }
        return $output;
    }

    private function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);
        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        }
        return $pb <= $pc ? $b : $c;
    }

    private function firstPageRef(array $catalog): array
    {
        $ref = $catalog['Pages'] ?? null;
        for ($depth = 0; $depth < 32 && $this->isRef($ref); $depth++) {
            $node = $this->object($ref['num']);
            if (($node['Type'] ?? null) === '/Page') {
                return $ref;
            }
            $kids = $node['Kids'] ?? [];
            if ($this->isRef($kids)) {
                $kids = $this->object($kids['num']);
            }
            $ref = $kids[0] ?? null;
        }
        throw new \RuntimeException('PDF ilk sayfası bulunamadı.');
    }

    private function withField(array $form, array $fieldRef, array &$updated): array
    {
        $fields = $form['Fields'] ?? [];
        if ($this->isRef($fields)) {
            $list = $this->object($fields['num']);
            $list = is_array($list) && !$this->isDict($list) ? $list : [];
            $list[] = $fieldRef;
            $updated[$fields['num']] = [$fields['gen'], $list];
        } else {
            $form['Fields'] = array_merge(is_array($fields) ? $fields : [], [$fieldRef]);
        }
        $form['SigFlags'] = 3;
        return $form;
    }

    private function appendXref(string $output, array $entries, int $size): string
    {
        ksort($entries);
        $id = $this->trailer['ID'] ?? null;
        $firstId = is_array($id) && isset($id[0]) ? $this->serialize($id[0]) : '<' . bin2hex(random_bytes(16)) . '>';
        $trailerItems = '/Size ' . $size . ' /Root ' . $this->serialize($this->trailer['Root'])
            . (isset($this->trailer['Info']) ? ' /Info ' . $this->serialize($this->trailer['Info']) : '')
            . ' /ID [' . $firstId . ' <' . bin2hex(random_bytes(16)) . '>] /Prev ' . $this->startXref;

        $subsections = [];
        foreach ($entries as $number => $entry) {
            $last = array_key_last($subsections);
            if ($last !== null && $subsections[$last]['start'] + count($subsections[$last]['rows']) === $number) {
                $subsections[$last]['rows'][] = $entry;
            } else {
                $subsections[] = ['start' => $number, 'rows' => [$entry]];
            }
        }

        if (!$this->xrefStream) {
            $xrefOffset = strlen($output);
            $output .= "xref\n";
            foreach ($subsections as $section) {
                $output .= $section['start'] . ' ' . count($section['rows']) . "\n";
                foreach ($section['rows'] as [$offset, $generation]) {
                    $output .= sprintf("%010d %05d n\r\n", $offset, $generation);
                }
            }
            return $output . 'trailer' . "\n<< " . $trailerItems . " >>\nstartxref\n" . $xrefOffset . "\n%%EOF\n";
        }

        $xrefNumber = $size;
        $xrefOffset = strlen($output);
        $subsections[] = ['start' => $xrefNumber, 'rows' => [[$xrefOffset, 0]]];
        $index = [];
        $data = '';
        foreach ($subsections as $section) {
            $index[] = $section['start'] . ' ' . count($section['rows']);
            foreach ($section['rows'] as [$offset, $generation]) {
                $data .= "\x01" . pack('N', $offset) . pack('n', $generation);
            }
        }
        $trailerItems = str_replace('/Size ' . $size, '/Size ' . ($size + 1), $trailerItems);
        return $output . $xrefNumber . " 0 obj\n<< /Type /XRef " . $trailerItems . ' /Index [' . implode(' ', $index) . '] /W [1 4 2] /Length ' . strlen($data)
            . " >>\nstream\n" . $data . "\nendstream\nendobj\nstartxref\n" . $xrefOffset . "\n%%EOF\n";
    }

    private function parseValue(int &$position)
    {
        $this->skipWhitespace($position);
        $char = $this->pdf[$position] ?? '';
        if ($char === '<' && ($this->pdf[$position + 1] ?? '') === '<') {
            $position += 2;
            $dictionary = ['__dict' => true];
            while (true) {
                $this->skipWhitespace($position);
                if (substr($this->pdf, $position, 2) === '>>') {
                    $position += 2;
                    return $dictionary;
                }
                $key = $this->parseValue($position);
                if (!is_string($key) || !str_starts_with($key, '/')) {
                    throw new \RuntimeException('PDF sözlüğü okunamadı.');
                }
                $dictionary[substr($key, 1)] = $this->parseValue($position);
            }
        }
        if ($char === '[') {
            $position++;
            $array = [];
            while (true) {
                $this->skipWhitespace($position);
                if (($this->pdf[$position] ?? ']') === ']') {
                    $position++;
                    return $array;
                }
                $array[] = $this->parseValue($position);
            }
        }
        if ($char === '/') {
            preg_match('/\G\/[^\s\/\[\]<>(){}%]*/', $this->pdf, $m, 0, $position);
            $position += strlen($m[0]);
            return $m[0];
        }
        if ($char === '(') {
            $start = $position;
            $depth = 0;
            $length = strlen($this->pdf);
            for (; $position < $length; $position++) {
                $c = $this->pdf[$position];
                if ($c === '\\') {
                    $position++;
                } elseif ($c === '(') {
                    $depth++;
                } elseif ($c === ')' && --$depth === 0) {
                    $position++;
                    break;
                }
            }
            return ['__raw' => substr($this->pdf, $start, $position - $start)];
        }
        if ($char === '<') {
            $end = strpos($this->pdf, '>', $position);
            $raw = substr($this->pdf, $position, $end - $position + 1);
            $position = $end + 1;
            return ['__raw' => $raw];
        }
        if (preg_match('/\G(\d+)\s+(\d+)\s+R(?![A-Za-z])/', $this->pdf, $m, 0, $position)) {
            $position += strlen($m[0]);
            return ['__ref' => true, 'num' => (int) $m[1], 'gen' => (int) $m[2]];
        }
        if (preg_match('/\G[+-]?(?:\d+\.?\d*|\.\d+)/', $this->pdf, $m, 0, $position)) {
            $position += strlen($m[0]);
            return str_contains($m[0], '.') ? ['__raw' => $m[0]] : (int) $m[0];
        }
        if (preg_match('/\G(true|false|null)/', $this->pdf, $m, 0, $position)) {
            $position += strlen($m[0]);
            return ['__raw' => $m[0]];
        }
        throw new \RuntimeException('PDF değeri okunamadı.');
    }

    private function skipWhitespace(int &$position): void
    {
        $length = strlen($this->pdf);
        while ($position < $length) {
            $c = $this->pdf[$position];
            if ($c === '%') {
                while ($position < $length && $this->pdf[$position] !== "\n" && $this->pdf[$position] !== "\r") {
                    $position++;
                }
            } elseif (strpos(" \t\r\n\f\0", $c) !== false) {
                $position++;
            } else {
                return;
            }
        }
    }

    private function serialize($value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return $value;
        }
        if (isset($value['__raw'])) {
            return $value['__raw'];
        }
        if (isset($value['__ref'])) {
            return $value['num'] . ' ' . $value['gen'] . ' R';
        }
        if (isset($value['__dict'])) {
            $parts = [];
            foreach ($value as $key => $item) {
                if ($key !== '__dict') {
                    $parts[] = '/' . $key . ' ' . $this->serialize($item);
                }
            }
            return '<< ' . implode(' ', $parts) . ' >>';
        }
        return '[' . implode(' ', array_map(fn($item) => $this->serialize($item), $value)) . ']';
    }

    private function isRef($value): bool
    {
        return is_array($value) && isset($value['__ref']);
    }

    private function isDict($value): bool
    {
        return is_array($value) && isset($value['__dict']);
    }

    private function literal(string $value): string
    {
        return '(' . strtr($value, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']) . ')';
    }

    private function text(string $value): string
    {
        return '<FEFF' . strtoupper(bin2hex(mb_convert_encoding($value, 'UTF-16BE', 'UTF-8'))) . '>';
    }
}
