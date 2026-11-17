
<?php
session_start();

require_once "../config/conexion.php";

// Si ya inició sesión, ir al panel
if (isset($_SESSION['id_usuario'])) {
    header("Location: ../dashboard.php");
    exit;
}

$error = "";

// Token de seguridad CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Solicitud no válida. Recarga la página.";
    } else {

        $usuario = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($usuario === "" || $password === "") {

            $error = "Completa todos los campos.";

        } else {

            $sql = "SELECT * FROM usuarios
                    WHERE usuario = ? AND estado = 'activo'
                    LIMIT 1";

            $consulta = $conexion->prepare($sql);
            $consulta->execute([$usuario]);

            $datos = $consulta->fetch();

            if ($datos && password_verify($password, $datos['password'])) {

                session_regenerate_id(true);

                $_SESSION['id_usuario'] = $datos['id_usuario'];
                $_SESSION['nombre'] = $datos['nombre'];
                $_SESSION['usuario'] = $datos['usuario'];
                $_SESSION['rol'] = $datos['rol'];

                header("Location: ../dashboard.php");
                exit;

            } else {

                $error = "Usuario o contraseña incorrectos.";

            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | CAFETIGO</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f4eee7;
        }

        .login-container {
            width: 90%;
            max-width: 400px;
            background: white;
            padding: 40px 35px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .logo {
            text-align: center;
            color: #70452b;
            font-size: 35px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #777;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #493326;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #ddd;
            border-radius: 8px;
            outline: none;
            font-size: 15px;
        }

        input:focus {
            border-color: #94613e;
        }

        button {
            width: 100%;
            padding: 14px;
            background: #70452b;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #50301e;
        }

        .error {
            background: #ffe5e5;
            color: #b42318;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .footer {
            text-align: center;
            margin-top: 25px;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>

<body>

    <div class="login-container">

        <div class="logo">CAFETIGO</div>

        <p class="subtitle">
            Sistema de pedidos para cafeterías
        </p>

        <?php if ($error !== ""): ?>
            <div class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
            >

            <div class="form-group">
                <label>Nombre de usuario</label>

                <input
                    type="text"
                    name="usuario"
                    placeholder="Ingresa tu usuario"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="form-group">
                <label>Contraseña</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Ingresa tu contraseña"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit">
                INICIAR SESIÓN
            </button>

        </form>

        <div class="footer">
            CAFETIGO © 2026 - Todos los derechos reservados
        </div>

    </div>

</body>
</html>
