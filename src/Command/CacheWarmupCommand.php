<?php

namespace App\Command;

use App\Service\CacheService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
  name: 'app:cache:warmup-leads',
  description: 'Warm up lead-related caches for better performance'
)]
class CacheWarmupCommand extends Command
{
  public function __construct(
    private readonly CacheService $cacheService)
  {
    parent::__construct();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);

    $io->title('Lead Cache Warmup');
    $io->text('Starting cache warmup process...');

    try {
      // Clear existing caches first
      $io->section('Clearing existing caches...');
      $this->cacheService->clearLeadCaches();
      $io->success('Existing caches cleared');

      // Warm up basic cache entries
      $io->section('Warming up basic queries...');
      $this->cacheService->warmUpCache();
      $io->success('Basic queries cached');

      // Preload common queries
      $io->section('Preloading common queries...');
      $this->cacheService->preloadCommonQueries();
      $io->success('Common queries preloaded');

      $io->success('Cache warmup completed successfully!');
      $io->note('The application should now have better response times for lead queries.');

      return Command::SUCCESS;
    } catch (\Exception $e) {
      $io->error('Failed to warm up cache: ' . $e->getMessage());
      return Command::FAILURE;
    }
  }
}