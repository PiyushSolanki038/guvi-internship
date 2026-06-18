# GUVI Developer Internship — Register · Login · Profile

A full-stack registration, login, and profile management system built exactly to the GUVI internship spec — PHP, MySQL, MongoDB, Redis, jQuery AJAX, and Bootstrap 5.

**Live demo:** https://16-170-202-225.sslip.io
**Flow:** Register → Login → Profile

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, JavaScript (jQuery) |
| CSS Framework | Bootstrap 5 |
| Backend | PHP 8.4 |
| Relational DB | MySQL (registration data) |
| Document DB | MongoDB (profile data) |
| Session Store | Redis |
| Hosting | AWS EC2 (Ubuntu) + Nginx + Let's Encrypt SSL |

## Folder Structure

```
assets/              static assets (icons, images)
css/
  style.css          all styles — no inline CSS anywhere
js/
  register.js        registration page logic (jQuery AJAX)
  login.js           login page logic (jQuery AJAX)
  profile.js         profile page logic (jQuery AJAX)
php/
  config.php         DB/Redis/Mongo connections, env-based config
  register.php       handles registration → MySQL (prepared statements)
  login.php          handles login → MySQL lookup + Redis session token
  profile.php        handles profile GET/POST → MongoDB
  db_setup.sql        creates the MySQL database + users table
index.html           landing page
register.html        registration page
login.html           login page
profile.html         profile page (protected)
```

## Requirements Checklist

- [x] HTML, JS, CSS, and PHP code kept in separate files — no inline `<script>` or `style=""` anywhere
- [x] Only jQuery AJAX used for backend calls — no form submission
- [x] All forms built with Bootstrap 5 for responsiveness
- [x] MySQL stores registration data; MongoDB stores profile data
- [x] MySQL uses Prepared Statements exclusively — no raw SQL queries
- [x] Login session maintained only via browser `localStorage` — no PHP sessions
- [x] Redis stores the backend session token (`session:<token>` → `user_id`, 1 hour TTL)
- [x] Only 2 fonts used (Space Grotesk + Inter); icons are SVG, no raster images
- [x] Hosted on AWS EC2 with a free SSL certificate (Let's Encrypt)
- [x] Source uploaded to GitHub

## Local Setup

1. Install PHP 8.4+, MySQL, MongoDB, Redis, and Composer.
2. Install PHP dependencies:
   ```bash
   composer install
   ```
3. Copy the environment template and fill in your local credentials:
   ```bash
   cp php/.env.php.example php/.env.php
   ```
4. Create the database:
   ```bash
   mysql -u root < php/db_setup.sql
   ```
5. Start MySQL, MongoDB, and Redis locally, then serve the project:
   ```bash
   php -S localhost:8080
   ```
6. Open `http://localhost:8080` in your browser.

## API Endpoints

| Endpoint | Method | Description |
|---|---|---|
| `php/register.php` | POST | Create a new user in MySQL (prepared statements, bcrypt password hash) |
| `php/login.php` | POST | Verify credentials, issue a session token stored in Redis |
| `php/profile.php` | GET | Fetch the logged-in user's profile from MongoDB |
| `php/profile.php` | POST | Create/update the logged-in user's profile in MongoDB |

All authenticated requests send the session token via the `Authorization: Bearer <token>` header, which is verified against Redis on every call.
