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
