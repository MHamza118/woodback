<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvailabilityReason extends Model
{
    protected $fillable = [
        'reason',
        'comment_required',
    ];

    protected $casts = [
        'comment_required' => 'boolean',
    ];
}
