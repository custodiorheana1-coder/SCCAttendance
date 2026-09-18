
(function () {
  var config = window.SSC_SCAN_CONFIG || {};
  var input = document.getElementById('qrHidden');
  var frame = document.getElementById('frame');
  var status = document.getElementById('status');
  var result = document.getElementById('result');
  var eventId = Number(config.eventId || 0);
  var timer;
  var lastCode = '';
  var lastScan = 0;
  var requestInFlight = false;
  var resultModalActive = false;
  var audioContext = null;
  var modalSeconds = Math.max(1, Number(config.modalSeconds || 2));
  var audioVolume = Math.max(0.05, Math.min(0.5, Number(config.audioVolume || 0.28)));

  if (!input) return;

  function showModal(data) {
    var existing = document.getElementById('SSCScanModal');
    if (existing) existing.remove();
    var ok = !!data.ok;
    var modal = document.createElement('div');
    modal.id = 'SSCScanModal';
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm';
    if (!ok) {
      var duplicate = data.title === 'ALREADY CHECKED IN';
      modal.innerHTML = '<div class="SSC-failed-modal" role="alert">' +
        '<div class="SSC-failed-sheen"></div><div class="SSC-failed-mark" aria-hidden="true"><svg viewBox="0 0 52 52" focusable="false"><path d="M14 14l24 24M38 14L14 38" /></svg></div>' +
        '<div class="SSC-failed-title"><span>' + (duplicate ? 'ALREADY' : 'SCAN') + '</span><strong>' + (duplicate ? 'CHECKED IN' : 'FAILED!') + '</strong></div>' +
        '<div class="SSC-failed-rule"></div><p class="SSC-failed-message">' + (duplicate ? '<b>You are already recorded as present for this event.</b><br><span>Time In: ' + escapeHtml(data.scan_in || '--') + '</span><br><span>' + escapeHtml(data.message || '') + '</span>' : escapeHtml(data.message || 'Please try again.')) + '</p>' +
        (duplicate && data.student ? '<div class="SSC-success-student"><div><b>Student:</b> ' + escapeHtml(data.student) + '</div><div><b>Student ID:</b> ' + escapeHtml(data.student_id || '--') + '</div></div>' : '') +
        '<div class="SSC-failed-progress"><div class="SSC-modal-progress"></div></div></div>';
    } else {
      modal.innerHTML = '<div class="SSC-success-modal" role="status" aria-live="assertive">' +
      '<div class="SSC-success-sheen"></div><div class="SSC-success-mark" aria-hidden="true"><svg viewBox="0 0 52 52" focusable="false"><path d="M9 27.5l11 11L46 13" /></svg></div>' +
      '<div class="SSC-success-title"><span>' + (data.direction === 'OUT' ? 'SCAN OUT' : 'SCAN IN') + '</span><strong>SUCCESSFUL</strong></div>' +
      '<div class="SSC-success-rule"></div><p class="SSC-success-message">' + (data.direction === 'OUT' ? '<strong>Thank you, ' + escapeHtml(data.student || 'Student') + '!</strong><br><span><b>Time Out:</b> ' + escapeHtml(data.scan_out || '--') + '</span><br><span>Your attendance has been successfully recorded.</span>' : '<strong>Welcome, ' + escapeHtml(data.student || 'Student') + '!</strong><br><span>You are now <b>checked in</b> for this event.</span>') + '</p>' +
      (data.student ? '<div class="SSC-success-student"><div><b>Student:</b> ' + escapeHtml(data.student) + '</div><div><b>Student ID:</b> ' + escapeHtml(data.student_id || '--') + '</div><div><b>Status:</b> ' + escapeHtml(data.status || 'PRESENT') + '</div></div>' : '') +
      '<div class="SSC-success-progress"><div class="SSC-modal-progress"></div></div></div>';
      (data.student ? '<div class="mt-4 rounded-2xl bg-slate-50 p-4 text-left text-sm text-slate-700"><div><b>Student:</b> ' +
      escapeHtml(data.student) + '</div><div class="mt-1"><b>Student ID:</b> ' + escapeHtml(data.student_id || '--') +
      '</div><div class="mt-1"><b>Grade / Section:</b> ' + escapeHtml((data.grade || '--') + ' / ' + (data.section || '--')) +
      '</div><div class="mt-1"><b>Scan type:</b> ' + escapeHtml(data.scan_type || '--') + '</div><div class="mt-1"><b>Status:</b> ' + escapeHtml(data.status || '--') +
      '</div><div class="mt-1"><b>Date:</b> ' + escapeHtml(new Date().toLocaleDateString()) + '</div><div class="mt-1"><b>Time:</b> ' + escapeHtml(data.scan_in || data.scan_out || '--') + '</div></div>' : '') +
      '<div class="mt-6 h-1.5 overflow-hidden rounded-full bg-slate-200"><div class="SSC-modal-progress h-full ' +
      'bg-emerald-500" style="width:100%"></div></div></div></div>';
    }
    document.body.appendChild(modal);
    resultModalActive = true;
    if (ok) speakSuccess(data);
    else speakError(data);
    var progress = modal.querySelector('.SSC-modal-progress');
    requestAnimationFrame(function () { progress.style.width = '0%'; });
    var displaySeconds = ok ? modalSeconds : 1.2;
    progress.style.transition = 'width ' + displaySeconds + 's linear';
    setTimeout(function () {
      if (document.getElementById('SSCScanModal') === modal) {
        modal.remove();
        resultModalActive = false;
        if (!ok) {
          status.textContent = 'Waiting for scan...';
          status.className = 'mt-4 h-8 text-sm text-slate-600';
          frame.classList.remove('shake');
        }
        focusScanner();
      }
    }, displaySeconds * 1000);
  }

  function speakError(data) {
    if (!('speechSynthesis' in window)) return;
    window.speechSynthesis.cancel();
    var speech = new SpeechSynthesisUtterance(data.title === 'ALREADY CHECKED IN'
      ? 'Already checked in. You are already recorded as present for this event. Time in ' + (data.scan_in || '') + '.'
      : 'Scan Failed, the Student ID is not registered.');
    speech.rate = 1;
    speech.volume = 1;
    window.speechSynthesis.speak(speech);
  }

  function speakSuccess(data) {
    if (!('speechSynthesis' in window)) return;
    window.speechSynthesis.cancel();
    var message = data.direction === 'OUT'
      ? 'Scan out successful. Thank you, ' + (data.student || 'Student') + '. Your attendance has been successfully recorded.'
      : 'Scan in successful. Welcome, ' + (data.student || 'Student') + '. You are now checked in for this event.';
    var speech = new SpeechSynthesisUtterance(message);
    speech.rate = 1.1;
    speech.pitch = 1;
    speech.volume = 1;
    window.speechSynthesis.speak(speech);
  }

  function focusScanner() {
    if (!input.disabled && document.activeElement !== input) input.focus();
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>'"]/g, function (character) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character];
    });
  }

  function unlockAudio() {
    var AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;
    if (!audioContext) audioContext = new AudioContext();
    if (audioContext.state === 'suspended') audioContext.resume().catch(function () {});
  }

  function sound(success) {
    unlockAudio();
    if (!audioContext) return;
    var now = audioContext.currentTime;
    var volume = audioContext.createGain();
    volume.gain.setValueAtTime(0.0001, now);
    volume.gain.exponentialRampToValueAtTime(audioVolume, now + 0.02);
    volume.gain.exponentialRampToValueAtTime(0.0001, now + (success ? 0.32 : 0.42));
    volume.connect(audioContext.destination);

    function beep(frequency, start, duration, type) {
      var oscillator = audioContext.createOscillator();
      oscillator.type = type || (success ? 'sine' : 'square');
      oscillator.frequency.setValueAtTime(frequency, now + start);
      oscillator.connect(volume);
      oscillator.start(now + start);
      oscillator.stop(now + start + duration);
    }

    if (success) {
      beep(880, 0, 0.14);
      beep(1175, 0.15, 0.17);
    } else {
      // Descending sawtooth tones give invalid scans a clear warning-buzzer sound.
      beep(240, 0, 0.18, 'sawtooth');
      beep(135, 0.2, 0.22, 'sawtooth');
    }
  }

  function render(data) {
    var ok = !!data.ok;
    status.textContent = data.message || 'Scan complete.';
    status.className = 'mt-4 h-8 text-sm ' + (ok ? 'text-emerald-700' : 'text-rose-600');
    result.className = 'hidden';
    if (data.ok) updateRecentRows(data);
    frame.classList.toggle('exit-mode', data.direction === 'OUT');
    frame.classList.toggle('shake', !ok);
    if (ok) {
      frame.classList.add('pulse-ring');
      setTimeout(function () { frame.classList.remove('pulse-ring'); }, 1300);
    } else {
      setTimeout(function () { frame.classList.remove('shake'); }, 380);
    }
    showModal(data);
    sound(ok);
  }

  function updateRecentRows(data) {
    var rows = document.getElementById('recentRows');
    if (!rows || !data.student) return;
    var name = data.student.split(' ').reverse().join(', ');
    var row = Array.prototype.slice.call(rows.querySelectorAll('tr')).find(function (item) {
      return item.children[0] && item.children[0].textContent.trim() === name;
    });
    if (!row) {
      var empty = rows.querySelector('td[colspan="5"]');
      if (empty) rows.innerHTML = '';
      row = document.createElement('tr');
      row.className = 'border-b border-white/60';
      row.innerHTML = '<td class="px-2 py-2 font-medium"></td><td class="px-2 py-2 text-slate-600"></td><td class="px-2 py-2"></td><td class="px-2 py-2"></td><td class="px-2 py-2"><span class="rounded bg-slate-100 px-2 py-1 text-xs"></span></td>';
      rows.insertBefore(row, rows.firstChild);
    }
    row.children[0].textContent = name;
    row.children[1].textContent = (data.course || '') + ' / ' + (data.grade || '') + ' / ' + (data.section || '');
    row.children[2].textContent = data.scan_in || '--';
    row.children[3].textContent = data.scan_out || '--';
    row.children[4].firstElementChild.textContent = data.status || 'PRESENT';
    rows.insertBefore(row, rows.firstChild);
    while (rows.children.length > 10) rows.removeChild(rows.lastElementChild);
  }

  function send(code) {
    if (!code || !eventId || requestInFlight || resultModalActive || (code === lastCode && Date.now() - lastScan < 3000)) return;
    lastCode = code;
    lastScan = Date.now();
    requestInFlight = true;
    status.textContent = 'Checking attendance...';
    fetch(config.api || 'api/scan.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({code: code, qr: code, event_id: eventId, scan_type: config.scanType || 'usb'})
    }).then(function (response) {
      return response.json();
    }).then(render).catch(function () {
      render({ok: false, title: 'SCAN DENIED', message: 'Unable to reach the attendance server.'});
    }).finally(function () {
      input.value = '';
      requestInFlight = false;
      config.scanType = 'usb';
      focusScanner();
    });
  }

  function submitScan() {
    var code = input.value.trim();
    input.value = '';
    if (code) send(code);
  }

  input.addEventListener('keydown', function (event) {
    unlockAudio();
    if (event.key === 'Enter') {
      event.preventDefault();
      submitScan();
    }
  });
  input.addEventListener('input', function () {
    unlockAudio();
    clearTimeout(timer);
    timer = setTimeout(function () { if (input.value.trim()) submitScan(); }, 220);
  });
  setInterval(focusScanner, 500);
  focusScanner();
})();
