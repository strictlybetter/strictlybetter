
<div class="container">
	<div class="row">
		{{ $cards->links() }}

		@if($cards->currentPage() == 1 && !$cards->hasMorePages())
			<ul class="pagination" role="navigation">
				<li class="page-item disabled" aria-disabled="true" aria-label="« Previous">
					<span class="page-link" aria-hidden="true">‹</span>
				</li>
				<li class="page-item active" aria-current="page"><span class="page-link">1</span></li>
				<li class="page-item disabled" aria-disabled="true" aria-label="Next »">
					<span class="page-link" aria-hidden="true">›</span>
				</li>
			</ul>
		@endif
		<span style="color:darkgrey;margin: 6px 12px;">{{ $cards->total() }} results</span>
	</div>
</div>

@if(count($cards) == 0)
	<!--<div class="row cardrow">
		<div class="col-lg-4 cardpanel-inferior"></div>
		<div class="row col-lg-8" style="margin:0;padding:0">
			<div class="col-lg-4 cardpanel-current">
				<p>Sorry, can't find "{{ $search }}"</p>
			</div>
			<div class="col-lg-8 cardpanel-superior"></div>
		</div>

	</div>-->
	<div class="container"><br>
		<p>Sorry, can't find "{{ $search }}"</p>
	</div>

	<!--<br><p>
			@if(isset($search) && $search)
				There are no upgrades for "{{ $search }}" in 
				{{ (isset($format) && $format) ? ucfirst($format) : 'any format' }}
				<br>
			@else
				No upgrades found.<br>
			@endif
			Perhaps you'd like to <a href="{{ route('card.create') }}">tell us otherwise</a>?

		</p>-->
@else
	@foreach($cards as $card)
	
	<div class="row cardrow">

		@if($card->relationLoaded('inferiors'))
		<div class="col-sm-4 cardpanel-inferior">
			<h4>Inferiors</h4>
			<div class="row" style="float:right">

				@if(count($card->inferiors) == 0)
					<p style="margin-right: 25px;">No budget options found.</p>
				@endif

				@if(Request::is('quicksearch'))
				<div class="mtgcard-wrapper newcard">
					<a class="card-create-link" href="{{ route('card.create', [$card->id, 'inferior' => 1]) }}" title="New inferior card for {{ $card->name }}">
						{{ Html::image(asset('image/card-back.jpg'), 'New inferior card', ['class' => 'mtgcard']) }}
						<span class="mtgcard-text">New inferior card</span>
						<span class="plus-sign"><b>+</b></span>
					</a>
				</div>
				@endif
				
				@foreach($card->inferiors as $i => $inferior)
					@include('card.partials.relatedcard', ['related' => $inferior, 'type' => 'inferior'])
				@endforeach
				
			</div>
		</div>
		@endif
	
		<div class="row col-sm-8" style="margin:0;padding:0;">
			@include('card.partials.upgrade')
		</div>
	</div>
	@endforeach

@endif
<br>
<div class="container">
	<div class="row">
		{{ $cards->links() }}
	</div>
</div>