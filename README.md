# Wepics

## WebUI

This backend for [Wepics WebUI](https://github.com/Kopchan/wepics-vue)


## Self-hosted with Docker (zero-config)

No configuration needed — just [Docker Desktop](https://www.docker.com/products/docker-desktop/):

```bash
docker compose up -d --build
```

Open [http://localhost:8080](http://localhost:8080) and put photo folders into `./media/`
(or mount your own folder in `docker-compose.yml`, see the `app` volumes).
New files are picked up automatically within ~5 minutes (scheduled `app:index`);
as a fallback they are also indexed on every stack (re)start.
Impatient: `docker compose exec app php artisan app:index`

Optional overrides via a `docker.env` file next to `docker-compose.yml`
(copy `docker.env.example` to `docker.env`):

```env
APP_NAME=MyGallery
APP_URL=http://localhost:8080
UPLOAD_ENABLE=true
```

Octane workers variant (maybe faster): `docker compose -f docker-compose.octane.yml up -d --build`

OG screenshots need Chromium: 

`docker compose --profile chromium up -d --build` 

or octane variant:

`docker compose -f docker-compose.octane.yml --profile chromium up -d --build`.


## Setup (dev, XAMPP)

Complete prerequirements:
* Install [Git](https://git-scm.com/download) to cloning this repo 
* Install [XAMPP](https://www.apachefriends.org/ru/download.html) to get Apache distribution easily 
* Install [Composer](https://getcomposer.org/download/) to get project dependencies

Open `XAMPP Control Panel` and click `Start` buttons next to `Apache` and `MySQL`

Open `shell` by clicking on the corresponding button and start enter following commands:

Go to folder with websites
```bash
cd htdocs
```

Create folder for project
```bash
mkdir wepics
```

Go to new folder
```bash
cd wepics
```

Clone this repo into current empty folder
```bash
git clone https://github.com/Kopchan/wepics-laravel .
```

Setup dependencies
```bash
composer i
```

Copy example config file into current folder at `.env` name
```bash
copy .env.example .env
```

Generate key
```bash
php artisan key:generate
```

Create database and fill basic info (press "y" if ask create DB)
```bash
php artisan migrate:fresh --seed
```

To access the website simply through the domain, you need to create a file called `.htaccess` in the root of the project and fill it with the following content:
```apacheconf
RewriteEngine on
RewriteRule (.*)? /public/$1
```
Or you can create symlink to `public/` folder, if you clone repo in different place

After you can open site at [http://localhost/wepics](http://localhost/wepics)

Or open API docs at [http://localhost/wepics/swagger/docs](http://localhost/wepics/swagger/docs)

## Add local folder

For add local folder for display in WebUI you can copy folder into `storage/app/images/` in project folder

Or you can create symlink to local folders:

Windows CMD with full paths: 
```bat
mklink /D "C:\xampp\htdocs\wepics\storage\app\images\ALBUM_NAME" "C:\Users\USERNAME\Pictures\GRAB_FOLDER_NAME"
```
Windows CMD if in the project folder:
```bat
cd storage\app\images\ 
mklink /D ALBUM_NAME "D:\Photos"
```
