<?php
// Configuración de parámetros de conexión
$host = "localhost";
$user = "root";
$password = "";
$database = "colegio_bd";

// Crear la conexión
$conexion = mysqli_connect($host, $user, $password, $database);

// Verificar la conexión
if (!$conexion) {
    die("Error crítico de conexión a la Base de Datos: " . mysqli_connect_error());
}

// Establecer conjunto de caracteres UTF-8
mysqli_set_charset($conexion, "utf8mb4");
?>