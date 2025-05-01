<?php
/**
 * نموذج طلب تعديل برمجيات ECU
 * يتم تضمينه في صفحة ecu-tuning.php
 */
?>

<div class="ecu-tuning-container">
    <div class="ecu-header">
        <h1>خدمة تعديل برمجيات ECU</h1>
        <p>حسّن أداء سيارتك مع خبراء البرمجة المتخصصين لدينا</p>
    </div>
    
    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
        <div class="alert alert-success">
            <strong>تم بنجاح!</strong> تم إرسال طلب تعديل ECU بنجاح. سنتواصل معك قريباً.
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors ?? [])): ?>
        <div class="alert alert-danger">
            <strong>خطأ:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="ecu-info-box">
        <h3>مميزات خدمة تعديل ECU</h3>
        <ul>
            <li>زيادة قوة المحرك وتحسين عزم الدوران</li>
            <li>تحسين استجابة دواسة الوقود واستهلاك الوقود</li>
            <li>إزالة محددات السرعة وتحسين أداء التروس</li>
            <li>تعديلات مخصصة حسب نوع السيارة واحتياجاتك</li>
            <li>ضمان على جميع التعديلات مع دعم فني مستمر</li>
        </ul>
    </div>
    
    <div class="ecu-form">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="car_type">نوع السيارة</label>
                <input type="text" id="car_type" name="car_type" placeholder="مثال: تويوتا كامري 2022" value="<?php echo htmlspecialchars($car_type ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="chassis">رقم الشاصي (VIN)</label>
                <input type="text" id="chassis" name="chassis" placeholder="يرجى إدخال رقم الشاصي المكون من 17 خانة" minlength="17" maxlength="17" value="<?php echo htmlspecialchars($chassis ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>نوع التعديل</label>
                <div class="tuning-types">
                    <label class="tuning-type-option">
                        <input type="radio" name="tuning_type" value="Stage 1" <?php echo ($tuning_type ?? '') === 'Stage 1' ? 'checked' : ''; ?> required>
                        <span>Stage 1 - تعديل أساسي</span>
                    </label>
                    <label class="tuning-type-option">
                        <input type="radio" name="tuning_type" value="Stage 2" <?php echo ($tuning_type ?? '') === 'Stage 2' ? 'checked' : ''; ?>>
                        <span>Stage 2 - تعديل متوسط</span>
                    </label>
                    <label class="tuning-type-option">
                        <input type="radio" name="tuning_type" value="Stage 3" <?php echo ($tuning_type ?? '') === 'Stage 3' ? 'checked' : ''; ?>>
                        <span>Stage 3 - تعديل متقدم</span>
                    </label>
                    <label class="tuning-type-option">
                        <input type="radio" name="tuning_type" value="Eco" <?php echo ($tuning_type ?? '') === 'Eco' ? 'checked' : ''; ?>>
                        <span>Eco - توفير الوقود</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label for="ecu_file">ملف ECU (اختياري)</label>
                <input type="file" id="ecu_file" name="ecu_file" class="file-input">
                <small>يمكنك رفع نسخة من ملف ECU الحالي إذا كان متوفراً لديك</small>
            </div>
            
            <div class="form-group">
                <label for="notes">ملاحظات إضافية</label>
                <textarea id="notes" name="notes" rows="5" placeholder="أي معلومات إضافية ترغب في إضافتها حول طلبك"><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
            </div>
            
            <button type="submit">إرسال طلب التعديل</button>
        </form>
    </div>
</div>

<script>
    // تحسين تجربة المستخدم عند اختيار نوع التعديل
    document.addEventListener('DOMContentLoaded', function() {
        const tuningOptions = document.querySelectorAll('.tuning-type-option');
        
        tuningOptions.forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            
            // إضافة الكلاس عند التحميل إذا كان مختاراً
            if (radio.checked) {
                option.classList.add('selected');
            }
            
            // إضافة الكلاس عند النقر
            option.addEventListener('click', function() {
                tuningOptions.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                radio.checked = true;
            });
        });
    });
</script>