// Minimal ANSI SGR renderer. xterm.js used to do this for us, but a terminal emulator cannot
// prepend rows or be virtualized, so the viewer renders its own rows and parses the escapes here.

const FG = {
  30: '#000000',
  31: '#cd3131',
  32: '#0dbc79',
  33: '#e5e510',
  34: '#2472c8',
  35: '#bc3fbc',
  36: '#11a8cd',
  37: '#e5e5e5',
  90: '#666666',
  91: '#f14c4c',
  92: '#23d18b',
  93: '#f5f543',
  94: '#3b8eea',
  95: '#d670d6',
  96: '#29b8db',
  97: '#ffffff',
};

const BG = Object.fromEntries(
  Object.entries(FG).map(([code, color]) => [Number(code) + 10, color])
);

const CUBE = [0, 95, 135, 175, 215, 255];

// The 256 colour palette: 16 base colours, a 6x6x6 cube, then 24 greys.
const ansi256 = (n) => {
  if (n < 16) {
    return FG[n < 8 ? 30 + n : 82 + n];
  }

  if (n < 232) {
    const i = n - 16;
    const r = CUBE[Math.floor(i / 36) % 6];
    const g = CUBE[Math.floor(i / 6) % 6];
    const b = CUBE[i % 6];

    return `rgb(${r},${g},${b})`;
  }

  const grey = 8 + (n - 232) * 10;

  return `rgb(${grey},${grey},${grey})`;
};

// Applies one SGR sequence's codes on top of the running style.
const applySgr = (style, codes) => {
  let next = { ...style };

  for (let i = 0; i < codes.length; i++) {
    const code = codes[i];

    if (code === 0) {
      next = {};
    } else if (code === 1) {
      next.fontWeight = 'bold';
    } else if (code === 2) {
      next.opacity = 0.7;
    } else if (code === 3) {
      next.fontStyle = 'italic';
    } else if (code === 4) {
      next.textDecoration = 'underline';
    } else if (code === 22) {
      delete next.fontWeight;
      delete next.opacity;
    } else if (code === 23) {
      delete next.fontStyle;
    } else if (code === 24) {
      delete next.textDecoration;
    } else if (code === 39) {
      delete next.color;
    } else if (code === 49) {
      delete next.backgroundColor;
    } else if (FG[code]) {
      next.color = FG[code];
    } else if (BG[code]) {
      next.backgroundColor = BG[code];
    } else if (code === 38 || code === 48) {
      // 38;5;n extended colour and 38;2;r;g;b truecolour, and their background twins.
      const key = code === 38 ? 'color' : 'backgroundColor';

      if (codes[i + 1] === 5) {
        next[key] = ansi256(codes[i + 2]);
        i += 2;
      } else if (codes[i + 1] === 2) {
        next[key] = `rgb(${codes[i + 2]},${codes[i + 3]},${codes[i + 4]})`;
        i += 4;
      }
    }
  }

  return next;
};

// SGR sequences carry the styling. Every other escape - cursor moves, OSC titles, charset
// switches - is dropped rather than printed as garbage.
const SGR = '\\x1B\\[([0-9;]*)m';
const OSC = '\\x1B\\][^\\x07\\x1B]*(?:\\x07|\\x1B\\\\)';
const OTHER_CSI = '\\x1B[[()#][0-9;?]*[A-Za-z]';
const LONE_ESCAPE = '\\x1B.';

const escapesPattern = () =>
  new RegExp([SGR, OSC, OTHER_CSI, LONE_ESCAPE].join('|'), 'g');

/**
 * Splits one log line into styled segments.
 *
 * @param   {string}  line
 * @returns {{text: string, style: object}[]}
 */
export const parseAnsi = (line) => {
  // Progress bars redraw themselves with carriage returns - only the last draw is the line.
  const source = line.includes('\r')
    ? line.slice(line.lastIndexOf('\r') + 1)
    : line;
  const escapes = escapesPattern();
  const segments = [];
  let style = {};
  let cursor = 0;
  let match;

  while ((match = escapes.exec(source)) !== null) {
    if (match.index > cursor) {
      segments.push({ text: source.slice(cursor, match.index), style });
    }

    if (match[1] !== undefined) {
      const codes = match[1]
        .split(';')
        .map((code) => (code === '' ? 0 : Number(code)));
      style = applySgr(style, codes);
    }

    cursor = match.index + match[0].length;
  }

  if (cursor < source.length) {
    segments.push({ text: source.slice(cursor), style });
  }

  return segments.length ? segments : [{ text: '', style: {} }];
};

/**
 * Strips escapes without styling, for copying a selection out.
 *
 * @param   {string}  line
 * @returns {string}
 */
export const stripAnsi = (line) => line.replace(escapesPattern(), '');
