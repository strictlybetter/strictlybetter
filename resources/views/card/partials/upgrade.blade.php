<?php 
	// No lazy loading. Also prevent reprints listing, if it wasn't intended.
	$reprint_count = $card->relationLoaded('functionalReprints') ? count($card->functionalReprints) : 0; 
?>
<div class="col-sm-3 cardpanel-current" style="min-height: {{ 405 + $reprint_count * 35}}px">
	<div class="row" style="position:relative">
		<div class="centered">

			@if($reprint_count > 0)
				@foreach($card->functionalReprints as $i => $reprint_card)

					<div class="mtgcard-wrapper reprint-stack reprint-{{ ($i >= 2) ? ($i) : $i }}">
						@if(Request::is('card/*'))
							<a class="card-link" href="{{ route('card.create', [$reprint_card->id]) }}" title="{{ $reprint_card->name }}">
						@else
							<a class="card-link" href="{{ route('index', ['format' => isset($format) ? $format : '', 'search' => $reprint_card->name, 'filters' => isset($filters) ? $filters : '']) }}" title="{{ $reprint_card->name }}">
						@endif
							{{ Html::image($reprint_card->imageUrl, $reprint_card->name, ['class' => 'mtgcard']) }}
							<span class="mtgcard-text">{{ $reprint_card->name }}</span>
						</a>
						<div class="row"></div>
						<!--<a class="btn btn-light btn-gatherer" href="{{ $reprint_card->gathererUrl }}" rel="noopener nofollow">Gatherer</a>-->
					</div>

				@endforeach
			@endif
		
			<div class="mtgcard-wrapper reprint-{{ ($reprint_count > 3) ? $reprint_count : $reprint_count }} reprint-primary" >
				@if(Request::is('card/*'))
					<a class="card-link" href="{{ route('card.create', [$card->id]) }}" title="{{ $card->name }}">
				@else
					<a class="card-link" href="{{ route('index', ['format' => isset($format) ? $format : '', 'search' => $card->name, 'filters' => isset($filters) ? $filters : '']) }}" title="{{ $card->name }}">
				@endif
					{{ Html::image($card->imageUrl, $card->name, ['class' => 'mtgcard']) }}
					<span class="mtgcard-text">{{ $card->name }}</span>
				</a><br>
				<div class="row"></div>
				@if($card->scryfall_link)<a class="btn btn-light btn-gatherer" href="{{ $card->scryfall_link }}" rel="noopener nofollow">Scryfall</a>@endif
				@if($card->multiverse_id)<a class="btn btn-light btn-gatherer" href="{{ $card->gathererUrl }}" rel="noopener nofollow">Gatherer</a>@endif
			</div>
		</div>
	</div>
</div>

<div class="col-sm-9 cardpanel-superior">
	<h4>Superiors</h4>
	<div class="row">
		
		@foreach($card->superiors as $i => $superior)
			@include('card.partials.relatedcard', ['related' => $superior, 'type' => 'superior'])
		@endforeach
		@if(Request::is('quicksearch'))
		<div class="mtgcard-wrapper newcard">
			<a class="card-create-link" href="{{ route('card.create', [$card->id]) }}" title="New supeior card for {{ $card->name }}">
				{{ Html::image(asset('image/card-back.jpg'), 'New superior card', ['class' => 'mtgcard']) }}
				<span class="mtgcard-text">New superior card</span>
				<span class="plus-sign"><b>+</b></span>
			</a>
		</div>
		@endif

		@if(count($card->superiors) == 0)
			<p>
			No upgrade needed.<br>
			Unless you'd like to <a class="tell_superior" href="{{ route('card.create', [$card->id]) }}">tell us about it</a>?
			</p>
		@endif
	</div>
</div>

