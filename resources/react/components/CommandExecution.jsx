import React, { useState, useEffect } from 'react';
import axios from 'axios';
import CommandHelp from './CommandHelp';

const csrfToken = document
  .querySelector('meta[name="csrf-token"]')
  .getAttribute('content');

// Updated CommandExecution now accepts activeUuid as a prop. This uuid is passed along with command execution results to TerminalLogger.
const CommandExecution = ({ baseUrl = '', activeUuid, setActiveUuid }) => {
  const [commands, setCommands] = useState([]);
  const [commandSelected, setCommandSelected] = useState('');
  const [params, setParams] = useState('');
  const [bulkParams, setBulkParams] = useState('');
  const [mode, setMode] = useState('single'); // 'single' or 'bulk'
  const [showHelp, setShowHelp] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  // Fetch commands from API and transform the object to an array.
  useEffect(() => {
    axios
      .get(`${baseUrl}/commands`)
      .then((response) => {
        const data = response.data;
        if (data.commands) {
          setCommands(Object.values(data.commands));
        }
      })
      .catch((err) => console.error(err));
  }, [baseUrl]);

  function createFetchPromise(commandParams) {
    return axios
      .post(
        `${baseUrl}/execute`,
        { command: commandSelected, params: commandParams },
        {
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
          },
        }
      )
      .then((response) => response.data);
  }

  const executeCommand = () => {
    setLoading(true);
    setError('');
    let commandRequests = [];

    if (mode === 'single') {
      commandRequests.push(createFetchPromise(params));
    } else if (mode === 'bulk') {
      const paramsArray = bulkParams
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line);

      commandRequests = paramsArray.map((params) => createFetchPromise(params));
    }

    Promise.all(commandRequests)
      .then((results) => {
        console.log(results);
        let result = results[results.length - 1];
        setLoading(false);
        setActiveUuid(result.id);
      })
      .catch((err) => {
        console.error(err);
        setLoading(false);
        setError(err.response?.data?.message || err.message || 'An error occurred while executing the command');
      });
  };

  return (
    <div className="p-6 bg-white rounded shadow">
      <h2 className="text-2xl font-bold mb-4">Execute</h2>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          executeCommand();
        }}
      >
        <div className="mb-4">
          <label
            htmlFor="command"
            className="block text-gray-700 font-medium mb-1"
          >
            Select a command
          </label>
          <div className="flex items-center space-x-4">
            <select
              id="command"
              required
              value={commandSelected}
              onChange={(e) => {
                setCommandSelected(e.target.value);
                setParams('');
              }}
              className="border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2"
            >
              <option value="">Select Command</option>
              {commands.map((cmd, idx) => (
                <option key={idx} value={cmd.name}>
                  {cmd.name} - {cmd.description}
                </option>
              ))}
            </select>
            <label className="flex items-center space-x-2 text-sm text-gray-700">
              <input
                type="checkbox"
                checked={showHelp}
                onChange={() => setShowHelp(!showHelp)}
                className="form-checkbox h-4 w-4 text-blue-600"
              />
              <span>
                {showHelp ? 'Hide commands help' : 'Show commands help'}
              </span>
            </label>
          </div>
        </div>

        <div className="mb-4">
          <div className="flex items-center space-x-4">
            <label className="flex items-center space-x-2">
              <input
                type="radio"
                name="mode"
                value="single"
                checked={mode === 'single'}
                onChange={() => setMode('single')}
                className="form-radio h-4 w-4 text-blue-600"
              />
              <span>Single Command</span>
            </label>
            <label className="flex items-center space-x-2">
              <input
                type="radio"
                name="mode"
                value="bulk"
                checked={mode === 'bulk'}
                onChange={() => setMode('bulk')}
                className="form-radio h-4 w-4 text-blue-600"
              />
              <span>Bulk Commands</span>
            </label>
          </div>
        </div>

        <div className="mb-4">
          {mode === 'single' ? (
            <textarea
              placeholder="Input options & arguments (if required)..."
              value={params}
              onChange={(e) => setParams(e.target.value)}
              className="w-3/4 border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          ) : (
            <textarea
              placeholder="Enter one command per line..."
              value={bulkParams}
              onChange={(e) => setBulkParams(e.target.value)}
              className="w-3/4 h-48 border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          )}
        </div>

        {error && (
          <div className="mb-4 p-3 bg-red-500 text-white rounded">
            {error}
          </div>
        )}

        <button
          type="submit"
          disabled={loading}
          className={`bg-blue-500 text-white font-semibold py-2 px-4 rounded transition duration-300 ${
            loading ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-600'
          }`}
        >
          Execute
        </button>
      </form>

      {showHelp && commandSelected && (
        <div className="mt-4">
          <CommandHelp
            command={commands.find((cmd) => cmd.name === commandSelected)}
          />
        </div>
      )}
    </div>
  );
};

export default CommandExecution;
