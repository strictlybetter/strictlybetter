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
<div class="container">
	<h1>Changelog</h1>

	<p>This page lists usage affecting changes on the site.</p>

	<h4>06/21/19</h4>
	<ul>
		<li>Added new cards to database (some M20 spoilers).</li>
		<li>Added Scryfall links for cards</li>
		<li>Use Scryfall images for cards without multiverse id (instead of Gatherer)</li>
		<li>Re-added non-legal cards (Unhinged, etc)</li>
		<li>Functional reprints API now uses "@@@" in place of the cards name in rules text</li>
		<li>Reset functional reprints IDs in the API. The IDs will NOT be reset again in the future</li>
		<li>Added 1 new functional reprint family (now totaling 148) and 3 individual cards to new/existing families (now totaling 306)</li>
		<li>Added 8 new strictly-better suggestions programmatically</li>
		<li>Fixed a scroll issue with small screens when selecting a format or labels to filter on Browse page</li>
	</ul>

	<h4>06/12/19</h4>
	<ul>
		<li>Back/Forward navigation works more intuitively on Browse page. Remembers scroll positions and records format/label filter changes to page history.</li>
		<li>Scroll to top when clicking navigation elements in Browse page (only if top pagination menu is not visible)</li>
	</ul>

	<h4>06/11/19</h4>

	<ul>
		<li>Major UI rework
			<ul>
				<li>Changed card listing layout</li>
				<li>Inferior cards are listed aswell on Browse -page</li>
				<li>Functional reprints are listed for searched cards</li>
				<li>Clicking a card on Browse page or using the searchbox on navbar displays the card Browse page instead of going to Add Suggestion page</li>
				<li>Inferior and superior suggestions can be added via Browse page cards with big "+" sign on them</li>
				<li>Added labels for suggestions indicating "issues" with their worse-better relation if any</li>
				<li>If no suggestions are found for a card name searched on Browse -page, the card database is searched instead</li>
				<li>When worse card is selected on Add Suggetion -page, current superiors are shown, so people know if a suggestion alredy exists</li>
				<li>Mobile-friendly UI</li>
				<li>Added autocomplete for quicksearch</li>
				<li>Adjusted search timers to prevent excessive calls when typing to search inputs, potentially also increasing response speed</li>
			</ul>
		</li>
		<li>Suggestions adding rules changed
			<ul>
				<li>Color is no longer checked</li>
				<li>If better and worse cards have subtypes/tribes, they must have atleast 1 in common</li>
				<li>Better card may not use manacolors in manacost not present in worse card, hybrid mana is an exception</li>
				<li>Better card may cost more mana of a color, but must still have less or equal CMC. A label is added to better cards that cost more colored mana.</li>
				<li>Better/Worse creature may also have other types. The difference is indicated with a label</li>
			</ul>
		</li>
		<li>Removed non-playable cards like tokens, schemes, emblems, etc</li>
		<li>Removed cards not legal in any format</li>
		<li>Added suggestion labels to API</li>
		<li>Added some suggestions programmatically (only works for cards with identical rules texts)</li>
	</ul>

	<h4>05/29/19</h4>

	<ul>
		<li>Added About and Changelog pages</li>

		<li>Changed API formats to save bandwidth (the info is available from other sites). Function reprints API uses the same paging format.</li>

		<li>If commander format is selected when ugrading deck, the suggestions must follow the color identity of the cards in the deck</li>

		<li>To speed up Browse -page initial load time, images are only fetched after everything else is ready</li>

		<li>Voting no longer refreshes the page, but is done in the background instead</li>
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

</div>
@stop

@section('js')
<script>

</script>
@stop