@extends('layout')

@section('meta')
	<meta name="Description" content="Browse and find strictly better MTG cards">
	<title>StrictlyBetter - Browse</title>
@stop

@section('content')

	<div class="container">
		<h1>Browse</h1>

		<div class="row">
			{{ Form::search('quicksearch', isset($search) ? $search : null, ['id' => 'quicksearch', 'class' => 'form-control col-sm-5', 'placeholder' => 'Quick search']) }}
			<span class="spinner-border spinner-border-sm search-spinner" role="status"></span>
			<span>{{ Form::select('format', $formatlist, isset($format) ? $format : null, ['id' => 'format', 'class' => 'form-control']) }}</span>
			<span>{{ Form::select('filters', $filterlist, isset($filters) ? $filters : null, ['id' => 'filters', 'multiple' => 'multiple', 'class' => 'form-control']) }}</span>
		</div>
		<br>
<!--
		<div class="row filterlist">
			<span class="form-check">
				{{ Form::checkbox('filter_less_colors', null, null, ['id' => 'filter_less_colors', 'class' => 'form-check-input', 'title' => 'Suggestions may have less or more colors, but must still be playable with colors of the original card']) }}
				<label for="filter_less_colors" class="form-check-label disable-select" title="Suggestions may have less or more colors, but must still be playable with colors of the original card">{{ Lang::get('card.filters.less_colors') }}</label>
			</span>

			<span class="form-check">
				{{ Form::checkbox('filter_subtypes_differ', null, null, ['id' => 'filter_subtypes_differ', 'class' => 'form-check-input', 'title' => 'Cards may have other tribes or be artifacts/enchantments/lands in addition to original cards types or vice versa']) }}
				<label for="filter_subtypes_differ" class="form-check-label disable-select" title="Cards may have other tribes or be artifacts/enchantments/lands in addition to original cards types or vice versa">{{ Lang::get('card.filters.subtypes_differ') }}</label>
			</span>

			<span class="form-check">
				{{ Form::checkbox('filter_more_colored_mana', null, null, ['id' => 'filter_more_colored_mana', 'class' => 'form-check-input', 'title' => 'Cards may cost more colored mana of the colors already present in original card, but converted mana cost must still be less or equal']) }}
				<label for="filter_more_colored_mana" class="form-check-label disable-select" title="Cards may cost more colored mana of the colors already present in original card, but converted mana cost must still be less or equal">{{ Lang::get('card.filters.more_colored_mana') }}</label>
			</span>

		</div>
-->
	</div>

	<div id="cards">
		
	</div>

@stop

@section('js')
<script>

	// Ajax query when quicksearch input is edited (with 50ms delay)
	var quicksearch_timer = null;
	var quicksearch_ajax = null;
	var initial_page = "{{ isset($page) ? $page : 1 }}";

	function quicksearch(page, push_state) {

		if (page === undefined)
			page = initial_page;
		else
			initial_page = page;

		var search = $("#quicksearch").val();
		var format = $('#format').find(":selected").val();
		var filters = $('#filters').val();

		var params = {
			'format': format,
			'search': search,
			'filters': filters,
			'page': page
		};

		var search_params = new URLSearchParams(params);

		// Replace url, so current browse page may copied, pasted and followed correctly
		if (push_state)
			window.history.pushState(params, '', '/?' + search_params.toString());
		else
			window.history.replaceState(params, '', '/?' + search_params.toString());

		if (quicksearch_ajax)
			quicksearch_ajax.abort();

		$(".search-spinner").show();

		quicksearch_ajax = $.ajax({
			type: "GET",
			url: "{{ route('card.quicksearch') }}",
			dataType: "html",
			data: {
				"format": format,
				"search": search,
				"filters": filters,
				"page": page
			},
			success: function(response) {
				$(".search-spinner").hide();
				$("#cards").html(response);

				// Toggle pagination buttons for mobile view
				$('ul.pagination li.active')
					.prev().addClass('show-mobile')
					.prev().addClass('show-mobile');
				$('ul.pagination li.active')
					.next().addClass('show-mobile')
					.next().addClass('show-mobile');
				$('ul.pagination li:last-child')
					.prev().addClass('show-mobile')
					.prev()
					.prev().addClass('show-mobile');
				$('ul.pagination')
					.find('li:first-child, li:last-child, li.active')
					.addClass('show-mobile');
			}
		});
	}

	$(document).ready(function() {

		$("#quicksearch").on('input', function(event) {

			clearTimeout(quicksearch_timer);
			quicksearch_timer = setTimeout(function() { quicksearch(1); }, 200); 
		});

		$("#format").on('change', function(event) {
			quicksearch();
		});

		$('#filters').multiselect({
			placeholder: 'No Label Filters',
			texts: {
				selectAll: "Strictly better only",
				unselectAll: "No Label Filters"
			},
			selectAll: true,
			onOptionClick: function(element, option) {
				quicksearch();
			},
			onSelectAll: function(element, option) {
				quicksearch();
			}
		});


		$("#cards").on('click', 'a.page-link', function(e) {
			e.preventDefault();

			var url = new URL(this.href);
			var page = url.searchParams.get('page');

			quicksearch(page ? page : 1, true);
		});

		$("#cards").on('click', 'a.card-link', function(e) {
			e.preventDefault();

			var url = new URL(this.href);
			var search = url.searchParams.get('search');

			$("#quicksearch").val(search);
			quicksearch(1, true);
		});


		quicksearch(initial_page);

		card_autocomplete("#quicksearch", 5, function(event, ui) {

			if ($("#quicksearch").val() != ui.item.value) {
				$("#quicksearch").val(ui.item.value);
				quicksearch(1);
			}
		});

		window.onpopstate = function(event) {

			var params = event.state;

			$("#quicksearch").val(params.search);
			$('#format').val(params.format);
			$('#filters').val(params.filters);
			initial_page = params.page ? params.page : 1;

			quicksearch();
		};

	});

</script>
@stop