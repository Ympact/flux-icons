<?php

namespace Ympact\FluxIcons\Types;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Removes what would run script where a built icon is rendered: a component is inlined into the
 * page, so an icon package that ships a script reaches every page that draws its icons. No icon
 * needs any of it. Styles are kept: they run no script, though a `<style>` applies to the whole
 * page and a `url()` in one can load from another site.
 */
class SvgSanitiser
{
    /**
     * Elements that run script or load a document: SVG's own, and HTML's, since a browser
     * leaves an inline `<svg>` for some of them, such as `<embed>` and `<meta>`.
     */
    protected const ELEMENTS = ['script', 'handler', 'foreignobject', 'iframe', 'frame', 'frameset', 'embed', 'object', 'applet', 'meta', 'link', 'base'];

    /** URL schemes that run what follows them, matched once whitespace and case are removed. */
    protected const SCHEMES = ['javascript:', 'vbscript:', 'data:text/html'];

    public function sanitise(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);

        // iterator_to_array() first: removing a node from a live list skips the next one
        foreach (iterator_to_array($xpath->query('//*') ?: []) as $element) {
            if ($element instanceof DOMElement && in_array(strtolower($element->localName ?? ''), self::ELEMENTS, true)) {
                $element->parentNode?->removeChild($element);
            }
        }

        foreach (iterator_to_array($xpath->query('//@*') ?: []) as $attribute) {
            if ($attribute instanceof DOMAttr && $this->runsScript($attribute)) {
                $attribute->ownerElement?->removeAttributeNode($attribute);
            }
        }
    }

    /**
     * An event handler, or a value with a scheme that runs script: in `href`, but also in an
     * `<animate>` that sets `href`, or in a `style` url().
     */
    protected function runsScript(DOMAttr $attribute): bool
    {
        if (str_starts_with(strtolower($attribute->localName ?? ''), 'on')) {
            return true;
        }

        // a browser skips tabs and newlines inside a scheme, so `java&#9;script:` still runs
        $value = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $attribute->value));

        foreach (self::SCHEMES as $scheme) {
            if (str_contains($value, $scheme)) {
                return true;
            }
        }

        return false;
    }
}
