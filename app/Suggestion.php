<?php

namespace App;

use App\Obsolete;

use Illuminate\Database\Eloquent\Model;

class Suggestion extends Model
{
	protected $table = 'community_suggestions';
	protected $fillable = ['ip', 'obsolete_id', 'upvote'];

	public $timestamps = false;

	public function obsolete() 
	{
		return $this->belongsTo(Obsolete::class);
	}
}