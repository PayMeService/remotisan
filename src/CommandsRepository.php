<?php

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
            ->map(fn(Command $command) => new CommandData(
                $command->getName(),
                $command->getDefinition(),
                $command->getHelp(),
                $command->getDescription()
            ));
    }

    public function allByRole($role): Collection
    {
        return $this->all()->filter(fn(CommandData $command) => $command->canExecute($role));
    }

    public function find(string $name): ?CommandData
    {
        return $this->all()->first(fn(CommandData $cd) => $cd->getName() == $name);
    }
}
