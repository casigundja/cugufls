<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Segment;
use App\Models\Setting;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\UserAccess;
use App\Services\Access;
use App\Services\Audit;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class OperationsController extends Controller
{
    public function __construct(private Access $access) {}

    public function workspace(Request $request)
    {
        $kind = $request->route('kind');
        $id = $request->route('id');
        $user = $request->user();
        abort_unless($this->access->any($user, ['stock' => 'stock.update', 'transferencias' => $id ? 'transfers.view' : 'stock.transfer', 'pedidos' => 'orders.view'][$kind]), 403);
        $record = null;
        if ($kind === 'pedidos' && $id) {
            $record = $this->access->scope($user, Order::query(), 'orders.view')->with(['orderItems', 'orderStatusHistories', 'customer', 'branch'])->findOrFail($id);
        }
        if ($kind === 'transferencias' && $id) {
            $record = $this->access->scope($user, StockTransfer::query(), 'transfers.view')->with(['stockTransferItems.product', 'sourceBranch', 'destinationBranch'])->findOrFail($id);
        }

        return Inertia::render('Admin/Operations', [
            'kind' => $kind, 'record' => $record,
            'products' => $this->access->scope($user, Product::query(), 'products.view')->get(['id', 'name', 'segment_id']),
            'branches' => $this->access->scope($user, Branch::query(), 'branches.view')->with('segments:id')->get(['id', 'name']),
            'destinations' => Branch::where('active', true)->with('segments:id')->whereHas('segments', fn ($q) => $q->whereIn('segments.id', $this->access->scope($user, Segment::query(), 'segments.view')->select('segments.id')))->get(['id', 'name']),
            'segments' => $this->access->scope($user, Segment::query(), 'segments.view')->get(['id', 'name']),
            'inventories' => $this->access->scope($user, Inventory::query(), 'stock.view')->get(['id', 'product_id', 'branch_id', 'quantity', 'minimum_quantity', 'maximum_quantity']),
            'transitions' => $record instanceof Order ? array_values(array_filter(OrderService::TRANSITIONS[$record->status], fn ($status) => $this->access->allows($user, $status === 'cancelled' ? 'orders.cancel' : 'orders.update', $record->segment_id, $record->branch_id) && ($status !== 'delivered' || $this->access->allows($user, 'orders.deliver', $record->segment_id, $record->branch_id)))) : [],
            'capabilities' => $record instanceof StockTransfer ? [
                'dispatch' => $this->access->allows($user, 'transfers.dispatch', $record->segment_id, $record->source_branch_id),
                'receive' => $this->access->allows($user, 'transfers.receive', $record->segment_id, $record->destination_branch_id),
                'cancel' => $this->access->allows($user, 'stock.transfer', $record->segment_id, $record->source_branch_id),
            ] : ['update' => $record && $this->access->allows($user, 'orders.update', $record->segment_id, $record->branch_id)],
            'assignees' => $record instanceof Order ? User::where('active', true)->get()->filter(fn ($u) => $this->access->allows($u, 'orders.view', $record->segment_id, $record->branch_id))->map(fn ($u) => $u->only('id', 'name'))->values() : [],
            'receipt' => $record instanceof Order ? app(OrderService::class)->receipt($record) : null,
        ]);
    }

    public function adjust(Request $request, InventoryService $service)
    {
        $data = $request->validate(['product_id' => ['required', 'integer'], 'branch_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999'], 'expected_quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0'],
            'operation_id' => ['required', 'uuid'], 'reason' => ['required', 'string', 'min:3', 'max:1000'], 'type' => ['sometimes', Rule::in(['adjustment', 'entry', 'exit', 'return'])]]);
        $service->adjust($request->user(), $data);

        return back()->with('success', 'Stock atualizado com histórico.');
    }

    public function limits(Request $request, Inventory $inventory)
    {
        $this->access->authorize($request->user(), 'stock.update', $inventory->product->segment_id, $inventory->branch_id);
        $data = $request->validate(['minimum_quantity' => ['required', 'numeric', 'min:0', 'max:999999999'], 'maximum_quantity' => ['nullable', 'numeric', 'gte:minimum_quantity', 'max:999999999']]);
        DB::transaction(function () use ($inventory, $data) {
            $old = $inventory->toArray();
            $inventory->forceFill($data)->save();
            Audit::record('stock.limits', $inventory, $old, $inventory->toArray());
        });

        return back()->with('success', 'Limites atualizados.');
    }

    public function createTransfer(Request $request, InventoryService $service)
    {
        $data = $request->validate(['segment_id' => ['required', 'integer'], 'source_branch_id' => ['required', 'integer'],
            'destination_branch_id' => ['required', 'integer', 'different:source_branch_id'], 'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999']]);
        $transfer = $service->createTransfer($request->user(), $data);

        return redirect('/admin/transferencias/'.$transfer->id)->with('success', 'Transferência criada como rascunho.');
    }

    public function transfer(Request $request, StockTransfer $transfer, string $action, InventoryService $service)
    {
        abort_unless(in_array($action, ['dispatch', 'receive', 'cancel']), 404);
        $service->transfer($request->user(), $transfer, $action);

        return back()->with('success', 'Transferência atualizada.');
    }

    public function updateTransfer(Request $request, StockTransfer $transfer, InventoryService $service)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000'], 'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*.product_id' => ['required', 'integer', 'distinct'], 'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999']]);
        $service->updateDraft($request->user(), $transfer, $data);

        return back()->with('success', 'Rascunho atualizado.');
    }

    public function orderStatus(Request $request, Order $order, OrderService $service)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(OrderService::TRANSITIONS))], 'reason' => ['nullable', 'string', 'max:1000']]);
        $service->transition($request->user(), $order, $data['status'], $data['reason'] ?? null);

        return back()->with('success', 'Estado atualizado.');
    }

    public function upload(Request $request)
    {
        abort_unless($this->access->any($request->user(), 'content.update', true) || $this->access->any($request->user(), 'products.update', true), 403);
        $request->validate(['image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000']]);
        $path = $request->file('image')->store('media', 'public');

        return response()->json(['path' => $path, 'url' => Storage::disk('public')->url($path)]);
    }

    public function images(Request $request, Product $product)
    {
        $this->access->authorize($request->user(), 'products.update', $product->segment_id, segmentWide: true);
        $data = $request->validate(['path' => ['required', 'string', 'starts_with:media/', 'not_regex:/\.\./'], 'alt' => ['nullable', 'string', 'max:255'], 'sort_order' => ['required', 'integer', 'min:0']]);
        abort_unless(Storage::disk('public')->exists($data['path']), 422);
        (new ProductImage)->forceFill([...$data, 'product_id' => $product->id, 'disk' => 'public'])->save();

        return back()->with('success', 'Imagem adicionada.');
    }

    public function settings(Request $request)
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $keys = ['hero_title', 'hero_description', 'contact_phone', 'contact_email', 'contact_whatsapp', 'about', 'facebook', 'instagram'];
        if ($request->isMethod('patch')) {
            $data = $request->validate(array_fill_keys($keys, ['nullable', 'string', 'max:3000']));
            $request->validate(['contact_email' => ['nullable', 'email'], 'facebook' => ['nullable', 'url:https'], 'instagram' => ['nullable', 'url:https']]);
            DB::transaction(function () use ($data, $request) {
                foreach ($data as $key => $value) {
                    $setting = Setting::where('key', $key)->first() ?? new Setting;
                    $setting->forceFill(['key' => $key, 'value' => $value, 'type' => 'string', 'updated_by' => $request->user()->id])->save();
                    Audit::record('settings.updated', $setting);
                }
            });

            return back()->with('success', 'Website atualizado.');
        }

        return Inertia::render('Admin/Settings', ['values' => Setting::whereIn('key', $keys)->pluck('value', 'key')]);
    }

    public function accesses(Request $request)
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        if ($request->isMethod('post')) {
            $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'role_id' => ['required', 'integer', 'exists:roles,id'],
                'segment_id' => ['nullable', 'integer', 'exists:segments,id'], 'branch_id' => ['nullable', 'integer', 'exists:branches,id']]);
            DB::transaction(function () use ($data) {
                $user = User::lockForUpdate()->findOrFail($data['user_id']);
                $role = Role::findOrFail($data['role_id']);
                if (in_array($role->name, ['admin', 'super-admin'])) {
                    $user->assignRole($role);
                } else {
                    abort_unless(! empty($data['segment_id']), 422, 'Selecione um segmento.');
                    if (! empty($data['branch_id'])) {
                        abort_unless(Branch::findOrFail($data['branch_id'])->segments()->whereKey($data['segment_id'])->exists(), 422);
                    }
                    $query = UserAccess::where('user_id', $user->id)->where('role_id', $role->id)->where('segment_id', $data['segment_id'])->where('branch_id', $data['branch_id'] ?? null);
                    if (! $query->exists()) {
                        (new UserAccess)->forceFill($data)->save();
                    }
                }
                Audit::record('user.access.granted', $user, [], $data);
            });

            return back()->with('success', 'Acesso atribuído.');
        }

        return Inertia::render('Admin/Accesses', ['users' => User::with('roles:id,name', 'userAccesses.role', 'userAccesses.segment', 'userAccesses.branch')->get(['id', 'name', 'email']),
            'roles' => Role::with('permissions:id,name')->get(), 'segments' => Segment::get(['id', 'name']), 'branches' => Branch::with('segments:id')->get(['id', 'name']),
            'permissions' => Permission::where('guard_name', 'web')->orderBy('name')->pluck('name')]);
    }

    public function revoke(Request $request, User $user)
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate(['access_id' => ['nullable', 'integer'], 'role_id' => ['nullable', 'integer']]);
        DB::transaction(function () use ($user, $data) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            if (! empty($data['access_id'])) {
                $user->userAccesses()->whereKey($data['access_id'])->delete();
            } elseif (! empty($data['role_id'])) {
                $role = Role::findOrFail($data['role_id']);
                abort_if($role->name === 'super-admin' && User::role('super-admin')->where('active', true)->count() <= 1, 422, 'Preserve o último super-admin.');
                $user->removeRole($role);
            }
            $user->tokens()->delete();
            Audit::record('user.access.revoked', $user);
        });

        return back()->with('success', 'Acesso revogado.');
    }

    public function security(Request $request)
    {
        return Inertia::render('Admin/Security', ['enabled' => (bool) $request->user()->two_factor_confirmed_at, 'tokens' => $request->user()->tokens()->get(['id', 'name', 'last_used_at', 'expires_at']), 'canManageTokens' => $request->user()->hasRole('super-admin'), 'required' => $request->user()->hasAnyRole(['admin', 'super-admin'])]);
    }

    public function token(Request $request)
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $token = $request->user()->createToken($data['name'], ['read'], now()->addDays(30));
        if ($request->expectsJson()) {
            return response()->json(['token' => $token->plainTextToken, 'tokens' => $request->user()->tokens()->get(['id', 'name', 'expires_at'])]);
        }

        return back()->with('token', $token->plainTextToken);
    }

    public function revokeToken(Request $request, int $id)
    {
        $request->user()->tokens()->whereKey($id)->delete();
        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back()->with('success', 'Token revogado.');
    }
}
