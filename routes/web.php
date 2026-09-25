<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\FrontendController;
use App\Http\Middleware\RequireTwoFactor;
use Illuminate\Support\Facades\Route;

Route::get('/', [FrontendController::class, 'home'])->name('home');
Route::get('/produtos', [FrontendController::class, 'products'])->name('products.index');
Route::get('/produtos/{slug}', [FrontendController::class, 'product'])->name('products.show');
Route::get('/novidades/{slug}', [FrontendController::class, 'post'])->name('posts.show');
foreach (['sobre', 'segmentos', 'contacto', 'novidades'] as $page) {
    Route::get('/'.$page, [FrontendController::class, 'page'])->defaults('page', $page)->name($page);
}
Route::get('/paginas/{page}', [FrontendController::class, 'page'])->name('pages.show');
Route::post('/contacto', [FrontendController::class, 'contact'])->middleware('throttle:contacts')->name('contacts.store');
Route::post('/pedidos', [FrontendController::class, 'order'])->middleware('throttle:orders')->name('orders.store');
Route::get('/pedidos/confirmacao', [FrontendController::class, 'confirmation'])->name('orders.confirmation');
Route::get('/segmentos/{segment}', [FrontendController::class, 'segment'])->name('segments.show');
Route::get('/segmentos/{segment}/{section}', [FrontendController::class, 'segment'])->whereIn('section', ['produtos', 'servicos'])->name('segments.section');
Route::get('/{segment}/{section?}', [FrontendController::class, 'segment'])
    ->whereIn('segment', ['farmacia', 'comercial', 'timbragem', 'lubrificantes'])
    ->whereIn('section', ['produtos', 'servicos', 'contacto', 'luanda', 'bailundo', 'negomil-erp', 'tipografia', 'encomendas', 'categorias'])->name('segment');
Route::get('/robots.txt', fn () => response("User-agent: *\nDisallow: /admin\nDisallow: /login\n", 200, ['Content-Type' => 'text/plain']));

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'active', RequireTwoFactor::class])->group(function () {
    Route::get('/pedidos/criar', [WorkflowController::class, 'createOrder']);
    Route::post('/pedidos', [WorkflowController::class, 'storeOrder']);
    Route::patch('/pedidos/{order}', [WorkflowController::class, 'updateOrder']);
    Route::get('/contactos/{contact}', [WorkflowController::class, 'contact'])->whereNumber('contact');
    Route::patch('/contactos/{contact}', [WorkflowController::class, 'updateContact']);
    Route::match(['patch', 'delete'], '/imagens/{image}', [WorkflowController::class, 'image']);
    Route::get('/importacoes/modelo/{type}', [WorkflowController::class, 'template']);
    Route::get('/importacoes/{batch}/erros', [WorkflowController::class, 'errors']);
    Route::post('/perfis', [WorkflowController::class, 'role']);
    Route::patch('/perfis/{role}', [WorkflowController::class, 'updateRole']);
    Route::get('/imagens', [WorkflowController::class, 'media']);
    Route::get('/notificacoes', [WorkflowController::class, 'notifications']);
    Route::patch('/notificacoes/{id}/ler', [WorkflowController::class, 'readNotification']);
    Route::post('/exportacoes', [ExportController::class, 'store']);
    Route::get('/exportacoes/{export}', [ExportController::class, 'download'])->middleware('signed')->name('exports.download');
    Route::redirect('/', '/admin/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/seguranca', [OperationsController::class, 'security'])->name('security');
    Route::post('/tokens', [OperationsController::class, 'token'])->middleware('password.confirm');
    Route::delete('/tokens/{id}', [OperationsController::class, 'revokeToken']);
    Route::match(['get', 'patch'], '/configuracoes', [OperationsController::class, 'settings'])->name('settings');
    Route::match(['get', 'post'], '/acessos', [OperationsController::class, 'accesses'])->name('accesses');
    Route::delete('/acessos/{user}', [OperationsController::class, 'revoke']);
    Route::post('/imagens', [OperationsController::class, 'upload']);
    Route::post('/produtos/{product}/imagens', [OperationsController::class, 'images']);
    Route::get('/relatorios', [ReportController::class, 'index'])->name('reports');
    Route::get('/importacoes', [ImportController::class, 'index'])->name('imports');
    Route::get('/importacoes/{id}', [ImportController::class, 'index'])->whereNumber('id');
    Route::post('/importacoes', [ImportController::class, 'store']);
    Route::post('/importacoes/{batch}/confirmar', [ImportController::class, 'confirm']);
    Route::get('/stock/ajustar', [OperationsController::class, 'workspace'])->defaults('kind', 'stock');
    Route::post('/stock/ajustes', [OperationsController::class, 'adjust']);
    Route::patch('/stock/{inventory}/limites', [OperationsController::class, 'limits']);
    Route::get('/transferencias/criar', [OperationsController::class, 'workspace'])->defaults('kind', 'transferencias');
    Route::post('/transferencias', [OperationsController::class, 'createTransfer']);
    Route::patch('/transferencias/{transfer}', [OperationsController::class, 'updateTransfer']);
    Route::get('/transferencias/{id}', [OperationsController::class, 'workspace'])->whereNumber('id')->defaults('kind', 'transferencias');
    Route::post('/transferencias/{transfer}/{action}', [OperationsController::class, 'transfer'])->whereIn('action', ['dispatch', 'receive', 'cancel']);
    Route::get('/pedidos/{id}', [OperationsController::class, 'workspace'])->whereNumber('id')->defaults('kind', 'pedidos');
    Route::post('/pedidos/{order}/estado', [OperationsController::class, 'orderStatus']);
    Route::get('/{resource}', [ResourceController::class, 'index'])->name('resource.index');
    Route::get('/{resource}/criar', [ResourceController::class, 'form'])->name('resource.create');
    Route::get('/{resource}/{id}/editar', [ResourceController::class, 'form'])->whereNumber('id')->name('resource.edit');
    Route::post('/{resource}', [ResourceController::class, 'save'])->name('resource.store');
    Route::patch('/{resource}/{id}', [ResourceController::class, 'save'])->whereNumber('id')->name('resource.update');
    Route::delete('/{resource}/{id}', [ResourceController::class, 'destroy'])->whereNumber('id')->name('resource.destroy');
});
