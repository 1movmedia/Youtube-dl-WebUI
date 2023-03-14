FROM debian:buster

RUN apt-get update && apt-get install -y --no-install-recommends \
    git python python-pip ffmpeg apache2 php curl ca-certificates \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

#Install youtube-dl
#RUN curl https://yt-dl.org/downloads/latest/youtube-dl -o /usr/local/bin/youtube-dl && chmod a+x /usr/local/bin/youtube-dl
RUN pip install --upgrade youtube-dl

WORKDIR /
RUN mkdir /www

WORKDIR /www
RUN git clone https://github.com/azazar/Youtube-dl-WebUI youtube-dl \
 && cd /www/youtube-dl \
 && rm -rf .git README.md img .gitignore docker

WORKDIR /
RUN chmod -R 755 /www && chown -R www-data:www-data /www

COPY ./docker/vhost.conf /etc/apache2/conf.d/extra/vhost.conf

RUN ln -sf /dev/stdout /var/log/apache2/youtube-dl_access.log
RUN ln -sf /dev/stderr /var/log/apache2/youtube-dl_error.log

EXPOSE 80

VOLUME /www/youtube-dl/downloads

CMD youtube-dl -U && /usr/sbin/apache2ctl -D FOREGROUND
