@extends('layout')

@section('meta')
	<meta name="Description" content="Browse and find strictly better MTG cards">
	<title>StrictlyBetter - Browse</title>
@stop

@section('content')

	<h1>Browse</h1>

	<div class="row">
		{{ Form::search('quicksearch', isset($search) ? $search : null, ['id' => 'quicksearch', 'class' => 'form-control col-sm-5', 'placeholder' => 'Quick search']) }}
		<span>{{ Form::select('format', $formatlist, request()->input('format') ? request()->input('format') : null, ['id' => 'format', 'class' => 'form-control']) }}</span>
	</div>
	<br>

	<div id="cards">
		@include('card.partials.browse')
	</div>

@stop

@section('js')
<script>

	// Ajax query when quicksearch input is edited (with 50ms delay)
	var quicksearch_timer;
	var quicksearch_ajax;

	$(document).ready(function() {

		$("#quicksearch").on('input', function(event) {

			clearTimeout(quicksearch_timer);
			quicksearch_timer = setTimeout(quicksearch, 50); 
		});

		$("#format").on('change', function(event) {
			quicksearch();
		});

		function quicksearch(page) {

			if (page === undefined)
				page = 1;

			var search = $("#quicksearch").val();
			var format = $('#format').find(":selected").val();

			var params = new URLSearchParams({
				'format': format,
				'search': search,
				'page': page
			});
			window.history.replaceState(null, '', '/?' + params.toString());

			if (quicksearch_ajax)
				quicksearch_ajax.abort();

			quicksearch_ajax = $.ajax({
				type: "GET",
				url: "{{ route('card.quicksearch') }}",
				dataType: "html",
				data: {
					"format": format,
					"search": search,
					"page": page
				},
				success: function(response) {
					$("#cards").html(response);
				}
			});
		}
	});

</script>
@stop