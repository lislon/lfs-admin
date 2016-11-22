FROM suchja/wine:dev
MAINTAINER Igor Avdeev

USER root

RUN apt-get update
RUN apt-get install -y mono-devel
RUN rm -rf /tmp/*
RUN mkdir /lfs

ADD ./start.sh /start.sh
RUN chmod 755 /start.sh

USER xclient

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/bin/bash", "/start.sh"]

# dicrectory lfs will be created by php
COPY lfs /lfs

#RUN chown -R xclient /lfs

