<?php

namespace Ympact\FluxIcons\Tests\Fixtures\Vendors;

/**
 * A vendor that reads its icons' search terms from tags.json in its package.
 */
class AlphaTagged extends Alpha
{
    /**
     * @return array<array-key, list<string>>
     */
    public function tags(string $packageRoot): array
    {
        return array_map(fn ($terms) => $this->terms($terms), $this->readJson("{$packageRoot}/tags.json"));
    }
}
