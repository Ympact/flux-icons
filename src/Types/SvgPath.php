<?php

// class that is a representation of an icon using DomDocument and XPath
namespace Ympact\FluxIcons\Types;

use DOMNode;

class SvgPath{

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

    public function getType(){
        return $this->type;
    }

    public function getD(){
        return $this->d;
    }

    // get the attributes on the path
    public function getAttributes(){
        $attributes = [];
        foreach($this->tag->attributes as $key => $value){
            $attributes[$key] = $value;
        }
        return $attributes;
    }

    // set the attributes on the path
    public function setAttributes($attributes){
        foreach($attributes as $key => $value){
            $this->tag->setAttribute($key, $value);
        }
    }

    public function getNode(){
        return $this->tag;
    }

}
