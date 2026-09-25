<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ExportReport;
use App\Models\ReportExport;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function store(Request $request, ReportService $reports): RedirectResponse
    {
        $filters = $reports->filters($request);
        $reports->query($request->user(), $filters);
        $export = ReportExport::create(['user_id' => $request->user()->id, 'type' => $filters['type'], 'filters' => $filters, 'scope_hash' => $reports->fingerprint($request->user()), 'status' => 'pending', 'expires_at' => now()->addDay()]);
        ExportReport::dispatch($export->id)->afterCommit();

        return redirect('/admin/relatorios?'.http_build_query($filters))->with('success', 'Exportação enviada para processamento.');
    }

    public function download(Request $request, ReportExport $export, ReportService $reports): StreamedResponse
    {
        abort_unless($export->user_id === $request->user()->id, 404);
        abort_unless($export->status === 'completed' && $export->expires_at->isFuture(), 410);
        $reports->query($request->user(), $export->filters);
        abort_unless(hash_equals($export->scope_hash, $reports->fingerprint($request->user())), 403, 'O acesso mudou. Solicite uma nova exportação.');
        abort_unless(Storage::disk('local')->exists($export->path), 404);

        return Storage::disk('local')->download($export->path, 'relatorio-'.$export->type.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
