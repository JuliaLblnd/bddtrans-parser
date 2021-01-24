BDDTrans Parser
===============

Parseur pour le site BDDTrans qui extrait les praticiens en JSON et CSV.

Install dependencies (optional)
--------------------

The only dependency is the Google API Client, which is not required if you will not use the Google Spreadsheet Update functionality.

```bash
composer install
```

Usage
-----

```bash
php ./app.php
```

#### CLI Options

 - `-o`: Output directory for files `bddtrans.json` and `bddtrans.csv`
 - `-s`: Google Spreadsheet ID (see [Google Spreadsheet Update section](#google-spreadsheet-update))

Google Spreadsheet Update
-------------------------

#### Google API PHP Client Authentication with OAuth

1. Follow the instructions to [Create Web Application Credentials](https://github.com/googleapis/google-api-php-client/blob/master/docs/oauth-web.md#create-authorization-credentials)
2. Download the JSON credentials
3. Copy the credentials to `credentials.json` files in the same location than `functions.php` file.

> View the [related Section in the Google API PHP Client README](https://github.com/googleapis/google-api-php-client#authentication-with-oauth)

Requirements
------------

PHP 7.2

Authors
-------

- Julia Leblond  | [GitHub](https://github.com/JuliaLblnd)  | [Twitter](https://twitter.com/JuliaLblnd)

See also the list of [contributors](https://github.com/JuliaLblnd/bddtrans-parser/contributors) who participated in this project.

License
-------

This project is licensed under [The Unlicense License](https://unlicense.org/) - see the [LICENSE](./LICENSE) file for details
