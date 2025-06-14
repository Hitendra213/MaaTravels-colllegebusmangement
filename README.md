Maa Travels 🚌
Welcome to Maa Travels, a robust web application designed for managing college bus transportation services. Built with PHP, Tailwind CSS, and MySQL, this platform streamlines user registration, route selection, payments, and receipt management for students, with a powerful admin panel for overseeing operations. Whether you're a student tracking your bus route or an admin managing payments, Maa Travels ensures a seamless experience!
Key Features 🚀

User Registration & Authentication: Securely register, log in, and manage your profile with email and password validation.
Route Selection: Browse and select bus routes with a search feature for easy navigation.
Payment Processing: Make payments via credit/debit cards or UPI, with secure transaction handling and receipt generation.
Receipt Management: View, download, and export payment receipts as PDFs or CSV files.
User Dashboard: Get an overview of total payments, pending payments, and selected routes with recent activity.
Profile Management: Update personal details like name, email, mobile, address, and password securely.
Settings: Customize notification preferences (email/SMS) and preferred currency.
Admin Panel: Manage users, routes, payments, and contacts with advanced search, export, and analytics features.
Responsive Design: A fluid, Tailwind CSS-powered UI optimized for desktops, tablets, and mobiles.
Security: CSRF protection, input sanitization, password hashing, and secure session management.

Installation 🛠
Follow these steps to set up Maa Travels locally:

Clone the Repository:
git clone https://github.com/Hitendra213/Maa-Travels.git

(Replace with your actual GitHub repository link.)

Navigate to the Project Directory:
cd Maa-Travels


Set Up the Database:

Install MySQL and create a database named maatravels.
Import the database schema from maatravels.sql (create this file by exporting your database if not already provided).
Update includes/config.php with your MySQL credentials:$servername = "localhost";
$username = "root";
$password = "your_password";
$port = 3307;
$dbname = "maatravels";




Install Dependencies:

Ensure PHP (>=7.4) and a web server (e.g., Apache) are installed.
No additional PHP dependencies are required as the project uses native PHP and PDO.


Serve the Application:

Place the project in your web server’s root directory (e.g., htdocs for XAMPP).
Access the app via http://localhost/Maa-Travels or your configured domain.


Admin Access:

Register a user and manually set is_admin = 1 in the users table for admin privileges.
Access the admin panel at admin/index.php.



How the App Works 🔧

Home Page (index.php):

Showcases services (flight booking, hotel reservations, tour packages) with a hero section and call-to-action buttons.
Personalized welcome for logged-in users.


User Dashboard (dashboard.php):

Displays key metrics (total payments, amount paid, pending payments, selected route) and recent payment history.


Route Selection (routes.php):

Lists available bus routes with search and selection options, updating the user’s profile.


Payment Processing (payment.php):

Secure form for entering payment details (card or UPI) with client-side validation.


Receipt Management (receipt.php):

View all payments with options to download individual receipts as PDFs or export all as CSV.


Profile & Settings (profile.php, settings.php):

Update personal details and manage notification preferences or currency settings.


Admin Panel (admin/*.php):

Manage users, routes, payments, and contacts with search, export, and analytics features.


Contact Page (contact.php):

Submit inquiries with CSRF-protected forms, stored in the database.


About Page (about.php):

Highlights the mission and vision of Maa Travels.



Project Structure 📂
Maa-Travels/
├── admin/                  # Admin panel files
├── assets/                 # Images and static assets
├── css/                    # Custom styles (styles.css)
├── includes/               # Shared components (header.php, footer.php, config.php)
├── js/                     # JavaScript (scripts.js)
├── about.php               # About page
├── contact.php             # Contact form
├── dashboard.php           # User dashboard
├── index.php               # Home page
├── payment.php             # Payment processing
├── profile.php             # User profile management
├── receipt.php             # Receipt management
├── routes.php              # Route selection
├── settings.php            # User settings
├── login.php               # User login
├── register.php            # User registration
├── logout.php              # User logout
└── README.md               # Project documentation

Technologies Used 🧑‍💻

Frontend: HTML, Tailwind CSS v2.2.19, JavaScript
Backend: PHP 7.4+, PDO for MySQL
Database: MySQL (tables: users, payments, contacts, routes, user_settings)
Security: CSRF tokens, password hashing (Bcrypt), input sanitization, HTTP headers
Server: Apache (recommended for local development)

Contributing 🤝
Contributions are welcome! To contribute:

Fork the repository.
Create a feature branch (git checkout -b feature/YourFeature).
Commit your changes (git commit -m 'Add YourFeature').
Push to the branch (git push origin feature/YourFeature).
Open a pull request.

Please ensure your code follows the project’s coding standards and includes appropriate tests.
License 📜
This project is licensed under the MIT License. See the LICENSE file for details.
Contact 📧
For questions or support, reach out to Hitendra213 or open an issue on the repository.

Happy Traveling with Maa Travels! 🛤️
