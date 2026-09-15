<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Support;

final class ManagerUrl
{
    /**
     * Absolute URL of a path under the manager (e.g. `emcp/tokens`).
     */
    public static function to(string $path): string
    {
        return self::managerBase() . ltrim(trim($path), '/');
    }

    /**
     * Absolute URL of a path under the site root (e.g. `mcp/content`).
     */
    public static function siteUrl(string $path): string
    {
        return self::siteBase() . ltrim(trim($path), '/');
    }

    public static function managerBase(): string
    {
        if (defined('EVO_MANAGER_URL') && is_string(EVO_MANAGER_URL) && EVO_MANAGER_URL !== '') {
            return rtrim(EVO_MANAGER_URL, '/') . '/';
        }

        $configured = function_exists('evo') ? (string)evo()->getConfig('site_manager_url') : '';
        if ($configured !== '') {
            return rtrim($configured, '/') . '/';
        }

        return self::siteBase() . 'manager/';
    }

    public static function siteBase(): string
    {
        if (defined('EVO_SITE_URL') && is_string(EVO_SITE_URL) && EVO_SITE_URL !== '') {
            return rtrim(EVO_SITE_URL, '/') . '/';
        }

        $configured = function_exists('evo') ? (string)evo()->getConfig('site_url') : '';

        return $configured !== '' ? rtrim($configured, '/') . '/' : '/';
    }
}
