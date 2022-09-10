
@if(count($card->categoryCounts) > 0)
	<?php 
		$i = 0; 
		$max = count($card->categoryCounts) - 1; 
	?>
	There are
	@foreach($card->categoryCounts as $category => $info)
		<a data-bs-toggle="tab" href="#{{ $category }}-{{ $card->id }}" role="tab" aria-controls="{{ $category }}-{{ $card->id }}" aria-selected="true" title="{{ $info['title'] }}">{{ $info['count'] }} {{ $info['title'] }}</a>{{ $i < $max ? $i == $max-1 ? ' and ' : ', ' : '' }}
		<?php $i++; ?>
	@endforeach
	available though.
@endif