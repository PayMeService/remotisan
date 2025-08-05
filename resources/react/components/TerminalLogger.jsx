import React, { useEffect, useRef, useState } from 'react';
import { Terminal } from 'xterm';
import 'xterm/css/xterm.css';
import axios from 'axios';

const TerminalLogger = ({ activeUuid, baseUrl = '', setHistoryRefresh }) => {
  const terminalRef = useRef(null);
  const xtermInstance = useRef(null);
  const [lines, setLines] = useState([]);

  useEffect(() => {
    // Initialize the xterm terminal instance on component mount
    xtermInstance.current = new Terminal({ cols: 182 });
    xtermInstance.current.open(terminalRef.current);
    // Cleanup on unmount
    return () => {
      if (xtermInstance.current) {
        xtermInstance.current.dispose();
      }
    };
  }, []);

  useEffect(() => {
    if (!activeUuid) return;
    // Reset terminal and local output state when activeUuid changes.
    setLines([]);
    if (xtermInstance.current) {
      xtermInstance.current.clear();
      xtermInstance.current.writeln(`\x1b[36mLoading logs for UUID: ${activeUuid}...\x1b[0m`);
    }

    let timeoutId;

    // Polling function that fetches logs from the backend.
    const readLog = () => {
      axios
        .get(`${baseUrl}/execute/${activeUuid}`)
        .then((response) => {
          const data = response.data;
          // Assuming "data.content" is an array of new output strings.
          if (data && Array.isArray(data.content)) {
            write(data.content);
          }
          // If not ended, schedule another poll after 1 second.
          if (!data.isEnded) {
            timeoutId = setTimeout(readLog, 1000);
          } else {
            // Command execution is finished.
            // Notify parent to refresh the HistoryTable.
            if (setHistoryRefresh) {
              setHistoryRefresh((prev) => prev + 1);
            }
          }
        })
        .catch((error) => {
          console.error('Error reading log for UUID:', activeUuid, error);
          // If the request fails, show an error message in the terminal
          if (xtermInstance.current) {
            xtermInstance.current.writeln(`\x1b[31mError loading logs for UUID: ${activeUuid}\x1b[0m`);
            
            if (error.response?.status === 404) {
              xtermInstance.current.writeln(`\x1b[33mUUID not found or logs not available\x1b[0m`);
            } else if (error.response?.status === 500) {
              const errorMsg = error.response?.data?.message || '';
              if (errorMsg.includes('File does not exist')) {
                xtermInstance.current.writeln(`\x1b[33mLog file not found - command may not have run or logs were deleted\x1b[0m`);
                xtermInstance.current.writeln(`\x1b[36mTip: Try commands from recent pages (they may have active logs)\x1b[0m`);
              } else {
                xtermInstance.current.writeln(`\x1b[31mServer error: ${errorMsg}\x1b[0m`);
              }
            } else {
              xtermInstance.current.writeln(`\x1b[31mHTTP ${error.response?.status || 'Unknown'} error\x1b[0m`);
            }
          }
        });
    };

    // Start polling the log.
    readLog();

    // Cleanup function: clears any pending timeout when activeUuid changes or the component unmounts.
    return () => {
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
    };
  }, [activeUuid, baseUrl, setHistoryRefresh]);

  // This function handles new output lines from the backend
  const write = (content = []) => {
    setLines([...content]);
    if (xtermInstance.current) {
      xtermInstance.current.clear();
      content.forEach((line) => xtermInstance.current.writeln(line));
    }
  };

  return (
    <div className="p-6 bg-white rounded shadow my-2">
      <h2 className="text-2xl font-bold mb-4">Logger</h2>
      <div ref={terminalRef} />
    </div>
  );
};

export default TerminalLogger;
