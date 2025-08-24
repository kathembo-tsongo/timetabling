<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Examroom extends Model
{
    use HasFactory;

    protected $table = 'examrooms'; // Ensure this matches the table name in your database

    protected $fillable = [
        'name',
        'capacity',
        'location',
    ];
}
