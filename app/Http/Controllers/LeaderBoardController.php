<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class LeaderBoardController extends Controller
{
    public function leaderboard(int $limit = 10)
    {
        $leaderKey = 'leaderboard:customers';
        $items = Redis::zrevrange($leaderKey, 0, $limit - 1, ['WITHSCORES' => true]);

        $result = [];
        foreach ($items as $member => $score) {
            $id = explode(':', $member)[1];
            $customer = Customer::select('id', 'email')->find($id);
            $result[] = [
                'customer' => $customer,
                'revenue' => (int)$score / 100,
            ];
        }

        return response()->json($result);
    }
}
