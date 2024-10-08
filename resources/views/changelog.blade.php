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
	<hr>

	<h4>4.10.2024</h4>
	<ul>
		<li>Changed "enters the battlefield" rule filtering to match the new "enters" wording.
			<ul>
				<li>This allows adding some permanents to be better than some sorceries/instants again.</li>
			</ul>
		</li>
		<li>Added support for rooms, so unlocking is understood as an "enters" effect, which may be better than some instants/sorceries.</li>
	</ul>

	<h4>25.11.2022</h4>
	<ul>
		<li>Fixed Deck Upgrade -page not recognizing double faced card names.
			<ul>
				<li>Now supports both full name and name of either side.</li>
			</ul>
		</li>
	</ul>

	<h4>1.20.2022</h4>
	<ul>
		<li>Tweaked tribe, format and label filter selectbox appearance, so it's easier to notice if an item is selected</li>
	</ul>

	<h4>29.9.2022</h4>
	<ul>
		<li>Fix error while adding suggestions</li>
	</ul>

	<h4>5.9.2022</h4>
	<ul>
		<li>Card name autocompletion uses native browser caching instead of custom javascript implementation</li>
		<li>Cards restricted in selected format are now also listed</li>
		<li>Inferios are now also listed in Deck Upgrade and Add Suggestion pages</li>
		<li>Minor tweaks to texts</li>
	</ul>

	<h4>6.7.2022</h4>
	<ul>
		<li>Fixed some card misgroupings causing votes to be shared incorrectly.</li>
		<li>Moved some older suggestions from inferior/superior tabs to type variants (Add suggestions page also rejects adding type variants).</li>
	</ul>

	<h4>4.3.2022</h4>
	<ul>
		<li>Added support for <i>Channel</i>. Ie. <i>Ironhoof Boar</i> is now better than <i>Brute Strength</i></li>
	</ul>

	<h4>31.1.2022</h4>
	<ul>
		<li>Compared main card now sticky scrolls for easier comparing</li>
		<li>Minor UI tweaks for navbar and votehelp page</li>
	</ul>

	<h4>27.1.2022</h4>
	<ul>
		<li>New UI
			<ul>
				<li>Superiors and inferiors are now tabs. One tab can be viewed at a time, but this give a lot space for viewing cards</li>
				<li>Cards only shrink at 1020px width</li>
				<li>Hovering over cards shows even bigger picture (400px height) and vote buttons stay under while zooming</li>
				<li>Few icons</li>
				<li>Added tab for functionality groups (Referred to as "type variants") on many pages. 
					Lists otherwise identical cards, but different types like <i>Suntail Hawk</i> vs <i>Kitesail Scout</i>. 
					Not to be mixed with functional reprints that share types like <i>Llanowar Elves</i> vs <i>Fyndhorn Elves</i>.
				</li>
			</ul>
		</li>
	</ul>

	<h4>22.1.2022</h4>
	<ul>
		<li>Minor UI tweaks and info texts.</li>
		<li>Add Suggestion -page now automatically checks the validity of selected cards and tells if they are not passing a check for adding</li>
	</ul>

	<h4>21.1.2022</h4>
	<ul>
		<li>Added voting categories to Help Voting page and adjusted which cards are listed there
			<ul>
				<li><a href="{{ route('votehelp.low-on-votes') }}">Low on Votes</a> - (Default) The original mode which lists suggestions with a low vote count. Vote to help users and AI make better decisions.</li>
				<li><a href="{{ route('votehelp.disputed') }}">Disputed</a> - Lists seemingly controversial upgrade suggestions with mixed upvote/downvote status, so you can help find a balance.</li>
				<li><a href="{{ route('votehelp.spreadsheets') }}">External Sources</a> - Lists suggestions other people have gathered on other sites and spreadsheets to help transfer the valid ones here.</li>
			</ul>
		</li>
		<li>
			Added limited support for alternative casting costs (only for user added cards):
			<ul>
				<li>Cycling - Cycling can be done at instant speed, so cards with Cycling can't be worse than sorcery speed spells. Cycling cards may also be better than some instants.</li>
				<li>Morph/Megamorph - Morph allows casting the creature for morph cost + 3 colorless, which may be lower than the hardcast cost.</li>
				<li>Dash - May be lower than the hardcast cost.</li>
				<li>Bestow - May be lower than the hardcast cost.</li>
				<li>Awaken - May be lower than the hardcast cost.</li>
			</ul>
		</li>
	</ul>

	<h4><19.1.2022</h4>
	<ul>
		<li>Fixed automatic suggestions not comparing double digit power/toughness correctly. (A bug introduced in recent framework upgrade)</li>
	</ul>

	<h4>16.9.2021</h4>
	<ul>
		<li>Introducing <b>machine learning</b> to determine some rules text betterness and finding more card suggestions automatically.
			<ul>
				<li>Suggestions with good upvote status are used as training material. Training finds out the positive and negative effects in rules texts.</li>
				<li>The positive and negative effects are matched to otherwise similar card pairs to find more suggestions.
					<ul>
						<li>Each card is first matched to cards with identical or better stats, like manacost, power, cast speed, etc, but different rules text.</li>
						<li>Each matched card must only have positive effects or effects the original card also has.</li>
						<li>Each matched card must also have all the positive effects the original card has.</li>
						<li>If all above conditions are met, the matched cards are added as suggestions to the original card.</li>
					</ul>
				</li>
				<li>This feature is still undergoing some improvements and optimizations as such it has some limitations
					<ul>
						<li>This process is only run occasionally for now due to heavy load it causes.</li>
						<li>This process cannot yet evaluate variable information in rules texts, like numbers or manacosts.</li>
					</ul>
				</li>
			</ul>
		</li>
	</ul>

	<h4>31.8.2021</h4>
	<ul>
		<li>Identical cards (excluding subtypes) share better-worse relations and voting data.<br>
			Ie. voting for <i>Llanowar Elves</i> also votes for <i>Elvish Mystic</i>.</li>
		<li>Suggestions have less restrictions now. Tribe and label filters should be used to achieve previous functionality.
			<ul>
				<li>Removed various card type checks: Supertype, type and subtype.</li>
				<li>Added "speed" check: Checks the better card can be casted at same or faster "speed" than the worse card.</li>
				<li>Added check for immediate effects: Various permanents may be better than Instants/Sorceries if they have ETB or self sacrifice abilities.<br>
					Ie. <i>Sakura-Tribe Elder</i> vs <i>Rampant Growth</i>.</li>
				<li>Added functionality group check: Similar cards now belong to same functionality groups and cannot be added as worse or better than a card from the same group.
					<ul>
						<li>Ie. No more cards with different subtypes only can be added like <i>Suntail Hawk</i> vs <i>Aven Skirmisher</i>.<br>
							Instead such cards will be viewable in another way in the future.</li>
					</ul>
				</li>
			</ul>
		</li>
		<li>API change: /obsoletes endpoint has changed.
			<ul>
				<li>'inferior' and 'superior' keys replaced with 'inferiors' and 'superiors' keys.<br>
					Both keys now contain an array of card representations instead of single card representation.<br>
					The arrays contain all functional reprints of the inferior/superior cards.
				</li>
			</ul>
		</li>
		<li>Fixed Unhinged cards with duplicate names not being listed.</li>
		<li>Automatic suggestions now also search for cards costing more of same mana, but same cmc. (These are marked with a label and can be filtered in UI)</li>
		<li>Changed Add Suggestion page card links. Card links now point back to Add Suggestion page, except for the topmost Infreior card which takes user back to Browse page.</li>
		<li>Added support for alternative manacost: Evoke. 
			Ie. <i>Mulldrifter</i> is now better than <i>Divination</i></li>
		<li>Added Help Voting tab where visitors can upvote or downvote random suggestions that have a low vote count.</li>
	</ul>

	<h4>11.7.2021</h4>
	<ul>
		<li>Added support for alternative manacost: Overload.
		Ie. <i>Damn</i> is now better than <i>Wrath of God</i>.</li>
	</ul>

	<h4>9.11.2020</h4>
	<ul>
		<li>Use Scryfall's new card image CDN rather than Gatherer for performance</li>
		<li>Load card images only when in viewport to save client bandwith</li>
		<li>Show a placeholder image and animation while loading real card image</li>
	</ul>

	<h4>3.1.2020</h4>
	<ul>
		<li>Support for new split cards that use existing split card face names, like Start//Finish and Start//Fire</li>
	</ul>

	<h4>5.11.2019</h4>
	<ul>
		<li>Added donation button for development and maintenance</li>
	</ul>

	<h4>1.11.2019</h4>
	<ul>
		<li>Added subtype/tribe filters. On browse page, this limits suggestions to the selected tribe.
		On Deck Upgrade page cards that are not from the tribe also get non-tribe suggestions, but suggestions from the tribe are sorted first. Tribes are updated daily to match Scryfall data.</li>
		<li>Format selectbox is now searchable.</li>
		<li>Fixed search results when trying to access non-existant page number on Browse page. You will see the last available page instead.</li>
	</ul>

	<h4>28.10.2019</h4>
	<ul>
		<li>Spoiled cards are now handled correctly and renamed as soon as their real names are revealed. No more duplicates</li>
		<li>Removed non-playble cards, like 'Experience' where you put your experience counters on.</li>
		<li>Fixed bug with cards starting with special characters not being treated equally when they used their name in rules text</li>
	</ul>

	<h4>27.10.2019</h4>
	<ul>
		<li>Fixed automatic updates</li>
		<li>Selectable formats are now also updated daily to match Scryfall card data. This means pioneer and any future formats are now automatically available.</li>
	</ul>

	<h4>7.9.2019</h4>
	<ul>
		<li>API now allows CORS with any domain, so API may be used directly from browsers</li>
		<li>API requests use short term (60s) client based caching to mitigate frequent requests from browsers</li>
	</ul>

	<h4>3.8.2019</h4>
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

	<h4>6.7.2019</h4>
	<ul>
		<li>Upgrade Deck -feature no longer suggests cards that are alredy in the deck. E.g. when playing both Murder and Hero's Downfall</li>
	</ul>

	<h4>26.6.2019</h4>
	<ul>
		<li>Card database is now updated daily to match the cards available at Scryfall</li>
		<li>New programmatic suggestions and functional reprints are added automatically when card database is updated</li>
	</ul>

	<h4>22.6.2019</h4>
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

	<h4>21.6.2019</h4>
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

	<h4>12.6.2019</h4>
	<ul>
		<li>Back/Forward navigation works more intuitively on Browse page. Remembers scroll positions and records format/label filter changes to page history.</li>
		<li>Scroll to top when clicking navigation elements in Browse page (only if top pagination menu is not visible)</li>
	</ul>

	<h4>11.6.2019</h4>

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

	<h4>29.5.2019</h4>

	<ul>
		<li>Added About and Changelog pages</li>

		<li>Changed API formats to save bandwidth (the info is available from other sites). Function reprints API uses the same paging format.</li>

		<li>If commander format is selected when ugrading deck, the suggestions must follow the color identity of the cards in the deck</li>

		<li>To speed up Browse -page initial load time, images are only fetched after everything else is ready</li>

		<li>Voting no longer refreshes the page, but is done in the background instead</li>
	</ul>

	<h4>28.5.2019</h4>

	<ul>
		<li>Improved functional reprint detection to also detect cards that use their own name in rules text. This pushes functional reprint families from 102 -> 147 and individual cards from 202 -> 306</li>

		<li>Added support for formats. When a format is selected, only upgrades available in that format will be shown. The card to upgrade from may still be from any from format.</li>
	</ul>

	<h4>27.5.2019</h4>

	<ul>
		<li>Did some background work for functional reprints (Llanowar Elves, Elvish Mystic...) They are now available via API, but not yet present in UI.</li>

		<li>Added and fixed issues with color restrictions. The better card must not have colors the worse card doesn't have. (This means the better card may have less colors)</li>

		<li>Suggested better card must not be a functional reprint of the worse card.</li>

		<li>Fixed clipping issue with vote buttons.</li>

		<li>Removed existing suggestions that don't fall under new addition rules.</li>

		<li>If a suggested worse/better card has functional reprints, all reprints are automatically suggested as well.</li>
	</ul>

	<h4>26.5.2019</h4>
	<ul>
		<li>Initial release</li>
	</ul>

</div>
@stop

@section('js')
<script>

</script>
@stop