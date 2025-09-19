<?php
require_once __DIR__ . '/../includes/db.php';

$cn = db();

// Asegurar que exista el rol 'admin'
$roleAdmin = db_select_all("SELECT id FROM roles WHERE name='admin' LIMIT 1");
if (empty($roleAdmin) || isset($roleAdmin['_error'])) {
    die("No existe el rol 'admin' en tabla roles.");
}
$role_id = (int)$roleAdmin[0]['id'];

// ¿Ya existe un usuario admin por email?
$email = 'admin@local';
$check = db_select_all("SELECT id FROM users WHERE email = 'admin@local' LIMIT 1");
if (!empty($check) && empty($check['_error'])) {
    echo "Ya existe users.email=admin@local. Nada que hacer.";
    exit;
}

// Crear admin
$name  = 'Administrador';
$dept  = 'Sistemas';
$pass_plain = 'admin123'; // cámbiala después de entrar
$hash = password_hash($pass_plain, PASSWORD_DEFAULT);

// Escapar
$e_name = mysqli_real_escape_string($cn, $name);
$e_dept = mysqli_real_escape_string($cn, $dept);
$e_mail = mysqli_real_escape_string($cn, $email);
$e_hash = mysqli_real_escape_string($cn, $hash);

$sql = "
  INSERT INTO users (name, email, password_hash, department, role_id, is_active)
  VALUES ('$e_name', '$e_mail', '$e_hash', '$e_dept', $role_id, 1)
";
if (!mysqli_query($cn, $sql)) {
    die('Error creando admin: ' . mysqli_error($cn));
}

echo "OK: admin creado -> email: admin@local / pass: admin123 (cámbiala después).";
