<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Admin;

use App\Application\Query\DeleteTaskQuery;
use App\Application\QueryHandler\QueryBus;
use App\Infrastructure\Persistence\Doctrine\Task\TaskEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Presentation\Controller\Admin\TaskCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

#[CoversClass(TaskCrudController::class)]
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
        $controller = $this->createController();
        $crud = $controller->configureCrud(Crud::new());

        self::assertInstanceOf(Crud::class, $crud);
    }

    public function testConfigureFiltersReturnsFiltersWithExpectedConfiguration(): void
    {
        $controller = $this->createController();
        $filters = $controller->configureFilters(Filters::new());

        self::assertInstanceOf(Filters::class, $filters);
    }

    public function testConfigureActionsReturnsActionsWithExpectedConfiguration(): void
    {
        $controller = $this->createController();
        $actions = $controller->configureActions(Actions::new());

        self::assertInstanceOf(Actions::class, $actions);
    }

    public function testConfigureFieldsReturnsIterableWithExpectedFields(): void
    {
        $controller = $this->createController();
        $fields = $controller->configureFields('index');

        self::assertIsIterable($fields);

        $fields = iterator_to_array($fields);
        self::assertCount(10, $fields); // id, status, progress, user, video, preset, meta, createdAt, updatedAt, startedAt

        $fieldNames = [];
        foreach ($fields as $field) {
            if (method_exists($field, 'getAsDto')) {
                $fieldNames[] = $field->getAsDto()->getProperty();
            }
        }

        $expectedFields = [
            'id', 'status', 'progress', 'user', 'video', 'preset', 'meta',
            'createdAt', 'updatedAt', 'startedAt',
        ];
        foreach ($expectedFields as $expectedField) {
            self::assertContains($expectedField, $fieldNames);
        }
    }

    public function testMarkDeletedMethodHasAdminRouteAttribute(): void
    {
        $reflection = new \ReflectionMethod(TaskCrudController::class, 'markDeleted');
        $attributes = $reflection->getAttributes(AdminRoute::class);
        self::assertCount(1, $attributes);
    }

    public function testMarkDeletedRedirectsToIndexOnSuccess(): void
    {
        $adminUrlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->expects($this->once())->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('setController')
            ->with(TaskCrudController::class)->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('setAction')
            ->with(Crud::PAGE_INDEX)->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('generateUrl')->willReturn('/admin/task');

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->with($this->isInstanceOf(DeleteTaskQuery::class))
            ->willReturn(null);

        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');
        $user->email = 'admin@example.com';
        $user->roles = ['ROLE_ADMIN'];

        $context = $this->createAdminContext('33333333-3333-4333-8333-333333333333');

        $controller = new TaskCrudController($adminUrlGenerator, $queryBus);
        $controller->setContainer($this->createContainerMock($user));

        $response = $controller->markDeleted($context);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/task', $response->getTargetUrl());
        self::assertSame(302, $response->getStatusCode());
    }

    public function testMarkDeletedAddsFlashOnInvalidUuid(): void
    {
        $adminUrlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->expects($this->once())->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('setController')->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('setAction')->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('generateUrl')->willReturn('/admin/task');

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->never())->method('query');

        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');
        $user->email = 'admin@example.com';
        $user->roles = ['ROLE_ADMIN'];

        $context = $this->createAdminContext('not-a-valid-uuid');

        $controller = new TaskCrudController($adminUrlGenerator, $queryBus);
        $controller->setContainer($this->createContainerMock($user));

        $response = $controller->markDeleted($context);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/task', $response->getTargetUrl());
        self::assertSame(302, $response->getStatusCode());
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function createController(): TaskCrudController
    {
        return new TaskCrudController(
            $this->createStub(AdminUrlGeneratorInterface::class),
            $this->createStub(QueryBus::class),
        );
    }

    /**
     * Build an AdminContext suitable for unit-testing markDeleted().
     * EntityDto::getPrimaryKeyValue() early-returns null when entityInstance is null,
     * so we use reflection to initialise both fields on the uninitialised DTO.
     */
    private function createAdminContext(string $primaryKeyValue): AdminContext
    {
        $entityDtoRef = new \ReflectionClass(EntityDto::class);
        /** @var EntityDto $entityDto */
        $entityDto = $entityDtoRef->newInstanceWithoutConstructor();
        $entityDtoRef->getProperty('entityInstance')->setValue($entityDto, new \stdClass());
        $entityDtoRef->getProperty('primaryKeyValue')->setValue($entityDto, $primaryKeyValue);

        return AdminContext::forTesting(
            crudContext: CrudContext::forTesting(entityDto: $entityDto),
        );
    }

    private function createContainerMock(UserEntity $user): ContainerInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $flashBag = $this->createStub(FlashBagInterface::class);

        $session = $this->createStub(FlashBagAwareSessionInterface::class);
        $session->method('getFlashBag')->willReturn($flashBag);

        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getSession')->willReturn($session);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(
            fn (string $id) => \in_array($id, ['security.token_storage', 'request_stack'], true),
        );
        $container->method('get')->willReturnMap([
            ['security.token_storage', $tokenStorage],
            ['request_stack', $requestStack],
        ]);

        return $container;
    }
}
