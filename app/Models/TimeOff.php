<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeOff extends Model
{
    /** @use HasFactory<\Database\Factories\TimeOffFactory> */
    use HasFactory;

    protected $table = 'time_off';

    protected $fillable = [
        'business_id',
        'staff_member_id',
        'starts_at',
        'ends_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }
}
