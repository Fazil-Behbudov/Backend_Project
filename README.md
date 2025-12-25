# Driving Experience Application

A PHP + MySQL web app for logging and analyzing driving experiences. Backend uses PDO and sessions; frontend is responsive HTML/CSS.

## Setup

1. Copy `Fazil Behbudov__Application/api/config.example.php` to `Fazil Behbudov__Application/api/config.php` and fill your database credentials.
2. Ensure PHP (>= 8.0) is installed.

## Run locally

```bash
cd "/home/admin123/Desktop/Backend_Project"
php -S 127.0.0.1:8001 -t "Fazil Behbudov__Application"
```

Then open http://127.0.0.1:8001/ in your browser.

## Deploy notes

- Do not commit real credentials. `api/config.php` is ignored; commit the `config.example.php` instead.
- MySQL schema expects related tables for weather/traffic/road type/maneuver.

## GitHub

Initialize git, commit, and push to your repository (replace the remote URL with your repo):

```bash
cd "/home/admin123/Desktop/Backend_Project"
# first time
git init
git checkout -b main
git add .
git commit -m "Initial commit: app + API"
# set your repo URL
# git remote add origin https://github.com/<your-username>/<your-repo>.git
# git push -u origin main
```