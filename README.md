# RenoPHP

**A simple and beginner-friendly PHP framework for building web applications.**

RenoPHP is a PHP web framework created by **Moreno Jumyr**.

It is designed for developers who are learning PHP and want to build real web applications without getting overwhelmed by complicated framework concepts.

> **Less Magic. More Understanding.**

---

## Why RenoPHP?

Learning PHP is already a process. Learning a large framework at the same time can sometimes make things harder, especially for beginners.

RenoPHP focuses on making the basic concepts of web development easier to understand.

With RenoPHP, beginners can learn how things work while building an actual application.

### RenoPHP focuses on:

* Simple project structure
* Beginner-friendly syntax
* Easy routing
* MVC architecture
* Database support
* Simple CLI commands
* Helpful error messages
* Validation
* Authentication
* Middleware
* API development
* Secure defaults
* Modular architecture

---

## Made for Beginners

RenoPHP is designed with beginners in mind.

Instead of hiding everything behind complicated abstractions, RenoPHP tries to keep important parts of the application understandable.

For example:

```php
Route::get('/students', StudentController::class, 'index');
```

A beginner can understand what is happening:

```text
Route::get()
    ↓
GET request
    ↓
/students
    ↓
StudentController
    ↓
index()
```

The goal is not just to make the code shorter.

The goal is to make the code easier to understand.


## Learn While Building

RenoPHP is not only designed to help developers build applications.

It is also designed to help them learn.

For example, instead of simply generating code, RenoPHP can explain where the generated code belongs and what it does.

This makes the framework useful for:

* Students
* Beginners learning PHP
* Developers learning MVC
* Developers building school projects
* Developers creating small to medium web applications

---

## Simple Routing

Create routes using a simple syntax:

```php
Route::get('/', HomeController::class, 'index');

Route::get('/students', StudentController::class, 'index');

Route::get('/students/{id}', StudentController::class, 'show');
```

HTTP methods supported by the framework include:

```text
GET
POST
PUT
PATCH
DELETE
```

---

## Controllers

Controllers handle application requests.

Example:

```php
<?php

namespace App\Controllers;

class StudentController
{
    public function index()
    {
        return view('students.index');
    }
}
```

Create a controller using the CLI:

```bash
php reno make:controller StudentController
```

---

## Database

RenoPHP provides tools for working with databases.

The initial database targets are:

* MySQL
* MariaDB
* SQLite
* PostgreSQL

Example:

```php
$students = DB::table('students')->get();
```

You can also use conditions:

```php
$students = DB::table('students')
    ->where('status', 'active')
    ->get();
```

---

## Models

Models represent application data.

Example:

```php
<?php

namespace App\Models;

class Student extends Model
{
    protected $table = 'students';
}
```

Then:

```php
$student = Student::find(1);
```

---

## Migrations

Create a migration:

```bash
php reno make:migration create_students_table
```

Run migrations:

```bash
php reno migrate
```

Rollback migrations:

```bash
php reno migrate:rollback
```

Migrations make it easier to create and update database structures without manually editing the database every time.

---

## Validation

Validate user input with simple rules:

```php
$request->validate([
    'name' => 'required|string',
    'email' => 'required|email',
    'password' => 'required|min:8'
]);
```

Validation can be used for both web applications and APIs.

---

## Middleware

Middleware allows you to control requests before they reach your controller.

Example:

```php
Route::middleware('auth')
    ->get('/dashboard', DashboardController::class, 'index');
```

Middleware can be used for:

* Authentication
* Authorization
* Logging
* Rate limiting
* CORS
* Request filtering

---

## Authentication

RenoPHP plans to provide built-in authentication tools.

These include:

* Login
* Logout
* Registration
* Password hashing
* Password reset
* Email verification
* Sessions
* API authentication

Example:

```php
Auth::attempt([
    'email' => $email,
    'password' => $password
]);
```

---

## API Development

RenoPHP can also be used to build REST APIs.

Example:

```php
Route::get('/api/students', StudentController::class, 'index');
```

Return JSON:

```php
return response()->json([
    'success' => true,
    'data' => $students
]);
```

---

## CLI

RenoPHP includes a command-line interface for common development tasks.

Start the development server:

```bash
php reno serve
```

Create a controller:

```bash
php reno make:controller StudentController
```

Create a model:

```bash
php reno make:model Student
```

Create a migration:

```bash
php reno make:migration create_students_table
```

View routes:

```bash
php reno route:list
```

Run migrations:

```bash
php reno migrate
```

---

## Reno Doctor

One of the goals of RenoPHP is to make errors easier to understand.

Run:

```bash
php reno doctor
```

Reno Doctor can check common problems such as:

```text
PHP version
Required extensions
Environment configuration
Database connection
Storage permissions
Application configuration
```

Instead of simply showing:

```text
Database connection failed.
```

RenoPHP aims to provide useful information such as:

```text
Database Connection Failed

Host: 127.0.0.1
Port: 3306
Database: school_system

Possible causes:

- MySQL is not running
- Database name is incorrect
- Username or password is incorrect
- MySQL is using a different port
```

This is especially useful for beginners who may not know where to start when an application fails.

---

## Helpful Error Messages

RenoPHP aims to make errors easier to understand.

For example:

```text
Route Not Found

Request:
GET /student

Did you mean:

GET /students
```

Instead of giving developers an error with no explanation, RenoPHP tries to provide enough information to help identify the problem.

---

## Security

RenoPHP is designed with security in mind.

Planned security features include:

* Prepared SQL statements
* Password hashing
* CSRF protection
* XSS protection
* Secure sessions
* Secure cookies
* Authentication
* Authorization
* Rate limiting
* CORS configuration
* File upload validation
* Path traversal protection
* Security headers

Security should be part of the framework instead of something developers have to add later.

---

## Simple by Default

RenoPHP follows a simple rule:

> **Start simple. Add complexity when you need it.**

A small project should be able to remain small.

A larger application should still have the tools needed to grow.

RenoPHP is designed to support both.

---

## Modular

RenoPHP is being designed as a collection of components.

For example:

```text
Core
Routing
HTTP
Database
ORM
Validation
Authentication
Authorization
Cache
Queue
Mail
Storage
CLI
```

Applications should not be forced to use every component when they only need a few of them.

---

## Requirements

The initial target environment is:

```text
PHP 8.x or higher
Composer
MySQL / MariaDB / SQLite / PostgreSQL
```

Supported operating systems:

```text
Windows
Linux
macOS
```

---

## Installation

RenoPHP is currently under development.

The installation process is planned to use Composer:

```bash
composer create-project renophp/reno my-project
```

Then:

```bash
cd my-project
```

Configure the environment:

```bash
cp .env.example .env
```

Start the development server:

```bash
php reno serve
```

> Installation commands may change while RenoPHP is still under development.

---

## Example Application

A simple RenoPHP application could look like this:

### Route

```php
Route::get('/students', StudentController::class, 'index');
```

### Controller

```php
class StudentController
{
    public function index()
    {
        $students = Student::all();

        return view('students.index', [
            'students' => $students
        ]);
    }
}
```

### View

```php
<h1>Students</h1>

<?php foreach ($students as $student): ?>

    <p>
        <?= $student->name ?>
    </p>

<?php endforeach; ?>
```

The goal is to keep the relationship between the route, controller, model, and view easy to understand.

```text
Route
  ↓
Controller
  ↓
Model
  ↓
Database
  ↓
View
  ↓
Browser
```

---

## Development Philosophy

RenoPHP is guided by a few principles:

### 1. Understandable code

Developers should be able to understand what their application is doing.

### 2. Beginner-friendly

A beginner should be able to start with basic PHP knowledge and gradually learn the framework.

### 3. Useful abstractions

Abstractions should solve real problems instead of existing only for the sake of abstraction.

### 4. Clear errors

When something fails, the framework should help explain why.

### 5. Security by default

Common security protections should be built into the framework.

### 6. Grow with the developer

The framework should be simple enough for learning but provide tools that can support larger applications.

---

## Roadmap

RenoPHP is currently in development.

### Core

* [ ] Application lifecycle
* [ ] Configuration
* [ ] Environment variables
* [ ] Service container
* [ ] Error handling
* [ ] Logging

### HTTP

* [ ] Request
* [ ] Response
* [ ] Router
* [ ] Controllers
* [ ] Middleware

### Database

* [ ] Database manager
* [ ] Query builder
* [ ] MySQL
* [ ] MariaDB
* [ ] SQLite
* [ ] PostgreSQL
* [ ] Migrations
* [ ] Seeders

### ORM

* [ ] Models
* [ ] Relationships
* [ ] Query scopes
* [ ] Pagination
* [ ] Eager loading
* [ ] Soft deletes

### Application

* [ ] Validation
* [ ] Sessions
* [ ] Authentication
* [ ] Authorization
* [ ] Views
* [ ] API tools

### Developer Tools

* [ ] CLI
* [ ] Code generators
* [ ] Reno Doctor
* [ ] Route inspector
* [ ] Debugging tools

### Infrastructure

* [ ] Cache
* [ ] Queue
* [ ] Events
* [ ] Mail
* [ ] Filesystem

### Quality

* [ ] Automated tests
* [ ] Security checks
* [ ] Performance testing
* [ ] Documentation
* [ ] Stable release

---

## Project Status

RenoPHP is currently in **early development**.

The framework architecture, APIs, and commands may change before the first stable release.

The current goal is to build the foundation first and add features gradually.

---

## Contributing

RenoPHP is currently a personal project, but contributions may be welcomed as the project develops.

If you want to contribute:

1. Fork the repository.
2. Create a branch.

```bash
git checkout -b feature/my-feature
```

3. Make your changes.
4. Add tests when necessary.
5. Make sure existing tests still pass.
6. Create a pull request.

Before adding a feature, consider whether it follows the main philosophy of RenoPHP:

> **Less Magic. More Understanding.**

---

## Author

**Moreno Jumyr**

RenoPHP was created as a project focused on making PHP web development easier to learn and understand.

---

## License

The license will be decided before the first stable release.

---

# RenoPHP

**Less Magic. More Understanding.**

A PHP framework for developers who want to learn, build, and grow.
