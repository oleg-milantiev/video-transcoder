# Video Pipeline

## ЗАГРУЗКА

TODO обновить

  - **Frontend**: Пользователь загружает видеофайл через веб-интерфейс (uppy+tus форма с chunk загрузкой);
  - **Presentation + Infrastucture**: UploadController через Tus\Server грузит чанки, по готовности генерит TusPhp\Events\UploadComplete;
  - **Infrastructure Proxy**: инфраструктурное событие ловится TusPostFinishListener и проксируется в Application командой CreateVideo;
  - **Application Command Handler**: CreateVideoHandler:
    - создаёт Entity в Persistence;
     - продолжает pipeline через отправку ExtractVideoMetadataCommand;
  - **Application Command Handler**: ExtractVideoMetadataHandler
    - зная src, натравливает на него ffmpeg
    - читает результаты, складывает в Persistence:Video.meta
  - **Application Command Handler**: CreateVideoThumbnailHandler
    - зная src, натравливает на него ffmpeg 
    - *да, можно было объединить, но мне интересно было организовать pipeline. Больше для самообучения.*
    - постер, размером с кадр, в jpg формате, кладу в папку постеров;
    - в Persistence:Video.meta кладу данные о наличии постера.
    - в Video добавляю метод получения постера|null 
 
Всё, видео есть. У него есть мета и превью.
// Security: нельзя складывать постеры в public. Нужен механизм проксирования с auth.
