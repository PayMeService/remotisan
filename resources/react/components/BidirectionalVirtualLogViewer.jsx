import React, {
  useCallback,
  useEffect,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import { useLogChunks } from '../hooks/useLogChunks';
import { parseAnsi } from '../utils/ansi';

// Rows never wrap, so every row is exactly this tall and the virtualizer needs no measuring.
const ROW_HEIGHT = 18;
// Rows kept mounted past each edge of the viewport.
const OVERSCAN = 20;
// A sentinel this tall trips the loader while the user is still this far from the edge.
const SENTINEL_RATIO = 0.3;
// Within this many pixels of the bottom counts as watching the live tail.
const FOLLOW_THRESHOLD = 4;

const describeError = (error) => {
  if (error.response?.status === 404) {
    return 'UUID not found or logs not available.';
  }

  if (String(error.response?.data?.message).includes('File does not exist')) {
    return 'Log file not found - the command may not have run, or the log was deleted.';
  }

  return `Could not read the log (${error.response?.status || 'network error'}).`;
};

const Row = React.memo(({ line, top }) => (
  <div
    className="whitespace-pre px-3"
    style={{
      position: 'absolute',
      top,
      height: ROW_HEIGHT,
      lineHeight: `${ROW_HEIGHT}px`,
    }}
  >
    {parseAnsi(line.text).map((segment, index) => (
      <span key={index} style={segment.style}>
        {segment.text}
      </span>
    ))}
  </div>
));

Row.displayName = 'Row';

/**
 * Bidirectional infinite scroll over an execution log.
 *
 * Chunks are addressed by the byte offset of their first line, prefetched on both sides of the
 * loaded window, and dropped from the DOM once they are far away. Prepending preserves the scroll
 * anchor, so loading older output never moves what the user is reading.
 */
const BidirectionalVirtualLogViewer = ({
  activeUuid,
  baseUrl = '',
  setHistoryRefresh,
  linesPerChunk = 200,
  height = 480,
}) => {
  const containerRef = useRef(null);
  const topSentinelRef = useRef(null);
  const bottomSentinelRef = useRef(null);
  const anchorRef = useRef({ seq: 0 });
  const intersectingRef = useRef({ top: false, bottom: false });
  const wasEndedRef = useRef(false);

  const [scrollTop, setScrollTop] = useState(0);
  const [viewportHeight, setViewportHeight] = useState(height);
  const [following, setFollowing] = useState(true);

  const log = useLogChunks({
    baseUrl,
    uuid: activeUuid,
    limit: linesPerChunk,
    following,
  });

  const { lines, seq, topDelta, loadBefore, loadAfter, jumpToTail } = log;
  const totalHeight = lines.length * ROW_HEIGHT;

  const visible = useMemo(() => {
    const first = Math.max(0, Math.floor(scrollTop / ROW_HEIGHT) - OVERSCAN);
    const last = Math.min(
      lines.length,
      Math.ceil((scrollTop + viewportHeight) / ROW_HEIGHT) + OVERSCAN
    );

    return { first, rows: lines.slice(first, last) };
  }, [lines, scrollTop, viewportHeight]);

  // Rows added above the viewport shift everything down by exactly their height. Undoing that
  // shift on the scroll position is what keeps the viewport still while older output loads.
  useLayoutEffect(() => {
    const container = containerRef.current;

    if (!container || anchorRef.current.seq === seq) {
      return;
    }

    anchorRef.current.seq = seq;

    if (topDelta !== 0) {
      container.scrollTop += topDelta * ROW_HEIGHT;
      setScrollTop(container.scrollTop);
    }
  }, [seq, topDelta]);

  // Following means pinning to the bottom as the process writes.
  useLayoutEffect(() => {
    const container = containerRef.current;

    if (!container || !following) {
      return;
    }

    container.scrollTop = container.scrollHeight;
    setScrollTop(container.scrollTop);
  }, [following, totalHeight]);

  useEffect(() => {
    const container = containerRef.current;

    if (!container || typeof ResizeObserver === 'undefined') {
      return undefined;
    }

    const observer = new ResizeObserver(() =>
      setViewportHeight(container.clientHeight)
    );
    observer.observe(container);
    setViewportHeight(container.clientHeight);

    return () => observer.disconnect();
  }, []);

  // Sentinels near both edges drive the loading, rather than arithmetic on scroll events.
  useEffect(() => {
    const container = containerRef.current;

    if (!container || typeof IntersectionObserver === 'undefined') {
      return undefined;
    }

    const observer = new IntersectionObserver(
      (entries) =>
        entries.forEach((entry) => {
          const top = entry.target === topSentinelRef.current;
          intersectingRef.current[top ? 'top' : 'bottom'] =
            entry.isIntersecting;

          if (entry.isIntersecting) {
            (top ? loadBefore : loadAfter)();
          }
        }),
      { root: container, threshold: 0 }
    );

    [topSentinelRef.current, bottomSentinelRef.current].forEach((sentinel) => {
      if (sentinel) {
        observer.observe(sentinel);
      }
    });

    return () => observer.disconnect();
  }, [loadBefore, loadAfter]);

  // A revealed chunk can be short enough to leave the sentinel on screen. No new intersection
  // fires in that case, so the chain is picked up again here.
  useEffect(() => {
    if (intersectingRef.current.top) {
      loadBefore();
    }

    if (intersectingRef.current.bottom) {
      loadAfter();
    }
  }, [seq, loadBefore, loadAfter]);

  const onScroll = useCallback((event) => {
    const el = event.currentTarget;
    setScrollTop(el.scrollTop);
    setFollowing(
      el.scrollHeight - el.scrollTop - el.clientHeight <= FOLLOW_THRESHOLD
    );
  }, []);

  const onJumpToTail = useCallback(() => {
    setFollowing(true);
    jumpToTail();
  }, [jumpToTail]);

  // Tell the history table once, when the execution actually finishes.
  useEffect(() => {
    if (log.isEnded && !wasEndedRef.current && log.ready) {
      wasEndedRef.current = true;
      setHistoryRefresh?.((previous) => previous + 1);
    }

    if (!log.isEnded) {
      wasEndedRef.current = false;
    }
  }, [log.isEnded, log.ready, setHistoryRefresh]);

  const sentinelHeight = Math.max(
    1,
    Math.round(viewportHeight * SENTINEL_RATIO)
  );

  return (
    <div className="p-6 bg-white rounded shadow my-2">
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-2xl font-bold">Logger</h2>
        <div className="flex items-center gap-3 text-sm text-gray-500">
          {activeUuid && (
            <span>
              {lines.length} lines loaded
              {log.atStart ? '' : ' (older output above)'}
            </span>
          )}
          {!log.isEnded && activeUuid && (
            <span className="text-green-600">running</span>
          )}
          {!following && (
            <button
              type="button"
              onClick={onJumpToTail}
              className="px-2 py-1 rounded bg-indigo-600 text-white hover:bg-indigo-500"
            >
              {log.hasNewer && !log.isEnded
                ? 'New output - jump to latest'
                : 'Jump to latest'}
            </button>
          )}
        </div>
      </div>

      <div
        ref={containerRef}
        onScroll={onScroll}
        style={{ height, overflow: 'auto' }}
        className="bg-black text-gray-100 font-mono text-xs rounded relative"
      >
        {!activeUuid && (
          <div className="p-3 text-gray-400">
            Pick an execution to see its output.
          </div>
        )}

        {activeUuid && log.error && (
          <div className="p-3 text-red-400">{describeError(log.error)}</div>
        )}

        <div style={{ height: totalHeight, position: 'relative' }}>
          <div
            ref={topSentinelRef}
            style={{
              position: 'absolute',
              top: 0,
              height: sentinelHeight,
              width: 1,
            }}
          />

          {log.pending.before && (
            <div className="absolute top-0 left-0 right-0 text-center text-gray-400 py-1">
              loading older output...
            </div>
          )}

          {visible.rows.map((line, index) => (
            <Row
              key={line.offset}
              line={line}
              top={(visible.first + index) * ROW_HEIGHT}
            />
          ))}

          {log.pending.after && (
            <div className="absolute bottom-0 left-0 right-0 text-center text-gray-400 py-1">
              loading newer output...
            </div>
          )}

          <div
            ref={bottomSentinelRef}
            style={{
              position: 'absolute',
              bottom: 0,
              height: sentinelHeight,
              width: 1,
            }}
          />
        </div>
      </div>
    </div>
  );
};

export default BidirectionalVirtualLogViewer;
