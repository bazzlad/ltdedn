<?php

namespace App\Console\Commands;

use App\Enums\ProductEditionStatus;
use App\Models\ProductEditionTransfer;
use App\Notifications\ProductEditionTransferExpired;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireTransfersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transfers:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending transfers that have passed their expiration time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredTransfers = ProductEditionTransfer::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;

        foreach ($expiredTransfers as $transfer) {
            DB::transaction(function () use ($transfer) {
                $transfer->lockForUpdate(); // lock row

                if ($transfer->status !== 'pending') {
                    return;
                }

                $edition = $transfer->productEdition;
                $edition->lockForUpdate();

                $edition->update([
                    'status' => ProductEditionStatus::Sold,
                ]);

                $transfer->update([
                    'status' => 'expired',
                    'completed_at' => now(),
                ]);

                $transfer->sender->notify(new ProductEditionTransferExpired($transfer));
                $transfer->recipient->notify(new ProductEditionTransferExpired($transfer));
            });
            $count++;
        }

        $this->info("Expired {$count} transfers.");
    }
}
