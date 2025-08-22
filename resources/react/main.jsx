import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom/client';
import CommandExecution from './components/CommandExecution';
import HistoryTable from './components/HistoryTable';
import TerminalLogger from './components/TerminalLogger';
import './../css/app.css';

const App = () => {
  const baseUrl = window.remotisanBaseUrl || '';
  const [activeUuid, setActiveUuid] = useState(null);
  const [historyRefresh, setHistoryRefresh] = useState(0);

  useEffect(() => {
    const hash = window.location.hash.substring(1);
    if (hash) {
      setActiveUuid(hash);
    }
  }, []);

  useEffect(() => {
    if (activeUuid) {
      window.location.hash = activeUuid;
    }
  }, [activeUuid]);

  return (
    <div style={{ padding: '1rem' }}>
      <CommandExecution
        baseUrl={baseUrl}
        activeUuid={activeUuid}
        setActiveUuid={setActiveUuid}
      />
      <TerminalLogger
        baseUrl={baseUrl}
        activeUuid={activeUuid}
        setHistoryRefresh={setHistoryRefresh}
      />
      <HistoryTable
        baseUrl={baseUrl}
        setActiveUuid={setActiveUuid}
        historyRefresh={historyRefresh}
      />
    </div>
  );
};

const rootElement = document.getElementById('react-root');
if (rootElement) {
  ReactDOM.createRoot(rootElement).render(<App />);
} else {
  console.error('No element with id "react-root" found');
}
