<?php

namespace PayMe\Remotisan\Http\Controllers;

use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use PayMe\Remotisan\CommandsRepository;
use PayMe\Remotisan\Exceptions\ParametersLengthException;
use PayMe\Remotisan\Exceptions\RecordNotFoundException;
use PayMe\Remotisan\Exceptions\RemotisanException;
use PayMe\Remotisan\FileManager;
use PayMe\Remotisan\LogReader;
use PayMe\Remotisan\Models\Execution;
use PayMe\Remotisan\Remotisan;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            "commands" => $this->commandsRepo->allByRole($this->rt->getUserRoles())
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
     * Serves one cursor paginated chunk of an execution log, in either direction.
     *
     * The cursor is a byte offset carried over from a previous chunk, so the client can walk the
     * log backwards and forwards without the server ever holding the whole file.
     *
     * @param Request $request
     * @param         $uuid
     *
     * @return array
     * @throws FileNotFoundException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException  When the execution is unknown.
     */
    public function read(Request $request, $uuid): array
    {
        $this->rt->requireAuthenticated();

        $request->validate([
            "direction" => ["sometimes", Rule::in(LogReader::DIRECTIONS)],
            "cursor"    => ["sometimes", "nullable", "integer", "min:0"],
            "limit"     => ["sometimes", "integer", "min:0", "max:" . LogReader::MAX_LIMIT],
        ]);

        $cursor = $request->query("cursor");

        try {
            return FileManager::read(
                $uuid,
                $request->query("direction", LogReader::DIRECTION_TAIL),
                $cursor === null || $cursor === "" ? null : (int)$cursor,
                (int)$request->query("limit", LogReader::DEFAULT_LIMIT)
            );
        } catch (RecordNotFoundException $e) {
            // An unknown execution is a plain 404 for the caller, not an application failure.
            // Letting it bubble made every poll for a purged - or not yet replicated - execution
            // register as a fatal error in the host application.
            abort(404, $e->getMessage());
        }
    }

    /**
     * Streams the whole log file of an execution as a download.
     *
     * The file is pushed out block by block rather than read into memory, so a log of any size can
     * be taken away in full - the paginated viewer only ever shows a window of it.
     *
     * @param Request $request
     * @param         $uuid
     *
     * @return StreamedResponse
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException  When the execution
     *         is unknown or its log file is gone.
     */
    public function download(Request $request, $uuid): StreamedResponse
    {
        $this->rt->requireAuthenticated();

        try {
            $execution = Execution::getByJobUuid($uuid);

            if (!$execution) {
                throw new RecordNotFoundException();
            }

            $path = FileManager::requireLogFilePath($uuid);
        } catch (RecordNotFoundException | FileNotFoundException) {
            // An unknown execution, or a log that is no longer on disk, is a plain 404 for the
            // caller rather than an application failure - the same treatment read() gives it.
            // Both refusals still land before the response starts streaming.
            //
            // The message is deliberately not the caught one: FileNotFoundException carries the
            // absolute path of the log file, and Laravel renders an HTTP exception's message into
            // the response body even with app.debug off. The caller only needs to know there is
            // nothing to fetch.
            abort(404, "Not Found");
        }

        return response()->streamDownload(
            fn() => LogReader::stream($path),
            FileManager::getDownloadFileName($execution),
            ["Content-Type" => "text/plain; charset=UTF-8"]
        );
    }

    /**
     * The question a running execution is waiting on, polled by the browser.
     *
     * @param Request $request
     * @param string  $uuid
     *
     * @return array{prompt: array<string, mixed>|null, canAnswer: bool, isRunning: bool}
     */
    public function prompt(Request $request, string $uuid): array
    {
        $this->rt->requireAuthenticated();

        try {
            return $this->rt->getPendingPrompt($uuid);
        } catch (RecordNotFoundException) {
            abort(404, "Not Found");
        }
    }

    /**
     * Answer the question a running execution is waiting on.
     *
     * The answer may be a secret, so it is deliberately not run through Laravel's validator (whose
     * failure path can flash input to the session), never logged, and never echoed back: the
     * response carries no part of it.
     *
     * @param Request $request
     * @param string  $uuid
     *
     * @return JsonResponse
     */
    public function input(Request $request, string $uuid): JsonResponse
    {
        $this->rt->requireAuthenticated();

        $promptId = $request->json("promptId");
        // ConvertEmptyStringsToNull turns "" into null; an empty answer means "take the default".
        $answer = $request->json("answer") ?? "";

        if (!is_string($promptId) || $promptId === "" || !is_string($answer)) {
            return response()->json(["message" => "promptId and answer are required strings"], 422);
        }

        if (mb_strlen($answer) > config("remotisan.interactive.max_answer_length")) {
            return response()->json(["message" => "Answer is too long"], 422);
        }

        try {
            $this->rt->submitInput($uuid, $promptId, $answer);
        } catch (RecordNotFoundException) {
            return response()->json(["message" => "Not Found"], 404);
        } catch (RemotisanException $e) {
            return response()->json(["message" => $e->getMessage()], $e->getCode() ?: 500);
        }

        return response()->json(["accepted" => true]);
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
