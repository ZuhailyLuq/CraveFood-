<?php
require_once __DIR__ . '/api/db.php';

try {
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'orders'");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in 'orders' table:\n";
    foreach ($columns as $col) {
        echo "- " . $col['column_name'] . " (" . $col['data_type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
