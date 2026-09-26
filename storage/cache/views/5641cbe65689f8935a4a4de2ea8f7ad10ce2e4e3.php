<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title ?? 'Documentation'); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f7fafc;
            color: #2d3748;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            font-weight: 800;
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        
        .card-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .card h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #667eea;
        }
        
        .card p {
            color: #4a5568;
            margin-bottom: 1.5rem;
        }
        
        .card ul {
            list-style: none;
            padding: 0;
        }
        
        .card li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .card li:last-child {
            border-bottom: none;
        }
        
        .card a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .card a:hover {
            text-decoration: underline;
        }
        
        .quick-links {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 2rem;
        }
        
        .quick-links h2 {
            color: #667eea;
            margin-bottom: 1.5rem;
        }
        
        .link-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .link-button {
            display: block;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .link-button:hover {
            transform: translateY(-2px);
        }
        
        .code-block {
            background: #2d3748;
            color: #e2e8f0;
            padding: 1.5rem;
            border-radius: 8px;
            overflow-x: auto;
            margin: 1rem 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        
        .footer {
            text-align: center;
            padding: 3rem 2rem;
            background: #2d3748;
            color: white;
            margin-top: 4rem;
        }
        
        .footer p {
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📚 <?php echo e($framework); ?> Documentation</h1>
        <p>Complete guide to building amazing applications</p>
        <div class="badge">Version <?php echo e($version); ?></div>
    </div>
    
    <div class="container">
        <div class="grid">
            <div class="card">
                <div class="card-icon">🚀</div>
                <h2>Getting Started</h2>
                <p>Quick start guides and installation instructions</p>
                <ul>
                    <li><a href="/docs/installation">Installation Guide</a></li>
                    <li><a href="/docs/quickstart">Quick Start Tutorial</a></li>
                    <li><a href="/docs/configuration">Configuration</a></li>
                    <li><a href="/docs/structure">Directory Structure</a></li>
                </ul>
            </div>
            
            <div class="card">
                <div class="card-icon">🔥</div>
                <h2>Core Concepts</h2>
                <p>Learn the fundamentals of RENOPHP</p>
                <ul>
                    <li><a href="/docs/routing">Routing</a></li>
                    <li><a href="/docs/controllers">Controllers</a></li>
                    <li><a href="/docs/middleware">Middleware</a></li>
                    <li><a href="/docs/views">Views & Templates</a></li>
                </ul>
            </div>
            
            <div class="card">
                <div class="card-icon">🗄️</div>
                <h2>Database</h2>
                <p>Work with databases efficiently</p>
                <ul>
                    <li><a href="/docs/database">Query Builder</a></li>
                    <li><a href="/docs/orm">ORM (Eloquent)</a></li>
                    <li><a href="/docs/migrations">Migrations</a></li>
                    <li><a href="/docs/relationships">Relationships</a></li>
                </ul>
            </div>
            
            <div class="card">
                <div class="card-icon">✅</div>
                <h2>Validation</h2>
                <p>Validate user input securely</p>
                <ul>
                    <li><a href="/docs/validation">Validation Rules</a></li>
                    <li><a href="/docs/custom-rules">Custom Rules</a></li>
                    <li><a href="/docs/form-requests">Form Requests</a></li>
                    <li><a href="/docs/error-messages">Error Messages</a></li>
                </ul>
            </div>
            
            <div class="card">
                <div class="card-icon">🔒</div>
                <h2>Security</h2>
                <p>Keep your application secure</p>
                <ul>
                    <li><a href="/docs/authentication">Authentication</a></li>
                    <li><a href="/docs/authorization">Authorization</a></li>
                    <li><a href="/docs/csrf">CSRF Protection</a></li>
                    <li><a href="/docs/encryption">Encryption</a></li>
                </ul>
            </div>
            
            <div class="card">
                <div class="card-icon">🎨</div>
                <h2>Frontend</h2>
                <p>Build beautiful user interfaces</p>
                <ul>
                    <li><a href="/docs/blade">Blade Templates</a></li>
                    <li><a href="/docs/assets">Assets Management</a></li>
                    <li><a href="/docs/responses">HTTP Responses</a></li>
                    <li><a href="/docs/json">JSON APIs</a></li>
                </ul>
            </div>
        </div>
        
        <div class="quick-links">
            <h2>📖 Quick Reference</h2>
            <div class="link-grid">
                <a href="/" class="link-button">← Home</a>
                <a href="/api/hello" class="link-button">API Test</a>
                <a href="https://github.com/yourusername/renophp" class="link-button">GitHub →</a>
            </div>
        </div>
        
        <div class="quick-links">
            <h2>💻 Example Code</h2>
            <p><strong>Create a simple route:</strong></p>
            <div class="code-block">$router->get('hello', function() {
    return 'Hello, World!';
});</div>
            
            <p><strong>Query the database:</strong></p>
            <div class="code-block">$users = User::where('active', true)
    ->orderBy('created_at', 'desc')
    ->get();</div>
            
            <p><strong>Validate input:</strong></p>
            <div class="code-block">$validated = $request->validate([
    'email' => 'required|email',
    'password' => 'required|min:8'
]);</div>
        </div>
        
        <div class="quick-links">
            <h2>🎯 Available Documentation Files</h2>
            <ul>
                <li><a href="../QUICK_START.md" target="_blank">Quick Start Guide</a></li>
                <li><a href="../SETUP_GUIDE.md" target="_blank">Setup Guide (Tagalog)</a></li>
                <li><a href="../START_HERE.md" target="_blank">Start Here</a></li>
                <li><a href="../README.md" target="_blank">Complete README</a></li>
                <li><a href="../docs/HTTP_LAYER.md" target="_blank">HTTP Layer Documentation</a></li>
                <li><a href="../docs/ORM_API_REFERENCE.md" target="_blank">ORM API Reference</a></li>
                <li><a href="../docs/VALIDATION_GUIDE.md" target="_blank">Validation Guide</a></li>
                <li><a href="../docs/VIEW_GUIDE.md" target="_blank">View & Template Guide</a></li>
            </ul>
        </div>
    </div>
    
    <div class="footer">
        <p><strong>RENOPHP Framework v<?php echo e($version); ?></strong></p>
        <p>"Less Magic. More Understanding."</p>
        <p style="margin-top: 1rem; opacity: 0.7;">Built with ❤️ by Moreno Jumyr</p>
    </div>
</body>
</html>
