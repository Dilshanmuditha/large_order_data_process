<?php

namespace App\Jobs;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class FinalizeOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $orderId;
    /**
     * Create a new job instance.
     */
    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = Order::find($this->orderId);
        if (!$order) return;
        
        // Update Redis KPIs and leaderboard atomically with a pipeline
        $dateKey = Carbon::now()->toDateString(); // e.g. "2025-11-14"
        $revenueKey = "kpi:date:{$dateKey}:revenue";
        $ordersKey  = "kpi:date:{$dateKey}:orders";
        $leaderKey  = "leaderboard:customers";
        $customerMember = "customer:{$order->customer_id}";
        $amount = (int) $order->total_cents; // integer cents
        
        $notificationStatus = null; 

        if ($order->status === 'payment_success') {
            $order->status = 'completed';
            $order->processed_at = now();

            $notificationStatus = 'completed';

            Redis::pipeline(function ($pipe) use ($revenueKey, $ordersKey, $leaderKey, $customerMember, $amount) {
                $pipe->incrby($revenueKey, $amount);
                $pipe->incr($ordersKey); 
                $pipe->zincrby($leaderKey, $amount, $customerMember); 
            });

        } else {
            foreach ($order->items as $item) {
                $item->product->increment('stock', $item->quantity);
            }
            
            $order->status = 'cancelled';
            $notificationStatus = 'cancelled';
        }

        $order->save();

        if ($notificationStatus) {
            SendOrderNotificationJob::dispatch([
                'order_id'       => $order->id,
                'customer_id'    => $order->customer_id,
                'customer_email' => $order->customer->email,
                'status'         => $notificationStatus,
                'total_cents'    => $amount,
            ]);
        }
    }
}
