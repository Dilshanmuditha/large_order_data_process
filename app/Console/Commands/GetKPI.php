<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class GetKPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kpis:show {date?} {--top=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show daily KPIs and leaderboard';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date') ?? Carbon::now()->toDateString();
        $revenueKey = "kpi:date:{$date}:revenue";
        $ordersKey  = "kpi:date:{$date}:orders";
        $leaderKey  = "leaderboard:customers";

        $revenueCents = (int) Redis::get($revenueKey);
        $orders = (int) Redis::get($ordersKey);
        $aovCents = $orders ? intdiv($revenueCents, $orders) : 0; // integer division

        $this->info("KPIs for {$date}");
        $this->info("Revenue: " . number_format($revenueCents / 100, 2));
        $this->info("Orders : {$orders}");
        $this->info("AOV    : " . number_format($aovCents / 100, 2));

        // Top customers
        $top = Redis::zrevrange($leaderKey, 0, (int)$this->option('top') - 1, ['WITHSCORES' => true]);
        if (empty($top)) {
            $this->info("Leaderboard is empty.");
            return 0;
        }

        $rows = [];
        foreach ($top as $member => $score) {
            // member like "customer:3"
            $customerId = explode(':', $member)[1];
            $customer = Customer::find($customerId);
            $rows[] = [
                'rank' => count($rows) + 1,
                'customer_id' => $customerId,
                'email' => $customer?->email ?? 'N/A',
                'revenue' => number_format(((int)$score) / 100, 2),
            ];
        }

        $this->table(['#','customer_id','email','revenue'], $rows);
        return 0;
    }
}
