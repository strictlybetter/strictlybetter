<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Model;

class ExcerptVariableComparison extends Model
{
    protected $table = 'excerpt_comparison_variables';
    protected $guarded = ['id'];

    public $timestamps = false;

    public function inferior() {
        return $this->belongsTo(ExcerptVariable::class, 'inferior_variable_id');
    }

    public function superior() {
        return $this->belongsTo(ExcerptVariable::class, 'superior_variable_id');
    }

    public function comparison() {
        return $this->belongsTo(ExcerptComparison::class, 'comparison_id');
    }
}