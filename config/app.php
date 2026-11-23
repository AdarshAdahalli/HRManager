<?php
/**
 * Application Configuration & Global Constants
 */

// Ensure session directory is writable
$sessionDir = dirname(__DIR__) . '/data/sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0777, true);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    @ini_set('session.save_path', $sessionDir);
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// App Details
define('APP_NAME', 'InternHub Global');
define('APP_VERSION', '1.0.0');

// Physical HR Timezone (Default: Asia/Kolkata or dynamically read from HR user profile)
define('DEFAULT_HR_TIMEZONE', 'Asia/Kolkata');

// Auto-detect Base URL
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$protocol = $isHttps ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
$scriptDir = str_replace('\\', '/', dirname($scriptName));
$basePath = preg_replace('#/(hr|intern|api|config|includes|assets.*)$#', '', $scriptDir);
$basePath = ($basePath === '/' || $basePath === '.') ? '' : rtrim($basePath, '/');
define('BASE_URL', $protocol . $host . $basePath);

// Set PHP Default Timezone for system logging
date_default_timezone_set(DEFAULT_HR_TIMEZONE);

/**
 * Get current logged in user's active timezone
 */
function getUserTimezone(): string {
    if (isset($_SESSION['user']['timezone']) && !empty($_SESSION['user']['timezone'])) {
        return $_SESSION['user']['timezone'];
    }
    if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'hr') {
        return DEFAULT_HR_TIMEZONE;
    }
    return 'UTC';
}

/**
 * Format datetime for display in requested timezone
 */
function formatDatetime(?string $datetimeStr, string $targetTimezone = 'UTC', string $format = 'M d, Y h:i A T'): string {
    if (empty($datetimeStr)) {
        return '—';
    }
    try {
        $dt = new DateTime($datetimeStr, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone($targetTimezone));
        return $dt->format($format);
    } catch (Exception $e) {
        return $datetimeStr;
    }
}
