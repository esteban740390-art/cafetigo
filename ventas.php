
<?php
session_start();

require_once __DIR__ . '/config/conexion.php';

// Verificar inicio de sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: auth/login.php");
    exit;
}

$mensaje = "";
$error = "";

// Registrar una venta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_venta'])) {

    $id_pedido = filter_input(INPUT_POST, 'id_pedido', FILTER_VALIDATE_INT);
    $metodo_pago = $_POST['metodo_pago'] ?? '';

    $metodos_validos = ['efectivo', 'transferencia', 'qr'];

    if (!$id_pedido || !in_array($metodo_pago, $metodos_validos, true)) {
        $error = "Selecciona un pedido y un método de pago válido.";
    } else {
        try {
            $conexion->beginTransaction();

            // Consultar y bloquear el pedido
            $consulta = $conexion->prepare(
                "SELECT id_pedido, total, estado
                 FROM pedidos
                 WHERE id_pedido = ?
                 FOR UPDATE"
            );
            $consulta->execute([$id_pedido]);
            $pedido = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                throw new Exception("El pedido no existe.");
            }

            if ($pedido['estado'] !== 'entregado') {
                throw new Exception("Solo se pueden registrar pedidos entregados.");
            }

            if ((float)$pedido['total'] <= 0) {
                throw new Exception("El pedido no tiene un monto válido.");
            }

            // Evitar ventas duplicadas
            $verificar = $conexion->prepare(
                "SELECT id_venta FROM ventas WHERE id_pedido = ?"
            );
            $verificar->execute([$id_pedido]);

            if ($verificar->fetch()) {
                throw new Exception("Este pedido ya tiene una venta registrada.");
            }

            // Insertar venta
            $insertar = $conexion->prepare(
                "INSERT INTO ventas (id_pedido, metodo_pago, monto)
                 VALUES (?, ?, ?)"
            );

            $insertar->execute([
                $id_pedido,
                $metodo_pago,
                $pedido['total']
            ]);

            $conexion->commit();

            $mensaje = "¡Venta registrada correctamente!";

        } catch (Exception $e) {

            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}

// Consultar pedidos entregados que todavía no tienen venta
$consulta_pedidos = $conexion->query(
    "SELECT p.id_pedido, p.nombre_cliente, p.total, p.fecha_pedido
     FROM pedidos p
     LEFT JOIN ventas v ON p.id_pedido = v.id_pedido
     WHERE p.estado = 'entregado'
     AND v.id_venta IS NULL
     ORDER BY p.fecha_pedido DESC"
);

$pedidos_disponibles = $consulta_pedidos->fetchAll(PDO::FETCH_ASSOC);

// Consultar historial de ventas
$consulta_ventas = $conexion->query(
    "SELECT v.id_venta, v.id_pedido, v.metodo_pago,
            v.monto, v.fecha_venta, p.nombre_cliente
     FROM ventas v
     INNER JOIN pedidos p ON v.id_pedido = p.id_pedido
     ORDER BY v.fecha_venta DESC"
);

$ventas = $consulta_ventas->fetchAll(PDO::FETCH_ASSOC);

// Calcular total vendido
$total_ventas = 0;

foreach ($ventas as $venta) {
    $total_ventas += (float)$venta['monto'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas - CafetiGo</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f5f0e8;
            color: #44352b;
        }

        .contenido {
            padding: 35px;
            max-width: 1400px;
            margin: auto;
        }

        .encabezado {
            margin-bottom: 25px;
        }

        .encabezado h1 {
            color: #593d2b;
            margin-bottom: 8px;
        }

        .encabezado p {
            color: #887668;
        }

        .tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .tarjeta {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
        }

        .tarjeta h3 {
            color: #806c5b;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .tarjeta h2 {
            color: #593d2b;
            font-size: 27px;
        }

        .panel {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .panel h2 {
            color: #593d2b;
            margin-bottom: 20px;
            font-size: 19px;
        }

        .formulario {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            align-items: end;
        }

        .campo label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 14px;
        }

        .campo select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d8cbbd;
            border-radius: 7px;
            background: white;
        }

        .boton {
            background: #68472f;
            color: white;
            padding: 13px 20px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
        }

        .boton:hover {
            background: #49301f;
        }

        .tabla-contenedor {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #eee5dc;
            font-size: 14px;
        }

        th {
            background: #f3ece3;
            color: #593d2b;
        }

        .estado {
            background: #e1f2e5;
            color: #28733b;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        .mensaje {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #e1f2e5;
            color: #28733b;
        }

        .error {
            background: #fce4e4;
            color: #a12626;
        }

        .vacio {
            color: #887668;
            padding: 15px 0;
        }

        @media(max-width: 600px) {
            .contenido {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<div class="contenido">

    <div class="encabezado">
        <h1>Gestión de ventas</h1>
        <p>Administra los pagos y consulta las ventas de CafetiGo.</p>
    </div>

    <?php if ($mensaje): ?>
        <div class="mensaje">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mensaje error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="tarjetas">

        <div class="tarjeta">
            <h3>Total de ventas</h3>
            <h2>Bs <?= number_format($total_ventas, 2) ?></h2>
        </div>

        <div class="tarjeta">
            <h3>Ventas registradas</h3>
            <h2><?= count($ventas) ?></h2>
        </div>

        <div class="tarjeta">
            <h3>Pedidos por cobrar</h3>
            <h2><?= count($pedidos_disponibles) ?></h2>
        </div>

    </div>

    <div class="panel">

        <h2>Registrar nueva venta</h2>

        <?php if (count($pedidos_disponibles) > 0): ?>

            <form method="POST" class="formulario">

                <div class="campo">
                    <label>Seleccionar pedido</label>

                    <select name="id_pedido" required>
                        <option value="">Seleccione un pedido</option>

                        <?php foreach ($pedidos_disponibles as $pedido): ?>
                            <option value="<?= $pedido['id_pedido'] ?>">
                                Pedido #<?= $pedido['id_pedido'] ?>
                                - <?= htmlspecialchars($pedido['nombre_cliente'] ?? 'Cliente') ?>
                                - Bs <?= number_format($pedido['total'], 2) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="campo">
                    <label>Método de pago</label>

                    <select name="metodo_pago" required>
                        <option value="">Seleccione método</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="qr">QR</option>
                    </select>
                </div>

                <div class="campo">
                    <button type="submit" name="registrar_venta" class="boton">
                        Registrar venta
                    </button>
                </div>

            </form>

        <?php else: ?>

            <p class="vacio">
                No hay pedidos entregados pendientes de cobro.
            </p>

        <?php endif; ?>

    </div>

    <div class="panel">

        <h2>Historial de ventas</h2>

        <div class="tabla-contenedor">

            <table>

                <thead>
                    <tr>
                        <th>N.º Venta</th>
                        <th>N.º Pedido</th>
                        <th>Cliente</th>
                        <th>Método de pago</th>
                        <th>Monto</th>
                        <th>Fecha</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($ventas) > 0): ?>

                        <?php foreach ($ventas as $venta): ?>

                            <tr>
                                <td>#<?= $venta['id_venta'] ?></td>

                                <td>#<?= $venta['id_pedido'] ?></td>

                                <td>
                                    <?= htmlspecialchars($venta['nombre_cliente'] ?? 'Cliente') ?>
                                </td>

                                <td>
                                    <?= ucfirst(htmlspecialchars($venta['metodo_pago'])) ?>
                                </td>

                                <td>
                                    Bs <?= number_format($venta['monto'], 2) ?>
                                </td>

                                <td>
                                    <?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6">
                                Todavía no hay ventas registradas.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
