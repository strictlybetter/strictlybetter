<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Model;

class ExcerptComparison extends Pivot
{
    protected $table = 'excerpt_comparisons';
    protected $guarded = ['id'];

    public $incrementing = true;
    public $timestamps = false;

    public function inferior() {
        return $this->belongsTo(Excerpt::class, 'inferior_excerpt_id');
    }

    public function superior() {
        return $this->belongsTo(Excerpt::class, 'superior_excerpt_id');
    }

    public function variablecomparisons() {
        return $this->hasMany(ExcerptVariableComparison::class, 'comparison_id');
    }
}