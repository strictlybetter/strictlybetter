<?php

namespace App;

use App\Obsolete;

use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
	protected $table = 'votes';
	protected $fillable = ['ip', 'obsolete_id', 'upvote'];

	public $timestamps = false;

	public function obsolete() 
	{
		return $this->belongsTo(Obsolete::class);
	}
}