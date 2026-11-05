<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Renders the article editor's light markup into a small, fixed set of HTML tags. Articles are stored as plain
 * text (HTML tags are refused on save) and every character is escaped before any markup is applied, so the only
 * tags that can reach the page are the ones produced here.
 *
 * Supported markup, one block per run of lines separated by a blank line:
 *   ## Heading / ### Smaller heading
 *   - item (or * item)       → bulleted list
 *   1. item                  → numbered list
 *   > quote                  → quotation
 *   anything else            → paragraph; single line breaks are kept
 * Inline: **bold**, *italic*, ***bold italic***. The admin editor writes exactly this markup.
 */
final class ArticleFormatter
{
    public static function toHtml(string $text): HtmlString
    {
        // /u matters: without it \R also matches the byte 0x85 found inside letters such as "ễ".
        $lines = preg_split('/\R/u', trim($text)) ?: [];
        $html = '';
        $block = null;
        $items = [];
        $flush = function () use (&$html, &$block, &$items): void {
            if ($block === null) {
                return;
            }
            $html .= match ($block) {
                'p' => '<p>'.implode('<br>', $items).'</p>',
                'quote' => '<blockquote><p>'.implode('<br>', $items).'</p></blockquote>',
                'ul', 'ol' => '<'.$block.'>'.implode('', array_map(fn (string $item): string => '<li>'.$item.'</li>', $items)).'</'.$block.'>',
            };
            $block = null;
            $items = [];
        };

        foreach ($lines as $line) {
            $line = rtrim($line);
            if (trim($line) === '') {
                $flush();

                continue;
            }
            if (preg_match('/^(#{2,3})\s+(.+)$/u', $line, $match)) {
                $flush();
                $level = strlen($match[1]);
                $html .= '<h'.$level.'>'.self::inline($match[2]).'</h'.$level.'>';

                continue;
            }
            [$type, $content] = match (true) {
                (bool) preg_match('/^\s*[-*]\s+(.+)$/u', $line, $match) => ['ul', $match[1]],
                (bool) preg_match('/^\s*\d{1,3}[.)]\s+(.+)$/u', $line, $match) => ['ol', $match[1]],
                (bool) preg_match('/^>\s?(.*)$/u', $line, $match) => ['quote', $match[1]],
                default => ['p', $line],
            };
            if ($block !== $type) {
                $flush();
                $block = $type;
            }
            $items[] = self::inline($content);
        }
        $flush();

        return new HtmlString($html);
    }

    /** Escapes the text, then turns ***both***, **bold** and *italic* into tags. */
    private static function inline(string $text): string
    {
        $escaped = e(trim($text));
        $escaped = preg_replace('/\*\*\*(?=\S)(.+?)(?<=\S)\*\*\*/u', '<strong><em>$1</em></strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $escaped) ?? $escaped;

        return preg_replace('/(?<![*\w])\*(?=\S)([^*]+?)(?<=\S)\*(?![*\w])/u', '<em>$1</em>', $escaped) ?? $escaped;
    }
}
