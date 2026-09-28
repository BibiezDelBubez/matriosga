<?php
declare(strict_types=1);

namespace App\Helpers;

/** Ricerca ed evidenziazione di testo (lato PHP). */
final class Text
{
    /** Regex case-insensitive per $term; $wholeWord: non dentro un identificatore più lungo (BaCliFor ≠ BaCliForNote). */
    public static function regex(string $term, bool $wholeWord): string
    {
        $w = $wholeWord ? ['(?<![A-Za-z0-9_])', '(?![A-Za-z0-9_])'] : ['', ''];
        return '/' . $w[0] . '(' . preg_quote($term, '/') . ')' . $w[1] . '/iu';
    }

    /** HTML con le occorrenze in <mark>, tutto il resto escapato. */
    public static function highlight(string $text, string $term, bool $wholeWord = false): string
    {
        if ($term === '') {
            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $parts = preg_split(self::regex($term, $wholeWord), $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $html = '';
        foreach ($parts as $i => $part) {
            $safe = htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= $i % 2 ? '<mark>' . $safe . '</mark>' : $safe;
        }
        return $html;
    }
}
