<?php
declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Exception\QueryException;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\GetTaskListQuery;
use App\Application\Query\GetVideoListQuery;
use App\Application\QueryHandler\QueryBus;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Repository\UserRepositoryInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

#[AsCommand(name: 'app:smoke:prod', description: 'Run smoke tests to verify basic functionality after release')]
final class SmokeProdCommand extends Command
{
    private const string ADMIN_USER_ID = '123e4567-e89b-42d3-a456-426614174000';

    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LogServiceInterface $logService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>=== Production Smoke Tests Started ===</info>');
        $startTime = microtime(true);
        $tests = [];

        try {
            // Load admin user
            $adminUserId = Uuid::fromString(self::ADMIN_USER_ID);
            $adminUser = $this->userRepository->findById($adminUserId);
            if (!$adminUser) {
                $output->writeln('<error>Admin user not found</error>');
                $this->logService->log('smoke', 'prod', null, LogLevel::ERROR, 'Admin user not found');
                return Command::FAILURE;
            }

            $output->writeln(sprintf('<fg=blue>Admin user loaded: %s</>', $adminUser->email()->value()));

            // Test 1: Get video list (paginated)
            $output->writeln('<comment>Test 1: Fetching paginated video list...</comment>');
            $tests['video_list'] = $this->testVideoList($output, $adminUserId);

            // Test 2: Check video list items structure
            $output->writeln('<comment>Test 2: Checking video list items structure...</comment>');
            $tests['video_items'] = $this->testVideoItemsStructure($output, $adminUserId);

            // Test 3: Get task list
            $output->writeln('<comment>Test 3: Fetching paginated task list...</comment>');
            $tests['task_list'] = $this->testTaskList($output, $adminUserId);

            // Test 4: Check user email
            $output->writeln('<comment>Test 4: Verifying user data...</comment>');
            $tests['user_data'] = $this->testUserData($output, $adminUser);

            $duration = microtime(true) - $startTime;

            // Summary
            $passed = array_filter($tests, fn($result) => $result === true);
            $failed = count($tests) - count($passed);

            $this->logService->log('smoke', 'prod', null, LogLevel::INFO, 'Smoke tests completed', [
                'total_tests' => count($tests),
                'passed' => count($passed),
                'failed' => $failed,
                'duration' => round($duration, 3),
                'details' => $tests,
            ]);

            $output->writeln('');
            $output->writeln('<info>=== Smoke Tests Summary ===</info>');
            $output->writeln(sprintf('Total: %d tests', count($tests)));
            $output->writeln(sprintf('<fg=green>Passed: %d</>', count($passed)));
            if ($failed > 0) {
                $output->writeln(sprintf('<fg=red>Failed: %d</>', $failed));
            }
            $output->writeln(sprintf('Duration: %.3f seconds', $duration));

            return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
        } catch (\Throwable $e) {
            $this->logService->log('smoke', 'prod', null, LogLevel::ERROR, 'Smoke tests failed with exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $output->writeln(sprintf('<error>Smoke tests failed: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    private function testVideoList(OutputInterface $output, Uuid $userId): bool
    {
        try {
            $result = $this->queryBus->query(new GetVideoListQuery(new Request(['page' => '1', 'limit' => '10']), $userId));

            if (!is_array($result->items)) {
                $output->writeln('<error>  ✗ Invalid video list response: items is not an array</error>');
                return false;
            }

            $output->writeln(sprintf(
                '<fg=green>  ✓ Video list retrieved (%d items, total: %d, pages: %d)</>',
                count($result->items),
                $result->total,
                $result->totalPages,
            ));

            return true;
        } catch (QueryException $e) {
            $output->writeln(sprintf('<error>  ✗ Video list query failed: %s</error>', $e->getMessage()));
            return false;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>  ✗ Video list test failed: %s</error>', $e->getMessage()));
            return false;
        }
    }

    private function testVideoItemsStructure(OutputInterface $output, Uuid $userId): bool
    {
        try {
            $result = $this->queryBus->query(new GetVideoListQuery(new Request(['page' => '1', 'limit' => '5']), $userId));

            if (empty($result->items)) {
                $output->writeln('<fg=yellow>  ⊘ No videos to check structure</>');
                return true;
            }

            $failed = 0;
            foreach ($result->items as $item) {
                if (empty($item->uuid) || empty($item->title) || empty($item->createdAt)) {
                    $failed++;
                }
            }

            if ($failed > 0) {
                $output->writeln(sprintf('<error>  ✗ %d video items have invalid structure</error>', $failed));
                return false;
            }

            $output->writeln(sprintf(
                '<fg=green>  ✓ All %d video items have valid structure (uuid, title, createdAt)</>',
                count($result->items),
            ));

            return true;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>  ✗ Video items structure test failed: %s</error>', $e->getMessage()));
            return false;
        }
    }

    private function testTaskList(OutputInterface $output, Uuid $userId): bool
    {
        try {
            $result = $this->queryBus->query(new GetTaskListQuery(new Request(['page' => '1', 'limit' => '10']), $userId));

            if (!is_array($result->items)) {
                $output->writeln('<error>  ✗ Invalid task list response: items is not an array</error>');
                return false;
            }

            $output->writeln(sprintf(
                '<fg=green>  ✓ Task list retrieved (%d items, total: %d)</>',
                count($result->items),
                $result->total,
            ));

            return true;
        } catch (QueryException $e) {
            $output->writeln(sprintf('<error>  ✗ Task list query failed: %s</error>', $e->getMessage()));
            return false;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>  ✗ Task list test failed: %s</error>', $e->getMessage()));
            return false;
        }
    }

    private function testUserData(OutputInterface $output, \App\Domain\User\Entity\User $user): bool
    {
        try {
            $email = $user->email()->value();
            if (empty($email)) {
                $output->writeln('<error>  ✗ User email is empty</error>');
                return false;
            }

            if (!$user->hasRole('ROLE_ADMIN')) {
                $output->writeln('<error>  ✗ User is not an admin</error>');
                return false;
            }

            $output->writeln(sprintf(
                '<fg=green>  ✓ User data verified (email: %s, roles: ROLE_ADMIN)</>',
                $email,
            ));

            return true;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>  ✗ User data test failed: %s</error>', $e->getMessage()));
            return false;
        }
    }
}
