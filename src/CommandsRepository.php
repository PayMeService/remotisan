<?php
/**
 * Created by PhpStorm.
 * User: omer
 * Date: 05/11/2022
 * Time: 13:06
 */

namespace PayMe\Remotisan;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

class CommandsRepository
{
    /**
     * @return Collection
     */
    public function all(): Collection
    {
        return collect(Artisan::all())
            ->filter(fn (Command $command) => $this->canExecute($command))
            ->map(fn(Command $command) => new CommandData(
                $command->getName(),
                $command->getDefinition(),
                $command->getHelp(),
                $command->getDescription()
            ));
    }

    public function find(string $name): ?CommandData
    {
        return $this->all()->first(fn(CommandData $cd) => $cd->getName() == $name);
    }

    private function canExecute(Command $command)
    {
        return true;
    }
}
