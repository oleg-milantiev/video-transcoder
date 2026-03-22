# TODOs for video-transcoder

## Sprint

- реалтайм события
  - realtime обновления видео (мета и постер) в списке и на странице видео
  - всплывалки по приходу (не всех) сообщений. А то не видна обнова. Добавить в DTO текст (можно и ссылку). Отображать в flash
  - стабилизация task и video с ручными тестами
  - e2e тесты адаптировать
- окончанию загрузки видео:
  - редирект на карточку видео (адаптировать e2e)
  - всплывалка "Video is ready for transcoding"
- стабилизация
  - опять по клику на название не грузится форма, нужен f5 (добавить клик на название в e2e тест)

## Backlog

### DDD
Правильное направление зависимостей Domain <- Application <- Infrastructure.
- [High] Изолировать Domain от Symfony Http/File API: StorageInterface принимает Symfony\Component\HttpFoundation\File\File в develop/symfony/src/Domain/Video/Service/Storage/StorageInterface.php:5; лучше доменный абстрактный тип (например BinaryContent/StoredObject) или порт на уровне Application.
- [Low] Уменьшить primitive obsession в User aggregate: User хранит email, roles, password как сырые примитивы в develop/symfony/src/Domain/User/Entity/User.php:8; стоит ввести VO (Email, RoleSet, возможно PasswordHash) и инварианты (минимум один роль/валидный email).
- [Low] Закрыть тестовые пробелы по DDD-рискам: нет тестов на createFromCommand/VideoCreateFailed (поиск по тестам не дал совпадений), нет тестов на ошибочный VideoStatus::value(), и мало тестов на запрещенные переходы статусов.

- Video смешивает доменную модель и storage-представление (getSrcFilename(), getPoster(), meta['duration']) — риск утечки инфраструктурной логики в Video.
- Domain зависит от Symfony-типов (Uuid*, HttpFoundation\File) — риск слабой переносимости и тестируемости в StorageInterface.
- Слабая типизация meta: array в Task/Video — риск нарушения инвариантов и неявной связи с application-слоем.

- Medium: В домене есть инфраструктурная зависимость на HTTP-слой Symfony
  StorageInterface в домене принимает Symfony\Component\HttpFoundation\File\File (develop/symfony/src/Domain/Video/Service/Storage/StorageInterface.php:5, develop/symfony/src/Domain/Video/Service/Storage/StorageInterface.php:15). Это привязывает domain model к transport/framework и ухудшает изоляцию bounded context.
  Риск: сложнее переносимость/тестируемость и чище application-порты.
- Medium: Video содержит storage-проекцию/формат пути вместо чистой бизнес-семантики
  Методы getSrcFilename() и getPoster() шьют файловые соглашения в агрегат (develop/symfony/src/Domain/Video/Entity/Video.php:112, develop/symfony/src/Domain/Video/Entity/Video.php:117). Это ближе к инфраструктуре/presentation policy, чем к core-domain.
  Риск: размывание ответственности агрегата и тяжёлый рефактор storage-стратегии.
- Medium: Потенциальный NPE-контракт в Video::getSrcFilename()
  Метод использует $this->id->toString() без проверки на null (develop/symfony/src/Domain/Video/Entity/Video.php:114), хотя id nullable (develop/symfony/src/Domain/Video/Entity/Video.php:13, develop/symfony/src/Domain/Video/Entity/Video.php:61).
  Риск: скрытая ошибка при вызове до персиста (или в тестовых/edge сценариях).
- Low: Репозиторные интерфейсы домена зависят от Symfony UUID
  В TaskRepositoryInterface/VideoRepositoryInterface используется Symfony\Component\Uid\Uuid* (develop/symfony/src/Domain/Video/Repository/TaskRepositoryInterface.php:6, develop/symfony/src/Domain/Video/Repository/VideoRepositoryInterface.php:8). Это не критично, но это framework leakage в доменные порты.
  Риск: ограничение автономности домена и vendor lock-in на уровне ubiquitous language.

### Тесты и безопасность
- ? THINK ? security нельзя складывать видео и постеры в public. Нужен механизм проксирования с auth.
- e2e
  - 04 тест (базовый transcode)
    - добавить проверку, что таск с этим пресетом один
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
- добавить лимиты воркеру - messenger:consume [-l|--limit LIMIT] [-f|--failure-limit FAILURE-LIMIT] [-m|--memory-limit MEMORY-LIMIT] [-t|--time-limit TIME-LIMIT] [--sleep SLEEP] [-b|--bus BUS] [--queues QUEUES] [--no-reset] [--all] [--exclude-receivers EXCLUDE-RECEIVERS] [--keepalive [KEEPALIVE]

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
  - как-то завязать на тариф
- запуск задач через update lock?
- шедулер в крон
- кубер и тераформ масштабирование

### Frontend
- ? THINK ? показать иконкой "?" + baloon, почему задача не кодируется прям щас
  - ? в шапку инфо о тарифе. Следующее кодирование через хх ч:м:с. Одновременно Х кодирований
- обновление токена. А то щас через час без обновлений всё посыпется. Хотя бы обновление перезагрузкой. Хоть у меня есть и не используется api_auth_token 
  
## На потом
- Watchdog зависших процессов (+ общий таймаут кодирования).
- **Хранение:** Дифференцированные лимиты на объем S3-хранилища для исходников и результат транскода.
- Ограничение по качеству и длительности, низкий приоритет в очереди RabbitMQ, лимит на 1 активную задачу.
- **Pay-per-minute / Subscription (Optional):** Доступ к тяжелым пресетам (4K, HEVC) и длинным видео, высокий приоритет обработки, увеличенное дисковое пространство, параллелизм задач транскодирования.
- добавить fake иконку доллара — ускорение очереди за ресурсы.
- скопировать ядро в laravel, постаравшись оставить как можно больше из текущего src. Это сверхзадача, сменится только presentation и infrastructure уровни;
- в тариф тип инстанца (скорость кодирования, включая gpu).
- чекбоксы массовой перекодировки (в несколько пресетов) в карточке видео
