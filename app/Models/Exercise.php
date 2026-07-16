<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exercise extends Model
{
    protected $fillable = [
        'name',
        'target_muscle_group',
        'mechanics_type',
        'created_by',
        'status',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The user who contributed this exercise (null for the seeded catalog and
     * for de-identified rows after an account deletion).
     */
    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
