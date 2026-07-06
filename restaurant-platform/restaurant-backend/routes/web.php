<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\OrderExportController;
use App\Http\Controllers\Admin\OrderPrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Deliberately outside Filament's own `/admin` panel routing (registered
 * here in the plain `web` group instead) since a printable receipt must
 * render with no panel layout at all — see OrderPrintController's docblock.
 * `auth` here is the same `web` session guard the admin panel itself uses
 * (App\Providers\Filament\AdminPanelProvider takes no custom authGuard()),
 * so an admin's existing panel session already satisfies this middleware.
 */
Route::get('/admin/orders/{order}/print', OrderPrintController::class)
    ->middleware(['web', 'auth'])
    ->name('admin.orders.print');

// See App\Http\Controllers\Admin\OrderExportController's docblock. The
// extra `/csv` segment (not just `/admin/orders/export`) deliberately
// avoids colliding with OrderResource's `/admin/orders/{record}` view
// route, which is the exact same single-segment shape "export" would be.
Route::get('/admin/orders/export/csv', OrderExportController::class)
    ->middleware(['web', 'auth'])
    ->name('admin.orders.export');
