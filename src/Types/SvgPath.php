<?php

// class that is a representation of an icon using DomDocument and XPath

namespace Ympact\FluxIcons\Types;

use DOMNode;

class SvgPath
{
    protected $dom;

    protected DOMNode $tag;

    protected $type;

    protected $d;

    public function __construct(DOMNode $tag)
    {
        $this->tag = $tag;
        $this->dom = $tag->ownerDocument;
        // get attributes and node type from $tag
        $this->type = $tag->nodeName;
        $this->d = $tag->getAttribute('d');
    }

    public function getDom()
    {
        return $this->dom;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getD()
    {
        return $this->d;
    }

    /**
     * The attributes on the element, by name.
     *
     * @return array<string, string>
     */
    public function getAttributes(): array
    {
        $attributes = [];

        foreach ($this->tag->attributes ?? [] as $attribute) {
            $attributes[$attribute->nodeName] = (string) $attribute->nodeValue;
        }

        return $attributes;
    }

    /**
     * @param  array<string, string|int|float>  $attributes
     */
    public function setAttributes(array $attributes): static
    {
        foreach ($attributes as $name => $value) {
            $this->tag->setAttribute($name, (string) $value);
        }

        return $this;
    }

    public function getNode()
    {
        return $this->tag;
    }
}
