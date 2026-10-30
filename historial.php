
<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

require_once "conexion.php";

$usuario_id = $_SESSION["usuario_id"];
$mensaje = "";

// Eliminar movimiento
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_id"])) {

    $id = (int) $_POST["eliminar_id"];

    $sql = "DELETE FROM movimientos WHERE id = ? AND usuario_id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $id, $usuario_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        header("Location: historial.php?mensaje=eliminado");
        exit();
    }

    $mensaje = "No se pudo eliminar el movimiento.";
    $stmt->close();
}

// Mensaje después de eliminar
if (isset($_GET["mensaje"]) && $_GET["mensaje"] === "eliminado") {
    $mensaje = "Movimiento eliminado correctamente.";
}

// Filtros
$tipo = $_GET["tipo"] ?? "";
$categoria = $_GET["categoria"] ?? "";
$desde = $_GET["desde"] ?? "";
$hasta = $_GET["hasta"] ?? "";

$sql = "SELECT m.id, m.descripcion, m.tipo, m.monto, m.fecha,
               c.nombre AS categoria
        FROM movimientos m
        INNER JOIN categorias c ON m.categoria_id = c.id
        WHERE m.usuario_id = ?";

$parametros = [$usuario_id];
$tipos = "i";

if (in_array($tipo, ["ingreso", "gasto"], true)) {
    $sql .= " AND m.tipo = ?";
    $parametros[] = $tipo;
    $tipos .= "s";
}

if ($categoria !== "" && ctype_digit((string)$categoria)) {
    $sql .= " AND m.categoria_id = ?";
    $parametros[] = (int)$categoria;
    $tipos .= "i";
}

if ($desde !== "") {
    $sql .= " AND m.fecha >= ?";
    $parametros[] = $desde;
    $tipos .= "s";
}

if ($hasta !== "") {
    $sql .= " AND m.fecha <= ?";
    $parametros[] = $hasta;
    $tipos .= "s";
}

$sql .= " ORDER BY m.fecha DESC, m.id DESC";

$stmt = $conexion->prepare($sql);
$stmt->bind_param($tipos, ...$parametros);
$stmt->execute();

$movimientos = $stmt->get_result();
// Calcular resumen de los movimientos filtrados
$totalIngresos = 0;
$totalGastos = 0;
$cantidadMovimientos = $movimientos->num_rows;

while ($fila = $movimientos->fetch_assoc()) {
    if ($fila["tipo"] === "ingreso") {
        $totalIngresos += $fila["monto"];
    } else {
        $totalGastos += $fila["monto"];
    }
}

// Calcular saldo del historial filtrado
$saldoHistorial = $totalIngresos - $totalGastos;

// Volver al inicio para mostrar los movimientos en la tabla
$movimientos->data_seek(0);

// Categorías para el filtro
$categorias = $conexion->query(
    "SELECT id, nombre, tipo FROM categorias ORDER BY nombre"
);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial | SpendWise</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>

<div class="dashboard">

    <aside class="sidebar">
        <div class="logo">Spend<span>Wise.</span></div>

        <p class="menu-titulo">MENÚ PRINCIPAL</p>

        <a href="panel.php">▦ &nbsp; Dashboard</a>
        <a href="movimientos.php">↕ &nbsp; Movimientos</a>
        <a href="historial.php" class="activo">▤ &nbsp; Historial</a>

        <div class="sidebar-inferior">
            <a href="cerrar_sesion.php">↪ &nbsp; Cerrar sesión</a>
        </div>
    </aside>

    <main class="contenido-dashboard">

        <header class="encabezado-dashboard">
            <div>
                <h1>Historial financiero</h1>
                <p>Consulta y administra todos tus movimientos.</p>
            </div>
        </header>
        <a href="panel.php" class="boton-volver">
    ← Volver al Dashboard
</a>

        <?php if ($mensaje !== ""): ?>
            <p class="mensaje mensaje-exito">
                <?php echo htmlspecialchars($mensaje); ?>
            </p>
        <?php endif; ?>

        <section class="seccion-movimientos">
            

            <div class="titulo-seccion">
                <h2>Mis movimientos</h2>
                <a href="movimientos.php">+ Nuevo movimiento</a>
            </div>

            <form method="GET" class="filtros">

                <div>
                    <label>Tipo</label>
                    <select name="tipo">
                        <option value="">Todos</option>
                        <option value="ingreso" <?php if($tipo === "ingreso") echo "selected"; ?>>
                            Ingresos
                        </option>
                        <option value="gasto" <?php if($tipo === "gasto") echo "selected"; ?>>
                            Gastos
                        </option>
                    </select>
                </div>

                <div>
                    <label>Categoría</label>
                    <select name="categoria">
                        <option value="">Todas</option>

                        <?php while ($cat = $categorias->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>"
                                <?php if($categoria == $cat['id']) echo "selected"; ?>>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label>Desde</label>
                    <input type="date" name="desde"
                           value="<?php echo htmlspecialchars($desde); ?>">
                </div>

                <div>
                    <label>Hasta</label>
                    <input type="date" name="hasta"
                           value="<?php echo htmlspecialchars($hasta); ?>">
                </div>

                <button type="submit" class="boton-principal">
                    Filtrar
                </button>

                <a href="historial.php" class="limpiar-filtros">Limpiar</a>

            </form>

            <div class="tabla-contenedor">

                <table>
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th>Categoría</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($movimientos->num_rows > 0): ?>

                        <?php while ($movimiento = $movimientos->fetch_assoc()): ?>

                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($movimiento["descripcion"] ?: "Sin descripción"); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($movimiento["categoria"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($movimiento["fecha"]); ?>
                                </td>

                                <td>
                                    <span class="<?php echo $movimiento['tipo'] === 'ingreso' ? 'etiqueta-ingreso' : 'etiqueta-gasto'; ?>">
                                        <?php echo ucfirst($movimiento["tipo"]); ?>
                                    </span>
                                </td>

                                <td>
                                    $<?php echo number_format($movimiento["monto"], 2, ',', '.'); ?>
                                </td>

                                <td>
                                    <div class="acciones-movimiento">
                                        <a class="editar"
                                           href="editar_movimiento.php?id=<?php echo $movimiento['id']; ?>">
                                           Editar
                                        </a>

                                        <form method="POST"
                                              onsubmit="return confirm('¿Está seguro de eliminar este movimiento?');">

                                            <input type="hidden"
                                                   name="eliminar_id"
                                                   value="<?php echo $movimiento['id']; ?>">

                                            <button type="submit" class="eliminar">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6">
                                No se encontraron movimientos.
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
