<?php
require_once 'db.php';
$sql = "ALTER TABLE vendor ADD COLUMN Latitude DECIMAL(10, 8), ADD COLUMN Longitude DECIMAL(11, 8);";
if(mysqli_query($conn, $sql)){
    echo "Columns added successfully";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>
