<?php

use EvolutionCMS\eMCP\Http\Controllers\McpManagerController;
use EvolutionCMS\eMCP\Http\Controllers\TokensPageController;
use EvolutionCMS\eMCP\Http\Controllers\McpDispatchController;
use EvolutionCMS\eMCP\Services\ServerRegistry;
use EvolutionCMS\eMCP\Support\TransportError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$prefix = (string)config('cms.settings.eMCP.route.manager_prefix', 'emcp');
$prefix = trim($prefix, '/');

// Self-service token page: same "mgr" session + CSRF stack as the rest of the manager.
if ((bool)config('cms.settings.eMCP.tokens.self_service', true)) {
    Route::middleware(['mgr', 'emcp.permission'])
        ->prefix($prefix)
        ->group(function (): void {
            Route::get('/tokens', [TokensPageController::class, 'index']);
            Route::post('/tokens', [TokensPageController::class, 'store']);
            Route::post('/tokens/{id}/revoke', [TokensPageController::class, 'revoke'])->whereNumber('id');
        });
}

Route::middleware(['mgr', 'emcp.permission', 'emcp.actor', 'emcp.rate'])
    ->prefix($prefix)
    ->group(function (): void {
        Route::get('/{server}', function (Request $request) {
            return TransportError::response($request, 405, 'method_not_allowed', 'Method not allowed');
        });

        Route::post('/{server}', McpManagerController::class);

        Route::post('/{server}/dispatch', McpDispatchController::class)
            ->middleware('emcp.permission:emcp_dispatch');

        Route::get('/servers', function (ServerRegistry $registry) {
            return response()->json([
                'items' => $registry->handles(),
            ]);
        });
    });
