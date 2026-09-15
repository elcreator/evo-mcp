<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Services;

use EvolutionCMS\eMCP\Contracts\ToolProvider;
use EvolutionCMS\eMCP\Contracts\WritesSite;
use Laravel\Mcp\Server\Attributes\Name;
use ReflectionClass;

/**
 * Tools contributed by other extras, keyed by server handle.
 *
 * Two ways in: a ToolProvider registered at runtime, or class names listed under
 * `mcp.servers[].extra_tools` in config. The registry also answers "is this a write tool?"
 * for names it knows, so the policy layer needs no naming convention from extras.
 */
final class ToolRegistry
{
    /** @var array<string, array<int, class-string>> */
    private array $tools = [];

    /** @var array<string, class-string>|null tool name => class, built lazily */
    private ?array $names = null;

    public function register(ToolProvider $provider): void
    {
        $handle = trim($provider->server());
        if ($handle === '') {
            return;
        }

        foreach ($provider->tools() as $class) {
            $this->add($handle, $class);
        }
    }

    /**
     * @param  class-string  $class
     */
    public function add(string $handle, string $class): void
    {
        if (!class_exists($class)) {
            return;
        }

        $this->tools[$handle] ??= [];
        if (!in_array($class, $this->tools[$handle], true)) {
            $this->tools[$handle][] = $class;
            $this->names = null;
        }
    }

    /**
     * @return array<int, class-string>
     */
    public function toolsFor(string $handle): array
    {
        $configured = [];
        foreach ((array)config('mcp.servers', []) as $server) {
            if (!is_array($server) || trim((string)($server['handle'] ?? '')) !== $handle) {
                continue;
            }
            foreach ((array)($server['extra_tools'] ?? []) as $class) {
                if (is_string($class) && class_exists($class)) {
                    $configured[] = $class;
                }
            }
        }

        return array_values(array_unique(array_merge($this->tools[$handle] ?? [], $configured)));
    }

    /**
     * True when the tool is known to change the site: eMCP's own evo.write.* tools, or a
     * contributed tool implementing WritesSite.
     */
    public function isWriteTool(string $toolName): bool
    {
        $toolName = trim($toolName);
        if (str_starts_with($toolName, 'evo.write.')) {
            return true;
        }

        $class = $this->classFor($toolName);

        return $class !== null && is_subclass_of($class, WritesSite::class);
    }

    /**
     * @return class-string|null
     */
    public function classFor(string $toolName): ?string
    {
        if ($this->names === null) {
            $this->names = [];
            $handles = array_unique(array_merge(array_keys($this->tools), $this->configuredHandles()));
            foreach ($handles as $handle) {
                foreach ($this->toolsFor($handle) as $class) {
                    $name = $this->nameOf($class);
                    if ($name !== null) {
                        $this->names[$name] = $class;
                    }
                }
            }
        }

        return $this->names[$toolName] ?? null;
    }

    /**
     * @return array<int, string>
     */
    private function configuredHandles(): array
    {
        $handles = [];
        foreach ((array)config('mcp.servers', []) as $server) {
            if (is_array($server) && !empty($server['extra_tools'])) {
                $handles[] = trim((string)($server['handle'] ?? ''));
            }
        }

        return array_filter($handles);
    }

    /**
     * @param  class-string  $class
     */
    private function nameOf(string $class): ?string
    {
        $attributes = (new ReflectionClass($class))->getAttributes(Name::class);
        if ($attributes === []) {
            return null;
        }

        $name = $attributes[0]->newInstance()->value;

        return is_string($name) && $name !== '' ? $name : null;
    }
}
