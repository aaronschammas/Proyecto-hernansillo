<?php

function limpiarNumero($numero) {
    // Eliminar separadores de miles y convertir a punto decimal si es necesario
    $numero = str_replace('.', '', $numero);
    $numero = str_replace(',', '.', $numero);
    return floatval($numero);
}
function formatearNumero($numero) {
    return number_format($numero, 0, ',', '.');
}

date_default_timezone_set('America/Argentina/Buenos_Aires');

$host = 'localhost';
$username = 'u467512787_moda';
$password = 'Hernan2215';
$database = 'u467512787_mega';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sucursal1 = $_POST['sucursal'] ?? '';
    $fecha1 = $_POST['fecha'] ?? '';
    $turno1 = $_POST['turno'] ?? '';
    $horaActual = (new DateTime())->format('H:i:s');
    $usuario = $_POST['usuario'] ?? '';
    //$Chicas = $_POST['nombres_concatenados'];
    $inputTotalEfectivo = $_POST['inputTotalEfectivo'] ?? 0;
    $inputTotalCredito = $_POST['inputTotalCredito'] ?? 0;
    $inputTotalDebito = $_POST['inputTotalDebito'] ?? 0;
    $inputTotalFinancieras = $_POST['inputTotalFinancieras'] ?? 0;
    $inputTotalGastos = str_replace('.', '', $_POST['inputTotalGastos'] ?? 0);
    $inputEfectivoRendido = str_replace('.', '', $_POST['inputEfectivoRendido'] ?? 0);
    $billete1 = $_POST['cantidadBillete1'] ?? 0;
    $billete2 = $_POST['cantidadBillete2'] ?? 0;
    $billete3 = $_POST['cantidadBillete3'] ?? 0;
    $billete4 = $_POST['cantidadBillete4'] ?? 0;
    $billete5 = $_POST['cantidadBillete5'] ?? 0;
    $billete6 = $_POST['cantidadBillete6'] ?? 0;
    $chica1 = $_POST['chica1'] ?? '';
    $chica2 = $_POST['chica2'] ?? '';
    $chica3 = $_POST['chica3'] ?? '';
    $chica4 = $_POST['chica4'] ?? '';
    $comentarios = $_POST['comentarios'] ?? '';

    //var_dump($inputTotalEfectivo, $inputTotalCredito, $inputTotalDebito, $inputTotalFinancieras);
    $inputTotalEfectivo = limpiarNumero($inputTotalEfectivo);
    $inputTotalCredito = limpiarNumero($inputTotalCredito);
    $inputTotalDebito = limpiarNumero($inputTotalDebito);
    $inputTotalFinancieras = limpiarNumero($inputTotalFinancieras);
    $totalVentas = $inputTotalEfectivo + $inputTotalCredito + $inputTotalDebito + $inputTotalFinancieras;
   
    $fecha_obj = DateTime::createFromFormat('d-m-Y', $fecha1);
    if ($fecha_obj !== false) {
        $fecha1 = $fecha_obj->format('Y-m-d');
    } else {
        $fecha1 = null;
    }
    $sql = "INSERT INTO resumen_cierre (Id, sucursal, fecha, turno, hora, usuario, total_efectivo, total_tarjeta_credito, total_tarjeta_debito, total_financiera, total_gastos, efectivo_rendido, comentario)
            VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);   

    if ($stmt) {
        
        $stmt->bind_param("ssssssssssss", $sucursal1, $fecha1, $turno1, $horaActual, $usuario, $inputTotalEfectivo, $inputTotalCredito, $inputTotalDebito, $inputTotalFinancieras, $inputTotalGastos, $inputEfectivoRendido, $comentarios);

        if ($stmt->execute()) {
            echo "
                ------------ CIERRE CAJA ----------- :<br>\n
                <br>\n
                Sucursal : ".$sucursal1."<br>\n
                Fecha : ".$fecha1."<br>\n
                Tuno : ".$turno1."<br>\n
                Hora Cierre : ".$horaActual."<br>\n
                Usuario : ".$usuario."<br>\n
                <br>\n
                Total Ventas : $ ".formatearNumero($totalVentas)."<br>\n
                <br>\n
                 Efectivo : $ ".formatearNumero($inputTotalEfectivo)."<br>\n
                 Crédito : $ ".formatearNumero($inputTotalCredito)."<br>\n
                 Débito  : $ ".formatearNumero($inputTotalDebito)."<br>\n
                 Financieras : $ ".formatearNumero($inputTotalFinancieras)."<br>\n
                 Financieras : $ ".formatearNumero($inputTotalFinancieras)."<br>\n
                 <br>\n
                 Efectivo rendido : $ ".$inputEfectivoRendido."<br>\n
                 Billetes 10.000 : ".$billete1."<br>\n
                 Billetes 2.000 : ".$billete2."<br>\n
                 Billetes 1.000 : ".$billete3."<br>\n
                 Billetes 500 : ".$billete4."<br>\n
                 Billetes 200 : ".$billete5."<br>\n
                 Billetes 100 : ".$billete6."<br>\n
                 <br>\n
                 Chicas : ".$chica1."-".$chica2."-".$chica3."-".$chica4." <br>\n
                 <br>\n
                 Comentarios: ".$comentarios."<br>\n
                 <br>\n
                 <br>\n
                 Datos guardados correctamente.<br>\n
                 ------------------------------------
                "
                ;

        } else {
            echo "Error al guardar los datos: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Error en la preparación de la consulta: " . $conn->error;
    }
} else {
    echo "Método no permitido";
}

$conn->close();

?>
