<?php

namespace App\Jobs\Notifications;

use App\Jobs\BaseJob;
use App\Models\Receipt;
use App\Models\User;
use App\Notifications\WeeklySummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendWeeklySummary extends BaseJob
{
    public function __construct()
    {
        parent::__construct(\Illuminate\Support\Str::uuid());
        $this->jobName = 'Send Weekly Summary';
    }

    /**
     * Execute the job's logic.
     */
    protected function handleJob(): void
    {
        Log::info('Starting weekly summary generation');

        $today = Carbon::now();
        $dayOfWeek = strtolower($today->englishDayOfWeek);

        // Find users who want weekly summaries on this day
        $usersForSummary = User::whereHas('preferences', function ($query) use ($dayOfWeek) {
            $query->where('email_weekly_summary', true)
                ->where('weekly_summary_day', $dayOfWeek);
        })->with('preferences')->get();

        Log::info('Found users for weekly summary', [
            'count' => $usersForSummary->count(),
            'day' => $dayOfWeek,
        ]);

        foreach ($usersForSummary as $user) {
            try {
                // Calculate date range for the past week
                $startDate = $today->copy()->subWeek()->startOfDay();
                $endDate = $today->copy()->endOfDay();

                // Get receipts from the past week
                $receipts = Receipt::where('user_id', $user->id)
                    ->whereBetween('receipt_date', [$startDate, $endDate])
                    ->with(['merchant', 'category'])
                    ->get();

                // Calculate summary statistics
                $summaryData = [
                    'user' => $user,
                    'week_start' => $startDate,
                    'week_end' => $endDate,
                    'total_receipts' => $receipts->count(),
                    'total_amount' => $receipts->sum('total_amount'),
                    'currency' => $user->preference('currency', 'NOK'),
                    'categories' => $receipts->groupBy('category.name')->map->count(),
                    'merchants' => $receipts->groupBy('merchant.name')->map->count(),
                    'average_amount' => $receipts->count() > 0 ? $receipts->avg('total_amount') : 0,
                ];

                // Send notification
                $user->notify(new WeeklySummary($summaryData));

                Log::info('Weekly summary sent', [
                    'user_id' => $user->id,
                    'receipts_count' => $summaryData['total_receipts'],
                    'total_amount' => $summaryData['total_amount'],
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to send weekly summary', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed weekly summary generation');
    }
}
