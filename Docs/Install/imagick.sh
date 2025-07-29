# Ubuntu/Debian
sudo apt-get update
sudo apt-get install imagemagick php-imagick
# Specific PHP version (es. PHP 8.1)
sudo apt-get install php8.1-imagick

# CentOS/RHEL
# sudo yum install ImageMagick ImageMagick-devel
# sudo yum install php-imagick

# Restart the web server
sudo service apache2 restart 
sudo systemctl restart php8.??-fpm 
# OR
# nginx/php-f

##########
#IF THE ABOVE DOES NOT FUNCTION...
#
# sudo apt install php8.2-dev libmagickwand-dev
# sudo pecl install imagick
# echo "extension=imagick.so" | sudo tee /etc/php/8.2/mods-available/imagick.ini
# sudo phpenmod imagick
#

# INSTALLATION TEST:
php -i | grep imagick