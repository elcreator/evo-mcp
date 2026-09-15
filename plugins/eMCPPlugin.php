<?php

use Illuminate\Support\Facades\Event;

if (!function_exists('eMCP_settings')) {
    /**
     * @return array<string, mixed>
     */
    function eMCP_settings(): array
    {
        $settings = config('cms.settings.eMCP', []);

        return is_array($settings) ? $settings : [];
    }
}

// "MCP tokens" entry under Tools for every user who may use MCP at all.
Event::listen('evolution.OnManagerMenuPrerender', function ($params) {
    if (!(bool)config('cms.settings.eMCP.enable', true) || !(bool)config('cms.settings.eMCP.tokens.self_service', true)) {
        return serialize($params['menu']);
    }

    $permission = (string)config('cms.settings.eMCP.acl.permission', 'emcp');
    if (!function_exists('evo') || !evo()->hasPermission($permission, 'mgr')) {
        return serialize($params['menu']);
    }

    $prefix = trim((string)config('cms.settings.eMCP.route.manager_prefix', 'emcp'), '/');
    $title = __('eMCP::global.menu_tokens');
    $icon = function_exists('svg') ? svg('tabler-key')->toHtml() : '<i class="fa fa-key"></i>';

    $params['menu']['emcp_tokens'] = [
        'emcp_tokens',
        'tools',
        $icon . $title,
        \EvolutionCMS\eMCP\Support\ManagerUrl::to($prefix . '/tokens'),
        $title,
        '',
        '',
        'main',
        0,
        8,
    ];

    return serialize($params['menu']);
});
