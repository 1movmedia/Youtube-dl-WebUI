#!/bin/bash

prefix="/var/www/html/youtube-dl"

if [ ! -e "${prefix}/config/config.php" ]; then
    echo 'Installing default configuration (use root for login and password)...'

    cp -f /config.php.TEMPLATE "${prefix}/config/config.php"
fi

db_path=$(cd $prefix && echo '<?php $config = require("config/config.php"); echo __DIR__ . "/" . $config["db"];' | php)

echo 'Fixing downloads, data and logs directory permissions'
chown -R www-data:www-data "${prefix}/downloads" "${prefix}/data" "${prefix}/logs"
chmod 0775 "${prefix}/downloads" "${prefix}/data" "${prefix}/logs"

if [ ! -e "$db_path" ];then
    echo 'Initializing database'

    sqlite3 "$db_path" < "${prefix}/init.sql"
    chown www-data:www-data "$db_path"
fi

echo 'Starting web server...'

exec /usr/sbin/apache2ctl -D FOREGROUND
