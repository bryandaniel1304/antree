<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_ARRIVED = 'arrived';

    public const STATUS_IN_SERVICE = 'in_service';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_NO_SHOW = 'no_show';

    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_ONLINE = 'online';

    public const SOURCE_WALK_IN = 'walk_in';

    public const SOURCE_PHONE = 'phone';

    /**
     * Perpindahan status yang sah: status asal => daftar status tujuan yang diizinkan.
     *
     * @var array<string, array<int, string>>
     */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_ARRIVED, self::STATUS_CANCELLED, self::STATUS_NO_SHOW],
        self::STATUS_ARRIVED => [self::STATUS_IN_SERVICE, self::STATUS_CANCELLED, self::STATUS_NO_SHOW],
        self::STATUS_IN_SERVICE => [self::STATUS_COMPLETED],
        self::STATUS_COMPLETED => [],
        self::STATUS_NO_SHOW => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'business_id',
        'service_id',
        'staff_member_id',
        'customer_name',
        'customer_phone',
        'starts_at',
        'ends_at',
        'source',
        'status',
        'queue_number',
        'queue_date',
        'code',
        'public_token',
        'called_at',
        'started_at',
        'completed_at',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'queue_date' => 'date',
            'called_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }
}
