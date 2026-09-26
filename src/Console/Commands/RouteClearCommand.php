<?php

declare(strict_types=1);

namespace Reno\Console\Commands;

use Reno\Console\Command;
use Reno\Routing\RouteCache;

class RouteClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected string $signature = 'route:clear';

    /**
     * The console command description.
     */
    protected string $description = 'Remove the route cache file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cache = $this->getRouteCache();

        if ($cache->exists()) {
            $cache->clear();
            $this->info('Route cache cleared!');
        } else {
            $this->info('No route cache file found.');
        }

        return 0;
    }

    /**
     * Get the route cache instance.
     */
    protected function getRouteCache(): RouteCache
    {
        return new RouteCache(
            app()->getCachedRoutesPath()
        );
    }
}