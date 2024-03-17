<?php

namespace App\Console\Commands;

use App\Model\WalletRetainedBalance;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateRetainedBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'balances:migrate-retained';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate retained balances to total in wallet table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //$thresholdTime = Carbon::now()->subMinutes(2);
        $thresholdTime = Carbon::now()->subDays(30);

        $retainedBalances = WalletRetainedBalance::where('created_at', '<', $thresholdTime)
        ->get()
        ->map(function ($record) {
            $record->human_readable_diff = Carbon::parse($record->created_at)->diffForHumans();
            return $record;
        });
      
        foreach ($retainedBalances as $retainedBalance) {
            $wallet = $retainedBalance->wallet;

            // Migrate retained balance to total
            $wallet->update([
                'total' => DB::raw('total + ' . $retainedBalance->retained_balance),
            ]);

            // Delete the record from wallet_retained_balance
             $retainedBalance->delete();
            $newRetainedBalance = $wallet->retained_balance - $retainedBalance->retained_balance;

            if ($newRetainedBalance >= 0) {
                $wallet->update([
                    'retained_balance' => DB::raw('retained_balance - ' . $retainedBalance->retained_balance),
                ]);
            }
            $this->info('Migrated retained balance for wallet ' . $retainedBalance->id);
        }
    }
}
