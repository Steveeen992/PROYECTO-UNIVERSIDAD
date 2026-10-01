<?php
require_once 'config.php';
requireLogin();

// Obtener filtros
$elemento_id = $_GET['elemento_id'] ?? null;
$tipo_evento = $_GET['tipo_evento'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($elemento_id)) {
    $where_conditions[] = "h.elemento_id = ?";
    $params[] = $elemento_id;
}

if (!empty($tipo_evento)) {
    $where_conditions[] = "h.tipo_evento = ?";
    $params[] = $tipo_evento;
}

if (!empty($fecha_desde)) {
    $where_conditions[] = "h.fecha_evento >= ?";
    $params[] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $where_conditions[] = "h.fecha_evento <= ?";
    $params[] = $fecha_hasta;
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

// Obtener historial
$historial = $db->fetchAll(
    "SELECT h.*, i.codigo, i.nombre as elemento_nombre, u.nombre as usuario_nombre
     FROM historial_elementos h
     LEFT JOIN inventario i ON h.elemento_id = i.id
     LEFT JOIN usuarios u ON h.usuario_registro = u.id
     $where_clause
     ORDER BY h.fecha_evento DESC, h.fecha_registro DESC",
    $params
);

// Obtener elementos para el filtro
$elementos = $db->fetchAll("SELECT id, codigo, nombre FROM inventario ORDER BY nombre");

$message = getMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Productos - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php $nav_active = ''; include 'include_nav.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Historial de Productos</h1>
            <div class="header-actions">
                <a href="exportar_historial.php<?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" 
                   class="btn btn-success" target="_blank">
                    <i class="icon-download"></i> Exportar a PDF
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['message']; ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <select name="elemento_id">
                        <option value="">Todos los productos</option>
                        <?php foreach ($elementos as $elem): ?>
                            <option value="<?php echo $elem['id']; ?>" <?php echo $elemento_id == $elem['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($elem['codigo'] . ' - ' . $elem['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="tipo_evento">
                        <option value="">Todos los eventos</option>
                        <option value="adquisicion" <?php echo $tipo_evento == 'adquisicion' ? 'selected' : ''; ?>>Adquisición</option>
                        <option value="retiro" <?php echo $tipo_evento == 'retiro' ? 'selected' : ''; ?>>Retiro</option>
                        <option value="devolucion" <?php echo $tipo_evento == 'devolucion' ? 'selected' : ''; ?>>Devolución</option>
                        <option value="mantenimiento" <?php echo $tipo_evento == 'mantenimiento' ? 'selected' : ''; ?>>Mantenimiento</option>
                    </select>
                    
                    <input type="date" name="fecha_desde" placeholder="Fecha desde" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                    <input type="date" name="fecha_hasta" placeholder="Fecha hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                    
                    <button type="submit" class="btn btn-secondary">Filtrar</button>
                    <a href="historial.php" class="btn btn-light">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- Estadísticas rápidas -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3><?php echo count($historial); ?></h3>
                <p>Registros encontrados</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($historial, function($h) { return $h['tipo_evento'] == 'retiro'; })); ?></h3>
                <p>Retiros</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($historial, function($h) { return $h['tipo_evento'] == 'adquisicion'; })); ?></h3>
                <p>Adquisiciones</p>
            </div>
        </div>

        <!-- Tabla de historial -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Código</th>
                        <th>Tipo de Evento</th>
                        <th>Descripción</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historial)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No se encontraron registros</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historial as $registro): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($registro['fecha_evento'])); ?></td>
                                <td><?php echo htmlspecialchars($registro['elemento_nombre'] ?? 'Producto eliminado'); ?></td>
                                <td><?php echo htmlspecialchars($registro['codigo'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $registro['tipo_evento']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $registro['tipo_evento'])); ?>
                                    </span>
                                </td>
                                <td class="descripcion-cell">
                                    <?php echo htmlspecialchars($registro['descripcion']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($registro['usuario_nombre'] ?? 'Usuario desconocido'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Funcionalidad del dropdown
        document.querySelector('.dropdown-toggle').addEventListener('click', function(e) {
            e.preventDefault();
            this.parentElement.querySelector('.dropdown-menu').classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });

        // Auto-submit de filtros
        document.querySelectorAll('.filter-form select, .filter-form input[type="date"]').forEach(element => {
            element.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>

    <style>
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card h3 {
            font-size: 2rem;
            color: #3498db;
            margin: 0 0 0.5rem 0;
        }

        .stat-card p {
            color: #666;
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .badge-retiro {
            background-color: #e74c3c;
            color: white;
        }

        .badge-adquisicion {
            background-color: #27ae60;
            color: white;
        }

        .badge-devolucion {
            background-color: #3498db;
            color: white;
        }

        .badge-mantenimiento {
            background-color: #f39c12;
            color: white;
        }

        .descripcion-cell {
            max-width: 300px;
            word-wrap: break-word;
            white-space: normal;
        }

        .icon-download::before {
            content: "⬇";
            margin-right: 0.5rem;
        }
    </style>
</body>
</html>