# Contributing to Strictly Better

If you'd like to contribute, start by searching through the issues and pull requests to see whether someone else has raised a similar idea or question.

If the issue is related to security, email to me directly at henrij.aho@gmail.com instead of posting an issue here.

If you don't see your idea listed, you may post an issue yourself. 
For any minor changes you may also make a pull request directly, but do describe in what way and how does the pull request improve Strictly Better.

If setting up your pull requires more than merging the code, such as migrating database changes, please tell so.



## Setting up development environment

First you will need Apache/Nginx to run the server, PHP > 7.1, [Composer](https://getcomposer.org/) and database such as MySql.
- Clone the repository
- Copy environment file template in repository root:
``` 
cp .env.template .env
```
- Edit .env file to match your development environment
- Install Laravel framework and 3rd party Composer packages (while in repository root):
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
[Laravel 5.8 documentation](https://laravel.com/docs/5.8) can help you further if anything goes wrong during setup.

If parsing of [bulk-data files](https://scryfall.com/docs/api/bulk-data) (scryfall-default-cards.json) fails during full-update or load-scryfall artisan command, the format may have changed to an incompatible one. In such case an issue should be created here. 

If you can fix it yourself, you may also make a pull request. Investigating [Scryfall API documentation](https://scryfall.com/docs/api) and the downloaded scryfall-default-cards.json (in repository root) can help you there.


Henri "Dankkirk" Aho
