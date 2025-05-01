<?php
/**
 * سكربت بسيط لتغيير كلمة مرور المسؤول
 * 
 * استخدم هذا الملف لتغيير كلمة مرور المسؤول بسهولة
 * يرجى حذف هذا الملف بعد الاستخدام مباشرة!
 */

// تشغيل فقط من localhost
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    die('يجب تشغيل هذا الملف محليًا فقط!');
}

// اتصال بقاعدة البيانات
require_once 'includes/db.php';

// التعامل مع النموذج
$message = '';
$error = '';
$admin_email = 'raedfss@hotmail.com'; // البريد الإلكتروني للمسؤول

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        // التحقق من تطابق كلمتي المرور
        if ($new_password !== $confirm_password) {
            $error = 'كلمتا المرور غير متطابقتين';
        } 
        // التحقق من طول كلمة المرور
        elseif (strlen($new_password) < 8) {
            $error = 'يجب أن تكون كلمة المرور 8 أحرف على الأقل';
        } 
        // التحقق من تعقيد كلمة المرور
        elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $new_password)) {
            $error = 'يجب أن تحتوي كلمة المرور على حروف كبيرة وصغيرة وأرقام';
        } 
        else {
            try {
                // تشفير كلمة المرور
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // تحديث كلمة المرور في قاعدة البيانات
                $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
                $stmt->execute([
                    'password' => $hashed_password,
                    'email' => $admin_email
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $message = 'تم تغيير كلمة المرور بنجاح!';
                } else {
                    $error = 'لم يتم تغيير كلمة المرور. تأكد من أن البريد الإلكتروني صحيح.';
                }
            } catch (PDOException $e) {
                $error = 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تغيير كلمة مرور المسؤول</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #0056b3;
            margin-top: 0;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
        .button {
            background: #0056b3;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: block;
            width: 100%;
        }
        .button:hover {
            background: #003d82;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>تغيير كلمة مرور المسؤول</h1>
        
        <?php if ($message): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="warning">
            <strong>تحذير:</strong> هذا الملف مخصص لتغيير كلمة مرور المسؤول. يرجى حذفه بعد الاستخدام مباشرة.
        </div>
        
        <form method="post">
            <div class="form-group">
                <label for="new_password">كلمة المرور الجديدة:</label>
                <input type="password" id="new_password" name="new_password" required>
                <div class="password-requirements">
                    * يجب أن تكون كلمة المرور 8 أحرف على الأقل وتحتوي على حرف كبير، حرف صغير، ورقم واحد على الأقل.
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">تأكيد كلمة المرور:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="button">تغيير كلمة المرور</button>
        </form>
    </div>
</body>
</html>