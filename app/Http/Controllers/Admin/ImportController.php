<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImport;
use App\Models\Branch;
use App\Models\ImportBatch;
use App\Models\Segment;
use App\Services\Access;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function index(Request $request, Access $access, ?int $id = null)
    {
        abort_unless($access->any($request->user(), 'products.import') || $access->any($request->user(), 'stock.import'), 403);
        $query = ImportBatch::where('created_by', $request->user()->id);
        $batch = $id ? (clone $query)->with('importRows')->findOrFail($id) : null;

        return Inertia::render('Admin/Imports', ['batch' => $batch, 'batches' => $query->latest()->paginate(15),
            'segments' => $access->scope($request->user(), Segment::query(), 'segments.view')->get(['id', 'name']),
            'branches' => $access->scope($request->user(), Branch::query(), 'branches.view')->with('segments:id')->get(['id', 'name'])]);
    }

    public function store(Request $request, ImportService $service)
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'], 'type' => ['required', Rule::in(['catalog', 'inventory'])],
            'segment_id' => ['required', 'integer', 'exists:segments,id'], 'branch_id' => ['nullable', 'required_if:type,inventory', 'integer', 'exists:branches,id']]);
        $file = $data['file'];
        unset($data['file']);
        $batch = $service->preview($request->user(), $file, $data);

        return redirect('/admin/importacoes/'.$batch->id);
    }

    public function confirm(Request $request, ImportBatch $batch, Access $access)
    {
        abort_unless($batch->created_by === $request->user()->id, 404);
        $access->authorize($request->user(), $batch->type === 'inventory' ? 'stock.import' : 'products.import', $batch->segment_id, $batch->branch_id, $batch->type === 'catalog');
        DB::transaction(function () use ($batch) {
            $batch = ImportBatch::lockForUpdate()->findOrFail($batch->id);
            abort_unless($batch->status === 'validated', 409);
            abort_unless($batch->importRows()->where('status', 'pending')->exists(), 422, 'Não há linhas válidas para importar.');
            $batch->status = 'processing';
            $batch->save();
            ProcessImport::dispatch($batch->id)->afterCommit();
        });

        return back()->with('success', 'Importação enviada para processamento.');
    }
}
