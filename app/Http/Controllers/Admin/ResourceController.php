<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Segment;
use App\Models\User;
use App\Services\Access;
use App\Services\Audit;
use App\Services\CatalogRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    public function __construct(private Access $access, private CatalogRegistry $registry) {}

    public function index(Request $request, string $resource): Response
    {
        $definition = $this->registry->get($resource);
        abort_unless($this->access->any($request->user(), $definition['permission'].'.view'), 403);
        $query = $this->access->scope($request->user(), $definition['model']::query(), $definition['permission'].'.view');
        $column = in_array($definition['table'], ['products', 'brands', 'categories', 'branches', 'segments', 'services', 'customers', 'users', 'team_members', 'testimonials']) ? 'name' : (in_array($definition['table'], ['posts', 'pages', 'banners']) ? 'title' : null);
        $column ??= ['contacts' => 'name', 'faqs' => 'question', 'orders' => 'public_id', 'stock_transfers' => 'reference', 'activity_logs' => 'action'][$definition['table']] ?? null;
        if ($request->filled('q') && in_array($resource, ['stock', 'movimentos'])) {
            $query->whereHas('product', fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'));
        }
        if ($request->filled('segment_id') && in_array($resource, ['stock', 'movimentos'])) {
            $query->whereHas('product', fn ($q) => $q->where('segment_id', $request->integer('segment_id')));
        }
        if ($request->filled('branch_id') && $resource === 'transferencias') {
            $query->where(fn ($q) => $q->where('source_branch_id', $request->integer('branch_id'))->orWhere('destination_branch_id', $request->integer('branch_id')));
        }
        if ($column && $request->filled('q')) {
            $query->where($column, 'like', '%'.$request->string('q').'%');
        }
        if (in_array($definition['table'], ['products', 'categories', 'services', 'orders', 'stock_transfers', 'posts', 'banners', 'contacts']) && $request->filled('segment_id')) {
            $query->where('segment_id', $request->integer('segment_id'));
        }
        if (in_array($definition['table'], ['inventories', 'stock_movements', 'orders']) && $request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }
        if ($resource === 'stock') {
            $query->with(['product:id,name,sku,segment_id', 'branch:id,name']);
            if ($request->input('alert') === 'low') {
                $query->whereColumn('quantity', '<=', 'minimum_quantity');
            }
        }
        if ($resource === 'pedidos') {
            $query->with(['customer:id,name', 'branch:id,name']);
        }
        if ($resource === 'movimentos') {
            $query->with(['product:id,name', 'branch:id,name']);
        }
        if ($resource === 'transferencias') {
            $query->with(['sourceBranch:id,name', 'destinationBranch:id,name']);
        }
        if ($resource === 'stock' && $request->input('alert') === 'zero') {
            $query->where('quantity', 0);
        }
        if ($resource === 'stock' && $request->input('alert') === 'uncounted') {
            $query->whereNull('last_counted_at');
        }
        if ($request->filled('status') && in_array($resource, ['pedidos', 'transferencias', 'contactos', 'noticias', 'paginas'])) {
            $query->where('status', $request->string('status'));
        }
        $records = $query->latest('id')->paginate(20)->withQueryString();
        $records->getCollection()->transform(fn ($model) => $this->serialize($request, $model));

        return Inertia::render('Admin/Index', [
            'resource' => $resource, 'definition' => $definition, 'records' => $records,
            'filters' => $request->only('q', 'segment_id', 'branch_id', 'alert', 'status'),
            'canCreate' => (! $definition['readonly'] && (in_array($definition['table'], ['users', 'segments', 'branches', 'brands', 'pages', 'customers']) ? $this->access->global($request->user(), $definition['permission'].'.create') : $this->access->any($request->user(), $definition['permission'].'.create', true))) || ($resource === 'pedidos' && $this->access->any($request->user(), 'orders.create')),
            'canOperate' => $this->access->any($request->user(), $resource === 'transferencias' ? 'stock.transfer' : 'stock.update'),
            'segments' => $this->access->scope($request->user(), Segment::query(), 'segments.view')->get(['id', 'name']),
            'branches' => $this->access->scope($request->user(), Branch::query(), 'branches.view')->get(['id', 'name']),
        ]);
    }

    private function serialize(Request $request, Model $model): array
    {
        $data = $model->toArray();
        if ($model instanceof Product && ! $this->access->allows($request->user(), 'products.cost.view', $model->segment_id, segmentWide: true)) {
            unset($data['cost_price']);
        }
        unset($data['idempotency_key'], $data['request_hash']);
        $definition = collect(config('catalog'))->firstWhere('table', $model->getTable());
        if ($definition) {
            $global = in_array($model->getTable(), ['users', 'segments', 'branches', 'brands', 'pages', 'customers']);
            foreach (['update', 'delete'] as $action) {
                $data['can_'.$action] = ! $definition['readonly'] && ($global || $model->segment_id === null
                    ? $this->access->global($request->user(), $definition['permission'].'.'.$action)
                    : $this->access->allows($request->user(), $definition['permission'].'.'.$action, $model->segment_id, segmentWide: true));
            }
        }

        return $data;
    }

    public function form(Request $request, string $resource, ?int $id = null): Response
    {
        $definition = $this->registry->get($resource);
        abort_if($definition['readonly'], 405);
        $model = $id ? $definition['model']::findOrFail($id) : new $definition['model'];
        if ($model instanceof Product && $model->exists) {
            $model->load('productImages');
        }
        $this->authorizeWrite($request, $definition, $model, $id ? 'update' : 'create');
        $options = [];
        foreach ($definition['fields'] as $field) {
            if (! empty($field['target'])) {
                $target = collect(config('catalog'))->firstWhere('table', $field['target']);
                if ($target) {
                    $options[$field['name']] = $this->access->scope($request->user(), $target['model']::query(), $target['permission'].'.view')->limit(500)->get()->map(fn ($row) => ['value' => $row->id, 'label' => $row->name, 'segment_id' => $row->segment_id]);
                }
            }
        }
        $data = $this->serialize($request, $model);
        if ($model instanceof User) {
            unset($data['password']);
        }
        if ($model instanceof Branch && $model->exists) {
            $data['segment_ids'] = $model->segments()->pluck('segments.id');
        }
        if ($model instanceof Product && ! $this->access->allows($request->user(), 'products.cost.view', $model->segment_id, segmentWide: true)) {
            $definition['fields'] = array_values(array_filter($definition['fields'], fn ($f) => $f['name'] !== 'cost_price'));
        }

        return Inertia::render('Admin/Form', ['resource' => $resource, 'definition' => $definition, 'record' => $data, 'options' => $options]);
    }

    private function authorizeWrite(Request $request, array $definition, Model $model, string $action): void
    {
        $permission = $definition['permission'].'.'.$action;
        if ($model->exists) {
            $this->access->record($request->user(), $model, $definition['permission'].'.view');
        }
        $segment = $model->segment_id ?? $request->input('segment_id');
        $global = in_array($definition['table'], ['users', 'segments', 'branches', 'brands', 'pages', 'customers']);
        if ($global || $segment === null) {
            if (! $global && ! $model->exists && $request->isMethod('get')) {
                abort_unless($this->access->any($request->user(), $permission, true), 403);

                return;
            }
            abort_unless($this->access->global($request->user(), $permission), 403);
        } else {
            $this->access->authorize($request->user(), $permission, (int) $segment, segmentWide: true);
        }
        if ($model instanceof User && $model->exists && $model->hasAnyRole(['admin', 'super-admin'])) {
            abort_unless($request->user()->hasRole('super-admin'), 403);
        }
    }

    public function save(Request $request, string $resource, ?int $id = null): RedirectResponse
    {
        $definition = $this->registry->get($resource);
        abort_if($definition['readonly'], 405);
        $model = $id ? $definition['model']::findOrFail($id) : new $definition['model'];
        $this->authorizeWrite($request, $definition, $model, $id ? 'update' : 'create');
        $rules = [];
        foreach ($definition['fields'] as $field) {
            $name = $field['name'];
            $type = $field['type'];
            $rules[$name] = [! empty($field['required']) ? 'required' : 'nullable'];
            if ($type === 'checkbox') {
                $rules[$name] = ['sometimes', 'boolean'];
            } elseif ($type === 'multiselect') {
                $rules[$name] = ['required', 'array', 'min:1'];
                $rules[$name.'.*'] = ['integer', 'distinct', 'exists:segments,id'];
            } elseif ($type === 'number') {
                $rules[$name][] = 'numeric';
                $rules[$name][] = in_array($name, ['latitude', 'longitude']) ? 'between:-180,180' : 'min:0';
                $rules[$name][] = 'max:999999999999';
            } elseif ($type === 'select' && ! empty($field['target'])) {
                $rules[$name][] = 'integer';
                $rules[$name][] = 'exists:'.$field['target'].',id';
            } elseif (! empty($field['options'])) {
                $rules[$name][] = Rule::in(array_keys($field['options']));
            } elseif ($type === 'datetime-local') {
                $rules[$name][] = 'date';
            } elseif ($type === 'json') {
                $rules[$name][] = 'array';
            } else {
                $rules[$name][] = 'string';
                $rules[$name][] = 'max:'.($type === 'textarea' ? '30000' : '255');
            }
            if (in_array($name, ['slug', 'sku', 'email']) && ! ($name === 'email' && $resource !== 'utilizadores')) {
                $unique = Rule::unique($definition['table'], $name)->ignore($model->getKey());
                if ($resource === 'categorias' && $name === 'slug') {
                    $unique->where('segment_id', $request->integer('segment_id'));
                }
                $rules[$name][] = $unique;
            }
            if ($name === 'email') {
                $rules[$name][] = 'email';
            }
            if ($name === 'slug') {
                $rules[$name][] = 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
            }
            if ($name === 'password') {
                $rules[$name] = [$id ? 'nullable' : 'required', 'string', 'min:12', 'max:128'];
            }
        }
        $data = $request->validate($rules);
        if (isset($data['segment_id'])) {
            $this->access->authorize($request->user(), $definition['permission'].'.'.($id ? 'update' : 'create'), (int) $data['segment_id'], segmentWide: true);
        }
        if ($model->exists && array_key_exists('segment_id', $data) && (string) $model->segment_id !== (string) $data['segment_id']) {
            throw ValidationException::withMessages(['segment_id' => 'O segmento não pode mudar. Crie um novo cadastro.']);
        }
        if ($model instanceof Product) {
            abort_unless(Category::whereKey($data['category_id'])->where('segment_id', $data['segment_id'])->exists(), 422, 'Categoria de outro segmento.');
            if (($data['sale_price'] ?? null) !== null && (($data['price'] ?? null) === null || bccomp((string) $data['sale_price'], (string) $data['price'], 2) >= 0)) {
                throw ValidationException::withMessages(['sale_price' => 'O preço promocional deve ser inferior ao preço normal.']);
            }
            if (array_key_exists('cost_price', $data)) {
                $this->access->authorize($request->user(), 'products.cost.view', (int) $data['segment_id'], segmentWide: true);
            }
        }
        if ($model instanceof Category && ! empty($data['parent_id'])) {
            $parent = Category::where('segment_id', $data['segment_id'])->findOrFail($data['parent_id']);
            $visited = [];
            while ($parent) {
                if ($parent->id === $model->id || isset($visited[$parent->id])) {
                    throw ValidationException::withMessages(['parent_id' => 'Esta categoria criaria um ciclo.']);
                }
                $visited[$parent->id] = true;
                $parent = $parent->parent;
            }
        }
        if ($model instanceof User && $model->id === $request->user()->id && ! ($data['active'] ?? true)) {
            abort(422, 'Não pode desativar a própria conta.');
        }
        if ($definition['permission'] === 'content' && (($data['status'] ?? null) === 'published' || ($data['active'] ?? false))) {
            if (empty($data['segment_id'])) {
                abort_unless($this->access->global($request->user(), 'content.publish'), 403);
            } else {
                $this->access->authorize($request->user(), 'content.publish', (int) $data['segment_id'], segmentWide: true);
            }
            if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
                $data['published_at'] = now();
            }
        }
        if (! empty($data['ends_at']) && ! empty($data['starts_at']) && strtotime($data['ends_at']) < strtotime($data['starts_at'])) {
            throw ValidationException::withMessages(['ends_at' => 'A data final deve ser posterior ao início.']);
        }
        if ($model instanceof User && $model->hasRole('super-admin') && ! ($data['active'] ?? true) && User::role('super-admin')->where('active', true)->count() <= 1) {
            abort(422, 'Preserve pelo menos um super-admin ativo.');
        }
        if (! empty($data['target_url'])) {
            abort_unless(str_starts_with($data['target_url'], '/') && ! str_starts_with($data['target_url'], '//') && ! preg_match('/[\\\\\x00-\x20]/', $data['target_url']), 422, 'Use um caminho interno para o botão.');
        }
        if (! empty($data['image'])) {
            abort_unless(str_starts_with($data['image'], 'media/') && ! str_contains($data['image'], '..'), 422, 'Imagem inválida.');
        }
        if (isset($data['branch_id'], $data['segment_id'])) {
            abort_unless(Branch::findOrFail($data['branch_id'])->segments()->whereKey($data['segment_id'])->exists(), 422);
        }
        DB::transaction(function () use ($request, $model, $definition, $data) {
            $old = $model->toArray();
            foreach ($data as $key => $value) {
                if ($key === 'segment_ids' || ($key === 'password' && ! $value)) {
                    continue;
                }
                $model->$key = $value;
            }
            if ($definition['table'] === 'posts' && ! $model->exists) {
                $model->author_id = $request->user()->id;
            }
            if ($definition['table'] === 'pages') {
                $model->updated_by = $request->user()->id;
            }
            $model->save();
            if ($model instanceof User && ! $model->active) {
                $model->tokens()->delete();
                DB::table('sessions')->where('user_id', $model->id)->delete();
            }
            if ($model instanceof Branch) {
                $removed = $model->segments()->pluck('segments.id')->diff($data['segment_ids'] ?? []);
                if ($removed->isNotEmpty() && ($model->inventories()->exists() || $model->orders()->exists())) {
                    throw ValidationException::withMessages(['segment_ids' => 'Não remova segmentos de filiais com histórico.']);
                }
                $model->segments()->sync($data['segment_ids']);
            }
            Audit::record('record.saved', $model, $old, $model->toArray());
        });

        return redirect('/admin/'.$resource)->with('success', 'Alterações guardadas.');
    }

    public function destroy(Request $request, string $resource, int $id): RedirectResponse
    {
        $definition = $this->registry->get($resource);
        abort_if($definition['readonly'] || in_array($resource, ['utilizadores', 'clientes']), 405);
        $model = $definition['model']::findOrFail($id);
        $this->authorizeWrite($request, $definition, $model, 'delete');
        try {
            DB::transaction(function () use ($model) {
                Audit::record('record.deleted', $model);
                $model->delete();
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['record' => 'Este registo tem histórico. Desative-o em vez de eliminar.']);
        }

        return back()->with('success', 'Registo eliminado.');
    }
}
