# Contributing to Strictly Better

If you'd like to contribute, start by searching through the issues and pull requests to see whether someone else has raised a similar idea or question.

If the issue is related to security, email to me directly at henri.kulotie@gmail.com instead of posting an issue here.

If you don't see your idea listed, you may post an issue yourself. 
For any minor changes you may also make a pull request directly, but do describe in what way and how does the pull request improve Strictly Better.

If setting up your pull requires more than merging the code, such as migrating database changes, please tell so.



## Setting up the development environment


### Core Requirements
 - HTTP Server (Apache or Nginx)
 - PHP >= 8.1
 - MySQL (recommend >= v8.x) or MariaDB
 - Composer


### Dependencies
 - Scryfall Bulk Data Files - Oracle Cards
 - Laravel 8.x
 - Bootstrap 4.3.x
 - jquery 3.4.x
 - Font Awesome 7.7.x


### Setup and Installation

Create your development environment by downloading, installing, and setting up the applications defined in the **Core Requirements** section above.

Create an empty MySQL database and provide user(s) with basic Table Privilages

Fork the strictlybetter/strictlybetter repository to your own GitHub account and clone to your local development environment
 - [GitHub Docs: Fork a repository](https://docs.github.com/en/free-pro-team@latest/github/getting-started-with-github/fork-a-repo)
 - [GitHub Docs: Cloning a repository](https://docs.github.com/en/free-pro-team@latest/github/creating-cloning-and-archiving-repositories/cloning-a-repository)


Copy the `.env.example` file in root directory and rename to `.env`

Edit the `.env` file and change the following values to match your MySQL Database environment:
```
DB_DATABASE=example_database
DB_USERNAME=example_username
DB_PASSWORD=example_password
```

Install Laravel framework and 3rd party Composer packages (while in repository root):
```
composer install 
```

Generate app key (while in repository root):
```
php artisan key:generate
```
   
Migrate database structure (while in repository root):
```
php artisan migrate 
```

Fetch card data from Scryfall (while in repository root):
```
php artisan full-update
```

Point Apache/Nginx webroot to repository's 'public' folder.


## Troubleshooting
[Laravel 8.x documentation](https://laravel.com/docs/8.x) can help you further if anything goes wrong during setup.

If parsing of [bulk-data files](https://scryfall.com/docs/api/bulk-data) (scryfall-oracle-cards.json) fails during full-update or load-scryfall artisan command, the format may have changed to an incompatible one. In such case an issue should be created here. 

If you can fix it yourself, you may also make a pull request. Investigating [Scryfall API documentation](https://scryfall.com/docs/api) and the downloaded scryfall-oracle-cards.json (in repository root) can help you there.


Henri "Dankirk" Kulotie
