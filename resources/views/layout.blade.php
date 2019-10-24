<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		@if(View::hasSection('meta'))
			@yield('meta')
		@else
			<meta name="Description" content="Find strictly better Magic the Gathering cards">
			<title>Strictly Better</title>
		@endif

		<meta name="apple-mobile-web-app-title" content="Strictly Better" />

		<!-- Preconnect to sites providing card images -->
		<link rel="preconnect" href="https://gatherer.wizards.com" />
		<link rel="preconnect" href="https://img.scryfall.com" />

		<link rel="icon" type="image/x-icon" href="{{ URL::to('favicon.ico') }}">
		<link rel="apple-touch-icon" sizes="180x180" href="{{ URL::to('apple-touch-icon.png') }}">
		<link rel="icon" type="image/png" sizes="32x32" href="{{ URL::to('favicon-32x32.png') }}">
		<link rel="icon" type="image/png" sizes="16x16" href="{{ URL::to('favicon-16x16.png') }}">	
		<link rel="manifest" href="{{ URL::to('site.webmanifest') }}">

		<link media="all" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
		<link media="all" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
		<link media="all" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
		<link media="all" href="{{ URL::to('css/select2.min.css') }}" rel="stylesheet">
		<link media="all" href="{{ URL::to('css/jquery.multiselect.css') }}" rel="stylesheet">
		<link media="all" href="{{ URL::to('css/main.css') }}" rel="stylesheet">
		@yield('head')
	</head>
	<body>
		<div class="container-fluid" style="padding:0">
			<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
				<a class="navbar-brand" href="{{ route('index') }}">Strictly Better</a>
				<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>

				<div class="collapse navbar-collapse" id="navbarSupportedContent">
					<ul class="navbar-nav mr-auto">
						<li class="nav-item {{ Request::is('/') ? 'active' : '' }}">
							<a class="nav-link" href="{{ route('index') }}">Browse</a>
						</li>
						<li class="nav-item {{ Request::is('deck') ? 'active' : '' }}">
							<a class="nav-link" href="{{ route('deck.index') }}">Upgrade Deck</a>
						</li>
						<li class="nav-item {{ Request::is('card', 'card/*') ? 'active' : '' }}">
							<a class="nav-link" href="{{ route('card.create') }}">Add Suggestion</a>
						</li>
						<li class="nav-item {{ Request::is('api-guide') ? 'active' : '' }}">
							<a class="nav-link" href="{{ route('api.guide') }}">API Guide</a>
						</li>
						<li class="nav-item {{ Request::is('about') ? 'active' : '' }}">
							<a class="nav-link" href="{{ route('about') }}">About</a>
						</li>
						<li class="nav-item {{ Request::is('changelog') ? 'active' : '' }}">
							<a class="nav-link" href="{{ route('changelog') }}">Changelog</a>
						</li>
					</ul>
					{{ Form::open(['id' => 'card-search-form', 'route' => 'card.search', 'autocomplete' => 'off', 'class' => 'form-inline my-2 my-lg-0']) }}
						<div class="ui-widget">
							<input id="card-search" name="term" class="form-control mr-sm-2" type="search" placeholder="Search Card" required minlength=2 aria-label="Search">
						</div>
						<button class="btn btn-success my-2 my-sm-0" type="submit">Search</button>
					{{ Form::close() }}
				</div>
			</nav>

			<br>
			@if ($errors->any())
				<div class="alert alert-danger alert-dismissible fade show" role="alert">
					<ul>
					@foreach ($errors->all() as $error)
						<li>{{ $error }}</li>
					@endforeach
					</ul>

					<button type="button" class="close" data-dismiss="alert" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
			@endif

			@yield('content')
		</div>

		<script src="https://code.jquery.com/jquery-3.4.1.min.js" integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script>
		<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
		<script src="{{ URL::to('js/select2/select2.min.js') }}"></script>
		<script src="{{ URL::to('js/jquery.multiselect.js') }}"></script>
	
		<script>
			$.fn.removeClassRegex = function(regex) {
				return $(this).removeClass(function(index, classes) {
					return classes.split(/\s+/).filter(function(c) {
						return regex.test(c);
					}).join(' ');
				});
			};
		</script>
		<script>

			$.ajaxSetup({
				headers: {
					'X-CSRF-TOKEN': "{{ csrf_token() }}"
				}
			});

			var card_cache = {};
			function card_autocomplete(selector, max_results, select_callback) {

				var autocomplete_query = null;

				$(selector).autocomplete({
					minLength: 2,
					delay: 200,
					source: function(request, response) {

						// Check cache before query
						if (request.term in card_cache) {
							response(card_cache[request.term + '|' + max_results]);
							return;
						}

						request.limit = max_results;

						if (autocomplete_query)
							autocomplete_query.abort();

						// Query
						autocomplete_query = $.getJSON("{{ route('card.autocomplete') }}", request, function(data, status, xhr) {
							card_cache[request.term + '|' + max_results] = data;
							response(data);
						});
					},
					select: select_callback
				}).focus(function() {

					// Show autocomplete options when re-focusing
					if ($(this).val().length >= 2)
						$(this).autocomplete("search", $(this).val());
				});
			}

			$(document).ready(function() { 

				// Card search
				card_autocomplete("#card-search", 25, function(event, ui) {

					// Automatically submit search if an autocomplete option was selected
					$("#card-search").val(ui.item.value);
					$("#card-search-form").submit();
				});

				// Upvotes / Downvote
				$(".container-fluid").on("submit", ".vote-form", function(event) {
					event.preventDefault();

					var row = $(this).closest('.row');

					$.ajax({
						type: "POST",
						url: $(this).attr('action'),
						dataType: "json",
						success: function(response) {
							$(row).find('.upvote-count').text(response.upvotes);
							$(row).find('.downvote-count').text(response.downvotes);
						}
					});
				});
			});

		</script>

		@yield('js')
	</body>
</html>
