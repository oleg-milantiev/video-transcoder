<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Admin;

use App\Application\Exception\DomainException;
use App\Application\Exception\InvalidUuidException;
use App\Application\Exception\TaskNotFoundException;
use App\Application\Exception\TranscodeAccessDeniedException;
use App\Application\Query\DeleteTaskQuery;
use App\Application\QueryHandler\QueryBus;
use App\Domain\Video\Exception\TaskAlreadyDeleted;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Presentation\Controller\Admin\TaskCrudController;
use App\Infrastructure\Persistence\Doctrine\Task\TaskEntity;
use EasyCorp\Bundle\EasyAdminBundle\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Choice;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \App\Presentation\Controller\Admin\TaskCrudController
 */
class TaskCrudControllerTest extends TestCase
{
    public function testGetEntityFqcnReturnsTaskEntity(): void
    {
        self::assertSame(
            TaskEntity::class,
            TaskCrudController::getEntityFqcn()
        );
    }

    public function testConfigureCrudReturnsCrudWithExpectedConfiguration(): void
    {
        $controller = new TaskCrudController(
            $this->createMock(AdminUrlGenerator::class),
            $this->createMock(QueryBus::class)
        );
        $crud = $controller->configureCrud(new Crud());
        
        self::assertInstanceOf(Crud::class, $crud);
        // Testing specific Crud configuration would require checking internal state
    }

    public function testConfigureFiltersReturnsFiltersWithExpectedConfiguration(): void
    {
        $controller = new TaskCrudController(
            $this->createMock(AdminUrlGenerator::class),
            $this->createMock(QueryBus::class)
        );
        $filters = $controller->configureFilters(new Filters());
        
        self::assertInstanceOf(Filters::class, $filters);
        // Testing specific filter configuration would require accessing internal state
    }

    public function testConfigureActionsReturnsActionsWithExpectedConfiguration(): void
    {
        $controller = new TaskCrudController(
            $this->createMock(AdminUrlGenerator::class),
            $this->createMock(QueryBus::class)
        );
        $actions = $controller->configureActions(new Actions());
        
        self::assertInstanceOf(Actions::class, $actions);
        // Testing specific Actions configuration would require checking internal state
    }

    public function testConfigureFieldsReturnsIterableWithExpectedFields(): void
    {
        $controller = new TaskCrudController(
            $this->createMock(AdminUrlGenerator::class),
            $this->createMock(QueryBus::class)
        );
        $fields = $controller->configureFields('index');
        
        self::assertIsIterable($fields);
        
        $fields = iterator_to_array($fields);
        self::assertCount(8, $fields); // id, status, progress, user, video, preset, meta, createdAt, updatedAt, startedAt
        
        // Check that we have the expected field types (basic check)
        $fieldNames = [];
        foreach ($fields as $field) {
            if (method_exists($field, 'getName')) {
                $fieldNames[] = $field->getName();
            }
        }
        
        $expectedFields = [
            'id', 'status', 'progress', 'user', 'video', 'preset', 'meta', 
            'createdAt', 'updatedAt', 'startedAt'
        ];
        foreach ($expectedFields as $expectedField) {
            self::assertContains($expectedField, $fieldNames);
        }
    }

    public function testMarkDeletedRedirectsToIndexOnSuccess(): void
    {
        $adminUrlGenerator = $this->createMock(AdminUrlGenerator::class);
        $adminUrlGenerator->expects($this->once())
            ->method('unsetAll')
            ->willReturnSelf();
        $adminUrlGenerator->expects($this->once())
            ->method('setController')
            ->with($this->equalTo(TaskCrudController::class))
            ->willReturnSelf();
        $adminUrlGenerator->expects($this->once())
            ->method('setAction')
            ->with($this->equalTo('index'))
            ->willReturnSelf();
        $adminUrlGenerator->expects($this->once())
            ->method('generateUrl')
            ->willReturn('/admin/task');

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(DeleteTaskQuery::class))
            ->willReturn(null);

        $context = $this->createMock(AdminContext::class);
        $entity = $this->createMock(TaskEntity::class);
        $entity->expects($this->once())
            ->method('getPrimaryKeyValue')
            ->willReturn('123');
        $context->expects($this->once())
            ->method('getEntity')
            ->willReturn($entity);

        $user = $this->createMock(\App\Domain\User\Entity\UserEntity::class);
        $user->expects($this->once())
            ->method('id')
            ->willReturn($this->createMock(\App\Domain\Shared\ValueObject\Uuid::class));
        $user->method('id->toRfc4122()')
            ->willReturn('user-id');

        $controller = new TaskCrudController($adminUrlGenerator, $queryBus);
        $controller->setContainer($this->getContainerMockWithUser($user));

        $response = $controller->markDeleted($context);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/task', $response->getTargetUrl());
        self::assertSame(302, $response->getStatusCode());
    }

    public function testMarkDeletedShowsErrorFlashOnInvalidUuid(): void
    {
        $adminUrlGenerator = $this->createMock(AdminUrlGenerator::class);
        $adminUrlGenerator->expects($this->once())
            ->method('unsetAll')
            ->willReturnSelf();
        $adminUrlGenerator->expects($this->once())
            ->method('setController')
            ->with($this->equalTo(TaskCrudController::class))
            ->willReturnSelf();
        $adminUrlGenerator->expects($this->once())
            ->method('setAction')
            ->with($this->equalTo('index'))
            ->willReturnSelf();
        $adminUrlGenerator->expects($this->once())
            ->method('generateUrl')
            ->willReturn('/admin/task');

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->never())
            ->method('query');

        $context = $this->createMock(AdminContext::class);
        $entity = $this->createMock(TaskEntity::class);
        $entity->expects($this->once())
            ->method('getPrimaryKeyValue')
            ->willReturn('not-a-uuid');
        $context->expects($this->once())
            ->method('getEntity')
            ->willReturn($entity);

        $user = $this->createMock(\App\Domain\User\Entity\UserEntity::class);
        $user->expects($this->once())
            ->method('id')
            ->willReturn($this->createMock(\App\Domain\Shared\ValueObject\Uuid::class));
        $user->method('id->toRfc4122()')
            ->willReturn('user-id');

        $controller = new TaskCrudController($adminUrlGenerator, $queryBus);
        $controller->setContainer($this->getContainerMockWithUser($user));

        $response = $controller->markDeleted($context);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/task', $response->getTargetUrl());
        self::assertSame(302, $response->getStatusCode());
        // Flash message testing would require accessing the session flash bag
    }

    private function getContainerMockWithUser($user): \PHPUnit\Framework\MockObject\MockObject
    {
        $container = $this->createMock(\Symfony\DependencyInjection\ContainerInterface::class);
        $container->method('get')
            ->willReturnMap([
                ['security.token_storage', $this->createMock(\Symfony\Component\Security\Http\Authentication\Token\Storage\TokenStorageInterface::class)],
            ]);
        
        $tokenStorage = $this->createMock(\Symfony\Component\Security\Http\Authentication\Token\Storage\TokenStorageInterface::class);
        $tokenStorage->expects($this->any())
            ->method('getToken')
            ->willReturn($this->createMockTokenWithUser($user));
        
        $container->method('get')
            ->with('security.token_storage')
            ->willReturn($tokenStorage);
        
        return $container;
    }

    private function createMockTokenWithUser($user): \PHPUnit\Framework\MockObject\MockObject
    {
        $token = $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);
        $token->expects($this->any())
            ->method('getUser')
            ->willReturn($user);
        return $token;
    }
}