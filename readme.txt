# Clinic Appointment Scheduling System

## Project Overview
A modern, web-based clinic appointment management system built with PHP, MySQL, HTML, CSS, and JavaScript. This system allows patients to book appointments online, doctors to manage their schedules, and administrators to oversee all operations.

## Features
- ✅ User Authentication (Patient, Doctor, Admin)
- ✅ Patient Registration and Profile Management
- ✅ Online Appointment Booking with Real-time Availability
- ✅ Doctor Dashboard with Patient Lists
- ✅ Admin Panel for Complete System Management
- ✅ Email Notifications for Appointments
- ✅ Responsive Design (Mobile Friendly)
- ✅ Appointment Status Tracking
- ✅ Prevent Double-booking
- ✅ Medical Records Management
- ✅ Reports Generation

## System Requirements
- XAMPP (PHP 7.4+ , MySQL 5.7+)
- Web Browser (Chrome, Firefox, Edge)
- Internet Connection (for CDN resources)

## Installation Steps

1. **Install XAMPP**
   - Download from: https://www.apachefriends.org/
   - Install in default directory

2. **Start XAMPP Services**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services

3. **Create Project Directory**
   - Navigate to C:\xampp\htdocs\
   - Create folder: `clinic-system`
   - Copy all project files into this folder

4. **Import Database**
   - Open browser and go to: http://localhost/phpmyadmin
   - Click on "New" to create database
   - Name it: `clinic_system`
   - Click on "Import" tab
   - Choose file: `sql/database.sql`
   - Click "Go" to import

5. **Configure Database Connection**
   - Open `config/database.php`
   - Update credentials if needed:
     ```php
     private $host = "localhost";
     private $db_name = "clinic_system";
     private $username = "root";
     private $password = "";