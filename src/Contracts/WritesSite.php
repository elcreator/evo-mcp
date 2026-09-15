<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Contracts;

/**
 * Marker for tools that change the site (content, elements, files, settings...).
 *
 * eMCP refuses such tools unless `security.enable_write_tools` is on and the token carries
 * the write scope, exactly as it does for its own evo.write.* tools.
 */
interface WritesSite
{
}
