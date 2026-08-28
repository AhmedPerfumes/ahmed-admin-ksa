<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackerRecoveryLog extends Model
{
    use HasFactory;

    protected $table = 'tracker_recovery_logs';

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'phone',
        'channel',
        'status',
        'message_content',
        'api_response',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
