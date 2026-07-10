# Render Deployment Guide for Clinic System

## What was prepared
- Added Render service configuration in render.yaml
- Added a Procfile for the web process
- Updated the PHP database connection logic to read from environment variables

## 1. Push the project to GitHub
Make sure the repository contains these files:
- render.yaml
- Procfile
- config/database.php
- install.php
- reset_db.php

## 2. Create a MySQL database
Render needs a database service. Create one using:
- Render Managed MySQL, or
- another MySQL provider such as PlanetScale / Railway / Clever Cloud

Copy the connection string once it is created.

## 3. Create the Render web service
1. Go to Render Dashboard
2. Click New > Web Service
3. Connect your GitHub repository
4. Set the build/start commands if needed:
   - Build: php -v
   - Start: php -S 0.0.0.0:$PORT -t .
5. Add environment variables:
   - DATABASE_URL = your_mysql_connection_string
   - PHP_VERSION = 8.2

## 4. Initialize the database
After the service is deployed:
1. Open your Render URL + /install.php
2. Wait for the installation to complete
3. Then open the homepage

## 5. Default login credentials
- Admin: admin@clinic.com / admin123
- Doctor: dr.smith@clinic.com / doctor123
- Patient: john@example.com / patient123

## Notes
- The app uses environment-based database settings, so it will work on Render instead of hard-coded localhost values.
- The built-in PHP server is used for deployment compatibility.
- If you want a production-grade setup later, you can switch to Apache/Nginx with PHP-FPM.
