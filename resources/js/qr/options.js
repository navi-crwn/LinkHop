/* The options handed to qr-code-styling, as a pure function so the page and the tests build them the same way.
 * `data` goes through binaryString: the library writes one byte per character, so it needs UTF-8 bytes
 * (without it any non-ASCII text is read back as garbage). */
import { binaryString } from './payloads.js';

export function qrStyleOptions(style, type, size, payload, margin) {
  var hasLogo = !!style.logo;
  return {
    width: size, height: size, type: type,
    data: binaryString(payload),
    margin: margin,
    qrOptions: { errorCorrectionLevel: hasLogo ? 'H' : 'M' },
    dotsOptions: { color: style.fg, type: style.dots },
    backgroundOptions: { color: style.bg },
    cornersSquareOptions: { color: style.fg, type: style.corners },
    cornersDotOptions: { color: style.fg, type: style.corners === 'dot' ? 'dot' : 'square' },
    image: hasLogo ? style.logo : '',
    imageOptions: { hideBackgroundDots: true, imageSize: style.logoSize / 100, margin: 2, crossOrigin: 'anonymous' }
  };
}
