<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;
	
	protected $table = 'company';
	
	protected $fillable = [
		'name',
		'description',
		'json',
	];

	public static function names()
	{
		$companies = Company::all();

		$names = [];
		foreach($companies as $company)
		{
			$names[] = $company->name;
		}

		return $names;
	}
}
