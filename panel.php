
<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

require_once "conexion.php";

$usuario_id = $_SESSION["usuario_id"];
$nombre = $_SESSION["nombre"];
// Obtener el mes y año seleccionados
$mes = isset($_GET["mes"]) ? (int) $_GET["mes"] : (int) date("m");
$anio = isset($_GET["anio"]) ? (int) $_GET["anio"] : (int) date("Y");

// Validar el mes
if ($mes < 1 || $mes > 12) {
    $mes = (int) date("m");
}

// Validar el año
if ($anio < 2020 || $anio > 2100) {
    $anio = (int) date("Y");
}

// Calcular ingresos del periodo seleccionado
$sql = "SELECT COALESCE(SUM(monto), 0) AS total
        FROM movimientos
        WHERE usuario_id = ?
        AND tipo = 'ingreso'
        AND MONTH(fecha) = ?
        AND YEAR(fecha) = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("iii", $usuario_id, $mes, $anio);
$stmt->execute();
$ingresos = $stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

// Calcular gastos del periodo seleccionado
$sql = "SELECT COALESCE(SUM(monto), 0) AS total
        FROM movimientos
        WHERE usuario_id = ?
        AND tipo = 'gasto'
        AND MONTH(fecha) = ?
        AND YEAR(fecha) = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("iii", $usuario_id, $mes, $anio);
$stmt->execute();
$gastos = $stmt->get_result()->fetch_assoc()["total"];
$stmt->close();
$saldo = $ingresos - $gastos;

// Últimos movimientos del periodo seleccionado
$sql = "SELECT m.descripcion, m.tipo, m.monto, m.fecha, c.nombre AS categoria
        FROM movimientos m
        INNER JOIN categorias c ON m.categoria_id = c.id
        WHERE m.usuario_id = ?
        AND MONTH(m.fecha) = ?
        AND YEAR(m.fecha) = ?
        ORDER BY m.fecha DESC, m.id DESC
        LIMIT 5";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("iii", $usuario_id, $mes, $anio);
$stmt->execute();
$movimientos = $stmt->get_result();
// Datos de ingresos y gastos del periodo seleccionado
$sql_grafica = "SELECT 
                    tipo,
                    SUM(monto) AS total
                FROM movimientos
                WHERE usuario_id = ?
                AND MONTH(fecha) = ?
                AND YEAR(fecha) = ?
                GROUP BY tipo";

$stmt_grafica = $conexion->prepare($sql_grafica);
$stmt_grafica->bind_param("iii", $usuario_id, $mes, $anio);
$stmt_grafica->execute();

$resultado_grafica = $stmt_grafica->get_result();
$datos_mensuales = [];

while ($fila = $resultado_grafica->fetch_assoc()) {
    $datos_mensuales[] = $fila;
}

$stmt_grafica->close();


// Datos de gastos por categoría del periodo seleccionado
$sql_categorias = "SELECT 
                       c.nombre AS categoria,
                       SUM(m.monto) AS total
                   FROM movimientos m
                   INNER JOIN categorias c ON m.categoria_id = c.id
                   WHERE m.usuario_id = ?
                   AND m.tipo = 'gasto'
                   AND MONTH(m.fecha) = ?
                   AND YEAR(m.fecha) = ?
                   GROUP BY c.id, c.nombre
                   ORDER BY total DESC";

$stmt_categorias = $conexion->prepare($sql_categorias);
$stmt_categorias->bind_param("iii", $usuario_id, $mes, $anio);
$stmt_categorias->execute();

$resultado_categorias = $stmt_categorias->get_result();
$datos_categorias = [];

while ($fila = $resultado_categorias->fetch_assoc()) {
    $datos_categorias[] = $fila;
}

$stmt_categorias->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SpendWise</title>
    <link rel="stylesheet" href="css/estilos.css">

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>

<div class="dashboard">

    
     <aside class="sidebar" id="sidebar">
        <div class="logo">Spend<span>Wise.</span></div>

        <div class="menu-encabezado">
            <p class="menu-titulo">MENÚ PRINCIPAL</p>

            <button class="boton-menu" id="botonMenu" type="button" aria-label="Mostrar u ocultar menú">
                ☰
            </button>
        </div>

        <a href="panel.php" class="activo">▦ &nbsp; Dashboard</a>
        <a href="movimientos.php">↕ &nbsp; Movimientos</a>
        <a href="historial.php">▤ &nbsp; Historial</a>
        <a href="categorias.php">▤ &nbsp; Categorías</a>

        <div class="sidebar-inferior">
            <a href="cerrar_sesion.php">↪ &nbsp; Cerrar sesión</a>
        </div>
    </aside>

    <main class="contenido-dashboard">

        <header class="encabezado-dashboard">
            <div>
                <h1>Dashboard</h1>
                <p>Resumen general de tus finanzas personales.</p>
            </div>

            <div class="usuario">
                <div class="avatar">
                    <?php echo strtoupper(substr($nombre, 0, 1)); ?>
                </div>
                <span><?php echo htmlspecialchars($nombre); ?></span>
            </div>
        </header>

        <section class="bienvenida">
    <div>
        <h2>¡Hola, <?php echo htmlspecialchars($nombre); ?>!</h2>
        <p>Este es el resumen de tu actividad financiera.</p>
    </div>
    <span>SpendWise</span>
</section>

<!-- FILTRO DE PERIODO -->
<section class="filtro-periodo">
    <form method="GET" action="panel.php">

        <div class="campo-filtro">
            <label for="mes">Seleccionar mes</label>
            <select name="mes" id="mes">
                <?php
                $meses = [
                    1 => "Enero", 2 => "Febrero", 3 => "Marzo",
                    4 => "Abril", 5 => "Mayo", 6 => "Junio",
                    7 => "Julio", 8 => "Agosto", 9 => "Septiembre",
                    10 => "Octubre", 11 => "Noviembre", 12 => "Diciembre"
                ];

                foreach ($meses as $numero => $nombre_mes):
                ?>
                    <option value="<?php echo $numero; ?>"
                        <?php echo ($mes == $numero) ? "selected" : ""; ?>>
                        <?php echo $nombre_mes; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo-filtro">
            <label for="anio">Seleccionar año</label>
            <select name="anio" id="anio">
                <?php for ($a = (int)date("Y"); $a >= 2020; $a--): ?>
                    <option value="<?php echo $a; ?>"
                        <?php echo ($anio == $a) ? "selected" : ""; ?>>
                        <?php echo $a; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <button type="submit" class="boton-filtrar">
            Aplicar filtro
        </button>

    </form>
</section>

        <section class="tarjetas-resumen">

            <article class="tarjeta saldo">
                <p>Saldo disponible</p>
                <h2>$<?php echo number_format($saldo, 2, ',', '.'); ?></h2>
                <span>Ingresos menos gastos</span>
            </article>

            <article class="tarjeta">
                <p>Ingresos totales</p>
                <h2 class="valor-ingreso">
                    + $<?php echo number_format($ingresos, 2, ',', '.'); ?>
                </h2>
                <span>Total de dinero recibido</span>
            </article>

            <article class="tarjeta">
                <p>Gastos totales</p>
                <h2 class="valor-gasto">
                    - $<?php echo number_format($gastos, 2, ',', '.'); ?>
                </h2>
                <span>Total de dinero gastado</span>
            </article>

        </section>
        <section class="seccion-graficas">

    <div class="grafica-contenedor">
        <h2>Ingresos vs. gastos</h2>
        <canvas id="graficaMensual"></canvas>
    </div>

    <div class="grafica-contenedor">
        <h2>Gastos por categoría</h2>
        <canvas id="graficaCategorias"></canvas>
    </div>

</section>

        <section class="seccion-movimientos">
            <div class="titulo-seccion">
                <h2>Últimos movimientos</h2>
                <a href="movimientos.php">Ver todos</a>
            </div>

            <div class="tabla-contenedor">
                <table>
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th>Categoría</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if ($movimientos->num_rows > 0): ?>
                        <?php while ($movimiento = $movimientos->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($movimiento["descripcion"] ?? "Sin descripción"); ?></td>
                                <td><?php echo htmlspecialchars($movimiento["categoria"]); ?></td>
                                <td><?php echo htmlspecialchars($movimiento["fecha"]); ?></td>
                                <td>
                                    <?php echo ucfirst($movimiento["tipo"]); ?>
                                </td>
                                <td>
                                    $<?php echo number_format($movimiento["monto"], 2, ',', '.'); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">Todavía no tienes movimientos registrados.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        </main>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const botonMenu = document.getElementById("botonMenu");
    const sidebar = document.getElementById("sidebar");

    if (botonMenu && sidebar) {
        botonMenu.addEventListener("click", function () {
            sidebar.classList.toggle("sidebar-oculta");
        });
    }
});
</script>


<script>
    // Datos de ingresos y gastos del periodo seleccionado
    const datosMensuales = <?php echo json_encode($datos_mensuales); ?>;

    let totalIngresos = 0;
    let totalGastos = 0;

    datosMensuales.forEach(function (dato) {
        if (dato.tipo === "ingreso") {
            totalIngresos = Number(dato.total);
        } else if (dato.tipo === "gasto") {
            totalGastos = Number(dato.total);
        }
    });

    
// Gráfico de ingresos vs gastos
new Chart(document.getElementById("graficaMensual"), {
    type: "bar",
    data: {
        labels: ["Ingresos", "Gastos"],
        datasets: [{
            label: "Total del periodo",
            data: [totalIngresos, totalGastos],
            backgroundColor: ["#16a085", "#e76f51"],
            borderRadius: 8,
            barThickness: 35,
            maxBarThickness: 40
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});


    // Datos de gastos por categoría
    const datosCategorias = <?php echo json_encode($datos_categorias); ?>;

    // Gráfico de gastos por categoría
    new Chart(document.getElementById("graficaCategorias"), {
        type: "doughnut",
        data: {
            labels: datosCategorias.map(d => d.categoria),
            datasets: [{
                data: datosCategorias.map(d => Number(d.total)),
                backgroundColor: [
                    "#16a085", "#3498db", "#f39c12",
                    "#e76f51", "#9b59b6", "#34495e",
                    "#1abc9c", "#e84393"
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
</script>

</body>
</html>