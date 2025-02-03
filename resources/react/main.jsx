import React, { useState } from 'react';
import ReactDOM from 'react-dom/client';
import CommandExecution from './components/CommandExecution';
import HistoryTable from './components/HistoryTable';
import TerminalLogger from './components/TerminalLogger';
import './../css/app.css';

const App = () => {
    // You may define your baseUrl or have it injected by Laravel.
    const baseUrl = window.remotisanBaseUrl || '';

    // Instead of managing terminalLines state, we now use an activeUuid to coordinate command execution and logging.
    // Here we initialize activeUuid; in a more dynamic system, you might update this per command or when needed.
    const [activeUuid, setActiveUuid] = useState(null);
    const [historyRefresh, setHistoryRefresh] = useState(0);

    return (
        <div style={{ padding: '1rem' }}>
            <CommandExecution baseUrl={baseUrl} activeUuid={activeUuid} setActiveUuid={setActiveUuid} />
            <TerminalLogger baseUrl={baseUrl} activeUuid={activeUuid} setHistoryRefresh={setHistoryRefresh} />
            <HistoryTable baseUrl={baseUrl} activeUuid={activeUuid}  setActiveUuid={setActiveUuid} historyRefresh={historyRefresh} />
        </div>
    );
};

const rootElement = document.getElementById('react-root');
if (rootElement) {
    ReactDOM.createRoot(rootElement).render(<App />);
} else {
    console.error('No element with id "react-root" found');
}
