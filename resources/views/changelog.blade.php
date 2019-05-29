@extends('layout')

@section('meta')
	<meta name="Description" content="Changes on the site">
	<title>StrictlyBetter - Changelog</title>
@stop

@section('head')
	<style>
		.content p {
			text-align: justify;
		}
	</style>
@stop

@section('content')

	<h1>Changelog</h1>

	<p>This page lists usage affecting changes on the site.</p>

	<h4>05/29/19</h4>

	<ul>
		<li>Added About and Changelog pages</li>
	</ul>

	<h4>05/28/19</h4>

	<ul>
		<li>Improved functional reprint detection to also detect cards that use their own name in rules text. This pushes functional reprint families from 102 -> 147 and individual cards from 202 -> 306</li>

		<li>Added support for formats. When a format is selected, only upgrades available in that format will be shown. The card to upgrade from may still be from any from format.</li>
	</ul>

	<h4>05/27/19</h4>

	<ul>
		<li>Did some background work for functional reprints (Llanowar Elves, Elvish Mystic...) They are now available via API, but not yet present in UI.</li>

		<li>Added and fixed issues with color restrictions. The better card must not have colors the worse card doesn't have. (This means the better card may have less colors)</li>

		<li>Suggested better card must not be a functional reprint of the worse card.</li>

		<li>Fixed clipping issue with vote buttons.</li>

		<li>Removed existing suggestions that don't fall under new addition rules.</li>

		<li>If a suggested worse/better card has functional reprints, all reprints are automatically suggested as well.</li>
	</ul>

	<h4>05/26/19</h4>
	<ul>
		<li>Initial release</li>
	</ul>

@stop

@section('js')
<script>

</script>
@stop