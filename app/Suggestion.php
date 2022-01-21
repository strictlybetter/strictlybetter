<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use App\Card;

class Suggestion extends Model
{
	protected $table = 'suggestions';
	protected $guarded = ['id'];

	public $timestamps = false;

	public function getInferiorsAttribute() {
		return array_filter([trim($this->Inferior)]);
	}

	public function getSuperiorsAttribute() {
		return array_filter(array_map(function($name) { return trim($name); }, explode(",", $this->Superior)));
	}

	public function deleteLatest() {

		// Remove first superior from the list
		$new_list = $this->superiors;
		array_shift($new_list);

		// Once no superiors are left, delete suggestion
		if (empty($new_list)) {
			$this->delete();
			return;
		}

		// Otherwise save the new list
		else {
			$this->Superior = implode(",", $new_list);
			$this->save();
		}
	}
}
