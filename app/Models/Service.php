<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'duration_minutes',
        'buffer_minutes',
        'price',
        'requires_staff',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_staff' => 'boolean',
            'is_active' => 'boolean',
            'duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'price' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function staffMembers(): BelongsToMany
    {
        return $this->belongsToMany(StaffMember::class, 'staff_service');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
