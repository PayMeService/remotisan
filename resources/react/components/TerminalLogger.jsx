import React, { useEffect, useRef, useState } from 'react';
import { Terminal } from 'xterm';
import 'xterm/css/xterm.css';

const TerminalLogger = ({ activeUuid , baseUrl = '', setHistoryRefresh}) => {
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
        if (xtermInstance.current) xtermInstance.current.clear();

        let timeoutId;

        // Polling function that fetches logs from the backend.
        const readLog = () => {
            fetch(`${baseUrl}/execute/${activeUuid}`)
                .then(response => response.json())
                .then(data => {
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
                            setHistoryRefresh(prev => prev + 1);
                        }

                    }
                })
                .catch(error => {
                    console.error("Error reading log:", error);
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
    }, [activeUuid]);

    // This function handles new output lines from the backend
    const write = (content = []) => {
        setLines([...content]);
        if (xtermInstance.current) {
            xtermInstance.current.clear();
            content.forEach((line) => xtermInstance.current.writeln(line));
        }
    };

    return  <div className="p-6 bg-white rounded shadow my-2">
        <h2 className="text-2xl font-bold mb-4">Logger</h2>
        <div ref={terminalRef} />
    </div>;
};

export default TerminalLogger;
