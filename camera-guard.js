(function () {
  var originalOpenCamera = window.openCamera;
  var originalSubmitCameraCode = window.submitCameraCode;
  var lastCameraCode = '';
  var lastCameraScanAt = 0;
  var ignoreUntil = 0;

  if (typeof originalOpenCamera !== 'function' || typeof originalSubmitCameraCode !== 'function') return;

  window.openCamera = function () {
    ignoreUntil = Date.now() + 650;
    return originalOpenCamera.apply(this, arguments);
  };

  window.submitCameraCode = function (code) {
    code = String(code || '').trim();
    var now = Date.now();
    if (!code || now < ignoreUntil || (code === lastCameraCode && now - lastCameraScanAt < 5000)) return;
    lastCameraCode = code;
    lastCameraScanAt = now;
    return originalSubmitCameraCode.call(this, code);
  };
}());
