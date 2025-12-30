<?php

function obtenerSucursales() {
    try {
        $query = "SELECT nombre_abreviado FROM sucursales";
       
        $conexion = new Conexion();

        $conn = $conexion->getConexion();
        
        $stmt = $conn->prepare($query);

        $stmt->execute();
      
        return $stmt->fetchAll();
            
    } catch (Exception $e) {
        throw new Exception("Error en la conexión: " . $e->getMessage());
    }
}

function obtenerSucursalesNombre() {
    try {
        $query = "SELECT nombre_sucursal FROM sucursales";
       
        $conexion = new Conexion();

        $conn = $conexion->getConexion();
        
        $stmt = $conn->prepare($query);

        $stmt->execute();
      
        return $stmt->fetchAll();
            
    } catch (Exception $e) {
        throw new Exception("Error en la conexión: " . $e->getMessage());
    }
}

function obtenerNombreSucursal($sucursalSeleccionada) {
    try {
        $sql = "SELECT nombre_sucursal FROM sucursales WHERE nombre_abreviado = ?";
       
        $conexion = new Conexion();

        $conn = $conexion->getConexion();
        
        $stmt = $conn->prepare($sql);

        $stmt->bindParam(1, $sucursalSeleccionada);

        $stmt->execute();

        $resultado = $stmt->fetchAll();

        if ($resultado) {
            $primerResultado = $resultado[0];
            $nombreSucursalSeleccionada = $primerResultado['nombre_sucursal'];
        } else {
            $nombreSucursalSeleccionada = "No encontrado";
        }

        return $nombreSucursalSeleccionada;
            
    } catch (Exception $e) {
        throw new Exception("Error en la conexión: " . $e->getMessage());
    }
}

function obtenerFormasDePago($sucursalSeleccionada) {
    try {
        $sql = "SELECT nombre_forma_de_pago FROM formas_de_pago WHERE sucursales LIKE CONCAT('%', ?, '%')";

        $conexion = new Conexion();

        $conn = $conexion->getConexion();

        $stmt = $conn->prepare($sql);

        $stmt->bindParam(1, $sucursalSeleccionada);

        $stmt->execute();

        $resultados = $stmt->fetchAll();

        return $resultados;
            
    } catch (Exception $e) {
        throw new Exception("Error en la conexión: " . $e->getMessage());
    }
}

function obtenerTarjetasDebito($sucursalSeleccionada) {
    try {
        $sql = "SELECT nombre_tarjeta FROM tarjetas_debito WHERE sucursales LIKE CONCAT('%', ?, '%')";

        $conexion = new Conexion();

        $conn = $conexion->getConexion();

        $stmt = $conn->prepare($sql);

        $stmt->bindParam(1, $sucursalSeleccionada);

        $stmt->execute();

        $resultados = $stmt->fetchAll();

        return $resultados;
            
    } catch (Exception $e) {
        throw new Exception("Error en la conexión: " . $e->getMessage());
    }
}

function obtenerTarjetasCredito($sucursalSeleccionada) {
    try {
        $sql = "SELECT nombre_plan FROM tarjetas_credito WHERE sucursales LIKE CONCAT('%', ?, '%')";

        $conexion = new Conexion();

        $conn = $conexion->getConexion();

        $stmt = $conn->prepare($sql);

        $stmt->bindParam(1, $sucursalSeleccionada);

        $stmt->execute();

        $resultados = $stmt->fetchAll();

        return $resultados;
            
    } catch (Exception $e) {
        throw new Exception("Error en la conexión: " . $e->getMessage());
    }
}

function obtenerFinancieras($sucursalSeleccionada) {
    try {
        $sql = "SELECT nombre_plan FROM financieras WHERE sucursales LIKE CONCAT('%', ?, '%')";

        $conexion = new Conexion();

        $conn = $conexion->getConexion();

        $stmt = $conn->prepare($sql);

        $stmt->bindParam(1, $sucursalSeleccionada);

        $stmt->execute();

        $resultados = $stmt->fetchAll();

        return $resultados;
            
    } catch (Exception $e) {
        throw new Exception("Error en la conexión: " . $e->getMessage());
    }
}

function obtenerTransferencias($sucursalSeleccionada) {
    try {
        $sql = "SELECT nombre_transferencia FROM transferencias WHERE sucursales LIKE CONCAT('%', ?, '%')";

        $conexion = new Conexion();

        $conn = $conexion->getConexion();

        $stmt = $conn->prepare($sql);

        $stmt->bindParam(1, $sucursalSeleccionada);

        $stmt->execute();

        $resultados = $stmt->fetchAll();

        return $resultados;
            
    } catch (Exception $e) {
        throw new Exception("Error en la conexión: " . $e->getMessage());
    }
}
function obtenerTransferenciasQR($sucursalSeleccionada) {
    try {
        $sql = "SELECT nombre_transferencia FROM transferencias WHERE sucursales LIKE CONCAT('%', ?, '%')";

        $conexion = new Conexion();

        $conn = $conexion->getConexion();

        $stmt = $conn->prepare($sql);

        $stmt->bindParam(1, $sucursalSeleccionada);

        $stmt->execute();

        $resultados = $stmt->fetchAll();

        return $resultados;
            
    } catch (Exception $e) {
        throw new Exception("Error en la conexión: " . $e->getMessage());
    }
}

function generarIdCierreFinal(PDO $conexion, string $idCierreBase): string {
    try {
        $sql = "SELECT COUNT(*) as total FROM totales_ventas WHERE Id_Cierre LIKE ?";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparando la consulta: " . implode(" - ", $conexion->errorInfo()));
        }

        $searchPattern = $idCierreBase . '%';
        $stmt->execute([$searchPattern]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $cantidadExistente = $result['total'] ?? 0;
        $siguienteNumero = $cantidadExistente + 1;
        $idCierreFinal = $idCierreBase . str_pad($siguienteNumero, 2, '0', STR_PAD_LEFT);

        return $idCierreFinal;

    } catch (Exception $e) {
        error_log("Error al generar IdCierreFinal: " . $e->getMessage());
        return '';
    }
}

function guardarResumen() {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar_resumen'])) {
        try {
            // Incluir la conexión
            $conexion = new Conexion();
            $db = $conexion->getConexion();

            // Recibir datos del formulario
            $fecha = $_POST['inputFecha'];
            $turno = $_POST['inputTurno'];
            $total_efectivo = $_POST['inputTotalEfectivo'];
            $total_credito = $_POST['inputTotalCredito'];
            $total_debito = $_POST['inputTotalDebito'];
            $total_financiera = $_POST['inputTotalFinancieras'];
            $total_transferencia = $_POST['inputTotalTransferencias'];
            $total_transferenciaQR = $_POST['inputTotalTransferenciasQR'];
            $total_gastos = $_POST['inputTotalGastos'];
            $comentario = $_POST['txrcomentario'];

            // Procesar nombres de chicas
            $chicas = isset($_POST['chicas']) ? implode(", ", $_POST['chicas']) : "";

            // Insertar en la base de datos
            $sql = "INSERT INTO resumen_cierre (Fecha, Turno, total_efectivo, total_tarjeta_credito, total_tarjeta_debito, total_financiera, total_transferencia, total_transferenciaQR, total_gastos, chicas, comentario)
                    VALUES (:fecha, :turno, :total_efectivo, :total_credito, :total_debito, :total_financiera, :total_transferencia, :total_transferenciaQR, :total_gastos, :chicas, :comentario)";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':fecha', $fecha);
            $stmt->bindParam(':turno', $turno);
            $stmt->bindParam(':total_efectivo', $total_efectivo);
            $stmt->bindParam(':total_credito', $total_credito);
            $stmt->bindParam(':total_debito', $total_debito);
            $stmt->bindParam(':total_financiera', $total_financiera);
            $stmt->bindParam(':total_transferencia', $total_transferencia);
            $stmt->bindParam(':total_transferenciaQR', $total_transferenciaQR);
            $stmt->bindParam(':total_gastos', $total_gastos);
            $stmt->bindParam(':chicas', $chicas);
            $stmt->bindParam(':comentario', $comentario);

            // Ejecutar la consulta
            $stmt->execute();

            // Obtener el ID del registro insertado
            $ultimo_id = $db->lastInsertId();

            // Redirigir a la página de impresión
            header("Location: imprimir_resumen.php?id=$ultimo_id");
            exit();
        } catch (Exception $e) {
            echo "Error al guardar: " . $e->getMessage();
        }
    }
}

// Llamar a la función si se envió el formulario
guardarResumen();

function existeCierrePorUsuario(PDO $cn, string $sucursal, string $fecha, string $turno, string $usuario): string
{
    $sql = "
        SELECT 1
        FROM totales_ventas
        WHERE Sucursal = :sucursal
          AND Fecha    = :fecha
          AND Turno    = :turno
          AND Usuario  = :usuario
        LIMIT 1
    ";
    $st = $cn->prepare($sql);
    $st->execute([
        ':sucursal' => trim($sucursal),
        ':fecha'    => trim($fecha),             // formato 'YYYY-MM-DD'
        ':turno'    => strtoupper(trim($turno)), // 'M' o 'T'
        ':usuario'  => trim($usuario),
    ]);

    return ($st->fetchColumn() !== false) ? "SI" : "NO";
}
/*
function insertarResumenCierre($sucursal, $fecha, $turno, $total_efectivo, $total_tarjeta_credito, $total_tarjeta_debito, $total_financiera, $total_gastos, $chicas, $comentario) {
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtener los datos del formulario
    $sucursal = $_POST['inputSucursal'];
    $fecha = $_POST['fecha'];
    $turno = $_POST['turno'];
    $total_efectivo = $_POST['inputTotalEfectivo'];
    $total_tarjeta_credito = $_POST['inputTotalCredito'];
    $total_tarjeta_debito = $_POST['inputTotalDebito'];
    $total_financiera = $_POST['inputTotalFinancieras'];
    $total_gastos = $_POST['inputTotalGastos'];
    $chicas = $_POST['inputEfectivoRendido'];
    $comentario = $_POST['comentarios'];

    try {
        // Preparar la consulta SQL
        $sql = "INSERT INTO registro_mega.resumen_cierre (sucursal, fecha, turno, total_efectivo, total_tarjeta_credito, total_tarjeta_debito, total_financiera, total_gastos, chicas, comentario) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        // Obtener la conexión a la base de datos
        $conexion = new Conexion();
        $conn = $conexion->getConexion();

        // Preparar la consulta SQL
        $stmt = $conn->prepare($sql);

        // Vincular parámetros
        $stmt->bindParam(1, $sucursal);
        $stmt->bindParam(2, $fecha);
        $stmt->bindParam(3, $turno);
        $stmt->bindParam(4, $total_efectivo);
        $stmt->bindParam(5, $total_tarjeta_credito);
        $stmt->bindParam(6, $total_tarjeta_debito);
        $stmt->bindParam(7, $total_financiera);
        $stmt->bindParam(8, $total_gastos);
        $stmt->bindParam(9, $chicas);
        $stmt->bindParam(10, $comentario);

        // Ejecutar la consulta
        $stmt->execute();

        echo "Los datos se han insertado correctamente";
    } catch (Exception $e) {
        echo "Error al insertar los datos: " . $e->getMessage();
    }
}
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recibir los datos del formulario
    $fecha = $_POST['inputFecha'];
    $turno = $_POST['inputTurno'];
    $totalEfectivo = $_POST['inputTotalEfectivo'];
    $totalCredito = $_POST['inputTotalCredito'];
    $totalDebito = $_POST['inputTotalDebito'];
    $totalFinanciera = $_POST['inputTotalFinancieras'];
    $totalTransferencia = $_POST['inputTotalTransferencias'];
    $totalTransferenciaQR = $_POST['inputTotalTransferenciasQR'];
    $totalGastos = $_POST['inputTotalGastos'];
    $chicas = implode(", ", $_POST['chicas']); // Para el caso de chicas seleccionadas
    $comentario = $_POST['txrcomentario'];

    // Asegúrate de que los datos se están recibiendo
    var_dump($_POST);

    // Llamar a la función para insertar los datos en la base de datos
    insertarResumen($fecha, $turno, $totalEfectivo, $totalCredito, $totalDebito, $totalFinanciera, $totalTransferencia, $totalTransferenciaQR, $totalGastos, $chicas, $comentario);
}

function insertarResumen($fecha, $turno, $totalEfectivo, $totalCredito, $totalDebito, $totalFinanciera, $totalTransferencia, $totalTransferenciaQR, $totalGastos, $chicas, $comentario) {
    try {
        // Conectar a la base de datos
        $conexion = (new Conexion())->getConexion();

        // Insertar los datos en la tabla resumen_cierre
        $sql = "INSERT INTO resumen_cierre (Fecha, Turno, total_efectivo, total_tarjeta_credito, total_tarjeta_debito, total_financiera, total_transferencia, total_transferenciaQR, total_gastos, chicas, comentario)
                VALUES (:fecha, :turno, :totalEfectivo, :totalCredito, :totalDebito, :totalFinanciera, :totalTransferencia, :totalTransferenciaQR, :totalGastos, :chicas, :comentario)";

        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':turno', $turno);
        $stmt->bindParam(':totalEfectivo', $totalEfectivo);
        $stmt->bindParam(':totalCredito', $totalCredito);
        $stmt->bindParam(':totalDebito', $totalDebito);
        $stmt->bindParam(':totalFinanciera', $totalFinanciera);
        $stmt->bindParam(':totalTransferencia', $totalTransferencia);
        $stmt->bindParam(':totalTransferenciaQR', $totalTransferenciaQR);
        $stmt->bindParam(':totalGastos', $totalGastos);
        $stmt->bindParam(':chicas', $chicas);
        $stmt->bindParam(':comentario', $comentario);

        // Ejecutar la consulta
        $stmt->execute();

        // Redirigir al usuario a la página de resumen
        header("Location: resumen.php");
        exit;
    } catch (Exception $e) {
        echo "Error al insertar los datos: " . $e->getMessage();
    }
}

*/

    