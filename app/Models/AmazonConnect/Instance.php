<?php

namespace App\Models\AmazonConnect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instance extends Model
{
    use HasFactory;
	
	protected $table = 'connect_instances';
	
	protected $fillable = [
		'name',
		'account_id',
		'instance_id',
		'region',
		'flows',
		'storage',
		'json',
		'build_data'
	];

	public function agents()
	{
		return hasMany(Agent::class, 'foreign_key');
	}

	public function queues()
	{
		return hasMany(Queue::class, 'foreign_key');
	}

	public function flows()
	{
		return hasMany(ContactFlow::class, 'foreign_key');
	}
}
