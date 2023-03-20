FROM debian:buster

RUN apt-get update && apt-get install -y --no-install-recommends \
    git python3 python3-pip python3-setuptools build-essential ffmpeg apache2 php curl ca-certificates \
    python3-certifi python3-brotli python3-websockets python3-mutagen python3-pyxattr python3-secretstorage \
    php-sqlite3 \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

RUN pip3 install yt-dlp

COPY --chown=www-data:www-data . /var/www/html/youtube-dl
# RUN cd /var/www/html \
#  && git clone https://github.com/azazar/Youtube-dl-WebUI youtube-dl \
#  && cd /var/www/html/youtube-dl \
#  && rm -rf .git README.md img .gitignore docker

COPY docker/vhost.conf /etc/apache2/sites-available/ytdlwui.conf
RUN a2dissite 000-default \
 && a2ensite ytdlwui

RUN a2enmod rewrite

RUN ln -sf /dev/stdout /var/log/apache2/youtube-dl_access.log \
 && ln -sf /dev/stdout /var/log/apache2/youtube-dl_error.log

EXPOSE 80

VOLUME /var/www/html/youtube-dl/data
VOLUME /var/www/html/youtube-dl/downloads

ENV LANG=en_US.UTF-8
ENV LC_ALL=C.UTF-8

RUN echo ServerName ytdlwui > /etc/apache2/conf-enabled/servername.conf

CMD /usr/sbin/apache2ctl -D FOREGROUND
