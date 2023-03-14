FROM debian:buster

RUN apt-get update && apt-get install -y --no-install-recommends \
    git python python-pip ffmpeg apache2 php curl ca-certificates \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

RUN pip install --upgrade youtube-dl

COPY --chown=www-data:www-data . /var/www/html/youtube-dl
# RUN cd /var/www/html \
#  && git clone https://github.com/azazar/Youtube-dl-WebUI youtube-dl \
#  && cd /var/www/html/youtube-dl \
#  && rm -rf .git README.md img .gitignore docker

COPY docker/vhost.conf /etc/apache2/sites-available/ytdlwui.conf
RUN a2dissite 000-default \
 && a2ensite ytdlwui

RUN ln -sf /dev/stdout /var/log/apache2/youtube-dl_access.log \
 && ln -sf /dev/stderr /var/log/apache2/youtube-dl_error.log

EXPOSE 80

VOLUME /var/www/html/youtube-dl/downloads

ENV LANG=en_US.UTF-8
ENV LC_ALL=C.UTF-8

RUN echo ServerName ytdlwui > /etc/apache2/conf-enabled/servername.conf
RUN echo LogLevel debug > /etc/apache2/conf-enabled/debug.conf

CMD youtube-dl -U && /usr/sbin/apache2ctl -D FOREGROUND
