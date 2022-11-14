<?php
namespace PayMe\Remotisan\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PayMe\Remotisan\CommandData;
use PayMe\Remotisan\CommandsRepository;
use PayMe\Remotisan\Remotisan;

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

    public function commands(Request $request)
    {
        $this->rt->checkAuth();

        return [
            "commands" => $this->commandsRepo->allByRole($this->rt->getUserGroup())
                ->filter(fn(CommandData $c) => str_contains($c->getName(), "migrat"))
        ];
    }

    public function execute(Request $request)
    {
        $command    = $request->json("command");
        $definition = $request->json("definition", []);

        return [
            "id" => $this->rt->execute($command, $definition)
        ];
    }

    public function read(Request $request, $uuid)
    {
        $this->rt->checkAuth();

        return $this->rt->read($uuid);
    }
}
