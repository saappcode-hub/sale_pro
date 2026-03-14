<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Business;
use App\TransactionVisit;
use Spatie\Browsershot\Browsershot;

class SendDailySaleVisitSummary extends Command
{
    protected $signature   = 'telegram:send-daily-visit-summary';
    protected $description = 'Auto-send daily sale visit summary to Telegram as an image (based on telegram_schedules)';

    public function handle()
    {
        $now           = Carbon::now();
        $currentMinute = $now->format('H:i'); // e.g. "18:00"
        $currentDay    = $now->format('D');   // e.g. "Mon", "Tue"

        // 1. Get all active schedules whose time matches the current minute
        $schedules = DB::table('telegram_schedules')
            ->where('is_active', 1)
            ->whereRaw('DATE_FORMAT(schedule_time, "%H:%i") = ?', [$currentMinute])
            ->get();

        // 2. Filter by send_days JSON array (NULL = every day)
        $schedules = $schedules->filter(function ($s) use ($currentDay) {
            if (empty($s->send_days)) return true;
            $allowedDays = json_decode($s->send_days, true);
            return is_array($allowedDays) && in_array($currentDay, $allowedDays);
        });

        // 3. Filter by report_types JSON array — only "sales_visit" handled here
        $schedules = $schedules->filter(function ($s) {
            if (empty($s->report_types)) return true; // legacy rows — include
            $types = json_decode($s->report_types, true);
            return is_array($types) && in_array('sales_visit', $types);
        });

        // 4. Group by business_id and process each
        $groupedSchedules = $schedules->groupBy('business_id');

        foreach ($groupedSchedules as $business_id => $businessSchedules) {
            $chatIds = $businessSchedules->pluck('chat_id')->filter()->unique()->values()->toArray();
            if (!empty($chatIds)) {
                $this->sendSummaryForBusiness($business_id, $chatIds);
            }
        }

        $this->info(
            "Checked at {$currentMinute} ({$currentDay}). " .
            "Processed " . $groupedSchedules->count() . " business(es)."
        );
    }

    private function sendSummaryForBusiness(int $business_id, array $chat_ids): void
    {
        $today     = Carbon::today();
        $yesterday = Carbon::yesterday();

        $business      = Business::find($business_id);
        $business_name = $business ? $business->name : 'Unknown Business';

        // ── TODAY'S VISITS ──
        $todays_visits = TransactionVisit::leftJoin('users', 'transactions_visit.create_by', '=', 'users.id')
            ->where('transactions_visit.business_id', $business_id)
            ->whereDate('transactions_visit.transaction_date', $today)
            ->select('transactions_visit.*', 'users.username')
            ->get();

        $todays_visits_count = $todays_visits->count();
        $target_per_day      = 25;
        $sales_report        = [];
        $total_own           = 0;
        $total_other         = 0;

        foreach ($todays_visits as $visit) {
            $rep_id   = $visit->create_by;
            $rep_name = $visit->sale_rep ?: ($visit->username ?: 'Unknown');

            if (!isset($sales_report[$rep_id])) {
                $sales_report[$rep_id] = [
                    'name'              => $rep_name,
                    'target'            => $target_per_day,
                    'qty_visit'         => 0,
                    'own_product_qty'   => 0,
                    'other_product_qty' => 0,
                ];
            }

            $sales_report[$rep_id]['qty_visit'] += 1;
            $own_qty   = intval($visit->own_product   ?? 0);
            $other_qty = intval($visit->other_product ?? 0);
            $sales_report[$rep_id]['own_product_qty']   += $own_qty;
            $sales_report[$rep_id]['other_product_qty'] += $other_qty;
            $total_own   += $own_qty;
            $total_other += $other_qty;
        }

        $total_target = count($sales_report) > 0
            ? count($sales_report) * $target_per_day
            : $target_per_day;

        foreach ($sales_report as &$report) {
            $report['remaining'] = $report['qty_visit'] - $report['target'];
            $report['variance']  = $report['target'] > 0
                ? round(($report['qty_visit'] / $report['target']) * 100) : 0;
            $total_rep = $report['own_product_qty'] + $report['other_product_qty'];
            $report['own_pct']   = $total_rep > 0 ? round(($report['own_product_qty']   / $total_rep) * 100) : 0;
            $report['other_pct'] = $total_rep > 0 ? round(($report['other_product_qty'] / $total_rep) * 100) : 0;
        }
        unset($report);

        $total_products_overall = $total_own + $total_other;
        $overall_own_pct   = $total_products_overall > 0 ? round(($total_own   / $total_products_overall) * 100) : 0;
        $overall_other_pct = $total_products_overall > 0 ? round(($total_other / $total_products_overall) * 100) : 0;
        $overall_variance  = $total_target > 0 ? round(($todays_visits_count / $total_target) * 100) : 0;
        $overall_remaining = $todays_visits_count - $total_target;

        // ── YESTERDAY'S VISITS ──
        $yesterdays_visits       = TransactionVisit::where('business_id', $business_id)
            ->whereDate('transaction_date', $yesterday)->select('create_by')->get();
        $yesterdays_visits_count = $yesterdays_visits->count();
        $yesterday_unique_reps   = $yesterdays_visits->groupBy('create_by')->count();
        $yesterday_target        = $yesterday_unique_reps > 0
            ? $yesterday_unique_reps * $target_per_day : $target_per_day;
        $yesterday_variance      = $yesterday_target > 0
            ? round(($yesterdays_visits_count / $yesterday_target) * 100) : 0;
        $dod_change              = $overall_variance - $yesterday_variance;

        // ── RENDER BLADE → PNG via Browsershot ──
        $html = view('sales_visit.snapshot', compact(
            'today', 'business_name', 'todays_visits_count', 'total_target',
            'overall_variance', 'overall_remaining', 'overall_own_pct',
            'overall_other_pct', 'sales_report', 'yesterdays_visits_count',
            'yesterday_target', 'yesterday_variance', 'dod_change'
        ))->render();

        $fileName = 'auto_summary_' . $business_id . '_' . time() . '.png';
        $tempPath = storage_path('app/public/' . $fileName);

        try {
            Browsershot::html($html)
                ->windowSize(800, 600)   // height auto-grows via fullPage
                ->fullPage()
                ->waitUntilNetworkIdle()
                ->noSandbox()
                ->save($tempPath);

            $botToken = config('services.telegram.bot_token', '8737726993:AAEd8C5uWwHu5cYc8YVH4zfpUwUxSWaplSc');
            $caption  = "📊 *Daily Sale Visit Summary*\nDate: " . $today->format('Y-m-d');

            foreach ($chat_ids as $chat_id) {
                Http::attach('photo', file_get_contents($tempPath), $fileName)
                    ->post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                        'chat_id'    => $chat_id,
                        'caption'    => $caption,
                        'parse_mode' => 'Markdown',
                    ]);
            }

            if (file_exists($tempPath)) unlink($tempPath);

        } catch (\Exception $e) {
            Log::error("Telegram Auto-Image Error (business_id={$business_id}): " . $e->getMessage());
        }
    }
}