<?php

namespace App\Helper;

use DOMDocument;
use DOMElement;
use DOMNode;

final class RichTextSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's',
        'ul', 'ol', 'li', 'blockquote', 'h1', 'h2', 'h3', 'h4',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'a', 'hr'
    ];

    private const ALLOWED_ATTRIBUTES = ['href', 'title', 'target', 'style', 'colspan', 'rowspan'];

    public static function sanitize(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="rich-text-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $root = $document->getElementById('rich-text-root');
        if (!$root) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return '';
        }

        self::cleanChildren($root);

        $clean = '';
        foreach ($root->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return trim($clean);
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;

            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    $parent->removeChild($node);
                    $node = $next;
                    continue;
                }

                foreach (iterator_to_array($node->attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    if (!in_array($name, self::ALLOWED_ATTRIBUTES, true)) {
                        $node->removeAttribute($name);
                    }
                }

                if ($node->hasAttribute('href')) {
                    $href = trim($node->getAttribute('href'));
                    if (!preg_match('~^(https?://|mailto:|tel:|#)~i', $href)) {
                        $node->removeAttribute('href');
                    }
                }

                if ($node->hasAttribute('target') && $node->getAttribute('target') !== '_blank') {
                    $node->removeAttribute('target');
                }

                if ($node->hasAttribute('style')) {
                    $style = self::sanitizeStyle($node->getAttribute('style'));
                    $style === '' ? $node->removeAttribute('style') : $node->setAttribute('style', $style);
                }

                self::cleanChildren($node);
            }

            $node = $next;
        }
    }

    private static function sanitizeStyle(string $style): string
    {
        $allowed = ['text-align', 'font-family', 'font-size', 'font-weight', 'font-style', 'text-decoration', 'color', 'background-color'];
        $safe = [];

        foreach (explode(';', $style) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            if (in_array($property, $allowed, true) && !preg_match('/url\s*\(|expression|javascript:/i', $value)) {
                if ($property === 'font-family') {
                    if (stripos($value, 'arial') !== false) {
                        $safe['font-family'] = 'Arial';
                    } elseif (stripos($value, 'times new roman') !== false || stripos($value, 'times') !== false) {
                        $safe['font-family'] = 'Times New Roman';
                    }
                    continue;
                }
                $safe[$property] = $value;
            }
        }

        if (($safe['font-family'] ?? '') === 'Arial') {
            $safe['font-size'] = '11pt';
        } elseif (($safe['font-family'] ?? '') === 'Times New Roman') {
            $safe['font-size'] = '12pt';
        }

        return implode('; ', array_map(fn($property, $value) => $property . ': ' . $value, array_keys($safe), $safe));
    }
}
