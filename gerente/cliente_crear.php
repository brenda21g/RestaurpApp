<?php
// ==========================================================================
// CONTROLADOR Y VISTA: Registrar Nuevo Cliente
// ==========================================================================
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();

$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre    = trim($_POST['nombre'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $telefono  = trim($_POST['telefono'] ?? '');
    $etapa_crm = $_POST['etapa_crm'] ?? 'Prospecto';
    $estado    = $_POST['estado'] ?? 'Activo';
    $password  = md5('123456');

    if (!empty($nombre) && !empty($email)) {
        try {
            $stmt = $db->prepare("INSERT INTO usuarios_cliente (nombre, email, password, telefono, etapa_crm, estado, email_confirmado) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$nombre, $email, $password, $telefono, $etapa_crm, $estado]);
            header("Location: clientes.php");
            exit;
        } catch (PDOException $e) {
            $mensaje_error = "El correo electrónico ya se encuentra registrado.";
        }
    } else {
        $mensaje_error = "Los campos de nombre y correo son obligatorios.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Cliente – Restaurant App</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ==========================================================================
           VARIABLES Y RESET GENERAL
           ========================================================================== */
        :root {
            --bg-body: #011139;
            --bg-surface: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --color-primary: #2563eb;
            --color-success: #10b981;
            --color-danger: #ef4444;
            --sidebar-w: 250px;
            --radius: 10px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            font-size: 14px;
        }

        /* ==========================================================================
           CONTENEDOR Y FORMULARIO
           ========================================================================== */
        .form-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            padding: 32px;
            border-radius: var(--radius);
            width: 100%;
            max-width: 450px;
            box-shadow: var(--shadow-sm);
        }

        h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-main);
        }

        .field {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        input, select {
            background: #fff;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 10px 12px;
            border-radius: var(--radius);
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        input:focus, select:focus {
            border-color: var(--color-primary);
        }

        .btn-submit {
            width: 100%;
            background: var(--color-primary);
            color: #fff;
            padding: 12px;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: #1d4ed8;
        }

        .back {
            display: inline-block;
            margin-bottom: 16px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .back:hover {
            color: var(--color-primary);
        }

        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 10px;
            border-radius: var(--radius);
            font-size: 13px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
<div class="form-card">
    <a href="clientes.php" class="back">← Volver a clientes</a>
    <h2>Registrar Nuevo Cliente / Prospecto</h2>
    
    <?php if ($mensaje_error): ?><div class="alert-error"><?= $mensaje_error ?></div><?php endif; ?>

    <form method="POST">
        <div class="field">
            <label>Nombre completo</label>
            <input type="text" name="nombre" required placeholder="Ej. Juan Pérez">
        </div>
        <div class="field">
            <label>Correo electrónico</label>
            <input type="email" name="email" required placeholder="ejemplo@correo.com">
        </div>
        <div class="field">
            <label>Teléfono</label>
            <input type="text" name="telefono" placeholder="4491234567">
        </div>
        <div class="field">
            <label>Etapa CRM</label>
            <select name="etapa_crm">
                <option value="Prospecto">Prospecto</option>
                <option value="Cliente">Cliente</option>
            </select>
        </div>
        <div class="field">
            <label>Estado</label>
            <select name="estado">
                <option value="Activo">Activo</option>
                <option value="Inactivo">Inactivo</option>
                <option value="Baja">Baja</option>
            </select>
        </div>
        <button type="submit" class="btn-submit">Guardar Registro</button>
    </form>
</div>
</body>
</html>