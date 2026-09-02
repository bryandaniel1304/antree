<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** @use HasFactory<\Database\Factories\AuditLogFactory> */
    use HasFactory;

    public const ACTOR_OWNER = 'owner';

    public const ACTOR_STAFF = 'staff';

    public const ACTOR_CUSTOMER = 'customer';

    public const ACTOR_SYSTEM = 'system';

    public $timestamps = false;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'booking_id',
        'from_status',
        'to_status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $log) {
            $log->created_at ??= now();
        });
    }
}
