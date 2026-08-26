<?php
// Corregimos la ruta para que encuentre el archivo en la misma carpeta
require_once 'config.php';

// Limpiamos todas las variables de sesión
$_SESSION = array();

// Destruimos la sesión
session_destroy();

// Redirigimos al login
header('Location: login.php');
exit;