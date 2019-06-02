<div class="col-lg-3 mtgcard-wrapper">
	<a href="{{ URL::route('card.create', $related->id) }}">
		{{ Html::image($related->imageUrl, $related->name, ['class' => 'mtgcard']) }}
	</a>
	<div class="card-label-container">
		@if(is_array($related->pivot->labels))
		@foreach($related->pivot->labels as $label => $value)
			@if($value && $label != "strictly_better")
				<div class="card-label">{{ Lang::get('card.labels.' . $label) }}</div>
			@endif
		@endforeach
		@endif
	</div>	
	<br>

	<div class="row">

		{{ Form::open(['route' => ['upvote', $related->pivot->id], 'class' => 'form-inline vote-form']) }}
			<button type="submit" class="btn btn-light vote" style="color:green;">
				{{ (isset($type) && $type == 'inferior') ? "Strictly Worse" : "Strictly Better" }}
				<i class="fa fa-thumbs-up" style="font-size:24px;"></i>
				<span class="upvote-count">{{ $related->pivot->upvotes }}</span>
			</button>
		{{ Form::close() }}

		{{ Form::open(['route' => ['downvote', $related->pivot->id], 'class' => 'form-inline vote-form']) }}
			<button type="submit" class="btn btn-light vote" style="color:red;">
				Not
				<i class="fa fa-thumbs-down" style="font-size:24px;"></i>
				<span class="downvote-count">{{ $related->pivot->downvotes }}</span>
			</button>
		{{ Form::close() }}

	</div>
	<a href="{{ $related->gathererUrl }}" rel="noopener nofollow" style="padding:.2rem .25rem">Gatherer</a>
</div>