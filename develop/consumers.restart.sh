#!/bin/sh

docker exec -it develop-php-1 php bin/console c:c
docker compose exec ffmpeg bin/console messenger:stop-workers

#docker compose exec -it ffmpeg kill -1 1
#for id in $(docker compose ps -q ffmpeg-transcode); do
#    docker exec -it $id kill -1 1
#done
