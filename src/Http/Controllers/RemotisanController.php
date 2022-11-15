<?php
/**
 * Created by PhpStorm.
 * User: omer
 * Date: 04/11/2022
 * Time: 21:10
 */

namespace PayMe\Remotisan\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PayMe\Remotisan\CommandData;
use PayMe\Remotisan\CommandsRepository;
use PayMe\Remotisan\Remotisan;
use Symfony\Component\Console\Command\Command;

class RemotisanController extends Controller {

    protected Remotisan $rt;
    protected CommandsRepository $commandsRepo;

    const PARAM_COMMAND         = "command";
    const PARAM_COMMAND_ARGS    = "command_arguments";
    const PARAM_DEFINITION      = "definition";

    /**
     * @param Remotisan          $rt
     * @param CommandsRepository $commandsRepo
     */
    public function __construct(Remotisan $rt, CommandsRepository $commandsRepo)
    {
        $this->rt = $rt;
        $this->commandsRepo = $commandsRepo;
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index(): \Illuminate\Contracts\View\View
    {
        return view('remotisan::index');
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function commands(Request $request): array
    {
        $this->rt->checkAuth();

        return [
            "commands" => $this->commandsRepo->allByRole($this->rt->getUserGroup())
                ->filter(fn(CommandData $c) => str_contains($c->getName(), "migrat"))
        ];
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function execute(Request $request): array
    {
        $command    = $request->json(static::PARAM_COMMAND);
        $arguments  = $request->json(static::PARAM_COMMAND_ARGS);
        $definition = $request->json(static::PARAM_DEFINITION, []);

        return [
            "id" => $this->rt->execute($command, $arguments, $definition)
        ];
    }

    /**
     * @param Request $request
     * @param         $uuid
     *
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function read(Request $request, $uuid): array
    {
        $this->rt->checkAuth();

        return $this->rt->read($uuid);
    }
}
