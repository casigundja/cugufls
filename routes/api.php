<?php

use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\FrontendController;
use App\Models\Order;
use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:public-api')->group(function () {
    Route::get('/products', [CatalogController::class, 'products']);
    Route::get('/products/{slug}', [CatalogController::class, 'product']);
    Route::get('/{resource}/{slug?}', [CatalogController::class, 'index'])->whereIn('resource', ['segments', 'branches', 'categories', 'services', 'posts']);
    Route::post('/orders', [FrontendController::class, 'order'])->middleware('throttle:orders');
    Route::post('/contacts', [FrontendController::class, 'contact'])->middleware('throttle:contacts');
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/me', fn (Request $request) => ['data' => $request->user()->only('id', 'name', 'email')]);
        Route::get('/orders', function (Request $request, Access $access) {
            abort_unless($request->user()->tokenCan('read'), 403);

            return $access->scope($request->user(), Order::query(), 'orders.view')->select('id', 'public_id', 'status', 'branch_id', 'segment_id', 'total', 'currency', 'created_at')->latest()->paginate(20);
        });
        Route::delete('/tokens/current', function (Request $request) {
            $request->user()->currentAccessToken()?->delete();

            return response()->noContent();
        });
    });
});
