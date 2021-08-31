<?php 
	// No lazy loading. Also prevent reprints listing, if it wasn't intended.
	$reprint_count = $card->relationLoaded('functionalReprints') ? count($card->functionalReprints) : 0; 
?>
<div class="col-sm-3 cardpanel-current" style="min-height: {{ 420 + $reprint_count * 35}}px">
	<div class="panel-toggles">
		@if(Request::is('quicksearch'))
			<a href="#" class="inferior-toggle">Hide Inferiors</a>
			<a href="#" class="superior-toggle">Hide Superiors</a>
		@endif
	</div>
	<div class="row" style="position:relative">
		@include('card.partials.maincard', ['card' => $card])
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
			<a class="card-create-link" href="{{ route('card.create', [$card->id]) }}" title="New superior card for {{ $card->name }}">
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

