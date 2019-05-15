## System requirements

- Composer
- PHP >= 5.6

## Installation

```
git clone https://github.com/Kunstmaan/spaceport.git /some/path/spaceport
cd /some/path/spaceport
composer install
chmod +x /some/path/spaceport/spaceport
ln -s /some/path/spaceport/spaceport /usr/local/bin/spaceport   
```

## Building phar
1. Make sure you have [box](https://github.com/box-project/box2) installed
1. Create a new tag and push it
1. Build a new version ```box build -v```
1. Create a release on github and add the phar file
