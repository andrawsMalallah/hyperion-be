<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'is_public',
        'source_program_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    // New programs are PRIVATE unless publishing is explicit (Session 26,
    // ROADMAP 7.4) — an omitted flag must never mean "listed in Discover".
    // The programs.is_public DB default was flipped to match in the same session.
    protected $attributes = [
        'is_public' => false,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function days()
    {
        return $this->hasMany(ProgramDay::class)->orderBy('display_order');
    }
}
