<?php 
	// No lazy loading. Also prevent reprints listing, if it wasn't intended.
	$reprint_count = $card->relationLoaded('functionalReprints') ? count($card->functionalReprints) : 0; 
?>
<div class="col-12 col-sm-4 col-md-4 col-lg-3 col-xl-3 cardpanel-current" style="min-height: {{ 420 + $reprint_count * 35}}px">
	@include('card.partials.maincard', ['card' => $card])
</div>

<div class="col-12 col-sm-8 col-md-8 col-lg-9 col-xl-9 cardpanel-tabs">

	<ul class="nav nav-tabs panel-toggles cardlist-tabs">
		<li class="nav-item">
			<a class="nav-link superior-toggle active {{ $card->superiors->count() ? '' : 'no-items' }}" id="superior-tab-{{ $card->id }}" data-bs-toggle="tab" href="#superiors-{{ $card->id }}" role="tab" aria-controls="superiors-{{ $card->id }}" aria-selected="true" title="Superior cards">
				<i class="fa fa-thumbs-up"></i>
				Superiors ({{ $card->superiors->count() }})
			</a>
		</li>
		@if($card->relationLoaded('inferiors'))
		<li class="nav-item">
			<a class="nav-link inferior-toggle {{ $card->inferiors->count() ? '' : 'no-items' }}" id="inferior-tab-{{ $card->id }}" data-bs-toggle="tab" href="#inferiors-{{ $card->id }}" role="tab" aria-controls="inferiors-{{ $card->id }}" aria-selected="false" title="Inferior cards">
				<i class="fa fa-thumbs-down"></i>
				Inferiors ({{ $card->inferiors->count() }})
			</a>
		</li>
		@endif
		@if($card->relationLoaded('functionality'))
		<li class="nav-item">
			<a class="nav-link typevariants-toggle {{ $card->functionality->typevariantcards->count() ? '' : 'no-items' }}" id="typevariants-tab-{{ $card->id }}" data-bs-toggle="tab" href="#typevariants-{{ $card->id }}" role="tab" aria-controls="typevariants-{{ $card->id }}" aria-selected="false" title="Type variants">
				<i class="nav-item-typevariants">=</i>
				Type variants ({{ $card->functionality->typevariantcards->count() }}) 
			</a>
		</li>
		@endif
		<li class="nav-item">
			<a class="nav-link alternatives-toggle no-items" id="alternatives-tab-{{ $card->id }}" data-bs-toggle="tab" href="#alternatives-{{ $card->id }}" role="tab" aria-controls="alternatives-{{ $card->id }}" aria-selected="false" title="Alternatives are not yet available">
				<i class="nav-item-alternative">&thickapprox;</i>
				Alternatives
			</a>
		</li>
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
						Unless you'd like to <a class="tell_superior" href="{{ route('card.create', [$card->id]) }}">tell us about it</a>?<br>
						<br>
						@include('card.partials.tablinks')
					</p>
				@endif
			</div>
		</div>

		@if($card->relationLoaded('inferiors'))
		<div class="tab-pane fade" id="inferiors-{{ $card->id }}" role="tabpanel" aria-labelledby="inferior-tab-{{ $card->id }}">
			<div class="cardpanel cardpanel-inferior">
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
					<p class="cardpanel-not-found">
						No budget options found.<br>
						<br>
						@include('card.partials.tablinks')
					</p>
				@endif
			</div>
		</div>
		@endif
		@if($card->relationLoaded('functionality'))
		<div class="tab-pane fade" id="typevariants-{{ $card->id }}" role="tabpanel" aria-labelledby="typevariants-tab-{{ $card->id }}">
			<div class="cardpanel cardpanel-typevariants">
				@if(count($card->functionality->typevariantcards) == 0)
					<p class="cardpanel-not-found">
						No type variants found.<br>
						<br>
						@include('card.partials.tablinks')
					</p>
				@else
					@foreach($card->functionality->typevariantcards as $i => $related)
						@include('card.partials.relatedcard', ['related' => $related, 'type' => 'functionalitygroup'])
					@endforeach
				@endif
			</div>
		</div>
		@endif
		<div class="tab-pane fade" id="alternatives-{{ $card->id }}" role="tabpanel" aria-labelledby="alternatives-tab-{{ $card->id }}">
			<div class="cardpanel cardpanel-alternative">
				<p class="cardpanel-not-found">Alternatives are not yet available.</p>
			</div>
		</div>

	</div>

</div>
