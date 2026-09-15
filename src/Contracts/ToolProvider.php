<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Contracts;

/**
 * Lets another extra contribute MCP tools to an eMCP server.
 *
 * Register an implementation from your service provider's boot():
 *
 *     if (class_exists(\EvolutionCMS\eMCP\Services\ToolRegistry::class)) {
 *         app(\EvolutionCMS\eMCP\Services\ToolRegistry::class)->register(new MyToolProvider());
 *     }
 *
 * Tools are ordinary Laravel\Mcp\Server\Tool classes. A tool that changes the site should
 * implement WritesSite so eMCP applies its write gates (enable_write_tools, mcp:write scope).
 * Permission checks inside a tool see the impersonated manager user: evo()->hasPermission()
 * and friends answer for the token owner.
 */
interface ToolProvider
{
    /**
     * Handle of the eMCP server the tools belong to (`content` for the default server).
     */
    public function server(): string;

    /**
     * @return array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    public function tools(): array;
}
