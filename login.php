
<?php
session_start();
require_once "conexion.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $correo = trim($_POST["correo"]);
    $contrasena = $_POST["contrasena"];

    $sql = "SELECT id, nombre, correo, contrasena
            FROM usuarios
            WHERE correo = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $correo);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {

        $usuario = $resultado->fetch_assoc();

        if (password_verify($contrasena, $usuario["contrasena"])) {

            $_SESSION["usuario_id"] = $usuario["id"];
            $_SESSION["nombre"] = $usuario["nombre"];

            header("Location: panel.php");
            exit();

        } else {
            $mensaje = "Correo o contraseña incorrectos.";
        }

    } else {
        $mensaje = "Correo o contraseña incorrectos.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | SpendWise</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>

<div class="pagina-login">

    <section class="panel-presentacion">

        <div class="logo">Spend<span>Wise.</span></div>

        <div class="presentacion-contenido">

            <span class="etiqueta">CONTROL FINANCIERO PERSONAL</span>

            <h1>Controla tu dinero. Construye tu futuro.</h1>

            <p>
                Administra tus ingresos, controla tus gastos
                y toma mejores decisiones financieras desde
                un solo lugar.
            </p>

        </div>

        <small>SpendWise © 2026 | Gestión inteligente de tus finanzas</small>

    </section>

    <section class="panel-formulario">

        <div class="formulario-contenido">

            <h2>¡Bienvenido de nuevo!</h2>

            <p class="subtitulo">
                Ingresa tus datos para acceder a tu cuenta.
            </p>

            <form method="POST">

                <div class="grupo-campo">
                    <label>Correo electrónico</label>
                    <input type="email" name="correo"
                           placeholder="ejemplo@correo.com" required>
                </div>

                <div class="grupo-campo">
                    <label>Contraseña</label>
                    <input type="password" name="contrasena"
                           placeholder="Ingresa tu contraseña" required>
                </div>

                <button type="submit" class="boton-principal">
                    Iniciar sesión
                </button>

            </form>

            <?php if ($mensaje != ""): ?>
                <p class="mensaje">
                    <?php echo htmlspecialchars($mensaje); ?>
                </p>
            <?php endif; ?>

            <p class="enlace-registro">
                ¿Todavía no tienes una cuenta?
                <a href="registro.php">Crear cuenta</a>
            </p>

        </div>

    </section>

</div>

</body>
</html>
