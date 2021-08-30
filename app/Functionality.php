<?php

namespace App;

use App\Card;
use App\Labeling;
use App\FunctionalityGroup;

use Illuminate\Database\Eloquent\Model;
use \Staudenmeir\EloquentHasManyDeep\HasTableAlias;

class Functionality extends Model
{
	use HasTableAlias;

	protected $table = 'functionalities';
	protected $guarded = ['id'];

	public $timestamps = false;

	public function cards() 
	{
		return $this->hasMany(Card::class);
	}

	public function group()
	{
		return $this->belongsTo(FunctionalityGroup::class, 'group_id');
	}

	public function similiars()
	{
		return $this->hasMany(Functionality::class, 'group_id', 'group_id');
	}

	public function similiarcards()
	{
		return $this->hasManyThrough(Card::class, Functionality::class, 'group_id', 'functionality_id', 'group_id', 'id');
	}

	public function inferiorLabelings()
	{
		return $this->hasMany(Labeling::class, 'superior_functionality_id', 'id');
	}

	public function superiorLabelings()
	{
		return $this->hasMany(Labeling::class, 'inferior_functionality_id', 'id');
	}

	public function inferiors() 
	{
		return $this->belongsToMany(Functionality::class, 'labelings', 'superior_functionality_id', 'inferior_functionality_id', 'id')
			->using(Labeling::class)->withPivot(['labels', 'id', 'obsolete_id'])->withTimestamps();
	}

	public function superiors()
	{
		return $this->belongsToMany(Functionality::class, 'labelings', 'inferior_functionality_id', 'superior_functionality_id', 'id')
			->using(Labeling::class)->withPivot(['labels', 'id', 'obsolete_id'])->withTimestamps();
	}
}