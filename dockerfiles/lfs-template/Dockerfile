FROM suchja/wine:dev
MAINTAINER Igor Avdeev

USER root

RUN apt-get update
RUN apt-get install -y mono-devel
RUN rm -rf /tmp/*
RUN mkdir /lfs
RUN echo "xclient:docker" | chpasswd && adduser xclient sudo
COPY waitonprocess.sh /scripts/
RUN chmod +x /scripts/waitonprocess.sh

ADD ./start.sh /start.sh
RUN chmod 755 /start.sh

# directory lfs will be created by php
COPY lfs /lfs

RUN chown -R xclient /lfs

USER xclient

# update ~/.wine directory
RUN wine /usr/lib/i386-linux-gnu/wine/fakedlls/rundll32.exe

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/bin/bash", "/start.sh"]
