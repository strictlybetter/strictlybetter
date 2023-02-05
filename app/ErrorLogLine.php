<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ErrorLogLine extends Model
{
	protected $table = 'errors';
	protected $guarded = ['id'];
}