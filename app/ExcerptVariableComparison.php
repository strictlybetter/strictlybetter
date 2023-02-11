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

    public function valueComparisonDb(ExcerptVariableValue $a, ExcerptVariableValue $b, ExcerptVariable $sample) 
    {
        /*
        if ($a === null || $b === null)
            return 0;
        */
        return (new ExcerptVariable([
            'capture_type' => $sample->capture_type,
            'more_is_better' => $this->more_is_better
        ]))->valueComparisonDb($a, $b);
    }

    public function isSameComparison(ExcerptVariableComparison $other) 
    {
        return $this->superior_variable_id === $other->superior_variable_id && 
                $this->inferior_variable_id === $other->inferior_variable_id;
    }

    public function sumPoints(ExcerptVariableComparison $other)
    {
        $this->points_for_less += $other->points_for_less;
        $this->points_for_more += $other->points_for_more;
        $this->more_is_better = ($this->points_for_more === $this->points_for_less) ? null : (int)($this->points_for_more > $this->points_for_less);

        return $this;
    }
}