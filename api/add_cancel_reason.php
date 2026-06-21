<?php
// Migration helper — not needed in production (Supabase schema already includes CancelReason).
// Kept for reference only.
include('db.php');
try {
    $pdo->exec('ALTER TABLE orders ADD COLUMN IF NOT EXISTS "CancelReason" TEXT DEFAULT NULL');
    echo "Column CancelReason added (or already exists).";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
