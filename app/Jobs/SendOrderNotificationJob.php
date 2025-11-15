<?php

namespace App\Jobs;

use App\Mail\OrderCanceledMail;
use App\Mail\OrderCompletedMail;
use App\Models\OrderNotification;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

use function Laravel\Prompts\info;

class SendOrderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    protected array $orderData;
    /**
     * Create a new job instance.
     */
    public function __construct(array $orderData)
    {
        $this->onQueue('notifications');
        $this->orderData = $orderData;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $status = $this->orderData['status'];
        $orderId = $this->orderData['order_id'];
        $customerId = $this->orderData['customer_id'];
        $email = $this->orderData['customer_email'];
        
        $notificationType = 'email';

        if ($status === 'completed') {
            Mail::to($email)->send(new OrderCompletedMail($this->orderData));
            $finalMessage = "Order {$orderId} successfully completed and KPIs updated. Email dispatched to {$email}.";
        } else {
            Mail::to($email)->send(new OrderCanceledMail($this->orderData));
            $finalMessage = "Order {$orderId} failed or was cancelled. Stock rollback complete. Email dispatched to {$email}.";
        }

        try {
            OrderNotification::create([
                'order_id'    => $orderId,
                'customer_id' => $customerId,
                'status'      => $status,
                'total_cents' => $this->orderData['total_cents'],
                'type'        => $notificationType,
                'message'     => $finalMessage,
            ]);
        } catch (\Exception $e) {
            info("Failed to save notification history for Order ID {$orderId}: " . $e->getMessage());
        }
    }
}
