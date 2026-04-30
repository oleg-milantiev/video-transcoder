<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Exception\InvalidUuidException;
use App\Application\Exception\TaskDownloadAccessDeniedException;
use App\Application\Exception\TaskNotFoundException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\TaskDownloadQuery;
use App\Application\QueryHandler\QueryBus;
use App\Domain\Shared\ValueObject\Uuid;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class TaskController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly LogServiceInterface $logService,
    ) {
    }

    /**
     * Generates a signed redirect URL for downloading a completed transcode task output.
     * Resolves the task and video, verifies download access via the voter,
     * and redirects to the time-limited S3/storage URL.
     * todo и правда, вынести storage из public и давать TTL симлинк на скачивание
     *
     * @throws BadRequestHttpException  When the task ID is not a valid UUID.
     * @throws NotFoundHttpException    When the task or its parent video is not found.
     * @throws AccessDeniedHttpException When the user does not own the task.
     * @throws HttpException            On unexpected internal errors (HTTP 500).
     */
    #[Route('/task/{id}/download', name: 'task_download', requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function download(string $id): Response
    {
        try {
            return $this->redirect(
                $this->queryBus->query(
                    new TaskDownloadQuery($id, $this->getUser()->id->toRfc4122())
                )
            );
        } catch (InvalidUuidException) {
            throw new BadRequestHttpException('INVALID_TASK_ID');
        } catch (TaskNotFoundException) {
            throw new NotFoundHttpException('TASK_NOT_FOUND');
        } catch (VideoNotFoundException) {
            throw new NotFoundHttpException('VIDEO_NOT_FOUND');
        } catch (TaskDownloadAccessDeniedException) {
            throw new AccessDeniedHttpException('ACCESS_DENIED');
        } catch (Throwable $e) {
            $this->logService->log('task', 'download', Uuid::fromStringNullable($id), LogLevel::CRITICAL, 'Fail', [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);

            throw new HttpException(500, 'INTERNAL_ERROR');
        }
    }
}
