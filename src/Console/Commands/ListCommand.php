<?php

namespace Reno\Console\Commands;

use Reno\Console\Command;
use Reno\Console\Input;
use Reno\Console\Output;

/**
 * ListCommand
 * 
 * Lists all available commands.
 */
class ListCommand extends Command
{
    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('list')
            ->setDescription('List all available commands')
            ->setAliases(['commands']);
    }

    /**
     * Execute the command
     *
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output): int
    {
        $app = $this->getApplication();
        
        $output->writeln('');
        $output->writeln(sprintf('<info>%s</info> version <comment>%s</comment>', 
            $app->getName(), 
            $app->getVersion()
        ));
        $output->writeln('');
        
        $output->writeln('<comment>Usage:</comment>');
        $output->writeln('  command [options] [arguments]');
        $output->writeln('');
        
        $output->writeln('<comment>Available commands:</comment>');
        
        $commands = $app->all();
        
        $maxLength = max(array_map('strlen', array_keys($commands)));
        
        foreach ($commands as $name => $command) {
            $output->writeln(sprintf(
                '  <info>%-' . $maxLength . 's</info>  %s',
                $name,
                $command->getDescription()
            ));
        }
        
        $output->writeln('');
        
        return 0;
    }
}
