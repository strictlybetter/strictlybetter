<?php

namespace App;

use App\Card;
use App\Obsolete;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Labeling extends Pivot
{
	protected $table = 'labelings';
	protected $guarded = ['id'];

	protected $casts = ['labels' => 'array'];
	//protected $with = ['obsolete'];

	public static $labellist = ['more_colors', 'more_colored_mana', 'supertypes_differ', 'types_differ', 'subtypes_differ', 'less_colors', 'strictly_better', 'downvoted'];

	public function inferiors()
	{
		return $this->hasMany(Card::class, 'functionality_id', 'inferior_functionality_id');
	}

	public function superiors()
	{
		return $this->hasMany(Card::class, 'functionality_id', 'superior_functionality_id');
	}

	public function obsolete()
	{
		return $this->belongsTo(Obsolete::class);
	}

	public function inferior() 
	{
		return $this->belongsTo(Functionality::class, 'inferior_functionality_id');
	}

	public function superior() 
	{
		return $this->belongsTo(Functionality::class, 'superior_functionality_id');
	}
}