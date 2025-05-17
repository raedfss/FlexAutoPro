
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

// دالة عرض الرسائل - إضافة لمنع خطأ Undefined variable
if (!function_exists('showMessage')) {
    function showMessage($type, $text) {
        echo '<div class="alert alert-' . $type . '">' . $text . '</div>';
    }
}

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
                                
                                $success = "✅ تم إرسال طلب مسح بيانات Airbag بنجاح. سيتم مراجعته قريباً.";
                                
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

// CSS مخصص للصفحة مع إضافة تنسيقات محسنة للبحث الذكي
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
  padding: 12px 40px 12px 12px;
  border-radius: 8px;
  border: 1px solid rgba(66, 135, 245, 0.4);
  background: rgba(0, 40, 80, 0.4);
  color: white;
  box-sizing: border-box;
  transition: all 0.3s ease;
}

.form-group input:focus {
  border-color: #00d4ff;
  box-shadow: 0 0 15px rgba(0, 212, 255, 0.2);
  outline: none;
}

.form-group input[type="file"] {
  padding: 10px;
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
  text-decoration: none;
  display: inline-block;
}

.btn-primary {
  background: linear-gradient(145deg, #1e90ff, #0070cc);
  color: white;
  box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}

.btn-primary:hover {
  background: linear-gradient(145deg, #2eaaff, #0088ff);
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(0,0,0,0.4);
}

.btn-secondary {
  background: linear-gradient(145deg, #6c757d, #5a6268);
  color: white;
  box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}

.btn-secondary:hover {
  background: linear-gradient(145deg, #7a8288, #6c757d);
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(0,0,0,0.4);
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

/* تنسيقات محسنة للبحث الذكي */
.suggestions-container {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: rgba(0, 20, 40, 0.95);
  border: 1px solid rgba(66, 135, 245, 0.4);
  border-top: none;
  border-radius: 0 0 12px 12px;
  max-height: 250px;
  overflow-y: auto;
  z-index: 1000;
  display: none;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(5px);
}

.suggestion-item {
  padding: 12px 15px;
  cursor: pointer;
  color: #fff;
  text-align: right;
  border-bottom: 1px solid rgba(66, 135, 245, 0.2);
  transition: all 0.2s ease;
}

.suggestion-item:hover {
  background: rgba(30, 144, 255, 0.3);
  padding-right: 20px;
}

.suggestion-item:last-child {
  border-bottom: none;
}

.suggestion-item.active {
  background: rgba(30, 144, 255, 0.4);
  padding-right: 20px;
}

.no-suggestions {
  padding: 15px;
  color: #aaa;
  text-align: center;
  font-style: italic;
}

.loading-indicator {
  padding: 15px;
  color: #a8d8ff;
  text-align: center;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.loading-spinner {
  width: 20px;
  height: 20px;
  border: 3px solid rgba(66, 135, 245, 0.3);
  border-radius: 50%;
  border-top-color: #a8d8ff;
  animation: spin 1s infinite linear;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* تحسينات إضافية لتجربة المستخدم */
.form-group input.with-suggestions {
  border-radius: 8px 8px 0 0;
  border-bottom-color: rgba(66, 135, 245, 0.2);
}

/* أيقونات داخل حقول الإدخال */
.input-icon {
  position: absolute;
  top: 40px;
  right: 12px;
  color: rgba(66, 135, 245, 0.7);
  pointer-events: none;
}

/* تأثيرات إضافية */
.form-control-animated {
  transition: all 0.3s ease;
}

.form-control-animated:focus {
  transform: translateY(-2px);
}

.form-group.has-value label {
  color: #00d4ff;
}

/* تنسيقات لقسم المعلومات والمساعدة */
.info-section {
  background: rgba(0, 40, 80, 0.3);
  border-radius: 10px;
  padding: 20px;
  margin-top: 30px;
  border: 1px solid rgba(66, 135, 245, 0.2);
}

.info-title {
  color: #00d4ff;
  font-size: 1.2rem;
  margin-bottom: 15px;
  font-weight: bold;
}

.info-content {
  color: #a8d8ff;
  font-size: 0.9rem;
  line-height: 1.6;
}

.info-steps {
  text-align: right;
  padding-right: 20px;
}

.info-steps li {
  margin-bottom: 10px;
}

/* تنسيق زر التحميل الخاص بالملف */
.file-upload-container {
  position: relative;
  overflow: hidden;
  display: inline-block;
  width: 100%;
}

.file-upload-btn {
  background: linear-gradient(145deg, #254e77, #1a3c5e);
  color: white;
  border-radius: 8px;
  padding: 12px;
  display: block;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;
  border: 1px solid rgba(66, 135, 245, 0.4);
}

.file-upload-btn:hover {
  background: linear-gradient(145deg, #2e5d8c, #1e4a75);
  transform: translateY(-2px);
}

.file-upload-container input[type="file"] {
  position: absolute;
  left: 0;
  top: 0;
  opacity: 0;
  width: 100%;
  height: 100%;
  cursor: pointer;
}

.file-name-display {
  margin-top: 8px;
  padding: 5px 10px;
  background: rgba(0, 40, 80, 0.2);
  border-radius: 5px;
  color: #a8d8ff;
  text-align: center;
  font-size: 0.9em;
  display: none;
}

.progress-container {
  height: 5px;
  width: 100%;
  background-color: rgba(0, 40, 80, 0.3);
  border-radius: 10px;
  margin-top: 10px;
  overflow: hidden;
  display: none;
}

.progress-bar {
  height: 100%;
  width: 0%;
  background: linear-gradient(90deg, #1e90ff, #00d4ff);
  border-radius: 10px;
  transition: width 0.3s ease;
}

/* علامة تحقق جديدة للحقول عند إدخال قيمة صحيحة */
.form-group.validated::after {
  content: "✓";
  position: absolute;
  top: 42px;
  left: 12px;
  color: #28a745;
  font-weight: bold;
}

/* قسم "جاري البحث" أكثر جاذبية */
.searching-effect {
  font-weight: bold;
  background: linear-gradient(90deg, #1e90ff, #00d4ff, #1e90ff);
  background-size: 200% auto;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  animation: gradient 2s linear infinite;
}

@keyframes gradient {
  to {
    background-position: 200% center;
  }
}

/* تحسينات على ترويسة الصفحة */
.page-header {
  position: relative;
  margin-bottom: 30px;
}

.page-header h2 {
  display: inline-block;
  background: linear-gradient(90deg, #1e90ff, #00d4ff);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  padding-bottom: 10px;
  margin-bottom: 5px;
}

.page-header::after {
  content: "";
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 100px;
  height: 3px;
  background: linear-gradient(90deg, #1e90ff, #00d4ff);
  border-radius: 3px;
}

.header-subtitle {
  color: #a8d8ff;
  font-size: 0.9rem;
  margin-top: 5px;
}
CSS;

// JavaScript محسن للبحث الذكي
$page_js = <<<JS
<script>
// متغيرات عامة للبحث الذكي
let searchTimeouts = {};
let currentFocus = -1;
let currentField = null;
let searchHistory = {};
let validatedFields = new Set();

// تحسين تجربة المستخدم بتنفيذ الأحداث بعد تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    // إضافة الأيقونات لحقول الإدخال
    addInputIcons();
    
    // تهيئة معالجة ملف الرفع
    initFileUploadHandler();
    
    // إظهار رسائل الخطأ أو النجاح بطريقة متحركة
    animateMessages();
    
    // تفعيل التحقق من الحقول عند تغيير قيمتها
    setupFormValidation();
    
    // إضافة أزرار الماركات الشائعة
    addQuickBrandButtons();
    
    // تحميل مكتبة FontAwesome إذا لم تكن موجودة
    if (!document.querySelector('link[href*="font-awesome"]')) {
        var fontAwesome = document.createElement('link');
        fontAwesome.rel = 'stylesheet';
        fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
        document.head.appendChild(fontAwesome);
    }
    
    // إضافة تأثيرات التمرير
    addScrollEffects();
});

// دالة لإضافة أيقونات إلى حقول الإدخال
function addInputIcons() {
    var iconMap = {
        'brandInput': '<i class="fas fa-car"></i>',
        'modelInput': '<i class="fas fa-car-side"></i>',
        'yearInput': '<i class="fas fa-calendar-alt"></i>',
        'ecuNumberInput': '<i class="fas fa-microchip"></i>',
        'ecuVersionInput': '<i class="fas fa-code-branch"></i>',
        'eepromTypeInput': '<i class="fas fa-memory"></i>'
    };
    
    for (var id in iconMap) {
        if (iconMap.hasOwnProperty(id)) {
            var inputElement = document.getElementById(id);
            if (inputElement && inputElement.parentElement) {
                var inputGroup = inputElement.parentElement;
                var iconElement = document.createElement('span');
                iconElement.className = 'input-icon';
                iconElement.innerHTML = iconMap[id];
                inputGroup.appendChild(iconElement);
            }
        }
    }
}

// دالة لتهيئة معالجة ملف الرفع
function initFileUploadHandler() {
    var fileInput = document.getElementById('eeprom_file');
    var fileNameDisplay = document.querySelector('.file-name-display');
    var progressContainer = document.querySelector('.progress-container');
    var progressBar = document.querySelector('.progress-bar');
    
    if (fileInput && fileNameDisplay) {
        fileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                var fileName = this.files[0].name;
                var fileSize = (this.files[0].size / 1024 / 1024).toFixed(2);
                
                fileNameDisplay.textContent = fileName + ' (' + fileSize + ' MB)';
                fileNameDisplay.style.display = 'block';
                
                // للتوضيح فقط - محاكاة تقدم التحميل
                if (progressContainer && progressBar) {
                    progressContainer.style.display = 'block';
                    var width = 0;
                    var interval = setInterval(function() {
                        if (width >= 100) {
                            clearInterval(interval);
                            setTimeout(function() {
                                progressContainer.style.display = 'none';
                            }, 500);
                        } else {
                            width += 5;
                            progressBar.style.width = width + '%';
                        }
                    }, 50);
                }
                
                // التحقق من الامتداد
                var extension = fileName.split('.').pop().toLowerCase();
                if (['bin', 'hex'].indexOf(extension) === -1) {
                    showTooltip(fileInput, 'يجب أن يكون الملف بصيغة .bin أو .hex فقط');
                }
            }
        });
    }
}

// دالة إظهار رسائل الخطأ أو النجاح بطريقة متحركة
function animateMessages() {
    var alerts = document.querySelectorAll('.alert');
    for (var i = 0; i < alerts.length; i++) {
        var alert = alerts[i];
        // تطبيق تأثير ظهور تدريجي
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        
        (function(alertElement) {
            setTimeout(function() {
                alertElement.style.opacity = '1';
                alertElement.style.transform = 'translateY(0)';
            }, 100);
        })(alert);
        
        // إضافة زر إغلاق إذا لم يكن موجوداً
        if (!alert.querySelector('.btn-close')) {
            var closeBtn = document.createElement('button');
            closeBtn.className = 'btn-close';
            closeBtn.innerHTML = '&times;';
            closeBtn.onclick = function() {
                var currentAlert = this.parentElement;
                currentAlert.style.opacity = '0';
                currentAlert.style.transform = 'translateY(-20px)';
                setTimeout(function() {
                    currentAlert.remove();
                }, 500);
            };
            alert.appendChild(closeBtn);
            alert.classList.add('alert-dismissible');
        }
    }
}

// دالة لتعيين التحقق من الحقول
function setupFormValidation() {
    const inputs = document.querySelectorAll('.form input[type="text"]');
    
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this.id);
        });
        
        input.addEventListener('input', function() {
            // إضافة تصنيف للحقل الذي يحتوي على قيمة
            this.parentElement.classList.toggle('has-value', this.value.trim() !== '');
        });
    });
}

// دالة البحث الذكي العامة المحسنة
function performSmartSearch(field, query, action) {
    const minLength = 2;
    const suggestionContainer = document.getElementById(field + 'Suggestions');
    
    // إخفاء الاقتراحات إذا كان النص قصيراً
    if (query.length < minLength) {
        hideSuggestions(field);
        return;
    }
    
    // تحقق من سجل البحث لتجنب تكرار البحث بنفس المعايير
    const searchKey = field + ':' + action + ':' + query;
    if (searchHistory[searchKey]) {
        displaySuggestions(field, searchHistory[searchKey]);
        return;
    }
    
    // إلغاء البحث السابق إن وجد
    if (searchTimeouts[field]) {
        clearTimeout(searchTimeouts[field]);
    }
    
    // تأخير البحث لتحسين الأداء
    searchTimeouts[field] = setTimeout(() => {
        showLoading(field);
        
        // استخدام الترميز اليدوي بدلاً من encodeURIComponent
        const encodedQuery = encodeQueryParam(query);
        
        const url = 'search_airbag_ecus.php?action=' + action + '&q=' + encodedQuery;
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('فشل في الاتصال بالخادم');
                }
                return response.json();
            })
            .then(data => {
                hideLoading(field);
                // حفظ النتائج في سجل البحث
                searchHistory[searchKey] = data;
                displaySuggestions(field, data);
            })
            .catch(error => {
                console.error('خطأ في البحث:', error);
                hideLoading(field);
                showError(field, 'حدث خطأ أثناء البحث، يرجى المحاولة مرة أخرى');
            });
    }, 300); // تأخير 300ms
}

// دالة مساعدة لترميز معلمات URL بأمان
function encodeQueryParam(str) {
    if (!str) return '';
    return str.replace(/[^\w\s]/gi, function(c) {
        return '%' + c.charCodeAt(0).toString(16).padStart(2, '0');
    }).replace(/ /g, '+');
}

// دالة عرض الاقتراحات المحسنة
function displaySuggestions(field, suggestions) {
    var container = document.getElementById(field + 'Suggestions');
    var input = document.getElementById(field + 'Input');
    
    if (!container || !input) return;
    
    container.innerHTML = '';
    currentFocus = -1;
    
    if (suggestions.length === 0) {
        container.innerHTML = '<div class="no-suggestions">لا توجد نتائج مطابقة</div>';
        container.style.display = 'block';
        input.classList.add('with-suggestions');
        return;
    }
    
    for (var i = 0; i < suggestions.length; i++) {
        var item = suggestions[i];
        var div = document.createElement('div');
        div.className = 'suggestion-item';
        
        // تمييز الجزء المطابق من النص
        var query = input.value.toLowerCase();
        var itemText = item.toString();
        var lowerItemText = itemText.toLowerCase();
        
        if (lowerItemText.includes(query)) {
            var startIndex = lowerItemText.indexOf(query);
            var endIndex = startIndex + query.length;
            
            var beforeMatch = itemText.substring(0, startIndex);
            var match = itemText.substring(startIndex, endIndex);
            var afterMatch = itemText.substring(endIndex);
            
            div.innerHTML = beforeMatch + '<strong style="color:#00d4ff">' + match + '</strong>' + afterMatch;
        } else {
            div.textContent = itemText;
        }
        
        // يجب استخدام IIFE للحفاظ على قيمة المتغير في وظيفة الاستدعاء
        (function(index, itemValue) {
            div.onclick = function() {
                selectSuggestion(field, itemValue);
            };
            div.addEventListener('mouseenter', function() {
                currentFocus = index;
                updateActiveSuggestion(field);
            });
        })(i, item);
        
        container.appendChild(div);
    }
    
    container.style.display = 'block';
    input.classList.add('with-suggestions');
    currentField = field;
    
    // إضافة تأثير للظهور
    container.style.opacity = '0';
    container.style.transform = 'translateY(-10px)';
    container.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    
    setTimeout(function() {
        container.style.opacity = '1';
        container.style.transform = 'translateY(0)';
    }, 10);
}