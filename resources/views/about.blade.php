@extends('layout')

@section('meta')
	<meta name="Description" content="Help and how to contact developers">
	<title>Strictly Better - About</title>
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
		Strictly Better offers information about Magic: The Gathering cards that are functionally superior to other cards.
	</p>

	<p>
		Using the the site a MTG player may search for better card alternatives for their older deck or cards that may have been printed without putting in too much effort.
		For example, they may want to upgrade their <a href="{{ route('index') }}?search=Murder" rel="noopener noreferrer"><i>Murder</i> to <i>Hero's Downfall</i></a>.
	</p>

	<p>
		The site also has a public <a href="{{ route('api.guide') }}">API</a>, so other developers may further use the suggestion data as they wish.
	</p>
	<br>

	<h3>Where is the data coming from?</h3>
	<p>
		The suggestions listed on the site are added via <a href="{{ route('card.create') }}">Add Suggestion</a> page by anonymous Magic the Gathering players.<br>
		Adding new suggestions and voting for them requires no login or account.<br>
		<br>
		Some suggestions are also generated programmatically. Due to complex rules of the cards, only cards with identical rules are considered when evaluating strict-betterness this way.<br>
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
			<li>The supposed better card has higher CMC (Some alternative casting costs are also checked)</li>

			<li>The supposed better card has manacolors in manacost the worse card doesn't have. (Hybrid mana is an exception, alternative casting costs are checked too)</li>

			<li>The supposed better card can't be cast at same "speed" than the worse card. (Instant, Flash or other instant-speed ability)</li>

			<li>The supposed better card doesn't have immediate effect like the worse Instant or Sorcery.<br>
				(This can be achieved via "Enters the Battlefield", self sacrifice or cycling effects)</li>

			<li>The cards belong to same functionality group. (Ie. <i>Llanowar Elves</i> vs <i>Elvish Mystic</i>)</li>
		</ul>
	</p>
	<br>

	<h3>Labels and searching</h3>
	<p>
		When browsing, you may see labels on card suggestions indicating there might be cases the suggestion is not a strictly better choice.<br>
		Suggestions with labels are still listed when searching as they may still be useful when considering card replacements, unless you specifically hide them via filter menu on the Browse page.<br>
		<br>
		List of current labels:
		<ul>
			@foreach(Lang::get('card.filters') as $filter => $value)
				@if($filter != "strictly_better")
					<li><span class="card-label">{{ $value }}</span> - {{ Lang::get('card.filter_explanations.' . $filter) }}</li>
				@endif
			@endforeach
		</ul>
	</p>
	<br>

	<h3>The "strictly better" controversy</h3>
	<p>
		People have disagreements about what constitutes as "strictly better". So it is good to remember the site is primarily a tool for finding better options. Other than automated rules and labels/filters mentioned above, StrictlyBetter makes no ruling over what is truly strictly better, as I believe it is better to have the voters decide.<br>
		<br>
		Personally I prefer card suggestions that are better when considered <i>in a vacuum</i>. (Without knowledge of the deck it is played in or decks it's facing)<br>
		This outlines a few base rules like: <b>opponents card to exile > graveyard, "each" > "target 3", double strike > first strike</b><br>
	</p>
	<br>

	<h3>Legal</h3>
	<p>Strictly Better is unofficial Fan Content permitted under the Fan Content Policy. Not approved/endorsed by Wizards. Portions of the materials used are property of Wizards of the Coast. ©Wizards of the Coast LLC.</p>
	<br>

	<h3>How to contact the developer</h3>
	<p>
		<address>
			Site is run by Henri Kulotie. You may contact me at <a href="mailto:henri.kulotie@gmail.com" rel="noreferrer noopener">henri.kulotie@gmail.com</a>
		</address>
		If you want to see how this all works or help with development, you may see the <a href="https://github.com/Dankirk/strictlybetter">project on GitHub</a><br>
		You can also <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=P32N439KJFJR4&item_name=Strictly+Better,+development+and+maintenance&currency_code=EUR&source=url" target="_blank" rel="noopener noreferrer">donate</a> to support the site' development and maintenance.<br><br>
		There is also a <a href="https://www.reddit.com/r/magicTCG/comments/bt7ocz/i_just_put_up_a_website_for_finding_strictly/" rel="noreferrer noopener">Reddit thread about the site</a>.
	</p>

</div>
@stop

@section('js')
<script>

</script>
@stop