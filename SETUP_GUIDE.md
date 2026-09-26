# RENOPHP Setup Guide

Gabay sa pag-setup ng iyong RENOPHP application! 🚀

## 📋 Requirements

Bago magsimula, siguraduhing mayroon ka ng:

- ✅ PHP 8.0 or higher
- ✅ Composer
- ✅ MySQL, PostgreSQL, o SQLite
- ✅ Web server (Apache, Nginx, o PHP built-in server)

## 🛠️ Installation Steps

### Step 1: Install Dependencies

```bash
cd C:\Users\moren\Downloads\RENOPHP
composer install
```

### Step 2: Configure Environment

```bash
# Copy ang example environment file
copy .env.example .env

# I-edit ang .env file
# Lagyan ng database credentials
```

**Halimbawa ng .env configuration:**

```env
APP_NAME=RENOPHP
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=renophp_db
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Step 3: Create Database

```sql
CREATE DATABASE renophp_db;
```

### Step 4: Run Migrations

```bash
php console migrate
```

### Step 5: Start Development Server

```bash
php -S localhost:8000 -t public
```

**O kaya:**

```bash
php console serve
```

### Step 6: Open Browser

Buksan ang browser at pumunta sa:
```
http://localhost:8000
```

Makikita mo ang welcome page! 🎉

---

## 📁 Folder Structure Explained

```
RENOPHP/
├── app/                      # Iyong application code
│   ├── Controllers/          # HTTP controllers (handle requests)
│   ├── Models/               # Database models (Eloquent ORM)
│   ├── Middleware/           # Custom middleware
│   ├── Policies/             # Authorization policies
│   └── Requests/             # Form request validation
│
├── config/                   # Configuration files
│   ├── app.php              # App config
│   ├── database.php         # Database config
│   └── session.php          # Session config
│
├── database/                 # Database related files
│   ├── migrations/          # Database version control
│   ├── seeders/             # Test data generators
│   └── factories/           # Model factories
│
├── public/                   # Web root (publicly accessible)
│   ├── index.php            # Entry point
│   ├── css/                 # Stylesheets
│   ├── js/                  # JavaScript files
│   └── images/              # Images
│
├── resources/                # Application resources
│   ├── views/               # Blade templates
│   │   ├── layouts/         # Layout templates
│   │   └── components/      # Reusable components
│   └── assets/              # Raw assets (SASS, etc.)
│
├── routes/                   # Route definitions
│   ├── web.php              # Web routes
│   └── api.php              # API routes
│
├── storage/                  # Generated files
│   ├── cache/               # Application cache
│   ├── logs/                # Log files
│   ├── sessions/            # Session files
│   └── uploads/             # User uploads
│
├── src/                      # Framework core
│   ├── Auth/                # Authentication
│   ├── Database/            # Database & ORM
│   ├── Http/                # HTTP layer
│   ├── Routing/             # Router
│   ├── Validation/          # Validation
│   └── View/                # Template engine
│
└── vendor/                   # Composer dependencies
```

---

## 🎯 Creating Your First Feature

### 1. Create a Route

**routes/web.php**
```php
use App\Controllers\PostController;

$router->get('/posts', [PostController::class, 'index']);
$router->post('/posts', [PostController::class, 'store']);
```

### 2. Create a Controller

```bash
# Create manually o gawa ng generator (future feature)
```

**app/Controllers/PostController.php**
```php
<?php

namespace App\Controllers;

use App\Models\Post;
use Reno\Http\Request;
use Reno\Http\Response;

class PostController
{
    public function index()
    {
        $posts = Post::all();
        return view('posts.index', ['posts' => $posts]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'content' => 'required'
        ]);

        $post = Post::create($validated);

        return Response::json([
            'success' => true,
            'data' => $post
        ], 201);
    }
}
```

### 3. Create a Model

**app/Models/Post.php**
```php
<?php

namespace App\Models;

use Reno\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = ['title', 'content', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### 4. Create a Migration

**database/migrations/2024_01_01_000002_create_posts_table.php**
```php
<?php

use Reno\Database\Migrations\Migration;
use Reno\Database\Schema\Blueprint;
use Reno\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

Run migration:
```bash
php console migrate
```

### 5. Create a View

**resources/views/posts/index.blade.php**
```html
@extends('layouts.app')

@section('title', 'Posts')

@section('content')
    <div class="container">
        <h1>All Posts</h1>

        @if($posts->isEmpty())
            <p>Walang posts pa.</p>
        @else
            <div class="posts">
                @foreach($posts as $post)
                    <div class="card">
                        <h2>{{ $post->title }}</h2>
                        <p>{{ $post->content }}</p>
                        <small>By {{ $post->user->name }}</small>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
```

---

## 🔒 Security Best Practices

### 1. Never Commit .env File
```bash
# Already in .gitignore
.env
```

### 2. Use CSRF Protection
```php
// Automatic sa forms
@csrf
```

### 3. Validate All Input
```php
$request->validate([
    'email' => 'required|email',
    'password' => 'required|min:8'
]);
```

### 4. Hash Passwords
```php
$user->password = password_hash($password, PASSWORD_BCRYPT);
```

### 5. Use Prepared Statements
```php
// Automatic sa Query Builder at Eloquent
User::where('email', $email)->first();
```

---

## 🚀 Deployment

### For Shared Hosting

1. Upload files via FTP
2. Point domain to `/public` folder
3. Update `.env` for production:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   ```

### For VPS (Ubuntu)

```bash
# Install PHP & Extensions
sudo apt update
sudo apt install php8.1 php8.1-fpm php8.1-mysql php8.1-mbstring

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Clone & Setup
git clone your-repo
cd your-app
composer install --no-dev
cp .env.example .env
# Edit .env

# Run migrations
php console migrate

# Setup Nginx/Apache to point to /public
```

---

## 📚 Common Commands

```bash
# Development server
php -S localhost:8000 -t public
php console serve

# Database
php console migrate
php console migrate:rollback
php console migrate:fresh

# View routes
php console route:list

# Clear cache
php console cache:clear
```

---

## 💡 Tips

1. **Start simple** - Gumawa muna ng simple CRUD
2. **Read the docs** - Check `docs/` folder
3. **Use examples** - Tingnan ang `examples/` folder
4. **Test often** - Run tests regularly
5. **Keep it clean** - Follow PSR standards

---

## 🆘 Troubleshooting

### "Class not found" error
```bash
composer dump-autoload
```

### Database connection error
- Check `.env` credentials
- Siguraduhing running ang MySQL/PostgreSQL
- Verify database exists

### 500 Internal Server Error
- Check `storage/logs/` for errors
- Set `APP_DEBUG=true` sa `.env`
- Check file permissions

### Routing not working
- Check `.htaccess` file
- Enable `mod_rewrite` sa Apache
- Siguraduhing `/public` ang document root

---

## 📞 Need Help?

- Read documentation: `docs/`
- Check examples: `examples/`
- GitHub Issues: [link]
- Community: [link]

---

**Happy Coding! 🎉**

Built with ❤️ using RENOPHP
