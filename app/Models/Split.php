<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Split extends Model
{
    protected $fillable = [
        'user_id',
        'split_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function days()
    {
        return $this->hasMany(SplitDay::class)->orderBy('display_order');
    }
}
