
<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION["usuario_id"];
$mensaje = "";

// Obtener el ID del movimiento
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: historial.php");
    exit();
}

$id = (int) $_GET["id"];

// Consultar el movimiento
$stmt = $conexion->prepare(
    "SELECT * FROM movimientos WHERE id = ? AND usuario_id = ?"
);
$stmt->bind_param("ii", $id, $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();
$movimiento = $resultado->fetch_assoc();
$stmt->close();

if (!$movimiento) {
    die("Movimiento no encontrado.");
}

// Actualizar cuando se envíe el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tipo = $_POST["tipo"];
    $categoria_id = (int) $_POST["categoria_id"];
    $descripcion = trim($_POST["descripcion"]);
    $monto = (float) $_POST["monto"];
    $fecha = $_POST["fecha"];

    if (
        !in_array($tipo, ["ingreso", "gasto"]) ||
        $categoria_id <= 0 ||
        $descripcion === "" ||
        $monto <= 0 ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
    ) {
        $mensaje = "Verifica los datos ingresados.";
    } else {
        // Verificar que la categoría corresponda al tipo
        $stmt = $conexion->prepare(
            "SELECT id FROM categorias WHERE id = ? AND tipo = ?"
        );
        $stmt->bind_param("is", $categoria_id, $tipo);
        $stmt->execute();
        $categoria_valida = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$categoria_valida) {
            $mensaje = "La categoría no corresponde al tipo seleccionado.";
        } else {
            $stmt = $conexion->prepare(
                "UPDATE movimientos
                 SET tipo = ?, categoria_id = ?, descripcion = ?,
                     monto = ?, fecha = ?
                 WHERE id = ? AND usuario_id = ?"
            );

          $stmt->bind_param(
    "sisdsii",
    $tipo,
    $categoria_id,
    $descripcion,
    $monto,
    $fecha,
    $id,
    $usuario_id
);

            $stmt->execute();
            $stmt->close();

            header("Location: historial.php");
            exit();
        }
    }
}

// Consultar categorías
$categorias = $conexion->query(
    "SELECT id, nombre, tipo FROM categorias ORDER BY nombre"
);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar movimiento | SpendWise</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

<div class="contenedor-formulario">
    <h1>Editar movimiento</h1>
    <p>Actualiza la información de tu movimiento financiero.</p>

    <?php if ($mensaje !== ""): ?>
        <p class="mensaje-error">
            <?= htmlspecialchars($mensaje) ?>
        </p>
    <?php endif; ?>

    <form method="POST">
        <label>Tipo de movimiento</label>
        <select name="tipo" id="tipo" required>
            <option value="ingreso"
                <?= $movimiento["tipo"] === "ingreso" ? "selected" : "" ?>>
                Ingreso
            </option>
            <option value="gasto"
                <?= $movimiento["tipo"] === "gasto" ? "selected" : "" ?>>
                Gasto
            </option>
        </select>

        <label>Categoría</label>
        <select name="categoria_id" id="categoria_id" required>
            <?php while ($categoria = $categorias->fetch_assoc()): ?>
                <option
                    value="<?= $categoria["id"] ?>"
                    data-tipo="<?= htmlspecialchars($categoria["tipo"]) ?>"
                    <?= $movimiento["categoria_id"] == $categoria["id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($categoria["nombre"]) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Descripción</label>
        <input
            type="text"
            name="descripcion"
            value="<?= htmlspecialchars($movimiento["descripcion"]) ?>"
            maxlength="255"
            required
        >

        <label>Monto</label>
        <input
            type="number"
            name="monto"
            min="0.01"
            step="0.01"
            value="<?= htmlspecialchars($movimiento["monto"]) ?>"
            required
        >

        <label>Fecha</label>
        <input
            type="date"
            name="fecha"
            value="<?= htmlspecialchars($movimiento["fecha"]) ?>"
            required
        >

        <button type="submit">Guardar cambios</button>
        <a href="historial.php">Cancelar</a>
    </form>
</div>

<script>
const tipo = document.getElementById("tipo");
const categoria = document.getElementById("categoria_id");

function filtrarCategorias() {
    const tipoSeleccionado = tipo.value;

    Array.from(categoria.options).forEach(opcion => {
        opcion.hidden = opcion.dataset.tipo !== tipoSeleccionado;
    });

    const seleccionada = categoria.selectedOptions[0];

    if (!seleccionada || seleccionada.hidden) {
        const primera = Array.from(categoria.options).find(
            opcion => opcion.dataset.tipo === tipoSeleccionado
        );

        if (primera) {
            categoria.value = primera.value;
        }
    }
}

tipo.addEventListener("change", filtrarCategorias);
filtrarCategorias();
</script>

</body>
</html>
