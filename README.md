Maa Travels 🚌
Maa Travels is a web app for managing college bus services, built with PHP, Tailwind CSS, and MySQL using XAMPP. It simplifies user registration, route selection, payments, and receipts for students, with an admin panel for oversight.
Key Features 🚀

User Auth: Secure registration and login.
Route Selection: Browse and select bus routes.
Payments: Pay via card or UPI securely.
Receipts: Download PDFs or export CSVs.
Dashboard: View payments, routes, and activity.
Profile & Settings: Update details and preferences.
Admin Panel: Manage users, routes, and payments.
Responsive UI: Tailwind CSS for all devices.
Security: CSRF protection, password hashing.

Installation 🛠

Install XAMPP:

Download from apachefriends.org.
Start Apache and MySQL in XAMPP Control Panel.


Clone Repository:
git clone https://github.com/Hitendra213/Maa-Travels.git

(Replace with your GitHub link.)

Move to XAMPP:

Copy Maa-Travels to C:\xampp\htdocs.
Access at http://localhost/Maa-Travels.


Set Up Database:

In http://localhost/phpmyadmin, create database maatravels.
Update includes/config.php:$servername = "localhost";
$username = "root";
$password = "";
$port = 3306;
$dbname = "maatravels";


Tables auto-create via config.php or import maatravels.sql if available.


Run App:

Access http://localhost/Maa-Travels.
Register a user to start.


Admin Access:

Set is_admin = 1 in users table via phpMyAdmin.
Log in to access admin/index.php.



How It Works 🔧

Home (index.php): Services and user greetings.
Dashboard (dashboard.php): Payment and route stats.
Routes (routes.php): Select bus routes.
Payments (payment.php): Secure payment form.
Receipts (receipt.php): View/export receipts.
Profile/Settings (profile.php, settings.php): Manage details and preferences.
Admin Panel (admin/*.php): Manage system data.
Contact (contact.php): Submit inquiries.
About (about.php): Mission and vision.

Project Structure 📂
Maa-Travels/
├── admin/          # Admin panel
├── assets/         # Images (logo.png)
├── css/            # styles.css
├── includes/       # header.php, config.php
├── js/             # scripts.js
├── about.php       # About page
├── contact.php     # Contact form
├── dashboard.php   # User dashboard
├── index.php       # Home page
├── payment.php     # Payments
├── profile.php     # Profile
├── receipt.php     # Receipts
├── routes.php      # Routes
├── settings.php    # Settings
├── login.php       # Login
├── register.php    # Register
├── logout.php      # Logout
└── README.md       # Docs

Technologies 🧑‍💻

Frontend: HTML, Tailwind CSS v2.2.19, JavaScript
Backend: PHP 7.4+, PDO
Database: MySQL
Server: XAMPP (Apache, MySQL)
Security: CSRF, Bcrypt, sanitization

Troubleshooting ⚠️

DB Error: Check MySQL in XAMPP and config.php credentials.
404: Ensure project is in htdocs.
Admin: Verify is_admin in users table.
Ports: Adjust port conflicts in XAMPP or config.php.

Contributing 🤝

Fork the repo.
Create branch (git checkout -b feature/YourFeature).
Commit (git commit -m 'Add YourFeature').
Push (git push origin feature/YourFeature).
Open a pull request.

License 📜
MIT License. See LICENSE.
Contact 📧
Reach out to Hitendra213 or open an issue.

Happy Traveling with Maa Travels! 🛤️
