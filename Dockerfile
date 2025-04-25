# 1) نستخدم صورة PHP 8.1 مع Apache
FROM php:8.1-apache

# 2) نثبت libpq-dev لتطوير PostgreSQL، ثم نثبت امتدادات PDO لكل من MySQL و PostgreSQL، وننظف الكاش
RUN apt-get update \
 && apt-get install -y libpq-dev \
 && docker-php-ext-install pdo pdo_mysql pdo_pgsql mysqli \
 && rm -rf /var/lib/apt/lists/*

# 3) نفعّل mod_rewrite إذا كنت تستخدمه في روابط صديقة (اختياري)
RUN a2enmod rewrite

# 4) نضيف ServerName عالميّاً لتسكيت التحذير
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# 5) نضبط Apache للاستماع على البورت الذي يتلقّاه من متغير البيئة $PORT (Railway يوفّره تلقائياً)
ARG PORT=80
ENV PORT=${PORT}
RUN sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf \
 && sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# 6) ننسخ كل ملفات المشروع إلى مجلد الويب الافتراضي
COPY . /var/www/html/

# 7) (اختياري) نمنح الأذونات لمجلد uploads إن وجِد
RUN if [ -d "/var/www/html/uploads" ]; then \
      chown -R www-data:www-data /var/www/html/uploads; \
    fi

# 8) نُعلِن عن البورت داخل الحاوية (Railway يمرّره للـ Host تلقائياً)
EXPOSE ${PORT}

# 9) نُشغّل Apache في المقدمة
CMD ["apache2-foreground"]
