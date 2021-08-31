@extends('layout')

@section('meta')
	<meta name="Description" content="Help by voting for cards others have suggested">
	<title>Strictly Better - Help Voting</title>
@stop

@section('content')

	<div class="container">
		<h1>Help Voting</h1>

		<p>Voting helps determine validity of the suggestions.</p>

		@if($inferior && $superior)

		<br>
		<p>Instead of <b>{{ $inferior->name }}</b> should I play <b>{{ $superior->name }}</b> ?</p>

		<div class="row">
			
			<div class="col-sm-3">
				<div class="row" style="position:relative">
					@include('card.partials.maincard', ['card' => $inferior])
				</div>
			</div>
			<div class="col-sm-9">
				<div class="row" style="position:relative">
					@include('card.partials.relatedcard', ['related' => $superior, 'type' => 'superior'])
				</div>
			</div>
			
		</div>
		@else
			<p>Unfortunately, we couldn't find any suggestions to vote for.</p>
		@endif
	</div>

@stop

@section('js')
<script>
	set_vote_refreshing(true);
</script>
@stop