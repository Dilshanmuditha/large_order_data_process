<?php

namespace App\Jobs;

use App\Models\Refund;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class OrderRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $refundId;

    /**
     * Create a new job instance.
     */
    public function __construct($refundId)
    {
        $this->refundId = $refundId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::transaction(function () {
            $refund = Refund::lockForUpdate()->find($this->refundId);

            if (!$refund) {
                Log::warning("Refund ID {$this->refundId} not found.");
                return;
            }

            // check idempotency
            if ($refund->kpi_updated) {
                Log::info("Refund ID {$this->refundId} already processed. Skipping KPI update.");
                return;
            }

            $order = $refund->order;
            
            if (!$order || $order->status !== 'completed') {
                Log::warning("Refund ID {$this->refundId} order not found or not in 'completed' status.");
                
                $refund->status = 'failed';
                $refund->save();
                return;
            }

            $reverseAmount = -1 * (int) $refund->amount_cents;
            
            $dateKey = $order->processed_at ? Carbon::parse($order->processed_at)->toDateString() : Carbon::now()->toDateString();
            $revenueKey = "kpi:date:{$dateKey}:revenue";
            $leaderKey  = "leaderboard:customers";
            $customerMember = "customer:{$order->customer_id}";

            try { 
                Redis::pipeline(function ($pipe) use ($revenueKey, $leaderKey, $customerMember, $reverseAmount) {
                    $pipe->incrby($revenueKey, $reverseAmount);
                    $pipe->zincrby($leaderKey, $reverseAmount, $customerMember); 
                });

                foreach ($order->items as $item) {
                    $item->product->increment('stock', $item->quantity);
                }
                $order->status = 'fully_refunded';
                Log::info("Refund ID {$this->refundId}: Full refund, inventory restocked.");

                $refund->kpi_updated = true;
                $refund->status = 'processed';
                $refund->save();

                $order->save();
                
                Log::info("Refund ID {$this->refundId} processed successfully. Revenue reversed: {$reverseAmount} cents.");

            } catch (\Exception $e) {
                Log::error("Failed to process KPI reversal or stock update for Refund ID {$this->refundId}: " . $e->getMessage());
                $refund->status = 'failed';
                $refund->save();
                throw $e;
            }
        });
    }
}
