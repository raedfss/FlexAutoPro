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
    const iconMap = {
        'brandInput': '<i class="fas fa-car"></i>',
        'modelInput': '<i class="fas fa-car-side"></i>',
        'yearInput': '<i class="fas fa-calendar-alt"></i>',
        'ecuNumberInput': '<i class="fas fa-microchip"></i>',
        'ecuVersionInput': '<i class="fas fa-code-branch"></i>',
        'eepromTypeInput': '<i class="fas fa-memory"></i>'
    };
    
    Object.entries(iconMap).forEach(([id, icon]) => {
        const inputGroup = document.getElementById(id)?.parentElement;
        if (inputGroup) {
            const iconElement = document.createElement('span');
            iconElement.className = 'input-icon';
            iconElement.innerHTML = icon;
            inputGroup.appendChild(iconElement);
        }
    });
}

// دالة لتهيئة معالجة ملف الرفع
function initFileUploadHandler() {
    const fileInput = document.getElementById('eeprom_file');
    const fileNameDisplay = document.querySelector('.file-name-display');
    const progressContainer = document.querySelector('.progress-container');
    const progressBar = document.querySelector('.progress-bar');
    
    if (fileInput && fileNameDisplay) {
        fileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                const fileName = this.files[0].name;
                const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2);
                
                fileNameDisplay.textContent = fileName + ' (' + fileSize + ' MB)';
                fileNameDisplay.style.display = 'block';
                
                // للتوضيح فقط - محاكاة تقدم التحميل
                if (progressContainer && progressBar) {
                    progressContainer.style.display = 'block';
                    let width = 0;
                    const interval = setInterval(() => {
                        if (width >= 100) {
                            clearInterval(interval);
                            setTimeout(() => {
                                progressContainer.style.display = 'none';
                            }, 500);
                        } else {
                            width += 5;
                            progressBar.style.width = width + '%';
                        }
                    }, 50);
                }
                
                // التحقق من الامتداد
                const extension = fileName.split('.').pop().toLowerCase();
                if (['bin', 'hex'].indexOf(extension) === -1) {
                    showTooltip(fileInput, 'يجب أن يكون الملف بصيغة .bin أو .hex فقط');
                }
            }
        });
    }
}

// دالة لإظهار رسائل الخطأ أو النجاح بطريقة متحركة
function animateMessages() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        // تطبيق تأثير ظهور تدريجي
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        
        setTimeout(() => {
            alert.style.opacity = '1';
            alert.style.transform = 'translateY(0)';
        }, 100);
        
        // إضافة زر إغلاق إذا لم يكن موجوداً
        if (!alert.querySelector('.btn-close')) {
            const closeBtn = document.createElement('button');
            closeBtn.className = 'btn-close';
            closeBtn.innerHTML = '&times;';
            closeBtn.onclick = function() {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    alert.remove();
                }, 500);
            };
            alert.appendChild(closeBtn);
            alert.classList.add('alert-dismissible');
        }
    });
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
    const container = document.getElementById(field + 'Suggestions');
    const input = document.getElementById(field + 'Input');
    
    if (!container || !input) return;
    
    container.innerHTML = '';
    currentFocus = -1;
    
    if (suggestions.length === 0) {
        container.innerHTML = '<div class="no-suggestions">لا توجد نتائج مطابقة</div>';
        container.style.display = 'block';
        input.classList.add('with-suggestions');
        return;
    }
    
    for (let i = 0; i < suggestions.length; i++) {
        const item = suggestions[i];
        const div = document.createElement('div');
        div.className = 'suggestion-item';
        
        // تمييز الجزء المطابق من النص
        const query = input.value.toLowerCase();
        const itemText = item.toString();
        const lowerItemText = itemText.toLowerCase();
        
        if (lowerItemText.includes(query)) {
            const startIndex = lowerItemText.indexOf(query);
            const endIndex = startIndex + query.length;
            
            const beforeMatch = itemText.substring(0, startIndex);
            const match = itemText.substring(startIndex, endIndex);
            const afterMatch = itemText.substring(endIndex);
            
            div.innerHTML = beforeMatch + '<strong style="color:#00d4ff">' + match + '</strong>' + afterMatch;
        } else {
            div.textContent = itemText;
        }
        
        // إضافة مستمعات الأحداث
        div.onclick = function() {
            selectSuggestion(field, item);
        };
        
        div.addEventListener('mouseenter', function() {
            currentFocus = i;
            updateActiveSuggestion(field);
        });
        
        container.appendChild(div);
    }
    
    container.style.display = 'block';
    input.classList.add('with-suggestions');
    currentField = field;
    
    // إضافة تأثير للظهور
    container.style.opacity = '0';
    container.style.transform = 'translateY(-10px)';
    container.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    
    setTimeout(() => {
        container.style.opacity = '1';
        container.style.transform = 'translateY(0)';
    }, 10);
}

// دالة اختيار اقتراح محسنة
function selectSuggestion(field, value) {
    const input = document.getElementById(field + 'Input');
    if (input) {
        input.value = value;
        hideSuggestions(field);
        
        // تحقق من الحقل بعد الاختيار
        validateField(field + 'Input');
        
        // تشغيل أحداث خاصة حسب الحقل
        if (field === 'brand') {
            // مسح النماذج والسنوات عند تغيير الماركة
            clearField('model');
            clearField('year');
            clearField('ecuNumber');
            clearField('eepromType');
            triggerBrandChange();
        } else if (field === 'model') {
            // مسح السنوات عند تغيير النموذج
            clearField('year');
            clearField('ecuNumber');
            clearField('eepromType');
            triggerModelChange();
        } else if (field === 'year') {
            clearField('ecuNumber');
            clearField('eepromType');
            triggerYearChange();
        } else if (field === 'ecuNumber') {
            clearField('eepromType');
            triggerECUChange();
        }
        
        // تحريك التركيز إلى الحقل التالي
        moveToNextField(field);
    }
}

// دالة للانتقال إلى الحقل التالي
function moveToNextField(currentField) {
    const fieldOrder = ['brand', 'model', 'year', 'ecuNumber', 'ecuVersion', 'eepromType'];
    const currentIndex = fieldOrder.indexOf(currentField);
    
    if (currentIndex !== -1 && currentIndex < fieldOrder.length - 1) {
        const nextField = fieldOrder[currentIndex + 1] + 'Input';
        const nextInput = document.getElementById(nextField);
        
        if (nextInput) {
            setTimeout(() => {
                nextInput.focus();
            }, 100);
        }
    }
}

// دالة إخفاء الاقتراحات
function hideSuggestions(field) {
    const container = document.getElementById(field + 'Suggestions');
    const input = document.getElementById(field + 'Input');
    
    if (container) {
        // إضافة تأثير للاختفاء
        container.style.opacity = '0';
        container.style.transform = 'translateY(-10px)';
        
        setTimeout(() => {
            container.style.display = 'none';
        }, 300);
    }
    
    if (input) {
        input.classList.remove('with-suggestions');
    }
    
    currentField = null;
    currentFocus = -1;
}

// دالة عرض حالة التحميل محسنة
function showLoading(field) {
    const container = document.getElementById(field + 'Suggestions');
    if (container) {
        container.innerHTML = 
            '<div class="loading-indicator">' +
            '<div class="loading-spinner"></div>' +
            '<span class="searching-effect">جاري البحث...</span>' +
            '</div>';
        container.style.display = 'block';
        
        const input = document.getElementById(field + 'Input');
        if (input) {
            input.classList.add('with-suggestions');
        }
        
        // تأثير الظهور
        container.style.opacity = '0';
        container.style.transform = 'translateY(-10px)';
        container.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        
        setTimeout(() => {
            container.style.opacity = '1';
            container.style.transform = 'translateY(0)';
        }, 10);
    }
}

// دالة إخفاء حالة التحميل
function hideLoading(field) {
    // يتم استدعاؤها تلقائياً عند عرض النتائج
}

// دالة عرض خطأ محسنة
function showError(field, message) {
    const container = document.getElementById(field + 'Suggestions');
    if (container) {
        container.innerHTML = `<div class="no-suggestions">❌ ${message}</div>`;
        container.style.display = 'block';
        
        const input = document.getElementById(field + 'Input');
        if (input) {
            input.classList.add('with-suggestions');
            
            // تأثير إضافي للخطأ
            input.style.borderColor = 'rgba(220, 53, 69, 0.5)';
            setTimeout(() => {
                input.style.borderColor = '';
            }, 2000);
        }
        
        // تأثير الظهور
        container.style.opacity = '0';
        container.style.transform = 'translateY(-10px)';
        container.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        
        setTimeout(() => {
            container.style.opacity = '1';
            container.style.transform = 'translateY(0)';
        }, 10);
    }
}

// دالة للتحقق من حقل
function validateField(fieldId) {
    const input = document.getElementById(fieldId);
    if (!input) return;
    
    const value = input.value.trim();
    const formGroup = input.parentElement;
    
    // إزالة تصنيف التحقق السابق
    formGroup.classList.remove('validated');
    
    if (value !== '') {
        // تأكد من أن الماركة والموديل موجودان قبل التحقق من الحقول الأخرى
        if (fieldId === 'yearInput' || fieldId === 'ecuNumberInput' || fieldId === 'eepromTypeInput') {
            const brand = document.getElementById('brandInput').value.trim();
            const model = document.getElementById('modelInput').value.trim();
            
            if (brand === '' || model === '') {
                showTooltip(input, 'يرجى ملء البيانات السابقة أولاً');
                return false;
            }
        }
        
        // إضافة تصنيف التحقق
        formGroup.classList.add('validated');
        validatedFields.add(fieldId);
        return true;
    }
    
    return false;
}

// دالة لإظهار تلميح
function showTooltip(element, message) {
    // إنشاء التلميح إذا لم يكن موجوداً
    let tooltip = document.querySelector('.custom-tooltip');
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.className = 'custom-tooltip';
        document.body.appendChild(tooltip);
        
        // تصميم التلميح
        tooltip.style.position = 'absolute';
        tooltip.style.backgroundColor = 'rgba(0, 0, 0, 0.9)';
        tooltip.style.color = '#fff';
        tooltip.style.padding = '8px 15px';
        tooltip.style.borderRadius = '6px';
        tooltip.style.fontSize = '14px';
        tooltip.style.zIndex = '9999';
        tooltip.style.boxShadow = '0 4px 10px rgba(0,0,0,0.3)';
        tooltip.style.border = '1px solid rgba(66, 135, 245, 0.4)';
        tooltip.style.maxWidth = '250px';
        tooltip.style.textAlign = 'center';
    }
    
    // تعيين الرسالة
    tooltip.textContent = message;
    
    // تعيين الموقع
    const rect = element.getBoundingClientRect();
    tooltip.style.top = (rect.bottom + 10) + 'px';
    tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
    
    // إظهار التلميح بتأثير
    tooltip.style.opacity = '0';
    tooltip.style.transform = 'translateY(-10px)';
    tooltip.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    tooltip.style.display = 'block';
    
    setTimeout(() => {
        tooltip.style.opacity = '1';
        tooltip.style.transform = 'translateY(0)';
    }, 10);
    
    // إخفاء التلميح بعد فترة
    setTimeout(() => {
        tooltip.style.opacity = '0';
        tooltip.style.transform = 'translateY(-10px)';
        
        setTimeout(() => {
            tooltip.style.display = 'none';
        }, 300);
    }, 3000);
}

// دالة مسح حقل محسنة
function clearField(field) {
    const input = document.getElementById(field + 'Input');
    if (input) {
        input.value = '';
        hideSuggestions(field);
        
        // إزالة تصنيف التحقق
        input.parentElement.classList.remove('validated', 'has-value');
        validatedFields.delete(field + 'Input');
    }
}

// دالة تحديث الاقتراح النشط
function updateActiveSuggestion(field) {
    const container = document.getElementById(field + 'Suggestions');
    if (!container) return;
    
    const items = container.getElementsByClassName('suggestion-item');
    
    Array.from(items).forEach((item, index) => {
        item.classList.toggle('active', index === currentFocus);
        
        // تمرير إلى العنصر النشط
        if (index === currentFocus) {
            item.scrollIntoView({
                block: 'nearest',
                behavior: 'smooth'
            });
        }
    });
}

// دالة التنقل بلوحة المفاتيح محسنة
function handleKeyDown(field, event) {
    const container = document.getElementById(field + 'Suggestions');
    if (!container || container.style.display === 'none') return;
    
    const items = container.getElementsByClassName('suggestion-item');
    if (items.length === 0) return;
    
    switch(event.key) {
        case 'ArrowDown':
            event.preventDefault();
            currentFocus = (currentFocus + 1) % items.length;
            updateActiveSuggestion(field);
            break;
            
        case 'ArrowUp':
            event.preventDefault();
            currentFocus = currentFocus <= 0 ? items.length - 1 : currentFocus - 1;
            updateActiveSuggestion(field);
            break;
            
        case 'Enter':
            event.preventDefault();
            if (currentFocus >= 0 && items[currentFocus]) {
                items[currentFocus].click();
            } else if (items.length === 1) {
                // إذا كان هناك اقتراح واحد فقط، اختره تلقائياً
                items[0].click();
            }
            break;
            
        case 'Escape':
            hideSuggestions(field);
            break;
            
        case 'Tab':
            if (items.length === 1) {
                // إذا كان هناك اقتراح واحد فقط عند الضغط على Tab، اختره تلقائياً
                event.preventDefault();
                items[0].click();
            } else {
                hideSuggestions(field);
            }
            break;
    }
}

// دوال خاصة محسنة لكل حقل
function searchBrands(query) {
    performSmartSearch('brand', query, 'brands');
}

function searchModels(query) {
    const brand = document.getElementById('brandInput').value;
    if (!brand) {
        showError('model', 'يرجى اختيار الماركة أولاً');
        return;
    }
    performSmartSearch('model', query, `models&brand=${encodeURIComponent(brand)}`);
}

function searchYears(query) {
    const brand = document.getElementById('brandInput').value;
    const model = document.getElementById('modelInput').value;
    
    if (!brand || !model) {
        showError('year', 'يرجى اختيار الماركة والموديل أولاً');
        return;
    }
    performSmartSearch('year', query, `years&brand=${encodeURIComponent(brand)}&model=${encodeURIComponent(model)}`);
}

function searchECUs(query) {
    const brand = document.getElementById('brandInput').value;
    const model = document.getElementById('modelInput').value;
    const year = document.getElementById('yearInput').value;
    
    if (!brand || !model) {
        showError('ecuNumber', 'يرجى اختيار الماركة والموديل أولاً');
        return;
    }
    
    let searchUrl = `ecus&brand=${encodeURIComponent(brand)}&model=${encodeURIComponent(model)}`;
    if (year) {
        searchUrl += `&year=${encodeURIComponent(year)}`;
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
    
    let searchUrl = `eeproms&brand=${encodeURIComponent(brand)}&model=${encodeURIComponent(model)}`;
    if (ecu) {
        searchUrl += `&ecu=${encodeURIComponent(ecu)}`;
    }
    performSmartSearch('eepromType', query, searchUrl);
}

// دوال الأحداث المحسنة
function triggerBrandChange() {
    console.log('تم تغيير الماركة');
    // يمكن إضافة محتوى الموديلات الشائعة للماركة المختارة
    const brandInput = document.getElementById('brandInput');
    const modelInput = document.getElementById('modelInput');
    
    if (brandInput && modelInput) {
        modelInput.placeholder = `اكتب موديل ${brandInput.value}...`;
        
        // تأثير بصري لتنبيه المستخدم بالحقل التالي
        setTimeout(() => {
            modelInput.classList.add('form-control-animated');
            modelInput.focus();
            
            setTimeout(() => {
                modelInput.classList.remove('form-control-animated');
            }, 1000);
        }, 100);
    }
}

function triggerModelChange() {
    console.log('تم تغيير الموديل');
    
    const brandInput = document.getElementById('brandInput');
    const modelInput = document.getElementById('modelInput');
    const yearInput = document.getElementById('yearInput');
    
    if (brandInput && modelInput && yearInput) {
        yearInput.placeholder = `سنة صنع ${brandInput.value} ${modelInput.value}...`;
        
        // تأثير بصري لتنبيه المستخدم بالحقل التالي
        setTimeout(() => {
            yearInput.classList.add('form-control-animated');
            yearInput.focus();
            
            setTimeout(() => {
                yearInput.classList.remove('form-control-animated');
            }, 1000);
        }, 100);
    }
}

function triggerYearChange() {
    console.log('تم تغيير السنة');
    
    const ecuNumberInput = document.getElementById('ecuNumberInput');
    
    if (ecuNumberInput) {
        ecuNumberInput.placeholder = `اكتب رقم وحدة ECU...`;
        
        // تأثير بصري لتنبيه المستخدم بالحقل التالي
        setTimeout(() => {
            ecuNumberInput.classList.add('form-control-animated');
            ecuNumberInput.focus();
            
            setTimeout(() => {
                ecuNumberInput.classList.remove('form-control-animated');
            }, 1000);
        }, 100);
    }
}

function triggerECUChange() {
    console.log('تم تغيير رقم الكمبيوتر');
    
    const eepromTypeInput = document.getElementById('eepromTypeInput');
    
    if (eepromTypeInput) {
        eepromTypeInput.placeholder = `اكتب نوع EEPROM...`;
        
        // تأثير بصري لتنبيه المستخدم بالحقل التالي
        setTimeout(() => {
            eepromTypeInput.classList.add('form-control-animated');
            eepromTypeInput.focus();
            
            setTimeout(() => {
                eepromTypeInput.classList.remove('form-control-animated');
            }, 1000);
        }, 100);
    }
}

// إخفاء الاقتراحات عند النقر خارجها
document.addEventListener('click', function(event) {
    if (currentField && !event.target.closest(`#${currentField}Input, #${currentField}Suggestions`)) {
        hideSuggestions(currentField);
    }
});

// تنظيف المهايئات عند تحديث الصفحة
window.addEventListener('beforeunload', function() {
    Object.values(searchTimeouts).forEach(timeout => clearTimeout(timeout));
});

// دالة عرض رسائل النظام
function showSystemMessage(message, type) {
    const container = document.createElement('div');
    container.className = `alert alert-${type}`;
    container.innerHTML = message;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'btn-close';
    closeBtn.innerHTML = '&times;';
    closeBtn.onclick = function() {
        container.style.opacity = '0';
        container.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            container.remove();
        }, 500);
    };
    
    container.appendChild(closeBtn);
    
    const form = document.querySelector('.form');
    if (form) {
        form.parentNode.insertBefore(container, form);
    }
    
    // تأثير ظهور تدريجي
    container.style.opacity = '0';
    container.style.transform = 'translateY(-20px)';
    container.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    
    setTimeout(() => {
        container.style.opacity = '1';
        container.style.transform = 'translateY(0)';
    }, 100);
    
    // إخفاء تلقائي بعد فترة
    setTimeout(() => {
        container.style.opacity = '0';
        container.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            container.remove();
        }, 500);
    }, 10000);
}

// دالة لعرض تلميح المساعدة
function showHelp(field) {
    const helpMap = {
        'brand': 'أدخل اسم الشركة المصنعة للسيارة مثل تويوتا، هوندا، بي إم دبليو، إلخ.',
        'model': 'أدخل موديل السيارة مثل كامري، أكورد، X5، إلخ.',
        'year': 'أدخل سنة صنع السيارة باستخدام 4 أرقام، مثل 2020.',
        'ecuNumber': 'أدخل رقم وحدة التحكم الإلكترونية الموجود على العلبة الخاصة بها.',
        'ecuVersion': 'أدخل رقم الإصدار إن وجد، مثل V1.0 أو Rev A.',
        'eepromType': 'أدخل نوع شريحة EEPROM مثل 24C02, 24C04, 24C08 إلخ.'
    };
    
    showTooltip(document.getElementById(field + 'Input'), helpMap[field] || 'أدخل المعلومات المطلوبة');
}

// إضافة وظيفة البحث السريع للماركات الشائعة
function addQuickBrandButtons() {
    // الماركات الشائعة
    const commonBrands = ['تويوتا', 'هوندا', 'نيسان', 'مرسيدس', 'بي إم دبليو', 'أودي', 'هيونداي', 'كيا'];
    
    // إنشاء حاوية الأزرار
    const container = document.createElement('div');
    container.className = 'quick-brands';
    container.style.display = 'flex';
    container.style.flexWrap = 'wrap';
    container.style.gap = '10px';
    container.style.justifyContent = 'center';
    container.style.margin = '15px 0';
    
    // إضافة عنوان
    const title = document.createElement('div');
    title.textContent = 'الماركات الشائعة:';
    title.style.width = '100%';
    title.style.textAlign = 'center';
    title.style.color = '#a8d8ff';
    title.style.marginBottom = '8px';
    container.appendChild(title);
    
    // إنشاء زر لكل ماركة
    commonBrands.forEach(brand => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = brand;
        button.className = 'quick-brand-btn';
        button.style.background = 'rgba(0, 40, 80, 0.5)';
        button.style.border = '1px solid rgba(66, 135, 245, 0.4)';
        button.style.borderRadius = '6px';
        button.style.padding = '5px 12px';
        button.style.color = 'white';
        button.style.cursor = 'pointer';
        button.style.transition = 'all 0.3s ease';
        
        button.onmouseover = function() {
            this.style.background = 'rgba(30, 144, 255, 0.3)';
            this.style.transform = 'translateY(-2px)';
        };
        
        button.onmouseout = function() {
            this.style.background = 'rgba(0, 40, 80, 0.5)';
            this.style.transform = 'translateY(0)';
        };
        
        button.onclick = function() {
            const brandInput = document.getElementById('brandInput');
            if (brandInput) {
                brandInput.value = brand;
                validateField('brandInput');
                triggerBrandChange();
            }
        };
        
        container.appendChild(button);
    });
    
    // إضافة الحاوية قبل نموذج البحث
    const form = document.querySelector('.form');
    if (form) {
        form.parentNode.insertBefore(container, form);
    }
}

// دالة لإضافة تأثيرات التمرير
function addScrollEffects() {
    // إضافة تأثيرات ظهور العناصر عند التمرير
    const animateItems = document.querySelectorAll('.form-group, .info-section, .alert');
    
    // تحقق من دعم IntersectionObserver
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animated-item');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            root: null,
            threshold: 0.1,
            rootMargin: '0px'
        });
        
        animateItems.forEach(item => {
            // تعيين النمط الأولي
            item.style.opacity = '0';
            item.style.transform = 'translateY(20px)';
            item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            
            observer.observe(item);
        });
        
        // إضافة تصنيف للعناصر المرئية
        document.addEventListener('scroll', () => {
            animateItems.forEach(item => {
                const rect = item.getBoundingClientRect();
                const isVisible = rect.top < window.innerHeight && rect.bottom >= 0;
                
                if (isVisible && !item.classList.contains('animated-item')) {
                    item.classList.add('animated-item');
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }
            });
        });
    } else {
        // المتصفحات التي لا تدعم IntersectionObserver
        animateItems.forEach(item => {
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        });
    }
}

// دالة للتحقق من صحة النموذج قبل الإرسال
function validateForm() {
    const requiredFields = ['brand', 'model', 'ecuNumber', 'eepromType'];
    let isValid = true;
    
    requiredFields.forEach(field => {
        const input = document.getElementById(field + 'Input');
        if (!input || !input.value.trim()) {
            isValid = false;
            input.style.borderColor = 'rgba(220, 53, 69, 0.8)';
            
            setTimeout(() => {
                input.style.borderColor = '';
            }, 3000);
            
            showTooltip(input, 'هذا الحقل مطلوب');
        }
    });
    
    // التحقق من الملف
    const fileInput = document.getElementById('eeprom_file');
    if (!fileInput || !fileInput.files.length) {
        isValid = false;
        const fileLabel = document.querySelector('.file-upload-btn');
        if (fileLabel) {
            fileLabel.style.borderColor = 'rgba(220, 53, 69, 0.8)';
            
            setTimeout(() => {
                fileLabel.style.borderColor = '';
            }, 3000);
        }
        
        showTooltip(fileInput.parentElement, 'يرجى اختيار ملف');
    }
    
    return isValid;
}
</script>
JS;

// تعريف محتوى الصفحة
ob_start();
?>
<div class="container">
    <div class="page-header">
        <h2><?= $display_title ?></h2>
        <div class="header-subtitle">نظام آمن لمسح بيانات الحادث وإعادة برمجة وحدة الإيرباق</div>
    </div>

    <?php
    // عرض رسائل الخطأ أو النجاح
    if (!empty($error)) {
        showMessage('danger', $error);
    }
    if (!empty($success)) {
        showMessage('success', $success);
    }
    ?>

    <form method="POST" enctype="multipart/form-data" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="form" onsubmit="return validateForm()">
        <!-- توكن CSRF -->
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        
        <!-- ماركة السيارة مع البحث الذكي -->
        <div class="form-group">
            <label for="brandInput">
                ماركة السيارة <span style="color: #ff6b6b">*</span>
                <button type="button" class="btn-help" onclick="showHelp('brand')" style="background: none; border: none; color: #a8d8ff; font-size: 0.8rem; cursor: help;">
                    <i class="fas fa-question-circle"></i>
                </button>
            </label>
            <input type="text" id="brandInput" name="brand" required
                   maxlength="50"
                   value="<?= htmlspecialchars($brand ?? '', ENT_QUOTES) ?>"
                   onkeyup="searchBrands(this.value)"
                   onkeydown="handleKeyDown('brand', event)"
                   placeholder="اكتب لبدء البحث..."
                   autocomplete="off"
                   class="form-control-animated">
            <div id="brandSuggestions" class="suggestions-container"></div>
        </div>

        <!-- موديل السيارة مع البحث الذكي -->
        <div class="form-group">
            <label for="modelInput">
                موديل السيارة <span style="color: #ff6b6b">*</span>
                <button type="button" class="btn-help" onclick="showHelp('model')" style="background: none; border: none; color: #a8d8ff; font-size: 0.8rem; cursor: help;">
                    <i class="fas fa-question-circle"></i>
                </button>
            </label>
            <input type="text" id="modelInput" name="model" required
                   maxlength="50"
                   value="<?= htmlspecialchars($model ?? '', ENT_QUOTES) ?>"
                   onkeyup="searchModels(this.value)"
                   onkeydown="handleKeyDown('model', event)"
                   placeholder="اختر الماركة أولاً..."
                   autocomplete="off"
                   class="form-control-animated">
            <div id="modelSuggestions" class="suggestions-container"></div>
        </div>

        <!-- سنة الصنع مع البحث الذكي -->
        <div class="form-group">
            <label for="yearInput">
                سنة الصنع (اختياري)
                <button type="button" class="btn-help" onclick="showHelp('year')" style="background: none; border: none; color: #a8d8ff; font-size: 0.8rem; cursor: help;">
                    <i class="fas fa-question-circle"></i>
                </button>
            </label>
            <input type="text" id="yearInput" name="year"
                   maxlength="4"
                   pattern="[0-9]+"
                   title="يرجى إدخال أرقام فقط"
                   value="<?= htmlspecialchars($year ?? '', ENT_QUOTES) ?>"
                   onkeyup="searchYears(this.value)"
                   onkeydown="handleKeyDown('year', event)"
                   placeholder="اختر الماركة والموديل أولاً..."
                   autocomplete="off"
                   class="form-control-animated">
            <div id="yearSuggestions" class="suggestions-container"></div>
            <small class="form-text">السنة اختيارية ولكنها تساعد في تحديد نوع ECU المناسب</small>
        </div>

        <!-- رقم وحدة ECU مع البحث الذكي -->
        <div class="form-group">
            <label for="ecuNumberInput">
                رقم وحدة ECU <span style="color: #ff6b6b">*</span>
                <button type="button" class="btn-help" onclick="showHelp('ecuNumber')" style="background: none; border: none; color: #a8d8ff; font-size: 0.8rem; cursor: help;">
                    <i class="fas fa-question-circle"></i>
                </button>
            </label>
            <input type="text" id="ecuNumberInput" name="ecu_number" required
                   maxlength="50"
                   pattern="[A-Za-z0-9-.]+"
                   title="يرجى إدخال أرقام وحروف وعلامات - و . فقط"
                   value="<?= htmlspecialchars($ecu_number ?? '', ENT_QUOTES) ?>"
                   onkeyup="searchECUs(this.value)"
                   onkeydown="handleKeyDown('ecuNumber', event)"
                   placeholder="اختر الماركة والموديل أولاً..."
                   autocomplete="off"
                   class="form-control-animated">
            <div id="ecuNumberSuggestions" class="suggestions-container"></div>
        </div>

        <!-- إصدار ECU (اختياري) -->
        <div class="form-group">
            <label for="ecuVersionInput">
                إصدار ECU (اختياري)
                <button type="button" class="btn-help" onclick="showHelp('ecuVersion')" style="background: none; border: none; color: #a8d8ff; font-size: 0.8rem; cursor: help;">
                    <i class="fas fa-question-circle"></i>
                </button>
            </label>
            <input type="text" id="ecuVersionInput" name="ecu_version"
                   maxlength="20"
                   value="<?= htmlspecialchars($ecu_version ?? '', ENT_QUOTES) ?>"
                   placeholder="مثل: V1.0, Rev A، إلخ">
            <small class="form-text">إصدار البرنامج أو الهاردوير إن وجد</small>
        </div>

        <!-- نوع EEPROM مع البحث الذكي -->
        <div class="form-group">
            <label for="eepromTypeInput">
                نوع EEPROM <span style="color: #ff6b6b">*</span>
                <button type="button" class="btn-help" onclick="showHelp('eepromType')" style="background: none; border: none; color: #a8d8ff; font-size: 0.8rem; cursor: help;">
                    <i class="fas fa-question-circle"></i>
                </button>
            </label>
            <input type="text" id="eepromTypeInput" name="eeprom_type" required
                   maxlength="50"
                   value="<?= htmlspecialchars($eeprom_type ?? '', ENT_QUOTES) ?>"
                   onkeyup="searchEEPROMs(this.value)"
                   onkeydown="handleKeyDown('eepromType', event)"
                   placeholder="اختر الماركة والموديل أولاً..."
                   autocomplete="off"
                   class="form-control-animated">
            <div id="eepromTypeSuggestions" class="suggestions-container"></div>
            <small class="form-text">مثل: 24C02, 24C04, 24C08، إلخ</small>
        </div>

        <!-- رفع ملف EEPROM محسن -->
        <div class="form-group">
            <label for="eeprom_file">
                ملف EEPROM (.bin أو .hex) <span style="color: #ff6b6b">*</span>
            </label>
            <div class="file-upload-container">
                <div class="file-upload-btn">
                    <i class="fas fa-upload"></i> اختر ملف EEPROM
                </div>
                <input type="file" id="eeprom_file" name="eeprom_file" accept=".bin,.hex" required>
            </div>
            <div class="file-name-display"></div>
            <div class="progress-container">
                <div class="progress-bar"></div>
            </div>
            <small class="form-text">الحد الأقصى لحجم الملف: 2 ميجابايت</small>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> إرسال الطلب
        </button>
        
        <a href="home.php" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> العودة للصفحة الرئيسية
        </a>
    </form>
    
    <!-- قسم المعلومات والمساعدة -->
    <div class="info-section">
        <div class="info-title">
            <i class="fas fa-info-circle"></i> معلومات عن خدمة مسح بيانات الإيرباق
        </div>
        <div class="info-content">
            <p>خدمة مسح بيانات الحادث (Airbag Reset) هي عملية آمنة لإعادة برمجة وحدة التحكم في الوسائد الهوائية بعد حادث أو صيانة. تتضمن الخطوات التالية:</p>
            
            <ol class="info-steps">
                <li>قراءة بيانات EEPROM من وحدة التحكم باستخدام جهاز برمجة مناسب.</li>
                <li>رفع الملف إلى نظامنا عبر هذه الصفحة.</li>
                <li>يقوم فريقنا الفني بتحليل البيانات ومسح سجلات الحوادث.</li>
                <li>يتم إرسال الملف المعدل إلى بريدك الإلكتروني خلال 24 ساعة.</li>
                <li>إعادة كتابة البيانات إلى وحدة التحكم باستخدام نفس جهاز البرمجة.</li>
            </ol>

            <p><strong>ملاحظة هامة:</strong> البحث الذكي يساعدك في العثور على معلومات دقيقة عن المركبة. كلما أدخلت بيانات أكثر دقة، كلما كانت النتائج أفضل.</p>
        </div>
    </div>
    
    <!-- قسم الأسئلة الشائعة -->
    <div class="info-section" style="margin-top: 20px;">
        <div class="info-title">
            <i class="fas fa-question-circle"></i> الأسئلة الشائعة
        </div>
        <div class="info-content">
            <details>
                <summary style="cursor: pointer; color: #00d4ff; margin-bottom: 10px; font-weight: bold;">كيف أعرف رقم وحدة ECU الخاصة بي؟</summary>
                <p style="padding-right: 20px;">يوجد رقم وحدة ECU عادة على ملصق على علبة وحدة التحكم في الوسائد الهوائية. يمكنك استخدام خاصية البحث الذكي للعثور على الرقم المناسب لمركبتك.</p>
            </details>
            
            <details>
                <summary style="cursor: pointer; color: #00d4ff; margin-bottom: 10px; font-weight: bold;">ما هو نوع EEPROM المطلوب؟</summary>
                <p style="padding-right: 20px;">نوع EEPROM هو شريحة الذاكرة الموجودة على لوحة ECU. الأنواع الشائعة تشمل 24C02, 24C04, 24C08, 24C16, 25C160 وغيرها. استخدم البحث الذكي لتحديد النوع المناسب لمركبتك.</p>
            </details>
            
            <details>
                <summary style="cursor: pointer; color: #00d4ff; margin-bottom: 10px; font-weight: bold;">كم من الوقت تستغرق الخدمة؟</summary>
                <p style="padding-right: 20px;">عادة ما تستغرق عملية المعالجة من 12 إلى 24 ساعة كحد أقصى، ويتم إرسال الملف المعدل إلى بريدك الإلكتروني المسجل في الحساب.</p>
            </details>
            
            <details>
                <summary style="cursor: pointer; color: #00d4ff; margin-bottom: 10px; font-weight: bold;">هل الخدمة آمنة؟</summary>
                <p style="padding-right: 20px;">نعم، جميع العمليات تتم بواسطة فنيين محترفين باستخدام أدوات متخصصة. كما أن جميع الملفات والبيانات مشفرة ومحمية.</p>
            </details>
        </div>
    </div>
</div>

<?= $page_js ?>

<?php
$page_content = ob_get_clean();

// إدراج القالب
include __DIR__ . '/includes/layout.php';
?>