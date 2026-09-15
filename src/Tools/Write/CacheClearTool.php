<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Write;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.write.cache.clear')]
#[Description('Clear the site cache (same as "Refresh site" in the manager). Requires empty_cache.')]
class CacheClearTool extends BaseWriteTool
{
    public function handle(Request $request): ResponseFactory
    {
        $this->requirePermission('empty_cache');

        evo()->clearCache('full');
        $this->logManagerAction(26, 'Refreshed site via MCP', '-', '-');

        return $this->respondItem(['cleared' => true]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
