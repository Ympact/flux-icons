<?php

namespace Ympact\FluxIcons;

use Ympact\FluxIcons\Exceptions\SvgNotFound;

class FluxIconsManager
{


    public function arrayMergeRecursive(...$arrays): array
    {
        $result = array_replace_recursive(...$arrays);

        // Remove null values
        $result = array_filter($result, function($value) {
            return !is_null($value);
        });

        return $result;
    }

    /**
     * @throws SvgNotFound
     */
    public function svg(string $name, $class = '', array $attributes = []): Svg
    {
        [$set, $name] = $this->splitSetAndName($name);

        try {
            return new Svg(
                $name,
                $this->contents($set, $name),
                $this->formatAttributes($set, $class, $attributes),
            );
        } catch (SvgNotFound $exception) {
            if (isset($this->sets[$set]['fallback']) && $this->sets[$set]['fallback'] !== '') {
                $name = $this->sets[$set]['fallback'];

                try {
                    return new Svg(
                        $name,
                        $this->contents($set, $name),
                        $this->formatAttributes($set, $class, $attributes),
                    );
                } catch (SvgNotFound $exception) {
                    //
                }
            }

            if ($this->config['fallback']) {
                return $this->svg($this->config['fallback'], $class, $attributes);
            }

            throw $exception;
        }
    }

}