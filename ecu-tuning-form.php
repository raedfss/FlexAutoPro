<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php elseif (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="alert alert-success">
        ✅ تم إرسال طلب تعديل ECU بنجاح.
    </div>
<?php endif; ?>

<div class="ticket-form-container">
    <div class="form-card">
        <h2 class="form-title"><i class="fas fa-microchip"></i> طلب تعديل ECU</h2>
        <form method="post" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="car_type" class="form-label">نوع السيارة:</label>
                <input type="text" id="car_type" name="car_type" class="form-control"
                       value="<?= htmlspecialchars($car_type ?? '') ?>"
                       placeholder="مثال: Hyundai Elantra 2021" required>
            </div>

            <div class="form-group">
                <label for="chassis" class="form-label">رقم الشاصي (VIN):</label>
                <input type="text" id="chassis" name="chassis" class="form-control"
                       value="<?= htmlspecialchars($chassis ?? '') ?>"
                       placeholder="KMHD84LF0LU123456" maxlength="17" required>
                <div class="vin-validation" id="vin_validation"></div>
            </div>

            <div class="form-group">
                <label for="tuning_type" class="form-label">نوع التعديل المطلوب:</label>
                <select id="tuning_type" name="tuning_type" class="form-select" required>
                    <option value="" disabled selected>-- اختر نوع التعديل --</option>
                    <?php
                    $options = [
                        "زيادة القوة والعزم",
                        "تحسين استهلاك الوقود",
                        "تعديل DPF/EGR",
                        "إزالة محدد السرعة",
                        "تفعيل ميزات مخفية",
                        "تعديل مخصص"
                    ];
                    foreach ($options as $option):
                        $selected = (isset($tuning_type) && $tuning_type === $option) ? 'selected' : '';
                        echo "<option value=\"$option\" $selected>$option</option>";
                    endforeach;
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="ecu_file" class="form-label">ملف ECU (اختياري):</label>
                <div class="file-input-container">
                    <input type="file" id="ecu_file" name="ecu_file" class="file-input"
                           accept=".bin,.hex,.ori,.org,.md5,.cab">
                    <label for="ecu_file" class="file-input-button">
                        <i class="fas fa-upload"></i> اختر ملف (bin, hex, ori, org)
                    </label>
                </div>
                <div class="vin-validation">ملاحظة: يمكن رفع الملف لاحقاً أو إرساله عبر البريد الإلكتروني.</div>
            </div>

            <div class="form-group">
                <label for="notes" class="form-label">ملاحظات إضافية:</label>
                <textarea id="notes" name="notes" class="form-control form-textarea"
                          placeholder="أي معلومات إضافية ترغب في إضافتها لطلبك..."><?= htmlspecialchars($notes ?? '') ?></textarea>
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-paper-plane"></i> إرسال الطلب
            </button>
        </form>
    </div>
</div>
