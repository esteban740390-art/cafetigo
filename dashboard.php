<?php
// 1. Incluimos la seguridad y la conexión
require_once "includes/auth_check.php";

// 2. Definimos el título de esta página específica
$tituloPagina = "Panel principal";

// 3. Lógica específica del dashboard (solo para admins)
$totalProductos = 0;
$totalPedidos = 0;
$totalVentas = 0;

if ($esAdmin) {
    try {
        $totalProductos = $conexion->query("SELECT COUNT(*) FROM productos")->fetchColumn();
        $totalPedidos   = $conexion->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
        $totalVentas    = $conexion->query("SELECT COUNT(*) FROM ventas")->fetchColumn();
    } catch (PDOException $e) {
        // Si hay un error, lo registramos pero no rompemos la página
        error_log("Error en dashboard: " . $e->getMessage());
    }
}
?>

<!-- 4. Incluimos la estructura visual -->
<?php require_once "includes/header.php"; ?>
<?php require_once "includes/sidebar.php"; ?>

<!-- 5. Contenido único de esta página -->
<section class="bienvenida">
    <h1>¡Bienvenido a CAFETIGO!</h1>
    <p>Sistema web de pedidos y atención para cafeterías. Administra las operaciones desde un solo lugar.</p>
</section>

<?php if ($esAdmin): ?>
    <section class="tarjetas">
        <div class="tarjeta">
            <h3>Productos registrados</h3>
            <p><?= (int)$totalProductos ?></p>
        </div>
        <div class="tarjeta">
            <h3>Pedidos registrados</h3>
            <p><?= (int)$totalPedidos ?></p>
        </div>
        <div class="tarjeta">
            <h3>Ventas registradas</h3>
            <p><?= (int)$totalVentas ?></p>
        </div>
    </section>
<?php endif; ?>

<section class="modulos">
    <h2 style="margin-bottom: 20px;">Módulos del sistema</h2>
    
    <a href="pedidos.php" class="modulo">
        <h3>🛒 Pedidos</h3>
        <p>Registrar y administrar pedidos de los clientes.</p>
    </a>

    <?php if ($esAdmin): ?>
        <a href="productos.php" class="modulo">
            <h3>🍰 Productos</h3>
            <p>Registrar productos, precios y disponibilidad.</p>
        </a>
        <a href="ventas.php" class="modulo">
            <h3>💰 Ventas</h3>
            <p>Consultar y administrar las ventas.</p>
        </a>
        <!-- Puedes agregar el resto de los módulos aquí igual que antes -->
    <?php endif; ?>
</section>

<!-- 6. Cerramos la estructura visual -->
<?php require_once "includes/footer.php"; ?>