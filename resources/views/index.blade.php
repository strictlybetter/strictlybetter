@extends('layout')

@section('content')

	<h1>Browse</h1>

	{{ Form::search('quicksearch', isset($search) ? $search : null, ['id' => 'quicksearch', 'class' => 'form-control col-sm-6', 'placeholder' => 'Quick search']) }}
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

	$("#quicksearch").on('input', function(event) {

		clearTimeout(quicksearch_timer);
		quicksearch_timer = setTimeout(quicksearch, 50); 
	});

	function quicksearch(page) {

		if (page === undefined)
			page = 1;

		var search = $("#quicksearch").val();

		var params = new URLSearchParams({
			'search': search,
			'page':page
		});
		window.history.replaceState(null, '', '/?' + params.toString());

		if (quicksearch_ajax)
			quicksearch_ajax.abort();

		quicksearch_ajax = $.ajax({
			type: "GET",
			url: "{{ route('card.quicksearch') }}",
			dataType: "html",
			data: { 
				"search": search,
				"page": page
			},
			success: function(response) {
				$("#cards").html(response);
			}
		});
	}
	/*
	$(document).ready(function() { 
		if ($("#quicksearch").val()) {

			var params = new URLSearchParams(window.location.search);
			var page = params.get('page');
			quicksearch(page);
		}
	});*/

</script>
@stop