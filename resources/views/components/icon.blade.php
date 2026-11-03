@props(['name' => 'grid'])

<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
    @switch($name)
        @case('leaf')
            <path d="M20.6 3.4c-6.6-.7-11 1-13.1 4.3-1.6 2.5-1.3 5.5.4 7.5l6.3-6.3 1.4 1.4-6.3 6.3c2.1 1.8 5.2 1.9 7.6.2 3.2-2.2 4.7-6.7 3.7-13.4ZM4.7 20.7l5-5-1.4-1.4-5 5 1.4 1.4Z" />
            @break
        @case('grid')
            <rect x="3" y="3" width="8" height="8" rx="2" /><rect x="13" y="3" width="8" height="8" rx="2" /><rect x="3" y="13" width="8" height="8" rx="2" /><rect x="13" y="13" width="8" height="8" rx="2" />
            @break
        @case('sun')
            <circle cx="12" cy="12" r="4.5" /><path d="M11 1h2v4h-2zM11 19h2v4h-2zM1 11h4v2H1zM19 11h4v2h-4zM3.5 4.9l1.4-1.4 2.8 2.8-1.4 1.4zM16.3 17.7l1.4-1.4 2.8 2.8-1.4 1.4zM3.5 19.1l2.8-2.8 1.4 1.4-2.8 2.8zM16.3 6.3l2.8-2.8 1.4 1.4-2.8 2.8z" />
            @break
        @case('moon')
            <path d="M20.7 14.3A8.6 8.6 0 0 1 9.7 3.3a9.5 9.5 0 1 0 11 11Z" />
            @break
        @case('display')
            <path d="M3 3h18v14h-8v2h5v2H6v-2h5v-2H3V3Zm2 2v10h14V5H5Z" />
            @break
        @case('chevron-down')
            <path d="m5.3 8.3 6.7 6.6 6.7-6.6 1.4 1.4-8.1 8-8.1-8 1.4-1.4Z" />
            @break
        @case('drop')
            <path d="M12 2s-7 7.5-7 12a7 7 0 0 0 14 0c0-4.5-7-12-7-12Zm-4 12h2a2 2 0 0 0 2 2v2a4 4 0 0 1-4-4Z" />
            @break
        @case('thermometer')
            <path d="M8 5a4 4 0 0 1 8 0v8.5a6 6 0 1 1-8 0V5Zm4-2a2 2 0 0 0-2 2v9.5l-.5.3a4 4 0 1 0 5 0l-.5-.3V5a2 2 0 0 0-2-2Zm-1 4h2v8.2a2 2 0 1 1-2 0V7Z" />
            @break
        @case('tank')
            <path d="M5 3h14v18H5V3Zm2 2v7h10V5H7Zm2 9v2h6v-2H9Z" />
            @break
        @case('power')
            <path d="M11 2h2v10h-2V2ZM6.4 4.4l1.4 1.4a8 8 0 1 0 8.4 0l1.4-1.4a10 10 0 1 1-11.2 0Z" />
            @break
        @case('users')
            <circle cx="9" cy="7" r="4" /><path d="M1 21v-3a7 7 0 0 1 14 0v3H1ZM15 3a4 4 0 0 1 0 8V9a2 2 0 0 0 0-4V3Zm2 10a6 6 0 0 1 6 6v2h-6v-2h4a4 4 0 0 0-4-4v-2Z" />
            @break
        @case('settings')
            <path d="m10 2-.8 3.2-2.8-1.6L3.6 6.4l1.6 2.8L2 10v4l3.2.8-1.6 2.8 2.8 2.8 2.8-1.6L10 22h4l.8-3.2 2.8 1.6 2.8-2.8-1.6-2.8L22 14v-4l-3.2-.8 1.6-2.8-2.8-2.8-2.8 1.6L14 2h-4Zm2 6a4 4 0 1 1 0 8 4 4 0 0 1 0-8Z" />
            @break
        @case('book')
            <path d="M3 3h6a4 4 0 0 1 3 1.4A4 4 0 0 1 15 3h6v17h-6a3 3 0 0 0-3 2 3 3 0 0 0-3-2H3V3Zm2 2v13h4c.7 0 1.4.1 2 .4V7a2 2 0 0 0-2-2H5Zm10 0a2 2 0 0 0-2 2v11.4c.6-.3 1.3-.4 2-.4h4V5h-4Z" />
            @break
        @case('arrow')
            <path d="m13.3 5.3 1.4-1.4L22.8 12l-8.1 8.1-1.4-1.4L19 13H2v-2h17l-5.7-5.7Z" />
            @break
        @case('arrow-up')
            <path d="M6 4h14v14h-2V7.4L5.4 20 4 18.6 16.6 6H6V4Z" />
            @break
        @case('menu')
            <path d="M3 5h18v3H3zM3 11h18v3H3zM3 17h18v3H3z" />
            @break
        @case('close')
            <path d="m6.4 5 5.6 5.6L17.6 5 19 6.4 13.4 12l5.6 5.6-1.4 1.4-5.6-5.6L6.4 19 5 17.6l5.6-5.6L5 6.4 6.4 5Z" />
            @break
        @case('refresh')
            <path d="M19 3v4.2A9 9 0 1 0 21 15h-2.2a7 7 0 1 1-1.7-6H13v2h8V3h-2Z" />
            @break
        @case('logout')
            <path d="M3 3h9v2H5v14h7v2H3V3Zm12.4 3.6L21.8 13l-6.4 6.4-1.4-1.4 4-4H9v-2h9l-4-4 1.4-1.4Z" />
            @break
        @case('shield')
            <path d="m12 2 9 4v6c0 5.5-9 10-9 10S3 17.5 3 12V6l9-4Zm4.3 6.3L11 13.6l-2.3-2.3-1.4 1.4L11 16.4l6.7-6.7-1.4-1.4Z" />
            @break
        @case('clock')
            <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1 4h2v5.6l4.2 2.5-1 1.8-5.2-3.1V6Z" />
            @break
        @case('chart')
            <path d="M3 3h2v16h16v2H3V3Zm4 9 4-4 4 3 4-6 1.7 1.1-5.2 7.7-4.3-3.2-2.8 2.8L7 12Z" />
            @break
        @case('connection')
            <path d="M12 3a15 15 0 0 1 10.6 4.4L21.2 9a13 13 0 0 0-18.4 0L1.4 7.4A15 15 0 0 1 12 3Zm0 6a9 9 0 0 1 6.4 2.6L17 13a7 7 0 0 0-10 0l-1.4-1.4A9 9 0 0 1 12 9Zm0 6a3 3 0 1 1 0 6 3 3 0 0 1 0-6Z" />
            @break
        @case('plus')
            <path d="M11 4h2v7h7v2h-7v7h-2v-7H4v-2h7V4Z" />
            @break
        @case('edit')
            <path d="m16.7 2.3 5 5-2.4 2.4-5-5 2.4-2.4ZM3 16 12.9 6.1l5 5L8 21H3v-5Z" />
            @break
        @case('trash')
            <path d="M8 2h8l1 3h4v2H3V5h4l1-3Zm-3 7h14l-1 13H6L5 9Zm4 2v8h2v-8H9Zm4 0v8h2v-8h-2Z" />
            @break
        @case('eye')
            <path d="M12 4c6 0 10 8 10 8s-4 8-10 8S2 12 2 12s4-8 10-8Zm0 3a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6Z" />
            @break
        @case('info')
            <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1 4h2v2h-2V6Zm0 4h2v8h-2v-8Z" />
            @break
        @case('check')
            <path d="m20.3 5.3 1.4 1.4L9 19.4l-6.7-6.7 1.4-1.4L9 16.6 20.3 5.3Z" />
            @break
        @case('mail')
            <path d="M4 6h16a2 2 0 0 1 2 2v.4l-10 6-10-6V6a2 2 0 0 1 2-2Zm16 12H4a2 2 0 0 1-2-2V9.4l9.4 5.6a2 2 0 0 0 2.2 0L22 9.4V16a2 2 0 0 1-2 2Z" />
            @break
        @case('lock')
            <path d="M7 10V7a5 5 0 0 1 10 0v3h1a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h1Zm2 0h6V7a3 3 0 0 0-6 0v3Z" />
            @break
        @default
            <circle cx="12" cy="12" r="8" />
    @endswitch
</svg>
