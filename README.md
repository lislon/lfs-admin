# LFS Server Manager

Данная программа позволяет управлять группой серверов LFS (Live for speed)
запущенных в Docker

Поддерживаемые функции:
 * Создание/удаление севрера
 * Получение списка серверов
 * Проверка состояние сервера
 * Запуск/остановку сервера
 * Редактирование параметров setup.cfg/welcome.txt
 * (Не реализовано) Обновление версии сервера

## Примеры использования API

### Получить список серверов

Запрос:

    GET http://localhost:8080/servers

Ответ:

    [
        {
            id: '34c8fb2b5c07'
            state: 'running',
        },
        {
            id: '34p8so2o5p07'
            state: 'running',
        }
    ]
    
    
Параметры ответа:

 * id - Идентификатор сервера
 * state - Состояние сервера. Возможны следующие значения:
    * `running` - запущен
    * `restarting` - в процессе запуска (перезапуска)
    * `stopped` - остановлен

### Получить информацию об одном сервере

Запрос:

    GET http://localhost:8080/servers/34c8fb2b5c07

Ответ:

    HTTP/1.1 200 OK

    {
        id: '34c8fb2b5c07'
        state: 'running',
    }

Параметры ответа: такие же как и в списке серверов.

### Получить статистику по запущенному серверу


Запрос:

    GET http://localhost:8080/servers/34c8fb2b5c07/stats

Ответ:

    HTTP/1.1 200 OK

    {
      'lfs': '0.6M',
      'status': 'online',
      'guests': '0',
      'maxguests': '38',
      'host': '^5LSN ^0EVENTS PRACTICE',
      'pass': 'secret',
      'usemaster': 'yes',
      'trackcfg': 'AU4',
      'cars': '00100000001000000000',
      'qual': '0',
      'laps': '1',
      'conn': '^0EVENTS ^3PRACTICE'
    }

### Создать сервер

Запрос:

    POST http://localhost:8080/servers
    Content-Type: application/json

    {
        port: 6050,
        version: '0.6M',
        pereulok: true,
        host: '^7LSN TEST',
        welcome: '... welcome text ...',
        track: 'AU1'
    }

Ответ:

    HTTP/1.1 201 OK

    {
        id: '34c8fb2b5c07'
    }


Параметры запроса :

Большинство параметров передаются в неизменном виде в setup.cfg

 * port - номер порта (Обязательный)
 * version - номер Версии
 * host
 * pass
 * admin
 * mode
 * usemaster
 * track
 * cars
 * maxguests
 * adminslots
 * carsmax
 * carsguest
 * pps
 * qual
 * laps
 * wind
 * vote
 * select
 * autokick
 * rstmin
 * rstend
 * midrace
 * mustpit
 * canreset
 * fcv
 * cruise
 * start
 * player
 * tracks - string[] - список возможных трас на сервере. Например:  ['AU1', 'AU1x']
 * welcome - string Строка приветсвия на сервера (Можно использовать переносы строк `\n`)
 * autosave

### Удалить сервер

Запрос:

    DELETE http://localhost:8080/servers/34c8fb2b5c07

Ответ:

    HTTP/1.1 204 No Content

### Запустить сервер

Запрос:

    POST http://localhost:8080/servers/34c8fb2b5c07/start

Ответ:

    HTTP/1.1 202 OK

### Остановить сервер

Запрос:

    POST http://localhost:8080/servers/34c8fb2b5c07/stop

Ответ:

    HTTP/1.1 202 OK

### Перезапустить сервер

Запрос:

    POST http://localhost:8080/servers/34c8fb2b5c07/restart

Ответ:

    HTTP/1.1 202 OK

## Ответ с ошибкой

Запрос:

    DELETE http://localhost:8080/servers/34c8fb2b5c07

Ответ:

    HTTP/1.1 409 Conflict
    Content-Type: application/json

    {
        error: 409,
        message: 'Server should be stopped prior to deleting'
    }


## Статусы сервера

![Жизненый цикл сервера](docs/img/state.png)


## Установка сервера на CentOS 7


### Установка docker и php

TODO: проверить и дописать установку

    # as root
    tee /etc/yum.repos.d/docker.repo <<-'EOF'
    [dockerrepo]
    name=Docker Repository
    baseurl=https://yum.dockerproject.org/repo/main/centos/7/
    enabled=1
    gpgcheck=1
    gpgkey=https://yum.dockerproject.org/gpg
    EOF

    #php stuff
    rpm -Uvh https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm
    rpm -Uvh https://mirror.webtatic.com/yum/el7/webtatic-release.rpm 
    yum install -y php56w-fpm php56w-opcache
    
    
    yum install -y docker-engine epel-release git
    yum install -y python-pip
    yum upgrade python*
    pip install docker-compose 
    pip install backports.ssl_match_hostname --upgrade
    pip install --upgrade pip
    systemctl enable docker
    systemctl start docker
    groupadd docker
    
    # Create dedicated user for api
    useradd docker-api -U -n -G docker
    
    # as your user
    sudo usermod -aG docker $USER
    sudo systemctl enable docker
    ssh-keyscan -t rsa github.com > ~/.ssh/known_hosts


### Настройка nginx и php-fpm

Путь к проекту `/opt/lfsadmin/current/public`
php-fpm слушает на порту 9001

/etc/nginx/nginx.conf:

    ...
    
    # Docker-API server
    server {
        listen 8080 default_server;
        listen [::]:8080 default_server;


        root /opt/lfsadmin/current/public;

        index index.html index.php;

        location / {
            try_files $uri /index.php;
        }

        # pass the PHP scripts to FastCGI server listening on 127.0.0.1:9000
        #
        location ~ \.php$ {
            fastcgi_pass   127.0.0.1:9001;
            fastcgi_index  index.php;
            fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
            include        fastcgi_params;
        }
    }

/php-fpm.d/lfsapi.conf:

    listen = 127.0.0.1:9001
    listen.allowed_clients = 127.0.0.1
    user = lfsadmin
    group = lfsadmin
    pm = dynamic
    pm.max_children = 50
    pm.start_servers = 5

    php_flag[display_errors] = off
    php_admin_value[error_log] = /opt/lfsadmin/logs/php-error.log
    php_admin_flag[log_errors] = on

    ; Set session path to a directory owned by process user
    php_value[session.save_handler] = files
    php_value[session.save_path]    = /opt/lfsadmin/session
    php_value[soap.wsdl_cache_dir]  = /opt/lfsadmin/wsdlcache

## Решение проблем

### Ошибка Docker-а 500

Посмотреть подробнее ошибку можно тут:

    sudo journalctl -u docker
    
### Ошибка Docker-а при удалении контейнера

Error response from daemon: Driver devicemapper failed to remove root filesystem 19ffd38c2ff6826fcaa1cb0ba626892b7de48dad0812a4457e8412f234dc0979: Device is Busy

[См. также](https://github.com/docker/docker/issues/3823)

Решение: перезапустить докер
