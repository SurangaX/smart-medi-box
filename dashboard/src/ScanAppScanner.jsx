import React, { useEffect, useRef } from 'react';
import { Html5QrcodeScanner } from 'html5-qrcode';

// Minimal React wrapper around ScanApp-style Html5QrcodeScanner UI
export default function ScanAppScanner({ onDetected, onClose, selectedCameraId, onCameraError }) {
  const containerIdRef = useRef('scanapp-reader-' + Date.now());
  const scannerRef = useRef(null);

  const ensureContainerReady = async (id) => {
    const start = Date.now();
    let el = document.getElementById(id);
    while ((!el || el.clientWidth === 0) && Date.now() - start < 2000) {
      if (el) {
        try { el.style.width = el.style.width || '320px'; el.style.height = el.style.height || '240px'; } catch(e){}
      }
      await new Promise(r => setTimeout(r, 100));
      el = document.getElementById(id);
    }
    return document.getElementById(id);
  };

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

      // If mobile and an explicit camera wasn't requested, prefer back camera
      const isMobile = typeof navigator !== 'undefined' && /Mobi|Android|iPhone|iPad|Mobile/i.test(navigator.userAgent || '');
      if (isMobile && !selectedCameraId) {
        // small delay to allow scanner to initialize
        setTimeout(async () => {
          try {
            await ensureContainerReady(id);
            const html5Qrcode = scanner.html5Qrcode;
            if (html5Qrcode) {
              await html5Qrcode.start(
                { facingMode: { ideal: 'environment' } },
                { fps: 10, qrbox: 250 },
                (decoded) => { if (onDetected) onDetected(decoded); },
                (err) => {}
              );
              console.log('ScanAppScanner started with facingMode=environment');
            }
          } catch (e) {
            console.warn('ScanAppScanner facingMode start failed', e);
          }
        }, 300);
      }
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
        // ensure container has layout before starting to avoid clientWidth null
        try { await ensureContainerReady(containerIdRef.current); } catch (e) { /* ignore */ }
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
