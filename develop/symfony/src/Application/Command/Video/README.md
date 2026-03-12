# Video Pipeline

## ЗАГРУЗКА

  - **Frontend**: Пользователь загружает видеофайл через веб-интерфейс (uppy+tus форма с chunk загрузкой);
  - **Presentation + Infrastucture**: UploadController через Tus\Server грузит чанки, по готовности генерит TusPhp\Events\UploadComplete (@todo выделить Infrastructure);
  - **Infrastructure Proxy**: инфраструктурное событие ловится TusPostFinishListener и проксируется в Application командой CreateVideo;
  - **Application Command Handler**: CreateVideoHandler:
    - создаёт Entity в Persistence;
    - ? THINK ?
      - тус уже положил файл куда надо
      - нет нужды его двигать?
      - или перемещу в /{User.id}/{Video.id}.{Video.ext}?
      - нет, зачем мне uid в пути?
      - я ж не буду разграничивать и давать читать исходники на фронте.
      - или буду? Чтобы не проксировать через себя.
      - но как же безопасность? Нельзя смотреть чужие видео
    - ! THINK !
      - пока что оставлю файл на тус-месте
  - ? THINK ?
  - ... 
