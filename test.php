<?php
try {
  $pdo = new PDO('mysql:host=127.0.0.1;dbname=tabaany', 'root', '');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  // $pdo->exec('ALTER TABLE utilisateur DROP FOREIGN KEY FK_1D1C63B3D60322AC');
  $pdo->exec('DROP INDEX fk_role ON utilisateur');
  echo 'Dropped';
} catch (PDOException $e) {
  echo 'Failed: ' . $e->getMessage();
}
