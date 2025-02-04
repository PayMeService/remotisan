import React from 'react';

const CommandHelp = ({ command }) => {
  if (!command) return null;

  return (
    <div
      style={{ backgroundColor: '#f9fdf0', padding: '1rem', marginTop: '1rem' }}
    >
      <div>
        <strong>Command name:</strong> {command.name}
      </div>
      <div>
        <strong>Description:</strong> {command.description}
      </div>
      <div>
        <strong>Help:</strong> {command.help}
      </div>
      {command.definition && (
        <>
          <div>
            <strong>Arguments:</strong>
          </div>
          <div style={{ marginLeft: '20px' }}>
            {command.definition.args &&
              Object.entries(command.definition.args).map(
                ([argName, argDetails]) => (
                  <div key={argName}>
                    <strong>{argName}:</strong>{' '}
                    {argDetails.description || JSON.stringify(argDetails)}
                  </div>
                )
              )}
          </div>
          <div>
            <strong>Options:</strong>
          </div>
          <div style={{ marginLeft: '20px' }}>
            {command.definition.ops &&
              Object.entries(command.definition.ops).map(
                ([optName, optDetails]) => (
                  <div key={optName}>
                    <strong>{optName}:</strong>{' '}
                    {optDetails.description || JSON.stringify(optDetails)}
                  </div>
                )
              )}
          </div>
        </>
      )}
    </div>
  );
};

export default CommandHelp;
