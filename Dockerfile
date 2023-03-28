FROM debian:bullseye

RUN apt-get update && apt-get install -y --no-install-recommends \
    git python3 python3-pip python3-setuptools build-essential apache2 php curl ca-certificates \
    python3-certifi python3-brotli python3-websockets python3-mutagen python3-pyxattr python3-secretstorage \
    php-sqlite3 sqlite3 \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

RUN cd /usr/local/bin \
 && curl https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz --remote-name -s \
 && tar -xf ffmpeg-release-amd64-static.tar.xz \
 && cd ffmpeg-6.0-amd64-static \
 && mv ffmpeg ffprobe qt-faststart ../ \
 && cd .. \
 && rm -r ffmpeg-release-amd64-static.tar.xz ffmpeg-6.0-amd64-static

RUN pip3 install yt-dlp

COPY --chown=www-data:www-data . /var/www/html/youtube-dl

COPY docker/vhost.conf /etc/apache2/sites-available/ytdlwui.conf

COPY docker/start.sh /start.sh
COPY config/config.php.TEMPLATE /config.php.TEMPLATE

RUN a2dissite 000-default \
 && a2ensite ytdlwui \
 && rm -f /var/www/html/index.html

RUN a2enmod rewrite

RUN ln -sf /dev/stdout /var/log/apache2/youtube-dl_access.log \
 && ln -sf /dev/stdout /var/log/apache2/youtube-dl_error.log

EXPOSE 80

VOLUME /var/www/html/youtube-dl/data
VOLUME /var/www/html/youtube-dl/downloads

ENV LANG=en_US.UTF-8
ENV LC_ALL=C.UTF-8

RUN echo ServerName ytdlwui > /etc/apache2/conf-enabled/servername.conf

CMD /start.sh
