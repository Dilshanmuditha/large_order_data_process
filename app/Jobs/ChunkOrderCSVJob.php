<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ChunkOrderCSVJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    protected array $rows;

    /**
     * Create a new job instance.
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->rows as $row) {
            try {
                DB::transaction(function () use ($row) {
                [$orderId, $customer_name,$mail_domain, $customerEmail,$sku, $qty, $total] = $row;
                info("Processing order $orderId for $customerEmail");
                // Create customer
                $customer = Customer::firstOrCreate(['email' => $customerEmail]);

                // Create product
                $product = Product::firstOrCreate(
                    ['sku' => $sku],
                    ['name' => $sku,'stock' => 200]
                );

                // Create order
                $order = Order::firstOrCreate(
                    ['id' => $orderId],
                    [
                        'customer_id' => $customer->id,
                        'total_cents' => (int) ($total * 100),
                        'status' => 'pending',
                    ]
                );

                // Create order items
                if (!$order->items()->where('product_id', $product->id)->exists()) {
                    $order->items()->create([
                        'product_id' => $product->id,
                        'quantity' => (int) ($qty)
                    ]);
                }

                // Dispatch chained jobs
                $reserveJob = (new ReserveStockJob($order->id))
                    ->chain([
                        new SimulatePaymentJob($order->id),
                        new FinalizeOrderJob($order->id),
                    ]);

                dispatch($reserveJob);
            });
            } catch (\Throwable $e) {
                info("Failed row: ".json_encode($row)." Error: ".$e->getMessage());
                // throw $e;
            }
        }
    }

}
