
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

	<div class="container"><br>
		<p>Sorry, can't find "{{ $search }}"</p>
	</div>

@else
	@foreach($cards as $card)
	
	<div class="row cardrow cardpanel-main">
		@include('card.partials.upgrade')
	</div>
	@endforeach

@endif
<br>
<div class="container">
	<div class="row">
		{{ $cards->links() }}
	</div>
</div>