# TODOs for video-transcoder

## Основные задачи

### DDD
Правильное направление зависимостей Domain <- Application <- Infrastructure.
- [High] Изолировать Domain от Symfony Http/File API: StorageInterface принимает Symfony\Component\HttpFoundation\File\File в develop/symfony/src/Domain/Video/Service/Storage/StorageInterface.php:5; лучше доменный абстрактный тип (например BinaryContent/StoredObject) или порт на уровне Application.
- [Medium] Убрать persistence-утечки из сущностей: Task имеет публичный конструктор “for Doctrine only” (develop/symfony/src/Domain/Video/Entity/Task.php:21) и setId() (develop/symfony/src/Domain/Video/Entity/Task.php:158), Video генерирует id внутри сущности (develop/symfony/src/Domain/Video/Entity/Video.php:62); лучше единый паттерн создания агрегата + assignment id на границе репозитория.
- [Medium] Усилить инварианты переходов Task внутри агрегата: сейчас start() не использует проверку длительности (canStart() отдельно в develop/symfony/src/Domain/Video/Entity/Task.php:48 и develop/symfony/src/Domain/Video/Entity/Task.php:57), а updateProgress() разрешен не только для PROCESSING (develop/symfony/src/Domain/Video/Entity/Task.php:78); часть бизнес-правил может обходиться.
- [Medium] Убрать инфраструктурные/технические операции из доменных репозиториев: log() в develop/symfony/src/Domain/Video/Repository/VideoRepositoryInterface.php:14, develop/symfony/src/Domain/Video/Repository/TaskRepositoryInterface.php:15, develop/symfony/src/Domain/User/Repository/UserRepositoryInterface.php:11 — это cross-cutting concern, лучше отдельный порт/сервис.
- Создай новый doctrine entity Log, куда я буду складывать логи вместо entity.log поля.
  Я вижу поля: uuid id, enum entity, uuid objectId, enum level, string text, datetime createdAt.

Создай таблицу и удали поля log миграцией.

- [Low] Уменьшить primitive obsession в User aggregate: User хранит email, roles, password как сырые примитивы в develop/symfony/src/Domain/User/Entity/User.php:8; стоит ввести VO (Email, RoleSet, возможно PasswordHash) и инварианты (минимум один роль/валидный email).
- [Low] Закрыть тестовые пробелы по DDD-рискам: нет тестов на createFromCommand/VideoCreateFailed (поиск по тестам не дал совпадений), нет тестов на ошибочный VideoStatus::value(), и мало тестов на запрещенные переходы статусов.

### Тесты и безопасность
- ? THINK ? security нельзя складывать видео и постеры в public. Нужен механизм проксирования с auth.
- e2e
  - 04 тест (базовый transcode)
    - добавить проверку, что таск с этим пресетом один
  - 05 тест (отмена и продолжение)
    - загрузка длинного видео
    - транскодирование
    - отмена
    - повторный запуск
    - успешное скачивание
    - (? переиспользовать из 04)
    - ? THINK ? какие-то сложные последовательности отмен, fail, success
  - 06 тест (тарифы)
    - попытка запустить транскод в пресет 2
    - шедулер не даёт запустить второй транскод за час
    - в аду смена тарифа на премиум
    - отмена ожидания, транскод опять
    - успешный транскод и скачивание результата
  - 07 параллелизм
    - закачать новое видео
    - запустить транскод двух пресетов
    - следить за готовностью двух пресетов
    - скачать два готовых файла

### События, расширяемость
- add events for transcoding

### Видео и метаданные
- вертикальные видео и пропорции. Подходящие пресеты.

### Видео пайплайн
- tus выделить в Infrastructure;
- как-то pipeline надо в одной сущности делать, кажется, описывая порядок классов. Через yield?
- ? THINK ? а где у меня будет удаление видео?
  - не забыть удалить постеры и пресет-видео при удалении видео;
  - удалить mp4 по отмене задачи транскодера

### Шедулер и transcode, облака
- щас можно бесконечно клацать transcode -> cancel -> transcode -> cancel -> ...
- запуск задач через update lock?
- шедулер в крон
- кубер и тераформ масштабирование

### Frontend
- автообновление статуса и кнопок
  
## На потом
- перекинуть symfony messenger на redis?, отказавшись от rabbit
- Watchdog зависших процессов.
- **Хранение:** Дифференцированные лимиты на объем S3-хранилища для исходников и результат транскода.
- Ограничение по качеству и длительности, низкий приоритет в очереди RabbitMQ, лимит на 1 активную задачу.
- **Pay-per-minute / Subscription (Optional):** Доступ к тяжелым пресетам (4K, HEVC) и длинным видео, высокий приоритет обработки, увеличенное дисковое пространство, параллелизм задач транскодирования.
- добавить fake иконку доллара — ускорение очереди за ресурсы.
- скопировать ядро в laravel, постаравшись оставить как можно больше из текущего src. Это сверхзадача, сменится только presentation и infrastructure уровни;
- в тариф тип инстанца (скорость кодирования, включая gpu).
- чекбоксы массовой перекодировки (в несколько пресетов) в карточке видео
