<?php 
	// No lazy loading. Also prevent reprints listing, if it wasn't intended.
	$reprint_count = $card->relationLoaded('functionalReprints') ? count($card->functionalReprints) : 0; 
?>
<div class="col-sm-3 cardpanel-current" style="margin-bottom: {{ $reprint_count * 35}}px">
	<div class="row" style="position:relative">
		<div class="centered">

			@if($reprint_count > 0)
				@foreach($card->functionalReprints as $i => $reprint_card)

					<div class="mtgcard-wrapper reprint-stack reprint-{{ ($i >= 2) ? ($i) : $i }}">
						<a href="{{ URL::route('card.create', $reprint_card->id) }}">
							{{ Html::image($reprint_card->imageUrl, $reprint_card->name, ['class' => 'mtgcard']) }}
							<span class="mtgcard-text">{{ $reprint_card->name }}</span>
						</a>
						<div class="row"></div>
						<a class="link" href="{{ $card->gathererUrl }}" rel="noopener nofollow" style="padding:.2rem .25rem">Gatherer</a>
					</div>

				@endforeach
			@endif
		
			<div class="mtgcard-wrapper reprint-{{ ($reprint_count > 3) ? $reprint_count : $reprint_count }} reprint-primary" >
				<a href="{{ URL::route('card.create', $card->id) }}">
					{{ Html::image($card->imageUrl, $card->name, ['class' => 'mtgcard']) }}
					<span class="mtgcard-text">{{ $card->name }}</span>
				</a><br>
				<div class="row"></div>
				<a class="link" href="{{ $card->gathererUrl }}" rel="noopener nofollow" style="padding:.2rem .25rem">Gatherer</a>
			</div>
		</div>
	</div>
</div>

<div class="col-sm-9 cardpanel-superior">
	<h4>Superiors</h4>
	<div class="row">
		@if(count($card->superiors) > 0)
			@foreach($card->superiors as $i => $superior)
<!--
			@if($i % 2 == 0)
				</div>
				<div class="row">
			@endif
		-->

				@include('card.partials.relatedcard', ['related' => $superior, 'type' => 'superior'])
			@endforeach
		@else
			<p>
			No upgrade needed.<br>
			Unless you'd like to <a class="tell_superior" href="{{ route('card.create', [$card->id]) }}">tell us about it</a>?
			</p>
		@endif
	</div>
</div>

