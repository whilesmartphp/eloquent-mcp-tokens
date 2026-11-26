<?php

use Illuminate\Support\Facades\Route;

$controller = config('mcp-tokens.controller');
$actions = config('mcp-tokens.actions', []);
$write = config('mcp-tokens.write_middleware', []);

if (in_array('index', $actions, true)) {
    Route::get('mcp-tokens', [$controller, 'index'])->name('mcp-tokens.index');
}
if (in_array('store', $actions, true)) {
    Route::post('mcp-tokens', [$controller, 'store'])->middleware($write)->name('mcp-tokens.store');
}
if (in_array('destroy', $actions, true)) {
    Route::delete('mcp-tokens/{mcpToken}', [$controller, 'destroy'])->middleware($write)->name('mcp-tokens.destroy');
}
if (in_array('activity', $actions, true)) {
    Route::get('mcp-tokens/{mcpToken}/activity', [$controller, 'activity'])->name('mcp-tokens.activity');
}
if (in_array('current', $actions, true)) {
    Route::get('mcp-token', [$controller, 'current'])->name('mcp-tokens.current');
}
