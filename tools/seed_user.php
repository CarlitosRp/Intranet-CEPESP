<?php
require_once __DIR__ . '/../includes/db.php';

$cn = db();

// Asegurar que exista el rol 'admin'
$roleInventarios = db_select_all("SELECT id FROM roles WHERE name='inventarios' LIMIT 1");
if (empty($roleInventarios) || isset($rolInventarios['_error'])) {
    die("No existe el rol 'inventarios' en tabla roles.");
}
$role_id = (int)$roleInventarios[0]['id'];

// ¿Ya existe un usuario admin por email?
$email = 'paquiao@local';
$check = db_select_all("SELECT id FROM users WHERE email = 'paquiao@local' LIMIT 1");
if (!empty($check) && empty($check['_error'])) {
    echo "Ya existe users.email=paquiao@local. Nada que hacer.";
    exit;
}

// Crear admin
$name  = 'JOSE DE JESUS SALAZAR VERDUGO';
$dept  = 'Inventarios';
$pass_plain = 'paquiao34031'; // cámbiala después de entrar
$hash = password_hash($pass_plain, PASSWORD_DEFAULT);

// Escapar
$e_name = mysqli_real_escape_string($cn, $name);
$e_dept = mysqli_real_escape_string($cn, $dept);
$e_mail = mysqli_real_escape_string($cn, $email);
$e_hash = mysqli_real_escape_string($cn, $hash);

$sql = "
  INSERT INTO users (name, email, password_hash, department, role_id, is_active)
  VALUES ('$e_name', '$e_mail', '$e_hash', '$e_dept', $role_id, 2)
";
if (!mysqli_query($cn, $sql)) {
    die('Error creando inventarios: ' . mysqli_error($cn));
}

echo "OK: Usuario creado correctamente.";
