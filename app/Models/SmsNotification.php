<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsNotification extends Model
{
    protected $fillable = [
        'child_id',
        'vaccination_schedule_id',
        'reminder_days',
        'scheduled_date',
        'status',
        'sent_at',
        'failure_reason',
        'gateway_message_id',
        'gateway_queue_id',
    ];

    protected function casts(): array
    {
        return ['scheduled_date' => 'date', 'sent_at' => 'datetime'];
    }

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function schedule()
    {
        return $this->belongsTo(VaccinationSchedule::class, 'vaccination_schedule_id');
    }
}
