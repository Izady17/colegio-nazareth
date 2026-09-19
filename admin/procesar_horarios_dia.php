<?php
session_start();
require_once "../includes/conexion.php";

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_paralelo = intval($_POST['id_paralelo'] ?? 0);
    $dia = trim($_POST['dia'] ?? '');
    $periodos = $_POST['periodos'] ?? [];

    if (empty($id_paralelo) || empty($dia) || empty($periodos)) {
        $_SESSION['error'] = "Por favor, seleccione el paralelo y el día.";
        header("Location: horarios.php");
        exit();
    }

    $pdo->beginTransaction();
    $insertados = 0;
    $errores = [];

    try {
        foreach ($periodos as $nro_periodo => $datos) {
            $id_materia = !empty($datos['id_materia']) ? intval($datos['id_materia']) : null;
            $id_docente = !empty($datos['id_docente']) ? intval($datos['id_docente']) : null;
            $hora_inicio = $datos['hora_inicio'] ?? '';
            $hora_fin = $datos['hora_fin'] ?? '';
            $requiere_asistencia = isset($datos['requiere_asistencia']) ? 1 : 0;

            // Si no se asignó materia, omitimos este periodo
            if (!$id_materia) {
                continue;
            }

            // Validación de campos obligatorios si hay materia
            if (!$id_docente) {
                $errores[] = "En el Periodo $nro_periodo seleccionaste una materia pero no asignaste docente.";
                continue;
            }

            // 1. VALIDACIÓN DE CRUCE: Mismo paralelo a la misma hora y día
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM horarios 
                WHERE id_paralelo = :id_paralelo 
                  AND dia = :dia 
                  AND (
                      (hora_inicio < :hora_fin AND hora_fin > :hora_inicio)
                  )
            ");
            $stmt->execute([
                ':id_paralelo' => $id_paralelo,
                ':dia' => $dia,
                ':hora_inicio' => $hora_inicio,
                ':hora_fin' => $hora_fin
            ]);
            if ($stmt->fetchColumn() > 0) {
                $errores[] = "Periodo $nro_periodo: El paralelo ya tiene una clase asignada en el horario $hora_inicio - $hora_fin.";
                continue;
            }

            // 2. VALIDACIÓN DE CRUCE: Mismo docente a la misma hora y día
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM horarios 
                WHERE id_docente = :id_docente 
                  AND dia = :dia 
                  AND (
                      (hora_inicio < :hora_fin AND hora_fin > :hora_inicio)
                  )
            ");
            $stmt->execute([
                ':id_docente' => $id_docente,
                ':dia' => $dia,
                ':hora_inicio' => $hora_inicio,
                ':hora_fin' => $hora_fin
            ]);
            if ($stmt->fetchColumn() > 0) {
                $errores[] = "Periodo $nro_periodo: El docente seleccionado ya tiene clase en otro curso a las $hora_inicio - $hora_fin el día $dia.";
                continue;
            }

            // INSERTAR PERIODO
            $sql = "INSERT INTO horarios (id_paralelo, id_materia, id_docente, dia, hora_inicio, hora_fin, requiere_asistencia) 
                    VALUES (:id_paralelo, :id_materia, :id_docente, :dia, :hora_inicio, :hora_fin, :requiere_asistencia)";
            $stmtInsert = $pdo->prepare($sql);
            $stmtInsert->execute([
                ':id_paralelo' => $id_paralelo,
                ':id_materia' => $id_materia,
                ':id_docente' => $id_docente,
                ':dia' => $dia,
                ':hora_inicio' => $hora_inicio,
                ':hora_fin' => $hora_fin,
                ':requiere_asistencia' => $requiere_asistencia
            ]);

            $insertados++;
        }

        // Manejo de resultados
        if (!empty($errores)) {
            $pdo->rollBack();
            $_SESSION['error'] = "No se guardó el día debido a los siguientes conflictos:<br>" . implode("<br>", $errores);
        } else if ($insertados === 0) {
            $pdo->rollBack();
            $_SESSION['error'] = "No se seleccionó ninguna materia para guardar.";
        } else {
            $pdo->commit();
            $_SESSION['exito'] = "¡Éxito! Se registraron $insertados periodos para el día $dia correctamente.";
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error inesperado al guardar horarios: " . $e->getMessage();
    }

    header("Location: horarios.php");
    exit();
}