<div class="mtgcard-wrapper">
	<div class="mtgcard-header">
		{{ $related->name }}
	</div>
	<div class="mtgcard-body">
		@if(Request::is('card/*'))
			<a class="card-link" href="{{ route('card.create', [$related->id]) }}" title="{{ $related->name }}">
		@else
			<a class="card-link" href="{{ route('index', ['format' => isset($format) ? $format : '', 'search' => $related->name, 'filters' => isset($filters) ? $filters : '']) }}" title="{{ $related->name }}">
		@endif
			<div class="flipper">
				{{ Html::image(asset('image/card-back.jpg'), $related->name, ['class' => 'mtgcard back', 'loading' => 'eager']) }}
				{{ Html::image($related->imageUrl, $related->name, ['class' => 'mtgcard front', 'loading' => 'lazy', 'alt-src' => $related->gathererImg]) }}
				<span class="spinner-border spinner-border-xl mtgcard-loadspinner" role="status"></span>
			</div>

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
	</div>

	<div class="mtgcard-votes">

		{{ Form::open(['route' => ['upvote', $related->pivot->id], 'class' => 'vote-form']) }}
			<button type="submit" class="btn vote" style="color:green;">
				<div class="vote-counter">
					<i class="fa fa-thumbs-up""></i>
					<span class="upvote-count">{{ $related->pivot->upvotes }}</span>
				</div>
				<span class="vote-text">{{ (isset($type) && $type == 'inferior') ? "Strictly Worse" : "Strictly Better" }}</span>							
			</button>
		{{ Form::close() }}

		{{ Form::open(['route' => ['downvote', $related->pivot->id], 'class' => 'vote-form']) }}
			<button type="submit" class="btn vote" style="color:red;">
				<div class="vote-counter">
					<i class="fa fa-thumbs-down"></i>
					<span class="downvote-count">{{ $related->pivot->downvotes }}</span>
				</div>
				<span class="vote-text">{{ (isset($type) && $type == 'inferior') ? "Not Worse" : "Not Better" }}</span>
			</button>
		{{ Form::close() }}

	</div>
	<div class="mtgcard-footer">
		@if($related->scryfall_link)<a class="btn btn-light btn-gatherer" href="{{ $related->scryfall_link }}" rel="noopener nofollow">Scryfall</a>@endif
		@if($related->multiverse_id)<a class="btn btn-light btn-gatherer" href="{{ $related->gathererUrl }}" rel="noopener nofollow">Gatherer</a>@endif
	</div>
</div>