<?php
// ============================================================================
// 1. CONTROLADOR (LÓGICA Y BASE DE DATOS)
// Aquí no hay HTML. Solo PHP, validaciones y consultas a la BD.
// ============================================================================

require_once "includes/auth_check.php";

// Verificar que sea administrador (auth_check ya validó que esté logueado)
if (!$esAdmin) {
    header("Location: dashboard.php");
    exit;
}

$tituloPagina = "Gestión de Productos";
$mensaje = "";
$tipoMensaje = "";
$productoEditar = null;
$carpetaImagenes = __DIR__ . "/uploads/productos/";

// --- FUNCIÓN DE SUBIDA DE IMÁGENES ---
function guardarImagen($archivo, $carpeta) {
    if (!isset($archivo) || $archivo['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($archivo['error'] !== UPLOAD_ERR_OK) throw new Exception("Error al subir la fotografía.");
    if ($archivo['size'] > 2 * 1024 * 1024) throw new Exception("La fotografía no debe superar los 2 MB.");

    $tiposPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $tipoReal = mime_content_type($archivo['tmp_name']);

    if (!isset($tiposPermitidos[$tipoReal])) {
        throw new Exception("Solo se permiten imágenes JPG, PNG o WEBP.");
    }

    $extension = $tiposPermitidos[$tipoReal];
    $nombreArchivo = bin2hex(random_bytes(16)) . "." . $extension;

    if (!move_uploaded_file($archivo['tmp_name'], $carpeta . $nombreArchivo)) {
        throw new Exception("No se pudo guardar la fotografía.");
    }
    return $nombreArchivo;
}

// --- OBTENER CATEGORÍAS (Necesario para el formulario) ---
$stmtCat = $conexion->prepare("SELECT id_categoria, nombre FROM categorias WHERE estado = 'activo' ORDER BY nombre ASC");
$stmtCat->execute();
$categorias = $stmtCat->fetchAll();

// --- PROCESAR SOLICITUDES POST ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Validación CSRF centralizada para todos los POST
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Solicitud no válida (CSRF).");
    }

    try {
        if (isset($_POST['registrar'])) {
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $precio = $_POST['precio'] ?? '';
            $idCategoria = (int)($_POST['id_categoria'] ?? 0);

            if ($nombre === "" || !is_numeric($precio) || (float)$precio <= 0 || $idCategoria <= 0) {
                throw new Exception("Completa correctamente todos los campos obligatorios.");
            }

            $imagen = guardarImagen($_FILES['imagen'] ?? null, $carpetaImagenes);
            $sql = "INSERT INTO productos (id_categoria, nombre, descripcion, precio, imagen, estado) VALUES (?, ?, ?, ?, ?, 'activo')";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([$idCategoria, $nombre, $descripcion, $precio, $imagen]);
            
            header("Location: productos.php?mensaje=registrado");
            exit;

        } elseif (isset($_POST['actualizar'])) {
            $id = (int)($_POST['id_producto'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $precio = $_POST['precio'] ?? '';
            $idCategoria = (int)($_POST['id_categoria'] ?? 0);
            $estado = $_POST['estado'] ?? '';

            if ($id <= 0 || $nombre === "" || !is_numeric($precio) || (float)$precio <= 0 || $idCategoria <= 0 || !in_array($estado, ['activo', 'inactivo'], true)) {
                throw new Exception("Completa correctamente todos los campos.");
            }

            $stmtPrev = $conexion->prepare("SELECT imagen FROM productos WHERE id_producto = ?");
            $stmtPrev->execute([$id]);
            $productoAnterior = $stmtPrev->fetch();
            if (!$productoAnterior) throw new Exception("El producto no existe.");

            $imagen = $productoAnterior['imagen'];
            $nuevaImagen = guardarImagen($_FILES['imagen'] ?? null, $carpetaImagenes);
            if ($nuevaImagen !== null) {
                $imagen = $nuevaImagen;
                // Eliminar imagen anterior si existe
                if (!empty($productoAnterior['imagen']) && is_file($carpetaImagenes . basename($productoAnterior['imagen']))) {
                    unlink($carpetaImagenes . basename($productoAnterior['imagen']));
                }
            }

            $sql = "UPDATE productos SET id_categoria = ?, nombre = ?, descripcion = ?, precio = ?, imagen = ?, estado = ? WHERE id_producto = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([$idCategoria, $nombre, $descripcion, $precio, $imagen, $estado, $id]);
            
            header("Location: productos.php?mensaje=actualizado");
            exit;

        } elseif (isset($_POST['cambiar_estado'])) {
            $id = (int)($_POST['id_producto'] ?? 0);
            $estado = $_POST['nuevo_estado'] ?? '';
            if ($id > 0 && in_array($estado, ['activo', 'inactivo'], true)) {
                $stmt = $conexion->prepare("UPDATE productos SET estado = ? WHERE id_producto = ?");
                $stmt->execute([$estado, $id]);
                header("Location: productos.php?mensaje=estado");
                exit;
            }

        } elseif (isset($_POST['eliminar'])) {
            $id = (int)($_POST['id_producto'] ?? 0);
            $stmtPrev = $conexion->prepare("SELECT imagen FROM productos WHERE id_producto = ?");
            $stmtPrev->execute([$id]);
            $producto = $stmtPrev->fetch();

            if ($producto) {
                $stmtDel = $conexion->prepare("DELETE FROM productos WHERE id_producto = ?");
                $stmtDel->execute([$id]);
                
                if (!empty($producto['imagen']) && is_file($carpetaImagenes . basename($producto['imagen']))) {
                    unlink($carpetaImagenes . basename($producto['imagen']));
                }
            }
            header("Location: productos.php?mensaje=eliminado");
            exit;
        }
    } catch (PDOException $e) {
        $mensaje = "Error de base de datos: No se puede eliminar este producto porque está asociado a otros registros.";
        $tipoMensaje = "error";
        error_log("Error Productos: " . $e->getMessage());
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipoMensaje = "error";
    }
}

// --- PROCESAR SOLICITUDES GET ---
if (isset($_GET['editar'])) {
    $stmtEdit = $conexion->prepare("SELECT * FROM productos WHERE id_producto = ?");
    $stmtEdit->execute([(int)$_GET['editar']]);
    $productoEditar = $stmtEdit->fetch();
    if (!$productoEditar) {
        $mensaje = "El producto no existe.";
        $tipoMensaje = "error";
    }
}

if (isset($_GET['mensaje']) && empty($mensaje)) {
    $mensajes = [
        'registrado' => 'Producto registrado correctamente.',
        'actualizado' => 'Producto actualizado correctamente.',
        'eliminado' => 'Producto eliminado correctamente.',
        'estado' => 'Estado del producto actualizado.'
    ];
    if (isset($mensajes[$_GET['mensaje']])) {
        $mensaje = $mensajes[$_GET['mensaje']];
        $tipoMensaje = "exito";
    }
}

// --- OBTENER LISTA DE PRODUCTOS ---
$stmtProd = $conexion->query("SELECT p.*, c.nombre AS categoria FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria ORDER BY p.id_producto DESC");
$productos = $stmtProd->fetchAll();

// ============================================================================
// 2. VISTA (PRESENTACIÓN HTML)
// Aquí solo mostramos datos. No hay consultas SQL ni lógica compleja.
// ============================================================================
?>
<?php require_once "includes/header.php"; ?>
<?php require_once "includes/sidebar.php"; ?>

<a href="dashboard.php" class="volver">← Volver al panel principal</a>

<section style="margin-bottom: 25px;">
    <h1>Administración de productos</h1>
    <p>Registra y administra los alimentos y bebidas de tu cafetería.</p>
</section>

<?php if ($mensaje !== ""): ?>
    <div class="mensaje <?= $tipoMensaje ?>">
        <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<!-- FORMULARIO -->
<section class="panel">
    <h2><?= $productoEditar ? "✏️ Editar producto" : "➕ Registrar nuevo producto" ?></h2>

    <?php if (count($categorias) === 0): ?>
        <p>⚠️ Primero debes registrar al menos una categoría activa en el módulo de Categorías.</p>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data" class="formulario">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <?php if ($productoEditar): ?>
                <input type="hidden" name="id_producto" value="<?= $productoEditar['id_producto'] ?>">
            <?php endif; ?>

            <label>Nombre del producto
                <input type="text" name="nombre" maxlength="100" placeholder="Ejemplo: Café con leche" 
                       value="<?= htmlspecialchars($productoEditar['nombre'] ?? '') ?>" required>
            </label>

            <label>Categoría
                <select name="id_categoria" required>
                    <option value="">Selecciona una categoría</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?= $categoria['id_categoria'] ?>" 
                            <?= ($productoEditar && $productoEditar['id_categoria'] == $categoria['id_categoria']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($categoria['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Descripción
                <textarea name="descripcion" placeholder="Describe el producto..."><?= htmlspecialchars($productoEditar['descripcion'] ?? '') ?></textarea>
            </label>

            <label>Precio (Bs)
                <input type="number" name="precio" min="0.01" step="0.01" placeholder="Ejemplo: 15.00" 
                       value="<?= htmlspecialchars($productoEditar['precio'] ?? '') ?>" required>
            </label>

            <label>Fotografía del producto
                <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" 
                       <?= $productoEditar ? '' : 'required' ?>>
                <p class="ayuda">Formatos: JPG, PNG, WEBP. Máx: 2 MB. <?= $productoEditar ? 'Dejar vacío para conservar la actual.' : '' ?></p>
            </label>

            <?php if ($productoEditar): ?>
                <?php if (!empty($productoEditar['imagen'])): ?>
                    <div>
                        <p>Fotografía actual:</p>
                        <img src="uploads/productos/<?= rawurlencode(basename($productoEditar['imagen'])) ?>" class="foto-producto" alt="Foto actual">
                    </div>
                <?php endif; ?>

                <label>Estado del producto
                    <select name="estado" required>
                        <option value="activo" <?= $productoEditar['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= $productoEditar['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </label>

                <button type="submit" name="actualizar" class="boton">Guardar cambios</button>
                <a href="productos.php" class="volver">Cancelar edición</a>
            <?php else: ?>
                <button type="submit" name="registrar" class="boton">Guardar producto</button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</section>

<!-- LISTADO DE PRODUCTOS -->
<section class="panel">
    <h2>🍰 Productos registrados</h2>
    <div class="tabla-contenedor">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Fotografía</th><th>Producto</th><th>Categoría</th><th>Precio</th><th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($productos) > 0): ?>
                    <?php foreach ($productos as $producto): ?>
                        <tr>
                            <td><?= $producto['id_producto'] ?></td>
                            <td>
                                <?php if (!empty($producto['imagen'])): ?>
                                    <img src="uploads/productos/<?= rawurlencode(basename($producto['imagen'])) ?>" class="foto-producto" alt="Producto">
                                <?php else: ?>
                                    <div class="sin-foto">🍰</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($producto['nombre']) ?></strong><br>
                                <small><?= htmlspecialchars($producto['descripcion'] ?? '') ?></small>
                            </td>
                            <td><?= htmlspecialchars($producto['categoria'] ?? 'Sin categoría') ?></td>
                            <td>Bs <?= number_format((float)$producto['precio'], 2) ?></td>
                            <td class="<?= $producto['estado'] === 'activo' ? 'estado-activo' : 'estado-inactivo' ?>">
                                <?= htmlspecialchars($producto['estado']) ?>
                            </td>
                            <td>
                                <a href="productos.php?editar=<?= $producto['id_producto'] ?>" class="boton-editar">Editar</a>
                                
                                <form method="POST" style="margin-bottom: 5px;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="id_producto" value="<?= $producto['id_producto'] ?>">
                                    <input type="hidden" name="nuevo_estado" value="<?= $producto['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                                    <button type="submit" name="cambiar_estado" class="boton-estado">
                                        <?= $producto['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>

                                <form method="POST" onsubmit="return confirm('¿Estás seguro de eliminar este producto?');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="id_producto" value="<?= $producto['id_producto'] ?>">
                                    <button type="submit" name="eliminar" class="boton-eliminar">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7">Todavía no hay productos registrados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once "includes/footer.php"; ?>

