<div class="mtgcard-wrapper">
	<a href="{{ URL::route('card.create', $related->id) }}">
		{{ Html::image($related->imageUrl, $related->name, ['class' => 'mtgcard']) }}
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
	<br>

	<div class="row">

		{{ Form::open(['route' => ['upvote', $related->pivot->id], 'class' => 'form-inline vote-form']) }}
			<button type="submit" class="btn btn-light vote" style="color:green;">
				<span class="vote-text">{{ (isset($type) && $type == 'inferior') ? "Strictly Worse" : "Strictly Better" }}</span>
				<i class="fa fa-thumbs-up" style="font-size:24px;"></i>
				<span class="upvote-count">{{ $related->pivot->upvotes }}</span>
			</button>
		{{ Form::close() }}

		{{ Form::open(['route' => ['downvote', $related->pivot->id], 'class' => 'form-inline vote-form']) }}
			<button type="submit" class="btn btn-light vote" style="color:red;">
				<span class="vote-text">Not</span>
				<i class="fa fa-thumbs-down" style="font-size:24px;"></i>
				<span class="downvote-count">{{ $related->pivot->downvotes }}</span>
			</button>
		{{ Form::close() }}

	</div>
	<a href="{{ $related->gathererUrl }}" rel="noopener nofollow" style="padding:.2rem .25rem">Gatherer</a>
</div>