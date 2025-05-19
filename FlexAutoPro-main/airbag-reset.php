
<?php
// Importante: Iniciar buffer de salida antes de cualquier otra operación
ob_start();
session_start();

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/includes/db.php';

// Verificar login
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

// Variables de usuario
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$user_type = $_SESSION['user_role'] ?? 'user';
$email = $_SESSION['email'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// IMPORTANTE: Variables requeridas por layout.php
$page_title = 'مسح وإعادة ضبط بيانات الإيرباق';
$display_title = 'نظام مسح وإعادة ضبط الإيرباق';

// Variables de búsqueda
$query = $_GET['query'] ?? '';
$selected_brand = $_GET['brand'] ?? '';
$selected_model = $_GET['model'] ?? '';
$selected_ecu = $_GET['ecu'] ?? '';

// Mensajes
$success_message = '';
$error_message = '';

// Variables de resultados
$ecu_data = null;
$has_result = false;
$search_message = '';
$search_results = [];

// Procesamiento de upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_dump'])) {
    // Verificar CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'خطأ في التحقق من الأمان. يرجى تحديث الصفحة والمحاولة مرة أخرى.';
    } else {
        $ecu_id = (int)$_POST['ecu_id'];
        
        // Verificar archivo
        if (!isset($_FILES['dump_file']) || $_FILES['dump_file']['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'حدث خطأ أثناء تحميل الملف. يرجى المحاولة مرة أخرى.';
        } else {
            // Verificar tipo de archivo
            $file_info = pathinfo($_FILES['dump_file']['name']);
            $file_ext = strtolower($file_info['extension']);
            $allowed_extensions = ['bin', 'hex', 'dump', 'rom', 'dat', 'img', 'eep', 'srec', 'zip'];
            
            // ENHANCEMENT: Add MIME type validation
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($_FILES['dump_file']['tmp_name']);
            $allowed_mimes = [
                'application/octet-stream', 'application/x-binary', 'application/zip',
                'text/plain', 'application/x-zip-compressed'
            ];
            
            // Verificar tamaño (5MB max)
            $max_size = 5 * 1024 * 1024;
            
            if (!in_array($file_ext, $allowed_extensions)) {
                $error_message = 'نوع الملف غير مدعوم. يُسمح فقط بملفات: ' . implode(', ', $allowed_extensions);
            } elseif (!in_array($mime_type, $allowed_mimes)) {
                $error_message = 'نوع محتوى الملف غير مسموح به.';
            } elseif ($_FILES['dump_file']['size'] > $max_size) {
                $error_message = 'حجم الملف كبير جدًا. الحد الأقصى هو 5 ميجابايت.';
            } else {
                try {
                    // Start transaction for database consistency
                    $pdo->beginTransaction();
                    
                    // Obtener información del ECU
                    $ecu_stmt = $pdo->prepare("SELECT brand, model, ecu_number FROM airbag_ecus WHERE id = ?");
                    $ecu_stmt->execute([$ecu_id]);
                    $ecu_info = $ecu_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$ecu_info) {
                        throw new Exception("لم يتم العثور على معلومات ECU");
                    }
                    
                    // Crear directorio si no existe
                    $upload_dir = 'uploads/dump_files';
                    if (!file_exists($upload_dir)) {
                        if (!mkdir($upload_dir, 0755, true)) {
                            throw new Exception("فشل في إنشاء مجلد التحميل");
                        }
                    }
                    
                    // Nombre de archivo único con hash
                    $file_hash = hash_file('sha256', $_FILES['dump_file']['tmp_name']);
                    $file_hash_short = substr($file_hash, 0, 10);
                    $new_filename = $username . '_' . date('Ymd_His') . '_' . $file_hash_short . '_' . $ecu_id . '.' . $file_ext;
                    $upload_path = $upload_dir . '/' . $new_filename;
                    
                    // Mover archivo
                    if (!move_uploaded_file($_FILES['dump_file']['tmp_name'], $upload_path)) {
                        throw new Exception("فشل في نقل الملف المرفوع");
                    }
                    
                    // Validar y obtener datos del formulario
                    $dump_type = isset($_POST['dump_type']) && in_array($_POST['dump_type'], ['eeprom', 'flash', 'cpu']) 
                                ? $_POST['dump_type'] : 'eeprom';
                    $notes = !empty($_POST['notes']) ? htmlspecialchars(trim($_POST['notes'])) : '';
                    $phone = !empty($_POST['phone']) ? htmlspecialchars(trim($_POST['phone'])) : '';
                    $chassis = !empty($_POST['chassis']) ? htmlspecialchars(trim($_POST['chassis'])) : '';
                    
                    // Guardar en base de datos
                    $dump_stmt = $pdo->prepare("
                        INSERT INTO ecu_dumps (
                            ecu_id, username, filename, original_filename, file_path, 
                            dump_type, notes, file_size, file_type, upload_date
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    if (!$dump_stmt->execute([
                        $ecu_id,
                        $username,
                        $new_filename,
                        $_FILES['dump_file']['name'],
                        $upload_path,
                        $dump_type,
                        $notes,
                        $_FILES['dump_file']['size'],
                        $file_ext
                    ])) {
                        throw new Exception("فشل في حفظ بيانات الملف");
                    }
                    
                    $dump_id = $pdo->lastInsertId();
                    
                    // Crear ticket con mejor descripción
                    $service_type = 'إعادة ضبط الإيرباق';
                    $car_type = $ecu_info['brand'] . ' ' . $ecu_info['model'];
                    $description = sprintf(
                        'طلب إعادة ضبط كمبيوتر إيرباق. رقم الكمبيوتر: %s. نوع الدامب: %s. رقم الشاصي: %s. ملاحظات: %s',
                        $ecu_info['ecu_number'],
                        $dump_type,
                        $chassis,
                        $notes
                    );
                    
                    $ticket_stmt = $pdo->prepare("
                        INSERT INTO tickets (
                            username, email, phone, car_type, chassis, service_type, 
                            description, created_at, status, is_seen
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending', 0)
                    ");
                    
                    if (!$ticket_stmt->execute([
                        $username,
                        $email,
                        $phone,
                        $car_type,
                        $chassis,
                        $service_type,
                        $description
                    ])) {
                        throw new Exception("فشل في إنشاء التذكرة");
                    }
                    
                    $ticket_id = $pdo->lastInsertId();
                    
                    // Vincular ticket con archivo
                    $link_stmt = $pdo->prepare("UPDATE ecu_dumps SET ticket_id = ? WHERE id = ?");
                    if (!$link_stmt->execute([$ticket_id, $dump_id])) {
                        throw new Exception("فشل في ربط التذكرة بالملف");
                    }
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    // Success message with ticket ID
                    $success_message = sprintf(
                        'تم تحميل الملف وإنشاء تذكرة بنجاح. رقم التذكرة: #%d. يمكنك متابعة حالة الطلب في صفحة "تذاكري".',
                        $ticket_id
                    );
                } catch (Exception $e) {
                    // Rollback on error
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    
                    $error_message = 'حدث خطأ أثناء معالجة الطلب: ' . $e->getMessage();
                    error_log('Error in airbag-reset.php: ' . $e->getMessage());
                }
            }
        }
    }
}

// Búsqueda directa por ID
if (!empty($_GET['ecu_id'])) {
    $ecu_id = (int)$_GET['ecu_id'];
    
    $stmt = $pdo->prepare("
        SELECT ae.*,
               (SELECT COUNT(*) FROM ecu_images ei WHERE ei.ecu_id = ae.id) as image_count
        FROM airbag_ecus ae
        WHERE ae.id = ?
    ");
    $stmt->execute([$ecu_id]);
    $ecu_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ecu_data) {
        $has_result = true;
        
        // Obtener imágenes si están disponibles
        if ($ecu_data['image_count'] > 0) {
            $images_stmt = $pdo->prepare("
                SELECT * FROM ecu_images WHERE ecu_id = ? ORDER BY display_order ASC
            ");
            $images_stmt->execute([$ecu_id]);
            $ecu_data['images'] = $images_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

// Búsqueda por formulario
if (!empty($_GET['search']) && (
    !empty($selected_brand) || 
    !empty($selected_model) || 
    !empty($selected_ecu) || 
    !empty($query)
)) {
    $search_conditions = [];
    $search_params = [];
    
    if (!empty($selected_brand)) {
        $search_conditions[] = "brand = ?";
        $search_params[] = $selected_brand;
    }
    
    if (!empty($selected_model)) {
        $search_conditions[] = "model = ?";
        $search_params[] = $selected_model;
    }
    
    if (!empty($selected_ecu)) {
        $search_conditions[] = "ecu_number = ?";
        $search_params[] = $selected_ecu;
    }
    
    if (!empty($query)) {
        // More efficient search
        $search_conditions[] = "(
            brand LIKE ? OR 
            model LIKE ? OR 
            ecu_number LIKE ? OR 
            eeprom_type LIKE ?
        )";
        $search_params[] = "%$query%";
        $search_params[] = "%$query%";
        $search_params[] = "%$query%";
        $search_params[] = "%$query%";
    }
    
    if (!empty($search_conditions)) {
        $where_clause = "WHERE " . implode(" AND ", $search_conditions);
        
        // Pagination support
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        
        // Count total results for pagination
        $count_sql = "SELECT COUNT(*) FROM airbag_ecus ae $where_clause";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($search_params);
        $total_results = $count_stmt->fetchColumn();
        $total_pages = ceil($total_results / $per_page);
        
        $sql = "
            SELECT ae.*,
                   (SELECT COUNT(*) FROM ecu_images ei WHERE ei.ecu_id = ae.id) as image_count
            FROM airbag_ecus ae
            $where_clause
            ORDER BY brand, model
            LIMIT $per_page OFFSET $offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($search_params);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($search_results) === 1) {
            // Si solo hay un resultado, mostrarlo directamente
            $ecu_data = $search_results[0];
            $has_result = true;
            
            // Obtener imágenes si están disponibles
            if ($ecu_data['image_count'] > 0) {
                $images_stmt = $pdo->prepare("
                    SELECT * FROM ecu_images WHERE ecu_id = ? ORDER BY display_order ASC
                ");
                $images_stmt->execute([$ecu_data['id']]);
                $ecu_data['images'] = $images_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } elseif (count($search_results) > 1) {
            // Si hay múltiples resultados, mostrar lista para seleccionar
            $search_message = 'تم العثور على ' . count($search_results) . ' نتيجة، اختر واحدة:';
            if ($total_results > $per_page) {
                $search_message .= ' (عرض ' . min($per_page, count($search_results)) . ' من ' . $total_results . ')';
            }
        } else {
            // No hay resultados
            $search_message = 'لم يتم العثور على نتائج مطابقة، حاول مرة أخرى.';
        }
    }
}

// Verificar solicitudes previas para este ECU
$user_dump_requests = [];
if ($has_result && !empty($ecu_data)) {
    try {
        // Verificar si existe la tabla ecu_dumps
        $table_check = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'ecu_dumps' LIMIT 1");
        if ($table_check->fetchColumn()) {
            $dump_stmt = $pdo->prepare("
                SELECT ed.*, t.status as ticket_status, t.is_seen
                FROM ecu_dumps ed
                LEFT JOIN tickets t ON ed.ticket_id = t.id
                WHERE ed.ecu_id = ? AND ed.username = ?
                ORDER BY ed.upload_date DESC
            ");
            $dump_stmt->execute([$ecu_data['id'], $username]);
            $user_dump_requests = $dump_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log('Error checking dump requests: ' . $e->getMessage());
    }
}

// Obtener marcas para filtros
try {
    $brands = $pdo->query("SELECT DISTINCT brand FROM airbag_ecus ORDER BY brand")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $brands = [];
    error_log('Error fetching brands: ' . $e->getMessage());
}

// Registro de búsqueda
if ($has_result && !empty($ecu_data)) {
    try {
        // Verificar si existe la tabla de registros
        $check_table = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'search_logs' LIMIT 1");
        if ($check_table->fetchColumn()) {
            $log_stmt = $pdo->prepare("
                INSERT INTO search_logs (user_id, ecu_id, brand, model, ecu_number, search_term, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $search_term = $query ?: "$selected_brand $selected_model $selected_ecu";
            
            $log_stmt->execute([
                $user_id,
                $ecu_data['id'],
                $ecu_data['brand'],
                $ecu_data['model'],
                $ecu_data['ecu_number'],
                $search_term,
                $ip_address
            ]);
        }
    } catch (Exception $e) {
        error_log('Error logging search: ' . $e->getMessage());
    }
}

// Escapar variables para uso en HTML
$safe_selected_brand = htmlspecialchars($selected_brand);
$safe_selected_model = htmlspecialchars($selected_model);
$safe_selected_ecu = htmlspecialchars($selected_ecu);
$safe_query = htmlspecialchars($query);

// CSS mejorado
$page_css = '
.main-container {
    background: rgba(0,0,0,.7);
    padding: 30px;
    width: 95%;
    max-width: 1200px;
    border-radius: 16px;
    text-align: center;
    margin: 30px auto;
    box-shadow: 0 0 40px rgba(0,200,255,.15);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(66,135,245,.25);
}

.success-message {
    background-color: rgba(39,174,96,.2);
    color: #2ecc71;
    border: 1px solid rgba(39,174,96,.4);
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    margin-bottom: 20px;
    animation: fadeIn 0.5s ease-out;
}

.error-message {
    background-color: rgba(231,76,60,.2);
    color: #e74c3c;
    border: 1px solid rgba(231,76,60,.4);
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    margin-bottom: 20px;
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.search-container {
    background: rgba(255,255,255,.05);
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 30px;
    border: 1px solid rgba(66,135,245,.15);
    transition: box-shadow 0.3s ease;
}

.search-container:hover {
    box-shadow: 0 0 20px rgba(0,123,255,.1);
}

.search-title {
    color: #00d4ff;
    margin-bottom: 20px;
    font-size: 1.5em;
}

.search-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
    max-width: 800px;
    margin: 0 auto;
}

.form-group {
    display: flex;
    flex-direction: column;
    text-align: right;
}

.form-group label {
    margin-bottom: 8px;
    color: #a8d8ff;
    font-weight: 700;
}

.form-control {
    padding: 12px;
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(66,135,245,.3);
    border-radius: 8px;
    color: #fff;
    text-align: right;
    direction: rtl;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: 0;
    border-color: #00d4ff;
    background: rgba(255,255,255,.15);
    box-shadow: 0 0 10px rgba(0,123,255,.2);
}

.form-control.invalid {
    border-color: #e74c3c;
    background: rgba(231,76,60,.1);
}

.search-actions {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 20px;
}

.btn {
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 700;
    transition: all .3s ease;
    text-decoration: none;
    display: inline-block;
    position: relative;
    overflow: hidden;
}

.btn::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 5px;
    height: 5px;
    background: rgba(255, 255, 255, 0.5);
    opacity: 0;
    border-radius: 100%;
    transform: scale(1, 1) translate(-50%);
    transform-origin: 50% 50%;
}

.btn:hover::after {
    animation: ripple 1s ease-out;
}

@keyframes ripple {
    0% {
        transform: scale(0, 0);
        opacity: 0.5;
    }
    20% {
        transform: scale(25, 25);
        opacity: 0.3;
    }
    100% {
        opacity: 0;
        transform: scale(40, 40);
    }
}

.btn-primary {
    background: linear-gradient(145deg,#1e90ff,#0070cc);
    color: #fff;
}

.btn-primary:hover {
    background: linear-gradient(145deg,#2eaaff,#0088ff);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(30,144,255,.2);
}

.btn-secondary {
    background: linear-gradient(145deg,#6c757d,#5a6268);
    color: #fff;
}

.btn-secondary:hover {
    background: linear-gradient(145deg,#7a8288,#6c757d);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(108,117,125,.2);
}

.btn-success {
    background: linear-gradient(145deg,#28a745,#218838);
    color: #fff;
}

.btn-success:hover {
    background: linear-gradient(145deg,#34ce57,#28a745);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(40,167,69,.2);
}

.result-container {
    background: rgba(255,255,255,.05);
    border-radius: 12px;
    padding: 25px;
    margin-top: 30px;
    border: 1px solid rgba(66,135,245,.15);
    text-align: right;
    direction: rtl;
    animation: slideUp 0.5s ease-out;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.result-title {
    color: #00d4ff;
    margin-bottom: 20px;
    font-size: 1.5em;
    text-align: center;
}

.data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 15px 0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
}

.data-table td, .data-table th {
    padding: 15px;
    text-align: right;
    transition: background-color 0.3s ease;
}

.data-table tr:hover td {
    background: rgba(255,255,255,.08);
}

.data-table th {
    background: rgba(0,0,0,.5);
    color: #00d4ff;
    font-weight: 700;
    position: relative;
}

.data-table th::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 1px;
    background: linear-gradient(90deg, rgba(0,212,255,0), rgba(0,212,255,0.5), rgba(0,212,255,0));
}

.data-table td {
    color: #a8d8ff;
    border-bottom: 1px solid rgba(255,255,255,.1);
}

.instructions {
    background: rgba(0,0,0,.3);
    padding: 20px;
    border-radius: 10px;
    margin-top: 20px;
    text-align: right;
    direction: rtl;
    border: 1px solid rgba(66,135,245,.15);
}

.instructions ol {
    text-align: right;
    padding-right: 20px;
}

.instructions li {
    margin-bottom: 10px;
    color: #a8d8ff;
    position: relative;
    padding-right: 5px;
}

.instructions li::before {
    content: "";
    position: absolute;
    right: -20px;
    top: 10px;
    width: 6px;
    height: 6px;
    background: #00d4ff;
    border-radius: 50%;
}

.upload-form {
    background: rgba(0,123,255,.1);
    border: 1px solid rgba(0,123,255,.3);
    border-radius: 8px;
    padding: 20px;
    margin-top: 25px;
    text-align: right;
    transition: all 0.3s ease;
}

.upload-form:hover {
    box-shadow: 0 0 20px rgba(0,123,255,.2);
}

.upload-title {
    color: #00d4ff;
    margin-bottom: 15px;
    text-align: center;
    font-size: 1.3em;
}

.file-input-wrapper {
    position: relative;
    margin-bottom: 15px;
    text-align: center;
    transition: all 0.3s ease;
}

.file-input-wrapper.highlight {
    transform: scale(1.01);
}

.file-input {
    opacity: 0;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 10;
}

.file-input-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 25px;
    background: rgba(255,255,255,.05);
    border: 2px dashed rgba(66,135,245,.3);
    border-radius: 10px;
    cursor: pointer;
    transition: all .3s ease;
}

.file-input-label:hover, .file-input-wrapper.highlight .file-input-label {
    background: rgba(255,255,255,.1);
    border-color: #00d4ff;
    box-shadow: 0 0 15px rgba(0, 212, 255, 0.2);
}

.file-input-icon {
    font-size: 3em;
    color: #00d4ff;
    margin-bottom: 15px;
    transition: transform 0.3s ease;
}

.file-input-label:hover .file-input-icon, .file-input-wrapper.highlight .file-input-icon {
    transform: scale(1.1);
}

.file-selected {
    display: none;
    margin-top: 10px;
    color: #00d4ff;
    background: rgba(0,123,255,.1);
    padding: 10px;
    border-radius: 5px;
    animation: fadeIn 0.3s ease-out;
}

.upload-form-buttons {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 20px;
}

.file-type-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
    margin: 15px 0;
}

.file-type-option {
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(66,135,245,.3);
    padding: 12px 18px;
    border-radius: 20px;
    cursor: pointer;
    transition: all .3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.file-type-option.selected {
    background: rgba(0,123,255,.2);
    border-color: #00d4ff;
    color: #00d4ff;
    transform: scale(1.05);
    box-shadow: 0 0 10px rgba(0, 212, 255, 0.2);
}

.file-type-option:hover:not(.selected) {
    background: rgba(255,255,255,.1);
    transform: translateY(-2px);
}

.alert {
    padding: 15px;
    border-radius: 10px;
    margin: 15px 0;
    text-align: center;
    direction: rtl;
}

.alert-info {
    background: rgba(23,162,184,.2);
    border: 1px solid #17a2b8;
    color: #aef0ff;
}

.alert-warning {
    background: rgba(255,193,7,.2);
    border: 1px solid #ffc107;
    color: #ffe699;
}

.image-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.ecu-image {
    border: 1px solid rgba(66,135,245,.3);
    border-radius: 8px;
    overflow: hidden;
    background: rgba(0,0,0,.5);
    position: relative;
    transition: transform 0.3s ease;
}

.ecu-image:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,.3);
}

.ecu-image img {
    width: 100%;
    height: auto;
    transition: transform .3s ease;
    cursor: pointer;
}

.ecu-image img:hover {
    transform: scale(1.05);
}

.image-caption {
    background: rgba(0,0,0,.7);
    color: #a8d8ff;
    padding: 8px;
    text-align: center;
}

.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,.8);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal.show {
    opacity: 1;
}

.modal-content {
    background: rgba(0,0,0,.9);
    margin: 5% auto;
    padding: 30px;
    border-radius: 15px;
    max-width: 80%;
    max-height: 80vh;
    overflow: auto;
    border: 1px solid rgba(66,135,245,.3);
    position: relative;
    transform: scale(0.9);
    transition: transform 0.3s ease;
    box-shadow: 0 0 30px rgba(0,123,255,.2);
}

.modal.show .modal-content {
    transform: scale(1);
}

.modal img {
    max-width: 100%;
    height: auto;
    transition: transform 0.3s ease;
    cursor: zoom-in;
}

.modal img.zoomed {
    transform: scale(1.5);
    cursor: zoom-out;
}

.close {
    color: #aaa;
    position: absolute;
    top: 10px;
    right: 20px;
    font-size: 28px;
    font-weight: 700;
    transition: all 0.3s ease;
}

.close:hover {
    color: #fff;
    cursor: pointer;
    transform: rotate(90deg);
}

.search-results {
    margin: 20px 0;
    animation: fadeIn 0.5s ease-out;
}

.search-results table {
    width: 100%;
    border-collapse: collapse;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
}

.search-results th {
    background: rgba(0,0,0,.5);
    color: #00d4ff;
    padding: 12px;
    text-align: right;
}

.search-results td {
    padding: 10px;
    text-align: right;
    border-bottom: 1px solid rgba(255,255,255,.1);
    color: #a8d8ff;
}

.search-results tr:hover {
    background: rgba(255,255,255,.08);
    cursor: pointer;
}

.search-results .result-link {
    color: #40a9ff;
    text-decoration: none;
    transition: all 0.3s ease;
}

.search-results .result-link:hover {
    color: #00d4ff;
    text-decoration: underline;
}

.info-box {
    background: rgba(0,123,255,.1);
    border: 1px solid rgba(0,123,255,.3);
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.info-box:hover {
    box-shadow: 0 0 20px rgba(0,123,255,.2);
}

.info-box h3 {
    color: #00d4ff;
    margin-top: 0;
    margin-bottom: 10px;
}

.info-box p {
    color: #a8d8ff;
    margin: 0;
}

.info-box ul {
    text-align: right;
    padding-right: 20px;
    margin-top: 10px;
    color: #a8d8ff;
}

.info-box li {
    margin-bottom: 8px;
    position: relative;
}

.info-box li::before {
    content: "";
    position: absolute;
    right: -20px;
    top: 8px;
    width: 6px;
    height: 6px;
    background: #00d4ff;
    border-radius: 50%;
}

.autocomplete-container {
    position: relative;
}

.autocomplete-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    max-height: 220px;
    overflow-y: auto;
    background: rgba(0,0,0,.95);
    border: 1px solid rgba(66,135,245,.3);
    border-radius: 0 0 8px 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,.5);
    display: none;
    backdrop-filter: blur(8px);
}

.autocomplete-item {
    padding: 12px 15px;
    cursor: pointer;
    text-align: right;
    border-bottom: 1px solid rgba(255,255,255,.05);
    transition: all 0.2s ease;
}

.autocomplete-item:last-child {
    border-bottom: none;
}

.autocomplete-item.selected, .autocomplete-item:hover {
    background: rgba(66,135,245,.3);
}

.autocomplete-loading, .autocomplete-error, .autocomplete-empty {
    padding: 12px 15px;
    text-align: center;
    color: #a8d8ff;
}

.autocomplete-error {
    color: #ff7070;
}

.previous-uploads {
    margin-top: 25px;
    padding: 15px;
    background: rgba(0,0,0,.3);
    border-radius: 10px;
    text-align: right;
    animation: fadeIn 0.5s ease-out;
}

.previous-uploads-title {
    color: #00d4ff;
    margin-bottom: 10px;
    text-align: center;
}

.previous-uploads-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    border-radius: 8px;
    overflow: hidden;
}

.previous-uploads-table th {
    background: rgba(0,0,0,.5);
    color: #00d4ff;
    padding: 12px;
    text-align: right;
}

.previous-uploads-table td {
    padding: 10px;
    text-align: right;
    border-bottom: 1px solid rgba(255,255,255,.1);
    color: #a8d8ff;
}

.ticket-status {
    padding: 5px 10px;
    border-radius: 15px;
    font-size: .85em;
    display: inline-block;
}

.status-pending {
    background-color: rgba(255,193,7,.2);
    color: #ffc107;
    border: 1px solid rgba(255,193,7,.3);
}

.status-reviewed {
    background-color: rgba(40,167,69,.2);
    color: #28a745;
    border: 1px solid rgba(40,167,69,.3);
}

.status-cancelled {
    background-color: rgba(220,53,69,.2);
    color: #dc3545;
    border: 1px solid rgba(220,53,69,.3);
}

.loading-spinner {
    display: inline-block;
    width: 30px;
    height: 30px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: #00d4ff;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin: 20px 0;
}

.pagination a, .pagination span {
    padding: 8px 12px;
    border-radius: 5px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.pagination a {
    background: rgba(255,255,255,.1);
    color: #a8d8ff;
}

.pagination a:hover {
    background: rgba(66,135,245,.3);
    transform: translateY(-2px);
}

.pagination span.current {
    background: rgba(66,135,245,.5);
    color: #fff;
}

.back-link {
    display: inline-block;
    margin-top: 20px;
    padding: 12px 25px;
    background: linear-gradient(145deg,#6c757d,#5a6268);
    color: #fff;
    text-decoration: none;
    border-radius: 10px;
    transition: all .3s ease;
}

.back-link:hover {
    background: linear-gradient(145deg,#7a8288,#6c757d);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(108,117,125,.2);
}

#upload-progress-container {
    margin-top: 15px;
    width: 100%;
}

.progress {
    height: 20px;
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    width: 0%;
    background: linear-gradient(45deg, #00d4ff, #0070cc);
    transition: width 0.3s;
}

#upload-progress-text {
    text-align: center;
    margin-top: 5px;
    color: #a8d8ff;
}

/* Responsive enhancements */
@media (min-width: 768px) {
    .search-form {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .form-group.full-width {
        grid-column: span 2;
    }
}

@media (max-width: 767px) {
    .main-container {
        padding: 20px;
        width: 90%;
    }
    
    .btn {
        padding: 10px 15px;
        font-size: 14px;
    }
    
    .search-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .image-container {
        grid-template-columns: 1fr;
    }
    
    .upload-form-buttons {
        flex-direction: column;
    }
    
    .file-type-selector {
        flex-direction: column;
        align-items: center;
    }
    
    .file-type-option {
        width: 80%;
    }
    
    .modal-content {
        max-width: 95%;
    }
}';

// رسائل النجاح / الخطأ لتضمينها داخل الصفحة
$message_section = '';
if (!empty($success_message)) {
    $message_section .= '<div class="success-message"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($success_message) . '</div>';
}
if (!empty($error_message)) {
    $message_section .= '<div class="error-message"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($error_message) . '</div>';
}

// هنا نبدأ في بناء محتوى الصفحة
echo '<div class="main-container">';
echo '<h1>' . $display_title . '</h1>';

// Mensajes de éxito/error
echo $message_section;

// Sección de búsqueda
echo '<div class="search-container">';
echo '<h2 class="search-title">🔍 ابحث عن بيانات إعادة ضبط الإيرباق</h2>';
echo '<form method="GET" action="" class="search-form">';
echo '<input type="hidden" name="search" value="1">';

echo '<div class="form-group">';
echo '<label for="brand">العلامة التجارية</label>';
echo '<div class="autocomplete-container">';
echo '<input type="text" id="brand" name="brand" class="form-control" value="'. $safe_selected_brand .'" placeholder="أدخل العلامة التجارية...">';
echo '<div id="brand-results" class="autocomplete-results"></div>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="model">الموديل</label>';
echo '<div class="autocomplete-container">';
echo '<input type="text" id="model" name="model" class="form-control" value="'. $safe_selected_model .'" placeholder="أدخل الموديل...">';
echo '<div id="model-results" class="autocomplete-results"></div>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="ecu">رقم كمبيوتر الإيرباق</label>';
echo '<div class="autocomplete-container">';
echo '<input type="text" id="ecu" name="ecu" class="form-control" value="'. $safe_selected_ecu .'" placeholder="أدخل رقم كمبيوتر الإيرباق...">';
echo '<div id="ecu-results" class="autocomplete-results"></div>';
echo '</div>';
echo '</div>';

echo '<div class="form-group full-width">';
echo '<label for="query">بحث عام (العلامة التجارية، الموديل، الرقم، نوع EEPROM)</label>';
echo '<input type="text" id="query" name="query" class="form-control" value="'. $safe_query .'" placeholder="أدخل كلمات البحث...">';
echo '</div>';

echo '<div class="search-actions full-width">';
echo '<button type="submit" class="btn btn-primary">🔍 بحث</button>';
echo '<a href="airbag-reset.php" class="btn btn-secondary">↺ إعادة تعيين</a>';
echo '</div>';
echo '</form>';
echo '</div>';

// Mensaje de búsqueda
if (!empty($search_message)) {
    echo '<div class="alert alert-info">' . htmlspecialchars($search_message) . '</div>';
    
    if (isset($search_results) && count($search_results) > 0) {
        echo '<div class="search-results"><table><thead><tr><th>العلامة التجارية</th><th>الموديل</th><th>رقم الكمبيوتر</th><th>نوع EEPROM</th><th>الإجراء</th></tr></thead><tbody>';
        
        foreach ($search_results as $result) {
            echo '<tr>
                <td>' . htmlspecialchars($result['brand']) . '</td>
                <td>' . htmlspecialchars($result['model']) . '</td>
                <td>' . htmlspecialchars($result['ecu_number']) . '</td>
                <td>' . htmlspecialchars(isset($result['eeprom_type']) ? $result['eeprom_type'] : 'غير متوفر') . '</td>
                <td><a href="airbag-reset.php?ecu_id=' . $result['id'] . '" class="result-link">عرض التفاصيل</a></td>
            </tr>';
        }
        
        echo '</tbody></table>';
        
        // Añadir paginación si hay múltiples páginas
        if (isset($total_pages) && $total_pages > 1) {
            echo '<div class="pagination">';
            
            // Previous page link
            if ($page > 1) {
                $prev_params = $_GET;
                $prev_params['page'] = $page - 1;
                $prev_url = 'airbag-reset.php?' . http_build_query($prev_params);
                echo '<a href="' . $prev_url . '">&laquo; السابق</a>';
            }
            
            // Page links
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $start_page + 4);
            $start_page = max(1, $end_page - 4); // Adjust start if end is limited
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo '<span class="current">' . $i . '</span>';
                } else {
                    $page_params = $_GET;
                    $page_params['page'] = $i;
                    $page_url = 'airbag-reset.php?' . http_build_query($page_params);
                    echo '<a href="' . $page_url . '">' . $i . '</a>';
                }
            }
            
            // Next page link
            if ($page < $total_pages) {
                $next_params = $_GET;
                $next_params['page'] = $page + 1;
                $next_url = 'airbag-reset.php?' . http_build_query($next_params);
                echo '<a href="' . $next_url . '">التالي &raquo;</a>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
    }
}

// Resultados de búsqueda
if ($has_result && !empty($ecu_data)) {
    echo '<div class="result-container">';
    echo '<h2 class="result-title">🚗 بيانات كمبيوتر الإيرباق</h2>';
    
    echo '<table class="data-table">';
    echo '<tr>
          <th>العلامة التجارية:</th>
          <td>' . htmlspecialchars($ecu_data['brand']) . '</td>
        </tr>';
    echo '<tr>
          <th>الموديل:</th>
          <td>' . htmlspecialchars($ecu_data['model']) . '</td>
        </tr>';
    echo '<tr>
          <th>رقم كمبيوتر الإيرباق:</th>
          <td>' . htmlspecialchars($ecu_data['ecu_number']) . '</td>
        </tr>';
        
    if (!empty($ecu_data['eeprom_type'])) {
        echo '<tr>
          <th>نوع EEPROM:</th>
          <td>' . htmlspecialchars($ecu_data['eeprom_type']) . '</td>
        </tr>';
    }
    
    if (isset($ecu_data['crash_location']) && !empty($ecu_data['crash_location'])) {
        echo '<tr>
          <th>موقع بيانات الحادث:</th>
          <td>' . htmlspecialchars($ecu_data['crash_location']) . '</td>
        </tr>';
    }
    
    if (isset($ecu_data['reset_procedure']) && !empty($ecu_data['reset_procedure'])) {
        echo '<tr>
          <th>إجراءات إعادة الضبط:</th>
          <td>' . nl2br(htmlspecialchars($ecu_data['reset_procedure'])) . '</td>
        </tr>';
    }
    
    echo '</table>';
    
    // Imágenes
    if (isset($ecu_data['images']) && count($ecu_data['images']) > 0) {
        echo '<h3 style="color: #00d4ff; margin-top: 20px;">📷 صور مخطط الإيرباق</h3>';
        echo '<div class="image-container">';
        
        foreach ($ecu_data['images'] as $index => $image) {
            echo '<div class="ecu-image">
              <img src="uploads/ecu_images/' . htmlspecialchars($image['filename']) . '" 
                   alt="' . htmlspecialchars($ecu_data['brand'] . ' ' . $ecu_data['model']) . '"
                   onclick="openImageModal(\'uploads/ecu_images/' . htmlspecialchars($image['filename']) . '\')">
              ' . (isset($image['description']) && !empty($image['description']) ? '<div class="image-caption">' . htmlspecialchars($image['description']) . '</div>' : '') . '
            </div>';
        }
        
        echo '</div>';
    } else {
        echo '<div class="alert alert-warning">
          لا توجد صور متاحة لهذا الكمبيوتر
        </div>';
    }
    
    // Instrucciones - MODIFICADO para usar el texto en árabe que proporciona información al cliente
    echo '<div class="instructions">
        <h3 style="color: #00d4ff;">📋 ماذا يحدث بعد الرفع</h3>
        <ol>
          <li>يتم استلام ملف الدامب المرفوع (EEPROM / Flash / CPU) من قبل فريقنا التقني.</li>
          <li>يتم فحص الملف للتحقق من وجود بيانات الحادث أو تلف.</li>
          <li>إذا تم العثور على بيانات حادث قابلة للإزالة، نقوم بتنظيفها وإنشاء ملف مصلح.</li>
          <li>إذا كان الملف غير قابل للقراءة أو غير مدعوم، سنقوم بإبلاغك.</li>
          <li>سيتم إرسال الملف المنظف أو رد متابعة خلال 1-3 ساعات خلال وقت العمل.</li>
          <li>يمكنك تتبع حالة طلبك من صفحة "تذاكري".</li>
          <li>🛡️ تأكد دائمًا من التحقق من تنسيق الملف قبل الإرسال.</li>
        </ol>
      </div>';
    
    echo '<div class="info-box">
        <h3>🛠️ ملاحظة فنية</h3>
        <p>
          تأكد دائمًا من مقارنة رقم كمبيوتر الإيرباق الخاص بك مع الرقم المعروض. 
          في حالة عدم التطابق الدقيق، قد تكون هناك اختلافات في موقع بيانات الحادث.
          استخدم هذه المعلومات على مسؤوليتك الخاصة وتأكد من عمل نسخة احتياطية قبل أي تعديل.
        </p>
      </div>';
    
    // Solicitudes previas
    if (!empty($user_dump_requests)) {
        echo '<div class="previous-uploads">
          <h3 class="previous-uploads-title">📄 طلباتك السابقة لهذا الكمبيوتر</h3>
          <table class="previous-uploads-table">
            <thead>
              <tr>
                <th>تاريخ الطلب</th>
                <th>نوع الملف</th>
                <th>اسم الملف</th>
                <th>حالة الطلب</th>
              </tr>
            </thead>
            <tbody>';
            
        foreach ($user_dump_requests as $request) {
            $status_class = '';
            $status_text = '';
            
            if (isset($request['ticket_status']) && $request['ticket_status'] === 'cancelled') {
                $status_class = 'status-cancelled';
                $status_text = 'ملغي';
            } elseif (isset($request['is_seen']) && $request['is_seen']) {
                $status_class = 'status-reviewed';
                $status_text = 'تمت المراجعة';
            } else {
                $status_class = 'status-pending';
                $status_text = 'قيد المراجعة';
            }
            
            $dump_type_text = '';
            switch($request['dump_type']) {
                case 'eeprom': $dump_type_text = 'ذاكرة EEPROM'; break;
                case 'flash': $dump_type_text = 'ذاكرة الفلاش'; break;
                case 'cpu': $dump_type_text = 'وحدة المعالجة'; break;
                default: $dump_type_text = htmlspecialchars($request['dump_type']);
            }
            
            echo '<tr>
                <td>' . date('Y/m/d H:i', strtotime($request['upload_date'])) . '</td>
                <td>' . $dump_type_text . '</td>
                <td>' . htmlspecialchars($request['original_filename']) . '</td>
                <td><span class="ticket-status ' . $status_class . '">' . $status_text . '</span></td>
              </tr>';
        }
            
        echo '</tbody>
          </table>
          <div style="text-align: center; margin-top: 15px;">
            <a href="includes/my_tickets.php" class="btn btn-primary">
              <i class="fas fa-ticket-alt"></i> عرض جميع تذاكري
            </a>
          </div>
        </div>';
    }
    
    // Formulario de carga mejorado
    echo '<div class="upload-form">
        <h3 class="upload-title">📤 تحميل ملف الدامب لإعادة ضبط الإيرباق</h3>
        
        <form method="POST" enctype="multipart/form-data" id="upload-form">
          <input type="hidden" name="upload_dump" value="1">
          <input type="hidden" name="ecu_id" value="' . $ecu_data['id'] . '">
          <input type="hidden" name="csrf_token" value="' . $csrf_token . '">
          
          <div class="file-input-wrapper" id="drop-area">
            <input type="file" id="dump_file" name="dump_file" class="file-input" 
                   accept=".bin,.hex,.dump,.rom,.dat,.img,.eep,.srec,.zip" required>
            <label for="dump_file" class="file-input-label">
              <div class="file-input-icon">
                <i class="fas fa-file-upload"></i>
              </div>
              <div id="drop-text">اسحب الملف هنا أو اضغط لاختيار ملف</div>
              <div style="font-size: 0.8em; color: #a8d8ff; margin-top: 5px;">
                الملفات المدعومة: .bin, .hex, .dump, .rom, .dat, .img, .eep, .srec, .zip
              </div>
              <div style="font-size: 0.8em; color: #ff9a9a; margin-top: 5px;">
                الحد الأقصى للحجم: 5 ميجابايت
              </div>
            </label>
            <div id="file-selected" class="file-selected">تم اختيار: <span id="file-name"></span> <span id="file-size"></span></div>
            <div id="upload-progress-container" style="display:none;">
              <div class="progress">
                <div id="upload-progress-bar" class="progress-bar"></div>
              </div>
              <div id="upload-progress-text">0%</div>
            </div>
          </div>
          
          <!-- Enhanced file type selector with icons -->
          <div class="form-group">
            <label>نوع الدامب:</label>
            <div class="file-type-selector">
              <div class="file-type-option selected" data-value="eeprom">
                <i class="fas fa-microchip"></i> ذاكرة EEPROM
              </div>
              <div class="file-type-option" data-value="flash">
                <i class="fas fa-memory"></i> ذاكرة الفلاش
              </div>
              <div class="file-type-option" data-value="cpu">
                <i class="fas fa-brain"></i> وحدة المعالجة CPU
              </div>
            </div>
            <input type="hidden" name="dump_type" id="dump_type" value="eeprom">
          </div>
          
          <div class="form-group">
            <label for="chassis">رقم الشاصي (الهيكل):</label>
            <input type="text" id="chassis" name="chassis" class="form-control" placeholder="أدخل رقم شاصي السيارة...">
          </div>
          
          <div class="form-group">
            <label for="phone">رقم الهاتف:</label>
            <input type="text" id="phone" name="phone" class="form-control" placeholder="رقم هاتفك للتواصل..." required>
          </div>
          
          <div class="form-group">
            <label for="notes">ملاحظات إضافية:</label>
            <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="أي ملاحظات أو معلومات إضافية ترغب في إضافتها..."></textarea>
          </div>
          
          <div class="upload-form-buttons">
            <button type="submit" class="btn btn-success">
              <i class="fas fa-paper-plane"></i> إرسال الطلب
            </button>
            <button type="reset" class="btn btn-secondary" id="reset-btn">
              <i class="fas fa-undo"></i> إعادة تعيين
            </button>
          </div>
        </form>
      </div>
    </div>';
} elseif (!isset($search_results) || count($search_results) === 0) {
    // Información predeterminada
    echo '<div class="info-box">
      <h3>👋 مرحبًا بك في نظام مسح وإعادة ضبط الإيرباق</h3>
      <p>
        استخدم نموذج البحث أعلاه للعثور على معلومات حول كمبيوتر الإيرباق الخاص بسيارتك.
        يمكنك البحث عن طريق العلامة التجارية أو الموديل أو رقم الكمبيوتر.
      </p>
      <p style="margin-top: 10px;">
        بمجرد العثور على الكمبيوتر المطلوب، ستتمكن من:
      </p>
      <ul style="text-align: right; padding-right: 20px; margin-top: 10px; color: #a8d8ff;">
        <li>رؤية صور المخطط وتعليمات إعادة الضبط</li>
        <li>تحميل ملف الدامب الخاص بكمبيوتر السيارة</li>
        <li>إرسال طلب إعادة ضبط للفريق الفني</li>
        <li>متابعة حالة طلبك في قسم "تذاكري"</li>
      </ul>
    </div>';
}

// Botón de regreso
echo '<a href="home.php" class="back-link">
    ↩️ العودة إلى الصفحة الرئيسية
  </a>
</div>';

// Modal de imágenes mejorado
echo '<div id="imageModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeImageModal()">&times;</span>
    <img id="modalImage" src="" alt="صورة الإيرباق" style="width: 100%; height: auto;">
  </div>
</div>';

// JavaScript mejorado
?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Drag and drop file upload
    const dropArea = document.getElementById("drop-area");
    const fileInput = document.getElementById("dump_file");
    const fileSelected = document.getElementById("file-selected");
    const fileName = document.getElementById("file-name");
    const fileSize = document.getElementById("file-size");
    const dropText = document.getElementById("drop-text");
    const resetBtn = document.getElementById("reset-btn");
    
    if (dropArea) {
        ["dragenter", "dragover", "dragleave", "drop"].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ["dragenter", "dragover"].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ["dragleave", "drop"].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropArea.classList.add("highlight");
        }
        
        function unhighlight() {
            dropArea.classList.remove("highlight");
        }
        
        dropArea.addEventListener("drop", handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            updateFileInfo();
        }
        
        fileInput.addEventListener("change", updateFileInfo);
        
        function updateFileInfo() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                fileName.textContent = file.name;
                
                // Format file size
                let size;
                if (file.size < 1024) {
                    size = file.size + " bytes";
                } else if (file.size < 1024 * 1024) {
                    size = (file.size / 1024).toFixed(2) + " KB";
                } else {
                    size = (file.size / (1024 * 1024)).toFixed(2) + " MB";
                }
                
                fileSize.textContent = "(" + size + ")";
                fileSelected.style.display = "block";
                dropText.textContent = "تم اختيار الملف - انقر أو اسحب لتغييره";
                
                // Validation 
                if (file.size > 5 * 1024 * 1024) {
                    fileSize.style.color = "#ff5555";
                    alert("حجم الملف كبير جدًا. الحد الأقصى هو 5 ميجابايت.");
                } else {
                    fileSize.style.color = "#a8d8ff";
                }
                
                // Check extension
                const ext = file.name.split(".").pop().toLowerCase();
                const allowedExtensions = ["bin", "hex", "dump", "rom", "dat", "img", "eep", "srec", "zip"];
                
                if (!allowedExtensions.includes(ext)) {
                    fileName.style.color = "#ff5555";
                    alert("نوع الملف غير مدعوم. يُسمح فقط بملفات: " + allowedExtensions.join(", "));
                } else {
                    fileName.style.color = "#a8d8ff";
                }
            } else {
                fileSelected.style.display = "none";
                dropText.textContent = "اسحب الملف هنا أو اضغط لاختيار ملف";
            }
        }
        
        // Reset button should also reset file display
        if (resetBtn) {
            resetBtn.addEventListener("click", function() {
                fileSelected.style.display = "none";
                dropText.textContent = "اسحب الملف هنا أو اضغط لاختيار ملف";
                const progressContainer = document.getElementById("upload-progress-container");
                if (progressContainer) {
                    progressContainer.style.display = "none";
                }
            });
        }
    }
    
    // File type selector
    const fileTypeOptions = document.querySelectorAll(".file-type-option");
    const dumpTypeInput = document.getElementById("dump_type");
    
    if (fileTypeOptions.length && dumpTypeInput) {
        fileTypeOptions.forEach(option => {
            option.addEventListener("click", function() {
                fileTypeOptions.forEach(opt => opt.classList.remove("selected"));
                this.classList.add("selected");
                dumpTypeInput.value = this.getAttribute("data-value");
            });
        });
    }
    
    // Form submission with progress bar
    const uploadForm = document.getElementById("upload-form");
    const progressContainer = document.getElementById("upload-progress-container");
    const progressBar = document.getElementById("upload-progress-bar");
    const progressText = document.getElementById("upload-progress-text");
    
    if (uploadForm) {
        uploadForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            // Validate required fields
            const requiredFields = uploadForm.querySelectorAll("[required]");
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add("invalid");
                } else {
                    field.classList.remove("invalid");
                }
            });
            
            if (!isValid) {
                alert("يرجى ملء جميع الحقول المطلوبة");
                return;
            }
            
            // Show progress container
            if (progressContainer) {
                progressContainer.style.display = "block";
            }
            
            const formData = new FormData(uploadForm);
            const xhr = new XMLHttpRequest();
            
            xhr.open("POST", uploadForm.action || window.location.href, true);
            
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 100);
                    if (progressBar) {
                        progressBar.style.width = percentComplete + "%";
                    }
                    if (progressText) {
                        progressText.textContent = percentComplete + "%";
                    }
                }
            };
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    // Handle success - refresh page to show server response
                    window.location.reload();
                } else {
                    alert("حدث خطأ أثناء تحميل الملف");
                    if (progressContainer) {
                        progressContainer.style.display = "none";
                    }
                }
            };
            
            xhr.onerror = function() {
                alert("حدث خطأ في الاتصال");
                if (progressContainer) {
                    progressContainer.style.display = "none";
                }
            };
            
            xhr.send(formData);
        });
    }
    
    // Autocomplete functionality
    function setupAutocomplete(inputId, resultsId, action, paramsCallback) {
        var input = document.getElementById(inputId);
        var resultsContainer = document.getElementById(resultsId);
        
        if (!input || !resultsContainer) {
            return;
        }

        var selectedIndex = -1;
        var items = [];
        var timer;

        input.addEventListener("input", function() {
            clearTimeout(timer);
            var query = this.value.trim();
            
            if (query.length < 1) {
                resultsContainer.style.display = "none";
                return;
            }
            
            // Debounce the search
            timer = setTimeout(function() {
                var extraParams = "";
                if (typeof paramsCallback === "function") {
                    var params = paramsCallback();
                    for (var key in params) {
                        if (params.hasOwnProperty(key) && params[key]) {
                            extraParams += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(params[key]);
                        }
                    }
                }

                // Show loading indicator
                resultsContainer.innerHTML = "<div class='autocomplete-loading'>جاري البحث...</div>";
                resultsContainer.style.display = "block";

                fetch("search_airbag_ecus.php?action=" + action + "&q=" + encodeURIComponent(query) + extraParams)
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.error) {
                            console.error(data.error);
                            resultsContainer.innerHTML = "<div class='autocomplete-error'>حدث خطأ في البحث</div>";
                            return;
                        }

                        items = data;
                        if (items.length === 0) {
                            resultsContainer.innerHTML = "<div class='autocomplete-empty'>لا توجد نتائج</div>";
                            return;
                        }

                        resultsContainer.innerHTML = "";
                        items.forEach(function(item, index) {
                            var div = document.createElement("div");
                            div.className = "autocomplete-item";
                            div.textContent = item;
                            div.addEventListener("click", function() {
                                input.value = item;
                                resultsContainer.style.display = "none";
                                // Trigger a change event to update dependent dropdowns
                                var event = new Event('change');
                                input.dispatchEvent(event);
                            });
                            resultsContainer.appendChild(div);
                        });

                        resultsContainer.style.display = "block";
                        selectedIndex = -1;
                    })
                    .catch(function(error) {
                        console.error("Error fetching autocomplete results:", error);
                        resultsContainer.innerHTML = "<div class='autocomplete-error'>حدث خطأ في الاتصال</div>";
                    });
            }, 300); // 300ms debounce
        });

        // Keyboard navigation
        input.addEventListener("keydown", function(e) {
            var itemElements = resultsContainer.querySelectorAll(".autocomplete-item");
            if (itemElements.length === 0) return;

            if (e.key === "ArrowDown") {
                e.preventDefault();
                selectedIndex = (selectedIndex + 1) % itemElements.length;
                updateSelectedItem(itemElements);
            } else if (e.key === "ArrowUp") {
                e.preventDefault();
                selectedIndex = (selectedIndex - 1 + itemElements.length) % itemElements.length;
                updateSelectedItem(itemElements);
            } else if (e.key === "Enter" && selectedIndex !== -1) {
                e.preventDefault();
                input.value = items[selectedIndex];
                resultsContainer.style.display = "none";
                // Trigger a change event
                var event = new Event('change');
                input.dispatchEvent(event);
            } else if (e.key === "Escape") {
                resultsContainer.style.display = "none";
            }
        });

        document.addEventListener("click", function(e) {
            if (e.target !== input && !resultsContainer.contains(e.target)) {
                resultsContainer.style.display = "none";
            }
        });

        function updateSelectedItem(itemElements) {
            itemElements.forEach(function(item, index) {
                if (index === selectedIndex) {
                    item.classList.add("selected");
                    item.scrollIntoView({ block: "nearest" });
                } else {
                    item.classList.remove("selected");
                }
            });
        }
    }

    // Initialize autocompletion with dependencies
    setupAutocomplete("brand", "brand-results", "brands");
    
    setupAutocomplete("model", "model-results", "models", function() {
        var brandInput = document.getElementById("brand");
        return {
            brand: brandInput ? brandInput.value : ""
        };
    });
    
    setupAutocomplete("ecu", "ecu-results", "ecus", function() {
        var brandInput = document.getElementById("brand");
        var modelInput = document.getElementById("model");
        return {
            brand: brandInput ? brandInput.value : "",
            model: modelInput ? modelInput.value : ""
        };
    });
    
    // Add event listeners to update dependent dropdowns
    const brandInput = document.getElementById("brand");
    if (brandInput) {
        brandInput.addEventListener("change", function() {
            const modelInput = document.getElementById("model");
            if (modelInput) {
                // Clear model when brand changes
                modelInput.value = "";
                // Clear ECU when brand changes
                const ecuInput = document.getElementById("ecu");
                if (ecuInput) {
                    ecuInput.value = "";
                }
            }
        });
    }
    
    const modelInput = document.getElementById("model");
    if (modelInput) {
        modelInput.addEventListener("change", function() {
            // Clear ECU when model changes
            const ecuInput = document.getElementById("ecu");
            if (ecuInput) {
                ecuInput.value = "";
            }
        });
    }
    
    // Enhanced image modal
    window.openImageModal = function(imageSrc) {
        const modal = document.getElementById("imageModal");
        const modalImg = document.getElementById("modalImage");
        
        modalImg.src = imageSrc;
        modal.style.display = "block";
        
        // Add animation classes
        setTimeout(function() {
            modal.classList.add("show");
        }, 10);
        
        // Add zoom functionality
        modalImg.onclick = function() {
            this.classList.toggle("zoomed");
        };
    };
    
    window.closeImageModal = function() {
        const modal = document.getElementById("imageModal");
        modal.classList.remove("show");
        
        // Wait for animation to complete before hiding
        setTimeout(function() {
            modal.style.display = "none";
            document.getElementById("modalImage").classList.remove("zoomed");
        }, 300);
    };
    
    // Close modal on click outside content
    const modal = document.getElementById("imageModal");
    if (modal) {
        modal.addEventListener("click", function(e) {
            if (e.target === modal) {
                closeImageModal();
            }
        });
    }
    
    // Close modal with escape key
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape" && modal.style.display === "block") {
            closeImageModal();
        }
    });
});
</script>

<?php
// Crear search_airbag_ecus.php para AJAX if it doesn't exist
$ajax_handler_path = __DIR__ . '/search_airbag_ecus.php';
if (!file_exists($ajax_handler_path)) {
    $ajax_handler_content = '<?php
session_start();
require_once __DIR__ . \'/includes/db.php\';

// Check if user is logged in
if (!isset($_SESSION[\'email\'])) {
    die(json_encode([\'error\' => \'User not authenticated\']));
}

// Validate required parameters
if (!isset($_GET[\'action\']) || !isset($_GET[\'q\'])) {
    die(json_encode([\'error\' => \'Missing required parameters\']));
}

$action = $_GET[\'action\'];
$query = trim($_GET[\'q\']);

// Security: Minimum query length
if (strlen($query) < 1) {
    die(json_encode([]));
}

try {
    switch ($action) {
        case \'brands\':
            // Search for brands matching query
            $stmt = $pdo->prepare("
                SELECT DISTINCT brand 
                FROM airbag_ecus 
                WHERE brand LIKE ? 
                ORDER BY CASE WHEN brand = ? THEN 0 ELSE 1 END, brand
                LIMIT 10
            ");
            $stmt->execute(["%$query%", $query]);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
            
        case \'models\':
            // Search for models matching query and optional brand filter
            $brand = isset($_GET[\'brand\']) && !empty($_GET[\'brand\']) ? $_GET[\'brand\'] : null;
            
            if ($brand) {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT model 
                    FROM airbag_ecus 
                    WHERE model LIKE ? AND brand = ? 
                    ORDER BY CASE WHEN model = ? THEN 0 ELSE 1 END, model
                    LIMIT 10
                ");
                $stmt->execute(["%$query%", $brand, $query]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT model 
                    FROM airbag_ecus 
                    WHERE model LIKE ? 
                    ORDER BY CASE WHEN model = ? THEN 0 ELSE 1 END, model
                    LIMIT 10
                ");
                $stmt->execute(["%$query%", $query]);
            }
            
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
            
        case \'ecus\':
            // Search for ECU numbers with optional brand and model filters
            $brand = isset($_GET[\'brand\']) && !empty($_GET[\'brand\']) ? $_GET[\'brand\'] : null;
            $model = isset($_GET[\'model\']) && !empty($_GET[\'model\']) ? $_GET[\'model\'] : null;
            
            $sql = "SELECT DISTINCT ecu_number FROM airbag_ecus WHERE ecu_number LIKE ?";
            $params = ["%$query%"];
            
            if ($brand) {
                $sql .= " AND brand = ?";
                $params[] = $brand;
            }
            
            if ($model) {
                $sql .= " AND model = ?";
                $params[] = $model;
            }
            
            $sql .= " ORDER BY CASE WHEN ecu_number = ? THEN 0 ELSE 1 END, ecu_number LIMIT 10";
            $params[] = $query;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
            
        default:
            die(json_encode([\'error\' => \'Invalid action\']));
    }
    
    echo json_encode($results);
    
} catch (Exception $e) {
    error_log(\'Error in search_airbag_ecus.php: \' . $e->getMessage());
    die(json_encode([\'error\' => \'Database error\']));
}
';

    // Create the AJAX handler file
    file_put_contents($ajax_handler_path, $ajax_handler_content);
    chmod($ajax_handler_path, 0644); // Set proper permissions
}

// Incluir plantilla
include __DIR__ . '/includes/layout.php';