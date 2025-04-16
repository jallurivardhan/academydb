# AcademyDB Management System

A comprehensive academic management system built with PHP and MySQL, featuring role-based access control and secure data handling.

## Features

- **Role-Based Access Control**
  - Admin Dashboard
  - Faculty Dashboard
  - Student Dashboard

- **Student Features**
  - Course Registration
  - View Grades
  - Track Attendance
  - Submit Assignments
  - Access Course Materials
  - Security Settings

- **Faculty Features**
  - Course Management
  - Grade Management
  - Attendance Tracking
  - Assignment Management
  - Course Materials Upload
  - Security Settings

- **Admin Features**
  - User Management
  - System Reports
  - Database Management
  - Security Management
  - Data Backup

## Security Features

- Session Management
- CSRF Protection
- SQL Injection Prevention
- XSS Prevention
- Password Policy Enforcement
- Activity Logging
- Rate Limiting
- Data Masking

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- SSL certificate for HTTPS

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/academydb-management.git
   ```

2. Create a MySQL database and import the SQL files in the following order:
   ```bash
   mysql -u root -p your_database < sql/create_tables.sql
   mysql -u root -p your_database < sql/RBAC.sql
   mysql -u root -p your_database < sql/data_masking.sql
   ```

3. Configure the database connection:
   - Copy `config/database.example.php` to `config/database.php`
   - Update the database credentials in `config/database.php`

4. Set up the web server:
   - Configure the document root to point to the project directory
   - Enable URL rewriting (for Apache, ensure mod_rewrite is enabled)

5. Set proper permissions:
   ```bash
   chmod 755 -R /path/to/project
   chmod 777 -R /path/to/project/uploads
   chmod 777 -R /path/to/project/backups
   ```

6. Access the application:
   - Open your web browser and navigate to `https://your-domain.com`
   - Log in with the default admin credentials (change these after first login):
     - Username: admin
     - Password: Admin@123

## Directory Structure

```
academydb-management/
├── admin/           # Admin interface files
├── faculty/         # Faculty interface files
├── student/         # Student interface files
├── config/          # Configuration files
├── includes/        # Common include files
├── uploads/         # File upload directory
├── backups/         # Database backup directory
├── css/            # Stylesheets
└── sql/            # Database scripts
```

## Security Considerations

- Always use HTTPS in production
- Change default admin credentials immediately
- Regularly update dependencies
- Enable error logging but disable display_errors in production
- Regularly backup the database
- Monitor security logs
- Keep the system updated

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, please contact [support email] or create an issue in the repository.

## Acknowledgments

- Bootstrap for UI components
- jQuery for JavaScript functionality
- MySQL for database management
- PHP community for various libraries and tools 