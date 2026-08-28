<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TrackerEvent;
use App\Models\TrackerDailyStat;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AggregateTrackerStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracker:aggregate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregates raw tracker events into daily statistics and purges events older than 90 days.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting tracker stats aggregation...');

        // Group events by stat_date and event_name
        $aggregatedStats = TrackerEvent::select(
                DB::raw('DATE(created_at) as stat_date'),
                'event_name',
                DB::raw('COUNT(*) as event_count'),
                DB::raw('COUNT(DISTINCT visitor_id) as unique_visitors'),
                DB::raw('COUNT(DISTINCT session_id) as unique_sessions'),
                DB::raw('COALESCE(SUM(revenue), 0) as total_revenue')
            )
            ->groupBy(DB::raw('DATE(created_at)'), 'event_name')
            ->get();

        $upsertedCount = 0;
        foreach ($aggregatedStats as $stat) {
            TrackerDailyStat::updateOrCreate(
                [
                    'stat_date'  => $stat->stat_date,
                    'event_name' => $stat->event_name,
                ],
                [
                    'event_count'     => $stat->event_count,
                    'unique_visitors' => $stat->unique_visitors,
                    'unique_sessions' => $stat->unique_sessions,
                    'total_revenue'   => $stat->total_revenue,
                ]
            );
            $upsertedCount++;
        }

        $this->info("Aggregated {$upsertedCount} daily stat records.");

        // Purge raw events older than 90 days
        $cutoffDate = Carbon::now()->subDays(90)->startOfDay();
        $deletedEvents = TrackerEvent::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Purged {$deletedEvents} raw events older than 90 days (prior to {$cutoffDate->toDateTimeString()}).");

        return Command::SUCCESS;
    }
}
