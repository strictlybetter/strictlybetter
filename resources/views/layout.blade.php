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
		@foreach(config('externals.preconnect') as $precon_uri)
			<link rel="preconnect" href="{{ $precon_uri }}" />
		@endforeach

		<link rel="icon" type="image/x-icon" href="{{ URL::to('favicon.ico') }}">
		<link rel="apple-touch-icon" sizes="180x180" href="{{ URL::to('apple-touch-icon.png') }}">
		<link rel="icon" type="image/png" sizes="32x32" href="{{ URL::to('favicon-32x32.png') }}">
		<link rel="icon" type="image/png" sizes="16x16" href="{{ URL::to('favicon-16x16.png') }}">	
		<link rel="manifest" href="{{ URL::to('site.webmanifest') }}">

		<link media="all" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css" integrity="sha384-zCbKRCUGaJDkqS1kPbPd7TveP5iyJE0EjAuZQTgFLD2ylzuqKfdKlfG/eSrtxUkn" crossorigin="anonymous">

		<link media="all" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
		<link media="all" href="{{ URL::to('css/select2.min.css') }}" rel="stylesheet">
		<link media="all" href="{{ URL::to('css/jquery.multiselect.css') }}" rel="stylesheet">
		<link media="all" href="{{ URL::to('css/main.css') . '?q=' . filemtime(public_path() . '/css/main.css') }}" rel="stylesheet">
		@yield('head')
	</head>
	<body>
		<div class="container-fluid" style="padding:0">
			<nav class="navbar sticky-top navbar-expand-lg navbar-dark bg-dark">
				<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>
				<a class="navbar-brand" href="{{ route('index') }}">Strictly Better</a>
				<div class="collapse navbar-collapse" id="navbarSupportedContent">
					<ul class="navbar-nav mr-auto">
						<li class="nav-item {{ Request::is('/') ? 'active' : '' }}">
							<a title="Browse" class="nav-link" href="{{ route('index') }}">Browse</a>
						</li>
						<li class="nav-item {{ Request::is('deck') ? 'active' : '' }}">
							<a title="Find upgrade suggestions for all the cards in your deck" class="nav-link" href="{{ route('deck.index') }}">Upgrade Deck</a>
						</li>
						<li class="nav-item {{ Request::is('card', 'card/*') ? 'active' : '' }}">
							<a title="New suggestions are always welcome" class="nav-link" href="{{ route('card.create') }}">Add Suggestion</a>
						</li>
						<li class="nav-item {{ Request::is('votehelp/*') ? 'active' : '' }} dropdown" id="nav-helpvoting">
							<a class="nav-link dropdown-toggle" href="{{ route('votehelp.low-on-votes') }}" role="button" aria-haspopup="true" aria-expanded="false">
								Help By Voting
							</a>
							<div class="dropdown-menu" aria-labelledby="nav-helpvoting">
								<a class="dropdown-item {{ Request::is('votehelp/low-on-votes') ? 'active' : '' }}" href="{{ route('votehelp.low-on-votes') }}" 
									title="Help determine if fresh suggestions with low vote count are valid or not">📉 Low on Votes</a>
								<a class="dropdown-item {{ Request::is('votehelp/disputed') ? 'active' : '' }}" href="{{ route('votehelp.disputed') }}" 
									title="Help find a final verdict for disputed suggestions with similar amount of upvotes and downvotes">&#x2694; Disputed</a>
								<a class="dropdown-item {{ Request::is('votehelp/spreadsheets') ? 'active' : '' }}" href="{{ route('votehelp.spreadsheets') }}"
									title="Help validate suggestions people have listed elsewhere">&#127760; External Sources</a>
							</div>
						</li>
						<li class="nav-item {{ Request::is('api-guide') ? 'active' : '' }}">
							<a title="API Guide provides information how to use this site for other progams" class="nav-link" href="{{ route('api.guide') }}">API Guide</a>
						</li>
						<li class="nav-item {{ Request::is('about') ? 'active' : '' }}">
							<a title="About" class="nav-link" href="{{ route('about') }}">About</a>
						</li>
						<li class="nav-item {{ Request::is('changelog') ? 'active' : '' }}">
							<a title="Changes the website has gone through over time" class="nav-link" href="{{ route('changelog') }}">Changelog</a>
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

		<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
		<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-fQybjgWLrvvRgtW6bFlB7jaZrFsaBXjsOMm/tB9LTS58ONXgqbR9W8oWht/amnpF" crossorigin="anonymous"></script>

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

			$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}" } });
			@if(config('session.lifetime') > 0)
				setInterval(refreshCsrf, {{ (config('session.lifetime') * 60 - 30) * 1000 }});

				function refreshCsrf() {
					$.ajax({
						url: '/refresh-csrf',
						method: 'post'
					}).then(function (response) {
						$('input[name="_token"]').val(response.token);
						$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': response.token } });
					});
				}
			@endif

			const dropdown = $(".dropdown");
			const dropdownToggle = $(".dropdown-toggle");
			const dropdownMenu = $(".dropdown-menu");

			$(window).on("load resize", function() {
				if (this.matchMedia("(min-width: 768px)").matches) {
					dropdown.hover(function() {
						$(this).addClass('show');
						$(this).find(dropdownToggle).attr("aria-expanded", "true");
						$(this).find(dropdownMenu).addClass('show');
					},
					function() {
						$(this).removeClass('show');
						$(this).find(dropdownToggle).attr("aria-expanded", "false");
						$(this).find(dropdownMenu).removeClass('show');
					});
				} else {
					dropdown.off("mouseenter mouseleave");
				}
			});

			var vote_refresh = false;

			function set_vote_refreshing(value) {
				vote_refresh = value;
			}

			function card_autocomplete(selector, max_results, select_callback) {

				var autocomplete_query = null;

				$(selector).autocomplete({
					minLength: 2,
					delay: 200,
					source: function(request, response) {

						request.limit = max_results;

						if (autocomplete_query)
							autocomplete_query.abort();

						// Query
						autocomplete_query = $.getJSON("{{ route('card.autocomplete') }}", request, function(data, status, xhr) {
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

			function register_tabs(selector, callback) {
				$(selector).on('click', 'a[role="tab"]', function (event) {
					event.preventDefault();
					let target = $(this).attr('href');
					$('.cardlist-tabs a[href="' + target + '"]').tab('show');

					if (callback !== undefined)
						callback(this, event);
				});
			}

			function register_card_image_handlers(selector = '') {

				selector = (selector == '') ? '' : (selector + ' ');

				// Try alternative image urls if loading fails
				$(selector + "img.mtgcard").on("error", function() {
					var src_alt = this.getAttribute('alt-src');
					if (src_alt && src_alt !== this.src) {

						// Replace 'data-src' first, so lazy loader doesn't switch the url back 
						this.setAttribute('data-src', src_alt);	
						this.src = src_alt;
					}
					else
						$(this).siblings('.mtgcard-loadspinner').hide();
				});

				// Once actual image is loaded, flip the card
				$(selector + "img.mtgcard").on("load", function() {
					
					if ($(this).closest('.front').length) {
						$(this).closest('.flipper').addClass('load-ready');
					}
					if (!$(this).closest('.front').length && !$(this).closest('.back').length) {
						
						// If this wasn't the real url, switch to it and lazy load	
						var new_src = this.getAttribute('data-src');	
						if (new_src && this.src !== new_src) {
							this.setAttribute('loading', 'lazy');
							this.src = new_src;	
						}
					}

				}).each(function() {

					// If event registration was slower than image load, flip the card now
					if(this.complete) 
						$(this).trigger('load');
				});
			}

			function select2TemplateSelection(state) {
				var $state = $('<span></span>');
				$state.text(state.text);
				$state.addClass(state.id ? 'browse-selection' : 'browse-selection-none');
				return $state;
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

					var obsolete_id = $(this).closest('.card-votes').attr('data-obsolete-id');

					$.ajax({
						type: "POST",
						url: $(this).attr('action'),
						dataType: "json",
						data: $(this).serialize(),
						success: function(response) {
							// There might be multiple cards from same obsoletion id, so update them all
							$('.card-votes[data-obsolete-id='+obsolete_id+']').each(function(e) {
								$(this).find('.upvote-count').text(response.upvotes);
								$(this).find('.downvote-count').text(response.downvotes);
							});

							if (vote_refresh)
								location.reload();
						}
					});
				});

				register_card_image_handlers();
			});

		</script>

		@yield('js')
	</body>
</html>
