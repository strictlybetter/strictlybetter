<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'labels' => [
    	'more_colors' => '⚠️More colors',
		'more_colored_mana' => '⚠️Costs more colored',
		'supertypes_differ' => '⚠️Supertype variation',
		'types_differ' => '⚠️Type variation',
		'subtypes_differ' => '⚠️Tribe variation',
		'less_colors' => '⚠️Less colors',
		'strictly_better' => 'Strictly better',
		'downvoted' => '⚠️Downvoted'
    ],
    'inferior_labels' => [
    	'more_colors' => '⚠️Less colors',
		'more_colored_mana' => '',
		'supertypes_differ' => '⚠️Supertype variation',
		'types_differ' => '⚠️Type variation',
		'subtypes_differ' => '⚠️Tribe variation',
		'less_colors' => '⚠️More colors',
		'strictly_better' => 'Strictly worse',
		'downvoted' => '⚠️Downvoted'
    ],
    'filters' => [
		'more_colors' => 'More colors',
		'more_colored_mana' => 'Costs more colored',
		'supertypes_differ' => 'Supertype variation',
		'types_differ' => 'Type variation',
		'subtypes_differ' => 'Tribe variation',
		'less_colors' => 'Less colors',
		'strictly_better' => 'Strictly better',
		'downvoted' => 'Downvoted'
    ],
    'filter_explanations' => [
    	'more_colors' => 'Suggested card has more colors. (ie Dryad Militant vs Eager Cadet)',
		'more_colored_mana' => 'Suggested card costs more colored mana, but must still not have higher cmc. (ie Counterspell vs Mana Leak)',
		'supertypes_differ' => 'Suggested card has different supertypes (Legandary, Snow, ...)',
		'types_differ' => 'Suggested card has different types (Instant vs Sorcery, Artifact Creature vs Creature)',
		'subtypes_differ' => 'Suggested card has different subtypes (Elf vs Elf Druid)',
		'less_colors' => 'Suggested card has less colors (ie. Devoid spells do not have a color, but may otherwise be better than a colored counterpart)',
		'downvoted' => 'Number of downvotes exceeds upvotes by 10 votes'
    ]
];
