<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Welcome to Lumi</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; -webkit-text-size-adjust:100%;">
    <!-- Preheader (hidden preview text) -->
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
        Your Lumi account is ready &mdash; set your password to get started.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7;">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">

                    <!-- Logo -->
                    <tr>
                        <td align="center" style="padding-bottom:24px;">
                            <span style="font-family:Arial, Helvetica, sans-serif; font-size:26px; font-weight:bold; color:#18181b; letter-spacing:-0.5px;">lumi<span style="color:#a78bfa;">.</span></span>
                        </td>
                    </tr>

                    <!-- Card -->
                    <tr>
                        <td style="background-color:#ffffff; border-radius:12px; padding:40px 40px 32px 40px; border:1px solid #e4e4e7;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-family:Arial, Helvetica, sans-serif; font-size:20px; font-weight:bold; color:#18181b; padding-bottom:12px;">
                                        Welcome{{ $user->name ? ', '.$user->name : '' }} 👋
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#3f3f46; padding-bottom:24px;">
                                        An account has been created for you on <strong>Lumi</strong>. Use the temporary credentials below, then set your own password to activate your account.
                                    </td>
                                </tr>

                                <!-- Credentials box -->
                                <tr>
                                    <td style="padding-bottom:28px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafafa; border:1px solid #e4e4e7; border-radius:8px;">
                                            <tr>
                                                <td style="padding:16px 20px 6px 20px; font-family:Arial, Helvetica, sans-serif; font-size:12px; color:#71717a; text-transform:uppercase; letter-spacing:0.5px;">
                                                    Email
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:0 20px 14px 20px; font-family:'Courier New', Courier, monospace; font-size:14px; color:#18181b; font-weight:bold;">
                                                    {{ $user->email }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="border-top:1px solid #e4e4e7; padding:14px 20px 6px 20px; font-family:Arial, Helvetica, sans-serif; font-size:12px; color:#71717a; text-transform:uppercase; letter-spacing:0.5px;">
                                                    Temporary password
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:0 20px 16px 20px; font-family:'Courier New', Courier, monospace; font-size:14px; color:#18181b; font-weight:bold;">
                                                    {{ $temporaryPassword }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- CTA button -->
                                <tr>
                                    <td align="center" style="padding-bottom:28px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center" bgcolor="#18181b" style="border-radius:8px;">
                                                    <a href="{{ $resetUrl }}" target="_blank" rel="noopener"
                                                       style="display:inline-block; padding:14px 36px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:8px;">
                                                        Set your password
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- Fallback link -->
                                <tr>
                                    <td style="font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:20px; color:#71717a; padding-bottom:20px;">
                                        If the button doesn't work, copy and paste this link into your browser:<br>
                                        <a href="{{ $resetUrl }}" target="_blank" rel="noopener" style="color:#7c3aed; word-break:break-all;">{{ $resetUrl }}</a>
                                    </td>
                                </tr>

                                <!-- Expiry note -->
                                <tr>
                                    <td style="border-top:1px solid #e4e4e7; padding-top:20px; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:20px; color:#71717a;">
                                        This link expires after a limited time. If it has expired, ask an administrator to resend your invite.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding-top:24px; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#a1a1aa;">
                            You received this email because an account was created for you on Lumi.<br>
                            If you weren't expecting this, you can safely ignore it.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
