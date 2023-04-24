<?php

namespace PayMe\Remotisan\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PayMe\Remotisan\CommandsRepository;
use PayMe\Remotisan\Exceptions\ProcessFailedException;
use PayMe\Remotisan\Exceptions\RemotisanException;
use PayMe\Remotisan\FileManager;
use PayMe\Remotisan\Models\Execution;
use PayMe\Remotisan\Remotisan;

class RemotisanController extends Controller {

    protected Remotisan $rt;
    protected CommandsRepository $commandsRepo;

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
        $this->rt->requireAuthenticated();

        return view('remotisan::index');
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function commands(Request $request): array
    {
        $this->rt->requireAuthenticated();

        return [
            "commands" => $this->commandsRepo->allByRole($this->rt->getUserGroup())
        ];
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function execute(Request $request): array
    {
        $this->rt->requireAuthenticated();

        $command = $request->json("command");
        $params  = $request->json("params");

        return [
            "id" => $this->rt->execute($command, $params)
        ];
    }

    /**
     * Kill process endpoint. If PID returned, then process killed.
     * @param Request   $request
     * @param string    $uuid
     * @return array
     */
    public function sendKillSignal(Request $request, string $uuid)
    {
        $code = null;
        try {
            $this->rt->sendKillSignal($uuid);
        } catch (RemotisanException $e) {
            $uuid = null;
            $code = $e->getCode();
        }

        return response()->json(["uuid" => $uuid], ($uuid ? 200 : ($code ?? 500)));
    }

    /**
     * @param Request $request
     * @return Collection
     */
    public function history(Request $request): Collection
    {
        return Execution::query()
            //->where("user_identifier", $this->rt->getUserIdentifier()) // commented out by request to unscope history
            ->orderByDesc("executed_at")
            ->limit(config("remotisan.show_history_records_num"))
            ->get();
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
        $this->rt->requireAuthenticated();

        return FileManager::read($uuid);
    }
}
