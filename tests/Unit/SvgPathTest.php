<?php

use Ympact\FluxIcons\Types\SvgPath;

$makeNode = function (string $tag, array $attributes = []): DOMNode {
    $dom = new DOMDocument;
    $dom->loadXML('<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    $node = $dom->createElement($tag);

    foreach ($attributes as $key => $value) {
        $node->setAttribute($key, $value);
    }

    $dom->documentElement->appendChild($node);

    return $node;
};

it('returns the tag type', function () use ($makeNode) {
    $path = new SvgPath($makeNode('path', ['d' => 'M0 0h24v24H0z']));

    expect($path->getType())->toBe('path');
});

it('returns the d attribute', function () use ($makeNode) {
    $d = 'M10 19l-7-7m0 0l7-7m-7 7h18';
    $path = new SvgPath($makeNode('path', ['d' => $d]));

    expect($path->getD())->toBe($d);
});

it('returns an empty d attribute for non-path tags', function () use ($makeNode) {
    $path = new SvgPath($makeNode('circle', ['cx' => '12', 'cy' => '12', 'r' => '5']));

    expect($path->getD())->toBe('');
});

it('returns all node attributes', function () use ($makeNode) {
    $path = new SvgPath($makeNode('path', ['d' => 'M0 0', 'stroke-linecap' => 'round']));

    expect($path->getAttributes())
        ->toHaveKey('d')
        ->toHaveKey('stroke-linecap');
});

it('sets attributes on the node', function () use ($makeNode) {
    $path = new SvgPath($makeNode('path', ['d' => 'M0 0']));
    $path->setAttributes(['fill-rule' => 'evenodd', 'data-test' => 'yes']);

    expect($path->getAttributes())
        ->toHaveKey('fill-rule')
        ->toHaveKey('data-test');
});

it('returns the original dom node', function () use ($makeNode) {
    $node = $makeNode('path', ['d' => 'M0 0']);
    $path = new SvgPath($node);

    expect($path->getNode())->toBe($node);
});
