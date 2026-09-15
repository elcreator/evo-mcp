<?php

/**
 * Token-authenticated MCP endpoint, independent of sApi.
 *
 * POST /{api_prefix}/{server}            JSON-RPC (initialize, tools/list, tools/call, ...)
 * POST /{api_prefix}/{server}/dispatch   async dispatch (needs emcp_dispatch permission)
 */

use EvolutionCMS\eMCP\Http\Controllers\McpDispatchController;
use EvolutionCMS\eMCP\Http\Controllers\McpManagerController;
use EvolutionCMS\eMCP\Support\TransportError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$prefix = trim((string)config('cms.settings.eMCP.route.api_prefix', 'mcp'), '/');

Route::middleware(['emcp.pat', 'emcp.impersonate', 'emcp.permission', 'emcp.scope', 'emcp.actor', 'emcp.rate'])
    ->prefix($prefix)
    ->group(function (): void {
        Route::get('/{server}', function (Request $request) {
            return TransportError::response($request, 405, 'method_not_allowed', 'Method not allowed');
        });

        Route::post('/{server}', McpManagerController::class);

        Route::post('/{server}/dispatch', McpDispatchController::class)
            ->middleware('emcp.permission:emcp_dispatch');
    });
