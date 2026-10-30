
<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

require_once "conexion.php";

$usuario_id = $_SESSION["usuario_id"];
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $tipo = $_POST["tipo"];
    $categoria_id = (int) $_POST["categoria_id"];
    $descripcion = trim($_POST["descripcion"]);
    $monto = (float) $_POST["monto"];
    $fecha = $_POST["fecha"];

    if (
        !in_array($tipo, ["ingreso", "gasto"], true) ||
        $categoria_id <= 0 ||
        $monto <= 0 ||
        empty($fecha)
    ) {
        $mensaje = "Verifica los datos ingresados.";
    } else {

        // Comprobar que la categoría corresponda al tipo de movimiento.
        $sql = "SELECT id FROM categorias WHERE id = ? AND tipo = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("is", $categoria_id, $tipo);
        $stmt->execute();
        $categoria = $stmt->get_result();

        if ($categoria->num_rows === 1) {

            $sql = "INSERT INTO movimientos
                    (usuario_id, categoria_id, tipo, descripcion, monto, fecha)
                    VALUES (?, ?, ?, ?, ?, ?)";

            $stmt2 = $conexion->prepare($sql);
            $stmt2->bind_param(
                "iissds",
                $usuario_id,
                $categoria_id,
                $tipo,
                $descripcion,
                $monto,
                $fecha
            );

            if ($stmt2->execute()) {
                $mensaje = "¡Movimiento registrado correctamente!";
            } else {
                $mensaje = "No fue posible registrar el movimiento.";
            }

            $stmt2->close();

        } else {
            $mensaje = "La categoría no corresponde al tipo seleccionado.";
        }

        $stmt->close();
    }
}

// Consultar categorías
$sql = "SELECT id, nombre, tipo FROM categorias ORDER BY tipo, nombre";
$categorias = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos | SpendWise</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>

<div class="dashboard">

    <aside class="sidebar">
        <div class="logo">Spend<span>Wise.</span></div>

        <p class="menu-titulo">MENÚ PRINCIPAL</p>

        <a href="panel.php">▦ &nbsp; Dashboard</a>
        <a href="movimientos.php" class="activo">↕ &nbsp; Movimientos</a>
        <a href="categorias.php">▤ &nbsp; Categorías</a>

        <div class="sidebar-inferior">
            <a href="cerrar_sesion.php">↪ &nbsp; Cerrar sesión</a>
        </div>
    </aside>

    <main class="contenido-dashboard">

        <header class="encabezado-dashboard">
            <div>
                <h1>Registrar movimiento</h1>
                <p>Administra tus ingresos y gastos personales.</p>
            </div>
        </header>
        <a href="panel.php" class="boton-volver">
    ← Volver al Dashboard
</a>

        <section class="seccion-movimientos formulario-movimiento">

            <h2>Nuevo movimiento</h2>

            <p class="subtitulo">
                Completa los datos para guardar tu movimiento financiero.
            </p>

            <form method="POST">

                <div class="grupo-campo">
                    <label>Tipo de movimiento</label>
                    <select name="tipo" id="tipo" required>
                        <option value="ingreso">Ingreso</option>
                        <option value="gasto">Gasto</option>
                    </select>
                </div>

                <div class="grupo-campo">
                    <label>Categoría</label>
                    <select name="categoria_id" id="categoria" required>
                        <option value="">Selecciona una categoría</option>

                        <?php while ($cat = $categorias->fetch_assoc()): ?>
                            <option
                                value="<?php echo $cat['id']; ?>"
                                data-tipo="<?php echo $cat['tipo']; ?>">
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                        <?php endwhile; ?>

                    </select>
                </div>

                <div class="grupo-campo">
                    <label>Descripción</label>
                    <input type="text" name="descripcion"
                           placeholder="Ej. Pago de salario, mercado..."
                           maxlength="255">
                </div>

                <div class="grupo-campo">
                    <label>Monto ($)</label>
                    <input type="number" name="monto"
                           placeholder="Ej. 150000"
                           min="0.01" step="0.01" required>
                </div>

                <div class="grupo-campo">
                    <label>Fecha del movimiento</label>
                    <input type="date" name="fecha"
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <button type="submit" class="boton-principal">
                    Guardar movimiento
                </button>

            </form>

            <?php if ($mensaje != ""): ?>
                <p class="mensaje mensaje-exito">
                    <?php echo htmlspecialchars($mensaje); ?>
                </p>
            <?php endif; ?>

        </section>

    </main>

</div>

<script>
const tipo = document.getElementById("tipo");
const categoria = document.getElementById("categoria");

function filtrarCategorias() {
    const tipoSeleccionado = tipo.value;

    categoria.value = "";

    Array.from(categoria.options).forEach(opcion => {
        if (!opcion.value) return;

        opcion.hidden = opcion.dataset.tipo !== tipoSeleccionado;
    });
}

tipo.addEventListener("change", filtrarCategorias);
filtrarCategorias();
</script>

</body>
</html>
