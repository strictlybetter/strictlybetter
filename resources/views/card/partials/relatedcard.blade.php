<div class="mtgcard-wrapper">
	@if(Request::is('card/*') || Request::is('upgradeview/*'))
		<a class="card-link" href="{{ route('card.create', [$related->id]) }}" title="{{ $related->name }}">
	@else
		<a class="card-link" href="{{ route('index', ['format' => isset($format) ? $format : '', 'search' => $related->name, 'filters' => isset($filters) ? $filters : '']) }}" title="{{ $related->name }}">
	@endif
		<div class="flipper">
			{{ Html::image(asset('image/card-back.jpg'), $related->name, ['class' => 'mtgcard back', 'loading' => 'eager']) }}
			{{ Html::image($related->imageUrl, $related->name, ['class' => 'mtgcard front', 'loading' => 'lazy', 'alt-src' => $related->gathererImg]) }}
			<span class="spinner-border spinner-border-xl mtgcard-loadspinner" role="status"></span>
		</div>
		<span class="mtgcard-text">{{ $related->name }}</span>
	</a>
	<div class="card-label-container">
		@if(is_array($related->pivot->labels))
		@foreach($related->pivot->labels as $label => $value)
		
			<?php $labeltext = Lang::get('card.' . ((isset($type) && $type == 'inferior') ? 'inferior_labels.' : 'labels.') . $label); ?>

			@if($value && $label != "strictly_better" && $labeltext != "")
				<div class="card-label">{{ $labeltext }}</div>
			@endif
		@endforeach
		@endif
	</div>

	@if($related->pivot->obsolete_id)
	<div class="row card-votes" data-obsolete-id="{{ $related->pivot->obsolete_id }}">

		{{ Form::open(['route' => ['upvote', $related->pivot->obsolete_id], 'class' => 'form-inline vote-form']) }}
			<button type="submit" class="btn vote" style="color:green;">
				<span class="vote-text">{{ (isset($type) && $type == 'inferior') ? "Strictly Worse" : "Strictly Better" }}</span>
				<i class="fa fa-thumbs-up" style="font-size:24px;"></i>
				<span class="upvote-count">{{ $related->upvotes }}</span>
			</button>
		{{ Form::close() }}

		{{ Form::open(['route' => ['downvote', $related->pivot->obsolete_id], 'class' => 'form-inline vote-form']) }}
			<button type="submit" class="btn vote" style="color:red;">
				<span class="vote-text">Not</span>
				<i class="fa fa-thumbs-down" style="font-size:24px;"></i>
				<span class="downvote-count">{{ $related->downvotes }}</span>
			</button>
		{{ Form::close() }}

	</div>
	@elseif($related->pivot->suggestion_id)
	<div class="row card-votes">

		{{ Form::open(['route' => ['votehelp.add-suggestion', $related->pivot->suggestion_id], 'class' => 'form-inline vote-form']) }}
			<button type="submit" class="btn vote" style="color:green;">
				<span class="vote-text">{{ (isset($type) && $type == 'inferior') ? "Strictly Worse" : "Strictly Better" }}</span>
				<i class="fa fa-thumbs-up" style="font-size:24px;"></i>
				<span class="upvote-count">0</span>
			</button>
			{{ Form::hidden('superior_id', $related->id) }}
		{{ Form::close() }}

		{{ Form::open(['route' => ['votehelp.ignore-suggestion', $related->pivot->suggestion_id], 'class' => 'form-inline vote-form']) }}
			<button type="submit" class="btn vote" style="color:red;">
				<span class="vote-text">Not</span>
				<i class="fa fa-thumbs-down" style="font-size:24px;"></i>
				<span class="downvote-count">0</span>
			</button>
			{{ Form::hidden('superior_id', $related->id) }}
		{{ Form::close() }}

	</div>
	@endif
	<div class="row card-external-links">
		@if($related->scryfall_link)<a class="btn btn-light btn-gatherer" href="{{ $related->scryfall_link }}" rel="noopener nofollow">Scryfall</a>@endif
		@if($related->multiverse_id)<a class="btn btn-light btn-gatherer" href="{{ $related->gathererUrl }}" rel="noopener nofollow">Gatherer</a>@endif
	</div>
</div>
