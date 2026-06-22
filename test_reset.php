<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'api/db.php';
require_once 'api/db_helpers.php';

$email = 'test@example.com';
$hashed = password_hash('123456', PASSWORD_DEFAULT);

try {
    echo "Testing User Query...\n";
    $existing = db_fetch_one($pdo, 'SELECT "UserId" FROM "user" WHERE "Email" = ?', [$email]);
    echo "User fetch success. Existing? " . ($existing ? 'Yes' : 'No') . "\n";
    
    if ($existing) {
        db_execute($pdo, 'UPDATE "user" SET "Password" = ? WHERE "Email" = ?', [$hashed, $email]);
        echo "User update success.\n";
    }

    echo "Testing Vendor Query...\n";
    $existing = db_fetch_one($pdo, 'SELECT "VendorID" FROM vendor WHERE "Email" = ?', [$email]);
    echo "Vendor fetch success. Existing? " . ($existing ? 'Yes' : 'No') . "\n";

    if ($existing) {
        db_execute($pdo, 'UPDATE vendor SET "VendorPassword" = ? WHERE "Email" = ?', [$hashed, $email]);
        echo "Vendor update success.\n";
    }
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
