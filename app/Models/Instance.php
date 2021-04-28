<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instance extends Model
{
    use HasFactory;
	
	protected $table = 'connect_instances';
	
	protected $fillable = [
		'name',
		'account',
		'instance_id',
	];
}
