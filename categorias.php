
<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$mensaje = "";
$error = "";

// Mostrar mensajes después de una redirección
if (isset($_GET["mensaje"])) {
    $mensajes = [
        "creada" => "Categoría creada correctamente.",
        "editada" => "Categoría actualizada correctamente.",
        "eliminada" => "Categoría eliminada correctamente."
    ];

    $mensaje = $mensajes[$_GET["mensaje"]] ?? "";
}

if (isset($_GET["error"])) {
    $errores = [
        "datos" => "Verifica los datos de la categoría.",
        "asociada" => "No puedes eliminar esta categoría porque tiene movimientos asociados.",
        "incompatible" => "No puedes cambiar el tipo porque existen movimientos asociados incompatibles.",
        "guardar" => "No fue posible guardar los cambios.",
        "eliminar" => "No fue posible eliminar la categoría."
    ];

    $error = $errores[$_GET["error"]] ?? "";
}

// Crear categoría
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["crear"])) {

    $nombre = trim($_POST["nombre"]);
    $tipo = $_POST["tipo"];

    if ($nombre === "" || !in_array($tipo, ["ingreso", "gasto"])) {
        header("Location: categorias.php?error=datos");
        exit();
    }

    $stmt = $conexion->prepare(
        "INSERT INTO categorias (nombre, tipo) VALUES (?, ?)"
    );

    $stmt->bind_param("ss", $nombre, $tipo);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: categorias.php?mensaje=creada");
        exit();
    }

    $stmt->close();
    header("Location: categorias.php?error=guardar");
    exit();
}

// Editar categoría
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["editar"])) {

    $id = (int) $_POST["id"];
    $nombre = trim($_POST["nombre"]);
    $tipo = $_POST["tipo"];

    if ($id <= 0 || $nombre === "" || !in_array($tipo, ["ingreso", "gasto"])) {
        header("Location: categorias.php?error=datos");
        exit();
    }

    $stmt = $conexion->prepare(
        "SELECT COUNT(*) AS total
         FROM movimientos
         WHERE categoria_id = ? AND tipo <> ?"
    );

    $stmt->bind_param("is", $id, $tipo);
    $stmt->execute();

    $incompatibles = $stmt->get_result()->fetch_assoc()["total"];
    $stmt->close();

    if ($incompatibles > 0) {
        header("Location: categorias.php?error=incompatible");
        exit();
    }

    $stmt = $conexion->prepare(
        "UPDATE categorias SET nombre = ?, tipo = ? WHERE id = ?"
    );

    $stmt->bind_param("ssi", $nombre, $tipo, $id);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: categorias.php?mensaje=editada");
        exit();
    }

    $stmt->close();
    header("Location: categorias.php?error=guardar");
    exit();
}

// Eliminar categoría
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar"])) {

    $id = (int) $_POST["id"];

    $stmt = $conexion->prepare(
        "SELECT COUNT(*) AS total
         FROM movimientos
         WHERE categoria_id = ?"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $total = $stmt->get_result()->fetch_assoc()["total"];
    $stmt->close();

    if ($total > 0) {
        header("Location: categorias.php?error=asociada");
        exit();
    }

    $stmt = $conexion->prepare(
        "DELETE FROM categorias WHERE id = ?"
    );

    $stmt->bind_param("i", $id);
    $eliminada = $stmt->execute() && $stmt->affected_rows > 0;
    $stmt->close();

    if ($eliminada) {
        header("Location: categorias.php?mensaje=eliminada");
        exit();
    }

    header("Location: categorias.php?error=eliminar");
    exit();
}
// Obtener categorías
$resultado = $conexion->query(
    "SELECT c.id, c.nombre, c.tipo,
    (SELECT COUNT(*) FROM movimientos m WHERE m.categoria_id = c.id) AS movimientos
    FROM categorias c
    ORDER BY c.tipo, c.nombre"
);

$categorias = [];
while ($fila = $resultado->fetch_assoc()) {
    $categorias[] = $fila;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías | SpendWise</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .categorias-contenido {
            max-width: 1100px;
            margin: 35px auto;
            padding: 25px;
        }

        .categorias-encabezado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .categorias-encabezado h1 {
            color: #142b4a;
            margin: 0 0 8px;
        }

        .categorias-encabezado p {
            color: #718096;
            margin: 0;
        }

        .boton-nueva {
            background: #16a085;
            color: white;
            padding: 13px 20px;
            border-radius: 9px;
            border: none;
            font-weight: bold;
            cursor: pointer;
        }

        .categorias-resumen {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .resumen-tipo {
            background: white;
            padding: 22px;
            border-radius: 14px;
            box-shadow: 0 5px 20px rgba(15,35,70,.06);
        }

        .resumen-tipo h3 {
            color: #52657d;
            margin: 0 0 10px;
        }

        .resumen-tipo strong {
            font-size: 30px;
            color: #142b4a;
        }

        .lista-categorias {
            background: white;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 5px 20px rgba(15,35,70,.06);
            overflow-x: auto;
        }

        .lista-categorias table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px;
        }

        .lista-categorias th,
        .lista-categorias td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #edf0f4;
        }

        .lista-categorias th {
            color: #52657d;
            font-size: 13px;
        }

        .tipo-ingreso {
            color: #12816c;
            background: #e2f6f0;
            padding: 6px 11px;
            border-radius: 20px;
        }

        .tipo-gasto {
            color: #c0392b;
            background: #fdecea;
            padding: 6px 11px;
            border-radius: 20px;
        }

        .accion-editar,
        .accion-eliminar {
            border: none;
            background: none;
            cursor: pointer;
            font-weight: bold;
            margin-right: 10px;
        }

        .accion-editar { color: #2471a3; }
        .accion-eliminar { color: #c0392b; }

        .mensaje-categoria {
            padding: 13px;
            border-radius: 9px;
            margin-bottom: 20px;
        }

        .mensaje-correcto {
            color: #12816c;
            background: #e2f6f0;
        }

        .mensaje-error-categoria {
            color: #c0392b;
            background: #fdecea;
        }

        .modal-categoria {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(10,25,45,.55);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-contenido {
            background: white;
            width: 100%;
            max-width: 450px;
            padding: 28px;
            border-radius: 16px;
        }

        .modal-contenido h2 {
            color: #142b4a;
            margin-top: 0;
        }

        .modal-contenido label {
            display: block;
            margin: 14px 0 7px;
            color: #263b55;
            font-weight: 600;
        }

        .modal-contenido input,
        .modal-contenido select {
            width: 100%;
            padding: 12px;
            border: 1px solid #dce3ec;
            border-radius: 8px;
        }

        .modal-botones {
            display: flex;
            gap: 10px;
            margin-top: 22px;
        }

        .modal-botones button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }

        .guardar-categoria {
            background: #16a085;
            color: white;
        }

        .cancelar-categoria {
            background: #edf0f4;
            color: #52657d;
        }

        @media (max-width: 650px) {
            .categorias-resumen {
                grid-template-columns: 1fr;
            }

            .categorias-contenido {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<div class="categorias-contenido">

    <div class="categorias-encabezado">
        <a href="panel.php" class="boton-volver">
    ← Volver al Dashboard
</a>
        <div>
            <h1>Gestión de categorías</h1>
            <p>Organiza tus ingresos y gastos personales.</p>
        </div>

        <button class="boton-nueva" onclick="abrirCrear()">
            + Nueva categoría
        </button>
    </div>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje-categoria mensaje-correcto">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="mensaje-categoria mensaje-error-categoria">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php
    $totalIngresos = 0;
    $totalGastos = 0;

    foreach ($categorias as $categoria) {
        if ($categoria["tipo"] === "ingreso") {
            $totalIngresos++;
        } else {
            $totalGastos++;
        }
    }
    ?>

    <div class="categorias-resumen">
        <div class="resumen-tipo">
            <h3>Categorías de ingresos</h3>
            <strong><?= $totalIngresos ?></strong>
        </div>

        <div class="resumen-tipo">
            <h3>Categorías de gastos</h3>
            <strong><?= $totalGastos ?></strong>
        </div>
    </div>

    <div class="lista-categorias">
        <h2>Mis categorías</h2>

        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Movimientos</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($categorias as $categoria): ?>
                    <tr>
                        <td><?= htmlspecialchars($categoria["nombre"]) ?></td>

                        <td>
                            <?php if ($categoria["tipo"] === "ingreso"): ?>
                                <span class="tipo-ingreso">Ingreso</span>
                            <?php else: ?>
                                <span class="tipo-gasto">Gasto</span>
                            <?php endif; ?>
                        </td>

                        <td><?= $categoria["movimientos"] ?></td>

                        <td>
                            <button class="accion-editar"
                                onclick='abrirEditar(
                                    <?= (int)$categoria["id"] ?>,
                                    <?= json_encode($categoria["nombre"]) ?>,
                                    <?= json_encode($categoria["tipo"]) ?>
                                )'>
                                Editar
                            </button>

                            <form method="POST" style="display:inline"
                                onsubmit="return confirm('¿Está seguro de eliminar esta categoría?');">
                                <input type="hidden" name="id"
                                    value="<?= (int)$categoria["id"] ?>">
                                <button type="submit" name="eliminar"
                                    class="accion-eliminar">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (count($categorias) === 0): ?>
                    <tr>
                        <td colspan="4">Todavía no hay categorías registradas.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para crear y editar -->
<div class="modal-categoria" id="modalCategoria">
    <div class="modal-contenido">
        <h2 id="tituloModal">Nueva categoría</h2>

        <form method="POST" id="formCategoria">
            <input type="hidden" name="id" id="categoriaId">

            <label>Nombre de la categoría</label>
            <input type="text" name="nombre" id="nombreCategoria"
                maxlength="100" required>

            <label>Tipo de categoría</label>
            <select name="tipo" id="tipoCategoria" required>
                <option value="ingreso">Ingreso</option>
                <option value="gasto">Gasto</option>
            </select>

            <div class="modal-botones">
                <button type="submit" name="crear" id="botonGuardar"
                    class="guardar-categoria">
                    Crear categoría
                </button>

                <button type="button" class="cancelar-categoria"
                    onclick="cerrarModal()">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirCrear() {
    document.getElementById("tituloModal").textContent = "Nueva categoría";
    document.getElementById("formCategoria").reset();
    document.getElementById("categoriaId").value = "";
    document.getElementById("botonGuardar").textContent = "Crear categoría";
    document.getElementById("botonGuardar").name = "crear";
    document.getElementById("modalCategoria").style.display = "flex";
}

function abrirEditar(id, nombre, tipo) {
    document.getElementById("tituloModal").textContent = "Editar categoría";
    document.getElementById("categoriaId").value = id;
    document.getElementById("nombreCategoria").value = nombre;
    document.getElementById("tipoCategoria").value = tipo;
    document.getElementById("botonGuardar").textContent = "Guardar cambios";
    document.getElementById("botonGuardar").name = "editar";
    document.getElementById("modalCategoria").style.display = "flex";
}

function cerrarModal() {
    document.getElementById("modalCategoria").style.display = "none";
}
</script>

</body>
</html>
