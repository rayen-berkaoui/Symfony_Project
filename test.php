<?php $c = new PDO('mysql:host=localhost;dbname=tabaany', 'root', ''); print_r($c->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
