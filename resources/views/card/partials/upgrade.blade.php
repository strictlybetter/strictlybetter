<div class="row">

	<div class="col-md-2 mtgcard-wrapper">
		<a href="{{ URL::route('card.create', $card->id) }}">
			{{ Html::image($card->imageUrl, $card->name, ['class' => 'mtgcard']) }}
		</a><br>
		<div class="row" style="margin-left:0px;">
			<a href="{{ $card->gathererUrl }}" rel="noopener nofollow" style="padding:.2rem .25rem">Gatherer</a>
		</div>
	</div>

	@if(count($card->superiors) > 0)
	<div class="col-md-1 text-center" style="padding-top: 100px;">
		<i class="fa fa-arrow-right" style="font-size:30px;"></i>
	</div>
	<div class="col-md-8 mtgcard-wrapper">
		<div class="row">
			@foreach($card->superiors as $superior)

			<div class="col-md-3 mtgcard-wrapper" style="padding-bottom: 20px;">
				<a href="{{ URL::route('card.create', $superior->id) }}">
					{{ Html::image($superior->imageUrl, $superior->name, ['class' => 'mtgcard']) }}
				</a><br>

				<div class="row" style="margin-left:0px;">
					{{ Form::open(['route' => ['downvote', $superior->pivot->id], 'class' => 'form-inline vote-form']) }}
						<button type="submit" class="btn btn-light" style="color:red;padding:.2rem .55rem">
							<i class="fa fa-thumbs-down" style="font-size:24px;"></i>
							<span class="downvote-count">{{ $superior->pivot->downvotes }}</span>
						</button>
					{{ Form::close() }}
					{{ Form::open(['route' => ['upvote', $superior->pivot->id], 'class' => 'form-inline vote-form']) }}
						<button type="submit" class="btn btn-light" style="color:green;padding:.2rem .55rem">
							<i class="fa fa-thumbs-up" style="font-size:24px;"></i>
							<span class="upvote-count">{{ $superior->pivot->upvotes }}</span>
						</button>
					{{ Form::close() }}


					<a href="{{ $superior->gathererUrl }}" rel="noopener nofollow" style="padding:.2rem .25rem">Gatherer</a>
				</div>
			</div>

			@endforeach
		</div>
	</div>
	@else
	<div class="col-md-8" style="padding-top: 100px;">
		No updates needed
	</div>
	@endif

</div>