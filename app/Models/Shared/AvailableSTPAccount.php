<?php


namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvailableSTPAccount extends Model
{
    use HasFactory;

    protected $table = 'available_stp_accounts';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'STPAccount',
        'Available'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
