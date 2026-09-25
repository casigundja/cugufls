<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ImportBatch;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReportExport;
use App\Models\Segment;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Segment $segment;

    private Branch $branch;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['active' => true, 'two_factor_confirmed_at' => now()]);
        $this->admin->assignRole('super-admin');
        $this->segment = Segment::where('slug', 'farmacia')->firstOrFail();
        $this->branch = Branch::firstOrFail();
        $category = (new Category)->forceFill(['segment_id' => $this->segment->id, 'name' => 'Cuidados', 'slug' => 'cuidados', 'active' => true, 'sort_order' => 0]);
        $category->save();
        $this->product = (new Product)->forceFill(['segment_id' => $this->segment->id, 'category_id' => $category->id, 'name' => 'Produto de teste', 'slug' => 'produto-teste', 'sku' => 'TEST-1', 'price' => '12.50', 'unit' => 'unit', 'purchase_mode' => 'whatsapp', 'active' => true]);
        $this->product->save();
    }

    private function payload(): array
    {
        return ['segment_id' => $this->segment->id, 'branch_id' => $this->branch->id, 'customer' => ['name' => 'Cliente de teste', 'phone' => '+244900000001'], 'items' => [['product_id' => $this->product->id, 'quantity' => '2']], 'idempotency_key' => (string) Str::uuid()];
    }

    private function adjust(string $quantity, string $expected = '0'): array
    {
        return ['product_id' => $this->product->id, 'branch_id' => $this->branch->id, 'quantity' => $quantity, 'expected_quantity' => $expected, 'operation_id' => (string) Str::uuid(), 'reason' => 'Contagem de teste'];
    }

    public function test_public_pages_and_api_are_available_without_private_fields(): void
    {
        foreach (['/', '/sobre', '/contacto', '/segmentos', '/novidades', '/farmacia', '/comercial', '/timbragem', '/lubrificantes', '/farmacia/luanda', '/farmacia/bailundo', '/produtos', '/produtos/produto-teste', '/login', '/forgot-password'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->getJson('/api/v1/products/produto-teste')->assertOk()->assertJsonPath('data.price', '12.50')->assertJsonMissingPath('data.cost_price');
        $this->get('/register')->assertNotFound();
    }

    public function test_all_administrative_pages_resolve_real_components(): void
    {
        $this->actingAs($this->admin);
        foreach (['dashboard' => 'Dashboard', 'stock/ajustar' => 'Operations', 'transferencias/criar' => 'Operations', 'pedidos/criar' => 'Operations', 'importacoes' => 'Imports', 'relatorios' => 'Reports', 'configuracoes' => 'Settings', 'acessos' => 'Accesses', 'seguranca' => 'Security', 'notificacoes' => 'Notifications'] as $path => $component) {
            $this->get('/admin/'.$path)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/'.$component));
        }
        foreach (config('catalog') as $slug => $definition) {
            $this->get('/admin/'.$slug)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Index'));
            if (! $definition['readonly']) {
                $this->get('/admin/'.$slug.'/criar')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Form'));
            }
        }
    }

    public function test_order_is_idempotent_uses_server_prices_and_preserves_snapshots(): void
    {
        $data = $this->payload();
        $this->postJson('/api/v1/orders', $data)->assertCreated()->assertJsonPath('data.quoted_total', '25.00');
        $this->postJson('/api/v1/orders', $data)->assertOk();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('customers', 1);
        $this->product->forceFill(['name' => 'Nome alterado', 'price' => '99.00'])->save();
        $this->assertDatabaseHas('order_items', ['name' => 'Produto de teste', 'unit_price' => '12.50']);
        $data['items'][0]['quantity'] = '3';
        $this->postJson('/api/v1/orders', $data)->assertConflict();
    }

    public function test_order_rejects_tampered_prices_catalog_only_and_fractional_units(): void
    {
        $data = $this->payload();
        $data['items'][0]['unit_price'] = '0.01';
        $this->postJson('/api/v1/orders', $data)->assertUnprocessable();
        unset($data['items'][0]['unit_price']);
        $data['items'][0]['quantity'] = '0.5';
        $this->postJson('/api/v1/orders', $data)->assertUnprocessable();
        $data['items'][0]['quantity'] = '1';
        $this->product->forceFill(['purchase_mode' => 'catalog_only'])->save();
        $this->postJson('/api/v1/orders', $data)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_internal_order_followup_and_valid_state_transitions(): void
    {
        $this->actingAs($this->admin)->post('/admin/pedidos', $this->payload())->assertRedirect();
        $order = Order::firstOrFail();
        $this->get('/admin/pedidos/'.$order->id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Operations'));
        $this->postJson('/admin/pedidos/'.$order->id.'/estado', ['status' => 'delivered'])->assertConflict();
        foreach (['confirmed', 'preparing', 'ready', 'delivered'] as $status) {
            $this->post('/admin/pedidos/'.$order->id.'/estado', ['status' => $status])->assertRedirect();
        }
        $this->patch('/admin/pedidos/'.$order->id, ['notes' => 'Cliente avisado', 'assigned_to' => $this->admin->id])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered', 'assigned_to' => $this->admin->id]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_stock_adjustment_replay_stale_balance_and_negative_stock(): void
    {
        $this->actingAs($this->admin);
        $data = $this->adjust('10');
        $this->post('/admin/stock/ajustes', $data)->assertRedirect();
        $this->post('/admin/stock/ajustes', $data)->assertRedirect();
        $this->assertDatabaseCount('stock_movements', 1);
        $this->postJson('/admin/stock/ajustes', $this->adjust('20'))->assertUnprocessable();
        $this->postJson('/admin/stock/ajustes', [...$this->adjust('11', '10'), 'type' => 'exit'])->assertUnprocessable();
        $this->post('/admin/stock/ajustes', [...$this->adjust('3', '10'), 'type' => 'exit'])->assertRedirect();
        $this->assertDatabaseHas('inventories', ['quantity' => '7']);
        $this->assertEquals('7', StockMovement::sum('quantity'));
    }

    public function test_transfer_dispatch_and_receive_are_idempotent_and_reconciled(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/stock/ajustes', $this->adjust('10'))->assertRedirect();
        $destination = Branch::where('id', '!=', $this->branch->id)->firstOrFail();
        $this->post('/admin/transferencias', ['segment_id' => $this->segment->id, 'source_branch_id' => $this->branch->id, 'destination_branch_id' => $destination->id, 'items' => [['product_id' => $this->product->id, 'quantity' => 4]]])->assertRedirect();
        $transfer = StockTransfer::firstOrFail();
        $this->get('/admin/transferencias/'.$transfer->id)->assertOk();
        foreach (['dispatch', 'dispatch', 'receive', 'receive'] as $action) {
            $this->post('/admin/transferencias/'.$transfer->id.'/'.$action)->assertRedirect();
        }
        $this->assertDatabaseHas('inventories', ['branch_id' => $this->branch->id, 'quantity' => '6']);
        $this->assertDatabaseHas('inventories', ['branch_id' => $destination->id, 'quantity' => '4']);
        $this->assertDatabaseCount('stock_movements', 3);
        $this->postJson('/admin/transferencias/'.$transfer->id.'/cancel')->assertConflict();
    }

    public function test_import_validates_duplicate_rows_and_processes_without_duplication(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin);
        $file = UploadedFile::fake()->createWithContent('catalog.csv', "sku,nome,categoria,preco\nNEW-1,Novo produto,cuidados,30.50\nNEW-1,Duplicado,cuidados,10.00\nBAD,Sem categoria,nao-existe,2\n");
        $this->post('/admin/importacoes', ['file' => $file, 'type' => 'catalog', 'segment_id' => $this->segment->id])->assertRedirect();
        $batch = ImportBatch::firstOrFail();
        $this->assertEquals(2, $batch->failed_rows);
        $this->get('/admin/importacoes/'.$batch->id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Imports'));
        $this->post('/admin/importacoes/'.$batch->id.'/confirmar')->assertRedirect();
        $this->assertDatabaseHas('products', ['sku' => 'NEW-1', 'active' => false, 'price' => '30.50']);
        app(ImportService::class)->process($batch->fresh());
        $this->assertEquals(1, Product::where('sku', 'NEW-1')->count());
        $this->get('/admin/importacoes/'.$batch->id.'/erros')->assertOk();
    }

    public function test_private_export_runs_and_downloads_only_with_authorized_signed_url(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin)->post('/admin/exportacoes', ['type' => 'products'])->assertRedirect();
        $export = ReportExport::firstOrFail();
        $this->assertSame('completed', $export->status);
        $url = URL::temporarySignedRoute('admin.exports.download', now()->addHour(), ['export' => $export->id]);
        $this->get($url)->assertOk()->assertDownload('relatorio-products.csv');
        $this->get('/admin/exportacoes/'.$export->id)->assertForbidden();
        $other = User::factory()->create(['active' => true, 'two_factor_confirmed_at' => now()]);
        $other->assignRole('super-admin');
        $this->actingAs($other)->get($url)->assertNotFound();
    }

    public function test_contact_inbox_updates_and_low_stock_notifications_are_deduplicated(): void
    {
        $this->postJson('/api/v1/contacts', ['name' => 'Cliente', 'phone' => '+244900000001', 'type' => 'general', 'message' => 'Gostaria de mais informações.'])->assertAccepted();
        $contact = Contact::firstOrFail();
        $this->actingAs($this->admin)->get('/admin/contactos/'.$contact->id)->assertOk();
        $this->patch('/admin/contactos/'.$contact->id, ['status' => 'in_progress', 'assigned_to' => $this->admin->id])->assertRedirect();
        $this->post('/admin/stock/ajustes', $this->adjust('10'));
        $inventory = Inventory::firstOrFail();
        $this->patch('/admin/stock/'.$inventory->id.'/limites', ['minimum_quantity' => 5, 'maximum_quantity' => 20])->assertRedirect();
        $this->post('/admin/stock/ajustes', $this->adjust('4', '10'))->assertRedirect();
        $this->post('/admin/stock/ajustes', $this->adjust('3', '4'))->assertRedirect();
        $this->assertEquals(1, $this->admin->notifications()->count());
        $notification = $this->admin->notifications()->first();
        $this->patch('/admin/notificacoes/'.$notification->id.'/ler')->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_scheduled_content_is_hidden_until_publication_and_escaped(): void
    {
        $this->actingAs($this->admin)->post('/admin/paginas', ['title' => 'Teste de conteúdo', 'slug' => 'teste-conteudo', 'content' => '<script>alert(1)</script>', 'status' => 'published', 'published_at' => now()->addDay()->toDateTimeString()])->assertRedirect();
        $this->get('/paginas/teste-conteudo')->assertNotFound();
        $this->travel(2)->days();
        $this->get('/paginas/teste-conteudo')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }
}
