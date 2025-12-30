<?php
// consulta_precios_pico.php
date_default_timezone_set('America/Argentina/Buenos_Aires');
session_start();

// Si no hay usuario logueado, volver al login
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// (Opcional) Restringir solo a Pico
if (strcasecmp($_SESSION['usuario'], 'Pico') !== 0) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . "/conexion.php";

/* =========================
   CONFIG (YA FIJO A TU TABLA)
   ========================= */
$TABLA_PRODUCTOS = "productos_pico";

// posibles columnas (el script prueba en este orden)
$POSIBLES_COL_CODIGO = [
    "codigo_base", "codigo", "cod", "COD", "Codigo", "CODIGO",
    "codigo_producto", "cod_producto", "producto_codigo", "sku", "SKU"
];

$POSIBLES_COL_DESCRIPCION = [
    "descripcion", "Descripcion", "detalle", "Detalle", "nombre", "Nombre",
    "articulo", "Articulo", "producto", "Producto"
];

$POSIBLES_COL_PRECIO = [
    "precio_lista", "PrecioLista", "precio", "Precio", "precio_venta", "Precio_Venta",
    "lista", "LISTA", "precioList", "preciolista"
];

/* =========================
   CONEXIÓN PDO
   ========================= */
try {
    $db  = new Conexion();
    $pdo = $db->getConexion();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Error de conexión: " . htmlspecialchars($e->getMessage()));
}

/* =========================
   HELPERS
   ========================= */
function limpiarCodigoBarra($s) {
    $s = strtoupper(trim((string)$s));
    // sacar espacios y caracteres raros, dejar solo A-Z y 0-9
    $s = preg_replace('/\s+/', '', $s);
    $s = preg_replace('/[^A-Z0-9]/', '', $s);
    return $s;
}

// Equivalente práctico a REDOND.MULT (MROUND) al múltiplo indicado
function redond_mult($valor, $multiplo = 100) {
    if ($multiplo == 0) return 0;
    return round($valor / $multiplo, 0, PHP_ROUND_HALF_UP) * $multiplo;
}

function money_ar($n) {
    // formateo estilo AR: 1.234.567
    return number_format((float)$n, 0, ",", ".");
}

function quoteIdent($ident) {
    // backticks para evitar problemas con nombres reservados
    return "`" . str_replace("`", "``", $ident) . "`";
}

// intenta consultar un producto con una combinación de columnas
function buscarProducto($pdo, $tabla, $colCodigo, $colDesc, $colPrecio, $codigoBase) {
    $t  = quoteIdent($tabla);
    $cc = quoteIdent($colCodigo);
    $cd = quoteIdent($colDesc);
    $cp = quoteIdent($colPrecio);

    $sql = "SELECT {$cc} AS codigo, {$cd} AS descripcion, {$cp} AS precio_lista
            FROM {$t}
            WHERE {$cc} = :codigo
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':codigo' => $codigoBase]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =========================
   PROCESO
   ========================= */
$inputCodigoBarra = $_POST['codigo_barra'] ?? '';
$codigoLimpio     = limpiarCodigoBarra($inputCodigoBarra);
$codigoBase       = ($codigoLimpio !== '') ? substr($codigoLimpio, 0, 8) : '';

$producto = null;
$error    = '';
$noEncontrado = false;
$detectado = ""; // para debug visual

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($codigoLimpio === '' || strlen($codigoBase) < 8) {
        $error = "Código inválido. Debe tener al menos 8 caracteres.";
    } else {
        $encontro = false;
        $ultimoError = null;

        // probamos combinaciones de columnas dentro de productos_pico
        foreach ($POSIBLES_COL_CODIGO as $colCodigo) {
            foreach ($POSIBLES_COL_DESCRIPCION as $colDesc) {
                foreach ($POSIBLES_COL_PRECIO as $colPrecio) {
                    try {
                        $producto = buscarProducto($pdo, $TABLA_PRODUCTOS, $colCodigo, $colDesc, $colPrecio, $codigoBase);
                        if ($producto) {
                            $encontro  = true;
                            $detectado = "Detectado: {$TABLA_PRODUCTOS} ({$colCodigo}, {$colDesc}, {$colPrecio})";
                            break 3;
                        }
                    } catch (Exception $e) {
                        $ultimoError = $e->getMessage();
                        continue;
                    }
                }
            }
        }

        if (!$encontro) {
            // si el problema es columnas, mostramos el error para ajustar rápido
            if ($ultimoError) {
                $error = "No pude consultar {$TABLA_PRODUCTOS}. Probablemente las columnas se llaman distinto. Detalle: " . htmlspecialchars($ultimoError);
            } else {
                $noEncontrado = true;
            }
        }
    }
}

// cálculos si hay producto
$precioLista    = null;
$precioEfectivo = null;
$precioDebito   = null;

if ($producto) {
    $precioLista    = (float)$producto['precio_lista'];
    $precioEfectivo = redond_mult($precioLista / 1.4, 100);
    $precioDebito   = round($precioEfectivo * 1.08, 0, PHP_ROUND_HALF_UP);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Consulta de Precios - Pico</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .barcode-input { font-size: 1.4rem; padding: 14px 12px; }
    .precio-box { font-size: 1.6rem; font-weight: 700; }
  </style>
</head>
<body class="bg-light">

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-0">Consulta de precios</h4>
      <small class="text-muted">Usuario: <?= htmlspecialchars($_SESSION['usuario']) ?></small>
    </div>
    <div>
      <a class="btn btn-outline-secondary btn-sm" href="logout.php">Salir</a>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="POST" action="consulta_precios_pico.php" autocomplete="off">
        <label class="form-label">Escanear / ingresar código de barras completo</label>
        <input
          id="codigo_barra"
          name="codigo_barra"
          type="text"
          class="form-control barcode-input"
          placeholder="Ej: VEPO04K50613"
          value="<?= htmlspecialchars($inputCodigoBarra) ?>"
          autofocus
          required
        >
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary" type="submit">Buscar</button>
          <a class="btn btn-outline-danger" href="consulta_precios_pico.php">Limpiar</a>
        </div>
      </form>

      <?php if ($codigoBase !== ''): ?>
        <div class="mt-3 d-flex gap-2 flex-wrap">
          <span class="badge bg-dark">Código base usado: <?= htmlspecialchars($codigoBase) ?></span>
          <?php if ($detectado): ?>
            <span class="badge bg-success"><?= htmlspecialchars($detectado) ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger mt-3 mb-0"><?= $error ?></div>
      <?php endif; ?>

      <?php if ($noEncontrado): ?>
        <div class="alert alert-warning mt-3 mb-0">
          No se encontró el producto para el código base <strong><?= htmlspecialchars($codigoBase) ?></strong>.
        </div>
      <?php endif; ?>

      <?php if ($producto): ?>
        <hr class="my-4">

        <div class="mb-2">
          <div class="text-muted">Descripción</div>
          <div class="h5 mb-0"><?= htmlspecialchars($producto['descripcion']) ?></div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-4">
            <div class="card">
              <div class="card-body">
                <div class="text-muted">Precio Lista</div>
                <div class="precio-box">$ <?= money_ar($precioLista) ?></div>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="card">
              <div class="card-body">
                <div class="text-muted">Precio Efectivo</div>
                <div class="precio-box">$ <?= money_ar($precioEfectivo) ?></div>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="card">
              <div class="card-body">
                <div class="text-muted">Precio Débito</div>
                <div class="precio-box">$ <?= money_ar($precioDebito) ?></div>
              </div>
            </div>
          </div>
        </div>

      <?php endif; ?>

    </div>
  </div>
</div>

<script>
  // Para que el escáner sea cómodo: al cargar, selecciona todo el input
  const inp = document.getElementById('codigo_barra');
  if (inp) {
    inp.focus();
    inp.select();
  }
</script>

</body>
</html>

