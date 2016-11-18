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
 * state - Состояние сервера. Возможны следующие значения см. [#Состояния сервера]
    * `running` - запущен
    * `restarting` - перезагружается
    * `created` - остановлен

### Получить информацию об одном сервере

Запрос:

    GET http://example.org/servers

Ответ:

    HTTP/1.1 200 OK

    {
        id: '34c8fb2b5c07'
        state: 'running',
    }

Параметры ответа: такие же как и в списке серверов.

### Создание сервера

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


Параметры запроса:

 * port - номер порта (Обязательный)
 * image - название версии LFS
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
 * welcome
 * autosave

### Удаление сервера

Запрос:

    DELETE http://localhost:8080/servers/34c8fb2b5c07

Ответ:

    HTTP/1.1 204 No Content

### Запуск/остановка сервера

Запрос:

    PATCH http://localhost:8080/servers/34c8fb2b5c07
    Content-Type: application/json

    {
        state: 'running'
    }

Ответ:

    HTTP/1.1 200 OK


### Рестарт сервера

Запрос:

    POST http://localhost:8080/servers/34c8fb2b5c07/restart

Ответ:

    HTTP/1.1 200 OK

### Получение списка версий сервера

Запрос:

    GET http://localhost:8080/server-images

Ответ:

    HTTP/1.1 200 OK
    [
        {
            "id":"0.6M",
        },
        {
            "id":"0.6Q",
        }
    ]

