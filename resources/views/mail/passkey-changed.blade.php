<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $subject }}</title>
    <style>
        @media only screen and (max-width: 600px) {
            .mail-content {
                width: 100% !important;
            }

            .mail-padding {
                padding: 24px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #fafafa; color: #52525b; font-family: Arial, Helvetica, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; background-color: #fafafa;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <a href="{{ route('home') }}" style="display: inline-block; text-decoration: none;">
                    <img src="{{ $message->embed(public_path('assets/event/dcterra-logo.png')) }}" alt="DCTerra" width="200" height="51" style="display: block; width: 200px; height: auto; max-width: 100%; border: 0;">
                </a>
                <p style="margin: 12px 0 0; color: #52525b; font-size: 14px;">{{ $brandName }}</p>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding: 0 16px;">
                <table class="mail-content" width="570" cellpadding="0" cellspacing="0" role="presentation" style="width: 570px; max-width: 100%; background-color: #ffffff; border: 1px solid #e4e4e7; border-radius: 4px;">
                    <tr>
                        <td class="mail-padding" style="padding: 32px; font-size: 16px; line-height: 1.5; overflow-wrap: anywhere;">
                            <h1 style="margin: 0 0 20px; color: #18181b; font-size: 20px; line-height: 1.4;">{{ $greeting }}</h1>

                            @foreach ($introLines as $line)
                                <p style="margin: 0 0 20px;">{{ $line }}</p>
                            @endforeach

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 28px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $actionUrl }}" target="_blank" rel="noopener" style="display: inline-block; border-top: 10px solid #18181b; border-right: 18px solid #18181b; border-bottom: 10px solid #18181b; border-left: 18px solid #18181b; border-radius: 4px; background-color: #18181b; color: #ffffff; font-size: 14px; text-decoration: none;">{{ $actionText }}</a>
                                    </td>
                                </tr>
                            </table>

                            @foreach ($outroLines as $line)
                                <p style="margin: 0 0 20px;">{{ $line }}</p>
                            @endforeach

                            <p style="margin: 0 0 24px;">{{ $salutation }}<br>{{ $brandName }}</p>

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-top: 1px solid #e4e4e7;">
                                <tr>
                                    <td style="padding-top: 24px;">
                                        <p style="margin: 0; font-size: 13px; line-height: 1.5;">Werkt de knop "{{ $actionText }}" niet? Kopieer en plak deze link in je browser:<br><a href="{{ $actionUrl }}" style="color: #18181b; word-break: break-all;">{{ $displayableActionUrl }}</a></p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding: 28px 16px;">
                <p style="margin: 0; color: #71717a; font-size: 12px; line-height: 1.5;">© {{ now()->year }} {{ $brandName }}. Alle rechten voorbehouden.</p>
            </td>
        </tr>
    </table>
</body>
</html>
