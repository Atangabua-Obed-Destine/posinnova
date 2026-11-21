# I-NNOVA POS

Point of sale, stock, pharmacy and clinic management, by I-NNOVA. English and French.

## Installing

See **[INSTALL.md](INSTALL.md)**. In short, on a computer with PHP 8.1+, MySQL, Composer and Git:

```
git clone --branch clinic-phases-1-5 https://github.com/Atangabua-Obed-Destine/posinnova.git posinnova
cd posinnova
composer install --no-dev
php artisan innova:install
```

then open the link it prints to create the client's business.

## Updating

```
php artisan innova:update
```

## Third-party software

Built on the UltimatePOS code base (Codecanyon licence) and the Laravel framework (MIT licence).
