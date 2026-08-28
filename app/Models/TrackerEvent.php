<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackerEvent extends Model
{
    use HasFactory;

    protected $table = 'tracker_events';

    public $timestamps = false;

    protected $fillable = [
        'event_name',
        'event_data',
        'session_id',
        'visitor_id',
        'customer_id',
        'customer_phone',
        'page_url',
        'page_title',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'device_type',
        'browser',
        'os',
        'screen_width',
        'screen_height',
        'language',
        'country',
        'city',
        'currency',
        'ip_address',
        'user_agent',
        'revenue',
        'created_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'revenue' => 'float',
        'created_at' => 'datetime',
    ];

    /**
     * Scope query to events created today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', now()->today());
    }

    /**
     * Scope query to events created in the last X days.
     */
    public function scopeLastDays($query, int $days)
    {
        return $query->where('created_at', '>=', now()->subDays($days)->startOfDay());
    }

    /**
     * Scope query by specific event name.
     */
    public function scopeByEvent($query, string $eventName)
    {
        return $query->where('event_name', $eventName);
    }
}
