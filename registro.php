
<?php
require_once "conexion.php";

$mensaje = "";
$registro_exitoso = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $nombre = trim($_POST["nombre"]);
    $correo = trim($_POST["correo"]);
    $contrasena = $_POST["contrasena"];

    if (empty($nombre) || empty($correo) || empty($contrasena)) {

        $mensaje = "Todos los campos son obligatorios.";

    } elseif (strlen($contrasena) < 4) {

        $mensaje = "La contraseña debe tener al menos 4 caracteres.";

    } else {

        $contrasena_segura = password_hash($contrasena, PASSWORD_DEFAULT);

        $sql = "INSERT INTO usuarios (nombre, correo, contrasena)
                VALUES (?, ?, ?)";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sss", $nombre, $correo, $contrasena_segura);

        if ($stmt->execute()) {
            $mensaje = "¡Tu cuenta ha sido creada exitosamente!";
            $registro_exitoso = true;
        } else {
            $mensaje = "No se pudo registrar. Verifica si el correo ya existe.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta | SpendWise</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>

<div class="pagina-login">

    <section class="panel-presentacion">

        <div class="logo">Spend<span>Wise.</span></div>

        <div class="presentacion-contenido">

            <span class="etiqueta">COMIENZA A ORGANIZARTE</span>

            <h1>El primer paso hacia unas finanzas más inteligentes.</h1>

            <p>
                Crea tu cuenta en SpendWise y comienza a administrar
                tus ingresos, controlar tus gastos y organizar
                tus objetivos financieros.
            </p>

        </div>

        <small>SpendWise © 2026 | Gestión inteligente de tus finanzas</small>

    </section>

    <section class="panel-formulario">

        <div class="formulario-contenido">

            <h2>Crear una cuenta</h2>

            <p class="subtitulo">
                Regístrate gratis y comienza a controlar tu dinero.
            </p>

            <form method="POST">

                <div class="grupo-campo">
                    <label>Nombre completo</label>
                    <input
                        type="text"
                        name="nombre"
                        placeholder="Ingresa tu nombre completo"
                        value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>"
                        required>
                </div>

                <div class="grupo-campo">
                    <label>Correo electrónico</label>
                    <input
                        type="email"
                        name="correo"
                        placeholder="ejemplo@correo.com"
                        value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>"
                        required>
                </div>

                <div class="grupo-campo">
                    <label>Contraseña</label>
                    <input
                        type="password"
                        name="contrasena"
                        placeholder="Mínimo 4 caracteres"
                        required>
                </div>

                <button type="submit" class="boton-principal">
                    Crear mi cuenta
                </button>

            </form>

            <?php if ($mensaje != ""): ?>
                <p class="mensaje <?php echo $registro_exitoso ? 'mensaje-exito' : ''; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </p>
            <?php endif; ?>

            <p class="enlace-registro">
                ¿Ya tienes una cuenta?
                <a href="login.php">Iniciar sesión</a>
            </p>

        </div>

    </section>

</div>

</body>
</html>
