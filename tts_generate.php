<?php
header('Content-Type: application/json');

$text = $_POST['text'] ?? '';
$voiceName = $_POST['voice'] ?? '';

if (empty($text)) {
    echo json_encode(['error' => 'No text provided']);
    exit;
}

$downloadsDir = __DIR__ . '/downloads';
if (!file_exists($downloadsDir)) {
    mkdir($downloadsDir);
}

$filename = 'tts_' . time() . '.wav';
$filepath = $downloadsDir . '/' . $filename;
$textFile = $downloadsDir . '/temp_text.txt';
$scriptFile = $downloadsDir . '/temp_script.ps1';

file_put_contents($textFile, $text);

// Extract a keyword from the browser voice name (e.g. "Zira" from "Microsoft Zira - English (United States)")
$keyword = '';
if (preg_match('/Microsoft\s+(\w+)/i', $voiceName, $matches)) {
    $keyword = $matches[1];
}

$psScript = "Add-Type -AssemblyName System.Speech\n";
$psScript .= "\$synth = New-Object System.Speech.Synthesis.SpeechSynthesizer\n";
$psScript .= "\$text = Get-Content -Path '" . $textFile . "' -Raw\n";

if (!empty($keyword)) {
    $psScript .= "\$voice = \$synth.GetInstalledVoices() | Where-Object { \$_.VoiceInfo.Name -like '*" . $keyword . "*' } | Select-Object -First 1\n";
    $psScript .= "if (\$voice) { \$synth.SelectVoice(\$voice.VoiceInfo.Name) }\n";
}

$psScript .= "\$synth.SetOutputToWaveFile('" . $filepath . "')\n";
$psScript .= "\$synth.Speak(\$text)\n";
$psScript .= "\$synth.Dispose()\n";

file_put_contents($scriptFile, $psScript);

$output = [];
$returnVar = 0;
exec('powershell -ExecutionPolicy Bypass -File "' . $scriptFile . '" 2>&1', $output, $returnVar);

if (file_exists($filepath)) {
    echo json_encode([
        'success' => true,
        'file' => 'downloads/' . $filename,
        'voice_received' => $voiceName,
        'keyword_used' => $keyword
    ]);
} else {
    echo json_encode([
        'error' => 'Failed to generate audio',
        'debug_output' => $output,
        'return_code' => $returnVar,
        'voice_received' => $voiceName
    ]);
}
