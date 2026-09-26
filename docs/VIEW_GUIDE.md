# Horizon Framework - View System Guide

**Philosophy: "Less Magic. More Understanding."**

The Horizon view system provides a powerful yet simple template engine with automatic XSS protection, elegant syntax, and excellent debugging capabilities.

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [Basic Views](#basic-views)
3. [Passing Data](#passing-data)
4. [Blade Syntax](#blade-syntax)
5. [Layouts & Sections](#layouts--sections)
6. [Components](#components)
7. [View Composers](#view-composers)
8. [Shared Data](#shared-data)
9. [Security](#security)
10. [Performance & Caching](#performance--caching)
11. [Best Practices](#best-practices)
12. [Troubleshooting](#troubleshooting)

---

## Quick Start

### Creating a Simple View

**resources/views/welcome.php:**
```php
<!DOCTYPE html>
<html>
<head>
    <title><?= e($title) ?></title>
</head>
<body>
    <h1><?= e($message) ?></h1>
</body>
</html>
```

**Controller:**
```php
return view('welcome', [
    'title' => 'Welcome',
    'message' => 'Hello, World!'
]);
```

---

## Basic Views

### Returning Views from Controllers

```php
// Simple view
return view('home');

// With data
return view('profile', ['user' => $user]);

// Render to string
$html = view('email', ['order' => $order])->render();
```

### View Locations

Views are stored in `resources/views/` by default:

```
resources/views/
├── home.php
├── profile.php
├── auth/
│   ├── login.php
│   └── register.php
└── admin/
    └── dashboard.php
```

Access nested views using dot notation:
```php
view('auth.login');
view('admin.dashboard');
```

### Checking if View Exists

```php
if (view()->exists('custom.template')) {
    return view('custom.template');
}

// Get first existing view
return view()->first(['custom.template', 'default.template'], $data);
```

---

## Passing Data

### Multiple Ways to Pass Data

```php
// Array syntax
return view('profile', ['user' => $user, 'posts' => $posts]);

// with() method
return view('profile')
    ->with('user', $user)
    ->with('posts', $posts);

// Magic methods
return view('profile')
    ->withUser($user)
    ->withPosts($posts);

// Array access
$view = view('profile');
$view['user'] = $user;
$view['posts'] = $posts;
return $view;
```

### Accessing Data in Views

```php
// In your view
<?= e($user->name) ?>
<?= e($posts->count()) ?>

// Check if variable exists
<?php if (isset($user)): ?>
    Welcome, <?= e($user->name) ?>
<?php endif; ?>
```

---

## Blade Syntax

Horizon supports Blade-like syntax for cleaner templates.

### Echoing Data

```blade
{{-- Escaped output (SAFE - use by default) --}}
{{ $name }}
{{ $user->email }}
{{ strtoupper($title) }}

{{-- Raw output (DANGEROUS - only for trusted content) --}}
{!! $htmlContent !!}

{{-- Comments (not in HTML output) --}}
{{-- This is a comment --}}
```

### Control Structures

```blade
{{-- If Statements --}}
@if ($user->isAdmin())
    <p>Admin Panel</p>
@elseif ($user->isModerator())
    <p>Moderator Panel</p>
@else
    <p>User Panel</p>
@endif

{{-- Unless --}}
@unless ($user->isSubscribed())
    <p>Please subscribe to access premium content.</p>
@endunless

{{-- Isset & Empty --}}
@isset($records)
    <p>Records found!</p>
@endisset

@empty($records)
    <p>No records found.</p>
@endempty
```

### Loops

```blade
{{-- Foreach --}}
@foreach ($users as $user)
    <p>{{ $user->name }}</p>
@endforeach

{{-- For --}}
@for ($i = 0; $i < 10; $i++)
    <p>{{ $i }}</p>
@endfor

{{-- While --}}
@while ($condition)
    <p>Looping...</p>
@endwhile

{{-- Break & Continue --}}
@foreach ($users as $user)
    @if ($user->type === 'admin')
        @continue
    @endif
    
    <p>{{ $user->name }}</p>
    
    @if ($loop->iteration === 10)
        @break
    @endif
@endforeach
```

### Including Sub-Views

```blade
{{-- Basic include --}}
@include('partials.header')

{{-- Include with data --}}
@include('partials.sidebar', ['menu' => $menuItems])

{{-- Conditional includes --}}
@includeIf('partials.banner')
@includeWhen($showBanner, 'partials.banner')
```

---

## Layouts & Sections

### Creating a Layout

**resources/views/layouts/app.php:**
```blade
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'Default Title')</title>
    @stack('styles')
</head>
<body>
    <header>
        <nav>@yield('navigation')</nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer>
        @yield('footer')
    </footer>

    @stack('scripts')
</body>
</html>
```

### Extending a Layout

**resources/views/pages/home.php:**
```blade
@extends('layouts.app')

@section('title')
    Home Page
@endsection

@section('content')
    <h1>Welcome to Horizon</h1>
    <p>This is the home page content.</p>
@endsection

@section('footer')
    @parent
    <p>Additional footer content</p>
@endsection

@push('styles')
    <link rel="stylesheet" href="/css/home.css">
@endpush

@push('scripts')
    <script src="/js/home.js"></script>
@endpush
```

### Section Directives

```blade
{{-- Define a section --}}
@section('content')
    Content here
@endsection

{{-- Define and immediately show --}}
@section('sidebar')
    Sidebar content
@show

{{-- Yield section with default --}}
@yield('content', 'Default content')

{{-- Include parent section content --}}
@section('content')
    @parent
    Additional content
@endsection

{{-- Check if section has content --}}
@if (has_section('optional'))
    <div>@yield('optional')</div>
@endif
```

### Stacks (Scripts & Styles)

```blade
{{-- In layout --}}
@stack('scripts')
@stack('styles')

{{-- In child view - push to end --}}
@push('scripts')
    <script src="/js/app.js"></script>
@endpush

{{-- In child view - prepend to beginning --}}
@prepend('scripts')
    <script src="/js/critical.js"></script>
@endprepend
```

---

## Components

Components allow you to create reusable UI elements.

### View-Based Components

**resources/views/components/alert.php:**
```blade
<div class="alert alert-{{ $type ?? 'info' }}">
    <strong>{{ $title }}</strong>
    <p>{{ $slot }}</p>
</div>
```

**Usage:**
```blade
@component('components.alert', ['type' => 'success', 'title' => 'Success!'])
    Your profile has been updated.
@endcomponent
```

### Class-Based Components

**src/View/Components/Alert.php:**
```php
<?php

namespace App\View\Components;

use Horizon\View\Component;

class Alert extends Component
{
    public string $type;
    public string $title;

    public function __construct(string $type = 'info', string $title = 'Notice')
    {
        $this->type = $type;
        $this->title = $title;
    }

    public function render(): string
    {
        return view('components.alert', [
            'type' => $this->type,
            'title' => $this->title,
            'slot' => $this->slot,
        ])->render();
    }
}
```

**Usage:**
```blade
@component(new \App\View\Components\Alert('success', 'Success!'))
    Your profile has been updated.
@endcomponent
```

### Component Slots

**Component with named slots:**
```blade
{{-- resources/views/components/card.php --}}
<div class="card">
    <div class="card-header">
        {{ $header }}
    </div>
    <div class="card-body">
        {{ $slot }}
    </div>
    <div class="card-footer">
        {{ $footer }}
    </div>
</div>
```

**Usage:**
```blade
@component('components.card')
    @slot('header')
        Card Title
    @endslot
    
    This is the card body (default slot)
    
    @slot('footer')
        Card Footer
    @endslot
@endcomponent
```

---

## View Composers

View composers bind data to views automatically before rendering.

### Registering Composers

**In a Service Provider or bootstrap file:**
```php
// Share data with specific view
view()->composer('profile', function ($view) {
    $view->with('posts', Post::recent()->get());
});

// Share data with multiple views
view()->composer(['profile', 'dashboard'], function ($view) {
    $view->with('user', auth()->user());
});

// Share data with wildcard pattern
view()->composer('admin.*', function ($view) {
    $view->with('adminMenu', [
        'dashboard' => '/admin',
        'users' => '/admin/users',
        'settings' => '/admin/settings',
    ]);
});
```

### View Creators

Creators run when view is instantiated (before rendering):

```php
view()->creator('auth.*', function (&$data) {
    $data['csrfToken'] = csrf_token();
    $data['returnUrl'] = request()->input('return', '/');
});
```

### Difference: Composer vs Creator

- **Creator**: Runs during view instantiation, receives data array
- **Composer**: Runs just before rendering, receives View instance

---

## Shared Data

Share data globally with all views:

```php
// Share single value
view()->share('appName', 'Horizon Framework');

// Share multiple values
view()->share([
    'appName' => 'Horizon Framework',
    'version' => '1.0.0',
    'year' => date('Y'),
]);
```

**Access in any view:**
```blade
<footer>
    &copy; {{ $year }} {{ $appName }} v{{ $version }}
</footer>
```

---

## Security

### Automatic XSS Protection

**All output is escaped by default:**

```php
// SAFE - HTML is escaped
<?= e($userInput) ?>
{{ $userInput }}

// SAFE - Shorthand
<?= escape($userInput) ?>
```

### Raw Output (Use Carefully!)

```php
// DANGEROUS - No escaping (only for trusted content)
<?= raw($trustedHtml) ?>
{!! $trustedHtml !!}
```

### Security Helpers

```blade
{{-- CSRF Token --}}
@csrf
<!-- Outputs: <input type="hidden" name="_token" value="..."> -->

{{-- HTTP Method Spoofing --}}
@method('PUT')
<!-- Outputs: <input type="hidden" name="_method" value="PUT"> -->

{{-- JSON encoding with XSS protection --}}
<script>
    var data = @json($data);
</script>
```

### Best Practices

1. **Always escape user input** - Use `{{ }}` or `e()` by default
2. **Only use raw output for trusted content** - Admin-generated HTML, sanitized content
3. **Never output passwords or secrets** - Even escaped
4. **Validate file uploads** - Before displaying file names or URLs
5. **Use CSRF protection** - On all forms that modify data

---

## Performance & Caching

### Template Compilation

Blade templates are compiled to PHP once and cached:

```php
// Automatically compiles and caches on first use
return view('dashboard');

// Compiled file stored in: storage/framework/views/
```

### Cache Management

```php
// Clear all compiled views
view_cache_clear();

// Get cache statistics
$stats = view_cache_stats();
// Returns: ['files' => 42, 'size' => 152034, 'size_human' => '148.47 KB', ...]

// Clear view location cache
view()->flushFinderCache();
```

### Production Optimization

**1. Pre-compile all views:**
```bash
php horizon view:cache
```

**2. Disable debug mode:**
```env
APP_DEBUG=false
APP_ENV=production
```

**3. Use view caching:**
```php
// Views are automatically cached after first compile
// No runtime compilation overhead
```

### View Location Caching

```php
// View paths are cached internally
// First lookup: filesystem search
// Subsequent lookups: cache hit

// Clear when adding new view directories
view()->flushFinderCache();
```

---

## Best Practices

### 1. Keep Logic Minimal

**❌ Bad - Complex logic in view:**
```blade
@foreach ($users as $user)
    @if ($user->status === 'active' && $user->hasPermission('view') && !$user->isSuspended())
        <p>{{ $user->name }}</p>
    @endif
@endforeach
```

**✅ Good - Logic in controller/model:**
```php
// Controller
$visibleUsers = $users->filter->isVisible();
return view('users', compact('visibleUsers'));
```

```blade
@foreach ($visibleUsers as $user)
    <p>{{ $user->name }}</p>
@endforeach
```

### 2. Use Layouts

**❌ Bad - Duplicate HTML:**
```blade
<!-- Every page repeats header, footer, etc -->
<!DOCTYPE html>
<html>
<head>...</head>
<body>
    <header>...</header>
    <!-- Page content -->
    <footer>...</footer>
</body>
</html>
```

**✅ Good - Extend layout:**
```blade
@extends('layouts.app')

@section('content')
    <!-- Only unique content -->
@endsection
```

### 3. Use Components for Reusable UI

**✅ Good:**
```blade
@component('components.card', ['title' => 'Recent Posts'])
    @foreach ($posts as $post)
        <p>{{ $post->title }}</p>
    @endforeach
@endcomponent
```

### 4. Organize Views Logically

```
resources/views/
├── layouts/
│   ├── app.php          # Main layout
│   ├── auth.php         # Auth pages layout
│   └── admin.php        # Admin layout
├── components/
│   ├── alert.php
│   ├── card.php
│   └── modal.php
├── partials/
│   ├── header.php
│   ├── footer.php
│   └── nav.php
├── auth/
│   ├── login.php
│   └── register.php
├── admin/
│   └── dashboard.php
└── pages/
    ├── home.php
    └── about.php
```

### 5. Use View Composers for Repeated Data

**❌ Bad - Repeat in every controller:**
```php
public function show()
{
    $menu = Menu::all();
    return view('page')->with('menu', $menu);
}
```

**✅ Good - Use composer:**
```php
// In service provider
view()->composer('*', function ($view) {
    $view->with('menu', Menu::all());
});
```

---

## Troubleshooting

### View Not Found

**Error:** `View [profile] not found`

**Solutions:**
1. Check view file exists: `resources/views/profile.php`
2. Check file extension: `.php` or `.blade.php`
3. Check nested paths: `resources/views/users/profile.php` → `view('users.profile')`
4. Clear view cache: `view()->flushFinderCache()`

### Undefined Variable

**Error:** `Undefined variable: user`

**Solutions:**
1. Pass variable from controller: `view('profile', ['user' => $user])`
2. Check variable name matches: `$user` not `$profile`
3. Use `isset()` check in view: `<?php if (isset($user)): ?>`
4. Check view composer: Verify composer is registered

### Syntax Error in Compiled View

**Error:** `syntax error, unexpected 'if'...`

**Solutions:**
1. Check Blade syntax: `@if` not `@if()`
2. Check closing tags: Every `@if` needs `@endif`
3. Clear compiled views: `view_cache_clear()`
4. Check for typos in directives

### Layout Not Applied

**Issue:** Layout sections not showing

**Solutions:**
1. Use `@extends()` first line of view
2. Check layout path: `@extends('layouts.app')`
3. Ensure sections match: `@section('content')` → `@yield('content')`
4. Check for multiple `@extends()` calls (only one allowed)

### XSS Not Prevented

**Issue:** HTML rendering unescaped

**Solutions:**
1. Use `{{ }}` not `{!! !!}`
2. Use `e()` not `echo` in PHP templates
3. Never use `raw()` with user input
4. Sanitize content before displaying

### Performance Issues

**Issue:** Slow view rendering

**Solutions:**
1. Clear old compiled views: `view_cache_clear()`
2. Reduce database queries in view composers
3. Use eager loading for relationships
4. Cache expensive computations
5. Profile with: `$stats = view_cache_stats()`

---

## Helper Functions Reference

### View Helpers

```php
view($view, $data)              // Create view instance
e($value)                       // Escape HTML
escape($value)                  // Alias for e()
raw($value)                     // Return unescaped value
```

### Layout Helpers

```php
extend($layout)                 // Extend parent layout
section($name)                  // Start section
endsection()                    // End section
show()                          // End and show section
yield_content($section, $default) // Output section content
has_section($name)              // Check if section exists
```

### Stack Helpers

```php
push($name)                     // Start push to stack
endpush()                       // End push
prepend($name)                  // Start prepend to stack
endprepend()                    // End prepend
stack($name)                    // Output stack content
```

### Component Helpers

```php
component($component, $data)    // Start component
endcomponent()                  // End and render component
slot($name)                     // Start named slot
endslot()                       // End slot
render_component($component, $data) // Render inline
```

### Security Helpers

```php
csrf_field()                    // CSRF token input
csrf_token()                    // Get CSRF token
method_field($method)           // HTTP method input
old($key, $default)             // Old input value
json_encode_safe($value)        // JSON with XSS protection
```

### Cache Helpers

```php
view_cache_clear()              // Clear compiled views
view_cache_stats()              // Get cache statistics
```

---

## Examples

### Example 1: Simple Blog Post

**Controller:**
```php
public function show($id)
{
    $post = Post::with('author', 'comments')->findOrFail($id);
    
    return view('blog.post', compact('post'));
}
```

**View (resources/views/blog/post.php):**
```blade
@extends('layouts.app')

@section('title', $post->title)

@section('content')
    <article>
        <h1>{{ $post->title }}</h1>
        <p class="meta">
            By {{ $post->author->name }} on {{ $post->created_at->format('M d, Y') }}
        </p>
        
        <div class="content">
            {!! $post->content !!}
        </div>
        
        <section class="comments">
            <h2>Comments ({{ $post->comments->count() }})</h2>
            
            @foreach ($post->comments as $comment)
                @include('blog.comment', ['comment' => $comment])
            @endforeach
        </section>
    </article>
@endsection

@push('scripts')
    <script src="/js/comments.js"></script>
@endpush
```

### Example 2: Dashboard with Composer

**Service Provider:**
```php
view()->composer('admin.*', function ($view) {
    $view->with([
        'user' => auth()->user(),
        'notifications' => auth()->user()->unreadNotifications()->get(),
        'stats' => [
            'users' => User::count(),
            'posts' => Post::count(),
            'comments' => Comment::count(),
        ],
    ]);
});
```

**View:**
```blade
@extends('layouts.admin')

@section('content')
    <h1>Welcome, {{ $user->name }}</h1>
    
    @if ($notifications->count() > 0)
        <div class="alerts">
            You have {{ $notifications->count() }} notifications
        </div>
    @endif
    
    <div class="stats">
        <div>Users: {{ $stats['users'] }}</div>
        <div>Posts: {{ $stats['posts'] }}</div>
        <div>Comments: {{ $stats['comments'] }}</div>
    </div>
@endsection
```

### Example 3: Reusable Alert Component

**Component Class:**
```php
<?php

namespace App\View\Components;

use Horizon\View\Component;

class Alert extends Component
{
    public string $type;
    public string $title;
    public bool $dismissible;

    public function __construct(
        string $type = 'info',
        string $title = '',
        bool $dismissible = true
    ) {
        $this->type = $type;
        $this->title = $title;
        $this->dismissible = $dismissible;
    }

    public function render(): string
    {
        return view('components.alert', [
            'type' => $this->type,
            'title' => $this->title,
            'dismissible' => $this->dismissible,
            'slot' => $this->slot,
        ])->render();
    }
}
```

**Component View:**
```blade
<div class="alert alert-{{ $type }} {{ $dismissible ? 'alert-dismissible' : '' }}">
    @if ($dismissible)
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    @endif
    
    @if ($title)
        <strong>{{ $title }}</strong>
    @endif
    
    <div>{{ $slot }}</div>
</div>
```

**Usage:**
```blade
@component(new \App\View\Components\Alert('success', 'Success!', true))
    Your changes have been saved successfully.
@endcomponent
```

---

## Next Steps

- Learn about [HTTP Layer](HTTP_LAYER.md)
- Explore [Routing Guide](ROUTING_GUIDE.md)
- Read about [Security Best Practices](SECURITY_GUIDE.md)
- Check out [Performance Optimization](PERFORMANCE_GUIDE.md)

---

**Remember: "Less Magic. More Understanding."**

The Horizon view system is designed to be simple, secure, and fast. If something seems unclear, check the compiled views in `storage/framework/views/` to see exactly what PHP code is generated.
