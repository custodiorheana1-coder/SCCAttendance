(function () {
  var button = document.getElementById('cameraScanButton');
  var modal = document.getElementById('cameraModal');
  var video = document.getElementById('cameraPreview');
  var status = document.getElementById('cameraStatus');
  var closeButton = document.getElementById('closeCamera');
  var cancelButton = document.getElementById('cancelCamera');
  var step1 = document.getElementById('step1Card');
  var formCard = document.getElementById('formCard');
  var reader = null;
  var stream = null;
  var busy = false;
  var scanInput = document.getElementById('qrHidden');

  if (!button || !modal || !video) return;

  if (scanInput) {
    scanInput.addEventListener('input', function (event) { event.stopImmediatePropagation(); }, true);
    scanInput.addEventListener('keydown', function (event) { event.stopImmediatePropagation(); }, true);
  }

  function syncRegistrationStep() {
    if (!step1 || !formCard) return;
    var formVisible = !formCard.classList.contains('hidden');
    step1.classList.toggle('hidden', formVisible);
  }

  syncRegistrationStep();
  if (formCard && window.MutationObserver) {
    new MutationObserver(syncRegistrationStep).observe(formCard, { attributes: true, attributeFilter: ['class'] });
  }

  function setStatus(message, error) {
    status.textContent = message;
    status.className = 'mt-3 text-center text-sm ' + (error ? 'text-rose-600' : 'text-slate-600');
  }

  function stopCamera() {
    if (reader) {
      if (typeof reader.reset === 'function') {
        try {
          reader.reset();
        } catch (error) {}
      }
      reader = null;
    }
    if (stream) {
      stream.getTracks().forEach(function (track) { track.stop(); });
      stream = null;
    }
    video.srcObject = null;
  }

  function closeCamera() {
    stopCamera();
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    button.focus();
  }

  function handleResult(result) {
    if (busy || !result || !result.text) return;
    var code = result.text.trim();
    if (!code) return;
    busy = true;
    setStatus('Scan successful. Checking the database...');
    var hidden = document.getElementById('qrHidden');
    hidden.value = code;
    closeCamera();
    hidden.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function startCamera() {
    var hidden = document.getElementById('qrHidden');
    if (hidden) hidden.disabled = false;
    busy = false;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setStatus('Camera access is not supported by this browser.', true);
      return;
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setStatus('Requesting camera access...');
    navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
      .then(function (cameraStream) {
        stream = cameraStream;
        video.srcObject = stream;
        return Promise.resolve();
      })
      .then(function () {
        if (!window.ZXingBrowser) throw new Error('Scanner library unavailable.');
        reader = new ZXingBrowser.BrowserMultiFormatReader();
        setStatus('Scanning QR codes and barcodes...');
        reader.decodeFromVideoElement(video, handleResult);
      })
      .catch(function () {
        stopCamera();
        setStatus('Unable to access the camera. Check browser permission and try again.', true);
      });
  }

  button.addEventListener('click', startCamera);
  closeButton.addEventListener('click', closeCamera);
  cancelButton.addEventListener('click', closeCamera);
  modal.addEventListener('click', function (event) {
    if (event.target === modal) closeCamera();
  });
  window.addEventListener('beforeunload', stopCamera);
})();
