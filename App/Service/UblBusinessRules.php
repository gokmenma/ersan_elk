<?php
namespace App\Service;

/** Executes the official pre-sign rules for the profiles/types this module creates.
 * Envelope, XAdES and specialized unsupported profiles are validated by EDM.
 */
final class UblBusinessRules
{
    private const SUPPORTED_RULES = [
        'UBLVersionIDCheck','CustomizationIDCheck','ProfileIDCheck','InvoiceIDCheck','CopyIndicatorCheck',
        'InvoiceTypeCodeCheck','CurrencyCodeCheck','SignatureCountCheck','GeneralWithholdingTaxTotalCheck',
        'IADEInvioceCheck','UUIDCheck','SignatureCheck','WithholdingTaxTotalCheck',
        'PartyIdentificationSchemeIDCheck','PartyIdentificationTCKNVKNCheck','DocumentSenderCheck','DocumentReceiverCheck',
        'PartyVDCheck','PartyIdentificationPartyNamePersonCheck','TaxTypeCheck','TaxExemptionReasonCheck','TaxExemptionReasonCodeCheck',
        'PriceAmountCheck','InvoicedQuantityCheck','decimalCheck','SignatoryPartyPartyIdentificationCheck','PaymentMeansCodeCheck',
        'GeneralCurrencyCodeCheck','GeneralCurrencyIDCheck','GeneralUnitCodeCheck','CountryCodeCheck','EmptyCheck',
    ];

    private static function literal(string $value): string
    {
        if (!str_contains($value, "'")) return "'" . $value . "'";
        return 'concat(' . implode(',"\'",', array_map(static fn($v) => "'" . $v . "'", explode("'", $value))) . ')';
    }

    public static function matches(string $value, string $pattern): bool
    {
        return preg_match('~' . str_replace('~', '\\~', $pattern) . '~u', $value) === 1;
    }

    /** Translate only the two XPath 2 functions used by our supported rules.
     * No eval; all expressions originate from pinned official XML resources.
     */
    private function expression(string $expression, array $variables): string
    {
        $output = ''; $length = strlen($expression);
        for ($i = 0; $i < $length;) {
            $char = $expression[$i];
            if ($char === "'" || $char === '"') {
                $end = strpos($expression, $char, $i + 1);
                if ($end === false) throw new \RuntimeException('UBL kuralında kapatılmamış metin.');
                $output .= substr($expression, $i, $end - $i + 1); $i = $end + 1; continue;
            }
            if ($char === '$' && preg_match('/\G\$([A-Za-z_][A-Za-z0-9_]*)/A', $expression, $match, 0, $i)) {
                if (!array_key_exists($match[1], $variables)) throw new \RuntimeException('UBL kural değişkeni eksik: ' . $match[1]);
                $output .= '(' . $variables[$match[1]] . ')'; $i += strlen($match[0]); continue;
            }
            if (preg_match('/\G(matches|exists)\s*\(/A', $expression, $match, 0, $i)) {
                $start = $i + strlen($match[0]); $depth = 1; $quote = null; $args = []; $argumentStart = $start;
                for ($j = $start; $j < $length; $j++) {
                    $c = $expression[$j];
                    if ($quote !== null) { if ($c === $quote) $quote = null; continue; }
                    if ($c === "'" || $c === '"') { $quote = $c; continue; }
                    if ($c === '(') $depth++;
                    if ($c === ')') $depth--;
                    if (($c === ',' && $depth === 1) || $depth === 0) {
                        $args[] = $this->expression(substr($expression, $argumentStart, $j - $argumentStart), $variables);
                        $argumentStart = $j + 1;
                    }
                    if ($depth === 0) break;
                }
                if ($depth !== 0) throw new \RuntimeException('UBL kuralında kapatılmamış işlev.');
                $output .= $match[1] === 'exists' ? 'boolean(' . $args[0] . ')' : "php:function('App\\Service\\UblBusinessRules::matches',string(" . $args[0] . '),string(' . $args[1] . '))';
                $i = $j + 1; continue;
            }
            $output .= $char; $i++;
        }
        return $output;
    }

    public function validate(\DOMDocument $document, string $documentType): void
    {
        $root = dirname(__DIR__, 2) . '/resources/efatura/schematron/';
        $main = new \DOMDocument(); $common = new \DOMDocument(); $codes = new \DOMDocument();
        if (!$main->load($root . 'UBL-TR_Main_Schematron.xml', LIBXML_NONET) || !$common->load($root . 'UBL-TR_Common_Schematron.xml', LIBXML_NONET) || !$codes->load($root . 'UBL-TR_Codelist.xml', LIBXML_NONET)) throw new \RuntimeException('Resmi UBL doğrulama dosyaları yüklenemedi.');
        $schemaXPath = static function (\DOMDocument $dom): \DOMXPath {
            $xpath = new \DOMXPath($dom); $xpath->registerNamespace('sch', 'http://purl.oclc.org/dsdl/schematron'); return $xpath;
        };
        $mainXPath = $schemaXPath($main); $commonXPath = $schemaXPath($common); $codeXPath = $schemaXPath($codes);
        $variables = ['type' => self::literal($documentType === 'EARSIV' ? 'earchive' : 'efatura')];
        foreach ($mainXPath->query('/sch:schema/sch:let') as $node) if ($node->getAttribute('name') !== 'type') $variables[$node->getAttribute('name')] = $node->getAttribute('value');
        foreach ($codeXPath->query('//sch:let') as $node) $variables[$node->getAttribute('name')] = $node->getAttribute('value');
        $xpath = new \DOMXPath($document);
        foreach ($mainXPath->query('/sch:schema/sch:ns') as $node) $xpath->registerNamespace($node->getAttribute('prefix'), $node->getAttribute('uri'));
        $xpath->registerNamespace('php', 'http://php.net/xpath'); $xpath->registerPhpFunctions([self::class . '::matches']);
        $abstracts = [];
        foreach ($commonXPath->query('//sch:rule[@abstract="true"]') as $rule) $abstracts[$rule->getAttribute('id')] = $rule;
        $errors = [];
        foreach ($mainXPath->query('//sch:rule[@context]') as $rule) {
            $context = $rule->getAttribute('context');
            $nodes = $xpath->query(str_starts_with($context, '/') ? $context : '//' . $context);
            if ($nodes === false) throw new \RuntimeException('UBL kural bağlamı değerlendirilemedi.');
            if ($nodes->length === 0) continue;
            foreach ($mainXPath->query('sch:extends', $rule) as $extension) {
                $id = $extension->getAttribute('rule');
                if (!in_array($id, self::SUPPORTED_RULES, true)) continue;
                if (!isset($abstracts[$id])) throw new \RuntimeException('UBL kural tanımı eksik: ' . $id);
                foreach ($commonXPath->query('sch:assert', $abstracts[$id]) as $assertion) {
                    $test = $this->expression($assertion->getAttribute('test'), $variables);
                    foreach ($nodes as $node) {
                        $ok = $xpath->evaluate('boolean(' . $test . ')', $node);
                        if (!$ok) $errors[] = $id . ': ' . preg_replace('/\s+/u', ' ', trim($assertion->textContent));
                    }
                }
            }
        }
        if ($errors) throw new \InvalidArgumentException('UBL-TR iş kuralı doğrulaması: ' . implode(' ', array_slice(array_unique($errors), 0, 3)));
    }
}
