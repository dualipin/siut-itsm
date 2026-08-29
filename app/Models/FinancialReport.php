<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class FinancialReport extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'year',
        'status',
        'created_by',
    ];
}
