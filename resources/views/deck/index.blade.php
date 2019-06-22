@extends('layout')

@section('meta')
	<meta name="Description" content="Upgrade your whole MTG deck with better cards">
	<title>StrictlyBetter - Upgrade Deck</title>
@stop

@section('content')

	<div class="container">
		<h1>Upgrade Deck</h1>

		{{ Form::open(['route' => 'deck.upgrade']) }}
			Paste your deck here<br>
			{{ Form::textarea('deck', null, ['class' => 'form-control', 'required', 'placeholder' => '4x Llanowar Elves&#x0a;2x Tinder Wall']) }}<br>
			<div class="row">
				<span class="form-group row" >
					<span><label for="format" style="padding: 6px 6px 0px 0px">Suggestions from format</label></span>
					<span>{{ Form::select('format', $formatlist, isset($format) ? $format : null, ['id' => 'format', 'class' => 'form-control']) }}</span>
				</span>
				<span class="form-group" style="margin-left: 20px">{{ Form::submit('Upgrade', ['class' => 'btn btn-primary']) }}</span>
			</div>

		{{ Form::close() }}
	</div>
	<div class="container collapse-inferior" id="upgrade_view_container">

		@if(isset($deckupgrades))

			<h2>Results</h2>

			@if(count($deckupgrades) === 0)
				<p>Congratulations! No upgrades needed.</p>
			@else
				@foreach($deckupgrades as $card)
					<div class="row cardrow">
						@include('card.partials.upgrade')
					</div>
				@endforeach
			@endif
		@endif
		<br><br>
	</div>

@stop

@section('js')
<script>

</script>
@stop