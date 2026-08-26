<?php
require_once __DIR__ . '/config.php';

// Iniciamos sesión si no existe una activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si no hay ID de admin en la sesión, mandamos al login
//if (!isset($_SESSION['admin_id'])) {
  //  header('Location: login.php'); 
   // exit;
//}

$admin_nombre = $_SESSION['admin_nombre'] ?? 'Admin';