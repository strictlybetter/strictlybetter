<?php

namespace App;

use App\Functionality;

use Illuminate\Database\Eloquent\Model;

class FunctionalityGroup extends Model
{
	protected $table = 'functionality_groups';
	protected $guarded = ['id'];

	public $timestamps = false;

	public function functionalities()
	{
		return $this->hasMany(Functionality::class, 'group_id');
	}
}