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
  const [selectedUser, setSelectedUser] = useState('');
  const [searchable, setSearchable] = useState('');
  const [pagination, setPagination] = useState([]);
  const [page, setPage] = useState(1);

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
    const params = new URLSearchParams({
      page: pageToFetch,
      user: selectedUser,
      command: searchable,
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
      <form
        onSubmit={handleFilter}
        className="mb-4 flex flex-wrap items-center gap-4"
      >
        <div className="flex items-center gap-2">
          <label
            htmlFor="userSelect"
            className="text-sm font-medium text-gray-700"
          >
            Select User
          </label>
          <select
            id="userSelect"
            value={selectedUser}
            onChange={(e) => setSelectedUser(e.target.value)}
            className="border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
          >
            {users.map((u) => (
              <option key={u.key} value={u.key}>
                {u.name}
              </option>
            ))}
          </select>
        </div>
        <div className="flex items-center gap-2">
          <label
            htmlFor="searchable"
            className="text-sm font-medium text-gray-700"
          >
            Command:
          </label>
          <input
            type="search"
            id="searchable"
            value={searchable}
            onChange={(e) => setSearchable(e.target.value)}
            maxLength="100"
            className="border border-gray-300 rounded-md py-1 px-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <button
          type="submit"
          className="bg-blue-500 text-white font-semibold py-2 px-4 rounded hover:bg-blue-600 transition duration-300 cursor-pointer"
        >
          Filter
        </button>
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
              <th className="px-4 py-2 text-left text-xs font-medium text-gray-700">
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
                  className="px-4 py-2 text-sm text-gray-900"
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
