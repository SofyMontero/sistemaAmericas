<?php
ob_start();
include_once "header.php"; 
require_once "../models/ProductoModel.php";

// Obtener productos desde la base de datos
$producto = new Producto();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'actualizar_producto') {
    $idProducto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
    $codigoProducto = trim($_POST['codigo_productos'] ?? '');
    $descripcionProducto = trim($_POST['descripcion_producto'] ?? '');
    $cantidadPaca = $_POST['cantidad_paca_producto'] ?? 0;
    $precioUnidad = $_POST['precio_unidad_producto'] ?? 0;
    $precioPaca = $_POST['precio_paca_producto'] ?? 0;
    $categoria = $_POST['id_cate_producto'] ?? 1;
    $actiUnidad = $_POST['acti_Unidad'] ?? 0;
    $imagenProducto = trim($_POST['imagen_producto'] ?? '');
    $estadoProducto = $_POST['estado_producto'] ?? '1';

    try {
        if (!$idProducto || $codigoProducto === '' || $descripcionProducto === '') {
            throw new Exception('Completa los campos obligatorios del producto.');
        }

        $actualizado = $producto->actualizarProducto(
            $idProducto,
            $codigoProducto,
            $descripcionProducto,
            $cantidadPaca,
            $precioUnidad,
            $precioPaca,
            $categoria,
            $actiUnidad,
            $imagenProducto,
            $estadoProducto
        );

        if (!$actualizado) {
            throw new Exception('No se pudo actualizar el producto.');
        }

        $success = 'producto_actualizado';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cambiar_estado_producto') {
    $idProducto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
    $estadoProducto = $_POST['estado_producto'] ?? null;
    $esAjax = ($_POST['ajax'] ?? '') === '1';

    try {
        if (!$idProducto || !in_array($estadoProducto, ['0', '1'], true)) {
            throw new Exception('No se pudo cambiar el estado del producto.');
        }

        $db = new Database();
        $stmt = $db->connect()->prepare("
            UPDATE productos
            SET estado_producto = :estado_producto
            WHERE id_producto = :id_producto
        ");
        $stmt->execute([
            'estado_producto' => $estadoProducto,
            'id_producto' => $idProducto
        ]);

        if ($esAjax) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Estado actualizado',
                'estado' => $estadoProducto
            ]);
            exit;
        }

        $success = 'estado_actualizado';
    } catch (Exception $e) {
        if ($esAjax) {
            if (ob_get_length()) {
                ob_clean();
            }
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit;
        }

        $error = $e->getMessage();
    }
}

$productos = $producto->obtenerProductosLista(0);
$categorias = $producto->obtenerCategorias();
$categoriasPorId = [];

foreach ($categorias as $categoriaItem) {
    $categoriasPorId[(string) $categoriaItem['id_categoria']] = $categoriaItem['nombre_categoria'];
}

// Capturar parámetros de resultado
$importados = $_GET['importados'] ?? 0;
$actualizados = $_GET['actualizados'] ?? 0;
$errores = $_GET['errores'] ?? 0;
$success = $success ?? ($_GET['success'] ?? null);
$error = $error ?? ($_GET['error'] ?? null);
$creado = $_GET['creado'] ?? null;
$erroresDetalle = isset($_GET['errores_detalle']) ? explode('|', $_GET['errores_detalle']) : [];
$filasVacias = $_GET['filas_vacias'] ?? 0;
$delimitador = $_GET['delimitador'] ?? null;
$encabezado = $_GET['encabezado'] ?? null;
$preciosActualizados = $_GET['precios_actualizados'] ?? 0;
?>

<!-- Estilos para notificaciones toast -->
<link href="../css/subir_excel.css?v=3" rel="stylesheet" type="text/css" />
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css" rel="stylesheet">

<style>
:root {
    --primary-color: #1f5f80;
    --menu-header-gradient: linear-gradient(180deg, #1f5f80 0%, #174b66 100%);
    --secondary-color: #64748b;
    --card-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
}

.modern-card,
.product-modal-content {
    background: white;
    border: none;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

.product-modal-content {
    box-shadow: 0 20px 45px rgb(15 23 42 / 0.18);
}

.rounded-action {
    border-radius: 6px;
    min-width: 2.25rem;
}

.estado-producto-form {
    min-width: 120px;
}

.estado-producto-select {
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.3rem 0.75rem;
    height: auto;
}

.estado-producto-select.estado-activo {
    background: #dcfce7;
    color: #059669;
    border-color: #bbf7d0;
}

.estado-producto-select.estado-inactivo {
    background: #f1f5f9;
    color: #64748b;
    border-color: #e2e8f0;
}

.btn-modern {
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border: none;
}

.btn-primary-modern {
    background: var(--primary-color);
    color: white;
}

.btn-primary-modern:hover {
    background: #1d4ed8;
    color: white;
    transform: translateY(-1px);
}

.btn-outline-modern {
    background: transparent;
    border: 1px solid #d1d5db;
    color: var(--secondary-color);
}

.btn-outline-modern:hover {
    background: #f8fafc;
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.table-modern {
    background: white;
    border-radius: 12px;
    overflow: hidden;
}

.table-modern th {
    border: none;
    padding: 1rem;
    font-weight: 600;
}

.table-modern td {
    border: none;
    padding: 1rem;
    border-top: 1px solid #f1f5f9;
    vertical-align: middle;
}

#tabla-productos thead,
#tabla-productos thead th {
    background: var(--primary-color) !important;
    color: white !important;
    border: none;
}

.dataTables_wrapper {
    padding: 1rem;
}

.dataTables_wrapper .dataTables_info {
    color: #64748b;
    font-size: 0.875rem;
    padding-top: 0.65rem;
}

.dataTables_wrapper .dataTables_length select,
.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 0.4rem 0.65rem;
}

.dataTables_wrapper .dataTables_paginate .page-link {
    min-width: 2.25rem;
    height: 2.25rem;
    padding: 0;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    border-color: #e2e8f0;
    box-shadow: none;
}

.dataTables_wrapper .dataTables_paginate .page-link:hover {
    color: var(--primary-color);
    background: #eff6ff;
    border-color: #bfdbfe;
}

.dataTables_wrapper .dataTables_paginate .page-item.active .page-link {
    background: #009688;
    border-color: #009688;
    color: white;
}

.dataTables_wrapper .dataTables_paginate .page-item.disabled .page-link {
    color: #cbd5e1;
    background: #f8fafc;
}

.module-header {
    background: var(--menu-header-gradient);
    color: white;
    border-radius: 12px;
    padding: 2rem;
    margin: 1rem 1.5rem 2rem;
}

.module-header h1 {
    font-size: 2.25rem;
    line-height: 1.1;
}

.module-header p {
    font-size: 1rem;
}

.section-card-header {
    background: var(--menu-header-gradient);
    color: white;
    border: 0;
    border-radius: 12px 12px 0 0 !important;
    padding: 1rem 1.25rem;
}

.section-card-header .text-primary {
    color: white !important;
}

.filters-bar {
    border-top: 1px solid #f1f5f9;
}

.filters-bar label {
    color: #64748b;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 0.35rem;
}

.filters-bar .form-control {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    min-height: 42px;
}

.bulk-upload-toggle {
    align-items: center;
    border: 0;
    color: white;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    text-align: left;
    width: 100%;
}

.bulk-upload-card {
    border-radius: 12px !important;
    overflow: hidden;
}

.bulk-upload-toggle:hover,
.bulk-upload-toggle:focus {
    color: white;
    outline: none;
}

.bulk-upload-toggle .toggle-icon {
    transition: transform 0.2s ease;
}

.bulk-upload-toggle[aria-expanded="true"] .toggle-icon {
    transform: rotate(180deg);
}

.bulk-upload-summary {
    display: block;
    font-size: 0.85rem;
    font-weight: 400;
    opacity: 0.9;
}
</style>


<!-- Container para las notificaciones toast -->
<div class="toast-container" id="toastContainer"></div>

<!-- Page header -->
<div class="module-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap">
        <div class="d-flex align-items-center">
            <i class="fas fa-box-open fa-2x mr-3"></i>
            <div>
                <h1 class="mb-1 font-weight-bold">Gestión de Productos</h1>
                <p class="mb-0 opacity-90">Carga productos en lote, edita precios y administra el catálogo</p>
            </div>
        </div>
        <button type="button"
                class="btn btn-light btn-lg mt-3 mt-md-0"
                data-toggle="modal"
                data-target="#crearProductoModal"
                id="btnCrearProducto">
            <i class="fas fa-plus-circle"></i> Crear producto
        </button>
    </div>
</div>
<div style="display: none;">
    <p class="text-justify">
        A continuación se presenta la lista de productos disponibles. Puede cargar productos en lote desde un archivo CSV.
    </p>
</div>

<!-- Estadísticas rápidas -->
<?php if ($success && $success !== 'producto_actualizado'): ?>
<div class="stats-card">
    <div class="row">
        <div class="col-md-4">
            <div class="stat-item">
                <span class="stat-number"><?php echo $importados; ?></span>
                <span class="stat-label">Nuevos productos</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-item">
                <span class="stat-number"><?php echo $actualizados; ?></span>
                <span class="stat-label">Productos actualizados</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-item">
                <span class="stat-number"><?php echo count($productos); ?></span>
                <span class="stat-label">Total productos</span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Formulario de carga de Excel -->
<div class="container-fluid">

    <div class="container-fluid px-0 py-3">
        <div class="row no-gutters">
            <div class="col-12">

                <div class="card shadow-lg border-0 rounded-4 bulk-upload-card">
                    <button class="card-header section-card-header bulk-upload-toggle"
                        type="button"
                        data-toggle="collapse"
                        data-target="#actualizacionMasivaProductos"
                        aria-expanded="false"
                        aria-controls="actualizacionMasivaProductos">
                        <span>
                            <span class="h4 mb-0 d-block">💰 Actualización Masiva de Precios</span>
                            <span class="bulk-upload-summary">Importa productos y actualiza precios automáticamente</span>
                        </span>
                        <i class="fas fa-chevron-down toggle-icon" aria-hidden="true"></i>
                    </button>

                    <div class="card-body p-4 collapse" id="actualizacionMasivaProductos">
                        <div class="template-download mb-4 text-center">
                            <p class="text-muted mb-2">
                                Descarga la plantilla con los productos actuales, edita precios y vuelve a subirla.
                            </p>
                            <a href="../controllers/ProductoController.php?accion=descargar_plantilla"
                               class="btn btn-modern btn-outline-modern"
                               id="btnDescargarPlantilla">
                                <i class="fas fa-download"></i> Descargar plantilla actualizada
                            </a>
                            <small class="d-block text-muted mt-2">
                                Incluye <?php echo count($productos); ?> productos · Formato CSV
                            </small>
                        </div>

                        <hr class="my-4">

                        <form id="uploadForm" action="../controllers/ProductoController.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="importar" value="1">
                            <div id="uploadProcessingOverlay">Procesando archivo...</div>
                            <div class="upload-zone" id="uploadZone">
                                <div class="upload-icon">💰</div>
                                <h5>Arrastra tu archivo CSV de precios aquí</h5>
                                <p class="text-muted">El sistema actualizará automáticamente los productos existentes</p>
                                <input type="file" name="archivo_excel" id="archivo_excel" class="file-input" accept=".csv">
                                <div class="progress-bar">
                                    <div class="progress-fill"></div>
                                </div>
                            </div>

                            <div class="mt-3 text-center">
                                <button type="button" class="btn btn-success btn-lg" id="submitBtn" disabled title="Selecciona un archivo CSV válido para continuar">
                                    <i class="fas fa-upload"></i> Procesar Archivo
                                </button>
                                <small id="submitHint" class="d-block text-muted mt-2">Selecciona un archivo CSV para habilitar el procesamiento</small>
                            </div>
                        </form>
                    </div>

                </div>

            </div>
        </div>
    </div>

</div>

<!-- Lista de productos -->
<div class="container-fluid mt-4">
    <div class="modern-card">
        <div class="card-header section-card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-list text-primary"></i> &nbsp; Lista de Productos</h5>
            <button type="button"
                    class="btn btn-light btn-sm"
                    data-toggle="modal"
                    data-target="#crearProductoModal">
                <i class="fas fa-plus"></i> Nuevo
            </button>
        </div>
        <div class="filters-bar bg-white px-3 py-3">
            <div class="row align-items-end">
                <div class="col-md-4 col-lg-3">
                    <label for="filtro-categoria-productos">Filtrar por categoria</label>
                    <select class="form-control" id="filtro-categoria-productos">
                        <option value="">Todas las categorias</option>
                        <?php foreach ($categorias as $categoriaItem): ?>
                            <option value="<?php echo htmlspecialchars($categoriaItem['nombre_categoria']); ?>">
                                <?php echo htmlspecialchars($categoriaItem['nombre_categoria']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 col-lg-3 mt-3 mt-md-0">
                    <button type="button" class="btn btn-outline-modern btn-block" id="limpiar-filtro-categoria">
                        <i class="fas fa-eraser mr-2"></i>Limpiar filtro
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-modern table-sm mb-0" id="tabla-productos">
                    <thead>
                        <tr class="text-center roboto-medium">
                            <th>ID</th>
                            <th>CODIGO</th>
                            <th>PRODUCTO</th>
                            <th>EMBALAGE</th>
                            <th>PRECIO UNIDAD</th>
                            <th>PRECIO PACA</th>
                            <th>CATEGORIA</th>
                            <th>U o P</th>
                            <th>IMAGEN</th>
                            <th>ESTADO</th>
                            <th>ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php if (!empty($productos)): ?>
                            <?php $contador = 1; foreach ($productos as $prod): ?>
                            <tr class="text-center producto-row"
                                data-id="<?php echo (int)$prod['id_producto']; ?>">
                                <td><?php echo htmlspecialchars($prod['id_producto']); ?></td>
                                <td><?php echo htmlspecialchars($prod['codigo_productos']); ?></td>
                                <td><?php echo htmlspecialchars($prod['descripcion_producto']); ?></td>
                                <td><?php echo htmlspecialchars($prod['cantidad_paca_producto']); ?></td>
                                <td data-order="<?php echo (float)$prod['precio_unidad_producto']; ?>">
                                    <div class="price-edit-wrap">
                                        <span class="price-prefix">$</span>
                                        <input type="number"
                                               class="form-control form-control-sm price-input precio-unidad"
                                               min="1"
                                               step="1"
                                               value="<?php echo (float)$prod['precio_unidad_producto']; ?>"
                                               data-original="<?php echo (float)$prod['precio_unidad_producto']; ?>"
                                               title="Editar precio unidad">
                                    </div>
                                </td>
                                <td data-order="<?php echo (float)$prod['precio_paca_producto']; ?>">
                                    <div class="price-edit-wrap">
                                        <span class="price-prefix">$</span>
                                        <input type="number"
                                               class="form-control form-control-sm price-input precio-paca"
                                               min="0"
                                               step="1"
                                               value="<?php echo (float)$prod['precio_paca_producto']; ?>"
                                               data-original="<?php echo (float)$prod['precio_paca_producto']; ?>"
                                               title="Editar precio paca">
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($categoriasPorId[(string) $prod['id_cate_producto']] ?? $prod['id_cate_producto']); ?></td>
                                <td><?php echo htmlspecialchars($prod['acti_Unidad']); ?></td>
                                <td>
                                    <?php if (!empty($prod['imagen_producto'])): ?>
                                        <a href="<?php echo htmlspecialchars($prod['imagen_producto']); ?>" target="_blank" rel="noopener noreferrer">Ver</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form action="" method="POST" class="estado-producto-form mb-0">
                                        <input type="hidden" name="action" value="cambiar_estado_producto">
                                        <input type="hidden" name="id_producto" value="<?php echo htmlspecialchars($prod['id_producto']); ?>">
                                        <select name="estado_producto"
                                            class="form-control form-control-sm estado-producto-select <?php echo (string) $prod['estado_producto'] === '1' ? 'estado-activo' : 'estado-inactivo'; ?>"
                                            data-estado-anterior="<?php echo htmlspecialchars($prod['estado_producto']); ?>">
                                            <option value="1" <?php echo (string) $prod['estado_producto'] === '1' ? 'selected' : ''; ?>>Activo</option>
                                            <option value="0" <?php echo (string) $prod['estado_producto'] === '0' ? 'selected' : ''; ?>>Inactivo</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="text-nowrap">
                                    <button type="button"
                                        class="btn btn-success btn-sm rounded-action btn-guardar-precio"
                                        title="Guardar precios"
                                        disabled>
                                        <i class="fas fa-save"></i>
                                    </button>
                                    <button type="button"
                                        class="btn btn-outline-primary btn-sm rounded-action editar-producto-btn"
                                        title="Editar producto"
                                        data-producto="<?php echo htmlspecialchars(json_encode($prod), ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="productoModal" tabindex="-1" role="dialog" aria-labelledby="productoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content product-modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <i class="fas fa-edit text-primary mr-2"></i>
                    <h5 class="modal-title mb-0 font-weight-bold" id="productoModalLabel">Editar Producto</h5>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <form action="" method="POST" id="form-editar-producto" autocomplete="off">
                    <input type="hidden" name="action" value="actualizar_producto">
                    <input type="hidden" name="id_producto" id="edit_id_producto">

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_codigo_productos">Codigo <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="codigo_productos" id="edit_codigo_productos" required>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="edit_descripcion_producto">Producto <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="descripcion_producto" id="edit_descripcion_producto" maxlength="250" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_cantidad_paca_producto">Embalaje</label>
                                <input type="number" class="form-control" name="cantidad_paca_producto" id="edit_cantidad_paca_producto" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_precio_unidad_producto">Precio unidad</label>
                                <input type="number" class="form-control" name="precio_unidad_producto" id="edit_precio_unidad_producto" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_precio_paca_producto">Precio paca</label>
                                <input type="number" class="form-control" name="precio_paca_producto" id="edit_precio_paca_producto" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_id_cate_producto">Categoria</label>
                                <select class="form-control" name="id_cate_producto" id="edit_id_cate_producto">
                                    <?php foreach ($categorias as $categoriaItem): ?>
                                        <option value="<?php echo htmlspecialchars($categoriaItem['id_categoria']); ?>">
                                            <?php echo htmlspecialchars($categoriaItem['nombre_categoria']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_acti_Unidad">Venta por unidad</label>
                                <select class="form-control" name="acti_Unidad" id="edit_acti_Unidad">
                                    <option value="1">Si</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_estado_producto">Estado</label>
                                <select class="form-control" name="estado_producto" id="edit_estado_producto">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="edit_imagen_producto">Imagen</label>
                                <input type="text" class="form-control" name="imagen_producto" id="edit_imagen_producto" maxlength="250">
                            </div>
                        </div>
                    </div>

                    <div class="text-center border-top pt-3 mt-3">
                        <button type="button" class="btn btn-modern btn-outline-modern" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-modern btn-primary-modern ml-2">
                            <i class="fas fa-save mr-2"></i>Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal crear producto -->
<div class="modal fade" id="crearProductoModal" tabindex="-1" role="dialog" aria-labelledby="crearProductoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form method="POST"
              action="../controllers/ProductoController.php"
              enctype="multipart/form-data"
              class="modal-content product-modal-content"
              id="formCrearProducto">
            <input type="hidden" name="accion" value="crear_producto">

            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <i class="fas fa-plus-circle text-primary mr-2"></i>
                    <h5 class="modal-title mb-0 font-weight-bold" id="crearProductoModalLabel">Crear producto</h5>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="codigo_productos">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="codigo_productos" name="codigo_productos" required maxlength="50" placeholder="Ej: 1001">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="id_cate_producto">Categoría <span class="text-danger">*</span></label>
                            <select class="form-control" id="id_cate_producto" name="id_cate_producto" required>
                                <?php if (!empty($categorias)): ?>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo (int)$cat['id_categoria']; ?>">
                                            <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="1">Sin categorías</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="descripcion_producto">Descripción / Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="descripcion_producto" name="descripcion_producto" required maxlength="250" placeholder="Ej: Aguardiente Antioqueño 750ml">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="cantidad_paca_producto">Embalaje (unid. por paca)</label>
                            <input type="number" class="form-control" id="cantidad_paca_producto" name="cantidad_paca_producto" min="1" step="1" value="1">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="precio_unidad_producto">Precio unidad <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="precio_unidad_producto" name="precio_unidad_producto" min="1" step="1" required placeholder="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="precio_paca_producto">Precio paca</label>
                            <input type="number" class="form-control" id="precio_paca_producto" name="precio_paca_producto" min="0" step="1" value="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="acti_Unidad">Venta por unidad</label>
                            <select class="form-control" id="acti_Unidad" name="acti_Unidad">
                                <option value="1" selected>Sí</option>
                                <option value="0">No (solo paca)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="estado_producto">Estado</label>
                            <select class="form-control" id="estado_producto" name="estado_producto">
                                <option value="1" selected>Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="imagen">Imagen</label>
                            <input type="file" class="form-control-file" id="imagen" name="imagen" accept="image/*">
                            <small class="form-text text-muted">Opcional. JPG, PNG, GIF o WEBP (máx. 3MB)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-modern btn-outline-modern" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-modern btn-primary-modern" id="btnGuardarProducto">
                    <i class="fas fa-save mr-2"></i>Guardar producto
                </button>
            </div>
        </form>
    </div>
</div>

<?php include_once "footer.php"; ?>

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let tablaProductos = null;

    if (window.jQuery && $.fn.DataTable) {
        tablaProductos = $('#tabla-productos').DataTable({
            pageLength: 50,
            lengthMenu: [[50, 100, 200, -1], [50, 100, 200, 'Todos']],
            order: [[0, 'desc']],
            columnDefs: [
                { targets: 10, orderable: false, searchable: false }
            ],
            language: {
                decimal: '',
                emptyTable: 'No hay productos registrados',
                info: '_START_ - _END_ de _TOTAL_ productos',
                infoEmpty: '0 productos',
                infoFiltered: '(filtrado de _MAX_ productos en total)',
                lengthMenu: 'Mostrar _MENU_ productos',
                loadingRecords: 'Cargando...',
                processing: 'Procesando...',
                search: 'Buscar:',
                zeroRecords: 'No se encontraron productos',
                paginate: {
                    first: '<i class="fas fa-angle-double-left" aria-hidden="true"></i>',
                    last: '<i class="fas fa-angle-double-right" aria-hidden="true"></i>',
                    next: '<i class="fas fa-chevron-right" aria-hidden="true"></i>',
                    previous: '<i class="fas fa-chevron-left" aria-hidden="true"></i>'
                }
            }
        });

        $('#filtro-categoria-productos').on('change', function() {
            const categoria = this.value;
            const busqueda = categoria ? '^' + $.fn.dataTable.util.escapeRegex(categoria) + '$' : '';

            tablaProductos
                .column(6)
                .search(busqueda, true, false)
                .draw();
        });

        $('#limpiar-filtro-categoria').on('click', function() {
            $('#filtro-categoria-productos').val('');

            tablaProductos
                .column(6)
                .search('')
                .draw();
        });
    }

    document.querySelectorAll('.editar-producto-btn').forEach(button => {
        button.addEventListener('click', function() {
            const producto = JSON.parse(this.dataset.producto);

            document.getElementById('edit_id_producto').value = producto.id_producto || '';
            document.getElementById('edit_codigo_productos').value = producto.codigo_productos || '';
            document.getElementById('edit_descripcion_producto').value = producto.descripcion_producto || '';
            document.getElementById('edit_cantidad_paca_producto').value = producto.cantidad_paca_producto || 0;
            document.getElementById('edit_precio_unidad_producto').value = producto.precio_unidad_producto || 0;
            document.getElementById('edit_precio_paca_producto').value = producto.precio_paca_producto || 0;
            document.getElementById('edit_id_cate_producto').value = producto.id_cate_producto || 1;
            document.getElementById('edit_acti_Unidad').value = String(producto.acti_Unidad ?? 0);
            document.getElementById('edit_estado_producto').value = String(producto.estado_producto ?? '1');
            document.getElementById('edit_imagen_producto').value = producto.imagen_producto || '';

            $('#productoModal').modal('show');
        });
    });

    document.querySelectorAll('.estado-producto-select').forEach(select => {
        select.addEventListener('change', function() {
            const form = this.closest('form');
            const formData = new FormData(form);
            const estadoAnterior = this.dataset.estadoAnterior;

            formData.append('ajax', '1');
            this.disabled = true;

            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json().then(data => {
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'No se pudo actualizar el estado');
                }
                return data;
            }))
            .then(data => {
                const estado = String(data.estado);
                this.dataset.estadoAnterior = estado;
                this.classList.toggle('estado-activo', estado === '1');
                this.classList.toggle('estado-inactivo', estado !== '1');
                showToast('success', 'Estado actualizado', 'El estado del producto se guardo correctamente.');
            })
            .catch(error => {
                this.value = estadoAnterior;
                this.classList.toggle('estado-activo', estadoAnterior === '1');
                this.classList.toggle('estado-inactivo', estadoAnterior !== '1');
                showToast('error', 'Error', error.message);
            })
            .finally(() => {
                this.disabled = false;
            });
        });
    });
});

// Sistema de notificaciones toast
function showToast(type, title, message, details = []) {
    const toastContainer = document.getElementById('toastContainer');
    const toastId = 'toast-' + Date.now();
    
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️'
    };
    
    let detailsHtml = '';
    if (details.length > 0) {
        detailsHtml = '<div class="toast-details">' + details.map(d => '• ' + d).join('<br>') + '</div>';
    }
    
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${icons[type]}</span>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
            ${detailsHtml}
        </div>
        <button class="toast-close" onclick="closeToast('${toastId}')">&times;</button>
        <div class="toast-progress"></div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Auto cerrar después de 5 segundos
    setTimeout(() => closeToast(toastId), 5000);
}

function closeToast(toastId) {
    const toast = document.getElementById(toastId);
    if (toast) {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }
}

// Mostrar notificaciones según los parámetros URL
<?php if ($creado): ?>
    showToast('success', 'Producto creado', 'El producto se registró correctamente.');
<?php elseif ($success): ?>
    <?php if ($success === 'producto_actualizado'): ?>
        showToast('success', 'Producto actualizado', 'Los cambios se guardaron correctamente.');
    <?php endif; ?>
    <?php if ($success === 'estado_actualizado'): ?>
        showToast('success', 'Estado actualizado', 'El estado del producto se guardo correctamente.');
    <?php endif; ?>
    <?php if ($importados > 0 || $actualizados > 0): ?>
        let message = '';
        if (<?php echo $importados; ?> > 0) message += '<?php echo $importados; ?> productos importados. ';
        if (<?php echo $actualizados; ?> > 0) message += '<?php echo $actualizados; ?> productos actualizados.';
        
        <?php if ($errores > 0): ?>
            showToast('warning', 'Proceso completado', message + ' <?php echo $errores; ?> errores encontrados.', <?php echo json_encode($erroresDetalle); ?>);
        <?php else: ?>
            showToast('success', '¡Éxito!', message);
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php if ($error): ?>
    showToast('error', 'Error', <?php echo json_encode($error); ?>);
<?php endif; ?>

// Drag and drop functionality
const uploadZone = document.getElementById('uploadZone');
const fileInput = document.getElementById('archivo_excel');
const uploadForm = document.querySelector('#uploadForm');
const submitHint = document.getElementById('submitHint');
const progressBar = document.querySelector('.progress-bar');
const progressFill = document.querySelector('.progress-fill');
const overlayProceso = document.getElementById('uploadProcessingOverlay');
const submitBtnDefaultHtml = '<i class="fas fa-upload"></i> Procesar Archivo';
const zonaTituloDefault = uploadZone.querySelector('h5').textContent;
const zonaTextoDefault = uploadZone.querySelector('p').textContent;
const zonaIconoDefault = uploadZone.querySelector('.upload-icon').textContent;
let archivoValido = false;
let procesando = false;

function botonProcesar() {
    return document.getElementById('submitBtn');
}

function aplicarEstiloBloqueo(btn) {
    btn.disabled = true;
    btn.setAttribute('disabled', 'disabled');
    btn.setAttribute('aria-disabled', 'true');
    btn.classList.add('disabled', 'is-processing');
    btn.classList.remove('btn-success');
    btn.classList.add('btn-secondary');
    btn.style.setProperty('background-color', '#9e9e9e', 'important');
    btn.style.setProperty('background-image', 'none', 'important');
    btn.style.setProperty('border-color', '#9e9e9e', 'important');
    btn.style.setProperty('color', '#fff', 'important');
    btn.style.setProperty('opacity', '0.7', 'important');
    btn.style.setProperty('pointer-events', 'none', 'important');
    btn.style.setProperty('cursor', 'not-allowed', 'important');
    btn.style.setProperty('box-shadow', 'none', 'important');
    btn.style.setProperty('filter', 'grayscale(1)', 'important');
}

function quitarEstiloBloqueo(btn) {
    btn.classList.remove('disabled', 'is-processing', 'btn-secondary');
    btn.classList.add('btn-success');
    btn.removeAttribute('aria-disabled');
    btn.style.removeProperty('background-color');
    btn.style.removeProperty('background-image');
    btn.style.removeProperty('border-color');
    btn.style.removeProperty('color');
    btn.style.removeProperty('opacity');
    btn.style.removeProperty('pointer-events');
    btn.style.removeProperty('cursor');
    btn.style.removeProperty('box-shadow');
    btn.style.removeProperty('filter');
}

function setBotonProcesar(listo, mensajeHint = '') {
    if (procesando) {
        return;
    }
    const btn = botonProcesar();
    archivoValido = !!listo;
    btn.innerHTML = submitBtnDefaultHtml;
    if (listo) {
        btn.disabled = false;
        btn.removeAttribute('disabled');
        quitarEstiloBloqueo(btn);
        btn.title = 'Procesar el archivo seleccionado';
        submitHint.textContent = mensajeHint || 'Archivo listo. Puedes procesarlo.';
    } else {
        aplicarEstiloBloqueo(btn);
        btn.title = 'Selecciona un archivo CSV válido para continuar';
        submitHint.textContent = mensajeHint || 'Selecciona un archivo CSV para habilitar el procesamiento';
    }
}

function bloquearDuranteProceso() {
    procesando = true;
    archivoValido = false;
    const btn = botonProcesar();
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    btn.title = 'El archivo se está procesando';
    aplicarEstiloBloqueo(btn);
    submitHint.textContent = 'Procesando el archivo, espera un momento...';
    uploadZone.classList.add('is-processing');
    progressBar.style.display = 'block';
    if (overlayProceso) {
        overlayProceso.classList.add('is-active');
    }
}

function resetZonaCarga() {
    if (procesando) {
        return;
    }
    archivoValido = false;
    uploadZone.classList.remove('is-processing');
    uploadZone.querySelector('h5').textContent = zonaTituloDefault;
    uploadZone.querySelector('p').textContent = zonaTextoDefault;
    uploadZone.querySelector('.upload-icon').textContent = zonaIconoDefault;
    progressBar.style.display = 'none';
    progressFill.style.width = '0%';
    if (overlayProceso) {
        overlayProceso.classList.remove('is-active');
    }
    setBotonProcesar(false);
}

function iniciarProcesamiento() {
    if (procesando) {
        return false;
    }
    if (!archivoValido || !fileInput.files[0]) {
        showToast('error', 'Sin archivo', 'Espera a que el archivo se cargue correctamente');
        return false;
    }

    bloquearDuranteProceso();
    showToast('warning', 'Procesando archivo', 'El archivo se está procesando. No cierres esta página.');

    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 20;
        if (progress > 90) progress = 90;
        progressFill.style.width = progress + '%';
    }, 120);

    setTimeout(() => {
        progressFill.style.width = '90%';
        clearInterval(interval);
        uploadForm.submit();
    }, 200);

    return true;
}

uploadZone.addEventListener('click', (e) => {
    if (procesando) {
        e.preventDefault();
        e.stopPropagation();
        return;
    }
    if (e.target === fileInput) {
        return;
    }
    fileInput.click();
});

// Drag and drop events
uploadZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    if (procesando) {
        return;
    }
    uploadZone.classList.add('dragover');
});

uploadZone.addEventListener('dragleave', () => {
    uploadZone.classList.remove('dragover');
});

uploadZone.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    if (procesando) {
        return;
    }

    const files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        handleFileSelect();
    }
});

// File selection handler
fileInput.addEventListener('change', handleFileSelect);

function handleFileSelect() {
    if (procesando) {
        return;
    }

    const file = fileInput.files[0];
    if (!file) {
        resetZonaCarga();
        return;
    }

    const fileName = file.name;
    const fileSize = (file.size / 1024 / 1024).toFixed(2);

    archivoValido = false;
    const btnCarga = botonProcesar();
    btnCarga.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando archivo...';
    aplicarEstiloBloqueo(btnCarga);
    submitHint.textContent = 'Espera a que el archivo se cargue correctamente';

    if (!fileName.toLowerCase().endsWith('.csv')) {
        showToast('error', 'Archivo inválido', 'Solo se permiten archivos CSV');
        fileInput.value = '';
        resetZonaCarga();
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        showToast('error', 'Archivo muy grande', 'El archivo no debe superar los 5MB');
        fileInput.value = '';
        resetZonaCarga();
        return;
    }

    const reader = new FileReader();
    reader.onload = function (event) {
        if (procesando) {
            return;
        }
        const contenido = event.target.result;
        if (!contenido || !String(contenido).trim()) {
            showToast('error', 'Archivo vacío', 'El CSV no tiene contenido para procesar');
            fileInput.value = '';
            resetZonaCarga();
            return;
        }

        uploadZone.querySelector('h5').textContent = fileName;
        uploadZone.querySelector('p').textContent = `${fileSize} MB - Listo para procesar`;
        uploadZone.querySelector('.upload-icon').textContent = '📄✅';
        setBotonProcesar(true, `${fileName} cargado. Ya puedes procesarlo.`);
        showToast('success', 'Archivo listo', `${fileName} (${fileSize} MB)`);
    };
    reader.onerror = function () {
        showToast('error', 'Error de carga', 'No se pudo leer el archivo. Inténtalo de nuevo.');
        fileInput.value = '';
        resetZonaCarga();
    };
    reader.readAsText(file);
}

// Bloquear en el primer toque, antes de que Material Design u otros scripts lo manejen
['pointerdown', 'click'].forEach(function (evt) {
    document.addEventListener(evt, function (e) {
        const btn = e.target.closest('#submitBtn');
        if (!btn) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        iniciarProcesamiento();
    }, true);
});

uploadForm.addEventListener('submit', (e) => {
    e.preventDefault();
    iniciarProcesamiento();
});

aplicarEstiloBloqueo(botonProcesar());

// Feedback al guardar producto desde el modal
const formCrearProducto = document.getElementById('formCrearProducto');
const btnGuardarProducto = document.getElementById('btnGuardarProducto');
if (formCrearProducto) {
    formCrearProducto.addEventListener('submit', () => {
        btnGuardarProducto.disabled = true;
        btnGuardarProducto.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    });
}

// Edición inline de precios en la lista
function marcarFilaCambiada(row) {
    const unidadInput = row.querySelector('.precio-unidad');
    const pacaInput = row.querySelector('.precio-paca');
    const btnGuardar = row.querySelector('.btn-guardar-precio');
    const cambio =
        Number(unidadInput.value) !== Number(unidadInput.dataset.original) ||
        Number(pacaInput.value) !== Number(pacaInput.dataset.original);

    btnGuardar.disabled = !cambio;
    row.classList.toggle('precio-modificado', cambio);
}

function sincronizarFilaGuardada(row, precioUnidad, precioPaca) {
    const celdaUnidad = row.querySelector('.precio-unidad').closest('td');
    const celdaPaca = row.querySelector('.precio-paca').closest('td');
    celdaUnidad.dataset.order = String(precioUnidad);
    celdaPaca.dataset.order = String(precioPaca);

    const btnEditar = row.querySelector('.editar-producto-btn');
    if (btnEditar) {
        const producto = JSON.parse(btnEditar.dataset.producto);
        producto.precio_unidad_producto = precioUnidad;
        producto.precio_paca_producto = precioPaca;
        btnEditar.dataset.producto = JSON.stringify(producto);
    }

    if (window.jQuery && $.fn.DataTable && $.fn.DataTable.isDataTable('#tabla-productos')) {
        $('#tabla-productos').DataTable().row(row).invalidate('dom');
    }
}

async function guardarPreciosFila(row) {
    const id = row.dataset.id;
    const unidadInput = row.querySelector('.precio-unidad');
    const pacaInput = row.querySelector('.precio-paca');
    const btnGuardar = row.querySelector('.btn-guardar-precio');
    const precioUnidad = Number(unidadInput.value);
    const precioPaca = Number(pacaInput.value);

    if (!precioUnidad || precioUnidad <= 0) {
        showToast('error', 'Precio inválido', 'El precio unidad debe ser mayor a 0');
        unidadInput.focus();
        return;
    }

    if (precioPaca < 0) {
        showToast('error', 'Precio inválido', 'El precio paca no puede ser negativo');
        pacaInput.focus();
        return;
    }

    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    const formData = new FormData();
    formData.append('accion', 'actualizar_precios');
    formData.append('id_producto', id);
    formData.append('precio_unidad_producto', precioUnidad);
    formData.append('precio_paca_producto', precioPaca);

    try {
        const response = await fetch('../controllers/ProductoController.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            unidadInput.dataset.original = String(precioUnidad);
            pacaInput.dataset.original = String(precioPaca);
            sincronizarFilaGuardada(row, precioUnidad, precioPaca);
            row.classList.remove('precio-modificado');
            row.classList.add('precio-guardado');
            setTimeout(() => row.classList.remove('precio-guardado'), 1200);
            showToast('success', 'Precios guardados', data.message || 'Actualización correcta');
        } else {
            showToast('error', 'Error', data.message || 'No se pudieron guardar los precios');
        }
    } catch (err) {
        showToast('error', 'Error de red', 'No se pudo conectar con el servidor');
    } finally {
        btnGuardar.innerHTML = '<i class="fas fa-save"></i>';
        marcarFilaCambiada(row);
    }
}

// Delegado en document porque DataTables saca del DOM las filas de otras páginas
document.addEventListener('input', (e) => {
    const input = e.target.closest('.producto-row .price-input');
    if (input) {
        marcarFilaCambiada(input.closest('.producto-row'));
    }
});

document.addEventListener('keydown', (e) => {
    const input = e.target.closest('.producto-row .price-input');
    if (!input || e.key !== 'Enter') {
        return;
    }
    e.preventDefault();
    const row = input.closest('.producto-row');
    if (!row.querySelector('.btn-guardar-precio').disabled) {
        guardarPreciosFila(row);
    }
});

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.producto-row .btn-guardar-precio');
    if (btn && !btn.disabled) {
        guardarPreciosFila(btn.closest('.producto-row'));
    }
});

// Limpiar parámetros URL después de mostrar notificaciones
if (window.location.search) {
    const url = window.location.protocol + "//" + window.location.host + window.location.pathname;
    window.history.replaceState({path: url}, '', url);
}
</script>
