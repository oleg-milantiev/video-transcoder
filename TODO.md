# TODOs for video-transcoder

## Основные задачи

### DDD
- домен User улучшить (VO)

### Тесты и безопасность
- ? THINK ? security нельзя складывать видео и постеры в public. Нужен механизм проксирования с auth.
- тесты App\Application\CommandHandler

### События, расширяемость
- split command and event message buses (CreateVideoHandler, CreateVideoPreviewHandler, ExtractVideoMetadataHandler)
- add events for transcoding

### Видео и метаданные
- добавить возможность отмены pending и transcoding
- вертикальные видео и пропорции. Подходящие пресеты.

### Видео пайплайн (из Application/Command/Video/README.md)
- tus выделить в Infrastructure;
- как-то pipeline надо в одной сущности делать, кажется, описывая порядок классов. Через yield?
- ? THINK ? а где у меня будет удаление видео?
  - не забыть удалить постеры и пресет-видео при удалении видео;

### Шедулер и transcode, облака
- запуск задач через update lock?
- шедулер в крон
- заполнене task.meta
- кубер и тераформ масштабирование

### Frontend
- первый вход не показывает список видео / задач
- переход на Vue в SPO
  - перевод video/details в JSON

## На потом
- разделить TranscodeVideoHandler и обернуть тестами
- добавить fake иконку доллара — ускорение очереди за ресурсы.
- перенести ядро в laravel, постаравшись оставить как можно больше из текущего src. Это сверхзадача, сменится только presentation и infrastructure уровни;
- в тариф тип инстанца (скорость кодирования, включая gpu).
- чекбоксы массовой перекодировки (в несколько пресетов) в карточке видео
