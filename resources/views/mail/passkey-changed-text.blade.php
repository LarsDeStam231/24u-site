{!! $brandName !!}

{!! $greeting !!}

@foreach ($introLines as $line)
{!! $line !!}

@endforeach
{!! $actionText !!}: {!! $actionUrl !!}

@foreach ($outroLines as $line)
{!! $line !!}

@endforeach
{!! $salutation !!}
{!! $brandName !!}

Werkt de knop "{!! $actionText !!}" niet? Kopieer en plak deze link in je browser:
{!! $actionUrl !!}

© {{ now()->year }} {!! $brandName !!}. Alle rechten voorbehouden.
