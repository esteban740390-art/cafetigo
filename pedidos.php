
<?php
session_start();

require_once __DIR__ . '/config/conexion.php';

// Permitir el acceso a administradores y cajeros.
if (
    !isset($_SESSION['id_usuario']) ||
    !in_array($_SESSION['rol'] ?? '', ['administrador', 'cajero'], true)
) {
    header('Location: index.php');
    exit;
}

$mi_id = (int) $_SESSION['id_usuario'];

if (empty($_SESSION['csrf_pedidos'])) {
    $_SESSION['csrf_pedidos'] = bin2hex(random_bytes(32));
}

$mensaje = '';
$tipo_mensaje = 'exito';

function limpiarPedido($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// Registrar mensajes.
function mostrarMensaje($texto, $tipo = 'exito') {
    global $mensaje, $tipo_mensaje;
    $mensaje = $texto;
    $tipo_mensaje = $tipo;
}

// Procesar formularios.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_pedidos'], $token)) {

        mostrarMensaje('La solicitud no es válida. Recarga la página.', 'error');

    } else {

        $accion = $_POST['accion'] ?? '';

        // REGISTRAR PEDIDO.
        if ($accion === 'registrar') {

            try {

                $nombre_cliente = trim($_POST['nombre_cliente'] ?? '');
                $tipo_pedido = $_POST['tipo_pedido'] ?? 'para_llevar';
                $numero_mesa = trim($_POST['numero_mesa'] ?? '');

                $productos_ids = $_POST['id_producto'] ?? [];
                $cantidades = $_POST['cantidad'] ?? [];

                if (!in_array($tipo_pedido, ['mesa', 'para_llevar'], true)) {
                    throw new Exception('Selecciona un tipo de pedido válido.');
                }

                if ($tipo_pedido === 'mesa' && $numero_mesa === '') {
                    throw new Exception('Debes indicar el número de mesa.');
                }

                if (strlen($nombre_cliente) > 100) {
                    throw new Exception('El nombre del cliente es demasiado largo.');
                }

                if ($tipo_pedido === 'para_llevar') {
                    $numero_mesa = null;
                }

                if (
                    !is_array($productos_ids) ||
                    !is_array($cantidades) ||
                    count($productos_ids) === 0 ||
                    count($productos_ids) !== count($cantidades)
                ) {
                    throw new Exception('Agrega al menos un producto al pedido.');
                }

                // Agrupar productos repetidos y validar cantidades.
                $carrito = [];

                foreach ($productos_ids as $indice => $id_producto) {

                    $id_producto = filter_var(
                        $id_producto,
                        FILTER_VALIDATE_INT
                    );

                    $cantidad = filter_var(
                        $cantidades[$indice] ?? null,
                        FILTER_VALIDATE_INT
                    );

                    if (!$id_producto || !$cantidad || $cantidad < 1 || $cantidad > 1000) {
                        throw new Exception('Revisa los productos y sus cantidades.');
                    }

                    if (!isset($carrito[$id_producto])) {
                        $carrito[$id_producto] = 0;
                    }

                    $carrito[$id_producto] += $cantidad;

                    if ($carrito[$id_producto] > 1000) {
                        throw new Exception('La cantidad de un producto no puede superar 1000 unidades.');
                    }
                }

                // Consultar precios reales de los productos activos.
                $conexion->beginTransaction();

                $consulta_producto = $conexion->prepare(
                    "SELECT id_producto, nombre, precio
                     FROM productos
                     WHERE id_producto = ? AND estado = 'activo'"
                );

                $detalles = [];
                $total = 0;

                foreach ($carrito as $id_producto => $cantidad) {

                    $consulta_producto->execute([$id_producto]);
                    $producto = $consulta_producto->fetch();

                    if (!$producto) {
                        throw new Exception('Uno de los productos no existe o está inactivo.');
                    }

                    $precio = (float) $producto['precio'];
                    $subtotal = round($precio * $cantidad, 2);

                    $total = round($total + $subtotal, 2);

                    $detalles[] = [
                        'id_producto' => (int) $producto['id_producto'],
                        'cantidad' => $cantidad,
                        'precio' => $precio,
                        'subtotal' => $subtotal
                    ];
                }

                // Crear el pedido.
                $consulta_pedido = $conexion->prepare(
                    "INSERT INTO pedidos
                     (id_usuario, nombre_cliente, tipo_pedido, numero_mesa, total, estado)
                     VALUES (?, ?, ?, ?, ?, 'pendiente')"
                );

                $consulta_pedido->execute([
                    $mi_id,
                    $nombre_cliente !== '' ? $nombre_cliente : null,
                    $tipo_pedido,
                    $numero_mesa,
                    $total
                ]);

                $id_pedido = (int) $conexion->lastInsertId();

                // Registrar los productos del pedido.
                $consulta_detalle = $conexion->prepare(
                    "INSERT INTO detalle_pedido
                     (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
                     VALUES (?, ?, ?, ?, ?)"
                );

                foreach ($detalles as $detalle) {

                    $consulta_detalle->execute([
                        $id_pedido,
                        $detalle['id_producto'],
                        $detalle['cantidad'],
                        $detalle['precio'],
                        $detalle['subtotal']
                    ]);
                }

                $conexion->commit();

                mostrarMensaje(
                    '¡Pedido #' . $id_pedido . ' registrado correctamente! Total: Bs ' .
                    number_format($total, 2, ',', '.')
                );

            } catch (Throwable $e) {

                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }

                if ($e instanceof PDOException) {
                    mostrarMensaje('No se pudo registrar el pedido. Revisa la base de datos.', 'error');
                } else {
                    mostrarMensaje($e->getMessage(), 'error');
                }
            }
        }

        // CAMBIAR ESTADO DEL PEDIDO.
        elseif ($accion === 'cambiar_estado') {

            $id_pedido = filter_var(
                $_POST['id_pedido'] ?? null,
                FILTER_VALIDATE_INT
            );

            $nuevo_estado = $_POST['estado'] ?? '';

            $estados_validos = [
                'pendiente',
                'preparando',
                'listo',
                'entregado',
                'cancelado'
            ];

            if (!$id_pedido || !in_array($nuevo_estado, $estados_validos, true)) {

                mostrarMensaje('Los datos del cambio de estado no son válidos.', 'error');

            } else {

                try {

                    $consulta = $conexion->prepare(
                        "UPDATE pedidos SET estado = ? WHERE id_pedido = ?"
                    );

                    $consulta->execute([$nuevo_estado, $id_pedido]);

                    mostrarMensaje('El estado del pedido fue actualizado.');

                } catch (PDOException $e) {

                    mostrarMensaje('No se pudo actualizar el estado del pedido.', 'error');
                }
            }
        }
    }
}

// Obtener productos activos.
$consulta = $conexion->query(
    "SELECT id_producto, nombre, precio
     FROM productos
     WHERE estado = 'activo'
     ORDER BY nombre ASC"
);

$productos = $consulta->fetchAll();

// Obtener pedidos registrados y sus productos.
$consulta = $conexion->query(
    "SELECT
        p.id_pedido,
        p.nombre_cliente,
        p.tipo_pedido,
        p.numero_mesa,
        p.fecha_pedido,
        p.total,
        p.estado,
        u.nombre AS nombre_usuario,
        COUNT(d.id_detalle) AS cantidad_productos,
        GROUP_CONCAT(
            CONCAT(pr.nombre, ' (', d.cantidad, ')')
            ORDER BY pr.nombre
            SEPARATOR ', '
        ) AS productos_pedido
     FROM pedidos p
     INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
     LEFT JOIN detalle_pedido d ON p.id_pedido = d.id_pedido
     LEFT JOIN productos pr ON d.id_producto = pr.id_producto
     GROUP BY
        p.id_pedido,
        p.nombre_cliente,
        p.tipo_pedido,
        p.numero_mesa,
        p.fecha_pedido,
        p.total,
        p.estado,
        u.nombre
     ORDER BY p.id_pedido DESC"
);

$pedidos = $consulta->fetchAll();

// Contadores.
$total_pedidos = count($pedidos);
$pendientes = 0;
$preparando = 0;
$listos = 0;
$entregados = 0;

foreach ($pedidos as $pedido) {

    if ($pedido['estado'] === 'pendiente') {
        $pendientes++;
    } elseif ($pedido['estado'] === 'preparando') {
        $preparando++;
    } elseif ($pedido['estado'] === 'listo') {
        $listos++;
    } elseif ($pedido['estado'] === 'entregado') {
        $entregados++;
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pedidos | CAFETIGO</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f5f0e8;
            color: #3e2b20;
            padding: 30px;
        }

        .contenedor {
            max-width: 1250px;
            margin: auto;
        }

        .encabezado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        h1 {
            color: #563923;
            margin-bottom: 7px;
        }

        .subtitulo {
            color: #806b5a;
        }

        .volver {
            background: #6b4226;
            color: white;
            padding: 11px 18px;
            border-radius: 7px;
            text-decoration: none;
        }

        .tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .tarjeta {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #0000000d;
        }

        .tarjeta p {
            color: #806b5a;
            margin-bottom: 8px;
        }

        .tarjeta h2 {
            color: #6b4226;
        }

        .panel {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px #0000000d;
        }

        .panel h2 {
            margin-bottom: 20px;
            color: #563923;
        }

        .formulario {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .campo label {
            font-weight: bold;
        }

        .campo input,
        .campo select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d8c8b8;
            border-radius: 6px;
            font-size: 15px;
        }

        .ancho-completo {
            grid-column: 1 / -1;
        }

        .productos-titulo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }

        .fila-producto {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 10px;
            margin-bottom: 12px;
            align-items: end;
        }

        .fila-producto button {
            height: 42px;
        }

        .total {
            background: #f5f0e8;
            border-radius: 8px;
            padding: 18px;
            text-align: right;
            font-size: 21px;
            font-weight: bold;
            color: #6b4226;
        }

        button {
            border: none;
            border-radius: 6px;
            padding: 10px 14px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-principal {
            background: #6b4226;
            color: white;
            padding: 13px 20px;
        }

        .btn-agregar {
            background: #d9a441;
            color: #2f241b;
        }

        .btn-quitar {
            background: #a83232;
            color: white;
        }

        .mensaje {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .exito {
            background: #e1f2e4;
            color: #245b30;
        }

        .error {
            background: #f8e1e1;
            color: #8a2424;
        }

        .tabla-contenedor {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #eee5dc;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #6b4226;
            color: white;
        }

        tr:hover td {
            background: #faf7f2;
        }

        .estado {
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .pendiente {
            background: #fff0cf;
            color: #805500;
        }

        .preparando {
            background: #dceaff;
            color: #24528a;
        }

        .listo {
            background: #e8defa;
            color: #57358a;
        }

        .entregado {
            background: #e1f2e4;
            color: #245b30;
        }

        .cancelado {
            background: #f8e1e1;
            color: #8a2424;
        }

        .estado-form {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .estado-form select {
            padding: 8px;
            border: 1px solid #d8c8b8;
            border-radius: 5px;
        }

        .btn-guardar {
            background: #6b4226;
            color: white;
        }

        .ayuda {
            color: #806b5a;
            font-size: 13px;
        }

        @media (max-width: 650px) {
            body {
                padding: 15px;
            }

            .formulario {
                grid-template-columns: 1fr;
            }

            .ancho-completo {
                grid-column: auto;
            }

            .fila-producto {
                grid-template-columns: 1fr 1fr;
            }

            .fila-producto .campo:first-child {
                grid-column: 1 / -1;
            }

            .total {
                font-size: 18px;
            }
        }
    </style>
</head>

<body>

<div class="contenedor">

    <div class="encabezado">
        <div>
            <h1>Gestión de pedidos</h1>
            <p class="subtitulo">Registra y administra los pedidos de CAFETIGO.</p>
        </div>

        <a href="dashboard.php" class="volver">Volver al panel</a>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="mensaje <?= limpiarPedido($tipo_mensaje) ?>">
            <?= limpiarPedido($mensaje) ?>
        </div>
    <?php endif; ?>

    <div class="tarjetas">

        <div class="tarjeta">
            <p>Total de pedidos</p>
            <h2><?= $total_pedidos ?></h2>
        </div>

        <div class="tarjeta">
            <p>Pendientes</p>
            <h2><?= $pendientes ?></h2>
        </div>

        <div class="tarjeta">
            <p>Preparando</p>
            <h2><?= $preparando ?></h2>
        </div>

        <div class="tarjeta">
            <p>Listos</p>
            <h2><?= $listos ?></h2>
        </div>

        <div class="tarjeta">
            <p>Entregados</p>
            <h2><?= $entregados ?></h2>
        </div>

    </div>

    <div class="panel">

        <h2>Registrar nuevo pedido</h2>

        <?php if (count($productos) > 0): ?>

            <form method="POST" class="formulario" id="formPedido">

                <input type="hidden" name="csrf_token"
                       value="<?= limpiarPedido($_SESSION['csrf_pedidos']) ?>">

                <input type="hidden" name="accion" value="registrar">

                <div class="campo">
                    <label>Nombre del cliente</label>
                    <input type="text" name="nombre_cliente"
                           maxlength="100"
                           placeholder="Nombre del cliente (opcional)">
                </div>

                <div class="campo">
                    <label>Tipo de pedido</label>
                    <select name="tipo_pedido" id="tipo_pedido" required>
                        <option value="para_llevar">Para llevar</option>
                        <option value="mesa">Para mesa</option>
                    </select>
                </div>

                <div class="campo ancho-completo" id="campo_mesa" style="display:none;">
                    <label>Número de mesa</label>
                    <input type="text" name="numero_mesa"
                           id="numero_mesa" maxlength="20"
                           placeholder="Ej. Mesa 1">
                </div>

                <div class="ancho-completo">

                    <div class="productos-titulo">
                        <h3>Productos del pedido</h3>
                        <button type="button" class="btn-agregar"
                                onclick="agregarProducto()">
                            + Agregar producto
                        </button>
                    </div>

                    <div id="lista_productos">

                        <div class="fila-producto">

                            <div class="campo">
                                <label>Producto</label>

                                <select name="id_producto[]" class="producto-select"
                                        onchange="calcularTotal()" required>

                                    <option value="">Seleccionar producto</option>

                                    <?php foreach ($productos as $producto): ?>
                                        <option
                                            value="<?= (int) $producto['id_producto'] ?>"
                                            data-precio="<?= limpiarPedido($producto['precio']) ?>">
                                            <?= limpiarPedido($producto['nombre']) ?>
                                            - Bs <?= number_format((float) $producto['precio'], 2, ',', '.') ?>
                                        </option>
                                    <?php endforeach; ?>

                                </select>
                            </div>

                            <div class="campo">
                                <label>Cantidad</label>
                                <input type="number" name="cantidad[]"
                                       class="cantidad-input"
                                       min="1" max="1000" value="1"
                                       onchange="calcularTotal()"
                                       oninput="calcularTotal()" required>
                            </div>

                            <button type="button" class="btn-quitar"
                                    onclick="quitarProducto(this)" disabled>
                                Quitar
                            </button>

                        </div>

                    </div>

                </div>

                <div class="total ancho-completo">
                    Total: Bs <span id="total_pedido">0,00</span>
                </div>

                <div class="ancho-completo">
                    <p class="ayuda">
                        Los precios se obtienen de los productos registrados en CAFETIGO.
                    </p>
                </div>

                <div class="ancho-completo">
                    <button type="submit" class="btn-principal">
                        Registrar pedido
                    </button>
                </div>

            </form>

        <?php else: ?>

            <p class="ayuda">
                No hay productos activos. Primero registra o activa productos en el módulo Productos.
            </p>

        <?php endif; ?>

    </div>

    <div class="panel">

        <h2>Pedidos registrados</h2>

        <div class="tabla-contenedor">

            <table>

                <thead>
                    <tr>
                        <th>N.º</th>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Productos</th>
                        <th>Total</th>
                        <th>Fecha</th>
                        <th>Atendido por</th>
                        <th>Estado</th>
                        <th>Actualizar</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($pedidos) > 0): ?>

                    <?php foreach ($pedidos as $pedido): ?>

                        <tr>

                            <td>#<?= (int) $pedido['id_pedido'] ?></td>

                            <td>
                                <?= limpiarPedido($pedido['nombre_cliente'] ?: 'Sin nombre') ?>
                            </td>

                            <td>
                                <?php if ($pedido['tipo_pedido'] === 'mesa'): ?>
                                    Mesa <?= limpiarPedido($pedido['numero_mesa']) ?>
                                <?php else: ?>
                                    Para llevar
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= limpiarPedido($pedido['productos_pedido'] ?: 'Sin productos') ?>
                            </td>

                            <td>
                                Bs <?= number_format((float) $pedido['total'], 2, ',', '.') ?>
                            </td>

                            <td>
                                <?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?>
                            </td>

                            <td>
                                <?= limpiarPedido($pedido['nombre_usuario']) ?>
                            </td>

                            <td>
                                <span class="estado <?= limpiarPedido($pedido['estado']) ?>">
                                    <?= ucfirst(limpiarPedido($pedido['estado'])) ?>
                                </span>
                            </td>

                            <td>

                                <form method="POST" class="estado-form">

                                    <input type="hidden" name="csrf_token"
                                           value="<?= limpiarPedido($_SESSION['csrf_pedidos']) ?>">

                                    <input type="hidden" name="accion"
                                           value="cambiar_estado">

                                    <input type="hidden" name="id_pedido"
                                           value="<?= (int) $pedido['id_pedido'] ?>">

                                    <select name="estado">

                                        <?php
                                        $estados = [
                                            'pendiente' => 'Pendiente',
                                            'preparando' => 'Preparando',
                                            'listo' => 'Listo',
                                            'entregado' => 'Entregado',
                                            'cancelado' => 'Cancelado'
                                        ];
                                        ?>

                                        <?php foreach ($estados as $valor => $nombre): ?>
                                            <option value="<?= $valor ?>"
                                                <?= $pedido['estado'] === $valor ? 'selected' : '' ?>>
                                                <?= $nombre ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>

                                    <button type="submit" class="btn-guardar">
                                        Guardar
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9">Todavía no hay pedidos registrados.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<script>
    const tipoPedido = document.getElementById('tipo_pedido');
    const campoMesa = document.getElementById('campo_mesa');
    const numeroMesa = document.getElementById('numero_mesa');

    function actualizarTipoPedido() {
        if (!tipoPedido) return;

        if (tipoPedido.value === 'mesa') {
            campoMesa.style.display = 'flex';
            numeroMesa.required = true;
        } else {
            campoMesa.style.display = 'none';
            numeroMesa.required = false;
            numeroMesa.value = '';
        }
    }

    if (tipoPedido) {
        tipoPedido.addEventListener('change', actualizarTipoPedido);
        actualizarTipoPedido();
    }

    function agregarProducto() {

        const lista = document.getElementById('lista_productos');
        const primeraFila = lista.querySelector('.fila-producto');
        const nuevaFila = primeraFila.cloneNode(true);

        nuevaFila.querySelector('select').value = '';
        nuevaFila.querySelector('input').value = 1;

        const boton = nuevaFila.querySelector('button');
        boton.disabled = false;

        lista.appendChild(nuevaFila);

        actualizarBotones();
        calcularTotal();
    }

    function quitarProducto(boton) {

        const lista = document.getElementById('lista_productos');
        const filas = lista.querySelectorAll('.fila-producto');

        if (filas.length > 1) {
            boton.closest('.fila-producto').remove();
        }

        actualizarBotones();
        calcularTotal();
    }

    function actualizarBotones() {

        const filas = document.querySelectorAll('.fila-producto');

        filas.forEach(fila => {
            const boton = fila.querySelector('button');
            boton.disabled = filas.length === 1;
        });
    }

    function calcularTotal() {

        let total = 0;

        document.querySelectorAll('.fila-producto').forEach(fila => {

            const select = fila.querySelector('select');
            const cantidad = fila.querySelector('input');

            const opcion = select.options[select.selectedIndex];

            const precio = parseFloat(opcion?.dataset.precio || 0);
            const unidades = parseInt(cantidad.value || 0);

            if (unidades > 0) {
                total += precio * unidades;
            }
        });

        document.getElementById('total_pedido').textContent =
            total.toLocaleString('es-BO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
    }

    actualizarBotones();
    calcularTotal();
</script>

</body>
</html>
