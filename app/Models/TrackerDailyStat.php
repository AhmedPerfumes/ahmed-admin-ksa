<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackerDailyStat extends Model
{
    use HasFactory;

    protected $table = 'tracker_daily_stats';

    protected $fillable = [
        'stat_date',
        'event_name',
        'event_count',
        'unique_visitors',
        'unique_sessions',
        'total_revenue',
    ];

    protected $casts = [
        'stat_date' => 'date',
        'total_revenue' => 'float',
    ];
}
