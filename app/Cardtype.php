<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cardtype extends Model
{
	protected $table = 'cardtypes';
	protected $fillable = ['section', 'type', 'key'];

	public $timestamps = false;
}