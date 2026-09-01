@php
    use App\Support\Legacy\ParticipantTableRenderer;
    use App\Support\Legacy\StatusHelper;
@endphp

@forelse ($participants as $p)
    @php
        $statusRaw = strtolower(trim((string) ($p['statrec'] ?? $p['status'] ?? '')));
        $statusTextRaw = strtolower(trim((string) ($p['status_text'] ?? $p['q_status'] ?? '')));

        $isFinishedStatus = $is_finished || ParticipantTableRenderer::isFinishedStatus($statusRaw, $statusTextRaw);
        $isNotStartedStatus = ParticipantTableRenderer::isNotStartedStatus($statusRaw, $statusTextRaw);

        $displayProgressPct = (int) ($p['progress_pct'] ?? 0);
        $displayAnsFilled = (int) ($p['ans_filled'] ?? 0);
        $displayAnsTotal = (int) ($p['ans_total'] ?? 0);

        if ($statusRaw === '7' && (int) ($p['ke_suspend'] ?? 0) !== 1 && $displayAnsTotal > 0) {
            $displayAnsFilled = $displayAnsTotal;
            $displayProgressPct = 100;
        }

        $remainingSeconds = max(0, (int) ($p['remaining_seconds'] ?? 0));
        $isTimerActive = ($remainingSeconds > 0 && ! $isFinishedStatus && ! $isNotStartedStatus);
        $timerText = ParticipantTableRenderer::getTimerText($remainingSeconds, $isFinishedStatus, $isNotStartedStatus);

        $sessionLabel = ParticipantTableRenderer::getSessionLabel($p);
        $photoUrl = ParticipantTableRenderer::getParticipantPhotoUrl($p);
    @endphp
    <tr class="participant-row"
        data-rec-id="{{ $p['rec_id'] ?? '' }}"
        data-auth-id="{{ $p['id'] ?? '' }}"
        data-name="{{ $p['name'] ?? '' }}"
        data-status="{{ $statusRaw }}"
        data-statrec="{{ $statusRaw }}"
        data-status-text="{{ $p['status_text'] ?? $p['q_status'] ?? '' }}"
        data-ke-suspend="{{ $p['ke_suspend'] ?? 0 }}"
        data-participant-json="{{ json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
        data-photo-url="{{ $photoUrl }}">
        
        <td class="px-4 py-3 text-center">{{ ! empty($p['is_online']) ? '🟢' : '⚪' }}</td>

        <td class="px-4 py-4">
            <button type="button" onclick="openParticipantDetailModal(this)" class="text-left group">
                @if (empty(trim($p['name'] ?? '')))
                    <div class="text-base font-semibold text-gray-800 uppercase group-hover:text-brand-primary group-hover:underline">{{ $p['id'] ?? '' }}</div>
                    <div class="text-[10px] text-blue-500 mt-1 opacity-0 group-hover:opacity-100 transition">Klik untuk lihat detail</div>
                @else
                    <div class="text-base font-semibold text-gray-800 uppercase group-hover:text-brand-primary group-hover:underline">{{ $p['name'] ?? '' }}</div>
                    <div class="text-base text-gray-500 mt-0.5 font-mono group-hover:text-brand-primary">{{ $p['id'] ?? '' }}</div>
                    <div class="text-[10px] text-blue-500 mt-1 opacity-0 group-hover:opacity-100 transition">Klik untuk lihat detail</div>
                @endif
            </button>
        </td>

        <td class="px-4 py-4 text-center">Part {{ $p['partno'] ?? '' }}</td>
        <td class="px-4 py-4 text-center"><span class="{{ $p['q_color'] ?? '' }}">{{ $p['q_status'] ?? '' }}</span></td>

        <td class="px-4 py-4">
            <div class="flex justify-between text-sm mb-1.5">
                <span class="font-bold text-gray-700">{{ max(0, min(100, $displayProgressPct)) }}%</span>
                <span class="text-gray-500">Soal: <b>{{ $displayAnsFilled }}</b>/{{ $displayAnsTotal }}</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-4 border border-gray-200">
                <div class="bg-brand-primary h-3 rounded-full" style="width: {{ max(0, min(100, $displayProgressPct)) }}%"></div>
            </div>
        </td>

        <td class="px-4 py-3 text-center">
            <span class="timer-countdown timer-wrapper inline-flex items-center justify-center rounded-md bg-blue-50 text-blue-700 font-semibold"
                  data-active="{{ $isTimerActive ? 'true' : 'false' }}"
                  data-finished="{{ $isFinishedStatus ? 'true' : 'false' }}"
                  data-not-started="{{ $isNotStartedStatus ? 'true' : 'false' }}"
                  data-remaining-seconds="{{ $remainingSeconds }}"
                  data-session="{{ $sessionLabel }}">
                @if ($isTimerActive && ! in_array($timerText, ['Selesai', 'Belum Mulai', '-']))
                    <span class="timer-session-label {{ $sessionLabel === 'R' ? 'reading' : 'listening' }}">{{ $sessionLabel }}</span>
                @endif
                <span class="timer-value">{{ $timerText }}</span>
            </span>
        </td>

        <td class="px-4 py-4 text-center">{!! StatusHelper::getStatusBadge($statusRaw, $p['ke_suspend'] ?? '') !!}</td>

        <td class="px-4 py-4 text-center">
            <div class="flex justify-center space-x-1.5">
                <button onclick="controlTimer('play','{{ $p['id'] ?? '' }}')" class="text-green-600 bg-green-50 h-9 w-9 text-sm rounded"><i class="fas fa-play"></i></button>
                <button onclick="controlTimer('pause','{{ $p['id'] ?? '' }}')" class="text-yellow-600 bg-yellow-50 h-9 w-9 text-sm rounded"><i class="fas fa-pause"></i></button>
                <button onclick="controlTimer('stop','{{ $p['id'] ?? '' }}')" class="text-red-500 bg-red-50 h-9 w-9 text-sm rounded"><i class="fas fa-stop"></i></button>
            </div>
        </td>
    </tr>
@empty
    <tr id="emptyRow"><td colspan="8" class="px-6 py-10 text-center text-gray-400 text-sm italic">Belum ada peserta yang masuk ujian.</td></tr>
@endforelse
