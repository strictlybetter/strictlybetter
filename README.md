# Strictly Better
A community run webapp made with PHP 7 framework Laravel 5.8, for finding strictly better Magic: The Gathering cards

The app is running on https://www.strictlybetter.eu

Strictly Better offers information about Magic the Gathering cards that are functionally superior to other cards.
Using the the site a MTG player may search for better card alternatives for their older deck or cards that may have been printed without putting in too much effort. 

The site also has a [public API](https://www.strictlybetter.eu/api-guide), so other developers may further use the suggestion data as they wish. 

[Changelog](https://www.strictlybetter.eu/changelog) lists the changes affecting usability.

## Where is the data coming from?

The suggestions listed on the site are added via [Add Suggestion page](https://www.strictlybetter.eu/card) by anonymous Magic the Gathering players. Adding new suggestions and voting for them requires no login or account.

Some suggestions are also generated programmatically. Due to complex rules of the cards, only cards with identical rules are considered when evaluating strict-betterness this way.

The card database is downloaded from [Scryfall](https://scryfall.com) using their [bulk-data files](https://scryfall.com/docs/api/bulk-data).

More information is available on live [About page](https://www.strictlybetter.eu/about).


## Contributing and development environment

Please see [CONTRIBUTING.md](https://github.com/Dankirk/strictlybetter/blob/master/CONTRIBUTING.md) for guide lines how to contribute and set up a development environment for yourself.

## License
[MIT](https://github.com/Dankirk/strictlybetter/blob/master/LICENSE.md)
