@extends('layout')

@section('meta')
	<meta name="Description" content="Find all the strictly better MTG cards">
	<title>Strictly Better - Browse</title>
@stop

@section('content')

	<div class="container">
		<h1>Browse</h1>

		<div class="row">
			{{ Form::search('quicksearch', isset($search) ? $search : null, ['id' => 'quicksearch', 'class' => 'form-control col-sm-4', 'placeholder' => 'Quick search', 'aria-label' => 'Quick search', 'maxlength' => 100]) }}
			<span class="spinner-border spinner-border-sm search-spinner" role="status"></span>
			<span>{{ Form::select('tribe', $tribelist, isset($tribe) ? $tribe : null, ['id' => 'tribe', 'class' => 'form-control']) }}</span>
			<span>{{ Form::select('format', $formatlist, isset($format) ? $format : null, ['id' => 'format', 'class' => 'form-control', 'aria-label' => 'Format']) }}</span>
			<span>{{ Form::select('filters', $filterlist, isset($filters) ? $filters : null, ['id' => 'filters', 'multiple' => 'multiple', 'class' => 'form-control', 'aria-label' => 'Filters']) }}</span>
			<span>{{ Form::select('order', $orderlist, isset($order) ? $order : 'null', ['id' => 'order', 'class' => 'form-control', 'aria-label' => 'Sort by']) }}</span>
		</div>
		<br>
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
	var last_search_term = $("#quicksearch").val();

	function quicksearch(page, push_state, scrollTop) {

		if (page === undefined)
			page = initial_page;
		else
			initial_page = page;

		var search = $("#quicksearch").val();
		var tribe = $("#tribe").find(":selected").val();
		var format = $('#format').find(":selected").val();
		var filters = $('#filters').val();
		var order = $('#order').val();

		last_search_term = search;

		var params = {
			'tribe': tribe,
			'format': format,
			'search': search,
			'filters': filters,
			'order': order,
			'page': page
		};

		// Omit empty params
		for (var prop in params) {
			if (params.hasOwnProperty(prop) && params[prop] == '')
				delete params[prop];
		}

		var search_params = new URLSearchParams(params);

		// Replace url, so current browse page may copied, pasted and followed correctly
		if (push_state) {

			// Replace current state first, so we can remember the scroll position
			if (window.history.state !== undefined) {

				current_state = window.history.state;

				if (current_state.scrollTop !== undefined)
					delete current_state.scrollTop;

				current_search_params = new URLSearchParams(current_state);
				current_state.scrollTop = $(window).scrollTop();

				window.history.replaceState(current_state, '', '/?' + current_search_params.toString());
			}

			window.history.pushState(params, '', '/?' + search_params.toString());
		}
		else if (scrollTop === undefined) {
			params.scrollTop = $(window).scrollTop();
			window.history.replaceState(params, '', '/?' + search_params.toString());
		}

		if (quicksearch_ajax)
			quicksearch_ajax.abort();

		$(".search-spinner").show();

		quicksearch_ajax = $.ajax({
			type: "GET",
			url: "{{ route('card.quicksearch') }}",
			dataType: "html",
			data: {
				"tribe": tribe,
				"format": format,
				"search": search,
				"filters": filters,
				"order": order,
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

				// Scroll to where we were, or back to top if history is not relevant
				if (scrollTop !== undefined)
					$(window).scrollTop(scrollTop);
				/*
				else if (!isScrolledIntoView('.pagination'))
					$(window).scrollTop(0);
				*/

				register_card_image_handlers('#cards');
			}
		});
	}

	function scroll_to_element(selector, scrollTo) {

		// If no element is defined, find first visible element
		if (!scrollTo) {
			var cutoff = $(window).scrollTop();
			$('.cardpanel-current .mtgcard-wrapper').each(function() {
				if ($(this).offset().top > cutoff) {
					scrollTo = this;
					return false;
				}
			});
			if (!scrollTo)
				scrollTo = document.body;
		}

		var offset = scrollTo.getBoundingClientRect().top;

		setTimeout(function(){ 
			var current = scrollTo.getBoundingClientRect().top + window.scrollY;
			$('html').animate({ scrollTop: current - offset }, 100);
		}, 405);

	}

	function isScrolledIntoView(elem)
	{
		var docViewTop = $(window).scrollTop();
		var docViewBottom = docViewTop + $(window).height();

		var elemTop = $(elem).offset().top;
		var elemBottom = elemTop + $(elem).height();

		return ((elemBottom <= docViewBottom) && (elemTop >= docViewTop));
	}

	$(document).ready(function() {

		$("#format").select2({
			allowClear: true, 
			placeholder: "Any Format",

		}).on('change', function(event) {
			quicksearch(initial_page, true);
		});

		$("#tribe").select2({
			allowClear: true, 
			placeholder: "Any Tribe",

		}).on('change', function(event) {
			quicksearch(initial_page, true);
		});

		$("#quicksearch").on('input', function(event) {

			clearTimeout(quicksearch_timer);
			quicksearch_timer = setTimeout(function() { quicksearch(1); }, 200); 
		});

		$('#filters').multiselect({
			placeholder: 'No Label Filters',
			texts: {
				selectAll: "Strictly better only",
				unselectAll: "No Label Filters"
			},
			selectAll: true,
			onOptionClick: function(element, option) {
				quicksearch(initial_page, true);
			},
			onSelectAll: function(element, option) {
				quicksearch(initial_page, true);
			}
		});

		$("#order").on('change', function(event) {
			quicksearch(initial_page, true);
		});

		$("#cards").on('click', 'a.page-link', function(e) {
			e.preventDefault();

			var url = new URL(this.href);
			var page = url.searchParams.get('page');

			quicksearch(page ? page : 1, true, 0);

		}).on('click', 'a.card-link', function(e) {
			e.preventDefault();

			var url = new URL(this.href);
			var search = url.searchParams.get('search');

			$("#quicksearch").val(search);
			quicksearch(1, true, 0);

		});

		register_tabs("#cards");

/*
		register_tabs("#cards", function(el, event) {
			let anchor = $(el).attr('href');
			save_state(get_search_params(), false, undefined, anchor);
		});
*/
		quicksearch(initial_page);

		card_autocomplete("#quicksearch", 5, function(event, ui) {

			if (last_search_term != ui.item.value) {
				$("#quicksearch").val(ui.item.value);
				quicksearch(1, false);
			}
		});

		window.onpopstate = function(event) {

			if (event.state === undefined || event.state === null) {
				quicksearch();
				return;
			}

			var params = event.state;

			$("#quicksearch").val(params.search);
			$('#tribe').val(params.tribe).trigger('change.select2');
			$('#format').val(params.format).trigger('change.select2');
			$('#filters').val(params.filters);
			$('#order').val(params.order);
			initial_page = params.page ? params.page : 1;

			$('#filters').multiselect('reload');

			quicksearch(initial_page, false, params.scrollTop);
		};

	});

</script>
@stop