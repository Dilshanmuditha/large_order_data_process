<?php

namespace App\Http\Controllers;

use App\Jobs\OrderRefundJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderRefundController extends Controller
{
    function orderRefundFully(Request $request, $orderId) {
        $validate = Validator::make($request->all(), [
            'email' => 'required|email:rfc,dns',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validate->errors(),
            ], 422);
        }

        return DB::transaction(function () use ($orderId, $request) {
            $orderToRefund = Order::find($orderId);
            $customer = Customer::where('email', $request->email)->first();

            if (!$customer) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Customer with provided email not found.',
                    'data' => null
                ], 404);
            }

            if (!$orderToRefund) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Order not found.',
                    'data' => null
                ], 404);
            }
            
            if ($customer->id != $orderToRefund->customer_id) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'The provided email address does not match the customer for this order.',
                    'data' => null
                ], 403);
            }
            
            if ($orderToRefund->status !== 'completed') {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Order status is ' . $orderToRefund->status . '. Only completed orders can be fully refunded.',
                    'data' => null
                ], 422);
            }

            // idempotency check to prevent duplicate requests
            $existingRefund = Refund::where('order_id', $orderId)
                                    ->whereIn('status', ['pending', 'processed'])
                                    ->exists();

            if ($existingRefund) {
                 return response()->json([
                    'status' => 'failed',
                    'message' => 'A full refund request for this order is already pending or has been processed.',
                    'data' => null
                ], 422);
            }

            $refundAmount = $orderToRefund->total_cents;

            $refund = Refund::create([
                'order_id' => $orderToRefund->id,
                'amount_cents' => $refundAmount,
                'reason' => 'Customer request (Full Refund)',
                'kpi_updated' => false,
                'status' => 'pending',
            ]);

            OrderRefundJob::dispatch($refund->id); 
            
            return response()->json([
                'status' => 'success',
                'message' => 'Full refund request submitted. It will be processed.',
                'refund_id' => $refund->id
            ], 202);
        });
    
    }
}
