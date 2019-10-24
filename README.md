# Strictly Better
A community run webapp for finding strictly better Magic: The Gathering cards

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
- Point Apache/Nginx webroot to repository's 'public' folder.
- Generate app key:
``` 
php artisan key:generate
```
- Copy environment file template:
``` 
cp .env.template .env
```
- Edit .env file to match your development environment
- Install composer packages while in repository root:
``` 
composer install 
```
- Migrate database at repository root:
``` 
php artisan migrate 
```
- Fetch card data from Scryfall:
``` 
php artisan full-update 
```



## License
MIT
