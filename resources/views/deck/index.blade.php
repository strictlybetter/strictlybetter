@extends('layout')

@section('content')

	<h1>Upgrade Deck</h1>

	{{ Form::open(['route' => 'deck.upgrade']) }}
		Paste your deck here<br>
		{{ Form::textarea('deck', null, ['class' => 'form-control', 'required', 'placeholder' => '4x Llanowar Elves&#x0a;2x Tinder Wall']) }}<br>
		{{ Form::submit('Upgrade', ['class' => 'btn btn-primary']) }}
	{{ Form::close() }}
	<br>

	@if(isset($deckupgrades))

		<h2>Results</h2>

		@if(count($deckupgrades) === 0)
			<p>Congratulations! No upgrades needed.</p>
		@else
			@foreach($deckupgrades as $card)
				@include('card.partials.upgrade')
			@endforeach
		@endif
	@endif

@stop

@section('js')
<script>

</script>
@stop