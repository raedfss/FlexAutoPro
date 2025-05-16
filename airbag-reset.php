<?php
session_start();

// 1) الاتصال بقاعدة البيانات (PDO)
require_once __DIR__ . '/includes/db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$user_type = $_SESSION['user_role'] ?? 'user';
$email = $_SESSION['email'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// استيراد الدوال المساعدة والأمان
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

// إنشاء توكن CSRF لحماية النموذج
$csrf_token = generateCSRFToken();

// إعداد عنوان الصفحة
$page_title = 'طلب مسح بيانات الحادث (Airbag Reset)';
$display_title = 'طلب مسح بيانات الحادث (Airbag Reset)';

// تهيئة رسائل التنفيذ
$success = '';
$error = '';

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من توكن CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "❌ فشل التحقق من الأمان. يرجى تحديث الصفحة والمحاولة مرة أخرى.";
    } else {
        // جلب القيم مع تنظيفها - تحديث الحقول حسب النظام الجديد
        $brand = sanitizeInput($_POST['brand'] ?? '');
        $model = sanitizeInput($_POST['model'] ?? '');
        $year = sanitizeInput($_POST['year'] ?? '');
        $ecu_number = sanitizeInput($_POST['ecu_number'] ?? '');
        $ecu_version = sanitizeInput($_POST['ecu_version'] ?? '');
        $eeprom_type = sanitizeInput($_POST['eeprom_type'] ?? '');
        $file = $_FILES['eeprom_file'] ?? null;

        // التحقق من اكتمال الحقول المطلوبة
        if (empty($brand) || empty($model) || empty($ecu_number) || empty($eeprom_type) || !$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = "❌ جميع الحقول المطلوبة يجب تعبئتها.";
        } else {
            // فحص الامتداد والحجم بشكل آمن
            $allowed_exts = ['bin', 'hex'];
            $file_info = pathinfo($file['name']);
            $ext = strtolower($file_info['extension'] ?? '');

            // التحقق من الامتداد
            if (!in_array($ext, $allowed_exts, true)) {
                $error = "❌ الملف غير مدعوم. يجب أن يكون بصيغة .bin أو .hex فقط.";
            } 
            // التحقق من الحجم
            elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = "❌ حجم الملف كبير. الحد الأقصى المسموح هو 2 ميجابايت.";
            } 
            // التحقق من نوع MIME الفعلي
            elseif (!validateBinaryFile($file['tmp_name'], $ext)) {
                $error = "❌ محتوى الملف غير صالح.";
            }
            else {
                // توليد اسم فريد وآمن للملف
                $filename = secureFileName(uniqid('eeprom_', true) . '.' . $ext);
                $upload_dir = __DIR__ . '/uploads/';
                $destination = $upload_dir . $filename;

                // التأكد من وجود المجلد وحقوق الكتابة
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $error = "❌ فشل في إنشاء مجلد الرفع. الرجاء الاتصال بالمسؤول.";
                    }
                }

                if (empty($error)) {
                    // إضافة تحقق من حقوق الكتابة
                    if (!is_writable($upload_dir)) {
                        $error = "❌ المجلد غير قابل للكتابة. الرجاء الاتصال بالمسؤول.";
                    } else {
                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            try {
                                // إدخال السجل في قاعدة البيانات مع الحقول الجديدة
                                $stmt = $pdo->prepare("
                                    INSERT INTO airbag_resets (user_id, brand, model, year, ecu_number, ecu_version, eeprom_type, uploaded_file, created_at)
                                    VALUES (:uid, :brand, :model, :year, :ecu, :ecu_ver, :eeprom_type, :file, NOW())
                                ");
                                $stmt->execute([
                                    ':uid'        => (int)$user_id,
                                    ':brand'      => $brand,
                                    ':model'      => $model,
                                    ':year'       => $year,
                                    ':ecu'        => $ecu_number,
                                    ':ecu_ver'    => $ecu_version,
                                    ':eeprom_type'=> $eeprom_type,
                                    ':file'       => $filename
                                ]);

                                // سجل العملية في سجل الأحداث
                                logActivity('airbag_reset', 'تم إرسال طلب مسح بيانات Airbag', $user_id);
                                
                                $success = "✅ تم إرسال طلب مسح بيانات Airbag بنجاح.";
                                
                                // إعادة تعيين المتغيرات لمنع إعادة الإرسال
                                $brand = '';
                                $model = '';
                                $year = '';
                                $ecu_number = '';
                                $ecu_version = '';
                                $eeprom_type = '';
                            } catch (PDOException $e) {
                                // معالجة خطأ قاعدة البيانات بشكل آمن
                                $error = "❌ حدث خطأ في قاعدة البيانات. الرجاء المحاولة مرة أخرى.";
                                // تسجيل الخطأ للمراجعة من قبل المسؤول
                                logError('Database error in airbag_reset: ' . $e->getMessage());
                                
                                // حذف الملف في حالة فشل الإدخال في قاعدة البيانات
                                if (file_exists($destination)) {
                                    unlink($destination);
                                }
                            }
                        } else {
                            $error = "❌ فشل في رفع الملف. الرجاء المحاولة مرة أخرى.";
                        }
                    }
                }
            }
        }
    }
}

// تحديث توكن CSRF بعد المعالجة
$csrf_token = generateCSRFToken();

// CSS مخصص للصفحة مع إضافة تنسيقات البحث الذكي
$page_css = <<<CSS
.container {
  background: rgba(0, 0, 0, 0.7);
  padding: 35px;
  width: 90%;
  max-width: 880px;
  border-radius: 16px;
  text-align: center;
  margin: 30px auto;
  box-shadow: 0 0 40px rgba(0, 200, 255, 0.15);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(66, 135, 245, 0.25);
}
.form {
  max-width: 600px;
  margin: 0 auto;
  text-align: right;
}
.form-group {
  margin-bottom: 20px;
  position: relative;
}
.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: bold;
  color: #a8d8ff;
}
.form-group input {
  width: 100%;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid rgba(66, 135, 245, 0.4);
  background: rgba(0, 40, 80, 0.4);
  color: white;
  box-sizing: border-box;
}
.form-group input[type="file"] {
  padding: 8px;
  background: rgba(0, 40, 80, 0.4);
}
.form-text {
  font-size: 0.8rem;
  color: #aaa;
  margin-top: 5px;
}
.btn {
  padding: 12px 25px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: bold;
  transition: 0.3s;
  margin-top: 10px;
}
.btn-primary {
  background: linear-gradient(145deg, #1e90ff, #0070cc);
  color: white;
  box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}
.btn-primary:hover {
  background: linear-gradient(145deg, #2eaaff, #0088ff);
  transform: translateY(-2px);
}
.alert {
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
  position: relative;
}
.alert-danger {
  background: rgba(220, 53, 69, 0.2);
  border: 1px solid rgba(220, 53, 69, 0.5);
  color: #ff6b6b;
}
.alert-success {
  background: rgba(40, 167, 69, 0.2);
  border: 1px solid rgba(40, 167, 69, 0.5);
  color: #75ff75;
}
.alert-info {
  background: rgba(23, 162, 184, 0.2);
  border: 1px solid rgba(23, 162, 184, 0.5);
  color: #5dccff;
}
.alert-dismissible .btn-close {
  position: absolute;
  top: 0;
  right: 0;
  padding: 15px;
  color: inherit;
  background: transparent;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
}

/* تنسيقات البحث الذكي (Autocomplete) */
.suggestions-container {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: rgba(0, 40, 80, 0.95);
  border: 1px solid rgba(66, 135, 245, 0.4);
  border-top: none;
  border-radius: 0 0 8px 8px;
  max-height: 200px;
  overflow-y: auto;
  z-index: 1000;
  display: none;
}

.suggestion-item {
  padding: 10px 12px;
  cursor: pointer;
  color: #fff;
  text-align: right;
  border-bottom: 1px solid rgba(66, 135, 245, 0.2);
  transition: background-color 0.2s;
}

.suggestion-item:hover {
  background: rgba(30, 144, 255, 0.2);
}

.suggestion-item:last-child {
  border-bottom: none;
}

.suggestion-item.active {
  background: rgba(30, 144, 255, 0.3);
}

.no-suggestions {
  padding: 10px 12px;
  color: #aaa;
  text-align: center;
  font-style: italic;
}

.loading-indicator {
  padding: 10px 12px;
  color: #a8d8ff;
  text-align: center;
  font-style: italic;
}

/* تحسين تجربة المستخدم */
.form-group input.with-suggestions {
  border-radius: 8px 8px 0 0;
}

/* تنسيق للحقول مع معلومات إضافية */
.field-with-info {
  position: relative;
}

.field-info {
  position: absolute;
  left: 5px;
  top: 50%;
  transform: translateY(-50%);
  color: #a8d8ff;
  font-size: 0.85rem;
  pointer-events: none;
}
CSS;

// JavaScript للبحث الذكي
$page_js = <<<JS
<script>
// متغيرات عامة للبحث الذكي
let searchTimeouts = {};
let currentFocus = -1;
let currentField = null;

// دالة البحث الذكي العامة
function performSmartSearch(field, query, action) {
    const minLength = 2;
    const suggestionContainer = document.getElementById(field + 'Suggestions');
    
    // إخفاء الاقتراحات إذا كان النص قصيراً
    if (query.length < minLength) {
        hideSuggestions(field);
        return;
    }
    
    // إلغاء البحث السابق إن وجد
    if (searchTimeouts[field]) {
        clearTimeout(searchTimeouts[field]);
    }
    
    // تأخير البحث لتحسين الأداء
    searchTimeouts[field] = setTimeout(() => {
        showLoading(field);
        
        fetch(\`search_airbag_ecus.php?action=\${action}&q=\` + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
                hideLoading(field);
                displaySuggestions(field, data);
            })
            .catch(error => {
                console.error('خطأ في البحث:', error);
                hideLoading(field);
                showError(field, 'خطأ في البحث');
            });
    }, 300); // تأخير 300ms
}

// دالة عرض الاقتراحات
function displaySuggestions(field, suggestions) {
    const container = document.getElementById(field + 'Suggestions');
    const input = document.getElementById(field + 'Input');
    
    if (!container || !input) return;
    
    container.innerHTML = '';
    currentFocus = -1;
    
    if (suggestions.length === 0) {
        container.innerHTML = '<div class="no-suggestions">لا توجد نتائج مطابقة</div>';
        container.style.display = 'block';
        return;
    }
    
    suggestions.forEach((item, index) => {
        const div = document.createElement('div');
        div.className = 'suggestion-item';
        div.textContent = item;
        div.onclick = () => selectSuggestion(field, item);
        div.addEventListener('mouseenter', () => {
            currentFocus = index;
            updateActiveSuggestion(field);
        });
        container.appendChild(div);
    });
    
    container.style.display = 'block';
    input.classList.add('with-suggestions');
    currentField = field;
}

// دالة اختيار اقتراح
function selectSuggestion(field, value) {
    const input = document.getElementById(field + 'Input');
    if (input) {
        input.value = value;
        hideSuggestions(field);
        
        // تشغيل أحداث خاصة حسب الحقل
        if (field === 'brand') {
            // مسح النماذج والسنوات عند تغيير الماركة
            clearField('model');
            clearField('year');
            triggerBrandChange();
        } else if (field === 'model') {
            // مسح السنوات عند تغيير النموذج
            clearField('year');
            triggerModelChange();
        }
    }
}

// دالة إخفاء الاقتراحات
function hideSuggestions(field) {
    const container = document.getElementById(field + 'Suggestions');
    const input = document.getElementById(field + 'Input');
    
    if (container) {
        container.style.display = 'none';
    }
    if (input) {
        input.classList.remove('with-suggestions');
    }
    currentField = null;
    currentFocus = -1;
}

// دالة عرض حالة التحميل
function showLoading(field) {
    const container = document.getElementById(field + 'Suggestions');
    if (container) {
        container.innerHTML = '<div class="loading-indicator">جاري البحث...</div>';
        container.style.display = 'block';
    }
}

// دالة إخفاء حالة التحميل
function hideLoading(field) {
    // يتم استدعاؤها تلقائياً عند عرض النتائج
}

// دالة عرض خطأ
function showError(field, message) {
    const container = document.getElementById(field + 'Suggestions');
    if (container) {
        container.innerHTML = \`<div class="no-suggestions">\${message}</div>\`;
        container.style.display = 'block';
    }
}

// دالة مسح حقل
function clearField(field) {
    const input = document.getElementById(field + 'Input');
    if (input) {
        input.value = '';
        hideSuggestions(field);
    }
}

// دالة تحديث الاقتراح النشط
function updateActiveSuggestion(field) {
    const container = document.getElementById(field + 'Suggestions');
    if (!container) return;
    
    const items = container.getElementsByClassName('suggestion-item');
    Array.from(items).forEach((item, index) => {
        item.classList.toggle('active', index === currentFocus);
    });
}

// دالة التنقل بلوحة المفاتيح
function handleKeyDown(field, event) {
    const container = document.getElementById(field + 'Suggestions');
    if (!container || container.style.display === 'none') return;
    
    const items = container.getElementsByClassName('suggestion-item');
    
    switch(event.key) {
        case 'ArrowDown':
            event.preventDefault();
            currentFocus = Math.min(currentFocus + 1, items.length - 1);
            updateActiveSuggestion(field);
            break;
            
        case 'ArrowUp':
            event.preventDefault();
            currentFocus = Math.max(currentFocus - 1, -1);
            updateActiveSuggestion(field);
            break;
            
        case 'Enter':
            event.preventDefault();
            if (currentFocus >= 0 && items[currentFocus]) {
                items[currentFocus].click();
            }
            break;
            
        case 'Escape':
            hideSuggestions(field);
            break;
    }
}

// دوال خاصة لكل حقل
function searchBrands(query) {
    performSmartSearch('brand', query, 'brands');
}

function searchModels(query) {
    const brand = document.getElementById('brandInput').value;
    if (!brand) {
        showError('model', 'يرجى اختيار الماركة أولاً');
        return;
    }
    performSmartSearch('model', query, \`models&brand=\${encodeURIComponent(brand)}\`);
}

function searchYears(query) {
    const brand = document.getElementById('brandInput').value;
    const model = document.getElementById('modelInput').value;
    
    if (!brand || !model) {
        showError('year', 'يرجى اختيار الماركة والموديل أولاً');
        return;
    }
    performSmartSearch('year', query, \`years&brand=\${encodeURIComponent(brand)}&model=\${encodeURIComponent(model)}\`);
}

function searchECUs(query) {
    const brand = document.getElementById('brandInput').value;
    const model = document.getElementById('modelInput').value;
    const year = document.getElementById('yearInput').value;
    
    if (!brand || !model) {
        showError('ecuNumber', 'يرجى اختيار الماركة والموديل أولاً');
        return;
    }
    
    let searchUrl = \`ecus&brand=\${encodeURIComponent(brand)}&model=\${encodeURIComponent(model)}\`;
    if (year) {
        searchUrl += \`&year=\${encodeURIComponent(year)}\`;
    }
    performSmartSearch('ecuNumber', query, searchUrl);
}

function searchEEPROMs(query) {
    const brand = document.getElementById('brandInput').value;
    const model = document.getElementById('modelInput').value;
    const ecu = document.getElementById('ecuNumberInput').value;
    
    if (!brand || !model) {
        showError('eepromType', 'يرجى اختيار الماركة والموديل أولاً');
        return;
    }
    
    let searchUrl = \`eeproms&brand=\${encodeURIComponent(brand)}&model=\${encodeURIComponent(model)}\`;
    if (ecu) {
        searchUrl += \`&ecu=\${encodeURIComponent(ecu)}\`;
    }
    performSmartSearch('eepromType', query, searchUrl);
}

// دوال الأحداث
function triggerBrandChange() {
    // يمكن إضافة منطق إضافي عند تغيير الماركة
    console.log('تم تغيير الماركة');
}

function triggerModelChange() {
    // يمكن إضافة منطق إضافي عند تغيير الموديل
    console.log('تم تغيير الموديل');
}

// إخفاء الاقتراحات عند النقر خارجها
document.addEventListener('click', function(event) {
    if (currentField && !event.target.closest(\`#\${currentField}Input, #\${currentField}Suggestions\`)) {
        hideSuggestions(currentField);
    }
});

// تنظيف المهايئات عند تحديث الصفحة
window.addEventListener('beforeunload', function() {
    Object.values(searchTimeouts).forEach(timeout => clearTimeout(timeout));
});
</script>
JS;

// تعريف محتوى الصفحة
ob_start();
?>
<div class="container">
    <h2><?= $display_title ?></h2>

    <?php
    // عرض رسائل الخطأ أو النجاح
    if ($error)   showMessage('danger', $error);
    if ($success) showMessage('success', $success);
    ?>

    <form method="POST" enctype="multipart/form-data" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="form">
        <!-- توكن CSRF -->
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        
        <!-- ماركة السيارة مع البحث الذكي -->
        <div class="form-group">
            <label for="brandInput">ماركة السيارة:</label>
            <input type="text" id="brandInput" name="brand" required
                   maxlength="50"
                   value="<?= htmlspecialchars($brand ?? '', ENT_QUOTES) ?>"
                   onkeyup="searchBrands(this.value)"
                   onkeydown="handleKeyDown('brand', event)"
                   placeholder="اكتب لبدء البحث..."
                   autocomplete="off">
            <div id="brandSuggestions" class="suggestions-container"></div>
        </div>

        <!-- موديل السيارة مع البحث الذكي -->
        <div class="form-group">
            <label for="modelInput">موديل السيارة:</label>
            <input type="text" id="modelInput" name="model" required
                   maxlength="50"
                   value="<?= htmlspecialchars($model ?? '', ENT_QUOTES) ?>"
                   onkeyup="searchModels(this.value)"
                   onkeydown="handleKeyDown('model', event)"
                   placeholder="اكتب لبدء البحث..."
                   autocomplete="off">
            <div id="modelSuggestions" class="suggestions-container"></div>
        </div>

        <!-- سنة الصنع مع البحث الذكي -->
        <div class="form-group">
            <label for="yearInput">سنة الصنع (اختياري):</label>
            <input type="text" id="yearInput" name="year"
                   maxlength="4"
                   pattern="[0-9]+"
                   title="يرجى إدخال أرقام فقط"
                   value="<?= htmlspecialchars($year ?? '', ENT_QUOTES) ?>"
                   onkeyup="searchYears(this.value)"
                   onkeydown="handleKeyDown('year', event)"
                   placeholder="اكتب لبدء البحث..."
                   autocomplete="off">
            <div id="yearSuggestions" class="suggestions-container"></div>
            <small class="form-text">السنة اختيارية ولكنها تساعد في تحديد نوع ECU المناسب</small>
        </div>

        <!-- رقم وحدة ECU مع البحث الذكي -->
        <div class="form-group">
            <label for="ecuNumberInput">رقم وحدة ECU:</label>
            <input type="text" id="ecuNumberInput" name="ecu_number" required
                   maxlength="50"
                   pattern="[A-Za-z0-9-.]+"
                   title="يرجى إدخال أرقام وحروف وعلامات - و . فقط"
                   value="<?= htmlspecialchars($ecu_number ?? '', ENT_QUOTES) ?>"
                   onkeyup="searchECUs(this.value)"
                   onkeydown="handleKeyDown('ecuNumber', event)"
                   placeholder="اكتب لبدء البحث..."
                   autocomplete="off">
            <div id="ecuNumberSuggestions" class="suggestions-container"></div>
        </div>

        <!-- إصدار ECU (اختياري) -->
        <div class="form-group">
            <label for="ecuVersionInput">إصدار ECU (اختياري):</label>
            <input type="text" id="ecuVersionInput" name="ecu_version"
                   maxlength="20"
                   value="<?= htmlspecialchars($ecu_version ?? '', ENT_QUOTES) ?>"
                   placeholder="مثل: V1.0, Rev A، إلخ">
            <small class="form-text">إصدار البرنامج أو الهاردوير إن وجد</small>
        </div>

        <!-- نوع EEPROM مع البحث الذكي -->
        <div class="form-group">
            <label for="eepromTypeInput">نوع EEPROM:</label>
            <input type="text" id="eepromTypeInput" name="eeprom_type" required
                   maxlength="50"
                   value="<?= htmlspecialchars($eeprom_type ?? '', ENT_QUOTES) ?>"
                   onkeyup="searchEEPROMs(this.value)"
                   onkeydown="handleKeyDown('eepromType', event)"
                   placeholder="اكتب لبدء البحث..."
                   autocomplete="off">
            <div id="eepromTypeSuggestions" class="suggestions-container"></div>
            <small class="form-text">مثل: 24C02, 24C04, 24C08، إلخ</small>
        </div>

        <!-- رفع ملف EEPROM -->
        <div class="form-group">
            <label for="eeprom_file">ملف EEPROM (.bin أو .hex):</label>
            <input type="file" id="eeprom_file" name="eeprom_file" accept=".bin,.hex" required>
            <small class="form-text">الحد الأقصى لحجم الملف: 2 ميجابايت</small>
        </div>

        <button type="submit" class="btn btn-primary">إرسال الطلب</button>
    </form>
    
    <!-- عرض تحذير أمان للمستخدم -->
    <div class="alert alert-info mt-4">
        <strong>ملاحظة:</strong> يرجى التأكد من صحة الملف المرفوع، حيث سيتم التعامل معه من قبل الفريق الفني.
        <br>
        <strong>تلميح:</strong> استخدم ميزة البحث الذكي للحصول على اقتراحات دقيقة أثناء الكتابة.
    </div>
</div>

<?= $page_js ?>

<?php
$page_content = ob_get_clean();

// إدراج القالب
include __DIR__ . '/includes/layout.php';
?>