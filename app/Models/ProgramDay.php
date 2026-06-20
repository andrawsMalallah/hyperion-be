<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramDay extends Model
{
    protected $fillable = [
        'program_id',
        'day_name',
        'display_order',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function exercises()
    {
        return $this->belongsToMany(Exercise::class, 'day_exercise')
            ->withPivot('display_order')
            ->orderBy('pivot_display_order');
    }
}
