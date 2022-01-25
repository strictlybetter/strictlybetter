<?php

namespace App;

use App\Functionality;
use App\Excerpt;

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

	public function excerpts()
	{
		return $this->belongsToMany(Excerpt::class, 'excerpt_group', 'group_id', 'excerpt_id')->withPivot(['amount']);
	}

	public function variablevalues()
	{
		return $this->hasMany(ExcerptVariableValue::class, 'group_id');
	}

	public function examplecard()
	{
		return $this->hasOneThrough(Card::class, Functionality::class, 'group_id', 'functionality_id');
	}
}