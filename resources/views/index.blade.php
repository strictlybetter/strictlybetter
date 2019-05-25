@extends('layout')

@section('content')

	<h1>Browse</h1>

	{{ $cards->links() }}
	
	@foreach($cards as $card)
		@include('card.partials.upgrade')
	@endforeach

	{{ $cards->links() }}

@stop

@section('js')
<script>

</script>
@stop