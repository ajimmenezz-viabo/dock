<?php

namespace App\Models\Embossing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmbossingBatch extends Model
{
    use HasFactory;

    protected $table = 'embossing_batches';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'UserId',
        'PersonId',
        'ExternalId',
        'TotalCards',
        'Status'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;

}