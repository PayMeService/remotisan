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