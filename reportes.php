<?php
session_start();

require_once __DIR__ . '/config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: auth/login.php");
    exit;
}

// Resumen de ventas
$sql = "SELECT
            COUNT(*) AS cantidad,
            COALESCE(SUM(monto), 0) AS ingresos
        FROM ventas";
$resumenVentas = $conexion->query($sql)->fetch(PDO::FETCH_ASSOC);

// Total de pedidos
$totalPedidos = $conexion
    ->query("SELECT COUNT(*) FROM pedidos")
    ->fetchColumn();

// Pedidos por estado
$sql = "SELECT estado, COUNT(*) AS cantidad
        FROM pedidos
        GROUP BY estado";
$datosEstados = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$estados = [
    'pendiente' => 0,
    'preparando' => 0,
    'listo' => 0,
    'entregado' => 0,
    'cancelado' => 0
];

foreach ($datosEstados as $dato) {
    $estados[$dato['estado']] = $dato['cantidad'];
}

// Ventas por método de pago
$sql = "SELECT metodo_pago,
               COUNT(*) AS cantidad,
               COALESCE(SUM(monto), 0) AS total
        FROM ventas
        GROUP BY metodo_pago";
$ventasMetodo = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Últimas ventas registradas
$sql = "SELECT
            v.id_venta,
            v.id_pedido,
            v.metodo_pago,
            v.monto,
            v.fecha_venta,
            p.nombre_cliente
        FROM ventas v
        INNER JOIN pedidos p ON v.id_pedido = p.id_pedido
        ORDER BY v.fecha_venta DESC
        LIMIT 10";

$ultimasVentas = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Pedidos entregados que todavía no tienen venta
$sql = "SELECT COUNT(*)
        FROM pedidos p
        LEFT JOIN ventas v ON p.id_pedido = v.id_pedido
        WHERE p.estado = 'entregado'
        AND v.id_venta IS NULL";

$pendientesCobro = $conexion->query($sql)->fetchColumn();

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - CAFETIGO</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f5f0e8;
            color: #3e2723;
        }

        .contenedor {
            max-width: 1100px;
            margin: auto;
        }

        h1 {
            color: #5d4037;
        }

        .volver {
            display: inline-block;
            margin-bottom: 20px;
            color: white;
            background: #795548;
            padding: 10px 16px;
            text-decoration: none;
            border-radius: 6px;
        }

        .tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .tarjeta {
            background: white;
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #00000012;
        }

        .tarjeta h3 {
            margin-top: 0;
            font-size: 15px;
            color: #795548;
        }

        .numero {
            font-size: 26px;
            font-weight: bold;
            color: #4e342e;
        }

        .seccion {
            background: white;
            padding: 22px;
            margin-bottom: 22px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #00000012;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        th {
            background: #795548;
            color: white;
        }

        .estado {
            text-transform: capitalize;
        }

        .sin-datos {
            color: #777;
            padding: 15px 0;
        }

        @media (max-width: 650px) {
            body {
                padding: 12px;
            }

            .seccion {
                overflow-x: auto;
            }

            table {
                min-width: 550px;
            }
        }
    </style>
</head>

<body>

<div class="contenedor">

    <a href="dashboard.php" class="volver">← Volver al panel</a>

    <h1>📊 Reportes de CAFETIGO</h1>
    <p>Consulta las estadísticas y los movimientos de tu cafetería.</p>

    <div class="tarjetas">

        <div class="tarjeta">
            <h3>Ingresos totales</h3>
            <div class="numero">
                Bs <?= number_format((float)$resumenVentas['ingresos'], 2) ?>
            </div>
        </div>

        <div class="tarjeta">
            <h3>Ventas registradas</h3>
            <div class="numero">
                <?= (int)$resumenVentas['cantidad'] ?>
            </div>
        </div>

        <div class="tarjeta">
            <h3>Pedidos registrados</h3>
            <div class="numero">
                <?= (int)$totalPedidos ?>
            </div>
        </div>

        <div class="tarjeta">
            <h3>Pendientes de cobro</h3>
            <div class="numero">
                <?= (int)$pendientesCobro ?>
            </div>
        </div>

    </div>

    <div class="seccion">

        <h2>Pedidos por estado</h2>

        <table>
            <thead>
                <tr>
                    <th>Estado</th>
                    <th>Cantidad de pedidos</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($estados as $estado => $cantidad): ?>
                    <tr>
                        <td class="estado"><?= escapar($estado) ?></td>
                        <td><?= (int)$cantidad ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>

    <div class="seccion">

        <h2>Ventas por método de pago</h2>

        <table>
            <thead>
                <tr>
                    <th>Método de pago</th>
                    <th>Número de ventas</th>
                    <th>Total recaudado</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($ventasMetodo) > 0): ?>

                    <?php foreach ($ventasMetodo as $venta): ?>
                        <tr>
                            <td><?= escapar($venta['metodo_pago']) ?></td>
                            <td><?= (int)$venta['cantidad'] ?></td>
                            <td>Bs <?= number_format((float)$venta['total'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="3" class="sin-datos">
                            Todavía no hay ventas registradas.
                        </td>
                    </tr>

                <?php endif; ?>
            </tbody>
        </table>

    </div>

    <div class="seccion">

        <h2>Historial de las últimas ventas</h2>

        <table>
            <thead>
                <tr>
                    <th>N.º venta</th>
                    <th>N.º pedido</th>
                    <th>Cliente</th>
                    <th>Método de pago</th>
                    <th>Monto</th>
                    <th>Fecha</th>
                </tr>
            </thead>

            <tbody>

                <?php if (count($ultimasVentas) > 0): ?>

                    <?php foreach ($ultimasVentas as $venta): ?>
                        <tr>
                            <td>#<?= (int)$venta['id_venta'] ?></td>
                            <td>#<?= (int)$venta['id_pedido'] ?></td>
                            <td><?= escapar($venta['nombre_cliente'] ?: 'Sin nombre') ?></td>
                            <td><?= escapar($venta['metodo_pago']) ?></td>
                            <td>Bs <?= number_format((float)$venta['monto'], 2) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="sin-datos">
                            Todavía no hay ventas registradas.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>
        </table>

    </div>

</div>

</body>
</html>