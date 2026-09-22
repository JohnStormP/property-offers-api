<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Services\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public Import $import,
    ) {
        $this->afterCommit();
    }

    public function handle(ImportService $importService): void
    {
        $importService->process($this->import);
    }

    public function failed(?Throwable $exception): void
    {
        Import::query()
            ->whereKey($this->import->id)
            ->whereIn('status', [
                ImportStatus::Pending->value,
                ImportStatus::Processing->value,
            ])
            ->update([
                'status' => ImportStatus::Failed,
                'error' => $exception?->getMessage() ?? 'Import processing failed.',
                'completed_at' => now(),
            ]);
    }
}
