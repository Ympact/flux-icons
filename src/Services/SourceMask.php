<?php

namespace Ympact\FluxIcons\Services;

/**
 * Blanks out the parts of a source that never run, so a search over what is left finds only
 * live references. Every byte keeps its offset and every line its number: blanked text
 * becomes spaces, and its line breaks stay.
 */
class SourceMask
{
    /** What a block of Blade PHP is tokenized behind, to be read as PHP. */
    protected const OPEN_TAG = '<?php ';

    /**
     * A PHP file, of which only the string literals and any inline HTML are kept.
     */
    public static function php(string $contents): string
    {
        $kept = [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML];
        $masked = '';

        foreach (token_get_all($contents) as $token) {
            $text = is_array($token) ? $token[1] : $token;
            $masked .= is_array($token) && in_array($token[0], $kept, true) ? $text : static::blank($text);
        }

        return $masked;
    }

    /**
     * A Blade template without its `{{-- --}}` comments, nor the PHP comments in its
     * `<?php ?>` and `@php @endphp` blocks. An HTML comment stays: Blade still renders
     * the components inside one.
     */
    public static function blade(string $contents): string
    {
        $contents = (string) preg_replace_callback('/\{\{--.*?--\}\}/s', fn (array $comment) => static::blank($comment[0]), $contents);
        $contents = static::phpComments($contents, $contents, 0);

        preg_match_all('/@php(?!\s*\()(.*?)@endphp/s', $contents, $blocks, PREG_OFFSET_CAPTURE);

        foreach ($blocks[1] as [$body, $offset]) {
            $contents = static::phpComments($contents, static::OPEN_TAG.$body, $offset - strlen(static::OPEN_TAG));
        }

        return $contents;
    }

    /**
     * Blank the comments that tokenizing $code finds, in $contents where $code starts at $offset.
     */
    protected static function phpComments(string $contents, string $code, int $offset): string
    {
        foreach (token_get_all($code) as $token) {
            $text = is_array($token) ? $token[1] : $token;

            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $contents = substr_replace($contents, static::blank($text), $offset, strlen($text));
            }

            $offset += strlen($text);
        }

        return $contents;
    }

    protected static function blank(string $text): string
    {
        return (string) preg_replace('/[^\n]/', ' ', $text);
    }
}
