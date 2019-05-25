<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @yield('meta')
        <title>StrictlyBetter</title>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
        <link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
        <link href="{{ URL::to('css/select2.min.css') }}" rel="stylesheet">
        <link href="{{ URL::to('css/main.css') }}" rel="stylesheet">
        @yield('head')
    </head>
    <body>

    	<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
		  <a class="navbar-brand" href="{{ route('index') }}">StrictlyBetter</a>
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
		      <li class="nav-item {{ Request::is('card') ? 'active' : '' }}">
		        <a class="nav-link" href="{{ route('card.create') }}">Add Suggestion</a>
		      </li>
		      <li class="nav-item {{ Request::is('api-guide') ? 'active' : '' }}">
		        <a class="nav-link" href="{{ route('api.guide') }}">API Guide</a>
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

		<div class="container">
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

        <script>

        	$.ajaxSetup({
				headers: {
					'X-CSRF-TOKEN': "{{ csrf_token() }}"
				}
			}); 

        	// Card search
        	var card_cache = {};
        	$("#card-search").autocomplete({
        		minLength: 2,
        		delay: 50,
			    source: function(request, response) {

			    	// Check cache before query
					if (request.term in card_cache) {
						response(card_cache[request.term]);
						return;
					}

					// Query
					$.getJSON("{{ route('card.autocomplete') }}", request, function(data, status, xhr) {
						card_cache[request.term] = data;
						response(data);
					});
				},
				select: function(event, ui) {

					// Automatically submit search if an autocomplete option was selected
					$("#card-search").val(ui.item.value);
					$("#card-search-form").submit();
				}
			}).focus(function() {

				// Show autocomplete options when re-focusing
				if ($(this).val().length >= 2)
					$(this).autocomplete("search", $(this).val());
			});


			// Upvotes / Downvote
			$(".vote-form").submit(function(event) {
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

        </script>

        @yield('js')
    </body>
</html>
