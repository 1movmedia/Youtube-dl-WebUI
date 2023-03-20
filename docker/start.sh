#!/bin/bash

prefix="/var/www/html/youtube-dl"

if [ ! -e "${prefix}/config/config.php" ]; then
    echo 'Installing default configuration (use root for login and password)...'

    cp -f /config.php.TEMPLATE "${prefix}/config/config.php"
fi

db_path=$(cd $prefix && echo '<?php $config = require("config/config.php"); echo __DIR__ . "/" . $config["db"];' | php)

if [ ! -e "$db_path" ];then
    echo 'Initializing database'

    sqlite3 "$db_path" < "${prefix}/init.sql"
    chown www-data:www-data "$db_path"
fi

echo 'Fixing downloads file permissions'
chown -R www-data:www-data "${prefix}/downloads"

exec /usr/sbin/apache2ctl -D FOREGROUND
