@echo off
echo Setting up Installment Management System...

echo Creating upload directories...
if not exist "uploads\customers" mkdir uploads\customers
if not exist "uploads\products" mkdir uploads\products

echo Setting up database configuration...
if not exist "config\db.php" (
    copy "config\db.example.php" "config\db.php"
    echo Please edit config\db.php with your database credentials
)

echo Setup complete!
echo Next steps:
echo 1. Edit config\db.php with your database credentials
echo 2. Import database\schema.sql into your MySQL database
echo 3. Access the application at http://localhost/installment_app
pause