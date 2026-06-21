<?php
include('db.php');
$sql = "ALTER TABLE orders ADD COLUMN CancelReason TEXT DEFAULT NULL;";
if(mysqli_query($conn, $sql)){
    echo "Column CancelReason added successfully.";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>
