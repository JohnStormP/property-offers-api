<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Import $import,
    ) {
        $this->afterCommit();
    }

    public function handle(ImportService $importService): void
    {
        $importService->process($this->import);
    }
}
