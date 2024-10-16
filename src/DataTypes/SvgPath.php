<?php

// class that is a representation of an icon using DomDocument and XPath
namespace Ympact\FluxIcons\DataTypes;

use DOMNameSpaceNode;
use DOMNode;

class SvgPath{

    protected $dom;

    protected DOMNode|DOMNameSpaceNode $tag;

    protected $type;

    protected $d;

    public function __construct(DOMNode|DOMNameSpaceNode $tag)
    {
        $this->tag = $tag;
        // get attributes and node type from $tag
        $this->type = $tag->nodeName;
        $this->d = $tag->getAttribute('d');
    }

    public function getType(){
        return $this->type;
    }

    public function getD(){
        return $this->d;
    }

}
