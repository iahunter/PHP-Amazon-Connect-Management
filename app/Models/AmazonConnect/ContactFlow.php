<?php

namespace App\Models\AmazonConnect;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactFlow extends Model
{
    use HasFactory;

    protected $table = 'connect_contact_flow';

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
