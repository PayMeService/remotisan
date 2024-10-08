<?php

namespace PayMe\Remotisan\Http\Controllers;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PayMe\Remotisan\CommandsRepository;
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
    public function filters(Request $request): array
    {
        $this->rt->requireAuthenticated();

        return [
            "users" => Execution::getUsers()
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

        $request->validate(["command" => "required"]);

        $this->validateParamsLength($request->json("params"));

        $command = $request->json("command");
        $params  = $request->json("params");

        return [
            "id" => $this->rt->execute($command, $params)
        ];
    }

    /**
     * Kill process endpoint. If PID returned, then process killed.
     *
     * @param Request   $request
     * @param string    $uuid
     *
     * @return JsonResponse
     */
    public function sendKillSignal(Request $request, string $uuid): JsonResponse
    {
        $code = 200;
        try {
            $this->rt->sendKillSignal($uuid);
        } catch (RemotisanException $e) {
            $uuid = null;
            $code = $e->getCode() ?: 500;
        }

        return response()->json(["uuid" => $uuid], $code);
    }

    /**
     * @param Request $request
     * @return LengthAwarePaginator
     */
    public function history(Request $request): LengthAwarePaginator
    {
        $this->rt->requireAuthenticated();

        $shouldScope = config("remotisan.history.should-scope", false); // Get results only for the logged user
        $command = $request->input("command");
        $userName = null;

        if ($shouldScope) {
            $userName = $this->rt->getUserIdentifier();
        } elseif ($request->input("user") != "null") {
            $userName = $request->input("user");
        }

        return Execution::query()
            ->when($userName, fn(Builder $q) => $q->where("user_identifier", $userName))
            ->when($command, fn(Builder $q) => $q->whereRaw("CONCAT(command, ' ' , parameters) LIKE '%{$command}%'"))
            ->orderByDesc("executed_at")
            ->limit(config("remotisan.history.max_records"))
            ->paginate(10);
    }

    /**
     * @param Request $request
     * @param         $uuid
     *
     * @return array
     * @throws FileNotFoundException
     */
    public function read(Request $request, $uuid): array
    {
        $this->rt->requireAuthenticated();

        return FileManager::read($uuid);
    }

    /**
     * @param $params
     * @return void
     */
    private function validateParamsLength($params): void
    {
        $paramsLength = config("remotisan.commands.max_params_chars_length");

        if (strlen($params) > $paramsLength) {
            throw new \UnexpectedValueException("Parameters length exceeded {$paramsLength} chars");
        }
    }
}
