# Мои запросы

## Backend 

- [Новые поля в домен](symfony/new-domain-fields.md)
- [Новая консольная команда](symfony/create-new-command.md)
- [Новый storage realtime](symfony/new-realtime-storage.md)

## Frontend

- [В закладку Upload добавить текст о загружаемом файле](symfony/add-text-from-config.md)
- [В карточку видео добавь иконку ? рядом с Pending задачами](symfony/add-pending-tasks-info.md)
- [Новая страница тарифов](symfony/new-tariffs-page.md)
- [Дизайн страницы профиля](symfony/design-profile-page.md)
- [Новая колонка в карточке видео](symfony/new-resolution-column.md)


В frontend, на странице карточки видео серьёзные изменения.

Делаем только frontend, не обращай внимание на наполнение из Backend, его буду полностью переделывать под страницу.

Блок presets переименовать в New Task. Уходим от табличного вывода.

Под ним блок Transcoding tasks. Это таблица с уже запущенными ранее по этому видео задачами


Меняю API вызов страницы карточки видео. 
Вход такой же - это uuid видео.
Начало GetVideoDetailsHandler такое же - поиск видео и проверка прав на его просмотр.
Дальше изменения. VideoDetailsDTO теперь содержит:
- данные видео (VideoItemDTO)
- список пресетов (PresetItemDTO[])
- список задач (TaskItemDTO[])
В handler данные получаются из репо (могут понадобиться новые вызовы в репо и интерфейсах).
Передаются в VideoDetailsDTO::create.