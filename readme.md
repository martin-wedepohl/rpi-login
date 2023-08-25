## Install xdebug for php debugging

```
sudo apt install php-xdebug
```

### Log into database

## Create MySQL Database

### Log into database

From the command prompt on the Raspberry Pi server log in with root privilages.

`sudo mysql`

### Create MySQL User

The user will be USERNAME with the password of PASSWORD.

`MariaDB [(none)]> CREATE USER 'USERNAME'@'localhost' IDENTIFIED BY 'PASSWORD';`

### Grant privilages

User USERNAME will be able to do anything in the database DB_NAME.

```
MariaDB [(none)]> GRANT ALL PRIVILAGES ON DB_NAME.* TO 'USERNAME'@'localhost';
MariaDB [(none)]> FLUSH PRIVILEGES;
```

### Create database

Create the database DB_NAME with the default utf8mb4 character set.

`MariaDB [(none)]> CREATE DATABASE DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci`;

### Create tables

First we select the database.
Then we create tables.

1. users table (primary key of id, with username (UNIQUE), name, hash (optional) and token (optional)).
2. errorlog table (primary key of id, with line number, date, filename and error).

`MariaDB [(none)]> USE DB_NAME;`
```
    MariaDB [DB_NAME]> CREATE TABLE `DB_NAME`.`users`
    (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
     `modification` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     `username` VARCHAR(50) NOT NULL,
     `name` VARCHAR(100) NOT NULL,
     `email` VARCHAR(100) NOT NULL,
     `hash` VARCHAR(500) NULL,
    PRIMARY KEY (`id`),
    UNIQUE `u_un` (`username`),
    UNIQUE `u_em` (`email`)),
    ENGINE = InnoDB;
```

```
    MariaDB [DB_NAME]> CREATE TABLE `DB_NAME`.`errorlog`
    (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
     `line` INT UNSIGNED NOT NULL,
     `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     `filename` VARCHAR(250) NOT NULL,
     `errro` VARCHAR(1000) NOT NULL,
     PRIMARY KEY (`id`))
     ENGINE = InnoDB;
```

## Add Composer for php classes

### Install composer

In a command prompt in the working directory get, install composer and verify composer.

```
wget -O composer-setup.php https://getcomposer.org/installer
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```

Should get something like `Composer version 2.5.8 2023-06-09 17:13:21`.

Remove composer installation file `rm -rf composer-setup.php`.

### Initialize composer

```
composer init
```

Accept most of the defaults and install a project

```
Welcome to the Composer config generator

This command will guide you through creating your composer.json config.

Package name (<vendor>/<name>) [pi/work]:
Description []:
Author [n to skip]: n
Minimum Stability []:
Package Type (e.g. library, project, metapackage, composer-plugin) []: project
License []:

Define your dependencies.

Would you like to define your dependencies (require) interactively [yes]? n
Would you like to define your dev dependencies (require-dev) interactively [yes]? n
Add PSR-4 autoload mapping? Maps namespace "Pi\Work" to the entered relative path. [src/, n to skip]: n

{
    "name": "pi/work",
    "type": "project",
    "require": {}
}

Do you confirm generation [yes]? yes
```

### Edit and save the composer.json file

Add the appropriate information for the PSR4 class generation.

```
{
    "name": "pi/work",
    "type": "project",
    "require": {},
    "autoload": {
        "psr-4": {
            "CAT\\": "/",
            "CAT\\Api\\": "Api/"
        }
    }
}
```

### Run the composer installation

```
composer install
```

### Update if you add any other directories or classes not under the Api directory.

```
composer dump-autoload
```
