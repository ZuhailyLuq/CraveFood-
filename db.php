<?php

$host="localhost";
$username="root";
$password="";
$db="foodcrave";
$conn="";

$conn= mysqli_connect($host,$username,$password,$db);


if($conn){


}else{
	echo"error";
}

?>