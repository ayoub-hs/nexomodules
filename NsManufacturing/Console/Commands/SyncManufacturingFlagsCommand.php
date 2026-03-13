<?php

namespace Modules\NsManufacturing\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use Modules\NsManufacturing\Services\ManufacturingProductFlagSyncService;

class SyncManufacturingFlagsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ns:manufacturing:sync-flags';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill unit manufacturing flags and sync product-level flags.';

    public function handle(ManufacturingProductFlagSyncService $syncService): int
    {
        $this->info('Backfilling NULL manufacturing flags on unit quantities...');

        DB::table('nexopos_products_unit_quantities')
            ->whereNull('is_manufactured')
            ->update(['is_manufactured' => 0]);

        DB::table('nexopos_products_unit_quantities')
            ->whereNull('is_raw_material')
            ->update(['is_raw_material' => 0]);

        $this->info('Syncing product-level manufacturing flags...');

        Product::query()
            ->select('id')
            ->orderBy('id')
            ->chunk(200, function ($products) use ($syncService) {
                foreach ($products as $product) {
                    $syncService->syncProductFlags($product->id);
                }
            });

        $this->info('Manufacturing flags sync complete.');

        return self::SUCCESS;
    }
}
