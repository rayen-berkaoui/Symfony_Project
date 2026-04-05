<?php
$conn = mysqli_connect("127.0.0.1", "root", "", "tabaany");
$res = mysqli_query($conn, "SHOW CREATE TABLE reservation");
while($row = mysqli_fetch_assoc($res)) { print_r($row); }

