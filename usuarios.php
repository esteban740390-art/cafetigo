
<?php
session_start();

require_once __DIR__ . '/config/conexion.php';

// Solo los administradores pueden administrar usuarios.
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    header('Location: index.php');
    exit;
}

$mi_id = (int) $_SESSION['id_usuario'];

if (empty($_SESSION['csrf_usuarios'])) {
    $_SESSION['csrf_usuarios'] = bin2hex(random_bytes(32));
}

$mensaje = '';
$tipo_mensaje = '';
$editar = null;

function limpiar($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function mensaje($texto, $tipo = 'exito') {
    global $mensaje, $tipo_mensaje;
    $mensaje = $texto;
    $tipo_mensaje = $tipo;
}

// Procesar formularios.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_usuarios'], $token)) {
        mensaje('La solicitud no es válida. Recarga la página e inténtalo nuevamente.', 'error');
    } else {

        $accion = $_POST['accion'] ?? '';

        try {

            // REGISTRAR O EDITAR USUARIO.
            if ($accion === 'guardar') {

                $id = (int) ($_POST['id_usuario'] ?? 0);
                $nombre = trim($_POST['nombre'] ?? '');
                $usuario = trim($_POST['usuario'] ?? '');
                $password = $_POST['password'] ?? '';
                $rol = $_POST['rol'] ?? '';
                $estado = $_POST['estado'] ?? 'activo';

                if ($nombre === '' || $usuario === '') {
                    throw new Exception('Completa el nombre y el nombre de usuario.');
                }

                if (!in_array($rol, ['administrador', 'cajero'], true)) {
                    throw new Exception('Selecciona un rol válido.');
                }

                if (!in_array($estado, ['activo', 'inactivo'], true)) {
                    throw new Exception('Selecciona un estado válido.');
                }

                // Comprobar que el nombre de usuario no esté repetido.
                $sql = "SELECT id_usuario FROM usuarios WHERE usuario = ?";
                $parametros = [$usuario];

                if ($id > 0) {
                    $sql .= " AND id_usuario != ?";
                    $parametros[] = $id;
                }

                $consulta = $conexion->prepare($sql);
                $consulta->execute($parametros);

                if ($consulta->fetch()) {
                    throw new Exception('Ese nombre de usuario ya está registrado.');
                }

                if ($id === 0) {

                    if (strlen($password) < 8) {
                        throw new Exception('La contraseña debe tener al menos 8 caracteres.');
                    }

                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $consulta = $conexion->prepare(
                        "INSERT INTO usuarios (nombre, usuario, password, rol, estado)
                         VALUES (?, ?, ?, ?, ?)"
                    );

                    $consulta->execute([
                        $nombre,
                        $usuario,
                        $hash,
                        $rol,
                        $estado
                    ]);

                    mensaje('Usuario registrado correctamente.');

                } else {

                    $consulta = $conexion->prepare(
                        "SELECT id_usuario, rol, estado FROM usuarios WHERE id_usuario = ?"
                    );
                    $consulta->execute([$id]);
                    $usuario_actual = $consulta->fetch();

                    if (!$usuario_actual) {
                        throw new Exception('El usuario que intentas editar no existe.');
                    }

                    // No permitir desactivar la propia cuenta.
                    if ($id === $mi_id && $estado === 'inactivo') {
                        throw new Exception('No puedes desactivar tu propia cuenta.');
                    }

                    // No permitir quitar el último administrador activo.
                    if (
                        $usuario_actual['rol'] === 'administrador' &&
                        $usuario_actual['estado'] === 'activo' &&
                        ($rol !== 'administrador' || $estado !== 'activo')
                    ) {
                        $consulta = $conexion->prepare(
                            "SELECT COUNT(*) FROM usuarios
                             WHERE rol = 'administrador'
                             AND estado = 'activo'
                             AND id_usuario != ?"
                        );
                        $consulta->execute([$id]);

                        if ((int) $consulta->fetchColumn() === 0) {
                            throw new Exception('No puedes quitar el único administrador activo del sistema.');
                        }
                    }

                    if ($password !== '') {

                        if (strlen($password) < 8) {
                            throw new Exception('La nueva contraseña debe tener al menos 8 caracteres.');
                        }

                        $hash = password_hash($password, PASSWORD_DEFAULT);

                        $consulta = $conexion->prepare(
                            "UPDATE usuarios
                             SET nombre = ?, usuario = ?, password = ?, rol = ?, estado = ?
                             WHERE id_usuario = ?"
                        );

                        $consulta->execute([
                            $nombre,
                            $usuario,
                            $hash,
                            $rol,
                            $estado,
                            $id
                        ]);

                    } else {

                        $consulta = $conexion->prepare(
                            "UPDATE usuarios
                             SET nombre = ?, usuario = ?, rol = ?, estado = ?
                             WHERE id_usuario = ?"
                        );

                        $consulta->execute([
                            $nombre,
                            $usuario,
                            $rol,
                            $estado,
                            $id
                        ]);
                    }

                    mensaje('Los datos del usuario se actualizaron correctamente.');
                }
            }

            // ACTIVAR O DESACTIVAR USUARIO.
            elseif ($accion === 'cambiar_estado') {

                $id = (int) ($_POST['id_usuario'] ?? 0);

                if ($id === $mi_id) {
                    throw new Exception('No puedes cambiar el estado de tu propia cuenta.');
                }

                $consulta = $conexion->prepare(
                    "SELECT id_usuario, rol, estado FROM usuarios WHERE id_usuario = ?"
                );
                $consulta->execute([$id]);
                $usuario_objetivo = $consulta->fetch();

                if (!$usuario_objetivo) {
                    throw new Exception('El usuario no existe.');
                }

                if (
                    $usuario_objetivo['rol'] === 'administrador' &&
                    $usuario_objetivo['estado'] === 'activo'
                ) {
                    $consulta = $conexion->prepare(
                        "SELECT COUNT(*) FROM usuarios
                         WHERE rol = 'administrador'
                         AND estado = 'activo'
                         AND id_usuario != ?"
                    );
                    $consulta->execute([$id]);

                    if ((int) $consulta->fetchColumn() === 0) {
                        throw new Exception('No puedes desactivar el único administrador activo.');
                    }
                }

                $nuevo_estado = $usuario_objetivo['estado'] === 'activo'
                    ? 'inactivo'
                    : 'activo';

                $consulta = $conexion->prepare(
                    "UPDATE usuarios SET estado = ? WHERE id_usuario = ?"
                );
                $consulta->execute([$nuevo_estado, $id]);

                mensaje('El estado del usuario fue actualizado.');
            }

            // ELIMINAR USUARIO.
            elseif ($accion === 'eliminar') {

                $id = (int) ($_POST['id_usuario'] ?? 0);

                if ($id === $mi_id) {
                    throw new Exception('No puedes eliminar tu propia cuenta.');
                }

                $consulta = $conexion->prepare(
                    "SELECT id_usuario, rol, estado FROM usuarios WHERE id_usuario = ?"
                );
                $consulta->execute([$id]);
                $usuario_objetivo = $consulta->fetch();

                if (!$usuario_objetivo) {
                    throw new Exception('El usuario no existe.');
                }

                if (
                    $usuario_objetivo['rol'] === 'administrador' &&
                    $usuario_objetivo['estado'] === 'activo'
                ) {
                    $consulta = $conexion->prepare(
                        "SELECT COUNT(*) FROM usuarios
                         WHERE rol = 'administrador'
                         AND estado = 'activo'
                         AND id_usuario != ?"
                    );
                    $consulta->execute([$id]);

                    if ((int) $consulta->fetchColumn() === 0) {
                        throw new Exception('No puedes eliminar el único administrador activo.');
                    }
                }

                $consulta = $conexion->prepare(
                    "DELETE FROM usuarios WHERE id_usuario = ?"
                );
                $consulta->execute([$id]);

                mensaje('El usuario fue eliminado correctamente.');
            }

        } catch (PDOException $e) {
            mensaje('No se pudo completar la operación. Es posible que el usuario tenga registros relacionados.', 'error');
        } catch (Exception $e) {
            mensaje($e->getMessage(), 'error');
        }
    }
}

// Cargar usuario para editar.
if (isset($_GET['editar'])) {
    $id_editar = (int) $_GET['editar'];

    $consulta = $conexion->prepare(
        "SELECT id_usuario, nombre, usuario, rol, estado
         FROM usuarios WHERE id_usuario = ?"
    );
    $consulta->execute([$id_editar]);
    $editar = $consulta->fetch();

    if (!$editar) {
        mensaje('No se encontró el usuario que deseas editar.', 'error');
    }
}

// Obtener todos los usuarios.
$consulta = $conexion->query(
    "SELECT id_usuario, nombre, usuario, rol, estado, fecha_registro
     FROM usuarios
     ORDER BY id_usuario DESC"
);
$usuarios = $consulta->fetchAll();

// Contadores.
$total_usuarios = count($usuarios);
$total_administradores = 0;
$total_cajeros = 0;
$total_inactivos = 0;

foreach ($usuarios as $u) {
    if ($u['rol'] === 'administrador') {
        $total_administradores++;
    } else {
        $total_cajeros++;
    }

    if ($u['estado'] === 'inactivo') {
        $total_inactivos++;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios | CAFETIGO</title>

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
            max-width: 1200px;
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            padding: 12px;
            border: 1px solid #d8c8b8;
            border-radius: 6px;
            font-size: 15px;
            width: 100%;
        }

        .ancho-completo {
            grid-column: 1 / -1;
        }

        .ayuda {
            color: #806b5a;
            font-size: 12px;
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
            padding: 12px 20px;
        }

        .btn-editar {
            background: #d9a441;
            color: #2f241b;
        }

        .btn-estado {
            background: #e7e0d5;
            color: #3e2b20;
        }

        .btn-eliminar {
            background: #a83232;
            color: white;
        }

        .btn-cancelar {
            display: inline-block;
            background: #e7e0d5;
            color: #3e2b20;
            padding: 12px 18px;
            border-radius: 6px;
            text-decoration: none;
        }

        .acciones-formulario {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
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
            min-width: 750px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #eee5dc;
            text-align: left;
        }

        th {
            background: #6b4226;
            color: white;
        }

        tr:hover td {
            background: #faf7f2;
        }

        .estado {
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .activo {
            background: #e1f2e4;
            color: #245b30;
        }

        .inactivo {
            background: #f8e1e1;
            color: #8a2424;
        }

        .acciones {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .acciones form {
            display: inline;
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
        }
    </style>
</head>

<body>

<div class="contenedor">

    <div class="encabezado">
        <div>
            <h1>Gestión de usuarios</h1>
            <p class="subtitulo">Administra las cuentas de CAFETIGO.</p>
        </div>

        <a href="dashboard.php" class="volver">Volver al panel</a>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="mensaje <?= limpiar($tipo_mensaje) ?>">
            <?= limpiar($mensaje) ?>
        </div>
    <?php endif; ?>

    <div class="tarjetas">
        <div class="tarjeta">
            <p>Total de usuarios</p>
            <h2><?= $total_usuarios ?></h2>
        </div>

        <div class="tarjeta">
            <p>Administradores</p>
            <h2><?= $total_administradores ?></h2>
        </div>

        <div class="tarjeta">
            <p>Cajeros</p>
            <h2><?= $total_cajeros ?></h2>
        </div>

        <div class="tarjeta">
            <p>Usuarios inactivos</p>
            <h2><?= $total_inactivos ?></h2>
        </div>
    </div>

    <div class="panel">

        <h2><?= $editar ? 'Editar usuario' : 'Registrar nuevo usuario' ?></h2>

        <form method="POST" class="formulario">

            <input type="hidden" name="csrf_token"
                   value="<?= limpiar($_SESSION['csrf_usuarios']) ?>">

            <input type="hidden" name="accion" value="guardar">

            <input type="hidden" name="id_usuario"
                   value="<?= $editar ? (int) $editar['id_usuario'] : 0 ?>">

            <div class="campo">
                <label>Nombre completo</label>
                <input type="text" name="nombre" maxlength="100"
                       value="<?= limpiar($editar['nombre'] ?? '') ?>"
                       placeholder="Ej. Juan Pérez" required>
            </div>

            <div class="campo">
                <label>Nombre de usuario</label>
                <input type="text" name="usuario" maxlength="50"
                       value="<?= limpiar($editar['usuario'] ?? '') ?>"
                       placeholder="Ej. juanperez" required>
            </div>

            <div class="campo">
                <label>Contraseña <?= $editar ? '(opcional)' : '' ?></label>
                <input type="password" name="password"
                       minlength="8"
                       placeholder="<?= $editar ? 'Dejar vacío para conservarla' : 'Mínimo 8 caracteres' ?>"
                       <?= $editar ? '' : 'required' ?>>
                <?php if ($editar): ?>
                    <span class="ayuda">Si no deseas cambiarla, deja este campo vacío.</span>
                <?php endif; ?>
            </div>

            <div class="campo">
                <label>Rol</label>
                <select name="rol" required>
                    <option value="administrador"
                        <?= ($editar['rol'] ?? '') === 'administrador' ? 'selected' : '' ?>>
                        Administrador
                    </option>

                    <option value="cajero"
                        <?= ($editar['rol'] ?? '') === 'cajero' ? 'selected' : '' ?>>
                        Cajero
                    </option>
                </select>
            </div>

            <?php if ($editar): ?>
                <div class="campo">
                    <label>Estado</label>
                    <select name="estado" required>
                        <option value="activo"
                            <?= $editar['estado'] === 'activo' ? 'selected' : '' ?>>
                            Activo
                        </option>

                        <option value="inactivo"
                            <?= $editar['estado'] === 'inactivo' ? 'selected' : '' ?>>
                            Inactivo
                        </option>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="estado" value="activo">
            <?php endif; ?>

            <div class="acciones-formulario ancho-completo">
                <button type="submit" class="btn-principal">
                    <?= $editar ? 'Guardar cambios' : 'Registrar usuario' ?>
                </button>

                <?php if ($editar): ?>
                    <a href="usuarios.php" class="btn-cancelar">Cancelar edición</a>
                <?php endif; ?>
            </div>

        </form>
    </div>

    <div class="panel">

        <h2>Usuarios registrados</h2>

        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Fecha de registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($usuarios) > 0): ?>

                    <?php foreach ($usuarios as $u): ?>

                        <tr>
                            <td><?= (int) $u['id_usuario'] ?></td>
                            <td><?= limpiar($u['nombre']) ?></td>
                            <td><?= limpiar($u['usuario']) ?></td>

                            <td>
                                <?= $u['rol'] === 'administrador' ? 'Administrador' : 'Cajero' ?>
                            </td>

                            <td>
                                <span class="estado <?= limpiar($u['estado']) ?>">
                                    <?= ucfirst(limpiar($u['estado'])) ?>
                                </span>
                            </td>

                            <td><?= limpiar($u['fecha_registro']) ?></td>

                            <td>
                                <div class="acciones">

                                    <a href="usuarios.php?editar=<?= (int) $u['id_usuario'] ?>">
                                        <button type="button" class="btn-editar">Editar</button>
                                    </a>

                                    <?php if ((int) $u['id_usuario'] !== $mi_id): ?>

                                        <form method="POST">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= limpiar($_SESSION['csrf_usuarios']) ?>">
                                            <input type="hidden" name="accion"
                                                   value="cambiar_estado">
                                            <input type="hidden" name="id_usuario"
                                                   value="<?= (int) $u['id_usuario'] ?>">

                                            <button type="submit" class="btn-estado">
                                                <?= $u['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                                            </button>
                                        </form>

                                        <form method="POST"
                                              onsubmit="return confirm('¿Estás seguro de eliminar este usuario?');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= limpiar($_SESSION['csrf_usuarios']) ?>">
                                            <input type="hidden" name="accion"
                                                   value="eliminar">
                                            <input type="hidden" name="id_usuario"
                                                   value="<?= (int) $u['id_usuario'] ?>">

                                            <button type="submit" class="btn-eliminar">
                                                Eliminar
                                            </button>
                                        </form>

                                    <?php else: ?>
                                        <span class="ayuda">Tu cuenta</span>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7">Todavía no hay usuarios registrados.</td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

    </div>

</div>

</body>
</html>
