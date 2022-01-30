@extends('layout')

@section('meta')
	<meta name="Description" content="API information and usage instructions">
	<title>Strictly Better - Api Guide</title>
@stop

@section('head')
	<style>
		.content p {
			text-align: justify;
		}
	</style>
@stop

@section('content')

<div class="container">
	<h1>Api Guide</h1>

	<p>
		The strictly better cards on this site can be retrieved and searched with API requests.
	</p>
	<hr>

	<h3>General stuff</h3>

	<p>
		Results are sent in json format with UTF-8 character encoding.<br>
		<br>
		A maximum of <b>50 results per request</b> are sent to limit the size of responses.<br>
		To retrieve the second page of your results append <i>?page=2</i> to end of your url.
	</p>
	<br>

	<h3>All obsolete cards</h3>
	<p>Use following urls to get all obsolete cards.</p>
	<code>
		{{ route('api.obsoletes') }}<br>
		{{ route('api.obsoletes', [null, 'page' => 2]) }}<br>
	</code>

	<br>
	<p>
		You should treat the API entries with healthy skepticism as they are mainly inserted by the community.<br>
		To mitigate issues evaluate number of up/down votes and labels array of each entry in your own app.<br>
		You can find out more labels on <a href="{{ route('about') }}">About</a> page.
	</p>
	<br>

	<h3>Searching</h3>
	<p>
		You may also search for specific obsolete cards by appending their name to end of the url.<br>
		Partial matches are also supported.
	</p>

	<code>
		{{ route('api.obsoletes', 'Lightning Strike') }}<br>
		{{ route('api.obsoletes', 'Lightning') }}<br>
		{{ route('api.obsoletes', ['Lightning', 'page' => 2]) }}
	</code>

	<br><br>

	<h3>Functional Reprints</h3>
	<p>
		Many cards have functional reprints. Eg. Llanowar Elves, Elvish Mystic and Fyndhorn Elves function just the same.<br>
		Use following url to find all cards that have functional reprints grouped by the functionality.<br>
		<br>
		Note that cards with different types are not grouped as functionally equal.
	</p>

	<code>
		{{ route('api.functional_reprints') }}
	</code>

	<br><br>

	<h3>Throttling</h3>
	<p>
		All available API requests are throttled to prevent excessive use. Current limit is 100 requests per minute.<br>
		You may monitor your available requests with HTTP response headers <i>X-RateLimit-Limit</i> and <i>X-RateLimit-Remaining</i>.<br>
		If rate limit is exceeded, you will receive status code 429 and <i>X-RateLimit-Reset</i> header with timestamp when you may continue doing more requests.
	</p>
	
	<br>

	<h3>Legal</h3>
	<p>
		You are free to use the API and it's results for any purpose for which I may not be held liable for.<br>
		However, you must still adhere to <a href="https://company.wizards.com/fancontentpolicy" rel="noopner nofollow">Wizards of the Coast Fan Content Policy</a>.
	</p>

</div>
@stop

@section('js')
<script>

</script>
@stop