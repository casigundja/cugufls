<?php

namespace App\Jobs;

use App\Models\ReportExport as Export;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public int $exportId) {}

    public function handle(ReportService $reports): void
    {
        $export = Export::findOrFail($this->exportId);
        if ($export->status === 'completed' || $export->expires_at->isPast()) {
            return;
        }
        $user = User::findOrFail($export->user_id);
        abort_unless(hash_equals($export->scope_hash, $reports->fingerprint($user)), 403);
        $query = $reports->query($user, $export->filters);
        $export->update(['status' => 'processing']);
        $stream = fopen('php://temp/maxmemory:2097152', 'w+');
        try {
            fwrite($stream, "\xEF\xBB\xBF");
            $header = false;
            foreach ($query->cursor() as $record) {
                $values = $reports->row($user, $record, $export->filters);
                if (! $header) {
                    fputcsv($stream, array_keys($values), ',', '"', '');
                    $header = true;
                }
                fputcsv($stream, array_map(fn ($value) => preg_match('/^[=+\-@\t\r]/', (string) $value) ? "'".$value : $value, array_values($values)), ',', '"', '');
            }
            if (! $header) {
                fputcsv($stream, ['Sem resultados para os filtros selecionados'], ',', '"', '');
            }
            abort_unless(hash_equals($export->scope_hash, $reports->fingerprint($user->fresh())), 403);
            rewind($stream);
            $path = 'exports/'.Str::uuid().'.csv';
            if (! Storage::disk('local')->put($path, $stream)) {
                throw new \RuntimeException('Falha ao guardar exportação.');
            }
            $export->update(['status' => 'completed', 'path' => $path, 'error' => null]);
        } finally {
            fclose($stream);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Export::whereKey($this->exportId)->update(['status' => 'failed', 'error' => 'Não foi possível gerar o ficheiro. Reveja as permissões e solicite novamente.']);
    }
}
