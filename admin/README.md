# Admin Panel - Refactored Structure

## Overview
تم تقسيم ملف `admin.php` الضخم إلى عدة ملفات منظمة حسب الوظائف، مما يحسن سهولة الصيانة والقراءة والتطوير.

## Directory Structure

```
/admin/
├── admin.php                 # Entry point - orchestrates auth and dashboard
├── auth.php                  # Authentication and login handling
├── data.php                  # Data loading and request handling
├── views/
│   ├── login.php            # Login page template
│   └── dashboard.php        # Main admin dashboard view
└── handlers/
    ├── password.php         # Admin password update
    ├── seo-settings.php     # SEO configuration
    ├── scripts-settings.php # Analytics and tracking scripts
    ├── ads-settings.php     # Ad placement controls
    ├── articles.php         # Article CRUD operations
    ├── sources.php          # RSS and web source management
    ├── smart-sources.php    # Smart unified source intake
    ├── workflow.php         # Content workflow selection
    ├── automation.php       # Pipeline and automation settings
    ├── titles.php           # Auto title generation settings
    └── settings.php         # General settings management
```

## File Descriptions

### Core Files

- **admin.php** - Main entry point that ties everything together
  - Includes authentication check
  - Loads data and processes requests
  - Renders the dashboard

- **auth.php** - Authentication handler
  - Manages login/logout
  - Handles password verification
  - Session management
  - Rate limiting for login attempts

- **data.php** - Data loading and coordination
  - Processes all POST requests from handlers
  - Loads database data (articles, sources, settings, stats)
  - Prepares variables for the view

- **views/login.php** - Bootstrap-based login form
- **views/dashboard.php** - Main admin interface template

### Handler Files (in `/admin/handlers/`)

Each handler file processes specific POST requests for a particular feature:

- **password.php** - Updates admin password with validation
- **seo-settings.php** - Manages SEO metadata and translation settings
- **scripts-settings.php** - Handles tracking codes (GA, GTM, Meta Pixel, etc.)
- **ads-settings.php** - Configures ad injection and placement rules
- **articles.php** - Article CRUD, bulk operations, demo content generation
- **sources.php** - RSS and website source management
- **smart-sources.php** - Unified smart source intake with auto-detection
- **workflow.php** - Content workflow selection (RSS vs Web)
- **automation.php** - Pipeline configuration, fetch settings, scheduler
- **titles.php** - Auto title generation modes and templates
- **settings.php** - Generic setting save/delete and page visit clearing

## How to Use

### Accessing the Admin Panel

The admin panel is now accessed through the new `/admin/` directory:

```
http://yoursite.com/admin/admin.php
```

### Flow

1. **User accesses** `/admin/admin.php`
2. **auth.php** checks if logged in, shows login if not
3. If logged in, **data.php** processes POST requests via handlers
4. **dashboard.php** renders the interface with loaded data
5. JavaScript manages panel navigation and interactions

### Maintaining the Structure

To add new admin functionality:

1. Create a new handler file in `/admin/handlers/my-feature.php`
2. Include it in `/admin/data.php` during POST processing:
   ```php
   if ($requestMethod === 'POST') {
       // ... existing handlers ...
       require_once __DIR__ . '/handlers/my-feature.php';
   }
   ```
3. Add corresponding form/UI in the dashboard view

## Benefits

- **Better Organization** - Functionality split by feature
- **Easier Maintenance** - Find and update specific features quickly
- **Reduced File Size** - Smaller, more manageable files
- **Improved Readability** - Clear separation of concerns
- **Better Testability** - Individual handlers can be tested independently
- **Faster Development** - Easier to add new features without touching large monolithic files

## Important Notes

- The original `/admin.php` in the root should ideally be updated to redirect to `/admin/admin.php` or replaced
- All database connections and functions are included via `functions.php`
- CSRF tokens are required for all POST requests
- Session management is handled in `auth.php`
- All user input is properly escaped using the `e()` function

## Database

The application uses SQLite database (typically `data.db`) configured in `config.php`:
- Articles table
- RSS sources table
- Web sources table
- Niche sources table (for multi-niche support)
- Settings table
- Page visits table

## Future Improvements

- Consider moving database logic to separate classes
- Implement a Router class for better URL handling
- Extract template rendering to a View class
- Add admin logging for security
- Implement role-based access control
