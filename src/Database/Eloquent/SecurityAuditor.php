<?php

declare(strict_types=1);

namespace Reno\Database\Eloquent;

/**
 * Security Auditor
 * 
 * Handles logging and monitoring of security-related events
 * in the ORM system for compliance and threat detection.
 */
class SecurityAuditor
{
    /**
     * Audit log entries.
     */
    protected static array $auditLog = [];

    /**
     * Security violation counts.
     */
    protected static array $violationCounts = [];

    /**
     * Suspicious activity threshold.
     */
    protected static int $suspiciousThreshold = 5;

    /**
     * Log a security event.
     */
    public static function logEvent(
        string $event,
        string $level = 'info',
        array $context = []
    ): void {
        $entry = [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'event' => $event,
            'level' => $level,
            'context' => $context,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'session_id' => session_id() ?: 'none',
        ];

        static::$auditLog[] = $entry;

        // Track violations for pattern detection
        if ($level === 'warning' || $level === 'error') {
            static::trackViolation($context['violation_type'] ?? $event);
        }

        // Write to file in production environment
        static::writeToFile($entry);
    }

    /**
     * Log a mass assignment attempt.
     */
    public static function logMassAssignment(
        string $model,
        string $attribute,
        bool $allowed,
        array $context = []
    ): void {
        static::logEvent(
            'mass_assignment_attempt',
            $allowed ? 'info' : 'warning',
            array_merge($context, [
                'model' => $model,
                'attribute' => $attribute,
                'allowed' => $allowed,
                'violation_type' => 'mass_assignment',
            ])
        );
    }

    /**
     * Log a security violation.
     */
    public static function logViolation(
        string $type,
        string $attribute,
        mixed $value,
        array $context = []
    ): void {
        static::logEvent(
            'security_violation',
            'error',
            array_merge($context, [
                'violation_type' => $type,
                'attribute' => $attribute,
                'value_preview' => static::previewValue($value),
                'value_type' => gettype($value),
            ])
        );
    }

    /**
     * Log a SQL injection attempt.
     */
    public static function logSqlInjection(
        string $attribute,
        string $value,
        array $context = []
    ): void {
        static::logViolation(
            'sql_injection',
            $attribute,
            $value,
            array_merge($context, [
                'detected_patterns' => static::detectInjectionPatterns($value),
            ])
        );
    }

    /**
     * Log an XSS attempt.
     */
    public static function logXssAttempt(
        string $attribute,
        string $value,
        array $context = []
    ): void {
        static::logViolation(
            'xss_attempt',
            $attribute,
            $value,
            array_merge($context, [
                'detected_patterns' => static::detectXssPatterns($value),
            ])
        );
    }

    /**
     * Log authentication-related security events.
     */
    public static function logAuthEvent(
        string $event,
        ?string $userId = null,
        array $context = []
    ): void {
        static::logEvent(
            "auth_{$event}",
            in_array($event, ['failed_login', 'locked_account']) ? 'warning' : 'info',
            array_merge($context, [
                'user_id' => $userId,
                'auth_event' => $event,
            ])
        );
    }

    /**
     * Get the current audit log.
     */
    public static function getAuditLog(): array
    {
        return static::$auditLog;
    }

    /**
     * Get security violations summary.
     */
    public static function getViolationsSummary(): array
    {
        return static::$violationCounts;
    }

    /**
     * Check if current session shows suspicious activity.
     */
    public static function isSuspiciousActivity(?string $sessionId = null): bool
    {
        $sessionId = $sessionId ?: session_id();
        
        if (!$sessionId) {
            return false;
        }

        $violations = array_filter(static::$auditLog, function ($entry) use ($sessionId) {
            return $entry['session_id'] === $sessionId && 
                   in_array($entry['level'], ['warning', 'error']);
        });

        return count($violations) >= static::$suspiciousThreshold;
    }

    /**
     * Get recent security events.
     */
    public static function getRecentEvents(int $minutes = 60): array
    {
        $cutoff = time() - ($minutes * 60);

        return array_filter(static::$auditLog, function ($entry) use ($cutoff) {
            return $entry['timestamp'] >= $cutoff;
        });
    }

    /**
     * Get events by type.
     */
    public static function getEventsByType(string $type): array
    {
        return array_filter(static::$auditLog, function ($entry) use ($type) {
            return $entry['event'] === $type || 
                   ($entry['context']['violation_type'] ?? null) === $type;
        });
    }

    /**
     * Clear the audit log.
     */
    public static function clearLog(): void
    {
        static::$auditLog = [];
        static::$violationCounts = [];
    }

    /**
     * Export audit log to array.
     */
    public static function export(): array
    {
        return [
            'audit_log' => static::$auditLog,
            'violation_counts' => static::$violationCounts,
            'export_time' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Track a security violation for pattern detection.
     */
    protected static function trackViolation(string $type): void
    {
        if (!isset(static::$violationCounts[$type])) {
            static::$violationCounts[$type] = 0;
        }

        static::$violationCounts[$type]++;
    }

    /**
     * Create a safe preview of a value for logging.
     */
    protected static function previewValue(mixed $value): string
    {
        if (!is_string($value)) {
            return gettype($value);
        }

        $preview = mb_substr($value, 0, 100);
        
        if (mb_strlen($value) > 100) {
            $preview .= '... [truncated]';
        }

        // Remove potential secrets from preview
        $preview = preg_replace('/password|secret|token|key/i', '[REDACTED]', $preview);

        return $preview;
    }

    /**
     * Detect SQL injection patterns in a value.
     */
    protected static function detectInjectionPatterns(string $value): array
    {
        $patterns = [
            'union_select' => '/UNION\s+SELECT/i',
            'or_injection' => '/\'\s*OR\s+\'/i',
            'comment_injection' => '/\/\*.*\*\/|--|\#/i',
            'stacked_query' => '/;\s*(SELECT|INSERT|UPDATE|DELETE)/i',
        ];

        $detected = [];

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $value)) {
                $detected[] = $name;
            }
        }

        return $detected;
    }

    /**
     * Detect XSS patterns in a value.
     */
    protected static function detectXssPatterns(string $value): array
    {
        $patterns = [
            'script_tag' => '/<script/i',
            'javascript_protocol' => '/javascript:/i',
            'event_handler' => '/on\w+\s*=/i',
            'iframe_tag' => '/<iframe/i',
        ];

        $detected = [];

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $value)) {
                $detected[] = $name;
            }
        }

        return $detected;
    }

    /**
     * Write audit entry to file.
     */
    protected static function writeToFile(array $entry): void
    {
        // In a real implementation, this would write to a secure log file
        // For now, we'll just simulate the logging
        if (defined('SECURITY_LOG_FILE')) {
            $logLine = json_encode($entry) . "\n";
            file_put_contents(SECURITY_LOG_FILE, $logLine, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Set the suspicious activity threshold.
     */
    public static function setSuspiciousThreshold(int $threshold): void
    {
        static::$suspiciousThreshold = max(1, $threshold);
    }

    /**
     * Get security statistics.
     */
    public static function getSecurityStats(): array
    {
        $totalEvents = count(static::$auditLog);
        $violations = array_filter(static::$auditLog, function ($entry) {
            return in_array($entry['level'], ['warning', 'error']);
        });

        $recentViolations = array_filter($violations, function ($entry) {
            return $entry['timestamp'] >= (time() - 3600); // Last hour
        });

        return [
            'total_events' => $totalEvents,
            'total_violations' => count($violations),
            'recent_violations' => count($recentViolations),
            'violation_types' => static::$violationCounts,
            'suspicious_sessions' => static::getSuspiciousSessions(),
        ];
    }

    /**
     * Get sessions with suspicious activity.
     */
    protected static function getSuspiciousSessions(): array
    {
        $sessions = [];

        foreach (static::$auditLog as $entry) {
            $sessionId = $entry['session_id'];
            
            if ($sessionId && in_array($entry['level'], ['warning', 'error'])) {
                if (!isset($sessions[$sessionId])) {
                    $sessions[$sessionId] = 0;
                }
                $sessions[$sessionId]++;
            }
        }

        return array_filter($sessions, function ($count) {
            return $count >= static::$suspiciousThreshold;
        });
    }
}