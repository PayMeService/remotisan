import React, { useState, useEffect } from 'react';
import he from 'he';
import axios from 'axios';

const csrfToken = document
  .querySelector('meta[name="csrf-token"]')
  .getAttribute('content');

const HistoryTable = ({
  baseUrl = '',
  activeUuid,
  setActiveUuid,
  historyRefresh,
}) => {
  const [historyRecords, setHistoryRecords] = useState([]);
  const [users, setUsers] = useState([]);
  const [pagination, setPagination] = useState([]);
  const [page, setPage] = useState(1);
  const [searchOptions, setSearchOptions] = useState({
    command: '',
    user: '',
    status: '',
    dateFrom: '',
    dateTo: '',
    uuid: '',
  });

  useEffect(() => {
    fetchHistory(page);
    axios
      .get(`${baseUrl}/filters`)
      .then((response) => {
        const data = response.data;
        // Assume that data.users is an array of user names.
        setUsers([
          { key: 'null', name: 'All' },
          ...data.users.map((item) => ({ key: item, name: item })),
        ]);
      })
      .catch((err) => console.error(err));
  }, [baseUrl, page, activeUuid, historyRefresh]);

  const fetchHistory = (pageToFetch) => {
    fetchHistoryWithOptions(pageToFetch, searchOptions);
  };

  const fetchHistoryWithOptions = (pageToFetch, options) => {
    const params = new URLSearchParams({
      page: pageToFetch,
      user: options.user || 'null',
      command: options.command,
      status: options.status,
      uuid: options.uuid,
      date_from: options.dateFrom,
      date_to: options.dateTo,
    });

    // Remove empty parameters to keep URL clean
    Object.keys(Object.fromEntries(params)).forEach((key) => {
      if (!params.get(key) || params.get(key) === 'null') {
        params.delete(key);
      }
    });

    axios
      .get(`${baseUrl}/history?` + params.toString())
      .then((response) => {
        const data = response.data;
        setHistoryRecords(data.data);
        setPagination(data.links);
      })
      .catch((err) => console.error(err));
  };

  const handleFilter = (e) => {
    e.preventDefault();
    setPage(1);
    fetchHistory(1);
  };

  const handleClearFilters = () => {
    const clearedOptions = {
      command: '',
      user: '',
      status: '',
      dateFrom: '',
      dateTo: '',
      uuid: '',
    };
    setSearchOptions(clearedOptions);
    setPage(1);

    // Directly fetch with cleared filters instead of using setTimeout
    fetchHistoryWithOptions(1, clearedOptions);
  };

  const handleReRun = (command, params) => {
    if (!window.confirm(`Are you sure to re-run "${command} ${params}"?`)) {
      return;
    }

    axios
      .post(
        `${baseUrl}/execute`,
        { command, params },
        {
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
          },
        }
      )
      .then((response) => {
        const data = response.data;
        alert('Command re-run successfully.');
        setActiveUuid(data.id);
      })
      .catch((error) => {
        alert('Error re-running command');
        console.error(error);
      });
  };

  const handleKill = (job_uuid) => {
    if (window.confirm(`Do you really want to kill job ${job_uuid}?`)) {
      axios
        .post(`${baseUrl}/kill/${job_uuid}`, null, {
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
          },
        })
        .then(() => {
          alert('Process killed');
        })
        .catch((error) => {
          let errorMessage =
            error.response &&
            error.response.data &&
            error.response.data.error_message
              ? error.response.data.error_message
              : error.message;
          alert(`Error killing process: ${errorMessage}`);
        });
    }
  };

  // Returns the correct Bootstrap label class based on process status
  const getLabelClass = (status) => {
    if (status === 1)
      return 'inline-block rounded bg-blue-500 px-2 py-1 text-xs font-semibold text-white';
    if (status === 2)
      return 'inline-block rounded bg-green-500 px-2 py-1 text-xs font-semibold text-white';
    return 'inline-block rounded bg-red-500 px-2 py-1 text-xs font-semibold text-white';
  };

  // Converts a status code to a human-readable string
  const statusCodeToHumanReadable = (status) => {
    const procStatuses = {
      1: 'RUNNING',
      2: 'COMPLETED',
      3: 'FAILED',
      4: 'KILLED',
    };
    return procStatuses[status] || 'UNKNOWN';
  };

  // If the process was killed, show who killed it
  const showKilledByIfStatusKilled = (record) => {
    if (record.process_status === 4 && record.killed_by) {
      return ` (${record.killed_by})`;
    }
    return '';
  };

  return (
    <div className="p-4 bg-white rounded shadow">
      <h2 className="text-2xl font-bold mb-4">History</h2>
      <form onSubmit={handleFilter} className="mb-4 space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">
              Command
            </label>
            <input
              type="search"
              value={searchOptions.command}
              onChange={(e) =>
                setSearchOptions({ ...searchOptions, command: e.target.value })
              }
              className="border border-gray-300 rounded-md py-2 px-3 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Search commands..."
              maxLength="100"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">
              User
            </label>
            <select
              value={searchOptions.user}
              onChange={(e) =>
                setSearchOptions({ ...searchOptions, user: e.target.value })
              }
              className="border border-gray-300 rounded-md py-2 px-3 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Users</option>
              {users.map((u) => (
                <option key={u.key} value={u.key === 'null' ? '' : u.key}>
                  {u.name}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">
              Status
            </label>
            <select
              value={searchOptions.status}
              onChange={(e) =>
                setSearchOptions({ ...searchOptions, status: e.target.value })
              }
              className="border border-gray-300 rounded-md py-2 px-3 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Statuses</option>
              <option value="1">Running</option>
              <option value="2">Completed</option>
              <option value="3">Failed</option>
              <option value="4">Killed</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">
              UUID
            </label>
            <input
              type="search"
              value={searchOptions.uuid}
              onChange={(e) =>
                setSearchOptions({ ...searchOptions, uuid: e.target.value })
              }
              className="border border-gray-300 rounded-md py-2 px-3 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Search UUID..."
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">
              From Date
            </label>
            <input
              type="date"
              value={searchOptions.dateFrom}
              onChange={(e) =>
                setSearchOptions({ ...searchOptions, dateFrom: e.target.value })
              }
              className="border border-gray-300 rounded-md py-2 px-3 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">
              To Date
            </label>
            <input
              type="date"
              value={searchOptions.dateTo}
              onChange={(e) =>
                setSearchOptions({ ...searchOptions, dateTo: e.target.value })
              }
              className="border border-gray-300 rounded-md py-2 px-3 w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>

        <div className="flex gap-2">
          <button
            type="submit"
            className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition duration-300"
          >
            Search
          </button>
          <button
            type="button"
            onClick={handleClearFilters}
            className="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition duration-300"
          >
            Clear
          </button>
        </div>
      </form>

      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-100">
            <tr>
              <th className="px-4 py-2 text-left text-xs font-medium text-gray-700">
                #
              </th>
              <th className="px-4 py-2 text-left text-xs font-medium text-gray-700">
                User
              </th>
              <th className="px-4 py-2 text-left text-xs font-medium text-gray-700 max-w-[500px]">
                Command
              </th>
              <th className="px-4 py-2 text-left text-xs font-medium text-gray-700">
                UUID
              </th>
              <th className="px-4 py-2 text-left text-xs font-medium text-gray-700">
                Proc Status
              </th>
              <th className="px-4 py-2 text-left text-xs font-medium text-gray-700">
                Date
              </th>
              <th className="px-4 py-2 text-left text-xs font-medium text-gray-700">
                Finished
              </th>
              <th className="px-4 py-2 text-left text-xs font-medium text-gray-700 w-40">
                Actions
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {historyRecords.map((record) => (
              <tr key={record.id} className="hover:bg-gray-50">
                <td className="px-4 py-2 text-sm text-gray-900">{record.id}</td>
                <td className="px-4 py-2 text-sm text-gray-900">
                  {record.user_identifier}
                </td>
                <td
                  className="px-4 py-2 text-sm text-gray-900 max-w-[500px] truncate"
                  title={`${record.command} ${record.parameters}`}
                >
                  <button
                    onClick={() =>
                      navigator.clipboard.writeText(record.parameters)
                    }
                    className="cursor-pointer mr-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs font-medium px-2 py-1 rounded"
                  >
                    Copy
                  </button>
                  {record.command} {record.parameters}
                </td>
                <td className="px-4 py-2 text-sm">
                  <span
                    onClick={() => setActiveUuid(record.job_uuid)}
                    className="cursor-pointer text-blue-600 hover:underline"
                  >
                    {record.job_uuid}
                  </span>
                </td>
                <td className="px-4 py-2 text-sm text-gray-900 flex">
                  <span className={getLabelClass(record.process_status)}>
                    {statusCodeToHumanReadable(record.process_status)}
                    {showKilledByIfStatusKilled(record)}
                  </span>
                </td>
                <td className="px-4 py-2 text-sm text-gray-900">
                  {new Date(record.executed_at * 1000).toLocaleString()}
                </td>
                <td className="px-4 py-2 text-sm text-gray-900">
                  {record.finished_at
                    ? new Date(record.finished_at * 1000).toLocaleString()
                    : ''}
                </td>
                <td className="px-2 py-2 text-sm">
                  {record.process_status === 1 && (
                    <span
                      onClick={() => handleKill(record.job_uuid)}
                      className="cursor-pointer inline-block rounded bg-red-500 px-1 py-1 text-xs font-semibold text-white mr-2"
                    >
                      Kill Process
                    </span>
                  )}
                  <span
                    onClick={() =>
                      handleReRun(record.command, record.parameters)
                    }
                    className="cursor-pointer inline-block rounded bg-cyan-500 px-1 py-1 text-xs font-semibold text-white"
                  >
                    Re-Run
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {pagination.length > 0 && (
        <div className="mt-4 flex justify-center space-x-2">
          {pagination.map((link, idx) => (
            <button
              key={idx}
              disabled={!link.url}
              onClick={() => setPage(idx)}
              className={`px-3 py-1 rounded ${
                link.url
                  ? 'bg-blue-500 text-white hover:bg-blue-600 cursor-pointer'
                  : 'bg-gray-300 text-gray-600 cursor-not-allowed'
              }`}
            >
              {he.decode(link.label)}
            </button>
          ))}
        </div>
      )}
    </div>
  );
};

export default HistoryTable;
