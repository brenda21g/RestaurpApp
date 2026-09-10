```php
<?php

require_once __DIR__ . '/../config/auth_check.php';

$db = getDB();

$estado_filtro = $_GET['estado'] ?? '';
$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');

$where = "WHERE DATE(pe.creado_en) = ?";
$params = [$fecha_filtro];

if (
    $estado_filtro &&
    in_array(
        $estado_filtro,
        ['pendiente', 'preparando', 'listo', 'entregado', 'cancelado']
    )
) {
    $where .= " AND pe.estado = ?";
    $params[] = $estado_filtro;
}

/* Obtener pedidos */
$pedidos = $db->prepare("
    SELECT
        pe.*,
        m.numero as mesa_num
    FROM pedidos pe
    JOIN mesas m ON m.id = pe.mesa_id
    {$where}
    ORDER BY pe.creado_en DESC
");

$pedidos->execute($params);
$lista = $pedidos->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pedidos – RestaurApp Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap"
        rel="stylesheet"
    >

    <style>

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg: #f7f9fc;
            --card: #ffffff;
            --border: #e2e8f0;
            --accent: #ffffff;

            --text: #071b3a;
            --muted: #60708a;

            --sidebar: #03143d;
            --sidebar-hover: #092e70;
            --sidebar-border: #0b2455;

            --green: #07543f;
            --green-light: #e4f7f0;
            --blue: #1684c4;
            --red: #ff5b5b;

            --sidebar-w: 240px;

            --primary: #0788c9;
            --primary-dark: #0674ad;

            --green-dashboard: #13b77a;
            --amber: #f5a000;
            --red-dashboard: #ef5b63;

            --text-main: #101828;
            --text-secondary: #536987;
            --text-muted: #7890ad;

            --card-border: #e1e7ef;
            --card-bg: #ffffff;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            display: flex;
            min-height: 100vh;
            font-size: 14px;
        }

        /* ================================
           SIDEBAR
        ================================= */

        .sidebar {
            width: var(--sidebar-w);
            background: var(--sidebar);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 100;
        }

        .sidebar-logo {
            padding: 24px 20px;
            border-bottom: 1px solid var(--sidebar-border);
        }

        .sidebar-logo .name {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            color: var(--accent);
        }

        .sidebar-logo .role {
            font-size: 11px;
            color: #91a6c7;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .nav {
            padding: 16px 12px;
            flex: 1;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 12px;
            color: #9db0ce;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all .15s;
            margin-bottom: 4px;
        }

        .nav-item:hover {
            background: rgba(255,255,255,.06);
            color: #ffffff;
        }

        .nav-item.active {
            background: var(--sidebar-hover);
            color: #ffffff;
        }

        .nav-item .icon {
            font-size: 15px;
        }

        .sidebar-bottom {
            padding: 16px 12px;
            border-top: 1px solid var(--sidebar-border);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            color: #ff6868;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: background .15s;
        }

        .logout-btn:hover {
            background: rgba(255,104,104,.1);
        }

        /* ================================
           MAIN
        ================================= */

        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            padding: 28px 32px;
            max-width: calc(100% - var(--sidebar-w));
        }

        /* ================================
           TITLE
        ================================= */

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
        }

        /* ================================
           FILTERS
        ================================= */

        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            align-items: center;
        }

        .filter-input {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 14px;
            color: var(--text-main);
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            outline: none;
        }

        .filter-input:focus {
            border-color: var(--primary);
        }

        select.filter-input option {
            background: var(--card);
            color: var(--text-main);
        }

        .filter-btn {
            padding: 8px 16px;
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background .15s;
        }

        .filter-btn:hover {
            background: var(--primary-dark);
        }

        /* ================================
           TABLE
        ================================= */

        .table-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(7,27,58,.04);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px 16px;
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: .7px;
            text-transform: uppercase;
            color: var(--text-secondary);
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 13px 16px;
            border-top: 1px solid var(--border);
            font-size: 13px;
            vertical-align: middle;
            color: var(--text-main);
        }

        tbody tr:hover {
            background: #fafcff;
        }

        /* ================================
           BADGES
        ================================= */

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge.pendiente {
            background: #fff4d8;
            color: #d88900;
        }

        .badge.preparando {
            background: #e3f4fb;
            color: #0788c9;
        }

        .badge.listo {
            background: #dff7ed;
            color: #079969;
        }

        .badge.entregado {
            background: #e5f8f1;
            color: #0bad77;
        }

        .badge.cancelado {
            background: #fde9eb;
            color: #dc4c55;
        }

        /* ================================
           DETAIL BUTTON
        ================================= */

        .detail-btn {
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: transparent;
            color: var(--text-secondary);
            font-family: 'DM Sans', sans-serif;
            font-size: 12px;
            cursor: pointer;
            transition: all .15s;
        }

        .detail-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #f7fbfe;
        }

        /* ================================
           MODAL
        ================================= */

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(3,20,61,.55);
            backdrop-filter: blur(3px);
            z-index: 200;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 15px 40px rgba(7,27,58,.18);
        }

        .modal h3 {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            color: var(--text);
            margin-bottom: 16px;
        }

        .modal-close {
            float: right;
            background: none;
            border: none;
            color: var(--muted);
            font-size: 20px;
            cursor: pointer;
            transition: color .15s;
        }

        .modal-close:hover {
            color: var(--red);
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            color: var(--text-main);
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            padding-top: 12px;
            font-weight: 600;
            color: var(--primary);
            font-size: 15px;
        }

        /* ================================
           RESPONSIVE
        ================================= */

        @media(max-width:800px) {

            .sidebar {
                width: 210px;
            }

            .main {
                margin-left: 210px;
                padding: 24px;
                max-width: calc(100% - 210px);
            }
        }

        @media(max-width:600px) {

            .sidebar {
                width: 70px;
            }

            .sidebar-logo .name,
            .sidebar-logo .role,
            .nav-item:not(.active)::after,
            .logout-btn {
                font-size: 0;
            }

            .sidebar-logo {
                padding: 20px 10px;
                text-align: center;
            }

            .nav {
                padding: 16px 8px;
            }

            .nav-item {
                justify-content: center;
                padding: 12px 8px;
            }

            .nav-item .icon {
                font-size: 18px;
            }

            .logout-btn {
                justify-content: center;
                padding: 12px 8px;
            }

            .main {
                margin-left: 70px;
                padding: 20px 16px;
                max-width: calc(100% - 70px);
            }

            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-input,
            .filter-btn {
                width: 100%;
            }

            .table-card {
                overflow-x: auto;
            }

            table {
                min-width: 700px;
            }

            .modal {
                width: 95%;
                padding: 22px;
            }
        }

    </style>

</head>

<body>

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="sidebar-logo">

            <div class="name">
                🍽️ RestaurApp
            </div>

            <div class="role">
                Subgerente
            </div>

        </div>

        <nav class="nav">

            <a
                class="nav-item"
                href="dashboard.php"
            >
                <span class="icon">📊</span>
                Dashboard
            </a>

            <a
                class="nav-item active"
                href="pedidos.php"
            >
                <span class="icon">📋</span>
                Pedidos
            </a>

            <a
                class="nav-item"
                href="mesas_qr.php"
            >
                <span class="icon">🪑</span>
                Mesas & QR
            </a>

            <a
                class="nav-item"
                href="menu.php"
            >
                <span class="icon">🍽️</span>
                Menú
            </a>

            <a
                class="nav-item"
                href="corte.php"
            >
                <span class="icon">💵</span>
                Corte de Caja
            </a>

        </nav>

        <div class="sidebar-bottom">

            <a
                class="logout-btn"
                href="logout.php"
            >
                🚪 Cerrar sesión
            </a>

        </div>

    </aside>


    <!-- MAIN -->

    <main class="main">

        <div class="page-title">
            📋 Registro de pedidos
        </div>


        <!-- FILTROS -->

        <form
            class="filters"
            method="GET"
        >

            <input
                class="filter-input"
                type="date"
                name="fecha"
                value="<?= htmlspecialchars($fecha_filtro) ?>"
            >

            <select
                class="filter-input"
                name="estado"
            >

                <option value="">
                    Todos los estados
                </option>

                <?php foreach (
                    ['pendiente', 'preparando', 'listo', 'entregado', 'cancelado']
                    as $e
                ): ?>

                    <option
                        value="<?= htmlspecialchars($e) ?>"
                        <?= $estado_filtro === $e ? 'selected' : '' ?>
                    >
                        <?= ucfirst($e) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <button
                class="filter-btn"
                type="submit"
            >
                Filtrar
            </button>

        </form>


        <!-- TABLA -->

        <div class="table-card">

            <table>

                <thead>

                    <tr>

                        <th>
                            #Orden
                        </th>

                        <th>
                            Mesa
                        </th>

                        <th>
                            Total
                        </th>

                        <th>
                            Estado
                        </th>

                        <th>
                            Hora
                        </th>

                        <th>
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php if (empty($lista)): ?>

                        <tr>

                            <td
                                colspan="6"
                                style="
                                    text-align:center;
                                    color:var(--text-muted);
                                    padding:30px;
                                "
                            >
                                Sin pedidos registrados para el filtro seleccionado
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($lista as $p):

                            $numOrden =
                                $p['numero_orden']
                                ?? (
                                    '#'
                                    . str_pad(
                                        $p['id'],
                                        4,
                                        '0',
                                        STR_PAD_LEFT
                                    )
                                );

                        ?>

                            <tr>

                                <td style="font-weight:600;">

                                    <?= htmlspecialchars($numOrden) ?>

                                </td>

                                <td>

                                    Mesa
                                    <?= htmlspecialchars($p['mesa_num']) ?>

                                </td>

                                <td>

                                    $<?= number_format(
                                        $p['total'],
                                        2
                                    ) ?>

                                </td>

                                <td>

                                    <span
                                        class="badge <?= htmlspecialchars(
                                            $p['estado']
                                        ) ?>"
                                    >

                                        <?= ucfirst(
                                            htmlspecialchars(
                                                $p['estado']
                                            )
                                        ) ?>

                                    </span>

                                </td>

                                <td style="color:var(--text-muted);">

                                    <?= date(
                                        'H:i',
                                        strtotime($p['creado_en'])
                                    ) ?>

                                </td>

                                <td>

                                    <button
                                        type="button"
                                        class="detail-btn"
                                        onclick="verDetalle(<?= (int)$p['id'] ?>)"
                                    >
                                        Ver detalle
                                    </button>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </main>


    <!-- MODAL -->

    <div
        class="modal-overlay"
        id="modal"
    >

        <div class="modal">

            <button
                type="button"
                class="modal-close"
                onclick="cerrarModal()"
            >
                ✕
            </button>

            <h3 id="modal-title">
                Detalle del pedido
            </h3>

            <div id="modal-body"></div>

        </div>

    </div>


    <!-- JAVASCRIPT -->

    <script>

        async function verDetalle(id) {

            try {

                const res = await fetch(
                    'pedido_detalle.php?id=' + id
                );

                const data = await res.json();

                if (!data.success && data.error) {

                    alert(data.error);

                    return;

                }

                const p = data.pedido;
                const items = data.items;

                const ordenCodigo =
                    p.numero_orden ||
                    (
                        '#'
                        + String(p.id).padStart(4, '0')
                    );

                let html = `
                    <div
                        style="
                            color:var(--muted);
                            font-size:12px;
                            margin-bottom:16px;
                        "
                    >
                        Mesa ${p.mesa_num}
                        ·
                        ${new Date(
                            p.creado_en
                        ).toLocaleString('es-MX')}
                    </div>
                `;


                if (p.notas) {

                    html += `
                        <div
                            style="
                                background:#fff8e7;
                                border-left:3px solid var(--amber);
                                padding:8px 12px;
                                margin-bottom:16px;
                                border-radius:4px;
                                font-size:12px;
                                color:var(--text-main);
                            "
                        >
                            <strong>Notas:</strong>
                            ${p.notas}
                        </div>
                    `;

                }


                items.forEach(it => {

                    html += `
                        <div class="item-row">

                            <span>
                                ${it.cantidad}x
                                ${it.nombre}
                            </span>

                            <span>
                                $${parseFloat(
                                    it.subtotal
                                ).toFixed(2)}
                            </span>

                        </div>
                    `;

                });


                html += `
                    <div class="total-line">

                        <span>
                            Total
                        </span>

                        <span>
                            $${parseFloat(
                                p.total
                            ).toFixed(2)}
                        </span>

                    </div>
                `;


                document.getElementById(
                    'modal-title'
                ).textContent =
                    'Orden ' + ordenCodigo;


                document.getElementById(
                    'modal-body'
                ).innerHTML = html;


                document.getElementById(
                    'modal'
                ).classList.add('show');


            } catch (err) {

                alert(
                    'Ocurrió un error al cargar el detalle del pedido.'
                );

            }

        }


        function cerrarModal() {

            document.getElementById(
                'modal'
            ).classList.remove('show');

        }


        document
            .getElementById('modal')
            .addEventListener(
                'click',
                function(e) {

                    if (e.target === this) {

                        cerrarModal();

                    }

                }
            );

    </script>

</body>

</html>
```
