@extends('layout')

@section('meta')
	<meta name="Description" content="Help and how to contact developers">
	<title>StrictlyBetter - About</title>
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
	<h1>About</h1>

	<p>
		StrictlyBetter offers information about Magic the Gathering cards that are functionally superior to other cards.
	</p>

	<p>
		Using the the site a MTG player may search for better card alternatives for their older deck or cards that may have been printed without putting in too much effort.
		For example, they may want to upgrade their <a href="{{ route('index') }}?search=Murder" rel="noopener noreferrer">Murder to Hero's Downfall</a>.
	</p>

	<p>
		The site also has an <a href="{{ route('api.guide') }}">API</a>, so other developers may further use the suggestion data as they wish.
	</p>
	<br>

	<h3>Where is the data coming from?</h3>
	<p>
		The suggestions listed on the site are added via <a href="{{ route('card.create') }}">Add Suggestion</a> page by anonymous Magic the Gathering players.<br>
		Adding new suggestions and voting for them requires no login or account.<br>
		<br>
		The card database is downloaded from <a href="https://scryfall.com/" rel="noreferrer noopener">Scryfall</a> using their <a href="https://scryfall.com/docs/api/bulk-data" rel="noreferrer noopener">bulk-data files</a>
	</p>
	<br>

	<h3>Adding new suggestions</h3>
	<p>
		New suggestions have to pass a few automated checks, however, rules texts of the cards are not evaluated automatically (except for functional reprints).<br>
		As a community run site, the validation of card "betterness" is ultimately left to users and the voting system.
		<br><br>
		Any suggestions with following conditions are automatically rejected
		<ul>
			<li>Cards type lines do not match (sorcery vs instant is an exception)</li>

			<li>The supposed better card has higher CMC</li>

			<li>The supposed better card has colors the worse card doesn't</li>

			<li>The cards are the same or functional reprints of each other</li>
		</ul>
	</p>
	<br>

	<h3>The "strictly better" controversy</h3>
	<p>
		People have disagreements about what constitutes as "strictly better". So it is good to remember the site is primarily a tool for finding better options. Other than automated rules mentioned above, StrictlyBetter makes no ruling over what is truly strictly better, as I believe it is better to have the voters decide.<br>
		<br>
		Personally I prefer card suggestions that are better when considered <i>in a vacuum</i>. (Without knowledge of the deck it is played in or decks it's facing)<br>
		This outlines a few base rules like: <b>opponents card to exile > graveyard, "each" > "target 3", double strike > first strike</b><br>
		<br>
		The site will in the future employ search options and show disclaimers/infoboxes for each suggestion which show in which formats, creature types, color decks, etc the card is better in to help users decide about betterness.<br>
	</p>
	<br>

	<h3>How to contact the developer</h3>
	<p>
		<address>
			Site is run by Henri Aho. You may contact me at <a href="mailto:henrij.aho@gmail.com" rel="noreferrer noopener">henrij.aho@gmail.com</a>
		</address>
		There is also a <a href="https://www.reddit.com/r/magicTCG/comments/bt7ocz/i_just_put_up_a_website_for_finding_strictly/" rel="noreferrer noopener">Reddit thread about the site</a>.
	</p>
</div>
@stop

@section('js')
<script>

</script>
@stop