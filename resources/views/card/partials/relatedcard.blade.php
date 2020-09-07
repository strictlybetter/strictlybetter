<div class="mtgcard-wrapper">
	@if(Request::is('card/*'))
		<a class="card-link" href="{{ route('card.create', [$related->id]) }}" title="{{ $related->name }}">
	@else
		<a class="card-link" href="{{ route('index', ['format' => isset($format) ? $format : '', 'search' => $related->name, 'filters' => isset($filters) ? $filters : '']) }}" title="{{ $related->name }}">
	@endif
		{{ Html::image(asset('image/card-back.jpg'), $related->name, ['data-src' => $related->imageUrl, 'class' => 'mtgcard', 'loading' => 'eager', 'alt-src' => $related->gathererImg]) }}
		<span class="mtgcard-text">{{ $related->name }}</span>
		<span class="spinner-border spinner-border-xl mtgcard-loadspinner" role="status"></span>
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

	<div class="row">

		{{ Form::open(['route' => ['upvote', $related->pivot->id], 'class' => 'form-inline vote-form']) }}
			<button type="submit" class="btn vote" style="color:green;">
				<span class="vote-text">{{ (isset($type) && $type == 'inferior') ? "Strictly Worse" : "Strictly Better" }}</span>
				<i class="fa fa-thumbs-up" style="font-size:24px;"></i>
				<span class="upvote-count">{{ $related->pivot->upvotes }}</span>
			</button>
		{{ Form::close() }}

		{{ Form::open(['route' => ['downvote', $related->pivot->id], 'class' => 'form-inline vote-form']) }}
			<button type="submit" class="btn vote" style="color:red;">
				<span class="vote-text">Not</span>
				<i class="fa fa-thumbs-down" style="font-size:24px;"></i>
				<span class="downvote-count">{{ $related->pivot->downvotes }}</span>
			</button>
		{{ Form::close() }}

	</div>
	@if($related->scryfall_link)<a class="btn btn-light btn-gatherer" href="{{ $related->scryfall_link }}" rel="noopener nofollow">Scryfall</a>@endif
	@if($related->multiverse_id)<a class="btn btn-light btn-gatherer" href="{{ $related->gathererUrl }}" rel="noopener nofollow">Gatherer</a>@endif
</div>