<?php
// ==========================================================================
// CONTROLADOR Y VISTA: Editar Información del Cliente
// ==========================================================================
require_once __DIR__ . '/../config/auth_check.php';
$db = getDB();
$id = $_GET['id'] ?? null;

$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $etapa    = $_POST['etapa_crm'] ?? 'Prospecto';
    $estado   = $_POST['estado'] ?? 'Activo';

    if (!empty($nombre) && !empty($email)) {
        try {
            $update = $db->prepare("UPDATE usuarios_cliente SET nombre = ?, email = ?, telefono = ?, etapa_crm = ?, estado = ? WHERE id = ?");
            $update->execute([$nombre, $email, $telefono, $etapa, $estado, $id]);
            header("Location: clientes.php");
            exit;
        } catch (PDOException $e) {
            $mensaje_error = "El correo ya pertenece a otro cliente.";
        }
    } else {
        $mensaje_error = "Todos los campos obligatorios deben llenarse.";
    }
}

$stmt = $db->prepare("SELECT * FROM usuarios_cliente WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    header("Location: clientes.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Cliente – Restaurant App</title>
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
           FORMULARIO DE EDICIÓN
           ========================================================================== */
        form {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            padding: 32px;
            border-radius: var(--radius);
            width: 100%;
            max-width: 450px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            box-shadow: var(--shadow-sm);
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

        button {
            background: var(--color-primary);
            color: #fff;
            padding: 12px;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            margin-top: 5px;
            transition: background 0.2s;
        }

        button:hover {
            background: #1d4ed8;
        }

        .back {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .back:hover {
            color: var(--color-primary);
        }

        h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
        }

        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 10px;
            border-radius: var(--radius);
            font-size: 13px;
        }
    </style>
</head>
<body>
<form method="POST">
    <a href="clientes.php" class="back">← Volver a clientes</a>
    <h2>Editar Cliente / Prospecto</h2>
    
    <?php if ($mensaje_error): ?><div class="alert-error"><?= $mensaje_error ?></div><?php endif; ?>

    <input type="text" name="nombre" value="<?= htmlspecialchars($cliente['nombre']) ?>" required placeholder="Nombre">
    <input type="email" name="email" value="<?= htmlspecialchars($cliente['email']) ?>" required placeholder="Correo">
    <input type="text" name="telefono" value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>" placeholder="Teléfono">
    
    <select name="etapa_crm">
        <option value="Prospecto" <?= ($cliente['etapa_crm'] ?? '') === 'Prospecto' ? 'selected' : '' ?>>Prospecto</option>
        <option value="Cliente" <?= ($cliente['etapa_crm'] ?? '') === 'Cliente' ? 'selected' : '' ?>>Cliente</option>
    </select>
    
    <select name="estado">
        <option value="Activo" <?= ($cliente['estado'] ?? '') === 'Activo' ? 'selected' : '' ?>>Activo</option>
        <option value="Inactivo" <?= ($cliente['estado'] ?? '') === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
        <option value="Baja" <?= ($cliente['estado'] ?? '') === 'Baja' ? 'selected' : '' ?>>Baja</option>
    </select>
    
    <button type="submit">Actualizar Cambios</button>
</form>
</body>
</html>