<div class="row">

	<div class="col-md-2 mtgcard-wrapper">
		<a href="{{ URL::route('card.create', $card->id) }}">
			{{ Html::image($card->imageUrl, $card->name, ['class' => 'mtgcard']) }}
		</a><br>
		<div class="row">
			<a href="{{ $card->gathererUrl }}" rel="noopener nofollow" style="padding:.2rem .25rem">Gatherer</a>
		</div>
	</div>

	@if(count($card->superiors) > 0)
	<div class="col-md-1 text-center" style="padding-top: 100px;">
		<i class="fa fa-arrow-right" style="font-size:30px;"></i>
	</div>
	<div class="mtgcard-wrapper">
		<div class="row">
			@foreach($card->superiors as $i => $superior)

			@if($i % 2 == 0)
				</div>
				<div class="row">
			@endif

			<div class="col-md-3 mtgcard-wrapper">
				<a href="{{ URL::route('card.create', $superior->id) }}">
					{{ Html::image($superior->imageUrl, $superior->name, ['class' => 'mtgcard']) }}
				</a><br>

				<div class="row">

					{{ Form::open(['route' => ['upvote', $superior->pivot->id], 'class' => 'form-inline vote-form']) }}
						<button type="submit" class="btn btn-light" style="color:green;padding:.2rem .55rem;margin:.2rem">
							Better
							<i class="fa fa-thumbs-up" style="font-size:24px;"></i>
							<span class="upvote-count">{{ $superior->pivot->upvotes }}</span>
						</button>
					{{ Form::close() }}

					{{ Form::open(['route' => ['downvote', $superior->pivot->id], 'class' => 'form-inline vote-form']) }}
						<button type="submit" class="btn btn-light" style="color:red;padding:.2rem .55rem;margin:.1rem">
							Not
							<i class="fa fa-thumbs-down" style="font-size:24px;"></i>
							<span class="downvote-count">{{ $superior->pivot->downvotes }}</span>
						</button>
					{{ Form::close() }}

				</div>
				<a href="{{ $superior->gathererUrl }}" rel="noopener nofollow" style="padding:.2rem .25rem">Gatherer</a>
			</div>
			@endforeach
		</div>
	</div>
	@else
	<div class="col-md-8" style="padding-top: 100px;">
		No updgrade needed.<br>
		Unless you'd like to <a class="tell_superior" href="{{ route('card.create', [$card->id]) }}">tell us about it</a>?
	</div>
	@endif

</div>