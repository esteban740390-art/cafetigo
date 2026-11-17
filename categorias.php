
<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: auth/login.php");
    exit;
}

if ($_SESSION['rol'] !== "administrador") {
    header("Location: dashboard.php");
    exit;
}

require_once "config/conexion.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensaje = "";
$tipoMensaje = "";
$categoriaEditar = null;

// REGISTRAR CATEGORÍA
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['registrar'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Solicitud no válida.");
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($nombre === "") {
        $mensaje = "Debes escribir el nombre de la categoría.";
        $tipoMensaje = "error";
    } else {
        $sql = "INSERT INTO categorias (nombre, descripcion, estado)
                VALUES (?, ?, 'activo')";

        $consulta = $conexion->prepare($sql);
        $consulta->execute([$nombre, $descripcion]);

        header("Location: categorias.php?mensaje=registrada");
        exit;
    }
}

// ACTUALIZAR CATEGORÍA
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['actualizar'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Solicitud no válida.");
    }

    $id = (int) ($_POST['id_categoria'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($id <= 0 || $nombre === "") {
        $mensaje = "Completa correctamente los datos.";
        $tipoMensaje = "error";
    } else {
        $sql = "UPDATE categorias
                SET nombre = ?, descripcion = ?
                WHERE id_categoria = ?";

        $consulta = $conexion->prepare($sql);
        $consulta->execute([$nombre, $descripcion, $id]);

        header("Location: categorias.php?mensaje=actualizada");
        exit;
    }
}

// ELIMINAR CATEGORÍA
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['eliminar'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Solicitud no válida.");
    }

    $id = (int) ($_POST['id_categoria'] ?? 0);

    try {
        $consulta = $conexion->prepare(
            "DELETE FROM categorias WHERE id_categoria = ?"
        );

        $consulta->execute([$id]);

        header("Location: categorias.php?mensaje=eliminada");
        exit;

    } catch (PDOException $e) {
        $mensaje = "No se puede eliminar esta categoría porque tiene productos asociados.";
        $tipoMensaje = "error";
    }
}

// CARGAR CATEGORÍA PARA EDITAR
if (isset($_GET['editar'])) {

    $id = (int) $_GET['editar'];

    $consulta = $conexion->prepare(
        "SELECT * FROM categorias WHERE id_categoria = ?"
    );

    $consulta->execute([$id]);
    $categoriaEditar = $consulta->fetch();

    if (!$categoriaEditar) {
        $mensaje = "La categoría que buscas no existe.";
        $tipoMensaje = "error";
    }
}

// MENSAJES
if (isset($_GET['mensaje'])) {

    $mensajes = [
        "registrada" => "Categoría registrada correctamente.",
        "actualizada" => "Categoría actualizada correctamente.",
        "eliminada" => "Categoría eliminada correctamente."
    ];

    if (isset($mensajes[$_GET['mensaje']])) {
        $mensaje = $mensajes[$_GET['mensaje']];
        $tipoMensaje = "exito";
    }
}

// CONSULTAR CATEGORÍAS
$consulta = $conexion->query(
    "SELECT * FROM categorias ORDER BY id_categoria DESC"
);

$categorias = $consulta->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CAFETIGO - Categorías</title>

    <link rel="stylesheet" href="css/estilos.css">

    <style>
        .encabezado-pagina {
            margin-bottom: 25px;
        }

        .panel {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .panel h2 {
            margin-bottom: 20px;
        }

        .formulario {
            display: grid;
            gap: 15px;
        }

        .formulario label {
            font-weight: bold;
        }

        .formulario input,
        .formulario textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d6c8ba;
            border-radius: 7px;
            font-size: 15px;
            margin-top: 7px;
        }

        .formulario textarea {
            resize: vertical;
            min-height: 80px;
        }

        .boton {
            background: #795548;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
        }

        .boton:hover {
            background: #5d4037;
        }

        .tabla-contenedor {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f5f0e8;
            color: #4e342e;
        }

        .estado {
            color: #287a42;
            font-weight: bold;
        }

        .boton-eliminar {
            background: #b33a3a;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
        }

        .boton-editar {
            display: inline-block;
            background: #795548;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            margin-bottom: 5px;
        }

        .mensaje {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .exito {
            background: #e2f3e5;
            color: #246b35;
        }

        .error {
            background: #fbe4e4;
            color: #9b2929;
        }

        .volver {
            display: inline-block;
            margin-bottom: 20px;
            color: #795548;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="contenedor">

    <aside class="sidebar">

        <div class="logo">☕ CAFETIGO</div>

        <h3>Menú principal</h3>

        <a href="dashboard.php">🏠 Inicio</a>
        <a href="#">🛒 Pedidos</a>
        <a href="#">👥 Usuarios</a>
        <a href="categorias.php" class="activo">📂 Categorías</a>
        <a href="#">🍰 Productos</a>
        <a href="#">💰 Ventas</a>
        <a href="#">📊 Reportes</a>

    </aside>

    <main class="contenido">

        <header class="encabezado">

            <h2>Gestión de categorías</h2>

            <div class="usuario">
                👤 <?php echo htmlspecialchars($_SESSION['nombre']); ?>
            </div>

            <form action="auth/logout.php" method="POST">

                <input type="hidden" name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <button type="submit" class="boton-salir">
                    Cerrar sesión
                </button>

            </form>

        </header>

        <a href="dashboard.php" class="volver">
            ← Volver al panel principal
        </a>

        <section class="encabezado-pagina">

            <h1>Administración de categorías</h1>

            <p>
                Registra y organiza las categorías de productos de tu cafetería.
            </p>

        </section>

        <?php if ($mensaje !== ""): ?>

            <div class="mensaje <?php echo $tipoMensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>

        <?php endif; ?>

        <!-- FORMULARIO -->

        <section class="panel">

            <h2>
                <?php echo $categoriaEditar ? "✏️ Editar categoría" : "➕ Registrar nueva categoría"; ?>
            </h2>

            <form method="POST" class="formulario">

                <input type="hidden" name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <?php if ($categoriaEditar): ?>

                    <input type="hidden" name="id_categoria"
                        value="<?php echo $categoriaEditar['id_categoria']; ?>">

                <?php endif; ?>

                <label>
                    Nombre de la categoría

                    <input
                        type="text"
                        name="nombre"
                        maxlength="100"
                        placeholder="Ejemplo: Bebidas"
                        value="<?php echo htmlspecialchars($categoriaEditar['nombre'] ?? ''); ?>"
                        required
                    >
                </label>

                <label>
                    Descripción

                    <textarea
                        name="descripcion"
                        placeholder="Describe esta categoría..."
                    ><?php echo htmlspecialchars($categoriaEditar['descripcion'] ?? ''); ?></textarea>
                </label>

                <?php if ($categoriaEditar): ?>

                    <button type="submit" name="actualizar" class="boton">
                        Guardar cambios
                    </button>

                    <a href="categorias.php" class="volver">
                        Cancelar edición
                    </a>

                <?php else: ?>

                    <button type="submit" name="registrar" class="boton">
                        Guardar categoría
                    </button>

                <?php endif; ?>

            </form>

        </section>

        <!-- TABLA DE CATEGORÍAS -->

        <section class="panel">

            <h2>📂 Categorías registradas</h2>

            <div class="tabla-contenedor">

                <table>

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($categorias) > 0): ?>

                            <?php foreach ($categorias as $categoria): ?>

                                <tr>

                                    <td>
                                        <?php echo $categoria['id_categoria']; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($categoria['nombre']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($categoria['descripcion'] ?? ''); ?>
                                    </td>

                                    <td class="estado">
                                        <?php echo htmlspecialchars($categoria['estado']); ?>
                                    </td>

                                    <td>

                                        <a
                                            href="categorias.php?editar=<?php echo $categoria['id_categoria']; ?>"
                                            class="boton-editar">
                                            Editar
                                        </a>

                                        <form method="POST"
                                            onsubmit="return confirm('¿Estás seguro de eliminar esta categoría?');">

                                            <input type="hidden" name="csrf_token"
                                                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                            <input type="hidden" name="id_categoria"
                                                value="<?php echo $categoria['id_categoria']; ?>">

                                            <button type="submit"
                                                name="eliminar"
                                                class="boton-eliminar">
                                                Eliminar
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5">
                                    Todavía no hay categorías registradas.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

</body>
</html>
