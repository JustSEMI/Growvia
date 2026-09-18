@props(['activities'])

<ul {{ $attributes->class(['activity-list']) }} aria-label="Aktivitas aplikasi terbaru">
    @forelse($activities as $activity)
        <li class="activity-empty">
            <span class="icon-tile icon-tile--neutral"><x-icon name="clock" /></span>
            <div>
                <strong>{{ \App\Models\ActivityLog::actionLabels()[$activity->action] ?? 'Aktivitas aplikasi' }}</strong>
                <p>{{ $activity->actor_name }}@if($activity->subject_name) · Akun terkait: {{ $activity->subject_name }}@endif</p>
                <p><time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->copy()->timezone(config('app.timezone'))->locale('id')->translatedFormat('d F Y, H.i.s') }} {{ config('app.timezone') }}</time></p>
            </div>
        </li>
    @empty
        <li class="activity-empty">
            <span class="icon-tile icon-tile--neutral"><x-icon name="clock" /></span>
            <div><strong>Belum ada aktivitas aplikasi.</strong><p>Catatan akun, sesi, dan pengaturan akan muncul di sini.</p></div>
        </li>
    @endforelse
</ul>
