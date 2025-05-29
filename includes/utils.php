<?php
/**
 * دالة مخصصة لجلب مدخلات من المستخدم بطريقة آمنة
 * بديلة لـ filter_input لتجنب التعارض
 * @param string $method  نوع الطلب: GET أو POST
 * @param string $key     اسم المفتاح
 * @param int $filter     نوع الفلترة، افتراضي: FILTER_DEFAULT
 * @param array|int $options خيارات إضافية
 * @return mixed القيمة المفلترة أو null إن لم توجد
 */
function custom_input_filter(string $method, string $key, int $filter = FILTER_DEFAULT, array|int $options = 0): mixed {
    $source = match (strtoupper($method)) {
        'GET' => INPUT_GET,
        'POST' => INPUT_POST,
        'COOKIE' => INPUT_COOKIE,
        'SERVER' => INPUT_SERVER,
        'ENV' => INPUT_ENV,
        default => null,
    };

    if ($source === null) {
        return null;
    }

    return filter_input($source, $key, $filter, $options);
}
