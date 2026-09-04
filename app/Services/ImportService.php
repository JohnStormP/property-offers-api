<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportService
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     * @throws Throwable
     */
    public function queue(array $data): Import
    {
        $supplier = Supplier::query()
            ->where('code', $data['supplier'])
            ->firstOrFail();

        return DB::transaction(function () use ($supplier, $data) {
            $existing = Import::query()
                ->where('supplier_id', $supplier->id)
                ->where('external_import_id', $data['external_import_id'])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $import = Import::query()->create([
                'supplier_id' => $supplier->id,
                'external_import_id' => $data['external_import_id'],
                'sent_at' => $data['sent_at'],
                'status' => ImportStatus::Pending,
                'payload' => $data['offers'],
                'total_offers' => count($data['offers']),
                'processed_offers' => 0,
            ]);

            ProcessImportJob::dispatch($import);

            return $import;
        });
    }

    public function process(Import $import): void
    {
        $claimed = Import::query()
            ->whereKey($import->id)
            ->where('status', ImportStatus::Pending)
            ->update(['status' => ImportStatus::Processing]);

        if ($claimed === 0) {
            return;
        }

        $import->refresh();

        try {
            DB::transaction(function () use ($import) {
                foreach ($import->payload as $offerData) {
                    $property = Property::query()->updateOrCreate(
                        ['code' => $offerData['property']['code']],
                        [
                            'name' => $offerData['property']['name'],
                            'city' => $offerData['property']['city'],
                        ],
                    );

                    Offer::query()->updateOrCreate(
                        [
                            'supplier_id' => $import->supplier_id,
                            'external_id' => $offerData['external_id'],
                        ],
                        [
                            'property_id' => $property->id,
                            'import_id' => $import->id,
                            'check_in' => $offerData['check_in'],
                            'check_out' => $offerData['check_out'],
                            'max_guests' => $offerData['max_guests'],
                            'price' => $offerData['price'],
                            'currency' => $offerData['currency'],
                            'available_units' => $offerData['available_units'],
                            'expires_at' => $offerData['expires_at'],
                        ],
                    );

                    $import->increment('processed_offers');
                }

                $import->update([
                    'status' => ImportStatus::Completed,
                    'completed_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $import->update([
                'status' => ImportStatus::Failed,
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            report($exception);
        }
    }
}
