<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Branch;
use App\Models\Contact;
use App\Models\ImportBatch;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Segment;
use App\Models\Service;
use App\Models\User;
use App\Services\Access;
use App\Services\Audit;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkflowController extends Controller
{
    public function __construct(private Access $access) {}

    public function createOrder(Request $request): Response
    {
        abort_unless($this->access->any($request->user(), 'orders.create'), 403);

        return Inertia::render('Admin/Operations', ['kind' => 'pedidos', 'record' => null,
            'segments' => $this->access->scope($request->user(), Segment::query(), 'orders.create')->get(['id', 'name']),
            'branches' => $this->access->scope($request->user(), Branch::query(), 'orders.create')->with('segments:id')->get(['id', 'name']),
            'products' => $this->access->scope($request->user(), Product::query(), 'products.view')->where('active', true)->where('purchase_mode', '!=', 'catalog_only')->get(['id', 'name', 'segment_id']),
            'services' => $this->access->scope($request->user(), Service::query(), 'orders.create')->where('active', true)->get(['id', 'name', 'segment_id']),
        ]);
    }

    public function storeOrder(StoreOrderRequest $request, OrderService $service): RedirectResponse
    {
        $data = $request->validated();
        $this->access->authorize($request->user(), 'orders.create', (int) $data['segment_id'], (int) $data['branch_id']);
        $key = $request->validate(['idempotency_key' => ['required', 'uuid']])['idempotency_key'];
        unset($data['idempotency_key']);
        $order = $service->create($data, $key, 'admin');

        return redirect('/admin/pedidos/'.$order->id)->with('success', 'Pedido criado.');
    }

    public function contact(Request $request, Contact $contact): Response
    {
        $this->access->record($request->user(), $contact, 'contacts.view');

        return Inertia::render('Admin/Operations', ['kind' => 'contactos', 'record' => $contact,
            'capabilities' => ['update' => $this->access->allows($request->user(), 'contacts.update', $contact->segment_id, $contact->branch_id)],
            'assignees' => $this->assignees('contacts.view', $contact->segment_id, $contact->branch_id)]);
    }

    private function assignees(string $permission, ?int $segment, ?int $branch): array
    {
        return User::where('active', true)->get()->filter(fn ($user) => $this->access->allows($user, $permission, $segment, $branch))->map(fn ($user) => $user->only('id', 'name'))->values()->all();
    }

    public function updateContact(Request $request, Contact $contact): RedirectResponse
    {
        $this->access->record($request->user(), $contact, 'contacts.view');
        $this->access->authorize($request->user(), 'contacts.update', $contact->segment_id, $contact->branch_id);
        $data = $request->validate(['status' => ['required', Rule::in(['new', 'in_progress', 'closed'])], 'assigned_to' => ['nullable', Rule::in(array_column($this->assignees('contacts.view', $contact->segment_id, $contact->branch_id), 'id'))]]);
        DB::transaction(function () use ($contact, $data) {
            $old = $contact->toArray();
            $contact->forceFill($data)->save();
            Audit::record('contact.updated', $contact, $old, $contact->toArray());
        });

        return back()->with('success', 'Contacto atualizado.');
    }

    public function updateOrder(Request $request, Order $order): RedirectResponse
    {
        $this->access->authorize($request->user(), 'orders.update', $order->segment_id, $order->branch_id);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000'], 'assigned_to' => ['nullable', Rule::in(array_column($this->assignees('orders.view', $order->segment_id, $order->branch_id), 'id'))]]);
        DB::transaction(function () use ($order, $data) {
            $old = $order->toArray();
            $order->forceFill($data)->save();
            Audit::record('order.updated', $order, $old, $order->toArray());
        });

        return back()->with('success', 'Seguimento atualizado.');
    }

    public function image(Request $request, ProductImage $image): RedirectResponse
    {
        $this->access->authorize($request->user(), 'products.update', $image->product->segment_id, segmentWide: true);
        DB::transaction(function () use ($request, $image) {
            if ($request->isMethod('delete')) {
                Audit::record('image.deleted', $image);
                $image->delete();
            } else {
                $image->forceFill($request->validate(['alt' => ['nullable', 'string', 'max:255'], 'sort_order' => ['required', 'integer', 'min:0']]))->save();
                Audit::record('image.updated', $image);
            }
        });

        return back()->with('success', 'Galeria atualizada.');
    }

    public function template(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['catalog', 'inventory']), 404);
        abort_unless($this->access->any($request->user(), $type === 'catalog' ? 'products.import' : 'stock.import'), 403);

        return response()->streamDownload(function () use ($type) {
            echo $type === 'catalog' ? "sku,nome,categoria,preco\n" : "sku,stock\n";
        }, 'modelo-'.$type.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function errors(Request $request, ImportBatch $batch): StreamedResponse
    {
        abort_unless($batch->created_by === $request->user()->id, 404);
        $this->access->authorize($request->user(), $batch->type === 'catalog' ? 'products.import' : 'stock.import', $batch->segment_id, $batch->branch_id, $batch->type === 'catalog');

        return response()->streamDownload(function () use ($batch) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['linha', 'sku', 'erro'], ',', '"', '');
            foreach ($batch->importRows()->where('status', 'failed')->cursor() as $row) {
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : $v, [$row->row_number, $row->payload['sku'] ?? '', $row->error]), ',', '"', '');
            }
            fclose($out);
        }, 'importacao-'.$batch->id.'-erros.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store']);
    }

    public function role(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[a-z][a-z0-9-]+$/', 'unique:roles,name'], 'permissions' => ['required', 'array', 'min:1'], 'permissions.*' => ['string', Rule::in(Permission::where('guard_name', 'web')->pluck('name')->all())]]);
        DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            $role->syncPermissions($data['permissions']);
            Audit::record('role.created', $role);
        });

        return back()->with('success', 'Perfil criado.');
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        abort_if(in_array($role->name, ['super-admin', 'admin', 'manager', 'stock-manager', 'sales-manager', 'employee', 'content-manager']), 422, 'Os perfis internos são preservados. Crie um perfil personalizado.');
        $data = $request->validate(['permissions' => ['required', 'array', 'min:1'], 'permissions.*' => ['string', Rule::in(Permission::where('guard_name', 'web')->pluck('name')->all())]]);
        DB::transaction(function () use ($role, $data) {
            $role->syncPermissions($data['permissions']);
            Audit::record('role.updated', $role, [], $data);
        });

        return back()->with('success', 'Permissões atualizadas.');
    }

    public function media(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->access->any($user, 'products.view') || $this->access->any($user, 'content.view'), 403);
        $images = $this->access->scope($user, ProductImage::query(), 'products.view')->with('product:id,name,segment_id')->latest()->paginate(24);
        $images->getCollection()->transform(fn ($image) => [...$image->toArray(), 'can_update' => $this->access->allows($user, 'products.update', $image->product->segment_id, segmentWide: true)]);
        $content = [];
        foreach (config('catalog') as $slug => $definition) {
            if (! collect($definition['fields'])->contains('name', 'image')) {
                continue;
            }
            foreach ($this->access->scope($user, $definition['model']::query(), $definition['permission'].'.view')->whereNotNull('image')->limit(100)->get() as $record) {
                $content[] = ['id' => $slug.'-'.$record->id, 'name' => $record->name ?? $record->title ?? $record->question, 'path' => $record->image, 'url' => '/admin/'.$slug.'/'.$record->id.'/editar'];
            }
        }

        return Inertia::render('Admin/Media', ['images' => $images, 'content' => $content]);
    }

    public function notifications(Request $request): Response
    {
        return Inertia::render('Admin/Notifications', ['notifications' => $request->user()->notifications()->paginate(20)]);
    }

    public function readNotification(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return back();
    }
}
