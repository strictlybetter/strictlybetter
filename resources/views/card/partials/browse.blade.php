
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

@if(count($cards) == 0)
	<br>
	<p>
		@if(isset($term))
			There are no upgrades for "{{ $term }}".<br>
		@else
			No upgrades found.
		@endif
		Perhaps you'd like to <a href="{{ route('card.create') }}">tell us otherwise</a>?
	</p>
@else
	@foreach($cards as $card)
		@include('card.partials.upgrade')
	@endforeach
@endif

{{ $cards->links() }}