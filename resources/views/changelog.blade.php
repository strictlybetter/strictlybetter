@extends('layout')

@section('meta')
	<meta name="Description" content="Changes on the site">
	<title>Strictly Better - Changelog</title>
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

	<h4>10/28/19</h4>
	<ul>
		<li>Spoiled cards are now handled correctly and renamed as soon as their real names are revealed. No more duplicates</li>
		<li>Removed non-playble cards, like 'Experience' where you put your experience counters on.</li>
		<li>Fixed bug with cards starting with special characters not being treated equally when they used their name in rules text</li>
	</ul>

	<h4>10/27/19</h4>
	<ul>
		<li>Fixed automatic updates</li>
		<li>Selectable formats are now also updated daily to match Scryfall card data. This means pioneer and any future formats are now automatically available.</li>
	</ul>

	<h4>09/07/19</h4>
	<ul>
		<li>API now allows CORS with any domain, so API may be used directly from browsers</li>
		<li>API requests use short term (60s) client based caching to mitigate frequent requests from browsers</li>
	</ul>

	<h4>08/03/19</h4>
	<ul>
		<li>Update #2
			<ul>
				<li>Suggestions for cards can now be sorted by name, newness (default) or upvotes.
					<ul>
						<li>Note: The main cards are always sorted by newness, unless a search term is present in which case cards are sorted by name</li>
					</ul>
				</li>
				<li>Upgrade Deck suggestions are always sorted by upvotes</li>
				<li>Quick search now finds cards that only have inferiors without typing the exact card name
					<ul>
						<li>Typing "Lightning B" should now return Lightning Bolt as expected, even if it doesn't have any upgrade suggestions</li>
					</ul>
				</li>
			</ul>
		</li>
		<li>Update #1
			<ul>
				<li>Support for split and flip cards</li>
				<li>Existing superior card is no longer pre-selected when adding new suggestions</li>
				<li>Quick search prioritizes exact card name match over cards with suggestions</li>
				<li>New programmatically added suggestions will be shown on first Browse page just like suggestions added by users</li>
			</ul>
		</li>
	</ul>

	<h4>07/06/19</h4>
	<ul>
		<li>Upgrade Deck -feature no longer suggests cards that are alredy in the deck. E.g. when playing both Murder and Hero's Downfall</li>
	</ul>

	<h4>06/26/19</h4>
	<ul>
		<li>Card database is now updated daily to match the cards available at Scryfall</li>
		<li>New programmatic suggestions and functional reprints are added automatically when card database is updated</li>
	</ul>

	<h4>06/22/19</h4>
	<ul>
		<li>Harshly downvoted cards have a label on them (requires 10 more downvotes than upvotes).</li>
		<li>Harshly downvoted cards can be hidden from results with Labels Filters on Browse page</li>
		<li>UI rework for Browse page
			<ul>
				<li>For smaller screens cards may scaled a bit smaller. Put cursor over them to enlarge.</li>
				<li>Inferior/Superior panels can now be hidden, so you can see more cards for the side you want to see</li>
				<li>Inferior/Superior panels become exclusive if screen width is less than 1070px</li>
			</ul>
		</li>
		<li>Fixed obsoletes API not showing functional reprint IDs for inferior cards</li>
		<li>Fixed labels not being generated programmatic additions</li>
	</ul>

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