@if(isset($reprint_count) && $reprint_count > 0)
	@foreach($card->functionalReprints as $i => $reprint_card)

		<div class="mtgcard-wrapper reprint-stack reprint-{{ ($i >= 2) ? ($i) : $i }}">
			@if(Request::is('card/*') || Request::is('upgradeview/*'))
				<a class="card-link" href="{{ route('card.create', [$reprint_card->id]) }}" title="{{ $reprint_card->name }}">
			@else
				<a class="card-link" href="{{ route('index', ['format' => isset($format) ? $format : '', 'search' => $reprint_card->name, 'filters' => isset($filters) ? $filters : '']) }}" title="{{ $reprint_card->name }}">
			@endif
				<div class="flipper">
					{{ Html::image(asset('image/card-back.jpg'), $reprint_card->name, ['class' => 'mtgcard back', 'loading' => 'eager']) }}
					{{ Html::image($reprint_card->imageUrl, $reprint_card->name, ['class' => 'mtgcard front', 'loading' => 'lazy', 'alt-src' => $reprint_card->gathererImg]) }}
					<span class="spinner-border spinner-border-xl mtgcard-loadspinner" role="status"></span>
				</div>
				<span class="mtgcard-text">{{ $reprint_card->name }}</span>	
			</a>
			<div class="row"></div>
			<!--<a class="btn btn-gatherer" href="{{ $reprint_card->gathererUrl }}" rel="noopener nofollow">Gatherer</a>-->
		</div>

	@endforeach
@endif

<div class="mtgcard-wrapper reprint-{{ isset($reprint_count) ? $reprint_count : 0 }} reprint-primary" >
		<a class="card-link" href="{{ route('index', ['format' => isset($format) ? $format : '', 'search' => $card->name, 'filters' => isset($filters) ? $filters : '']) }}" title="{{ $card->name }}">
		<div class="flipper">
			{{ Html::image(asset('image/card-back.jpg'), $card->name, ['class' => 'mtgcard back', 'loading' => 'eager']) }}
			{{ Html::image($card->imageUrl, $card->name, ['class' => 'mtgcard front', 'loading' => 'lazy', 'alt-src' => $card->gathererImg]) }}
			<span class="spinner-border spinner-border-xl mtgcard-loadspinner" role="status"></span>
		</div>
		<span class="mtgcard-text">{{ $card->name }}</span>
	</a>
	
	<div class="card-external-links">
		@if($card->scryfall_link)<a class="btn btn-gatherer" href="{{ $card->scryfall_link }}" rel="noopener nofollow">Scryfall</a>@endif
		@if($card->multiverse_id)<a class="btn btn-gatherer" href="{{ $card->gathererUrl }}" rel="noopener nofollow">Gatherer</a>@endif
	</div>
</div>
