<?php
/**
 * phpMyAdmin configuration file with basic security settings
 */

// Basic security settings
$cfg['blowfish_secret'] = $_ENV['APP_SECRET'] ?? 'default-secret-key-change-this';

// Session settings
$cfg['SessionSavePath'] = '/tmp';

// Basic security
$cfg['ShowPhpInfo'] = false;
$cfg['ShowCreateDb'] = false;
$cfg['ShowServerInfo'] = false;

// Disable file uploads for security
$cfg['UploadDir'] = '';
$cfg['SaveDir'] = '';

// Limit database operations
$cfg['MaxRows'] = 50;
$cfg['MaxCharactersInDisplayedSQL'] = 1000;

// Disable SQL history
$cfg['QueryHistoryDB'] = false;
$cfg['QueryHistoryMax'] = 0;

// Additional security
$cfg['ProtectBinary'] = 'blob';
$cfg['ShowBlob'] = false;

// Disable potentially dangerous SQL commands
$cfg['DisableShortcutKeys'] = true;
$cfg['SendErrorReports'] = 'never';

// Logging
$cfg['Error_Handler']['display'] = false;
$cfg['Error_Handler']['log'] = true;
