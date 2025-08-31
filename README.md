# LTDEDN - Laravel Admin Panel

A Laravel 12 application with Vue 3 + Inertia.js frontend featuring a comprehensive admin panel for managing users, artists, products, and product editions.

## Features

- **Multi-Role Authentication**: Admin, Artist, and User roles with granular permissions
- **Admin Panel**: Full CRUD operations for users, artists, products, and product editions
- **Role-Based Access**: Artists can only manage their own content, admins see everything
- **Modern Stack**: Laravel 12, Vue 3, Inertia.js v2, Tailwind CSS v4
- **Comprehensive Testing**: Feature tests with proper Inertia.js testing practices
- **Database Design**: Products with editions, QR code generation, soft deletes

## Tech Stack

- **Backend**: Laravel 12.26.2, PHP 8.4.11
- **Frontend**: Vue 3.5.18, Inertia.js v2, Tailwind CSS v4.1.12
- **Database**: MySQL/SQLite
- **Testing**: PHPUnit with Inertia.js testing helpers
- **Code Quality**: Laravel Pint, ESLint, Prettier

## Requirements

- PHP 8.4+
- Composer
- Node.js 18+
- MySQL or SQLite
- Git

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd ltdedn
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Node.js Dependencies

```bash
npm install
```

### 4. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 5. Database Configuration

Edit your `.env` file with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ltdedn
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Or use SQLite for development:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite
```

### 6. Run Migrations and Seeders

```bash
php artisan migrate:fresh --seed
```

This will create:
- All necessary database tables
- Sample users with different roles
- Sample artists and products
- Product editions with QR codes

## Development

### Start the Development Servers

You need to run both the Laravel backend and the frontend build process:

**Terminal 1 - Laravel Server:**
```bash
php artisan serve
```

**Terminal 2 - Frontend Development:**
```bash
npm run dev
```

Or use Laravel Sail if you prefer Docker:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev
```

### Access the Application

- **Application**: http://localhost:8000
- **Admin Panel**: http://localhost:8000/admin

### Default Login Credentials

The seeder creates default users for testing:

- **Admin User**: 
  - Email: `admin@example.com`
  - Password: `password`
  - Role: Admin (full access)

- **Artist User**: 
  - Email: `artist@example.com` 
  - Password: `password`
  - Role: Artist (can manage owned artists and their products)

- **Regular User**:
  - Email: `user@example.com`
  - Password: `password` 
  - Role: User (limited access)

## Admin Panel Features

### User Management (Admin Only)
- View all users
- Create/edit/delete users
- Manage user roles and permissions

### Artist Management (Admin Only)
- View all artists
- Create/edit/delete artist profiles
- Assign ownership to users

### Product Management (Admin + Artists)
- **Admins**: Can manage all products
- **Artists**: Can only manage products for artists they own
- Full CRUD operations
- Product fields: name, description, pricing, edition settings
- Automatic slug generation and QR secret creation

### Product Edition Management (Admin + Artists)
- Nested under products
- Edition numbering (unique per product)
- Status tracking (available, sold, redeemed, etc.)
- QR code generation (long + short codes)
- Owner assignment

## Testing

### Run All Tests

```bash
php artisan test
```

### Run Specific Test Suites

```bash
# Product tests
php artisan test tests/Feature/Admin/ProductTest.php

# Product Edition tests  
php artisan test tests/Feature/Admin/ProductEditionTest.php

# Dashboard tests
php artisan test tests/Feature/Admin/AdminDashboardTest.php
```

### Test Coverage

The application includes comprehensive feature tests covering:
- Authentication and authorization
- CRUD operations for all entities
- Role-based access control
- Validation rules
- Database constraints
- Inertia.js responses

## Code Quality

### PHP Code Formatting

```bash
# Check and fix PHP code style
vendor/bin/pint

# Check without fixing
vendor/bin/pint --test
```

### JavaScript/Vue Linting

```bash
# Check and fix JavaScript/Vue issues
npm run lint

# Check without fixing  
npm run lint:check
```

## Database Schema

### Key Tables

- **users**: User accounts with roles (admin, artist, user)
- **artists**: Artist profiles owned by users
- **products**: Products created by artists
- **product_editions**: Individual editions of products with QR codes

### Relationships

- User → Artist (one-to-many, as owner)
- Artist → Product (one-to-many)  
- Product → ProductEdition (one-to-many)
- User → ProductEdition (one-to-many, as owner)

## API Endpoints

### Authentication
- `POST /login` - User login
- `POST /logout` - User logout
- `POST /register` - User registration

### Admin Panel (Protected Routes)
- `GET /admin` - Dashboard
- `GET /admin/users` - User management
- `GET /admin/artists` - Artist management  
- `GET /admin/products` - Product management
- `GET /admin/products/{product}/editions` - Edition management

## Deployment

### Production Build

```bash
# Install dependencies
composer install --optimize-autoloader --no-dev
npm ci

# Build frontend assets
npm run build

# Optimize Laravel
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force
```

### Environment Variables

Ensure these are set in production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=your-db-name
DB_USERNAME=your-db-user
DB_PASSWORD=your-secure-password

# Mail (for notifications)
MAIL_MAILER=smtp
MAIL_HOST=your-mail-host
MAIL_PORT=587
MAIL_USERNAME=your-mail-user
MAIL_PASSWORD=your-mail-password
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests (`php artisan test`)
5. Run code quality checks (`vendor/bin/pint && npm run lint`)
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support or questions, please open an issue in the repository.
