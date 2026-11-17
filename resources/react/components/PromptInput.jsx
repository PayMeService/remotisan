import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import usePendingPrompt from '../hooks/usePendingPrompt';

const csrfToken = document
  .querySelector('meta[name="csrf-token"]')
  ?.getAttribute('content');

/**
 * Answers the question a running command is waiting on, live.
 *
 * A secret question uses a password input, so it shows as asterisks. Its value is kept only in this
 * component's state, cleared as soon as it is sent, and never put into the URL, history,
 * localStorage or the log. The broker writes a fixed mask to the log instead.
 */
const PromptInput = ({ baseUrl, activeUuid }) => {
  const { prompt, canAnswer, refresh } = usePendingPrompt(baseUrl, activeUuid);
  const [value, setValue] = useState('');
  const [sending, setSending] = useState(false);
  const [error, setError] = useState(null);
  const inputRef = useRef(null);

  // A new question starts empty and takes the focus.
  useEffect(() => {
    setValue('');
    setError(null);
    if (prompt) {
      inputRef.current?.focus();
    }
  }, [prompt?.id]);

  if (!prompt) {
    return null;
  }

  const submit = (answer) => {
    if (sending) {
      return;
    }

    setSending(true);
    setError(null);

    axios
      .post(
        `${baseUrl}/execute/${activeUuid}/input`,
        { promptId: prompt.id, answer },
        {
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
          },
        }
      )
      .then(() => {
        setValue('');
        refresh();
      })
      .catch((requestError) => {
        setError(
          requestError.response?.data?.message || 'Could not send the answer'
        );
        if (requestError.response?.status === 409) {
          refresh();
        }
      })
      .finally(() => setSending(false));
  };

  const onKeyDown = (event) => {
    // Enter sends; Shift+Enter adds a line in a multi-line answer.
    if (event.key === 'Enter' && !(prompt.multiline && event.shiftKey)) {
      event.preventDefault();
      submit(value);
    }
  };

  const inputProps = {
    ref: inputRef,
    value,
    onChange: (event) => setValue(event.target.value),
    onKeyDown,
    disabled: !canAnswer || sending,
    autoComplete: prompt.secret ? 'new-password' : 'off',
    spellCheck: false,
    placeholder: prompt.default !== null ? `default: ${prompt.default}` : '',
    className:
      'flex-1 border border-gray-300 rounded px-2 py-1 font-mono text-sm disabled:bg-gray-100',
  };

  return (
    <div className="p-4 bg-amber-50 border border-amber-300 rounded shadow my-2">
      <div className="flex items-center justify-between mb-2">
        <label className="font-semibold">
          {prompt.question}
          {prompt.secret && (
            <span className="ml-2 text-xs text-gray-500">
              (secret - masked in the log)
            </span>
          )}
        </label>
        <span className="text-xs text-amber-700">waiting for input</span>
      </div>

      {!canAnswer && (
        <div className="text-sm text-gray-600 mb-2">
          Only the user who started this execution can answer.
        </div>
      )}

      {prompt.choices && prompt.choices.length > 0 && (
        <div className="flex flex-wrap gap-2 mb-2">
          {prompt.choices.map((choice) => (
            <button
              key={choice}
              type="button"
              disabled={!canAnswer || sending}
              onClick={() => submit(choice)}
              className={`px-3 py-1 rounded border ${
                choice === prompt.default
                  ? 'border-indigo-600 text-indigo-700'
                  : 'border-gray-300'
              } hover:bg-gray-100 disabled:opacity-50`}
            >
              {choice}
            </button>
          ))}
        </div>
      )}

      <div className="flex gap-2 items-start">
        {prompt.multiline && !prompt.secret ? (
          <textarea rows={4} {...inputProps} />
        ) : (
          <input type={prompt.secret ? 'password' : 'text'} {...inputProps} />
        )}
        <button
          type="button"
          disabled={!canAnswer || sending}
          onClick={() => submit(value)}
          className="px-3 py-1 rounded bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-50"
        >
          {sending ? 'Sending...' : 'Send'}
        </button>
      </div>

      {error && <div className="text-sm text-red-600 mt-2">{error}</div>}
    </div>
  );
};

export default PromptInput;
