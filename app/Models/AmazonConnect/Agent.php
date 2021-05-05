<?php

namespace App\Models\AmazonConnect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;

    protected $table = 'connect_agent';
	
	protected $fillable = [
		'name',
		'instance_id',
		'json'
	];

    public function instance()
    {
        return $this->belongsTo(Instance::class);
    }
}
