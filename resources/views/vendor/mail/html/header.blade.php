@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="https://cdn.asp.events/CLIENT_CloserSt_D86EA381_5056_B739_5482D50A1A831DDD/sites/ht-euro-2024/media/libraries/exhibitors/f744526a-adfa-11f0-95a-06bd0f937899-logo.png" class="logo" alt="DCTerra Logo">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
