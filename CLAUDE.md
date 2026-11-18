# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Testing
- `composer test` or `vendor/bin/phpunit` - Run all tests
- `vendor/bin/phpunit tests/src/[TestFile.php]` - Run specific test file
- `vendor/bin/phpunit --filter testMethodName` - Run specific test method
- Test coverage reports generated in `build/` directory

### Frontend Development
- `npm run dev` - Start development server with hot reloading
- `npm run build` - Build production assets to `dist/` directory
- `npm run lint` - Lint React/JavaScript files
- `npm run lint:fix` - Fix linting issues automatically
- `npm run format` - Format code with Prettier

### Laravel Integration Testing
The package is designed to work with Laravel applications using Orchestra Testbench:
- Tests extend `PayMe\Remotisan\Tests\src\TestCase`
- Database connection is set to `testing` environment
- Service provider automatically loaded in test environment

## Architecture Overview

### Core Components

**Remotisan Class** (`src/Remotisan.php`)
- Main service class that orchestrates command execution
- Manages authentication via role-based callbacks
- Handles process lifecycle (execute, kill signals)
- Uses dependency injection for CommandsRepository and ProcessExecutor

**Process Execution Flow**
1. `RemotisanController::execute()` - HTTP endpoint receives command request
2. `Remotisan::execute()` - Validates command permissions and creates execution record
3. `ProcessExecutor::execute()` - Spawns broker command in background
4. `ProcessBrokerCommand::handle()` - Runs actual artisan command with signal handling
5. Execution record updated with status (running → completed/failed/killed)

**Authentication & Authorization**
- Role-based access control via `Remotisan::authWith($role, $callable)`
- A user holds every matching role (`Remotisan::getUserRoles()`); a command is allowed when any of them is in its `RemotisanRoles`/config roles. `getUserGroup()` (first match) is kept for BC only - don't authorize with it
- Commands configured in `config/remotisan.php` with role requirements
- Super user concept allows killing any process
- User identification via configurable callback

### Key Patterns

**Command Repository Pattern** (`CommandsRepository.php`)
- Manages allowed commands and their role requirements
- Validates execution permissions before command runs
- Supports environment-specific command configuration via `REMOTISAN_ALLOWED_COMMANDS`

**Process State Management**
- `Execution` model tracks all command executions in database
- `ProcessStatuses` constants define process lifecycle states
- Events fired for execution completion/failure/kill (ExecutionCompleted, ExecutionFailed, ExecutionKilled)

**Signal-Based Process Killing**
- Kill signals sent via cache (Redis recommended for multi-instance)
- `ProcessBrokerCommand` implements `SignalableCommandInterface`
- Graceful shutdown with escalating signals (SIGQUIT → SIGINT → SIGTERM → SIGKILL)

**File-Based Logging**
- Each execution gets unique UUID and log file in storage
- Real-time log streaming via `FileManager::read()`
- Log files stored in configurable directory (`REMOTISAN_LOG_PATH`)

### Frontend Architecture

**React Components** (`resources/react/components/`)
- `CommandExecution.jsx` - Main interface for selecting and running commands
- `HistoryTable.jsx` - Display past executions with filtering
- `TerminalLogger.jsx` - Real-time log output display using xterm.js
- `CommandHelp.jsx` - Help documentation component

**API Integration**
- RESTful endpoints in `routes/web.php` prefixed by configurable URL
- Axios-based HTTP client for API communication
- Real-time log polling for live command output

### Configuration System

**Multi-Environment Support**
- Commands configurable via config file or `REMOTISAN_ALLOWED_COMMANDS` env var
- Role-based command restrictions per environment
- Configurable log paths, history limits, and UI settings

**Instance Identification**
- Server UUID automatically generated and stored for multi-instance deployments
- Kill signals routed to correct server instance via shared cache
- Essential for Docker/Kubernetes deployments

### Database Schema

**remotisan_executions Table**
- Tracks all command executions with metadata
- Includes job UUID, server UUID, user identifier, command/parameters
- Process status and timing information
- Kill tracking (who initiated kill, when)

## Development Notes

### Local Development Setup
Reference `LOCAL_REPOSITORY_SETUP.md` for detailed instructions on:
- Using local repository instead of Git dependencies
- Docker volume mounting for live development
- Asset building and cache clearing workflows

### Laravel Version Compatibility
- Supports Laravel 8-12 with PHP 8.1-8.3
- Uses Orchestra Testbench for package testing
- GitHub Actions matrix tests all supported combinations

### Multi-Instance Considerations
- Requires shared cache (Redis) for kill signal coordination
- Server identification via UUID prevents cross-instance conflicts
- Database audit table maintains execution history across instances
### Read Replicas and the Execution Record

Host applications commonly configure the default connection with a `read`/`write` split. The
execution record is written by the `POST /execute` request, but the two consumers that look it up
immediately afterwards run outside that request:

1. `ProcessBrokerCommand` - a separate CLI process
2. `RemotisanController::read()` - the frontend's first log poll, ~100ms later

Laravel's `sticky` flag only keeps reads on the primary *within the writing request*, so both of
these hit the replica and can miss a row that has not replicated yet. `Execution::getByJobUuid()`
therefore queries the replica first and retries a miss against the write connection. Any new
lookup of a freshly created execution must go through that method rather than building its own
query, or the race comes back.

When the record is genuinely absent, the failure modes are deliberate and must stay that way:
the broker exits with `FAILURE` after printing why, and the read endpoint answers `404`. Neither
should surface as a fatal error in the host application.

### Interactive Commands (Live Input)

Commands run by the broker can ask questions that the user answers live in the browser.
The code is in `src/Interactive/`:

- `PromptProtocol` is the wire format. The child writes a marker line
  `@@remotisan:prompt@@{json}` to stdout and reads one base64-encoded answer line from stdin.
- `RemotisanOutputStyle` is bound in place of `Illuminate\Console\OutputStyle` only when
  `REMOTISAN_INTERACTIVE=1` (set by the broker), in `RemotisanServiceProvider::registerInteractiveOutputStyle()`.
  Its `askQuestion()` is where every Laravel question API ends up. The provider also forces the
  Laravel Prompts fallback, so `text()`/`password()` reach it instead of returning their defaults.
- `PromptChannel` stores the pending prompt and the answers in the cache. Answers are encrypted,
  have a short TTL and are deleted on read. In a multi-instance setup this needs a shared cache,
  as kill instructions do.
- `ProcessBrokerCommand` gives the child an open `InputStream`. It strips markers from the output
  (`handleOutput`), writes `? question` to the log (`openPrompt`), and in its 100ms loop feeds
  answers to the child (`deliverAnswer`). A secret is echoed to the log as `> ********`.
- HTTP: `GET /execute/{uuid}/prompt`, and `POST /execute/{uuid}/input` with `{promptId, answer}`.
  Only the owner or a super user may answer, and the prompt id must match the pending prompt.
- Frontend: `hooks/usePendingPrompt.js` and `components/PromptInput.jsx` (a password input for secrets).

Rules for secrets:
- Never log an answer, store it, return it in a response or pass it as a process argument.
- `RemotisanController::input()` deliberately skips Laravel's validator, so its failure path can't
  flash the answer into the session.

Testing notes:
- `ProcessBrokerInteractiveTest` runs a real child process (`tests/fixtures/interactive_child.php`)
  against a scripted `PromptChannel`.
- The broker's kill check memoizes `FileManager::$server_uuid` in a static. Reset it in `tearDown`
  of any test that runs the broker, or `RemotisanTest::testGetInstanceUuid` fails depending on test order.
