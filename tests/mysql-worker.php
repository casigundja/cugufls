<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Segment;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'cugufls_testing' || (int) config('database.connections.mysql.port') !== 33079) {
    fwrite(STDERR, 'O teste exige o MySQL temporário cugufls_testing na porta 33079.');
    exit(1);
}
try {
    $mode = $argv[1];
    if ($mode === 'prepare') {
        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);
        $user = User::factory()->create(['active' => true]);
        $user->assignRole('super-admin');
        $segment = Segment::where('slug', 'farmacia')->firstOrFail();
        $branches = Branch::all();
        $category = (new Category)->forceFill(['name' => 'Concorrência', 'slug' => 'concorrencia', 'segment_id' => $segment->id, 'active' => true, 'sort_order' => 0]);
        $category->save();
        $product = (new Product)->forceFill(['name' => 'Teste de concorrência', 'slug' => 'concorrencia', 'segment_id' => $segment->id, 'category_id' => $category->id, 'sku' => 'CONCURRENT-1', 'unit' => 'unit', 'price' => '1.00', 'purchase_mode' => 'whatsapp', 'active' => true]);
        $product->save();
        app(InventoryService::class)->adjust($user, ['product_id' => $product->id, 'branch_id' => $branches[0]->id, 'quantity' => '5', 'expected_quantity' => '0', 'operation_id' => (string) Str::uuid(), 'reason' => 'Saldo inicial de teste']);
        echo json_encode(['user' => $user->id, 'product' => $product->id, 'segment' => $segment->id, 'branch' => $branches[0]->id, 'destination' => $branches[1]->id]);
    } elseif ($mode === 'adjust') {
        $data = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
        $user = User::findOrFail($data['user']);
        app(InventoryService::class)->adjust($user, ['product_id' => $data['product'], 'branch_id' => $data['branch'], 'quantity' => $data['quantity'], 'expected_quantity' => $data['expected'], 'operation_id' => $data['operation'], 'type' => 'exit', 'reason' => 'Concorrência']);
        echo json_encode(['ok' => true]);
    } elseif ($mode === 'order') {
        $data = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
        $order = app(OrderService::class)->create(['segment_id' => $data['segment'], 'branch_id' => $data['branch'], 'customer' => ['name' => 'Teste concorrente', 'phone' => '+244900000001'], 'items' => [['product_id' => $data['product'], 'quantity' => '1']]], $data['operation']);
        echo json_encode(['ok' => true, 'id' => $order->id]);
    } elseif ($mode === 'state') {
        echo json_encode(['quantity' => Inventory::firstOrFail()->quantity, 'movements' => StockMovement::count(), 'orders' => Order::count(), 'customers' => Customer::count()]);
    }
} catch (ValidationException $exception) {
    echo json_encode(['ok' => false, 'validation' => $exception->errors()]);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage());
    exit(1);
}
