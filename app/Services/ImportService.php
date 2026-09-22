<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportService
{
    private const  STALE_PROCESSING_MINUTES = 15;

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

        try {
            $import = DB::transaction(function () use ($supplier, $data) {
                return Import::query()->create([
                    'supplier_id' => $supplier->id,
                    'external_import_id' => $data['external_import_id'],
                    'sent_at' => $data['sent_at'],
                    'status' => ImportStatus::Pending,
                    'payload' => $data['offers'],
                    'total_offers' => count($data['offers']),
                    'processed_offers' => 0,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return Import::query()
                ->where('supplier_id', $supplier->id)
                ->where('external_import_id', $data['external_import_id'])
                ->firstOrFail();
        }

        ProcessImportJob::dispatch($import);

        return $import;
    }

    public function process(Import $import): void
    {
        try {
            DB::transaction(function () use ($import) {
                $locked = Import::query()
                    ->whereKey($import->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null || ! $this->canProcess($locked)) {
                    return;
                }

                $locked->update([
                    'status' => ImportStatus::Processing,
                    'processed_offers' => 0,
                    'error' => null,
                    'completed_at' => null,
                ]);

                foreach ($locked->payload as $offerData) {
                    $property = $this->upsertProperty($offerData['property']);
                    $this->upsertOffer($locked, $property, $offerData);
                    $locked->increment('processed_offers');
                }

                $locked->update([
                    'status' => ImportStatus::Completed,
                    'completed_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            Import::query()->whereKey($import->id)->update([
                'status' => ImportStatus::Failed,
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            report($exception);
        }
    }

    private function canProcess(Import $import): bool
    {
        return match ($import->status) {
            ImportStatus::Pending => true,
            ImportStatus::Processing => $import->updated_at->lte(
                now()->subMinutes(self::STALE_PROCESSING_MINUTES)
            ),
            default => false,
        };
    }

    /**
     * @param  array{code: string, name: string, city: string}  $propertyData
     */
    private function upsertProperty(array $propertyData): Property
    {
        $values = [
            'name' => $propertyData['name'],
            'city' => $propertyData['city'],
        ];

        try {
            return Property::query()->updateOrCreate(
                ['code' => $propertyData['code']],
                $values,
            );
        } catch (UniqueConstraintViolationException) {
            $property = Property::query()
                ->where('code', $propertyData['code'])
                ->firstOrFail();

            $property->update($values);

            return $property;
        }
    }

    /**
     * @param  array<string, mixed>  $offerData
     */
    private function upsertOffer(Import $import, Property $property, array $offerData): void
    {
        $values = [
            'property_id' => $property->id,
            'import_id' => $import->id,
            'check_in' => $offerData['check_in'],
            'check_out' => $offerData['check_out'],
            'max_guests' => $offerData['max_guests'],
            'price' => $offerData['price'],
            'currency' => $offerData['currency'],
            'available_units' => $offerData['available_units'],
            'expires_at' => $offerData['expires_at'],
        ];

        try {
            Offer::query()->updateOrCreate(
                [
                    'supplier_id' => $import->supplier_id,
                    'external_id' => $offerData['external_id'],
                ],
                $values,
            );
            return;
        } catch (UniqueConstraintViolationException) {
            $offer = Offer::query()
                ->where('supplier_id', $import->supplier_id)
                ->where('external_id', $offerData['external_id'])
                ->firstOrFail();

            $offer->update($values);

            return;
        }
    }
}
