import React, { useEffect, useRef } from 'react';
import { Html5QrcodeScanner } from 'html5-qrcode';

// Minimal React wrapper around ScanApp-style Html5QrcodeScanner UI
export default function ScanAppScanner({ onDetected, onClose, selectedCameraId, onCameraError }) {
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
      }, /* isFormFactorMobile */ true);
    } catch (e) {
      console.error('Failed to initialize ScanAppScanner', e);
      if (onCameraError) onCameraError(e);
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

  // Watch for external camera selection changes and instruct internal scanner to switch
  useEffect(() => {
    const scanner = scannerRef.current;
    if (!scanner || !selectedCameraId) return;
    // html5Qrcode instance is created by Html5QrcodeScanner and attached as html5Qrcode
    const html5Qrcode = scanner.html5Qrcode;
    if (!html5Qrcode) return;

    (async () => {
      try {
        // stop current scanning if any
        try { await html5Qrcode.stop(); } catch (e) {}
        await html5Qrcode.start(
          selectedCameraId,
          { fps: 10, qrbox: 250 },
          (decoded) => { if (onDetected) onDetected(decoded); },
          (err) => {}
        );
        console.log('ScanAppScanner switched to camera', selectedCameraId);
      } catch (err) {
        console.error('ScanAppScanner failed to switch camera', selectedCameraId, err);
        if (onCameraError) onCameraError(err);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedCameraId]);

  return (
    <div id={containerIdRef.current + '-wrapper'} style={{ width: '100%' }} />
  );
}
