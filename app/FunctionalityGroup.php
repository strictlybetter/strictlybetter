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

	public function migrateTo(FunctionalityGroup $new_group)
	{
		$from_inferiors = Obsolete::with(['labelings', 'votes'])->where('superior_functionality_group_id', $this->id)->get();
		$from_superiors = Obsolete::with(['labelings', 'votes'])->where('inferior_functionality_group_id', $this->id)->get();

		$to_inferiors = Obsolete::with(['labelings', 'votes'])->where('superior_functionality_group_id', $new_group->id)->get();
		$to_superiors = Obsolete::with(['labelings', 'votes'])->where('inferior_functionality_group_id', $new_group->id)->get();

		foreach ($from_inferiors as $obsolete) {

			$existing_obsolete = $to_inferiors->where('inferior_functionality_group_id', '=', $obsolete->inferior_functionality_group_id)->first();

			if ($existing_obsolete) {
				$obsolete->migrateVotesTo($existing_obsolete);

				foreach ($obsolete->labelings as $labeling) {
					$labeling->obsolete_id = $existing_obsolete->id;
					$labeling->save();
				}
				$obsolete->delete();
			}/* Handled by cascade update later on functionalities table
			else {
				$obsolete->timestamps = false;
				$obsolete->superior_functionality_group_id = $new_group->id;
				$obsolete->save();
			}*/
		}

		foreach ($from_superiors as $obsolete) {

			$existing_obsolete = $to_superiors->where('superior_functionality_group_id', '=', $obsolete->superior_functionality_group_id)->first();

			if ($existing_obsolete) {
				$obsolete->migrateVotesTo($existing_obsolete);

				foreach ($obsolete->labelings as $labeling) {
					$labeling->obsolete_id = $existing_obsolete->id;
					$labeling->save();
				}
				$obsolete->delete();
			}/* Handled by cascade update later on functionalities table
			else {
				$obsolete->timestamps = false;
				$obsolete->inferior_functionality_group_id = $new_group->id;
				$obsolete->save();
			}*/
		}
	}
}