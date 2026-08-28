<?php

declare(strict_types=1);

namespace Game\World\Interface\Console;

use Game\World\Application\WorldGenerationService;
use Game\World\Infrastructure\World;
use Illuminate\Console\Command;

final class GenerateWorldCommand extends Command
{
    protected $signature = 'game:generate-world {world : World code or ULID}';

    protected $description = 'Generate the deterministic regions and tiles for a world';

    public function handle(WorldGenerationService $generation): int
    {
        $argument = $this->argument('world');
        $identifier = is_string($argument) ? $argument : '';
        $world = World::query()
            ->where('code', $identifier)
            ->orWhere('id', $identifier)
            ->first();

        if ($world === null) {
            $this->error('World not found: '.$identifier);

            return self::FAILURE;
        }

        $result = $generation->generate($world);
        $verb = $result['generated'] ? 'Generated' : 'Already generated';
        $this->info(sprintf(
            '%s world %s: %d regions, %d tiles (seed %s).',
            $verb,
            $world->code,
            $result['regions'],
            $result['tiles'],
            $result['seed'],
        ));

        return self::SUCCESS;
    }
}
