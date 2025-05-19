<?php
// Este archivo solo inicia la sesión y redirecciona al archivo principal
// Sin comentarios, sin espacios extras, nada que pueda causar output

ob_start();
session_start();

// Verificar login
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

// Redireccionar al archivo principal
header("Location: airbag_reset_main.php?" . http_build_query($_GET));
exit;