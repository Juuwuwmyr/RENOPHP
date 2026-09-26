<?php

declare(strict_types=1);

/**
 * ORM Security Features Example
 * 
 * Demonstrates the Horizon Framework's comprehensive ORM security features
 * including mass assignment protection, SQL injection prevention, XSS protection,
 * input sanitization, and security auditing.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - ORM Security Features Example\n";
echo "=================================================\n\n";

use Horizon\Database\Eloquent\Model;
use Horizon\Database\Eloquent\MassAssignmentException;
use Horizon\Database\Eloquent\SecurityException;
use Horizon\Database\Eloquent\SecurityAuditor;

// Mock secure User model for demonstration
class SecureUser extends Model
{
    protected ?string $table = 'users';
    
    // Mass assignment protection
    protected array $fillable = [
        'name',
        'email',
        'bio',
        'website'
    ];
    
    protected array $guarded = [
        'id',
        'password',
        'is_admin',
        'balance',
        'api_token'
    ];
    
    // Attributes that should be sanitized
    protected array $sanitized = [
        'name',
        'email',
        'bio',
        'website'
    ];
    
    // Attributes that should be validated
    protected array $validated = [
        'email',
        'website',
        'bio'
    ];
    
    protected array $casts = [
        'is_admin' => 'boolean',
        'balance' => 'decimal:2',
        'created_at' => 'datetime',
    ];
}

// Mock vulnerable Post model for comparison
class VulnerablePost extends Model
{
    protected ?string $table = 'posts';
    protected array $guarded = []; // No protection!
}

try {
    echo "1. MASS ASSIGNMENT PROTECTION\n";
    echo "-----------------------------\n";

    echo "✓ SECURE MODEL WITH FILLABLE ATTRIBUTES\n\n";

    $user = new SecureUser();
    
    // Safe mass assignment - only fillable attributes
    $safeData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'bio' => 'Software developer',
        'website' => 'https://johndoe.com'
    ];
    
    echo "Attempting to fill secure model with safe data:\n";
    foreach ($safeData as $key => $value) {
        echo "  {$key}: {$value}\n";
    }
    
    $user->fill($safeData);
    echo "✅ Success! All attributes are fillable.\n\n";

    echo "❌ ATTEMPTING MASS ASSIGNMENT ATTACK\n\n";

    $maliciousData = [
        'name' => 'Attacker',
        'email' => 'attacker@evil.com',
        'is_admin' => true,  // Guarded attribute
        'balance' => 1000000, // Guarded attribute
        'api_token' => 'stolen_token' // Guarded attribute
    ];
    
    echo "Attempting to fill with malicious data:\n";
    foreach ($maliciousData as $key => $value) {
        $status = in_array($key, $user->getFillable()) ? '✓ fillable' : '❌ guarded';
        echo "  {$key}: " . json_encode($value) . " ({$status})\n";
    }
    
    try {
        $user->fill($maliciousData);
        echo "⚠️  Only fillable attributes were set (guarded attributes ignored)\n\n";
    } catch (MassAssignmentException $e) {
        echo "🛡️  Mass assignment blocked: " . $e->getMessage() . "\n\n";
    }

    echo "2. INPUT SANITIZATION\n";
    echo "---------------------\n";

    echo "Testing automatic input sanitization:\n\n";

    $dirtyData = [
        'name' => '  John <script>alert("xss")</script> Doe  ',
        'email' => 'JOHN@EXAMPLE.COM',
        'bio' => 'Developer with <iframe src="evil.com"></iframe> experience',
        'website' => 'javascript:alert("xss")'
    ];

    echo "Original data:\n";
    foreach ($dirtyData as $key => $value) {
        echo "  {$key}: {$value}\n";
    }
    echo "\n";

    $cleanUser = new SecureUser();
    $cleanUser->fill($dirtyData);
    
    echo "After sanitization:\n";
    foreach ($dirtyData as $key => $value) {
        if (in_array($key, $cleanUser->getFillable())) {
            $cleanValue = $cleanUser->getAttribute($key);
            echo "  {$key}: {$cleanValue}\n";
        }
    }
    echo "\n";

    echo "3. SECURITY VALIDATION\n";
    echo "----------------------\n";

    echo "Testing security validation:\n\n";

    $invalidData = [
        'email' => 'not-an-email',
        'website' => 'not-a-url',
        'bio' => 'Bio with potential SQL injection: SELECT * FROM users WHERE 1=1--'
    ];

    foreach ($invalidData as $key => $value) {
        echo "Testing {$key}: {$value}\n";
        
        try {
            $testUser = new SecureUser();
            $testUser->fill([$key => $value]);
            echo "  ✅ Passed validation\n";
        } catch (SecurityException $e) {
            echo "  🛡️  Blocked: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }

    echo "4. SQL INJECTION PREVENTION\n";
    echo "---------------------------\n";

    echo "Testing SQL injection detection:\n\n";

    $sqlInjectionAttempts = [
        'simple_injection' => "'; DROP TABLE users; --",
        'union_attack' => "1' UNION SELECT password FROM admin_users --",
        'boolean_injection' => "admin' OR '1'='1",
        'time_based' => "1'; WAITFOR DELAY '00:00:05' --",
        'stacked_query' => "1; INSERT INTO logs VALUES('hacked')",
    ];

    foreach ($sqlInjectionAttempts as $type => $payload) {
        echo "Testing {$type}:\n";
        echo "  Payload: {$payload}\n";
        
        try {
            $testUser = new SecureUser();
            $testUser->fill(['bio' => $payload]);
            echo "  ⚠️  Payload not detected (may need stronger validation)\n";
        } catch (SecurityException $e) {
            echo "  🛡️  Blocked: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }

    echo "5. XSS PROTECTION\n";
    echo "-----------------\n";

    echo "Testing XSS attack detection:\n\n";

    $xssAttempts = [
        'script_tag' => '<script>alert("XSS")</script>',
        'img_onerror' => '<img src="x" onerror="alert(\'XSS\')">',
        'javascript_url' => 'javascript:alert("XSS")',
        'event_handler' => '<div onclick="alert(\'XSS\')">Click me</div>',
        'iframe_embed' => '<iframe src="javascript:alert(\'XSS\')"></iframe>',
    ];

    foreach ($xssAttempts as $type => $payload) {
        echo "Testing {$type}:\n";
        echo "  Payload: {$payload}\n";
        
        try {
            $testUser = new SecureUser();
            $testUser->fill(['bio' => $payload]);
            $sanitized = $testUser->getAttribute('bio');
            echo "  🧹 Sanitized to: {$sanitized}\n";
        } catch (SecurityException $e) {
            echo "  🛡️  Blocked: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }

    echo "6. SECURITY AUDITING\n";
    echo "--------------------\n";

    echo "Security audit log:\n\n";

    // Clear previous logs for clean demonstration
    SecurityAuditor::clearLog();

    // Simulate various security events
    SecurityAuditor::logMassAssignment('User', 'is_admin', false, ['attempted_value' => true]);
    SecurityAuditor::logSqlInjection('bio', "'; DROP TABLE users; --", ['model' => 'User']);
    SecurityAuditor::logXssAttempt('bio', '<script>alert("xss")</script>', ['model' => 'User']);
    SecurityAuditor::logAuthEvent('failed_login', 'user_123', ['attempts' => 3]);
    SecurityAuditor::logViolation('oversized_data', 'bio', str_repeat('A', 70000), ['size' => 70000]);

    $auditLog = SecurityAuditor::getAuditLog();
    
    foreach ($auditLog as $entry) {
        echo "📋 [{$entry['datetime']}] {$entry['event']} ({$entry['level']})\n";
        if (!empty($entry['context'])) {
            foreach ($entry['context'] as $key => $value) {
                if (is_string($value) && strlen($value) > 50) {
                    $value = substr($value, 0, 50) . '...';
                }
                echo "    {$key}: " . json_encode($value) . "\n";
            }
        }
        echo "\n";
    }

    echo "7. SECURITY STATISTICS\n";
    echo "----------------------\n";

    $stats = SecurityAuditor::getSecurityStats();
    
    echo "Security Overview:\n";
    echo "  Total Events: {$stats['total_events']}\n";
    echo "  Total Violations: {$stats['total_violations']}\n";
    echo "  Recent Violations: {$stats['recent_violations']}\n\n";

    echo "Violation Types:\n";
    foreach ($stats['violation_types'] as $type => $count) {
        echo "  {$type}: {$count}\n";
    }
    echo "\n";

    if (!empty($stats['suspicious_sessions'])) {
        echo "Suspicious Sessions:\n";
        foreach ($stats['suspicious_sessions'] as $session => $violations) {
            echo "  {$session}: {$violations} violations\n";
        }
    } else {
        echo "No suspicious sessions detected.\n";
    }
    echo "\n";

    echo "8. SECURE MODEL METHODS\n";
    echo "-----------------------\n";

    echo "Using secure create/update methods:\n\n";

    try {
        // Demonstrate createSecurely method
        echo "Creating user securely:\n";
        $secureData = [
            'name' => 'Secure User',
            'email' => 'secure@example.com',
            'bio' => 'Clean bio content',
        ];
        
        // In real implementation, this would create the record
        echo "✅ SecureUser::createSecurely() would validate and create safely\n\n";
        
    } catch (SecurityException $e) {
        echo "🛡️  Security check failed: " . $e->getMessage() . "\n\n";
    }

    echo "9. COMPARISON: VULNERABLE VS SECURE\n";
    echo "-----------------------------------\n";

    echo "❌ VULNERABLE MODEL (No Protection):\n";
    $vulnerable = new VulnerablePost();
    $maliciousPayload = [
        'title' => 'Innocent Title',
        'content' => '<script>steal_cookies()</script>',
        'user_id' => 999999, // Attempt to impersonate
        'is_published' => true,
        'admin_only' => true, // Shouldn't be settable
    ];
    
    $vulnerable->fill($maliciousPayload);
    echo "  All attributes accepted without validation!\n\n";

    echo "✅ SECURE MODEL (Full Protection):\n";
    $secure = new SecureUser();
    try {
        $secure->fill($maliciousPayload);
        echo "  Only fillable attributes processed, with sanitization and validation\n\n";
    } catch (Exception $e) {
        echo "  🛡️  " . $e->getMessage() . "\n\n";
    }

    echo "10. SECURITY BEST PRACTICES\n";
    echo "---------------------------\n";

    echo "🔹 MASS ASSIGNMENT PROTECTION\n";
    echo "   • Always define \$fillable or \$guarded arrays\n";
    echo "   • Use \$guarded = ['*'] for maximum protection\n";
    echo "   • Never use \$guarded = [] in production\n\n";

    echo "🔹 INPUT VALIDATION\n";
    echo "   • Enable sanitization for user-facing attributes\n";
    echo "   • Validate email, URL, and other formatted fields\n";
    echo "   • Check for suspicious patterns in all inputs\n\n";

    echo "🔹 SQL INJECTION PREVENTION\n";
    echo "   • Always use parameterized queries (built-in)\n";
    echo "   • Validate raw SQL inputs if absolutely necessary\n";
    echo "   • Sanitize ORDER BY and GROUP BY clauses\n\n";

    echo "🔹 XSS PROTECTION\n";
    echo "   • Sanitize HTML content automatically\n";
    echo "   • Remove dangerous tags and attributes\n";
    echo "   • Escape output in views (framework responsibility)\n\n";

    echo "🔹 SECURITY MONITORING\n";
    echo "   • Enable security auditing in production\n";
    echo "   • Monitor for suspicious patterns\n";
    echo "   • Set up alerts for multiple violations\n\n";

    echo "🔹 SECURE CODING PRACTICES\n";
    echo "   • Use createSecurely() and updateSecurely() methods\n";
    echo "   • Regular security audits of model configurations\n";
    echo "   • Keep security features updated\n\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "====================================================\n";
echo "✅ ORM SECURITY FEATURES EXAMPLE COMPLETE!\n";
echo "====================================================\n\n";

echo "The Security system provides:\n\n";

echo "🔹 COMPREHENSIVE PROTECTION\n";
echo "   Complete defense against common web vulnerabilities\n\n";

echo "🔹 AUTOMATIC SANITIZATION\n";
echo "   Smart input cleaning based on attribute types\n\n";

echo "🔹 THREAT DETECTION\n";
echo "   Real-time detection of malicious patterns\n\n";

echo "🔹 AUDIT TRAIL\n";
echo "   Detailed logging for compliance and forensics\n\n";

echo "🔹 DEVELOPER FRIENDLY\n";
echo "   Easy to configure with sensible defaults\n\n";

echo "Security is built into every aspect of the ORM! 🛡️\n";