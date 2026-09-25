<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public int $batchId) {}

    public function handle(ImportService $service): void
    {
        $batch = ImportBatch::findOrFail($this->batchId);
        if ($batch->status !== 'processing') {
            return;
        }
        $service->process($batch);
    }

    public function failed(?\Throwable $exception): void
    {
        ImportBatch::whereKey($this->batchId)->update(['status' => 'failed']);
    }
}
