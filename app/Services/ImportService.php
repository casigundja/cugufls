<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;

class ImportService
{
    public function preview(User $user, UploadedFile $file, array $context): ImportBatch
    {
        app(Access::class)->authorize($user, $context['type'] === 'inventory' ? 'stock.import' : 'products.import', (int) $context['segment_id'], isset($context['branch_id']) ? (int) $context['branch_id'] : null, $context['type'] === 'catalog');
        $path = $file->store('imports', 'local');
        $rows = [];
        $absolute = Storage::disk('local')->path($path);
        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            $reader = new Reader;
            $reader->open($absolute);
            try {
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        $rows[] = array_map(fn ($value) => is_scalar($value) ? trim((string) $value) : '', $row->toArray());
                        abort_if(count($rows) > 10001, 422, 'Limite de 10.000 linhas.');
                    }
                    break;
                }
            } finally {
                $reader->close();
            }
        } else {
            $stream = fopen($absolute, 'r');
            try {
                while (($line = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                    if ($line === [null]) {
                        continue;
                    }
                    $rows[] = array_map(fn ($v) => trim((string) $v), $line);
                    abort_if(count($rows) > 10001, 422, 'Limite de 10.000 linhas.');
                }
            } finally {
                fclose($stream);
            }
        }
        abort_if(count($rows) < 2, 422, 'O ficheiro não contém dados.');
        $headers = array_map(fn ($v) => trim($v, "\xEF\xBB\xBF \t\n\r"), array_shift($rows));
        $required = $context['type'] === 'catalog' ? ['sku', 'nome', 'categoria', 'preco'] : ['sku', 'stock'];
        abort_if(array_diff($required, $headers) !== [], 422, 'Cabeçalhos necessários: '.implode(', ', $required));
        abort_if(count($headers) !== count(array_unique($headers)), 422, 'Cabeçalhos repetidos.');

        return DB::transaction(function () use ($rows, $headers, $context, $path, $user) {
            $batch = new ImportBatch;
            $batch->forceFill([...$context, 'created_by' => $user->id, 'file_path' => $path, 'status' => 'validated', 'total_rows' => count($rows), 'processed_rows' => 0, 'failed_rows' => 0])->save();
            $seen = [];
            foreach ($rows as $index => $values) {
                $payload = count($values) === count($headers) ? array_combine($headers, $values) : [];
                $rules = ['sku' => ['required', 'string', 'max:64']];
                $rules += $context['type'] === 'catalog'
                    ? ['nome' => ['required', 'string', 'max:255'], 'categoria' => ['required', 'string'], 'preco' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999']]
                    : ['stock' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999']];
                $validator = Validator::make($payload, $rules);
                $error = $validator->fails() ? implode(' ', $validator->errors()->all()) : null;
                if (! $error) {
                    $sku = mb_strtolower($payload['sku']);
                    if (isset($seen[$sku])) {
                        $error = 'SKU repetido no ficheiro.';
                    }
                    $seen[$sku] = true;
                    $product = Product::where('sku', $payload['sku'])->first();
                    if ($product && $product->segment_id !== (int) $context['segment_id']) {
                        $error = 'SKU de outro segmento.';
                    }
                    if ($context['type'] === 'catalog') {
                        $category = Category::where('segment_id', $context['segment_id'])->where('slug', $payload['categoria'])->first();
                        if (! $category) {
                            $error = 'Categoria não encontrada no segmento.';
                        }
                        $payload['category_id'] = $category?->id;
                    } else {
                        if (! $product) {
                            $error = 'SKU não encontrado.';
                        }
                        $payload['product_id'] = $product?->id;
                        $payload['expected_quantity'] = $product ? (Inventory::where('product_id', $product->id)->where('branch_id', $context['branch_id'])->value('quantity') ?? '0.000') : '0.000';
                    }
                }
                (new ImportRow)->forceFill(['import_batch_id' => $batch->id, 'row_number' => $index + 2, 'operation_id' => (string) Str::uuid(), 'status' => $error ? 'failed' : 'pending', 'payload' => $payload, 'error' => $error])->save();
            }
            $batch->failed_rows = $batch->importRows()->where('status', 'failed')->count();
            $batch->save();

            return $batch;
        });
    }

    public function process(ImportBatch $batch): void
    {
        $user = $batch->creator;
        $previousUser = auth()->user();
        app(Access::class)->authorize($user, $batch->type === 'inventory' ? 'stock.import' : 'products.import', $batch->segment_id, $batch->branch_id, $batch->type === 'catalog');
        auth()->setUser($user);
        try {
            foreach ($batch->importRows()->where('status', 'pending')->orderBy('id')->cursor() as $row) {
                try {
                    DB::transaction(function () use ($batch, $row, $user) {
                        $row = ImportRow::lockForUpdate()->findOrFail($row->id);
                        if ($row->status !== 'pending') {
                            return;
                        }
                        app(Access::class)->authorize($user->fresh(), $batch->type === 'inventory' ? 'stock.import' : 'products.import', $batch->segment_id, $batch->branch_id, $batch->type === 'catalog');
                        $payload = $row->payload;
                        if ($batch->type === 'inventory') {
                            app(InventoryService::class)->adjust($user, ['product_id' => $payload['product_id'], 'branch_id' => $batch->branch_id, 'quantity' => $payload['stock'], 'expected_quantity' => $payload['expected_quantity'], 'operation_id' => $row->operation_id, 'reason' => 'Importação #'.$batch->id.' linha '.$row->row_number]);
                        } else {
                            abort_unless(Category::whereKey($payload['category_id'])->where('segment_id', $batch->segment_id)->exists(), 422);
                            $product = Product::where('sku', $payload['sku'])->lockForUpdate()->first() ?? new Product;
                            abort_if($product->exists && $product->segment_id !== $batch->segment_id, 403);
                            $product->forceFill(['sku' => $payload['sku'], 'name' => $payload['nome'], 'segment_id' => $batch->segment_id, 'category_id' => $payload['category_id'], 'price' => $payload['preco'] === '' ? null : $payload['preco']]);
                            if (! $product->exists) {
                                $product->slug = Str::slug($payload['nome'].'-'.$payload['sku']);
                                $product->unit = 'unit';
                                $product->purchase_mode = 'request_availability';
                                $product->active = false;
                            }
                            $product->save();
                            Audit::record('product.imported', $product);
                        }
                        $row->status = 'succeeded';
                        $row->save();
                    });
                } catch (\Throwable $exception) {
                    $row->refresh();
                    $row->status = 'failed';
                    $row->error = $exception instanceof ValidationException ? implode(' ', $exception->validator->errors()->all()) : 'Não foi possível processar esta linha. Verifique os dados e as permissões.';
                    $row->save();
                    report($exception);
                }
            }
            $batch->processed_rows = $batch->importRows()->where('status', 'succeeded')->count();
            $batch->failed_rows = $batch->importRows()->where('status', 'failed')->count();
            $batch->status = 'completed';
            $batch->completed_at = now();
            $batch->save();
        } finally {
            if ($previousUser) {
                auth()->setUser($previousUser);
            } else {
                auth()->forgetUser();
            }
        }
    }
}
