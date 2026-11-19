<?php
declare(strict_types=1);

namespace App\Services;

final class CmsHtmlSanitizer
{
    private const TAGS = '<p><br><h2><h3><h4><ul><ol><li><strong><b><em><i><blockquote><a><hr>';

    public static function clean(string $html): string
    {
        $html = preg_replace('~<(script|style|iframe|object|embed|svg|math|form|input|button|video|audio|link|meta|base)\b[^>]*>.*?</\1\s*>~is', '', $html) ?? '';
        $html = preg_replace('~<(script|style|iframe|object|embed|svg|math|form|input|button|video|audio|link|meta|base)\b[^>]*>.*$~is', '', $html) ?? '';
        $html = preg_replace('~<(script|style|iframe|object|embed|svg|math|form|input|button|video|audio|link|meta|base)\b[^>]*/?>~is', '', $html) ?? '';
        $html = strip_tags($html, self::TAGS);
        return preg_replace_callback('~<(/?)([a-z0-9]+)([^>]*)>~i', static function (array $match): string {
            $tag = strtolower($match[2]);
            if (!in_array($tag, ['p','br','h2','h3','h4','ul','ol','li','strong','b','em','i','blockquote','a','hr'], true)) { return ''; }
            if ($match[1] === '/') { return in_array($tag, ['br','hr'], true) ? '' : '</'.$tag.'>'; }
            if ($tag !== 'a') { return '<'.$tag.'>'; }
            $attrs = $match[3];
            if (preg_match('~\bhref\s*=\s*(["\'])(.*?)\1~i', $attrs, $href) !== 1) { return '<a>'; }
            $url = html_entity_decode($href[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('~^(https?://[^\s]+|mailto:[^\s]+|/[^/][^\s]*|#[a-z0-9_-]+)$~i', $url) !== 1) { return '<a>'; }
            return '<a href="'.htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" rel="nofollow noopener noreferrer">';
        }, $html) ?? '';
    }
}
