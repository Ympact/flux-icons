<?php

namespace Ympact\FluxIcons\Types;

use RuntimeException;

/**
 * A metadata file of an icon package that is there but cannot be read, such as search terms in
 * JSON that does not parse: a package update that changed it, rather than one that never had it.
 */
class InvalidMetadata extends RuntimeException {}
