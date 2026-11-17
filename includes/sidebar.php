    <!-- MENÚ LATERAL -->
    <aside class="sidebar">
        <div class="logo">☕ CAFETIGO</div>
        <h3>Menú principal</h3>

        <?php 
        // Esto detecta en qué archivo estamos para poner la clase "activo"
        $pagina_actual = basename($_SERVER['PHP_SELF']); 
        ?>

        <a href="dashboard.php" class="<?= $pagina_actual === 'dashboard.php' ? 'activo' : '' ?>">🏠 Inicio</a>
        <a href="pedidos.php"  class="<?= $pagina_actual === 'pedidos.php' ? 'activo' : '' ?>">🛒 Pedidos</a>

        <?php if ($esAdmin): ?>
            <a href="usuarios.php"     class="<?= $pagina_actual === 'usuarios.php' ? 'activo' : '' ?>">👥 Usuarios</a>
            <a href="categorias.php"   class="<?= $pagina_actual === 'categorias.php' ? 'activo' : '' ?>">📂 Categorías</a>
            <a href="productos.php"    class="<?= $pagina_actual === 'productos.php' ? 'activo' : '' ?>">🍰 Productos</a>
            <a href="ventas.php"       class="<?= $pagina_actual === 'ventas.php' ? 'activo' : '' ?>">💰 Ventas</a>
            <a href="comprobantes.php" class="<?= $pagina_actual === 'comprobantes.php' ? 'activo' : '' ?>">🧾 Comprobantes</a>
            <a href="reportes.php"     class="<?= $pagina_actual === 'reportes.php' ? 'activo' : '' ?>">📊 Reportes</a>
        <?php endif; ?>
    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="contenido">
        <header class="encabezado">
            <h2><?= $tituloPagina ?? 'Panel principal' ?></h2>

            <div class="usuario">
                👤 <?= $nombre ?> <small>(<?= $rol ?>)</small>
            </div>

            <form action="auth/logout.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" class="boton-salir">Cerrar sesión</button>
            </form>
        </header>