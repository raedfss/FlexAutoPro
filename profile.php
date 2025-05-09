<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

// استيراد الملفات الضرورية
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

$username = $_SESSION['username'];
$user_type = $_SESSION['user_role'] ?? 'user';
$email = $_SESSION['email'] ?? '';
$user_id = $_SESSION['user_id'];

// إعداد عنوان الصفحة
$page_title = 'الملف الشخصي';
$display_title = 'إدارة الملف الشخصي';

$success = '';
$error = '';

// جلب بيانات المستخدم الحالية
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// عند تقديم النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من توكن CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "❌ فشل التحقق من الأمان. يرجى تحديث الصفحة والمحاولة مرة أخرى.";
    } else {
        $new_username = sanitizeInput($_POST['username']);
        $new_password = $_POST['password'];
        $confirm = $_POST['confirm'];

        if (empty($new_username)) {
            $error = "❌ يجب إدخال الاسم.";
        } elseif (!empty($new_password) && $new_password !== $confirm) {
            $error = "❌ كلمتا المرور غير متطابقتين.";
        } else {
            try {
                // تحديث الاسم
                $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                $stmt->execute([$new_username, $user_id]);
                $_SESSION['username'] = $new_username;

                // تحديث كلمة المرور إن وُجدت
                if (!empty($new_password)) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed, $user_id]);
                }

                $success = "✅ تم تحديث بيانات الحساب بنجاح.";
                
                // تسجيل النشاط
                logActivity('profile_update', 'تم تحديث بيانات الملف الشخصي', $user_id);
            } catch (PDOException $e) {
                $error = "❌ حدث خطأ أثناء تحديث البيانات. الرجاء المحاولة مرة أخرى.";
                logError('Error updating profile: ' . $e->getMessage());
            }
        }
    }
}

// إنشاء توكن CSRF لحماية النموذج
$csrf_token = generateCSRFToken();

// CSS مخصص للصفحة
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
.avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(145deg, #3494e6, #ec6ead);
  margin: 0 auto 15px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  color: white;
  box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}
.form {
  max-width: 600px;
  margin: 0 auto;
  text-align: right;
}
.form-group {
  margin-bottom: 20px;
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
.user-email {
  color: #a8d8ff;
  margin: 15px auto 25px;
  font-size: 18px;
}
CSS;

// تعريف محتوى الصفحة
ob_start();
?>
<div class="container">
    <div class="avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
    <h2><?= $display_title ?></h2>
    <div class="user-email"><?= htmlspecialchars($email) ?></div>

    <?php
    if (!empty($error)) {
        showMessage("danger", $error);
    }
    if (!empty($success)) {
        showMessage("success", $success);
    }
    ?>

    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="form">
        <!-- توكن CSRF -->
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        
        <div class="form-group">
            <label for="username">الاسم الكامل:</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>

        <div class="form-group">
            <label>البريد الإلكتروني (غير قابل للتعديل):</label>
            <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
        </div>

        <div class="form-group">
            <label for="password">كلمة مرور جديدة (اختياري):</label>
            <input type="password" id="password" name="password">
            <small class="form-text">اتركها فارغة إذا لم تكن ترغب في تغيير كلمة المرور</small>
        </div>

        <div class="form-group">
            <label for="confirm">تأكيد كلمة المرور:</label>
            <input type="password" id="confirm" name="confirm">
        </div>

        <button type="submit" class="btn btn-primary">تحديث البيانات</button>
    </form>
</div>
<?php
$page_content = ob_get_clean();

// إدراج القالب
include __DIR__ . '/includes/layout.php';
?>