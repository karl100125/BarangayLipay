# Gumamit ng official PHP na may Apache server
FROM php:8.2-apache

# Ayusin ang sinesenyas na error ng Apache sa Render (ServerName)
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Kopyahin lahat ng files mo papasok sa server ng Render
COPY . /var/www/html/

# I-enable ang mod_rewrite (kung gumagamit ka ng clean URLs)
RUN a2enmod rewrite

# I-install ang mga kailangang PHP extensions para sa Database, API, at SMTP
RUN docker-php-ext-install mysqli pdo_mysql curl