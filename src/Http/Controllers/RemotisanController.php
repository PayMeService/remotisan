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

    public function __construct(Remotisan $rt, CommandsRepository $commandsRepo)
    {
        $this->rt = $rt;
        $this->commandsRepo = $commandsRepo;
    }

    public function index()
    {
        return view('remotisan::index');
    }

    public function commands()
    {
        return [
            "commands" => $this->commandsRepo->all()
                                             ->filter(fn(CommandData $c) => str_contains($c->getName(), "migrat"))
        ];
    }

    public function execute(Request $request)
    {
        $command    = $request->json("command");
        $definition = $request->json("definition", []);

        $this->rt->checkAuth($request);

        return [
            "id" => $this->rt->execute($command, $definition)
        ];
    }

    public function read(Request $request, $uuid)
    {
        $this->rt->checkAuth($request);

        return $this->rt->read($uuid);
    }
}
