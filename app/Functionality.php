<?php

namespace App;

use App\Card;
use App\Labeling;
use App\FunctionalityGroup;
use App\Obsolete;

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

	public function excerpts()
	{
		return $this->belongsToMany(Excerpt::class, 'excerpt_group', 'group_id', 'excerpt_id', 'group_id');
	}

	public function scopeAnalyzedExcerpts($query)
	{
		return $query->with(['excerpts' => function($e) { return $e->whereNotNull('positive'); }])
			->whereHas('excerpts', function($e) { return $e->whereNotNull('positive'); });
	}

	public function migrateTo(Functionality $new_functionality, FunctionalityGroup $new_group)
	{
		$inferiors = Labeling::with(['obsolete'])->where('superior_functionality_id', $this->id)->get();
		$superiors = Labeling::with(['obsolete'])->where('inferior_functionality_id', $this->id)->get();

		// Inferiors
		foreach ($inferiors as $labeling) {
			$labeling->timestamps = false;
			if ($labeling->obsolete && $this->group_id !== $new_group->id) {

				$existing_obsolete = Obsolete::with(['votes'])->where('superior_functionality_group_id', $new_group->id)
					->where('inferior_functionality_group_id', $labeling->obsolete->inferior_functionality_group_id)
					->first();

				if ($existing_obsolete) {
					$labeling->obsolete->migrateVotesTo($existing_obsolete);

					$to_delete = $labeling->obsolete;

					$labeling->obsolete_id = $existing_obsolete->id;
					$labeling->save();

					$to_delete->delete();
				}/* Handled by cascade update later on functionalities table
				else {
					$labeling->obsolete->timestamps = false;
					$labeling->obsolete->superior_functionality_group_id = $new_group->id;
					$labeling->obsolete->save();
				}*/
			}

			if ($this->id != $new_functionality->id) {

				$existing_labeling = Labeling::where('superior_functionality_id', $new_functionality->id)
					->where('inferior_functionality_id', $labeling->inferior_functionality_id)
					->first();

				if ($existing_labeling) {
					$labeling->delete();
				}
				else {
					$labeling->superior_functionality_id = $new_functionality->id;
					$labeling->save();
				}
			}
		}

		// Superiors
		foreach ($superiors as $labeling) {
			$labeling->timestamps = false;
			if ($labeling->obsolete && $this->group_id !== $new_functionality->group_id) {

				$existing_obsolete = Obsolete::with(['votes'])->where('inferior_functionality_group_id', $new_functionality->group_id)
					->where('superior_functionality_group_id', $labeling->obsolete->superior_functionality_group_id)
					->first();

				if ($existing_obsolete) {
					$labeling->obsolete->migrateVotesTo($existing_obsolete);

					$to_delete = $labeling->obsolete;

					$labeling->obsolete_id = $existing_obsolete->id;
					$labeling->save();

					$to_delete->delete();
				}/* Handled by cascade update later on functionalities table
				else {
					$labeling->obsolete->timestamps = false;
					$labeling->obsolete->inferior_functionality_group_id = $new_group->id;
					$labeling->obsolete->save();
				}*/
			}

			if ($this->id != $new_functionality->id) {

				$existing_labeling = Labeling::where('inferior_functionality_id', $new_functionality->id)
					->where('superior_functionality_id', $labeling->superior_functionality_id)
					->first();

				if ($existing_labeling) {
					$labeling->delete();
				}
				else {
					$labeling->inferior_functionality_id = $new_functionality->id;
					$labeling->save();
				};
			}
		}
	}
}