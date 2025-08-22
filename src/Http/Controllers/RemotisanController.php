<?php

namespace PayMe\Remotisan\Http\Controllers;

use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PayMe\Remotisan\CommandsRepository;
use PayMe\Remotisan\Exceptions\ParametersLengthException;
use PayMe\Remotisan\Exceptions\RemotisanException;
use PayMe\Remotisan\FileManager;
use PayMe\Remotisan\Models\Execution;
use PayMe\Remotisan\Remotisan;
use RuntimeException;

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

        try {
            $this->validateParamsLength($request->json("params"));
            
            $command = $request->json("command");
            $params  = $request->json("params");

            return [
                "id" => $this->rt->execute($command, $params)
            ];
        } catch (ParametersLengthException|RuntimeException $e) {
            abort(400, $e->getMessage());
        } catch (Exception $e) {
            abort(500, 'An unexpected error occurred: ' . $e->getMessage());
        }
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

        $query = Execution::query();
        
        $this->applySearchFilters($query, $request);
        $this->applyUserFilters($query, $request);

        return $query->orderByDesc("executed_at")
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
     * Apply search filters to the query
     * 
     * @param Builder $query
     * @param Request $request
     * @return void
     */
    private function applySearchFilters(Builder $query, Request $request): void
    {
        if ($command = $request->input('command')) {
            $query->whereRaw("CONCAT(command, ' ', parameters) LIKE ?", ["%{$command}%"]);
        }
        
        if ($status = $request->input('status')) {
            $query->where('process_status', $status);
        }
        
        if ($uuid = $request->input('uuid')) {
            $query->where('job_uuid', 'LIKE', "%{$uuid}%");
        }
        
        if ($dateFrom = $request->input('date_from')) {
            $query->where('executed_at', '>=', strtotime($dateFrom));
        }
        
        if ($dateTo = $request->input('date_to')) {
            $query->where('executed_at', '<=', strtotime($dateTo) + 86400);
        }
    }

    /**
     * Apply user filters to the query
     * 
     * @param Builder $query
     * @param Request $request
     * @return void
     */
    private function applyUserFilters(Builder $query, Request $request): void
    {
        $shouldScope = config("remotisan.history.should-scope", false);
        $userName = null;

        if ($shouldScope) {
            $userName = $this->rt->getUserIdentifier();
        } elseif ($request->input("user") && $request->input("user") !== "null") {
            $userName = $request->input("user");
        }

        if ($userName) {
            $query->where("user_identifier", $userName);
        }
    }

    /**
     * @param $params
     * @return void
     */
    private function validateParamsLength($params): void
    {
        $paramsLength = config("remotisan.commands.max_params_chars_length");
        if (strlen($params) > $paramsLength) {
            throw new ParametersLengthException();
        }
    }
}
