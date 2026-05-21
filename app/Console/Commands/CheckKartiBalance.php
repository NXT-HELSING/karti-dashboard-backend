<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Services\Providers\KartiProvider;
use Illuminate\Support\Facades\Log;

#[Signature('karti:check-balance')]
#[Description('Checks Karti master account balance and logs an alert if it is below a threshold.')]
class CheckKartiBalance extends Command
{
    /**
     * The alert threshold in USD.
     */
    private const ALERT_THRESHOLD_USD = 50.00;

    /**
     * Execute the console command.
     */
    public function handle(KartiProvider $kartiProvider)
    {
        $this->info('Checking Karti balance...');
        
        try {
            $kartiBalances = $kartiProvider->getBalance();
            $usdBalance = 0.0;
            
            if (is_array($kartiBalances)) {
                foreach ($kartiBalances as $kb) {
                    if (isset($kb['currency']) && strtoupper($kb['currency']) === 'USD') {
                        $usdBalance = (float)($kb['balance'] ?? 0.0);
                        break;
                    }
                }
            }

            $this->info("Current Karti Master USD Balance: \${$usdBalance}");

            if ($usdBalance < self::ALERT_THRESHOLD_USD) {
                $message = "CRITICAL: Karti Master Balance is low! Available: \${$usdBalance} USD (Threshold: \$" . self::ALERT_THRESHOLD_USD . " USD). Please recharge immediately.";
                Log::alert($message);
                $this->error($message);
            } else {
                $this->info('Balance is healthy.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $errorMessage = 'Failed to check Karti balance: ' . $e->getMessage();
            Log::error($errorMessage);
            $this->error($errorMessage);
            return Command::FAILURE;
        }
    }
}
