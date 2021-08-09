<?php

namespace App\Models\AmazonConnect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;

    protected $table = 'connect_agents';
	
	protected $fillable = [
		'username',
		'instance_id',
        'arn',
        'status',
		'json'
	];

    public function instance()
    {
        return $this->belongsTo(Instance::class);
    }
}
