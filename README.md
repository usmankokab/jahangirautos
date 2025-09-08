# Installment Management System

A comprehensive PHP-based installment management system for tracking sales, customers, products, and payment schedules.

## Features

- **Customer Management**: Add, edit, and manage customer information with image upload and camera capture
- **Product Management**: Manage product catalog with pricing and installment terms
- **Sales Management**: Record sales with automatic installment calculation
- **Installment Tracking**: Monitor payment schedules and track overdue payments
- **Reporting**: Generate comprehensive reports and analytics
- **Responsive Design**: Bootstrap-based UI that works on all devices

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Modern web browser with camera support (for photo capture)

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/installment-management-system.git
cd installment-management-system
```

2. Create database and import schema:
```sql
CREATE DATABASE installment_db;
```

3. Configure database connection:
```php
// Copy config/db.example.php to config/db.php and update:
$servername = "localhost";
$username = "your_username";
$password = "your_password";
$dbname = "installment_db";
```

4. Set up file permissions:
```bash
chmod 755 uploads/customers/
chmod 755 uploads/products/
```

5. Access the application at `http://localhost/installment_app`

## Project Structure

```
installment_app/
├── actions/          # Backend processing files
├── assets/           # CSS, JS, images
├── config/           # Database configuration
├── includes/         # Header, footer, navigation
├── uploads/          # User uploaded files
├── views/            # Frontend pages
└── database/         # SQL schema files
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is licensed under the MIT License.