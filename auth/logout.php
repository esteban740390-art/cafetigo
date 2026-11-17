
<?php

session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit("Solicitud no válida.");
}

// Vaciar la sesión
$_SESSION = [];

// Eliminar la sesión
session_destroy();

// Volver al login
header("Location: login.php");
exit;

?>
