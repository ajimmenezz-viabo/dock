<?php


namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthorizationRequest extends Model
{
    use HasFactory;

    protected $table = 'authorization_requests';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'UUID',
        'ExternalId',
        'AuthorizationCode',
        'Endpoint',
        'Headers',
        'Body',
        'Response',
        'Error',
        'Code'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
