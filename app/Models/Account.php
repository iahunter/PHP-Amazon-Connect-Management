<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $table = 'account';
	
	protected $fillable = [
		'company_id',
        'account_number',
        'account_description',
        'account_app_key',
        'account_app_secret',
	];
}
