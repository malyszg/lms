<?php

// PHPUnit Bootstrap - Force test database usage
// This ensures tests ALWAYS use the test database, never production

// Determine the correct database host based on environment
$dbHost = 'mysql'; // Default for Docker
$dbPort = '3306';

// Check if we're in CI environment (GitHub Actions)
if (getenv('GITHUB_ACTIONS') === 'true' || getenv('CI') === 'true') {
    $dbHost = '127.0.0.1'; // CI environment uses localhost
}

// Use environment variables if available, otherwise use defaults
$testDbUrl = getenv('TEST_DATABASE_URL') ?: "mysql://lms_user:lms_password@{$dbHost}:{$dbPort}/lms_db_test";

// Force tests to use test database, regardless of base env (prod/dev/staging)
$_SERVER['DATABASE_URL'] = $testDbUrl;
$_ENV['DATABASE_URL'] = $testDbUrl;
putenv('DATABASE_URL=' . $testDbUrl);

echo "PHPUnit Bootstrap: Forced DATABASE_URL to test database\n";
echo "Test Database URL: " . $testDbUrl . "\n";
echo "Environment: " . (getenv('GITHUB_ACTIONS') ? 'CI' : 'Docker') . "\n";

// Load the standard Symfony autoloader
require_once __DIR__ . '/../vendor/autoload.php';
