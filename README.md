# Strictly Better
A community run webapp made with PHP 7 framework Laravel 5.8, for finding strictly better Magic: The Gathering cards

https://www.strictlybetter.eu

Strictly Better offers information about Magic the Gathering cards that are functionally superior to other cards.
Using the the site a MTG player may search for better card alternatives for their older deck or cards that may have been printed without putting in too much effort. 

The site also has a public API, so other developers may further use the suggestion data as they wish. 

API Guide is available on the live website

https://www.strictlybetter.eu/api-guide

## Where is the data coming from?

The suggestions listed on the site are added via Add Suggestion page by anonymous Magic the Gathering players.
Adding new suggestions and voting for them requires no login or account.

Some suggestions are also generated programmatically. Due to complex rules of the cards, only cards with identical rules are considered when evaluating strict-betterness this way.

The card database is downloaded from Scryfall using their bulk-data files.

https://scryfall.com/docs/api/bulk-data

More information is available on About page.

https://www.strictlybetter.eu/about

## Running locally

First you will need Apache/Nginx to run the server, PHP > 7.1, Composer (https://getcomposer.org/) and database such as MySql.
- Clone the repository
- Copy environment file template in repository root:
``` 
cp .env.template .env
```
- Edit .env file to match your development environment
- Install composer packages (while in repository root):
``` 
composer install 
```
- Generate app key (while in repository root):
``` 
php artisan key:generate
```
- Move the generated app key from config/app.php (a varible called 'key') to the .env file. Replace 'key' in app.php to read: 
```
'key' => env('APP_KEY'),
```
- Migrate database structure (while in repository root):
``` 
php artisan migrate 
```
- Fetch card data from Scryfall (while in repository root):
``` 
php artisan full-update 
```
- Point Apache/Nginx webroot to repository's 'public' folder.

## Troubleshooting
Laravel 5.8 documentation can help you further if anything goes wrong during setup.

https://laravel.com/docs/5.8

If parsing of scryfall-default-cards.json fails during full-update or load-scryfall artisan command, the format may have changed to an incompatible one.
In such case an issue should be created here. You may also make a pull request, if you can fix it yourself. 

See Scryfall API documentation (https://scryfall.com/docs/api) or investigate the downloaded scryfall-default-cards.json (in repository root) yourself.

## License
MIT
