
# Café POS System - (client group 6)
# Group 8 - Ric Vincent Villanueva (Project Leader, Main Developer)
          - Anie Jane Austria (Documentation Specialist)
          - Susan Jra Ombao (Documentation Specialist)
          - Nina De Jesus


A comprehensive Point of Sale (POS) system designed specifically for café operations, featuring inventory management, sales tracking, and supplier order management.

## Features

### 1. User Management
- Secure login system with role-based access control
- User roles: Manager, Cashier, Kitchen
- Profile management with password security
- Audit logging for user actions

### 2. Sales System
- Real-time product availability checking
- Dynamic cart management
- Stock limit validation
- Automatic hiding of out-of-stock products
- Receipt generation and printing
- Sales history tracking

### 3. Inventory Management
- Real-time stock tracking
- Low stock alerts (≤ 10 items)
- Product categorization (Hot Drinks, Cold Drinks, Snacks)
- Image upload for products
- Supplier order management system
  * Order status tracking (Pending → Preparing → Out for Delivery → Delivered)
  * Automated status progression with countdown timers
  * Order cancellation for pending orders
  * Stock auto-update upon delivery

### 4. Audit & Reporting
- Comprehensive audit logs for:
  * User logins/logouts
  * Sales transactions
  * Inventory changes
- Receipt viewing functionality
- Transaction history

## Technical Requirements

### Server Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP (recommended for local development)

### Browser Requirements
- Modern web browsers (Chrome, Firefox, Safari, Edge)
- JavaScript enabled
- Minimum screen resolution: 1024x768

## Installation

1. Clone the repository to your web server directory:
   ```bash
   git clone [repository-url]
   ```

2. Import the database:
   - Navigate to phpMyAdmin
   - Create a new database named 'db_pos_cafe'
   - Import the `db_pos_cafe.sql` file

3. Configure database connection:
   - Open `includes/db-conn.php`
   - Update database credentials if needed

4. Set up file permissions:
   - Ensure `uploads/` directory is writable
   - Set appropriate permissions for image uploads

## Directory Structure

```
cafes_pos/
├── dist/           # Main application files
├── includes/       # Common includes and configurations
├── database/       # Database related files
├── uploads/        # Product image uploads
├── img/           # Static images
└── vendor/        # Dependencies
```

## Security Features

- Password hashing
- SQL injection prevention
- XSS protection
- CSRF protection
- Session security
- Input validation

## Usage

1. Access the system through your web browser
2. Account Credentials:
Manager:
manager1 cafe-manager-1

Cashier:
cashier1 cafe-cashier-1

Kitchen:
kitchen1', cafe-kitchen-1

(add account as needed)

3. Change default passwords after first login

## Maintenance

- Monitor log files
- Keep dependencies updated
- Check for low stock items
- Review audit logs periodically

## Support

For issues and support, please contact:
- Technical Support Developer (Ric Villanueva: BA 3202)

## License

This EDM project is proprietary software. All rights reserved.

=======
# cafe_pos_edm
>>>>>>> 4674a8944b89c1e7852b99380cacd62462e06bf2
