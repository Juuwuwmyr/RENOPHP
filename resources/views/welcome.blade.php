<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'RENOPHP' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        
        .logo {
            font-size: 48px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        .tagline {
            color: #666;
            font-size: 16px;
            margin-bottom: 40px;
            font-style: italic;
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 32px;
        }
        
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
            font-size: 18px;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }
        
        .feature {
            background: #f7f7f7;
            padding: 20px;
            border-radius: 10px;
            transition: transform 0.2s;
        }
        
        .feature:hover {
            transform: translateY(-5px);
            background: #667eea;
            color: white;
        }
        
        .feature-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .feature-name {
            font-weight: 600;
            font-size: 14px;
        }
        
        .links {
            margin-top: 40px;
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            padding: 12px 24px;
            border: 2px solid #667eea;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .links a:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .version {
            margin-top: 30px;
            color: #999;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">RENOPHP</div>
        <div class="tagline">Less Magic. More Understanding.</div>
        
        <h1>{{ $title ?? 'Welcome to RENOPHP' }}</h1>
        <p>{{ $message ?? 'Your modern PHP framework is ready to use!' }}</p>
        
        <div class="features">
            <div class="feature">
                <div class="feature-icon">🚀</div>
                <div class="feature-name">Fast</div>
            </div>
            <div class="feature">
                <div class="feature-icon">🔒</div>
                <div class="feature-name">Secure</div>
            </div>
            <div class="feature">
                <div class="feature-icon">📦</div>
                <div class="feature-name">Modular</div>
            </div>
            <div class="feature">
                <div class="feature-icon">💡</div>
                <div class="feature-name">Simple</div>
            </div>
        </div>
        
        <div class="links">
            <a href="/docs">Documentation</a>
            <a href="/api/hello">API Test</a>
            <a href="https://github.com/Juuwuwmyr/renophp" target="_blank">GitHub</a>
        </div>
        
        <div class="version">
            RENOPHP v1.0.0 | PHP {{ PHP_VERSION }}
        </div>
    </div>
</body>
</html>
