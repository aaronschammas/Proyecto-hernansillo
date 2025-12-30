<?php
include_once("conexion.php");
include_once("funcionesphp1.php");

// Verifica que exista la función conectar()
if (!function_exists('conectar')) {
    die("Error: La función conectar() no está definida. Revisa conexion.php.");
}

// Conectar a la base de datos
$servidor    = "localhost";
$usuarioDB   = "u467512787_moda";
$contrasenia = "Hernan2215";
$base_datos  = "u467512787_mega";

try {
    $conexion = conectar($servidor, $usuarioDB, $contrasenia, $base_datos);
} catch (Exception $e) {
    die("Error de conexión: " . htmlspecialchars($e->getMessage()));
}

// Obtener opciones de sucursales para el select
try {
    $sucursales = obtenerSucursales($conexion);
} catch (Exception $e) {
    die("Error al obtener sucursales: " . htmlspecialchars($e->getMessage()));
}


$mensajeError = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    session_start();

    // Captura y valida campos
    $username = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['contrasena'] ?? '');
    $sucursal = trim($_POST['sucursal'] ?? '');

    if ($username === '' || $password === '' || $sucursal === '') {
        $mensajeError = 'Completa usuario, contraseña y sucursal.';
    } else {
        // Verificar credenciales
        $sql  = "SELECT * FROM usuarios WHERE nombre_usuario = :u AND contrasena = :p";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([':u' => $username, ':p' => $password]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Parsear sucursales habilitadas en array
            $habilitadas = array_map('trim', explode(',', $row['sucursales_habilitadas']));

            // Verificar acceso a la sucursal seleccionada
            if (in_array($sucursal, $habilitadas, true)) {
                // Guardar en sesión
                $_SESSION['usuario']    = $username;
                $_SESSION['sucursal']   = $sucursal;
                $_SESSION['sucursales'] = $habilitadas;
                $_SESSION['rol']        = $row['Rol'];

                // ✅ Caso especial: usuario Pico -> ir directo a consulta de precios
                if (strcasecmp($username, 'Pico') === 0) {
                    header("Location: https://www.megamoda.net/consulta_precios_pico.php");
                    exit();
                }

                // Redirigir según la sucursal
                if ($sucursal === 'ADM') {
                    header("Location: administracion.php");
                    exit();
                } elseif ($sucursal === 'CAJ') {
                    header("Location: caja/caja.php");
                    exit();
                } else {
                    header("Location: registro1.php");
                    exit();
                }
            } else {
                $mensajeError = 'No tienes acceso a la sucursal seleccionada.';
            }
        } else {
            $mensajeError = 'Usuario y/o contraseña inválidos.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mega Moda SRL : Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/login.css">

</head>
<body class="pt-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-4">
        <div class="card">
          <div class="card-header">REGISTRO MEGA MODA</div>
          <div class="card-body">
            <?php if (!empty($mensajeError)): ?>
              <div class="alert alert-danger text-center">
                <?= htmlspecialchars($mensajeError) ?>
              </div>
            <?php endif; ?>
            <form action="index.php" method="post">
              <div class="mb-3">
                <label class="form-label">Usuario</label>
                <input name="usuario" type="text" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <input name="contrasena" type="password" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Sucursal</label>
                <select name="sucursal" class="form-select" required>
                  <option value="" disabled selected>Selecciona una opción</option>
                  <?php foreach ($sucursales as $s): ?>
                    <option value="<?= htmlspecialchars($s['nombre_abreviado']) ?>">
                      <?= htmlspecialchars($s['nombre_abreviado']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button class="btn btn-success w-100" type="submit">Iniciar Sesión</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

