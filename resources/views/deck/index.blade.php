@extends('layout')

@section('meta')
	<meta name="Description" content="Upgrade your whole MTG deck with better cards">
	<title>StrictlyBetter - Upgrade Deck</title>
@stop

@section('content')

	<h1>Upgrade Deck</h1>

	{{ Form::open(['route' => 'deck.upgrade']) }}
		Paste your deck here<br>
		{{ Form::textarea('deck', null, ['class' => 'form-control', 'required', 'placeholder' => '4x Llanowar Elves&#x0a;2x Tinder Wall']) }}<br>
		<div class="row">
			<div class="col-md-2" style="padding-left: 0;">{{ Form::select('format', $formatlist, isset($format) ? $format : null, ['id' => 'format', 'class' => 'form-control']) }}</div>
			<div class="col-md-3">{{ Form::submit('Upgrade', ['class' => 'btn btn-primary']) }}</div>
		</div>

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