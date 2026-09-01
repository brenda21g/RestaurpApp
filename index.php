<?php
// Capturar parámetros de URL (por ejemplo ?mesa=1)
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';

// Redirigir a cliente/login.php
header('Location: cliente/login.php' . $queryString, true, 302);
exit;
?>