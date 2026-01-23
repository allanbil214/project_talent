# Talent Agency Platform - Auth & Core Classes

## Setup Instructions

### 1. Run Project Structure Setup

```bash
cd /path/to/your/webroot
bash setup_commands.sh
```

### 2. Install Composer Dependencies

```bash
cd talent-agency-platform
composer install
```

### 3. Configure Environment

```bash
cp .env.example .env
```

Edit `.env` with your settings:
- Database credentials
- SMTP settings for email
- Site URL

### 4. Import Password Resets Table

```bash
mysql -u root -p talent_agency < database/password_resets_table.sql
```

### 5. Set Permissions

```bash
chmod 755 uploads/*
chmod 644 uploads/*/.htaccess
chmod 644 config/*
chmod 644 classes/*
```

### 6. Test Authentication

Visit: `http://localhost/talent-agency-platform/api/auth.php?action=check-session`

Expected response:
```json
{
  "success": true,
  "logged_in": false,
  "user": null
}
```

## File Structure Created

```
talent-agency-platform/
├── config/
│   ├── database.php       ✅ PDO connection with .env support
│   ├── config.php         ✅ App settings & session config
│   └── constants.php      ✅ All enums and constants
├── includes/
│   ├── functions.php      ✅ 30+ helper functions
│   └── auth_check.php     ✅ Session validation middleware
├── classes/
│   ├── Database.php       ✅ PDO wrapper
│   ├── Validator.php      ✅ Form validation
│   ├── User.php          ✅ User CRUD operations
│   ├── Auth.php          ✅ Login/register/password reset
│   ├── Upload.php        ✅ Secure file uploads
│   └── Mail.php          ✅ PHPMailer wrapper
├── api/
│   └── auth.php          ✅ Authentication endpoints
├── uploads/              ✅ Protected with .htaccess
├── .env.example          ✅ Environment template
├── .gitignore           ✅ Security files excluded
└── composer.json        ✅ Dependencies defined
```

## API Endpoints Available

### POST /api/auth.php?action=login
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

### POST /api/auth.php?action=register
```json
{
  "email": "talent@example.com",
  "password": "password123",
  "role": "talent",
  "full_name": "John Doe"
}
```

### POST /api/auth.php?action=forgot-password
```json
{
  "email": "user@example.com"
}
```

### POST /api/auth.php?action=reset-password
```json
{
  "email": "user@example.com",
  "token": "abc123...",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

### POST /api/auth.php?action=change-password
```json
{
  "current_password": "oldpassword",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```

### GET /api/auth.php?action=check-session

### GET /api/auth.php?action=logout

## Security Features Implemented

✅ Password hashing with bcrypt
✅ CSRF token generation & validation
✅ Session security (httponly, secure cookies)
✅ SQL injection prevention (prepared statements)
✅ XSS prevention (htmlspecialchars)
✅ File upload validation (type, size, MIME)
✅ Session timeout
✅ Role-based access control
✅ Password reset with expiring tokens

## Next Steps

Other Claude sessions should create:
- Talent.php, Employer.php classes
- Job.php, Application.php classes
- Frontend pages (login.php, register.php, dashboards)
- Messaging & Notification systems

## Testing

You can test the API with curl:

```bash
# Register a talent
curl -X POST http://localhost/talent-agency-platform/api/auth.php?action=register \
  -H "Content-Type: application/json" \
  -d '{"email":"talent@test.com","password":"password123","role":"talent","full_name":"Test Talent"}'

# Login
curl -X POST http://localhost/talent-agency-platform/api/auth.php?action=login \
  -H "Content-Type: application/json" \
  -d '{"email":"talent@test.com","password":"password123"}'
```

## Notes

- All passwords are hashed with PASSWORD_DEFAULT (bcrypt)
- Sessions expire after 24 hours of inactivity
- File uploads are protected with .htaccess
- Email configuration required for password reset
- Database connection uses PDO with error handling