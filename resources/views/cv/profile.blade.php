<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $user->name }} - Resume</title>
    <style>
        @page { margin: 32px 38px; }
        body { color: #111; font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; line-height: 1.45; margin: 0; }
        h1 { font-size: 22px; line-height: 1.2; margin: 0 0 4px; }
        h2 { border-bottom: 1px solid #222; font-size: 12px; letter-spacing: .3px; margin: 18px 0 7px; padding-bottom: 3px; text-transform: uppercase; }
        h3 { font-size: 11px; margin: 0; }
        p { margin: 3px 0; }
        ul { margin: 5px 0 8px 18px; padding: 0; }
        li { margin-bottom: 2px; }
        .contact { margin-bottom: 8px; }
        .job-title { font-size: 12px; font-weight: bold; margin-bottom: 6px; }
        .entry { margin-bottom: 10px; page-break-inside: avoid; }
        .meta { color: #333; }
        .skills { line-height: 1.7; }
    </style>
</head>
<body>
    @php($basic = $profile->basic_info ?? [])

    <header>
        <h1>{{ $user->name }}</h1>
        <div class="job-title">{{ $basic['job_title_en'] ?? ($basic['job_title_ar'] ?? '') }}</div>
        <div class="contact">
            {{ collect([$user->email, $user->phone, $basic['city'] ?? null, $basic['country'] ?? null])->filter()->implode(' | ') }}
        </div>
    </header>

    @if(! empty($basic['summary_en']) || ! empty($basic['summary_ar']))
        <section>
            <h2>Professional Summary</h2>
            <p>{{ $basic['summary_en'] ?? $basic['summary_ar'] }}</p>
        </section>
    @endif

    @if(! empty($profile->skills))
        <section>
            <h2>Skills</h2>
            <p class="skills">{{ implode(' | ', $profile->skills) }}</p>
        </section>
    @endif

    @if(! empty($profile->experience))
        <section>
            <h2>Work Experience</h2>
            @foreach($profile->experience as $item)
                <div class="entry">
                    <h3>{{ $item['title'] ?? '' }}@if(! empty($item['company'])) — {{ $item['company'] }}@endif</h3>
                    <p class="meta">
                        {{ collect([$item['start_date'] ?? null, $item['end_date'] ?? null])->filter()->implode(' - ') }}
                        @if(empty($item['start_date']) && isset($item['years'])){{ $item['years'] }} years @endif
                    </p>
                    @if(! empty($item['description']))<p>{{ $item['description'] }}</p>@endif
                    @if(! empty($item['achievements']))
                        <ul>
                            @foreach($item['achievements'] as $achievement)<li>{{ $achievement }}</li>@endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </section>
    @endif

    @if(! empty($profile->education))
        <section>
            <h2>Education</h2>
            @foreach($profile->education as $item)
                <div class="entry">
                    <h3>{{ $item['degree'] ?? '' }}@if(! empty($item['field'])) — {{ $item['field'] }}@endif</h3>
                    <p>{{ collect([$item['institution'] ?? null, $item['year'] ?? null])->filter()->implode(' | ') }}</p>
                </div>
            @endforeach
        </section>
    @endif

    @if(! empty($profile->languages))
        <section>
            <h2>Languages</h2>
            <p>{{ implode(' | ', $profile->languages) }}</p>
        </section>
    @endif
</body>
</html>
