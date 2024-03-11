<?php


namespace App\Models\Security;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DockJwt extends Model
{
    use HasFactory;

    protected $table = 'dock_jwt';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'Token'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
