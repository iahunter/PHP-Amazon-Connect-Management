<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CtrBuckets extends Model
{
    use HasFactory;

    protected $table = 'connnect_ctr_buckets';
	
	protected $fillable = [
		'name',
		'instance_id',
        'account_id', 
		'region',
        'monitor'
	];
}
