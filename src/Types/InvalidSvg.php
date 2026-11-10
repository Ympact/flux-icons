<?php

namespace Ympact\FluxIcons\Types;

use RuntimeException;

/**
 * A source file that cannot be read as an SVG document, so no icon can be built from it.
 */
class InvalidSvg extends RuntimeException {}
