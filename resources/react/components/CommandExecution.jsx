import React, { useState, useEffect, useRef } from 'react';
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
  const [commandSearch, setCommandSearch] = useState('');
  const [showDropdown, setShowDropdown] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(-1);
  const dropdownRef = useRef(null);

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

  // Scroll highlighted item into view
  useEffect(() => {
    if (highlightedIndex >= 0 && dropdownRef.current) {
      const highlightedElement = dropdownRef.current.children[highlightedIndex];
      if (highlightedElement) {
        highlightedElement.scrollIntoView({
          behavior: 'smooth',
          block: 'nearest'
        });
      }
    }
  }, [highlightedIndex]);

  // Filter commands based on search input
  const filteredCommands = commands.filter(cmd => {
    const searchTerm = commandSearch.toLowerCase();
    return cmd.name.toLowerCase().includes(searchTerm) || 
           cmd.description.toLowerCase().includes(searchTerm);
  });

  // Handle command selection
  const handleCommandSelect = (command) => {
    setCommandSelected(command.name);
    setCommandSearch(command.name);
    setShowDropdown(false);
    setParams('');
    setHighlightedIndex(-1);
  };

  // Handle search input changes
  const handleSearchChange = (e) => {
    const value = e.target.value;
    setCommandSearch(value);
    setShowDropdown(true);
    setHighlightedIndex(-1);
    
    // Clear selection if search doesn't match current selection
    if (!value || !commandSelected.toLowerCase().includes(value.toLowerCase())) {
      setCommandSelected('');
    }
  };

  // Handle keyboard navigation
  const handleKeyDown = (e) => {
    if (!showDropdown) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setHighlightedIndex(prev => 
          prev < filteredCommands.length - 1 ? prev + 1 : 0
        );
        break;
      case 'ArrowUp':
        e.preventDefault();
        setHighlightedIndex(prev => 
          prev > 0 ? prev - 1 : filteredCommands.length - 1
        );
        break;
      case 'Enter':
        e.preventDefault();
        if (highlightedIndex >= 0 && filteredCommands[highlightedIndex]) {
          handleCommandSelect(filteredCommands[highlightedIndex]);
        }
        break;
      case 'Escape':
        setShowDropdown(false);
        setHighlightedIndex(-1);
        break;
    }
  };

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
    <div style={{ 
      padding: '30px', 
      background: 'white', 
      borderRadius: '12px', 
      boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
      border: '1px solid #e5e7eb'
    }}>
      <h2 style={{ 
        fontSize: '28px', 
        fontWeight: '700', 
        marginBottom: '20px',
        color: '#1f2937',
        display: 'flex',
        alignItems: 'center',
        gap: '10px'
      }}>
        ⚡ Execute Command
      </h2>
      
      <form
        onSubmit={(e) => {
          e.preventDefault();
          executeCommand();
        }}
      >
        <div style={{ marginBottom: '24px' }}>
          <label htmlFor="command" style={{ 
            display: 'block', 
            fontWeight: '600', 
            marginBottom: '8px',
            color: '#374151',
            fontSize: '16px'
          }}>
            🔍 Search & Select Command
          </label>
          
          <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
            <div style={{ position: 'relative', width: '100%', maxWidth: '600px' }}>
              <input
                type="text"
                id="command"
                required
                value={commandSearch}
                onChange={handleSearchChange}
                onKeyDown={handleKeyDown}
                onFocus={() => setShowDropdown(true)}
                onBlur={() => setTimeout(() => setShowDropdown(false), 150)}
                placeholder="Type to search commands... (try 'make', 'migrate', 'cache')"
                style={{
                  width: '100%',
                  border: '2px solid #d1d5db',
                  borderRadius: '8px',
                  padding: '12px 16px',
                  fontSize: '16px',
                  outline: 'none',
                  transition: 'all 0.2s',
                  background: '#fafafa'
                }}
                autoComplete="off"
              />
              
              {showDropdown && filteredCommands.length > 0 && (
                <div 
                  ref={dropdownRef}
                  style={{
                    position: 'absolute',
                    zIndex: 50,
                    width: '100%',
                    marginTop: '4px',
                    background: 'white',
                    border: '2px solid #e5e7eb',
                    borderRadius: '8px',
                    boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
                    maxHeight: '300px',
                    overflowY: 'auto'
                  }}
                >
                  {filteredCommands.map((cmd, idx) => (
                    <div
                      key={idx}
                      style={{
                        padding: '12px 16px',
                        cursor: 'pointer',
                        borderBottom: idx < filteredCommands.length - 1 ? '1px solid #f3f4f6' : 'none',
                        background: idx === highlightedIndex ? '#dbeafe' : 'white',
                        transition: 'background-color 0.1s'
                      }}
                      onClick={() => handleCommandSelect(cmd)}
                      onMouseEnter={() => setHighlightedIndex(idx)}
                    >
                      <div style={{ 
                        fontWeight: '600', 
                        color: '#1f2937',
                        marginBottom: '4px'
                      }}>
                        {cmd.name}
                      </div>
                      <div style={{ 
                        fontSize: '14px', 
                        color: '#6b7280' 
                      }}>
                        {cmd.description}
                      </div>
                    </div>
                  ))}
                </div>
              )}
              
              {showDropdown && commandSearch && filteredCommands.length === 0 && (
                <div style={{
                  position: 'absolute',
                  zIndex: 50,
                  width: '100%',
                  marginTop: '4px',
                  background: 'white',
                  border: '2px solid #e5e7eb',
                  borderRadius: '8px',
                  boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1)'
                }}>
                  <div style={{ 
                    padding: '16px', 
                    color: '#6b7280',
                    textAlign: 'center',
                    fontStyle: 'italic'
                  }}>
                    ❌ No commands found for "{commandSearch}"
                  </div>
                </div>
              )}
            </div>
            
            <label style={{ 
              display: 'flex', 
              alignItems: 'center', 
              gap: '8px',
              fontSize: '14px',
              color: '#374151',
              whiteSpace: 'nowrap'
            }}>
              <input
                type="checkbox"
                checked={showHelp}
                onChange={() => setShowHelp(!showHelp)}
                style={{ width: '16px', height: '16px' }}
              />
              {showHelp ? 'Hide commands help' : 'Show commands help'}
            </label>
          </div>
        </div>

        {showHelp && commandSelected && (
          <div style={{ 
            marginBottom: '24px',
            padding: '20px',
            background: '#f8fafc',
            border: '1px solid #e2e8f0',
            borderRadius: '8px'
          }}>
            <h3 style={{ 
              color: '#1e40af',
              marginTop: '0',
              marginBottom: '12px',
              fontSize: '18px',
              fontWeight: '600'
            }}>
              📚 Command Help: {commandSelected}
            </h3>
            <CommandHelp
              command={commands.find((cmd) => cmd.name === commandSelected)}
            />
          </div>
        )}

        <div style={{ marginBottom: '24px' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '24px' }}>
            <label style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <input
                type="radio"
                name="mode"
                value="single"
                checked={mode === 'single'}
                onChange={() => setMode('single')}
                style={{ width: '16px', height: '16px' }}
              />
              <span style={{ fontSize: '16px', fontWeight: '500' }}>Single Command</span>
            </label>
            <label style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <input
                type="radio"
                name="mode"
                value="bulk"
                checked={mode === 'bulk'}
                onChange={() => setMode('bulk')}
                style={{ width: '16px', height: '16px' }}
              />
              <span style={{ fontSize: '16px', fontWeight: '500' }}>Bulk Commands</span>
            </label>
          </div>
        </div>

        <div style={{ marginBottom: '24px' }}>
          <label style={{
            display: 'block',
            fontWeight: '600',
            marginBottom: '8px',
            color: '#374151',
            fontSize: '16px'
          }}>
            {mode === 'single' ? '📝 Parameters (optional)' : '📝 Bulk Commands (one per line)'}
          </label>
          {mode === 'single' ? (
            <textarea
              placeholder="Enter command parameters and options here..."
              value={params}
              onChange={(e) => setParams(e.target.value)}
              style={{
                width: '100%',
                maxWidth: '600px',
                border: '2px solid #d1d5db',
                borderRadius: '8px',
                padding: '12px 16px',
                minHeight: '100px',
                outline: 'none',
                resize: 'vertical',
                fontSize: '14px',
                background: '#fafafa'
              }}
            />
          ) : (
            <textarea
              placeholder="Enter one command per line... (e.g., --force, --seed, etc.)"
              value={bulkParams}
              onChange={(e) => setBulkParams(e.target.value)}
              style={{
                width: '100%',
                maxWidth: '600px',
                border: '2px solid #d1d5db',
                borderRadius: '8px',
                padding: '12px 16px',
                minHeight: '200px',
                outline: 'none',
                resize: 'vertical',
                fontSize: '14px',
                background: '#fafafa'
              }}
            />
          )}
        </div>

        {commandSelected && (
          <div style={{ 
            marginBottom: '24px', 
            padding: '16px', 
            background: 'linear-gradient(90deg, #ecfdf5 0%, #f0fdf4 100%)', 
            borderRadius: '8px',
            border: '1px solid #a7f3d0'
          }}>
            <div style={{ 
              fontWeight: '600',
              color: '#059669',
              marginBottom: '8px'
            }}>
              ✅ Selected Command: {commandSelected}
            </div>
            {(() => {
              const selectedCommand = commands.find(cmd => cmd.name === commandSelected);
              if (selectedCommand?.usageManual) {
                return (
                  <div style={{
                    fontFamily: 'Monaco, "Cascadia Code", "Roboto Mono", monospace',
                    fontSize: '14px',
                    color: '#065f46',
                    background: 'rgba(255, 255, 255, 0.7)',
                    padding: '8px 12px',
                    borderRadius: '4px',
                    border: '1px dashed #a7f3d0'
                  }}>
                    <strong>Example:</strong> php artisan {selectedCommand.usageManual.replace('Usage: ', '')}
                  </div>
                );
              }
              return null;
            })()}
          </div>
        )}

        {error && (
          <div style={{ 
            marginBottom: '24px',
            padding: '16px',
            background: 'linear-gradient(90deg, #fef2f2 0%, #fee2e2 100%)',
            border: '1px solid #fca5a5',
            borderRadius: '8px',
            color: '#dc2626'
          }}>
            ❌ {error}
          </div>
        )}

        <button
          type="submit"
          disabled={loading}
          style={{
            background: loading ? '#9ca3af' : 'linear-gradient(90deg, #3b82f6 0%, #1d4ed8 100%)',
            color: 'white',
            fontWeight: '600',
            padding: '12px 24px',
            borderRadius: '8px',
            border: 'none',
            cursor: loading ? 'not-allowed' : 'pointer',
            fontSize: '16px',
            transition: 'all 0.2s',
            boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
            opacity: loading ? 0.6 : 1
          }}
          onMouseOver={(e) => {
            if (!loading) {
              e.target.style.transform = 'translateY(-1px)';
              e.target.style.boxShadow = '0 6px 8px -1px rgba(0, 0, 0, 0.15)';
            }
          }}
          onMouseOut={(e) => {
            if (!loading) {
              e.target.style.transform = 'translateY(0)';
              e.target.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
            }
          }}
        >
          {loading ? '⏳ Executing...' : '🚀 Execute Command'}
        </button>
      </form>
    </div>
  );
};

export default CommandExecution;
