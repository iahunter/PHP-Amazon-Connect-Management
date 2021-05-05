<?php

namespace App\Models\AmazonConnect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoutingProfile extends Model
{
    use HasFactory;

    protected $table = 'connect_routing_profile';
	
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
