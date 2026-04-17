import React, { useEffect, useRef } from 'react';
import { Html5QrcodeScanner } from 'html5-qrcode';

// Minimal React wrapper around ScanApp-style Html5QrcodeScanner UI
export default function ScanAppScanner({ onDetected, onClose }) {
  const containerIdRef = useRef('scanapp-reader-' + Date.now());
  const scannerRef = useRef(null);

  useEffect(() => {
    const id = containerIdRef.current;
    // Create container element
    let el = document.getElementById(id);
    if (!el) {
      el = document.createElement('div');
      el.id = id;
      el.style.width = '100%';
      el.style.textAlign = 'center';
      // append inside a wrapper if possible
      document.getElementById(id + '-wrapper')?.appendChild(el) || document.body.appendChild(el);
    }

    // Create scanner UI
    try {
      const scanner = new Html5QrcodeScanner(id, { fps: 10, qrbox: 250 }, /* verbose */ false);
      scannerRef.current = scanner;
      scanner.render((decodedText) => {
        if (typeof onDetected === 'function') onDetected(decodedText);
      }, (errorMessage, error) => {
        // ignore intermittent scan errors
        // console.debug('scan error', errorMessage, error);
      }, /* isFormFactorMobile */ true);
    } catch (e) {
      console.error('Failed to initialize ScanAppScanner', e);
    }

    return () => {
      // cleanup
      try {
        if (scannerRef.current) {
          scannerRef.current.clear().catch(()=>{});
          scannerRef.current = null;
        }
        const elRem = document.getElementById(id);
        if (elRem && elRem.parentNode) elRem.parentNode.removeChild(elRem);
      } catch (e) {}
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div id={containerIdRef.current + '-wrapper'} style={{ width: '100%' }} />
  );
}
