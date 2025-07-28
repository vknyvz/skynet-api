<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
  name: 'app:messenger:worker',
  description: 'Start Symfony Messenger worker for async lead processing'
)]
class MessengerWorkerCommand extends Command
{
  protected function configure(): void
  {
    $this
      ->addOption('memory-limit', 'm', InputOption::VALUE_OPTIONAL, 'Memory limit (e.g., 128M)', '256M')
      ->addOption('transport', null, InputOption::VALUE_OPTIONAL, 'Transport to consume', 'rabbitmq_leads rabbitmq_logs');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);

    $memoryLimit = $input->getOption('memory-limit');
    $transport = $input->getOption('transport');

    $io->title('Lead Processing Worker');
    $io->info('Starting Symfony Messenger worker for async lead processing...');

    // Split transport string into array if multiple transports provided
    $transports = explode(' ', $transport);

    $command = [
      'php',
      'bin/console',
      'messenger:consume',
      ...$transports,
      '--memory-limit=' . $memoryLimit,
      '-vv'
    ];

    $io->note([
      'Worker Configuration:',
      '- Transport: ' . $transport,
      '- Memory limit: ' . $memoryLimit,
      '',
      'Press Ctrl+C to stop the worker gracefully'
    ]);

    $process = new Process($command);
    $process->setTimeout(null);

    try {
      $process->run(function ($type, $buffer) use ($output) {
        $output->write($buffer);
      });

      $exitCode = $process->getExitCode();

      if ($exitCode === 0) {
        $io->success('Worker completed successfully');
      } else {
        $io->error('Worker exited with code: ' . $exitCode);
      }

      return $exitCode;

    } catch (\Exception $e) {
      $io->error('Failed to start worker: ' . $e->getMessage());
      return Command::FAILURE;
    }
  }
}