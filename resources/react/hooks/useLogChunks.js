import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';

export const BEFORE = 'before';
export const AFTER = 'after';
export const TAIL = 'tail';

const DEFAULT_LIMIT = 200;
// Rows kept mounted. Anything beyond this is dropped from the window but stays in the chunk
// cache, so scrolling back to it costs no request.
const MAX_WINDOW_LINES = 3000;
const MAX_CACHED_CHUNKS = 40;
const POLL_INTERVAL = 1000;

const emptyView = () => ({
  lines: [],
  start: 0,
  end: 0,
  size: 0,
  atStart: false,
  atEnd: false,
  isEnded: false,
  ready: false,
  // Bumped on every mutation, so the viewer can anchor the scroll position exactly once per
  // change. topDelta counts the rows added above the viewport, negative when rows were dropped.
  seq: 0,
  topDelta: 0,
});

/**
 * Cursor paginated access to one execution log, in both directions.
 *
 * The cursor is the byte offset of a line, handed back by the server with every chunk. Chunks
 * adjacent to the loaded window are prefetched into a cache so revealing them is instant.
 */
export const useLogChunks = ({
  baseUrl,
  uuid,
  limit = DEFAULT_LIMIT,
  maxWindowLines = MAX_WINDOW_LINES,
  following = true,
}) => {
  const [view, setView] = useState(emptyView);
  const [pending, setPending] = useState({ before: false, after: false });
  const [error, setError] = useState(null);

  // Mirrors `view` for callbacks, which must read the current window without being rebuilt on
  // every single appended line.
  const viewRef = useRef(view);
  const cacheRef = useRef(new Map());
  const inFlightRef = useRef(new Map());
  const revealingRef = useRef({ [BEFORE]: false, [AFTER]: false });
  const abortRef = useRef(null);
  // Anything fetched before the session was bumped belongs to a window the user has left.
  const sessionRef = useRef(0);

  const commit = useCallback((mutate) => {
    setView((current) => {
      const next = mutate(current);
      viewRef.current = next;

      return next;
    });
  }, []);

  const remember = useCallback((key, chunk) => {
    const cache = cacheRef.current;
    cache.delete(key);
    cache.set(key, chunk);

    while (cache.size > MAX_CACHED_CHUNKS) {
      cache.delete(cache.keys().next().value);
    }
  }, []);

  /**
   * One request per (direction, cursor). A cached chunk resolves immediately, an identical
   * request already on the wire is shared rather than duplicated.
   */
  const request = useCallback(
    (direction, cursor, lineLimit) => {
      const key = `${direction}:${cursor}:${lineLimit}`;
      const cached = cacheRef.current.get(key);

      if (cached) {
        return Promise.resolve(cached);
      }

      const inFlight = inFlightRef.current.get(key);

      if (inFlight) {
        return inFlight;
      }

      const session = sessionRef.current;
      const promise = axios
        .get(`${baseUrl}/execute/${uuid}`, {
          params: { direction, cursor, limit: lineLimit },
          signal: abortRef.current?.signal,
        })
        .then(({ data }) => {
          if (session !== sessionRef.current) {
            return null;
          }

          // The log is append only, so a full page can never change and is safe to keep. A short
          // page sits at the tail and may still grow, so it is never cached.
          if (
            lineLimit > 0 &&
            (direction === BEFORE || data.lines.length === lineLimit)
          ) {
            remember(key, data);
          }

          setError(null);

          return data;
        })
        .catch((requestError) => {
          if (!axios.isCancel(requestError) && session === sessionRef.current) {
            setError(requestError);
          }

          return null;
        })
        .finally(() => inFlightRef.current.delete(key));

      inFlightRef.current.set(key, promise);

      return promise;
    },
    [baseUrl, uuid, remember]
  );

  /** Warms the cache with the chunk just past an edge of the window. */
  const prefetch = useCallback(
    (direction) => {
      const current = viewRef.current;

      if (!current.ready) {
        return;
      }

      if (direction === BEFORE && !current.atStart) {
        request(BEFORE, current.start, limit);
      }

      if (direction === AFTER && !current.atEnd) {
        request(AFTER, current.end, limit);
      }
    },
    [request, limit]
  );

  const prepend = useCallback(
    (chunk) =>
      commit((current) => {
        const known = current.lines.length ? current.lines[0].offset : Infinity;
        // Cursors are byte offsets, so a line seen twice is recognised by its own identity.
        const incoming = chunk.lines.filter((line) => line.offset < known);

        if (!incoming.length) {
          return {
            ...current,
            atStart: chunk.atStart,
            size: chunk.size,
            isEnded: chunk.isEnded ?? current.isEnded,
          };
        }

        let lines = incoming.concat(current.lines);
        let { end, atEnd } = current;

        if (lines.length > maxWindowLines) {
          end = lines[maxWindowLines].offset;
          lines = lines.slice(0, maxWindowLines);
          atEnd = false;
        }

        return {
          ...current,
          lines,
          start: lines[0].offset,
          end,
          atEnd,
          atStart: chunk.atStart,
          size: chunk.size,
          isEnded: chunk.isEnded ?? current.isEnded,
          seq: current.seq + 1,
          topDelta: incoming.length,
        };
      }),
    [commit, maxWindowLines]
  );

  const append = useCallback(
    (chunk) =>
      commit((current) => {
        const incoming = chunk.lines.filter(
          (line) => line.offset >= current.end
        );

        if (!incoming.length) {
          return {
            ...current,
            atEnd: chunk.atEnd,
            end: Math.max(current.end, chunk.end),
            size: chunk.size,
            isEnded: chunk.isEnded ?? current.isEnded,
          };
        }

        let lines = current.lines.concat(incoming);
        let { start, atStart } = current;
        let topDelta = 0;

        if (lines.length > maxWindowLines) {
          topDelta = maxWindowLines - lines.length;
          lines = lines.slice(-maxWindowLines);
          start = lines[0].offset;
          atStart = false;
        }

        return {
          ...current,
          lines,
          start,
          atStart,
          end: chunk.end,
          atEnd: chunk.atEnd,
          size: chunk.size,
          isEnded: chunk.isEnded ?? current.isEnded,
          seq: current.seq + 1,
          topDelta,
        };
      }),
    [commit, maxWindowLines]
  );

  /** Reveals the chunk past one edge, from cache when the prefetch got there in time. */
  const reveal = useCallback(
    async (direction) => {
      const current = viewRef.current;
      const edgeReached =
        direction === BEFORE ? current.atStart : current.atEnd;

      if (!current.ready || edgeReached || revealingRef.current[direction]) {
        return;
      }

      revealingRef.current[direction] = true;
      const cursor = direction === BEFORE ? current.start : current.end;
      const cached = cacheRef.current.has(`${direction}:${cursor}:${limit}`);

      // The spinner is for a prefetch that did not make it, not for every reveal.
      if (!cached) {
        setPending((state) => ({ ...state, [direction]: true }));
      }

      try {
        const chunk = await request(direction, cursor, limit);

        if (chunk) {
          (direction === BEFORE ? prepend : append)(chunk);
        }
      } finally {
        revealingRef.current[direction] = false;
        setPending((state) => ({ ...state, [direction]: false }));
      }

      // Keep one chunk ahead of wherever the user just got to.
      prefetch(direction);
    },
    [request, prepend, append, prefetch, limit]
  );

  const loadBefore = useCallback(() => reveal(BEFORE), [reveal]);
  const loadAfter = useCallback(() => reveal(AFTER), [reveal]);

  /** Drops the window and reloads it at the end of the log, abandoning anything in flight. */
  const jumpToTail = useCallback(async () => {
    sessionRef.current += 1;
    abortRef.current?.abort();
    abortRef.current = new AbortController();
    inFlightRef.current.clear();
    cacheRef.current.clear();
    revealingRef.current = { [BEFORE]: false, [AFTER]: false };
    setPending({ before: false, after: false });
    commit(() => emptyView());

    const chunk = await request(TAIL, null, limit);

    if (!chunk) {
      return;
    }

    commit((current) => ({
      ...current,
      lines: chunk.lines,
      start: chunk.start,
      end: chunk.end,
      size: chunk.size,
      atStart: chunk.atStart,
      atEnd: chunk.atEnd,
      isEnded: chunk.isEnded,
      ready: true,
      seq: current.seq + 1,
      topDelta: 0,
    }));

    prefetch(BEFORE);
  }, [request, commit, prefetch, limit]);

  // A new execution is a new session - drop the old window, cache and requests.
  useEffect(() => {
    if (!uuid) {
      sessionRef.current += 1;
      abortRef.current?.abort();
      commit(() => emptyView());

      return undefined;
    }

    jumpToTail();

    return () => {
      sessionRef.current += 1;
      abortRef.current?.abort();
    };
    // jumpToTail is stable for a given uuid/baseUrl pair - it must not restart the session.
  }, [uuid, baseUrl]);

  // Live tail. While following we pull the new lines, otherwise we only ask for the counters, so
  // reading older output is never interrupted by the window moving under the viewport.
  useEffect(() => {
    if (!uuid || !view.ready || (view.isEnded && view.atEnd)) {
      return undefined;
    }

    let timer;
    let cancelled = false;

    const tick = async () => {
      const current = viewRef.current;
      const chunk = await request(AFTER, current.end, following ? limit : 0);

      if (cancelled) {
        return;
      }

      if (chunk) {
        // The file shrank under us - it was rotated or truncated, so start over at its end.
        if (chunk.size < viewRef.current.end) {
          jumpToTail();

          return;
        }

        if (following) {
          append(chunk);
        } else {
          commit((state) => ({
            ...state,
            size: chunk.size,
            isEnded: chunk.isEnded,
          }));
        }
      }

      timer = setTimeout(tick, POLL_INTERVAL);
    };

    timer = setTimeout(tick, POLL_INTERVAL);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [
    uuid,
    view.ready,
    view.isEnded,
    view.atEnd,
    following,
    limit,
    request,
    append,
    commit,
    jumpToTail,
  ]);

  return {
    ...view,
    error,
    pending,
    hasNewer: view.ready && view.size > view.end,
    loadBefore,
    loadAfter,
    jumpToTail,
  };
};
