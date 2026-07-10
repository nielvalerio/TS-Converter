<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TS Converter</title>
    <link rel="icon" type="image/x-icon" href="TS.ico">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <nav class="navbar">
        <img src="TS_LOGO.png" alt="Logo" class="navbar-logo">
        <span class="navbar-title">TS Converter</span>
    </nav>

    <div class="app-shell">
        <!-- LEFT PANEL -->
        <div class="panel left-panel">
            <div class="field">
                <label>Voice Language</label>
                <select id="voiceLanguage">
                    <option value="en">English</option>
                </select>
            </div>

            <div class="field">
                <label>Gender</label>
                <div class="segmented" id="genderSegmented">
                    <button type="button" class="seg-btn active" data-gender="all">All</button>
                    <button type="button" class="seg-btn" data-gender="male">Male</button>
                    <button type="button" class="seg-btn" data-gender="female">Female</button>
                </div>
            </div>

            <div class="field">
                <label>Voice Search</label>
                <input type="text" id="voiceSearch" placeholder="Search by voice name">
            </div>

            <div class="voice-list" id="voiceList">
                <div class="voice-empty">Loading voices...</div>
            </div>

            <div class="field slider-field">
                <div class="slider-label-row">
                    <label><input type="checkbox" id="speedEnable"> Speed</label>
                    <span class="slider-value" id="speedValueDisplay">0%</span>
                </div>
                <input type="range" id="speedRange" min="-50" max="100" step="1" value="0" disabled>
            </div>

            <div class="field slider-field">
                <div class="slider-label-row">
                    <label><input type="checkbox" id="pitchEnable"> Pitch</label>
                    <span class="slider-value" id="pitchValueDisplay">0%</span>
                </div>
                <input type="range" id="pitchRange" min="-100" max="100" step="1" value="0" disabled>
            </div>

            <div class="field">
                <label>Output Format</label>
                <div class="segmented">
                    <button type="button" class="seg-btn" id="mp3Btn" disabled title="Not supported yet">MP3</button>
                    <button type="button" class="seg-btn active" id="wavBtn">WAV</button>
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL -->
        <div class="panel right-panel">
            <div class="input-header">
                <h1>Text to Speech</h1>
                <div class="input-header-right">
                    <span id="counter">0 characters (0 lines)</span>
                    <button type="button" class="import-btn" id="uploadBtn">Import File</button>
                    <input type="file" id="fileInput" accept=".txt" style="display:none;">
                </div>
            </div>

            <div class="textarea-wrap">
                <div id="highlightLayer" class="highlight-layer" aria-hidden="true"></div>
                <textarea id="textInput" placeholder="Enter text to synthesize..." spellcheck="false"></textarea>
            </div>

            <div class="action-buttons">
                <button type="button" id="previewBtn" class="btn-preview">Preview Audio ▶</button>
                <button type="button" id="downloadBtn" class="btn-generate">Generate Audio </button>
            </div>

            <div class="results-section">
                <h3>Results</h3>
                <div class="results-list" id="resultsList">
                    <div class="results-empty">No generated items yet</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="downloadOverlay">
        <div class="overlay-box">
            <div class="overlay-icon">🎵</div>
            <div class="sound-bars">
                <span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span>
            </div>
            <div class="overlay-title">Generating your audio...</div>
            <div class="overlay-sub">This may take a few seconds</div>
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill"></div>
            </div>
        </div>
    </div>

</body>

<script>
    const synth = window.speechSynthesis;
    const textInput = document.getElementById('textInput');
    const counter = document.getElementById('counter');
    const downloadOverlay = document.getElementById('downloadOverlay');
    const voiceListEl = document.getElementById('voiceList');
    const voiceSearchEl = document.getElementById('voiceSearch');
    const genderSegmented = document.getElementById('genderSegmented');
    const speedEnable = document.getElementById('speedEnable');
    const pitchEnable = document.getElementById('pitchEnable');
    const speedRange = document.getElementById('speedRange');
    const pitchRange = document.getElementById('pitchRange');
    const speedValueDisplay = document.getElementById('speedValueDisplay');
    const pitchValueDisplay = document.getElementById('pitchValueDisplay');
    const previewBtn = document.getElementById('previewBtn');
    const downloadBtn = document.getElementById('downloadBtn');
    const resultsList = document.getElementById('resultsList');
    const highlightLayer = document.getElementById('highlightLayer');

    let voices = [];
    let filteredVoices = [];
    let selectedVoiceIndex = null; // index into `voices`
    let currentGender = 'all';
    let isSpeaking = false;
    let speechToken = 0; // increments each time we start/stop, invalidates stale boundary events

    // ---- Highlight helpers ----
    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function renderHighlight(text, start, end) {
        if (start === null || start === undefined) {
            highlightLayer.innerHTML = escapeHtml(text);
            return;
        }
        highlightLayer.innerHTML =
            escapeHtml(text.slice(0, start)) +
            '<mark>' + escapeHtml(text.slice(start, end)) + '</mark>' +
            escapeHtml(text.slice(end));

        const markEl = highlightLayer.querySelector('mark');
        if (!markEl) return;

        // Position relative to highlightLayer's normal flow (unaffected by its own scroll).
        // highlightLayer shares font/padding with textInput, so this maps 1:1 to the textarea.
        const markTop = markEl.offsetTop;
        const markBottom = markTop + markEl.offsetHeight;

        const viewTop = textInput.scrollTop;
        const viewBottom = viewTop + textInput.clientHeight;

        const isAbove = markTop < viewTop;
        const isBelow = markBottom > viewBottom;

        if (isAbove || isBelow) {
            const maxScroll = textInput.scrollHeight - textInput.clientHeight;
            const target = markTop - (textInput.clientHeight / 2) + (markEl.offsetHeight / 2);
            const clamped = Math.max(0, Math.min(target, maxScroll));

            textInput.scrollTop = clamped;
            highlightLayer.scrollTop = clamped; // keep in sync immediately
        }
    }

    function clearHighlight() {
        renderHighlight(textInput.value, null, null);
    }

    textInput.addEventListener('scroll', () => {
        highlightLayer.scrollTop = textInput.scrollTop;
        highlightLayer.scrollLeft = textInput.scrollLeft;
    });

    // ---- Voice list ----
    function classifyGender(name) {
        const n = name.toLowerCase();
        if (n.includes('david')) return 'male';
        if (n.includes('zira')) return 'female';
        return 'unknown';
    }

    function populateVoices() {
        const allVoices = synth.getVoices();
        const allowedVoices = ['david', 'zira'];
        voices = allVoices.filter(v => allowedVoices.some(name => v.name.toLowerCase().includes(name)));

        if (selectedVoiceIndex === null && voices.length > 0) {
            selectedVoiceIndex = 0;
        }

        renderVoiceList();
    }

    function renderVoiceList() {
        const searchTerm = voiceSearchEl.value.trim().toLowerCase();

        filteredVoices = voices
            .map((v, i) => ({
                voice: v,
                index: i,
                gender: classifyGender(v.name)
            }))
            .filter(item => currentGender === 'all' || item.gender === currentGender)
            .filter(item => !searchTerm || item.voice.name.toLowerCase().includes(searchTerm));

        voiceListEl.innerHTML = '';

        if (filteredVoices.length === 0) {
            voiceListEl.innerHTML = '<div class="voice-empty">No voices found</div>';
            return;
        }

        filteredVoices.forEach(item => {
            const row = document.createElement('div');
            row.className = 'voice-item' + (item.index === selectedVoiceIndex ? ' selected' : '');

            const label = document.createElement('span');
            label.textContent = `${item.voice.name} (${item.voice.lang})`;

            const previewBtnMini = document.createElement('button');
            previewBtnMini.type = 'button';
            previewBtnMini.className = 'preview-mini-btn';
            previewBtnMini.textContent = 'Preview';
            previewBtnMini.addEventListener('click', (e) => {
                e.stopPropagation();
                speakSample(item.voice);
            });

            row.appendChild(label);
            row.appendChild(previewBtnMini);

            row.addEventListener('click', () => {
                selectedVoiceIndex = item.index;
                renderVoiceList();
            });

            voiceListEl.appendChild(row);
        });
    }

    function speakSample(voice) {
        if (synth.speaking) synth.cancel();
        const utterance = new SpeechSynthesisUtterance('Hello, this is a preview of my voice.');
        utterance.voice = voice;
        synth.speak(utterance);
    }

    populateVoices();
    if (synth.onvoiceschanged !== undefined) {
        synth.onvoiceschanged = populateVoices;
    }

    voiceSearchEl.addEventListener('input', renderVoiceList);

    genderSegmented.addEventListener('click', (e) => {
        const btn = e.target.closest('.seg-btn');
        if (!btn) return;
        genderSegmented.querySelectorAll('.seg-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentGender = btn.dataset.gender;
        renderVoiceList();
    });

    // ---- Speed / Pitch enable + value display ----
    function updateSpeedDisplay() {
        speedValueDisplay.textContent = speedRange.value + '%';
    }

    function updatePitchDisplay() {
        pitchValueDisplay.textContent = pitchRange.value + '%';
    }

    speedEnable.addEventListener('change', () => {
        speedRange.disabled = !speedEnable.checked;
    });
    pitchEnable.addEventListener('change', () => {
        pitchRange.disabled = !pitchEnable.checked;
    });
    speedRange.addEventListener('input', updateSpeedDisplay);
    pitchRange.addEventListener('input', updatePitchDisplay);
    updateSpeedDisplay();
    updatePitchDisplay();

    function getRate() {
        if (!speedEnable.checked) return 1;
        return 1 + (parseFloat(speedRange.value) / 100); // -50% -> 0.5, 100% -> 2
    }

    function getPitch() {
        if (!pitchEnable.checked) return 1;
        return 1 + (parseFloat(pitchRange.value) / 100); // -100% -> 0, 100% -> 2
    }

    // ---- Preview Audio (toggles speak/stop, highlights words, auto-scrolls) ----
    previewBtn.addEventListener('click', () => {
        if (isSpeaking) {
            speechToken++; // invalidate any in-flight boundary events
            synth.cancel();
            isSpeaking = false;
            previewBtn.textContent = 'Preview Audio ▶';
            previewBtn.classList.remove('speaking');
            clearHighlight();
            return;
        }
        if (!textInput.value.trim()) return;

        const text = textInput.value;
        const myToken = ++speechToken;

        const utterance = new SpeechSynthesisUtterance(text);
        utterance.voice = voices[selectedVoiceIndex];
        utterance.rate = getRate();
        utterance.pitch = getPitch();

        utterance.onstart = () => {
            if (myToken !== speechToken) return;
            isSpeaking = true;
            previewBtn.textContent = 'Stop ■';
            previewBtn.classList.add('speaking');
        };

        // --- Robust word tracking (handles engines that reset charIndex mid-utterance) ---
        let boundaryOffset = 0;
        let lastCharIndex = 0;

        utterance.onboundary = (event) => {
            if (myToken !== speechToken) return; // stale event from a cancelled utterance
            if (event.name && event.name !== 'word') return;

            let idx = event.charIndex;

            if (idx < lastCharIndex) {
                boundaryOffset += lastCharIndex;
            }
            lastCharIndex = idx;

            const start = Math.min(text.length, idx + boundaryOffset);
            let end = start + (event.charLength || 0);
            if (!event.charLength) {
                const match = text.slice(start).match(/\s/);
                end = match ? start + match.index : text.length;
            }
            end = Math.min(text.length, Math.max(end, start));

            renderHighlight(text, start, end);
        };

        utterance.onend = utterance.onerror = () => {
            if (myToken !== speechToken) return;
            isSpeaking = false;
            previewBtn.textContent = 'Preview Audio ▶';
            previewBtn.classList.remove('speaking');
            clearHighlight();
        };

        synth.speak(utterance);
    });

    // ---- Word/char/line counter ----
    function updateCounter() {
        const text = textInput.value;
        const chars = text.length;
        const lines = text.length ? text.split('\n').length : 0;
        counter.textContent = `${chars} characters (${lines} lines)`;
    }
    textInput.addEventListener('input', () => {
        updateCounter();
        clearHighlight();
    });
    updateCounter();
    clearHighlight();

    // ---- Import File ----
    const uploadBtn = document.getElementById('uploadBtn');
    const fileInput = document.getElementById('fileInput');
    uploadBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (!file) return;
        if (!file.name.toLowerCase().endsWith('.txt')) {
            alert('Please upload a .txt file.');
            fileInput.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
            textInput.value = e.target.result;
            updateCounter();
            clearHighlight();
        };
        reader.onerror = () => alert('Failed to read file.');
        reader.readAsText(file);
        fileInput.value = '';
    });

    // ---- Generate Audio -> tts_generate.php, add to Results list ----
    downloadBtn.addEventListener('click', () => {
        if (!textInput.value.trim()) return;
        const selectedVoice = voices[selectedVoiceIndex];
        const voiceName = selectedVoice ? selectedVoice.name : '';

        downloadOverlay.classList.add('active');
        downloadBtn.disabled = true;

        fetch('tts_generate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'text=' + encodeURIComponent(textInput.value) +
                    '&voice=' + encodeURIComponent(voiceName)
            })
            .then(res => res.json())
            .then(data => {
                downloadOverlay.classList.remove('active');
                downloadBtn.disabled = false;

                if (data.success) {
                    addResult(data.file, voiceName);
                } else {
                    alert(JSON.stringify(data, null, 2));
                }
            })
            .catch(err => {
                downloadOverlay.classList.remove('active');
                downloadBtn.disabled = false;
                alert('Fetch error: ' + err);
            });
    });

    function addResult(fileUrl, voiceName) {
        const emptyMsg = resultsList.querySelector('.results-empty');
        if (emptyMsg) emptyMsg.remove();

        const row = document.createElement('div');
        row.className = 'result-item';

        const name = document.createElement('span');
        name.className = 'result-name';
        name.textContent = voiceName || 'speech.wav';

        const audio = document.createElement('audio');
        audio.controls = true;
        audio.src = fileUrl;

        const link = document.createElement('a');
        link.href = fileUrl;
        link.download = 'speech.wav';
        link.textContent = 'Download';

        row.appendChild(name);
        row.appendChild(audio);
        row.appendChild(link);
        resultsList.prepend(row);
    }
</script>

</html>