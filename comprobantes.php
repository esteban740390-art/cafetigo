
<?php
session_start();

require_once __DIR__ . '/config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: auth/login.php");
    exit;
}

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

// Consultar comprobante seleccionado
$comprobante = null;

if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if (!$id) {
        die("Número de comprobante no válido.");
    }

    $sql = "SELECT
                v.id_venta,
                v.id_pedido,
                v.metodo_pago,
                v.monto,
                v.fecha_venta,
                p.nombre_cliente,
                p.tipo_pedido,
                p.numero_mesa,
                p.fecha_pedido
            FROM ventas v
            INNER JOIN pedidos p ON v.id_pedido = p.id_pedido
            WHERE v.id_venta = ?";

    $consulta = $conexion->prepare($sql);
    $consulta->execute([$id]);
    $comprobante = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$comprobante) {
        die("No se encontró el comprobante.");
    }

} else {
    // Historial de comprobantes
    $sql = "SELECT
                v.id_venta,
                v.id_pedido,
                v.metodo_pago,
                v.monto,
                v.fecha_venta,
                p.nombre_cliente
            FROM ventas v
            INNER JOIN pedidos p ON v.id_pedido = p.id_pedido
            ORDER BY v.fecha_venta DESC";

    $ventas = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobantes - CAFETIGO</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f0e8;
            color: #3e2723;
            margin: 0;
            padding: 25px;
        }

        .contenedor {
            max-width: 900px;
            margin: auto;
        }

        .volver {
            display: inline-block;
            background: #795548;
            color: white;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            margin-bottom: 20px;
        }

        .panel, .recibo {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-top: 20px;
            box-shadow: 0 2px 8px #00000012;
        }

        h1, h2 {
            color: #5d4037;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #795548;
            color: white;
        }

        .ver {
            background: #795548;
            color: white;
            padding: 7px 12px;
            text-decoration: none;
            border-radius: 5px;
        }

        .centrado {
            text-align: center;
        }

        .total {
            font-size: 24px;
            font-weight: bold;
            color: #4e342e;
        }

        .imprimir {
            background: #4e342e;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 20px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .volver, .imprimir {
                display: none;
            }

            .recibo {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>

<body>

<div class="contenedor">

    <a href="dashboard.php" class="volver">← Volver al panel</a>

    <?php if ($comprobante): ?>

        <div class="recibo">

            <div class="centrado">
                <h1>CAFETIGO</h1>
                <p>Sistema web de pedidos y atención para cafeterías</p>
                <h2>COMPROBANTE DE VENTA</h2>
            </div>

            <hr>

            <p><strong>N.º de venta:</strong>
                #<?= (int)$comprobante['id_venta'] ?></p>

            <p><strong>N.º de pedido:</strong>
                #<?= (int)$comprobante['id_pedido'] ?></p>

            <p><strong>Cliente:</strong>
                <?= escapar($comprobante['nombre_cliente'] ?: 'Sin nombre') ?></p>

            <p><strong>Tipo de pedido:</strong>
                <?= escapar($comprobante['tipo_pedido']) ?></p>

            <?php if ($comprobante['tipo_pedido'] === 'mesa'): ?>
                <p><strong>Número de mesa:</strong>
                    <?= escapar($comprobante['numero_mesa'] ?: 'No especificada') ?></p>
            <?php endif; ?>

            <p><strong>Fecha del pedido:</strong>
                <?= date('d/m/Y H:i', strtotime($comprobante['fecha_pedido'])) ?></p>

            <p><strong>Fecha de venta:</strong>
                <?= date('d/m/Y H:i', strtotime($comprobante['fecha_venta'])) ?></p>

            <p><strong>Método de pago:</strong>
                <?= escapar($comprobante['metodo_pago']) ?></p>

            <hr>

            <p class="total">
                TOTAL: Bs <?= number_format((float)$comprobante['monto'], 2) ?>
            </p>

            <div class="centrado">
                <p>¡Gracias por su compra!</p>
                <p>CAFETIGO</p>
            </div>

        </div>

        <div class="centrado">
            <button class="imprimir" onclick="window.print()">
                Imprimir comprobante
            </button>
        </div>

    <?php else: ?>

        <h1>🧾 Comprobantes de venta</h1>
        <p>Consulta e imprime los comprobantes de las ventas registradas.</p>

        <div class="panel">

            <h2>Historial de comprobantes</h2>

            <table>
                <thead>
                    <tr>
                        <th>N.º venta</th>
                        <th>Cliente</th>
                        <th>Pedido</th>
                        <th>Método de pago</th>
                        <th>Monto</th>
                        <th>Acción</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($ventas) > 0): ?>

                        <?php foreach ($ventas as $venta): ?>
                            <tr>
                                <td>#<?= (int)$venta['id_venta'] ?></td>
                                <td><?= escapar($venta['nombre_cliente'] ?: 'Sin nombre') ?></td>
                                <td>#<?= (int)$venta['id_pedido'] ?></td>
                                <td><?= escapar($venta['metodo_pago']) ?></td>
                                <td>Bs <?= number_format((float)$venta['monto'], 2) ?></td>
                                <td>
                                    <a class="ver"
                                       href="comprobantes.php?id=<?= (int)$venta['id_venta'] ?>">
                                        Ver comprobante
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6">Todavía no hay ventas registradas.</td>
                        </tr>

                    <?php endif; ?>

                </tbody>
            </table>

        </div>

    <?php endif; ?>

</div>

</body>
</html>
