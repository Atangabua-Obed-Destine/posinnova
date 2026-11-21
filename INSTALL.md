# Installing I-NNOVA POS

This guide is for the technician installing I-NNOVA POS on a client's computer or server, from a bare machine to a shop ready to sell. It works on Windows with XAMPP and on Linux.

1. [Prepare the computer](#1-prepare-the-computer)
2. [Get the code](#2-get-the-code)
3. [Run the installer](#3-run-the-installer)
4. [Create the business in the browser](#4-create-the-business-in-the-browser)
5. [Linux only: the web server](#5-linux-only-the-web-server)
6. [After installing](#6-after-installing)
7. [Updating an installation](#7-updating-an-installation)
8. [The superadmin password](#8-the-superadmin-password)

## 1. Prepare the computer

It needs:

- PHP 8.1 or newer (8.2 recommended), with these extensions on: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`
- MySQL or MariaDB
- Apache (XAMPP) or Nginx
- Composer 2 and Git
- Internet access during the installation (Composer downloads the libraries)

No Node.js is needed: the screen files come ready in the code.

### Windows (XAMPP)

1. Install XAMPP (PHP 8.2), Composer (getcomposer.org, "Composer-Setup.exe") and Git for Windows.
2. Turn on `gd` and `zip`, which XAMPP leaves off. Open `C:\xampp\php\php.ini` (XAMPP Control Panel → Apache → Config → PHP (php.ini)) and remove the `;` at the start of these two lines:

   ```
   extension=gd
   extension=zip
   ```

   Save, then restart Apache.
3. Start Apache and MySQL in the XAMPP Control Panel.
4. Check in PowerShell: `php -v` shows 8.1 or newer, and `php -m` lists `gd` and `zip`.

### Linux (Ubuntu or Debian)

```
sudo apt update
sudo apt install apache2 mariadb-server git unzip composer \
  php php-cli php-mysql php-gd php-zip php-mbstring php-xml php-bcmath php-curl
sudo a2enmod rewrite && sudo systemctl restart apache2
```

## 2. Get the code

Clone into the web folder (`C:\xampp\htdocs` on Windows, `/var/www` on Linux), then get the libraries:

```
git clone --branch clinic-phases-1-5 https://github.com/Atangabua-Obed-Destine/posinnova.git posinnova
cd posinnova
composer install --no-dev
```

`composer install` takes a few minutes the first time.

## 3. Run the installer

```
php artisan innova:install
```

On Windows, run it in PowerShell or Command Prompt rather than Git Bash, which can hang on the password question.

It checks the computer first and names anything missing (a PHP extension, a folder that cannot be written). Then it asks for:

- **The address** the shop's computers will type to reach I-NNOVA POS, for example `http://192.168.1.10/posinnova/public`. Use the computer's network address (`ipconfig` on Windows, `ip a` on Linux), not `localhost`, or the other computers of the shop will not reach it. On Linux with the document root on `public` (step 5), the address is `http://192.168.1.10`.
- **The database**: server, port, name, user and password. With XAMPP: `127.0.0.1`, `3306`, a new name such as `innova_pos`, user `root`, and an empty password (press Enter). It creates the database if it does not exist.

It then writes the settings (`.env`), creates the tables, creates the I-NNOVA superadmin with its own business "I-NNOVA", and prints a **private link**:

```
  The computer is ready. One step left, in a browser:

  http://192.168.1.10/posinnova/public/setup/Xk3...
```

If an installation stops part-way, fix the problem it names and run the same command again. It never touches an installation that already has a client business.

## 4. Create the business in the browser

Open the link. It asks for:

- **The business**: its name, currency (FCFA by default), time zone, country and city.
- **The owner**: name, username, password (8 characters or more), language, and the date until which the subscription is valid (7 years by default).

Press **Create the business**. The sign-in page opens: the owner signs in with the username and password just chosen, and sets up products, users and the rest from there.

The link works once, for 7 days. If it gets lost, run `php artisan innova:install` again for a new one.

### Who can sign in

| Account | Business | What it is for |
| --- | --- | --- |
| The owner created on the link | The client's business | Runs the shop: everything in the business |
| `kentojava` (the I-NNOVA superadmin) | I-NNOVA | Support: the Superadmin pages (businesses, subscriptions, packages, modules). Same username and password on every installation |

## 5. Linux only: the web server

Point the site at the `public` folder and let Apache write to two folders:

```
sudo chown -R www-data:www-data storage bootstrap/cache public/uploads
```

Example Apache site (`/etc/apache2/sites-available/innova.conf`):

```
<VirtualHost *:80>
    DocumentRoot /var/www/posinnova/public
    <Directory /var/www/posinnova/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Then `sudo a2ensite innova && sudo a2dissite 000-default && sudo systemctl reload apache2`.

For the scheduled tasks (reminders, automatic messages), add to the `www-data` crontab (`sudo crontab -u www-data -e`):

```
* * * * * cd /var/www/posinnova && php artisan schedule:run >> /dev/null 2>&1
```

## 6. After installing

- From another computer of the shop, open the address and sign in as the owner.
- On Windows, allow Apache through the firewall if other computers cannot reach it (Windows asks the first time Apache starts).
- Set XAMPP's Apache and MySQL to start with Windows (XAMPP Control Panel → Config → Autostart).
- Keep backups: the update command makes one each time (section 7), and the Backup page in the application makes more.

## 7. Updating an installation

In the folder of the code:

```
php artisan innova:update
```

It backs up the database to `storage/backups`, gets the new code from GitHub, installs new libraries if they changed, updates the tables and the superadmin, and clears the caches. If the computer has no internet, copy the new code into the folder by hand and run `php artisan innova:update --no-pull`.

## 8. The superadmin password

The superadmin `kentojava` has the same password on every installation. The code holds only a one-way scrambled form of it (a bcrypt hash in `config/innova.php`), never the password itself, because the repository is public.

To set or change it, on the I-NNOVA computer:

```
php artisan innova:superadmin-password
```

Type the new password twice (12 characters or more, letters and numbers), then commit and push `config/innova.php`. New installations get it by themselves; an existing installation gets it at its next `php artisan innova:update`, or right away with `php artisan innova:superadmin`.

Keep this password private: whoever has it can sign in as superadmin on every installation.
