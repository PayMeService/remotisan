import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';

const POLL_INTERVAL = 1000;

/**
 * Polls the question a running execution is waiting on.
 *
 * Polling stops once the execution has ended. The hook never sees answers - those are posted
 * by the caller and are not part of any response.
 *
 * @param {string} baseUrl
 * @param {string|null} uuid
 * @returns {{prompt: object|null, canAnswer: boolean, refresh: function}}
 */
export default function usePendingPrompt(baseUrl, uuid) {
  const [state, setState] = useState({ prompt: null, canAnswer: false });
  const timerRef = useRef(null);
  const sessionRef = useRef(0);

  const poll = useCallback(
    (session) => {
      axios
        .get(`${baseUrl}/execute/${uuid}/prompt`)
        .then(({ data }) => {
          if (session !== sessionRef.current) {
            return;
          }

          setState((current) =>
            current.prompt?.id === data.prompt?.id &&
            current.canAnswer === data.canAnswer
              ? current
              : { prompt: data.prompt, canAnswer: data.canAnswer }
          );

          if (data.isRunning) {
            timerRef.current = setTimeout(() => poll(session), POLL_INTERVAL);
          }
        })
        .catch(() => {
          if (session === sessionRef.current) {
            timerRef.current = setTimeout(
              () => poll(session),
              POLL_INTERVAL * 5
            );
          }
        });
    },
    [baseUrl, uuid]
  );

  const start = useCallback(() => {
    clearTimeout(timerRef.current);
    sessionRef.current += 1;

    if (uuid) {
      poll(sessionRef.current);
    }
  }, [poll, uuid]);

  useEffect(() => {
    setState({ prompt: null, canAnswer: false });
    start();

    return () => {
      clearTimeout(timerRef.current);
      sessionRef.current += 1;
    };
  }, [start]);

  return { ...state, refresh: start };
}
