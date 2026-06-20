<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SplitDay extends Model
{
    protected $fillable = [
        'split_id',
        'day_name',
        'display_order',
    ];

    public function split()
    {
        return $this->belongsTo(Split::class);
    }

    public function exercises()
    {
        return $this->belongsToMany(Exercise::class, 'day_exercise')
                    ->withPivot('display_order')
                    ->orderBy('pivot_display_order');
    }
}
