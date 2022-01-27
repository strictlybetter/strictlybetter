<?php 
	// No lazy loading. Also prevent reprints listing, if it wasn't intended.
	$reprint_count = $card->relationLoaded('functionalReprints') ? count($card->functionalReprints) : 0; 
?>
<div class="col-sm-3 cardpanel-current" style="min-height: {{ 420 + $reprint_count * 35}}px">
	<div class="row" style="position:relative">
		@include('card.partials.maincard', ['card' => $card])
	</div>
</div>

<div class="col-sm-9 cardpanel-tabs">

	<ul class="nav nav-tabs panel-toggles cardlist-tabs">
		<li class="nav-item">
			<a class="nav-link superior-toggle active" id="superior-tab-{{ $card->id }}" data-bs-toggle="tab" href="#superiors-{{ $card->id }}" role="tab" aria-controls="superiors-{{ $card->id }}" aria-selected="true" title="Superior cards">
				<i class="fa fa-thumbs-up"></i>
				Superiors ({{ $card->superiors->count() }})
			</a>
		</li>
		@if($card->relationLoaded('inferiors'))
		<li class="nav-item">
			<a class="nav-link inferior-toggle" id="inferior-tab-{{ $card->id }}" data-bs-toggle="tab" href="#inferiors-{{ $card->id }}" role="tab" aria-controls="inferiors-{{ $card->id }}" aria-selected="false" title="Inferior cards">
				<i class="fa fa-thumbs-down"></i>
				Inferiors ({{ $card->inferiors->count() }})
			</a>
		</li>
		<li class="nav-item">
			<a disabled class="nav-link alternatives-toggle" id="alternatives-tab-{{ $card->id }}" data-bs-toggle="tab" href="#alternatives-{{ $card->id }}" role="tab" aria-controls="alternatives-{{ $card->id }}" aria-selected="false" title="Alternatives are not yet available">
				<i class="nav-item-alternative">&thickapprox;</i>
				Alternatives
			</a>
		</li>
		@endif
	</ul>

	<div class="tab-content" id="tab-content-{{ $card->id }}">

		<div class="tab-pane fade show active" id="superiors-{{ $card->id }}" role="tabpanel" aria-labelledby="superior-tab-{{ $card->id }}">
			<div class="cardpanel cardpanel-superior">
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
					<p class="cardpanel-not-found">
					No upgrade needed.<br>
					Unless you'd like to <a class="tell_superior" href="{{ route('card.create', [$card->id]) }}">tell us about it</a>?
					</p>
				@endif
			</div>
		</div>

		@if($card->relationLoaded('inferiors'))
		<div class="tab-pane fade" id="inferiors-{{ $card->id }}" role="tabpanel" aria-labelledby="inferior-tab-{{ $card->id }}">
			<div class="cardpanel cardpanel cardpanel-inferior">
				@foreach($card->inferiors as $i => $inferior)
					@include('card.partials.relatedcard', ['related' => $inferior, 'type' => 'inferior'])
				@endforeach

				@if(Request::is('quicksearch'))
				<div class="mtgcard-wrapper newcard">
					<a class="card-create-link" href="{{ route('card.create', [$card->id, 'inferior' => 1]) }}" title="New inferior card for {{ $card->name }}">
						{{ Html::image(asset('image/card-back.jpg'), 'New inferior card', ['class' => 'mtgcard']) }}
						<span class="mtgcard-text">New inferior card</span>
						<span class="plus-sign"><b>+</b></span>
					</a>
				</div>
				@endif
			
				@if(count($card->inferiors) == 0)
					<p class="cardpanel-not-found">No budget options found.</p>
				@endif
			</div>
		</div>
		@endif
		<div class="tab-pane fade" id="alternatives-{{ $card->id }}" role="tabpanel" aria-labelledby="alternatives-tab-{{ $card->id }}">
			<p class="cardpanel-not-found">Alternatives are not yet available.</p>
		</div>

	</div>

</div>
