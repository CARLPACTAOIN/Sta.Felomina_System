<?php

namespace App\Models;

use Database\Factories\ResidentDocumentRequestCounterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResidentDocumentRequestCounter extends Model
{
    /** @use HasFactory<ResidentDocumentRequestCounterFactory> */
    use HasFactory;

    protected $fillable = ['year', 'last_sequence'];
}
