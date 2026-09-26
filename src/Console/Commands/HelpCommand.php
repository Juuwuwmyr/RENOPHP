<?php

namespace Reno\Console\Commands;

use Reno\Console\Command;
use Reno\Console\Input;
use Reno\Console\Output;

/**
 * HelpCommand
 * 
 * Displays help for a command.
 */
class HelpCommand extends Command
{
    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('help')
            ->setDescription('Display help for a command')
            ->addArgument('command_name', false, 'The command name');
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
        $commandName = $input->getArgument(1);
        
        if (!$commandName) {
            // Show general help
            return $this->getApplication()->find('list')->run($input, $output);
        }
        
        $command = $this->getApplication()->find($commandName);
        
        $output->writeln('');
        $output->writeln(sprintf('<info>%s</info>', $command->getName()));
        $output->writeln('');
        
        if ($description = $command->getDescription()) {
            $output->writeln($description);
            $output->writeln('');
        }
        
        // Show arguments
        $arguments = $command->getArguments();
        if (!empty($arguments)) {
            $output->writeln('<comment>Arguments:</comment>');
            foreach ($arguments as $name => $config) {
                $required = $config['required'] ? '<error>REQUIRED</error>' : '<info>OPTIONAL</info>';
                $output->writeln(sprintf(
                    '  <info>%s</info>  %s  %s',
                    $name,
                    $required,
                    $config['description']
                ));
            }
            $output->writeln('');
        }
        
        // Show options
        $options = $command->getOptions();
        if (!empty($options)) {
            $output->writeln('<comment>Options:</comment>');
            foreach ($options as $name => $config) {
                $shortcut = $config['shortcut'] ? sprintf('-%s, ', $config['shortcut']) : '    ';
                $output->writeln(sprintf(
                    '  %s<info>--%s</info>  %s',
                    $shortcut,
                    $name,
                    $config['description']
                ));
            }
            $output->writeln('');
        }
        
        return 0;
    }
}
