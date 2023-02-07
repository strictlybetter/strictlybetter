<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExcerptVariableValue extends Model
{
	protected $table = 'excerpt_variable_values';
	protected $guarded = ['id'];

	public $timestamps = false;

	//protected $casts = ['value' => 'array'];

	public function variable() 
	{
		return $this->belongsTo(ExcerptVariable::class, 'variable_id');
	}

	public function group()
	{
		return $this->belongsTo(FunctionalityGroup::class, 'group_id');
	}

	public function setValueAttribute($value) { $this->attributes['value'] = json_encode($value); }
	public function getValueAttribute() { return json_decode($this->attributes['value'], true); }
}